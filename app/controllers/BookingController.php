<?php
/**
 * Nfelia — Bookings (owner side)
 *
 * Bookings people make on the shop (StorefrontController). A request holds
 * its time while the owner checks the deposit; the owner confirms it (it goes
 * on Tasks & Calendar as an appointment) or declines it with a line for the
 * shopper. Later a confirmed booking can be cancelled or marked done.
 *
 *   GET  /bookings                 requests, coming up, past
 *   GET  /bookings/view/{id}       one booking
 *   POST /bookings/confirm/{id}    POST /bookings/decline/{id}
 *   POST /bookings/cancel/{id}     POST /bookings/complete/{id}
 *   GET|POST /bookings/settings    opening hours and booking rules
 */
class BookingController
{
    private $db;
    private $biz_id;

    public function __construct()
    {
        if (!is_logged_in()) {
            redirect('login');
        }
        $this->db = get_db();
        $this->biz_id = (int)auth_business_id();
    }

    public function index()
    {
        $user     = auth_user();
        $business = auth_business();
        $tab      = in_array($_GET['show'] ?? '', ['upcoming', 'past'], true) ? $_GET['show'] : 'requests';

        $counts = [];
        foreach ($this->db->query("
            SELECT
                SUM(status = 'pending') AS requests,
                SUM(status = 'confirmed' AND ends_at >= NOW()) AS upcoming
            FROM bookings WHERE business_id = " . $this->biz_id) as $row) {
            $counts = ['requests' => (int)$row['requests'], 'upcoming' => (int)$row['upcoming']];
        }
        $where = match ($tab) {
            'upcoming' => "status = 'confirmed' AND ends_at >= NOW() ORDER BY starts_at ASC",
            'past'     => "(status IN ('declined', 'cancelled', 'completed') OR (status = 'confirmed' AND ends_at < NOW())) ORDER BY starts_at DESC",
            default    => "status = 'pending' ORDER BY starts_at ASC",
        };
        $stmt = $this->db->prepare("SELECT * FROM bookings WHERE business_id = ? AND " . $where . " LIMIT 200");
        $stmt->execute([$this->biz_id]);
        $bookings = $stmt->fetchAll();

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM products WHERE business_id = ? AND booking_mode <> 'none' AND deleted_at IS NULL");
        $stmt->execute([$this->biz_id]);
        $bookable_count = (int)$stmt->fetchColumn();

        $title     = 'Bookings';
        $view_file = BASE_PATH . '/views/bookings/index.php';
        require_once BASE_PATH . '/views/layouts/app.php';
    }

    public function view($id = null)
    {
        $user     = auth_user();
        $business = auth_business();
        $booking  = $this->find($id);
        if (!$booking) {
            flash('error', 'That booking isn\'t here.');
            redirect('bookings');
        }
        $booking_link = booking_url($business, $booking);
        $customer_wa  = shop_whatsapp_digits(['phone' => $booking['customer_phone']]);

        $title     = 'Booking ' . $booking['booking_number'];
        $view_file = BASE_PATH . '/views/bookings/view.php';
        require_once BASE_PATH . '/views/layouts/app.php';
    }

    /**
     * Confirm a request: it goes on the calendar as an appointment, and the
     * shopper's page says it's confirmed.
     */
    public function confirm($id = null)
    {
        $booking = $this->posted($id, ['pending']);
        $user    = auth_user();

        $start = new DateTimeImmutable((string)$booking['starts_at'], planner_tz());
        $end   = new DateTimeImmutable((string)$booking['ends_at'], planner_tz());
        [$title, $time, $end_time] = match ($booking['kind']) {
            'nights' => ['Check-in: ' . $booking['customer_name'] . ' — ' . $booking['product_name'], $start->format('H:i:s'), null],
            'day'    => [$booking['product_name'] . ' — ' . $booking['customer_name'], null, null],
            default  => [$booking['product_name'] . ' — ' . $booking['customer_name'], $start->format('H:i:s'), $end->format('H:i:s')],
        };
        $notes = 'Booking ' . $booking['booking_number'] . ': ' . booking_when($booking) . '. Phone ' . $booking['customer_phone'] . '.'
               . ($booking['customer_note'] ? "\n" . $booking['customer_note'] : '');

        $this->db->beginTransaction();
        try {
            $remind = $time !== null ? 60 : null;
            $this->db->prepare("
                INSERT INTO tasks (business_id, kind, title, notes, due_date, due_time, end_time, customer_id, remind_before, remind_at, created_by)
                VALUES (?, 'appointment', ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([$this->biz_id, mb_substr($title, 0, 200), $notes, $start->format('Y-m-d'), $time, $end_time,
                         $booking['customer_id'] ?: null, $remind, $remind ? planner_remind_at($start->format('Y-m-d'), $time, $remind) : null,
                         (int)$user['id']]);
            $task_id = (int)$this->db->lastInsertId();
            $confirmed = $this->db->prepare("
                UPDATE bookings SET status = 'confirmed', task_id = ?, decided_by = ?, decided_at = NOW()
                WHERE id = ? AND business_id = ? AND status = 'pending'
            ");
            $confirmed->execute([$task_id, (int)$user['id'], (int)$booking['id'], $this->biz_id]);
            if ($confirmed->rowCount() !== 1) {
                // Someone else answered it a moment ago: no second appointment.
                $this->db->rollBack();
                flash('error', $booking['booking_number'] . ' was already answered. Here it is as it stands.');
                redirect('bookings/view/' . (int)$booking['id']);
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            error_log('Booking confirm failed: ' . $e->getMessage());
            flash('error', 'We couldn\'t confirm that booking. Please try again.');
            redirect('bookings/view/' . (int)$booking['id']);
        }
        $this->tell_shopper($booking, 'confirmed', '');
        flash('success', $booking['booking_number'] . ' confirmed and added to your calendar. Let ' . planner_first_name($booking['customer_name']) . ' know on WhatsApp.');
        redirect('bookings/view/' . (int)$booking['id']);
    }

    public function decline($id = null)
    {
        $booking = $this->posted($id, ['pending']);
        $message = mb_substr(trim((string)($_POST['message'] ?? '')), 0, 300);
        $this->decide($booking, 'declined', $message);
        flash('success', $booking['booking_number'] . ' declined. That time is free again.');
        redirect('bookings/view/' . (int)$booking['id']);
    }

    public function cancel($id = null)
    {
        $booking = $this->posted($id, ['confirmed']);
        $message = mb_substr(trim((string)($_POST['message'] ?? '')), 0, 300);
        $this->decide($booking, 'cancelled', $message);
        if ($booking['task_id']) {
            $this->db->prepare("UPDATE tasks SET deleted_at = NOW() WHERE id = ? AND business_id = ?")->execute([(int)$booking['task_id'], $this->biz_id]);
        }
        flash('success', $booking['booking_number'] . ' cancelled and taken off your calendar.');
        redirect('bookings/view/' . (int)$booking['id']);
    }

    public function complete($id = null)
    {
        $booking = $this->posted($id, ['confirmed']);
        $this->db->prepare("UPDATE bookings SET status = 'completed' WHERE id = ? AND business_id = ? AND status = 'confirmed'")
                 ->execute([(int)$booking['id'], $this->biz_id]);
        if ($booking['task_id']) {
            $this->db->prepare("UPDATE tasks SET done_at = COALESCE(done_at, NOW()), done_by = COALESCE(done_by, ?) WHERE id = ? AND business_id = ?")
                     ->execute([(int)auth_user()['id'], (int)$booking['task_id'], $this->biz_id]);
        }
        flash('success', $booking['booking_number'] . ' marked done.');
        redirect('bookings/view/' . (int)$booking['id']);
    }

    /**
     * Opening hours and booking rules. Shown to shoppers as free times.
     */
    public function settings()
    {
        $user     = auth_user();
        $business = auth_business();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            $hours = [];
            foreach (BOOKING_DAYS as $key => $_) {
                $hours[$key] = !empty($_POST['open_' . $key])
                    ? ['open' => (string)($_POST['from_' . $key] ?? ''), 'close' => (string)($_POST['to_' . $key] ?? '')]
                    : null;
            }
            $clean = booking_clean_settings([
                'hours'    => $hours,
                'notice'   => $_POST['notice'] ?? null,
                'ahead'    => $_POST['ahead'] ?? null,
                'step'     => $_POST['step'] ?? null,
                'at_once'  => $_POST['at_once'] ?? null,
                'checkin'  => $_POST['checkin'] ?? null,
                'checkout' => $_POST['checkout'] ?? null,
            ]);
            $bad = [];
            foreach (BOOKING_DAYS as $key => $day) {
                if ($hours[$key] && !$clean['hours'][$key]) {
                    $bad[] = $day;
                }
            }
            $this->db->prepare("UPDATE businesses SET booking_settings = ? WHERE id = ?")
                     ->execute([json_encode($clean), $this->biz_id]);
            storefront_purge($this->biz_id);   // booking pages show the check-in and check-out times
            flash($bad ? 'error' : 'success', $bad
                ? 'Saved, but ' . implode(' and ', $bad) . ' needs a closing time after its opening time, so it shows as closed.'
                : 'Booking hours saved. Shoppers see the new free times straight away.');
            redirect('bookings/settings');
        }
        $settings  = booking_settings($business);
        $title     = 'Booking hours';
        $view_file = BASE_PATH . '/views/bookings/settings.php';
        require_once BASE_PATH . '/views/layouts/app.php';
    }

    /* ── Helpers ───────────────────────────────────── */

    private function find($id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM bookings WHERE id = ? AND business_id = ?");
        $stmt->execute([(int)$id, $this->biz_id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * The booking a POST is about, if it is in one of $statuses.
     */
    private function posted($id, array $statuses): array
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('bookings');
        }
        csrf_verify();
        $booking = $this->find($id);
        if (!$booking || !in_array($booking['status'], $statuses, true)) {
            flash('error', 'That booking has already been dealt with.');
            redirect($booking ? 'bookings/view/' . (int)$booking['id'] : 'bookings');
        }
        return $booking;
    }

    private function decide(array $booking, string $status, string $message): void
    {
        // Only from the state it was in when the page showed it: two people
        // deciding at once, or a double tap, tell the shopper once.
        $stmt = $this->db->prepare("
            UPDATE bookings SET status = ?, owner_message = ?, decided_by = ?, decided_at = NOW()
            WHERE id = ? AND business_id = ? AND status = ?
        ");
        $stmt->execute([$status, $message !== '' ? $message : null, (int)auth_user()['id'], (int)$booking['id'], $this->biz_id, $booking['status']]);
        if ($stmt->rowCount() === 1) {
            $this->tell_shopper($booking, $status, $message);
        }
    }

    /**
     * Email the shopper when they gave an address, through the outbox (sent
     * after the page has answered, retried if the mail server is down).
     * Their booking page always shows the news.
     */
    private function tell_shopper(array $booking, string $state, string $message): void
    {
        $business = business_with_shop_link(auth_business());
        $url = booking_url($business, $booking);
        if (empty($booking['customer_email']) || !$url) {
            return;
        }
        $symbol = (string)($business['currency_symbol'] ?? '');
        $vars = [
            'state' => $state, 'first_name' => planner_first_name($booking['customer_name']), 'shop_name' => (string)$business['name'],
            'booking_number' => $booking['booking_number'], 'service' => $booking['product_name'], 'when' => booking_when($booking),
            'booking_url' => $url, 'total' => sf_money((float)$booking['total'], $symbol),
            'deposit' => (float)$booking['deposit_due'] > 0 ? sf_money((float)$booking['deposit_due'], $symbol) : '', 'message' => $message,
        ];
        $subject = 'Your booking ' . $booking['booking_number'] . ' with ' . $business['name'];
        notify_email($this->biz_id, (string)$booking['customer_email'], (string)$booking['customer_name'], $subject, 'booking_update', $vars,
                     'booking:' . (int)$booking['id'] . ':' . $state, 'shopper.booking');
    }
}
