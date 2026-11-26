<?php
/**
 * Meetings booked at nfelia.com/book (Admins and Sales). The calendar, the
 * emails and the lead are app/ops/meetings.php.
 *
 *   GET  meetings                     to do (needs a Meet link), coming up, past
 *   GET  meetings/{id}                one meeting
 *   POST meetings/{id}/confirm        save the Google Meet link and email it
 *   POST meetings/{id}/reschedule     ask them to pick a new time (email link)
 *   POST meetings/{id}/cancel         cancel, with a line for them
 *   POST meetings/{id}/outcome        done, or did not come
 *   GET  meetings/types               the kinds of meeting people can book
 *   POST meetings/types               add one
 *   POST meetings/types/{id}          change one (name, length, shown or hidden)
 *   POST meetings/types/{id}/remove   take one off for good (past meetings keep its name)
 *   GET|POST meetings/settings        opening hours, notice, gap, closed dates
 */
class OpsMeetingsController
{
    private PDO $db;
    private array $me;

    public function __construct()
    {
        require_once BASE_PATH . '/app/ops/meetings.php';
        $this->db = get_db();
        $this->me = ops_staff();
    }

    public function index(): void
    {
        $tab = in_array($_GET['show'] ?? '', ['upcoming', 'past'], true) ? $_GET['show'] : 'todo';
        $where = match ($tab) {
            'upcoming' => "status IN ('confirmed', 'reschedule') AND ends_at >= NOW() ORDER BY starts_at",
            'past'     => "(status IN ('cancelled', 'done', 'no_show') OR ends_at < NOW()) ORDER BY starts_at DESC LIMIT 200",
            default    => "status = 'booked' AND ends_at >= NOW() ORDER BY starts_at",
        };
        $rows = $this->db->query("SELECT * FROM meetings WHERE " . $where)->fetchAll();
        $counts = $this->db->query("SELECT SUM(status = 'booked' AND ends_at >= NOW()) AS todo,
                                           SUM(status IN ('confirmed', 'reschedule') AND ends_at >= NOW()) AS upcoming FROM meetings")->fetch();
        ops_render('meetings/index', ['rows' => $rows, 'tab' => $tab, 'counts' => array_map('intval', $counts ?: [])], 'Meetings');
    }

    public function show(string $id): void
    {
        $m = $this->meeting((int)$id);
        $stmt = $this->db->prepare("SELECT a.*, s.full_name FROM ops_audit a LEFT JOIN ops_staff s ON s.id = a.staff_id WHERE a.target_type = 'meeting' AND a.target_id = ? ORDER BY a.id DESC LIMIT 20");
        $stmt->execute([$m['id']]);
        $others = $this->db->prepare("SELECT id, type_name, starts_at, status FROM meetings WHERE email = ? AND id <> ? ORDER BY starts_at DESC LIMIT 5");
        $others->execute([$m['email'], $m['id']]);
        ops_render('meetings/show', ['m' => $m, 'history' => $stmt->fetchAll(), 'others' => $others->fetchAll()], meeting_who($m));
    }

    public function confirm(string $id): void
    {
        $m = $this->meeting((int)$id);
        if (!in_array($m['status'], ['booked', 'confirmed'], true)) {
            ops_flash('error', 'Only a meeting waiting for its link, or already confirmed, can be confirmed.');
            ops_redirect('meetings/' . $m['id']);
        }
        $url = trim((string)($_POST['meet_url'] ?? ''));
        if (!preg_match('~^https://[^\s/]+\.[^\s]+$~i', $url) || mb_strlen($url) > 255) {
            ops_flash('error', 'Paste the whole Meet link, starting with https:// (for example https://meet.google.com/abc-defg-hij).');
            ops_redirect('meetings/' . $m['id'] . '#confirm');
        }
        $this->db->prepare("UPDATE meetings SET meet_url = ?, status = 'confirmed', confirmed_at = NOW(), confirmed_by = ? WHERE id = ?")
           ->execute([$url, $this->me['id'], $m['id']]);
        $again = $m['status'] === 'confirmed';
        $m = meeting_find($this->db, (int)$m['id']);
        meeting_tell($m, 'confirmed');
        meeting_lead_note($this->db, $m, 'Meet link sent for ' . meeting_when($m) . '.', (int)$this->me['id']);
        ops_audit('meeting.confirmed', ($again ? 'Sent a new Meet link to ' : 'Confirmed the meeting with ') . meeting_who($m) . ', ' . meeting_short_when($m), ['type' => 'meeting', 'id' => (int)$m['id']]);
        ops_flash('success', 'Confirmed. ' . $m['email'] . ' gets the Meet link now, and reminders a day and an hour before.');
        ops_redirect('meetings/' . $m['id']);
    }

    public function reschedule(string $id): void
    {
        $m = $this->meeting((int)$id);
        if (!in_array($m['status'], ['booked', 'confirmed', 'reschedule'], true)) {
            ops_flash('error', 'This meeting is closed. Ask them to book again at nfelia.com/book.');
            ops_redirect('meetings/' . $m['id']);
        }
        $note = mb_substr(trim((string)($_POST['note'] ?? '')), 0, 500);
        $token = meeting_move_token($this->db, (int)$m['id']);
        $this->db->prepare("UPDATE meetings SET status = 'reschedule', move_note = ? WHERE id = ?")->execute([$note !== '' ? $note : null, $m['id']]);
        $m = meeting_find($this->db, (int)$m['id']);
        meeting_tell($m, 'reschedule', ['move_url' => APP_URL . '/book/move/' . $token, 'message' => $note]);
        meeting_lead_note($this->db, $m, 'Asked them to pick a new time' . ($note !== '' ? ': ' . $note : '.'), (int)$this->me['id']);
        ops_audit('meeting.reschedule', 'Asked ' . meeting_who($m) . ' to pick a new time' . ($note !== '' ? ' (' . $note . ')' : ''), ['type' => 'meeting', 'id' => (int)$m['id']]);
        ops_flash('success', 'Sent. ' . $m['email'] . ' picks a new time from the email; their current time stays held until then.');
        ops_redirect('meetings/' . $m['id']);
    }

    public function cancel(string $id): void
    {
        $m = $this->meeting((int)$id);
        if (!in_array($m['status'], MEETING_HOLDING, true)) {
            ops_redirect('meetings/' . $m['id']);
        }
        $reason = mb_substr(trim((string)($_POST['reason'] ?? '')), 0, 500);
        if (mb_strlen($reason) < 3) {
            ops_flash('error', 'Write a line for them: why it is cancelled. It goes in their email.');
            ops_redirect('meetings/' . $m['id'] . '#cancel');
        }
        $this->db->prepare("UPDATE meetings SET status = 'cancelled', cancel_reason = ?, move_token_hash = NULL WHERE id = ?")->execute([$reason, $m['id']]);
        $m = meeting_find($this->db, (int)$m['id']);
        meeting_tell($m, 'cancelled', ['message' => $reason]);
        meeting_lead_note($this->db, $m, 'Meeting cancelled (' . meeting_when($m) . '): ' . $reason, (int)$this->me['id']);
        meeting_tell_team($this->db, $m, 'Cancelled: ' . meeting_who($m), $m['type_name'] . ', ' . meeting_when($m) . ".\n\nCancelled by " . $this->me['full_name'] . ': ' . $reason, 'cancelled');
        ops_audit('meeting.cancelled', 'Cancelled the meeting with ' . meeting_who($m) . ': ' . $reason, ['type' => 'meeting', 'id' => (int)$m['id']]);
        ops_flash('success', 'Cancelled. ' . $m['email'] . ' has been told, and the time is free again.');
        ops_redirect('meetings/' . $m['id']);
    }

    public function outcome(string $id): void
    {
        $m = $this->meeting((int)$id);
        $to = (string)($_POST['outcome'] ?? '');
        if (!in_array($to, ['done', 'no_show'], true) || !in_array($m['status'], ['confirmed', 'booked'], true) || meeting_ts($m['starts_at']) > time()) {
            ops_flash('error', 'A meeting can be marked once it has started.');
            ops_redirect('meetings/' . $m['id']);
        }
        $this->db->prepare("UPDATE meetings SET status = ? WHERE id = ?")->execute([$to, $m['id']]);
        meeting_lead_note($this->db, $m, $to === 'done' ? 'Had the meeting ("' . $m['type_name'] . '").' : 'Did not come to the meeting ("' . $m['type_name'] . '").', (int)$this->me['id']);
        ops_audit('meeting.' . $to, ($to === 'done' ? 'Had the meeting with ' : 'No-show: ') . meeting_who($m), ['type' => 'meeting', 'id' => (int)$m['id']]);
        ops_flash('success', $to === 'done' ? 'Marked done.' . ($m['lead_id'] ? ' Set the lead\'s next follow-up while it is fresh.' : '') : 'Marked as did not come.');
        ops_redirect($m['lead_id'] && $to === 'done' ? 'leads/' . $m['lead_id'] . '#follow-up' : 'meetings/' . $m['id']);
    }

    /* ── Types ────────────────────────────────────────────── */

    public function types(): void
    {
        $counts = $this->db->query("SELECT meeting_type_id, COUNT(*) FROM meetings WHERE status IN ('booked', 'confirmed', 'reschedule') AND ends_at >= NOW() GROUP BY meeting_type_id")->fetchAll(PDO::FETCH_KEY_PAIR);
        ops_render('meetings/types', ['types' => meeting_types($this->db), 'coming' => $counts], 'Meeting types');
    }

    public function type_add(): void
    {
        $f = $this->type_fields();
        if ($f['name'] === '') {
            ops_flash('error', 'Give the meeting a name.');
            ops_redirect('meetings/types');
        }
        $sort = (int)$this->db->query("SELECT COALESCE(MAX(sort_order), 0) + 10 FROM meeting_types WHERE deleted_at IS NULL")->fetchColumn();
        $this->db->prepare("INSERT INTO meeting_types (slug, name, description, minutes, is_active, sort_order) VALUES (?, ?, ?, ?, 1, ?)")
           ->execute([meeting_type_slug($this->db, $f['name']), $f['name'], $f['description'], $f['minutes'], $sort]);
        ops_audit('meeting_type.added', 'Added the meeting type "' . $f['name'] . '" (' . $f['minutes'] . ' min)', ['type' => 'meeting_type', 'id' => (int)$this->db->lastInsertId()]);
        ops_flash('success', '"' . $f['name'] . '" is on the booking page now.');
        ops_redirect('meetings/types');
    }

    public function type_edit(string $id): void
    {
        $t = $this->type((int)$id);
        $f = $this->type_fields();
        if ($f['name'] === '') {
            ops_flash('error', 'A meeting type needs a name.');
            ops_redirect('meetings/types');
        }
        $active = !empty($_POST['is_active']) ? 1 : 0;
        $sort = max(-999, min(9999, (int)($_POST['sort_order'] ?? $t['sort_order'])));
        $this->db->prepare("UPDATE meeting_types SET name = ?, description = ?, minutes = ?, is_active = ?, sort_order = ? WHERE id = ?")
           ->execute([$f['name'], $f['description'], $f['minutes'], $active, $sort, $t['id']]);
        ops_audit('meeting_type.edited', 'Changed the meeting type "' . $f['name'] . '"' . ($active ? '' : ' (hidden)'), ['type' => 'meeting_type', 'id' => (int)$t['id']]);
        ops_flash('success', 'Saved.' . ($active ? '' : ' It is hidden from the booking page.'));
        ops_redirect('meetings/types');
    }

    public function type_remove(string $id): void
    {
        $t = $this->type((int)$id);
        $this->db->prepare("UPDATE meeting_types SET deleted_at = NOW(), is_active = 0 WHERE id = ?")->execute([$t['id']]);
        ops_audit('meeting_type.removed', 'Took the meeting type "' . $t['name'] . '" off', ['type' => 'meeting_type', 'id' => (int)$t['id']]);
        ops_flash('success', '"' . $t['name'] . '" is off the list. Meetings already booked with it go ahead.');
        ops_redirect('meetings/types');
    }

    /* ── Settings ─────────────────────────────────────────── */

    public function settings(): void
    {
        ops_render('meetings/settings', ['s' => meeting_settings($this->db), 'narrow' => true], 'Meeting hours');
    }

    public function settings_save(): void
    {
        $hours = [];
        foreach (MEETING_DAYS as $key => $_) {
            $hours[$key] = !empty($_POST['open_' . $key]) ? ['open' => (string)($_POST['from_' . $key] ?? ''), 'close' => (string)($_POST['to_' . $key] ?? '')] : null;
        }
        $closed = preg_split('/[\s,]+/', trim((string)($_POST['closed'] ?? ''))) ?: [];
        $today = meeting_now()->format('Y-m-d');
        $clean = meeting_clean_settings([
            'hours'  => $hours,
            'notice' => (int)($_POST['notice'] ?? 4),
            'ahead'  => (int)($_POST['ahead'] ?? 30),
            'gap'    => (int)($_POST['gap'] ?? 15),
            'step'   => (int)($_POST['step'] ?? 30),
            'closed' => array_filter($closed, fn($d) => $d >= $today),
        ]);
        foreach ($hours as $key => $h) {
            if ($h && !$clean['hours'][$key]) {
                ops_flash('error', MEETING_DAYS[$key] . ': the closing time must be after the opening time.');
                ops_redirect('meetings/settings');
            }
        }
        meeting_save_settings($this->db, $clean, (int)$this->me['id']);
        ops_audit('meeting.settings', 'Changed the meeting hours and rules', ['type' => 'meeting_settings', 'id' => 1, 'data' => $clean]);
        ops_flash('success', 'Saved. The booking page uses these now; meetings already booked keep their times.');
        ops_redirect('meetings/settings');
    }

    /* ── Shared ───────────────────────────────────────────── */

    private function meeting(int $id): array
    {
        $m = meeting_find($this->db, $id);
        if (!$m) ops_not_found();
        return $m;
    }

    private function type(int $id): array
    {
        $stmt = $this->db->prepare("SELECT * FROM meeting_types WHERE id = ? AND deleted_at IS NULL");
        $stmt->execute([$id]);
        $t = $stmt->fetch();
        if (!$t) ops_not_found();
        return $t;
    }

    private function type_fields(): array
    {
        $minutes = (int)($_POST['minutes'] ?? 30);
        return [
            'name'        => mb_substr(trim((string)($_POST['name'] ?? '')), 0, 120),
            'description' => mb_substr(trim((string)($_POST['description'] ?? '')), 0, 255) ?: null,
            'minutes'     => max(10, min(240, $minutes - $minutes % 5)),
        ];
    }
}
