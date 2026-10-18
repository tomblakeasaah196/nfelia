<?php
/**
 * Leads and the pipeline (Admins and Sales).
 *
 *   GET  leads                      the list, or the board (?view=board); ?q ?stage ?source ?owner ?due ?page
 *   POST leads                      add a lead (someone the team reached out to, met, or who called)
 *   GET  leads/{id}                 one lead: contact, stage, follow-up, notes, what happened
 *   POST leads/{id}                 change their details
 *   POST leads/{id}/stage           move along the pipeline (Lost needs a reason)
 *   POST leads/{id}/owner           who follows it up
 *   POST leads/{id}/follow-up       when to follow up, and about what (or done)
 *   POST leads/{id}/notes           a note (a call, a promise); "I contacted them" marks the contact
 */
class OpsLeadsController
{
    private const PAGE = 40;
    private PDO $db;
    private array $me;

    public function __construct()
    {
        $this->db = get_db();
        $this->me = ops_staff();
    }

    public function index(): void
    {
        $view   = ($_GET['view'] ?? '') === 'board' ? 'board' : 'list';
        $q      = trim((string)($_GET['q'] ?? ''));
        $stage  = isset(LEAD_STAGES[$_GET['stage'] ?? '']) ? $_GET['stage'] : ($view === 'board' ? '' : 'open');
        $source = isset(LEAD_SOURCES[$_GET['source'] ?? '']) ? $_GET['source'] : '';
        $owner  = (string)($_GET['owner'] ?? '');
        $due    = !empty($_GET['due']);
        $page   = max(1, (int)($_GET['page'] ?? 1));

        $where = ['1 = 1'];
        $args  = [];
        if ($q !== '') {
            $like = '%' . $q . '%';
            // A number matches however it was written: "0803 555 0199", "+234 803…"
            $digits = preg_replace('/\D/', '', $q);
            $digits = strlen($digits) >= 4 ? '%' . ltrim($digits, '0') . '%' : null;
            $where[] = "(l.name LIKE ? OR l.business_name LIKE ? OR l.email LIKE ? OR l.phone LIKE ? OR (? IS NOT NULL AND REGEXP_REPLACE(l.phone, '[^0-9]', '') LIKE ?))";
            array_push($args, $like, $like, $like, $like, $digits, $digits);
        }
        if ($stage === 'open') {
            $where[] = "l.stage IN ('new', 'contacted', 'demo', 'trial')";
        } elseif ($stage !== '') {
            $where[] = 'l.stage = ?';
            $args[] = $stage;
        }
        if ($source !== '') { $where[] = 'l.source = ?'; $args[] = $source; }
        if ($owner === 'me')       { $where[] = 'l.owner_id = ?'; $args[] = $this->me['id']; }
        elseif ($owner === 'none') { $where[] = 'l.owner_id IS NULL'; }
        elseif (ctype_digit($owner)) { $where[] = 'l.owner_id = ?'; $args[] = (int)$owner; }
        if ($due) { $where[] = "l.next_follow_up_at <= NOW() + INTERVAL 1 DAY AND l.stage IN ('new', 'contacted', 'demo', 'trial')"; }

        $from = "FROM leads l LEFT JOIN ops_staff s ON s.id = l.owner_id WHERE " . implode(' AND ', $where);
        $select = "SELECT l.*, s.full_name AS owner_name, (l.next_follow_up_at IS NOT NULL AND l.next_follow_up_at <= NOW()) AS overdue ";
        if ($view === 'board') {
            $stmt = $this->db->prepare($select . $from . " ORDER BY l.next_follow_up_at IS NULL, l.next_follow_up_at, l.updated_at DESC LIMIT 600");
            $stmt->execute($args);
            $columns = array_fill_keys(array_keys(LEAD_STAGES), []);
            foreach ($stmt->fetchAll() as $l) $columns[$l['stage']][] = $l;
            $rows = $columns; $total = 0; $pages = 1;
        } else {
            $stmt = $this->db->prepare("SELECT COUNT(*) " . $from);
            $stmt->execute($args);
            $total = (int)$stmt->fetchColumn();
            $pages = max(1, (int)ceil($total / self::PAGE));
            $page  = min($page, $pages);
            $stmt = $this->db->prepare($select . $from . " ORDER BY l.next_follow_up_at IS NULL, l.next_follow_up_at, l.updated_at DESC LIMIT " . self::PAGE . " OFFSET " . (($page - 1) * self::PAGE));
            $stmt->execute($args);
            $rows = $stmt->fetchAll();
        }
        $counts = $this->db->query("SELECT stage, COUNT(*) FROM leads GROUP BY stage")->fetchAll(PDO::FETCH_KEY_PAIR);
        $due_count = (int)$this->db->query("SELECT COUNT(*) FROM leads WHERE next_follow_up_at <= NOW() AND stage IN ('new', 'contacted', 'demo', 'trial')")->fetchColumn();

        ops_render('leads/index', [
            'mode' => $view, 'rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages, 'counts' => $counts, 'due_count' => $due_count,
            'filter' => compact('q', 'stage', 'source', 'owner') + ['due' => $due ? '1' : ''], 'sales' => $this->sales_team(),
        ], 'Leads');
    }

