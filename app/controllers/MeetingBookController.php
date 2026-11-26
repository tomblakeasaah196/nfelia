<?php
/**
 * Nfelia — booking a meeting with the Nfelia team (public, nfelia.com/book).
 * The calendar, the emails and the lead are app/ops/meetings.php; Ops looks
 * after each meeting under Sales → Meetings.
 *
 *   GET  /book                   the kinds of meeting
 *   GET  /book/{slug}            pick a day (?date) and a time (?time), then the form
 *   POST /book/{slug}            book it
 *   GET  /book/thanks            what was just booked (this session's)
 *   GET  /book/move/{token}      pick a new time, from the link Ops sent
 *   POST /book/move/{token}      move it there
 *
 * No JavaScript needed: days and times are plain links, so it works on any
 * phone and on a slow network.
 */

require_once BASE_PATH . '/app/ops/meetings.php';

class MeetingBookController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = get_db();
        if (empty($_SESSION['csrf_token'])) {
            csrf_regenerate();
        }
    }

    public function handle(string $action, ?string $param): void
    {
        $post = $_SERVER['REQUEST_METHOD'] === 'POST';
        if ($action === 'index' && !$post) {
            $this->index();
        } elseif ($action === 'thanks' && !$post) {
            $this->thanks();
        } elseif ($action === 'move' && $param !== null) {
            $post ? $this->move_save($param) : $this->move_form($param);
        } elseif (preg_match('/^[a-z0-9-]{1,60}$/', $action) && $param === null) {
            $post ? $this->book($action) : $this->pick($action);
        } else {
            $this->notice('This page does not exist', 'The link may be old or copied wrongly. Pick a meeting from the list.', 404);
        }
    }

    private function index(): void
    {
        $this->render('index', ['types' => meeting_types($this->db, true)], 'Book a meeting with Nfelia');
    }

    private function pick(string $slug): void
    {
        $type = meeting_type_by_slug($this->db, $slug);
        if (!$type) {
            $this->notice('This meeting can\'t be booked', 'It may have been taken off the list. Pick another kind of meeting.', 404);
        }
        $this->render('pick', $this->picker($type, null) + ['type' => $type, 'form_action' => APP_URL . '/book/' . $type['slug'], 'base' => APP_URL . '/book/' . $type['slug']],
                      $type['name'] . ' · Book a meeting with Nfelia');
    }

    private function book(string $slug): void
    {
        $type = meeting_type_by_slug($this->db, $slug);
        if (!$type) {
            $this->notice('This meeting can\'t be booked', 'It may have been taken off the list. Pick another kind of meeting.', 404);
        }
        $back = 'book/' . $type['slug'] . '?' . http_build_query(['date' => (string)($_POST['date'] ?? ''), 'time' => (string)($_POST['time'] ?? '')]);
        $this->guard($back);
        if (trim((string)($_POST['website'] ?? '')) !== '') {   // a field people never see: a robot filled it
            redirect('book');
        }
        $f = [
            'name'          => mb_substr(trim((string)($_POST['name'] ?? '')), 0, 120),
            'email'         => strtolower(trim((string)($_POST['email'] ?? ''))),
            'phone'         => mb_substr(trim((string)($_POST['phone'] ?? '')), 0, 40),
            'business_name' => mb_substr(trim((string)($_POST['business_name'] ?? '')), 0, 160),
            'note'          => mb_substr(trim((string)($_POST['note'] ?? '')), 0, 2000),
        ];
        $_SESSION['meeting_form'] = $f;
        $problem = match (true) {
            mb_strlen($f['name']) < 2                                       => 'Write your name.',
            !filter_var($f['email'], FILTER_VALIDATE_EMAIL)                 => 'Write your email address: your Google Meet link goes there.',
            $f['phone'] === '' || !preg_match('/\d{7,}/', preg_replace('/\D/', '', $f['phone'])) => 'Write your WhatsApp number, in case we need to reach you quickly.',
            default => null,
        };
        if ($problem) {
            flash('error', $problem);
            redirect($back . '#details');
        }
        if (!throttle_allows('meeting_ip', client_ip(), 8, 60) || !throttle_allows('meeting_email', $f['email'], 5, 1440)) {
            flash('error', 'You have booked a few meetings already today. Write to sales@nfelia.com and we\'ll sort it out.');
            redirect($back);
        }
        $f['phone'] = $f['phone'] ?: null;
        $f['business_name'] = $f['business_name'] ?: null;
        $f['note'] = $f['note'] ?: null;
        $m = meeting_book($this->db, $type, (string)($_POST['date'] ?? ''), (string)($_POST['time'] ?? ''), $f);
        if (is_string($m)) {
            flash('error', $m);
            redirect('book/' . $type['slug'] . '?' . http_build_query(['date' => (string)($_POST['date'] ?? '')]));
        }
        unset($_SESSION['meeting_form'], $_SESSION['meeting_moved']);
        $_SESSION['meeting_booked'] = (int)$m['id'];
        redirect('book/thanks');
    }

    private function thanks(): void
    {
        $m = !empty($_SESSION['meeting_booked']) ? meeting_find($this->db, (int)$_SESSION['meeting_booked']) : null;
        if (!$m) {
            redirect('book');
        }
        $this->render('thanks', ['m' => $m, 'moved' => !empty($_SESSION['meeting_moved'])], 'You\'re booked · Nfelia');
    }

    private function move_form(string $token): void
    {
        $m = $this->moving($token);
        $type = ['name' => $m['type_name'], 'minutes' => (int)$m['minutes'], 'description' => null];
        $base = APP_URL . '/book/move/' . $token;
        $this->render('pick', $this->picker($type, (int)$m['id']) + ['type' => $type, 'm' => $m, 'form_action' => $base, 'base' => $base],
                      'Pick a new time · Nfelia');
    }

    private function move_save(string $token): void
    {
        $m = $this->moving($token);
        $this->guard('book/move/' . $token);
        $moved = meeting_move($this->db, $m, (string)($_POST['date'] ?? ''), (string)($_POST['time'] ?? ''));
        if (is_string($moved)) {
            flash('error', $moved);
            redirect('book/move/' . $token . '?' . http_build_query(['date' => (string)($_POST['date'] ?? '')]));
        }
        $_SESSION['meeting_booked'] = (int)$moved['id'];
        $_SESSION['meeting_moved'] = true;
        redirect('book/thanks');
    }

    /* ── Shared ───────────────────────────────────────────── */

    /** The open days, the chosen day's times and the chosen time, from ?date and ?time. */
    private function picker(array $type, ?int $except): array
    {
        $settings = meeting_settings($this->db);
        $days = meeting_open_days($this->db, $settings, (int)$type['minutes'], $except);
        $date = (string)($_GET['date'] ?? '');
        if (!isset($days[$date])) {
            $date = (string)(array_key_first($days) ?? '');
        }
        $times = $date !== '' ? meeting_slots($settings, (int)$type['minutes'], $date, meeting_busy($this->db, $date, $date, $except)) : [];
        $time = meeting_clean_time((string)($_GET['time'] ?? ''));
        return ['days' => $days, 'date' => $date, 'times' => $times, 'time' => in_array($time, $times, true) ? $time : null,
                'form' => $_SESSION['meeting_form'] ?? []];
    }

    /** The meeting a "pick a new time" link is for, or a page saying it has been used. */
    private function moving(string $token): array
    {
        $m = meeting_by_move_token($this->db, $token);
        if (!$m) {
            $this->notice('This link has been used', 'Your meeting already has its time, or the link is old. Check your latest email from Nfelia, or write to sales@nfelia.com.', 410);
        }
        return $m;
    }

    /** The form's token: a page left open too long goes back to where it was, with a word. */
    private function guard(string $back): void
    {
        if (empty($_POST['csrf_token']) || !hash_equals((string)($_SESSION['csrf_token'] ?? ''), (string)$_POST['csrf_token'])) {
            flash('error', 'That page was open a long time. Check the details and try again.');
            redirect($back);
        }
    }

    private function render(string $view, array $vars, string $title): never
    {
        extract($vars, EXTR_SKIP);
        $flash     = get_flash();
        $view_file = BASE_PATH . '/views/book/' . $view . '.php';
        require BASE_PATH . '/views/book/layout.php';
        exit;
    }

    private function notice(string $title, string $body, int $status): never
    {
        $notice = ['title' => $title, 'body' => $body, 'status' => $status, 'action_url' => APP_URL . '/book', 'action_label' => 'See all meetings'];
        require BASE_PATH . '/views/public/notice.php';
        exit;
    }
}