    public function add(): void
    {
        $f = $this->fields();
        if ($f['name'] === '' || ($f['phone'] === null && $f['email'] === null)) {
            ops_flash('error', 'Give at least their name, and a phone number or an email.');
            ops_redirect('leads');
        }
        if ($same = ops_lead_match($this->db, $f['email'], $f['phone'])) {
            ops_flash('warn', ($same['business_name'] ?: $same['name']) . ' is already a lead with that phone or email. Here it is.');
            ops_redirect('leads/' . $same['id']);
        }
        $stage = isset(LEAD_STAGES[$_POST['stage'] ?? '']) && $_POST['stage'] !== 'paying' ? $_POST['stage'] : 'contacted';
        $follow = $this->when((string)($_POST['follow_up'] ?? ''));
        $this->db->prepare("INSERT INTO leads (name, business_name, email, phone, source, stage, owner_id, next_follow_up_at, follow_up_note, last_contact_at, created_by)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, IF(? IN ('contacted', 'demo', 'trial'), NOW(), NULL), ?)")
           ->execute([$f['name'], $f['business_name'], $f['email'], $f['phone'], $f['source'], $stage, $this->me['id'], $follow,
                      mb_substr(trim((string)($_POST['follow_up_note'] ?? '')), 0, 255) ?: null, $stage, $this->me['id']]);
        $id = (int)$this->db->lastInsertId();
        if ($note = trim((string)($_POST['note'] ?? ''))) {
            ops_lead_note($this->db, $id, (int)$this->me['id'], $note);
        }
        ops_audit('lead.added', 'Added ' . ($f['business_name'] ?: $f['name']) . ' as a lead (' . LEAD_SOURCES[$f['source']] . ')', ['type' => 'lead', 'id' => $id]);
        ops_flash('success', 'Lead added' . ($follow ? '. You\'ll be reminded ' . date('D j M, g:ia', strtotime($follow)) . '.' : '.'));
        ops_redirect('leads/' . $id);
    }

    public function show(string $id): void
    {
        $l = $this->lead((int)$id);
        $stmt = $this->db->prepare("SELECT n.*, s.full_name FROM ops_notes n LEFT JOIN ops_staff s ON s.id = n.staff_id WHERE n.subject_type = 'lead' AND n.subject_id = ? ORDER BY n.id DESC");
        $stmt->execute([$l['id']]);
        $notes = $stmt->fetchAll();
        $stmt = $this->db->prepare("SELECT a.*, s.full_name, NULL AS business_name FROM ops_audit a LEFT JOIN ops_staff s ON s.id = a.staff_id WHERE a.target_type = 'lead' AND a.target_id = ? ORDER BY a.id DESC LIMIT 20");
        $stmt->execute([$l['id']]);
        $history = $stmt->fetchAll();
        $client = null;
        if ($l['business_id']) {
            $stmt = $this->db->prepare("SELECT b.id, b.name, s.status AS plan_status, s.trial_ends_at, s.current_period_end, s.grace_ends_at, s.expiry_exempt FROM businesses b LEFT JOIN subscriptions s ON s.business_id = b.id WHERE b.id = ?");
            $stmt->execute([$l['business_id']]);
            $client = $stmt->fetch() ?: null;
        }
        $stmt = $this->db->prepare("SELECT c.name, cs.queued_at, COALESCE(o.status, cs.status) AS status FROM campaign_sends cs JOIN campaigns c ON c.id = cs.campaign_id LEFT JOIN notify_outbox o ON o.id = cs.outbox_id
                                    WHERE cs.lead_id = ? OR (? IS NOT NULL AND cs.email = ?) ORDER BY cs.id DESC LIMIT 10");
        $stmt->execute([$l['id'], $l['email'], $l['email']]);
        ops_render('leads/show', ['l' => $l, 'notes' => $notes, 'history' => $history, 'client' => $client, 'campaigns' => $stmt->fetchAll(), 'sales' => $this->sales_team()],
                   $l['business_name'] ?: $l['name']);
    }

    public function update(string $id): void
    {
        $l = $this->lead((int)$id);
        $f = $this->fields();
        if ($f['name'] === '') {
            ops_flash('error', 'A lead needs a name.');
            ops_redirect('leads/' . $l['id']);
        }
        $this->db->prepare("UPDATE leads SET name = ?, business_name = ?, email = ?, phone = ?, source = ? WHERE id = ?")
           ->execute([$f['name'], $f['business_name'], $f['email'], $f['phone'], $f['source'], $l['id']]);
        ops_audit('lead.edited', 'Changed the details of ' . ($f['business_name'] ?: $f['name']), ['type' => 'lead', 'id' => (int)$l['id']]);
        ops_flash('success', 'Saved.');
        ops_redirect('leads/' . $l['id']);
    }

    public function stage(string $id): void
    {
        $l = $this->lead((int)$id);
        $to = (string)($_POST['stage'] ?? '');
        if (!isset(LEAD_STAGES[$to]) || $to === $l['stage']) {
            ops_redirect(ops_back('leads/' . $l['id']));
        }
        $reason = trim((string)($_POST['reason'] ?? ''));
        if ($to === 'lost' && mb_strlen($reason) < 3) {
            ops_flash('error', 'Say why it is lost (too dear, no phone, went with someone else…): it helps the next campaign.');
            ops_redirect('leads/' . $l['id'] . '#stage');
        }
        $this->db->prepare("UPDATE leads SET stage = ?, stage_changed_at = NOW(), lost_reason = ?, next_follow_up_at = IF(? IN ('paying', 'lost'), NULL, next_follow_up_at),
                                         last_contact_at = IF(? IN ('contacted', 'demo'), NOW(), last_contact_at) WHERE id = ?")
           ->execute([$to, $to === 'lost' ? mb_substr($reason, 0, 255) : null, $to, $to, $l['id']]);
        ops_audit('lead.stage', ($l['business_name'] ?: $l['name']) . ': ' . LEAD_STAGES[$l['stage']]['label'] . ' → ' . LEAD_STAGES[$to]['label'] . ($to === 'lost' ? ' (' . $reason . ')' : ''),
                  ['type' => 'lead', 'id' => (int)$l['id'], 'data' => ['from' => $l['stage'], 'to' => $to]]);
        ops_flash('success', 'Moved to ' . LEAD_STAGES[$to]['label'] . '.');
        ops_redirect(ops_back('leads/' . $l['id']));
    }

    public function owner(string $id): void
    {
        $l = $this->lead((int)$id);
        $to = (int)($_POST['owner_id'] ?? 0);
        $person = $to ? ops_staff_by_id($to) : null;
        if ($to && (!$person || !(int)$person['is_active'] || !ops_can('sales', $person))) {
            ops_redirect('leads/' . $l['id']);
        }
        $this->db->prepare("UPDATE leads SET owner_id = ? WHERE id = ?")->execute([$to ?: null, $l['id']]);
        ops_audit('lead.owner', ($l['business_name'] ?: $l['name']) . ($person ? ' is now ' . $person['full_name'] . '\'s' : ' has nobody'), ['type' => 'lead', 'id' => (int)$l['id']]);
        if ($person && (int)$person['id'] !== (int)$this->me['id']) {
            ops_alert_one($this->db, (int)$person['id'], 'lead.assigned', $this->me['full_name'] . ' gave you a lead', ($l['business_name'] ?: $l['name']) . ($l['phone'] ? ' · ' . $l['phone'] : ''),
                          'leads/' . $l['id'], 'lead-assigned:' . $l['id'] . ':' . time());
        }
        ops_flash('success', $person ? $person['full_name'] . ' follows it up.' : 'Nobody has it now.');
        ops_redirect('leads/' . $l['id']);
    }

    public function follow_up(string $id): void
    {
        $l = $this->lead((int)$id);
        if (!empty($_POST['done'])) {
            $this->db->prepare("UPDATE leads SET next_follow_up_at = NULL, follow_up_note = NULL, last_contact_at = NOW() WHERE id = ?")->execute([$l['id']]);
            ops_audit('lead.followed_up', 'Followed up ' . ($l['business_name'] ?: $l['name']), ['type' => 'lead', 'id' => (int)$l['id']]);
            ops_flash('success', 'Done. Set the next follow-up when there is one.');
            ops_redirect('leads/' . $l['id'] . '#follow-up');
        }
        $when = $this->when((string)($_POST['follow_up'] ?? ''));
        if (!$when) {
            ops_flash('error', 'Choose a day and time.');
            ops_redirect('leads/' . $l['id'] . '#follow-up');
        }
        $note = trim((string)($_POST['follow_up_note'] ?? ''));
        $this->db->prepare("UPDATE leads SET next_follow_up_at = ?, follow_up_note = ?, follow_up_reminded_at = NULL, owner_id = COALESCE(owner_id, ?) WHERE id = ?")
           ->execute([$when, $note !== '' ? mb_substr($note, 0, 255) : null, $this->me['id'], $l['id']]);
        ops_audit('lead.follow_up', 'Set a follow-up for ' . ($l['business_name'] ?: $l['name']) . ': ' . date('D j M, g:ia', strtotime($when)) . ($note !== '' ? ', ' . $note : ''), ['type' => 'lead', 'id' => (int)$l['id']]);
        ops_flash('success', 'You\'ll be reminded ' . date('D j M, g:ia', strtotime($when)) . '.');
        ops_redirect('leads/' . $l['id'] . '#follow-up');
    }

    public function note(string $id): void
    {
        $l = $this->lead((int)$id);
        $body = trim((string)($_POST['body'] ?? ''));
        if ($body !== '') {
            ops_lead_note($this->db, (int)$l['id'], (int)$this->me['id'], $body);
            if (!empty($_POST['contacted'])) {
                $this->db->prepare("UPDATE leads SET last_contact_at = NOW(), stage = IF(stage = 'new', 'contacted', stage), stage_changed_at = IF(stage = 'new', NOW(), stage_changed_at) WHERE id = ?")
                   ->execute([$l['id']]);
            }
            ops_audit('lead.note', 'Wrote a note on ' . ($l['business_name'] ?: $l['name']), ['type' => 'lead', 'id' => (int)$l['id']]);
        }
        ops_redirect('leads/' . $l['id'] . '#notes');
    }

    /* ── Shared ───────────────────────────────────────────── */

    private function lead(int $id): array
    {
        $stmt = $this->db->prepare("SELECT l.*, s.full_name AS owner_name FROM leads l LEFT JOIN ops_staff s ON s.id = l.owner_id WHERE l.id = ?");
        $stmt->execute([$id]);
        $l = $stmt->fetch();
        if (!$l) ops_not_found();
        return $l;
    }

    /** Everyone who can follow up leads. */
    private function sales_team(): array
    {
        return array_values(array_filter($this->db->query("SELECT id, full_name, role FROM ops_staff WHERE is_active = 1 AND password_hash IS NOT NULL ORDER BY full_name")->fetchAll(),
                                         fn($s) => ops_can('sales', $s)));
    }

    private function fields(): array
    {
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $source = (string)($_POST['source'] ?? 'manual');
        return [
            'name'          => mb_substr(trim((string)($_POST['name'] ?? '')), 0, 120),
            'business_name' => mb_substr(trim((string)($_POST['business_name'] ?? '')), 0, 160) ?: null,
            'email'         => filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null,
            'phone'         => $phone !== '' ? mb_substr($phone, 0, 40) : null,
            'source'        => isset(LEAD_SOURCES[$source]) ? $source : 'manual',
        ];
    }

    /** A follow-up time from the form (a datetime-local in Lagos time), or null. */
    private function when(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') return null;
        $d = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $value) ?: DateTimeImmutable::createFromFormat('Y-m-d', $value);
        return $d ? $d->format(strlen($value) === 10 ? 'Y-m-d 09:00:00' : 'Y-m-d H:i:00') : null;
    }
}
