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

    /** The shopper's deposit screenshot (private file). */
    public function proof($id = null)
    {
        $booking = $this->find($id);
        send_private_file($booking['payment_proof_path'] ?? null);
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
        // The mail server said their email doesn't exist (app/helpers/bounces.php)
        require_once BASE_PATH . '/app/helpers/bounces.php';
        $email_bounced = bounce_at($this->db, $this->biz_id, $booking['customer_email']);
        $deposit_receipt = null;
        if (!empty($booking['receipt_id'])) {
            $stmt = $this->db->prepare("SELECT id, doc_number FROM receipts WHERE id = ? AND business_id = ?");
            $stmt->execute([(int)$booking['receipt_id'], $this->biz_id]);
            $deposit_receipt = $stmt->fetch() ?: null;
        }

        $title     = 'Booking ' . $booking['booking_number'];
        $view_file = BASE_PATH . '/views/bookings/view.php';
        require_once BASE_PATH . '/views/layouts/app.php';
    }

    /** The customer's right email, after the one on the booking bounced. */
    public function email($id = null)
    {
        $booking = $this->posted($id, ['pending', 'confirmed', 'declined', 'cancelled', 'completed']);
        require_once BASE_PATH . '/app/helpers/bounces.php';
        $email = bounce_new_email($this->db, $this->biz_id, 'bookings', $booking, (string)($_POST['email'] ?? ''));
        flash($email ? 'success' : 'error', $email ? 'Saved. Emails about this booking now go to ' . $email . '.' : 'Type the email address in full, like name@gmail.com.');
        redirect('bookings/view/' . (int)$booking['id'] . '#email');
    }

    /**
     * Confirm a request: it goes on the calendar as an appointment, and the
     * shopper's page says it's confirmed.
     */
    public function confirm($id = null)
    {
        $booking = $this->posted($id, ['pending']);
        $user    = auth_user();

        // A request older than BOOKING_HOLD_HOURS stopped holding its time:
        // someone else may have booked it since
        if (strtotime((string)$booking['created_at']) < time() - BOOKING_HOLD_HOURS * 3600 && booking_clashes($this->db, $booking)) {
            flash('error', 'That time has been booked by someone else since this request came in. Agree another time with '
                . planner_first_name($booking['customer_name']) . ' on WhatsApp, or decline it.');
            redirect('bookings/view/' . (int)$booking['id']);
        }

        $start = new DateTimeImmutable((string)$booking['starts_at'], planner_tz());
        $end   = new DateTimeImmutable((string)$booking['ends_at'], planner_tz());
        [$title, $time, $end_time] = match ($booking['kind']) {
            'nights' => ['Check-in: ' . $booking['customer_name'] . ' — ' . $booking['product_name'], $start->format('H:i:s'), null],
            'day'    => [$booking['product_name'] . ' — ' . $booking['customer_name'], null, null],
            default  => [$booking['product_name'] . ' — ' . $booking['customer_name'], $start->format('H:i:s'), $end->format('H:i:s')],
        };
        $notes = 'Booking ' . $booking['booking_number'] . ': ' . booking_when($booking) . '. Phone ' . $booking['customer_phone'] . '.'
               . ($booking['customer_note'] ? "\n" . $booking['customer_note'] : '');

        // A deposit the owner ticked as received goes in the books as a
        // receipt (M21)
        $deposit = round((float)$booking['deposit_due'], 2);
        $take_deposit = $deposit > 0 && !empty($_POST['deposit_received']);
        $receipt_number = null;

        $this->db->beginTransaction();
        try {
            // Claim the request first: a second tap, or a decline meanwhile,
            // finds it already dealt with (S7)
            $claim = $this->db->prepare("
                UPDATE bookings SET status = 'confirmed', decided_by = ?, decided_at = NOW()
                WHERE id = ? AND business_id = ? AND status = 'pending'
            ");
            $claim->execute([(int)$user['id'], (int)$booking['id'], $this->biz_id]);
            if ($claim->rowCount() !== 1) {
                throw new DomainException($booking['booking_number'] . ' was already answered. Here it is as it stands.');
            }

            $remind = $time !== null ? 60 : null;
            $remind_at = $remind ? planner_remind_at($start->format('Y-m-d'), $time, $remind) : null;
            // A reminder whose time has passed is marked sent: confirming late
            // never sends one after the appointment (P9)
            $now = planner_now()->format('Y-m-d H:i:s');
            $this->db->prepare("
                INSERT INTO tasks (business_id, kind, title, notes, due_date, due_time, end_time, customer_id, remind_before, remind_at, reminded_at, created_by)
                VALUES (?, 'appointment', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([$this->biz_id, mb_substr($title, 0, 200), $notes, $start->format('Y-m-d'), $time, $end_time,
                         $booking['customer_id'] ?: null, $remind, $remind_at, $remind_at !== null && $remind_at <= $now ? $now : null,
                         (int)$user['id']]);
            $task_id = (int)$this->db->lastInsertId();
            $this->db->prepare("UPDATE bookings SET task_id = ? WHERE id = ? AND business_id = ?")
                     ->execute([$task_id, (int)$booking['id'], $this->biz_id]);

            if ($take_deposit) {
                [$receipt_id, $receipt_number] = $this->deposit_receipt($booking, $deposit, (int)$user['id']);
                $this->db->prepare("UPDATE bookings SET receipt_id = ? WHERE id = ? AND business_id = ?")
                         ->execute([$receipt_id, (int)$booking['id'], $this->biz_id]);
            }
            $this->db->commit();
        } catch (DomainException $e) {
            $this->db->rollBack();
            flash('error', $e->getMessage());
            redirect('bookings/view/' . (int)$booking['id']);
        } catch (\Throwable $e) {
            $this->db->rollBack();
            error_log('Booking confirm failed: ' . $e->getMessage());
            flash('error', 'We couldn\'t confirm that booking. Please try again.');
            redirect('bookings/view/' . (int)$booking['id']);
        }
        $this->tell_shopper($booking, 'confirmed', '');
        flash('success', $booking['booking_number'] . ' confirmed and added to your calendar.'
            . ($receipt_number ? ' The deposit is on receipt ' . $receipt_number . '.' : '')
            . ' Let ' . planner_first_name($booking['customer_name']) . ' know on WhatsApp.');
        redirect('bookings/view/' . (int)$booking['id']);
    }

    public function decline($id = null)
    {
        $booking = $this->posted($id, ['pending']);
        $message = mb_substr(trim((string)($_POST['message'] ?? '')), 0, 300);
        $this->decide($booking, 'pending', 'declined', $message);
        flash('success', $booking['booking_number'] . ' declined. That time is free again.');
        redirect('bookings/view/' . (int)$booking['id']);
    }

    public function cancel($id = null)
    {
        $booking = $this->posted($id, ['confirmed']);
        $message = mb_substr(trim((string)($_POST['message'] ?? '')), 0, 300);
        $this->decide($booking, 'confirmed', 'cancelled', $message);
        if ($booking['task_id']) {
            $this->db->prepare("UPDATE tasks SET deleted_at = NOW() WHERE id = ? AND business_id = ?")->execute([(int)$booking['task_id'], $this->biz_id]);
        }
        flash('success', $booking['booking_number'] . ' cancelled and taken off your calendar.');
        redirect('bookings/view/' . (int)$booking['id']);
    }

    public function complete($id = null)
    {
        $booking = $this->posted($id, ['confirmed']);
        $stmt = $this->db->prepare("UPDATE bookings SET status = 'completed' WHERE id = ? AND business_id = ? AND status = 'confirmed'");
        $stmt->execute([(int)$booking['id'], $this->biz_id]);
        if ($stmt->rowCount() !== 1) {
            flash('error', 'That booking has already been dealt with.');
            redirect('bookings/view/' . (int)$booking['id']);
        }
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

    /**
     * Move a booking on from $from only: if someone else moved it first, say
     * so and stop (S7).
     */
    private function decide(array $booking, string $from, string $status, string $message): void
    {
        $stmt = $this->db->prepare("
            UPDATE bookings SET status = ?, owner_message = ?, decided_by = ?, decided_at = NOW()
            WHERE id = ? AND business_id = ? AND status = ?
        ");
        $stmt->execute([$status, $message !== '' ? $message : null, (int)auth_user()['id'], (int)$booking['id'], $this->biz_id, $from]);
        if ($stmt->rowCount() !== 1) {
            flash('error', $booking['booking_number'] . ' was already answered. Here it is as it stands.');
            redirect('bookings/view/' . (int)$booking['id']);
        }
        $this->tell_shopper($booking, $status, $message);
    }

    /**
     * A receipt for the deposit, to the booking's client if they are still
     * here. Returns [receipt id, receipt number].
     */
    private function deposit_receipt(array $booking, float $deposit, int $user_id): array
    {
        $customer_id = null;
        if (!empty($booking['customer_id'])) {
            $stmt = $this->db->prepare("SELECT id FROM customers WHERE id = ? AND business_id = ? AND deleted_at IS NULL");
            $stmt->execute([(int)$booking['customer_id'], $this->biz_id]);
            $customer_id = (int)$stmt->fetchColumn() ?: null;
        }
        $doc_number = generate_doc_number($this->db, $this->biz_id, 'REC');
        $method     = $booking['payment_method'] === 'bank_transfer' ? 'bank_transfer' : 'other';
        $this->db->prepare("
            INSERT INTO receipts (business_id, invoice_id, payment_id, customer_id, user_id, doc_number, issue_date, amount, balance_remaining, method, note, token)
            VALUES (?, NULL, NULL, ?, ?, ?, ?, ?, 0, ?, ?, ?)
        ")->execute([$this->biz_id, $customer_id, $user_id, $doc_number, date('Y-m-d'), $deposit, $method,
                     'Deposit for booking ' . $booking['booking_number'] . ' (' . $booking['customer_name'] . ')', generate_token()]);
        $receipt_id = (int)$this->db->lastInsertId();
        $this->db->prepare("
            INSERT INTO receipt_items (receipt_id, product_id, description, quantity, unit_price, subtotal, sort_order)
            VALUES (?, NULL, ?, 1, ?, ?, 0)
        ")->execute([$receipt_id, mb_substr('Deposit: ' . $booking['product_name'] . ', ' . booking_when($booking), 0, 500), $deposit, $deposit]);
        doc_made($this->db, 'receipt', $receipt_id, $user_id);   // signed or not, as Settings › Signatures say
        if ($customer_id) {
            customer_refresh_totals($this->db, $this->biz_id, $customer_id);
        }
        return [$receipt_id, $doc_number];
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
            'state' => $state, 'first_name' => mail_first_name((string)$booking['customer_name']), 'shop_name' => (string)$business['name'],
            'booking_number' => $booking['booking_number'], 'service' => $booking['product_name'], 'when' => booking_when($booking),
            'booking_url' => $url, 'total' => sf_money((float)$booking['total'], $symbol),
            'deposit' => (float)$booking['deposit_due'] > 0 ? sf_money((float)$booking['deposit_due'], $symbol) : '', 'message' => $message,
        ];
        $subject = 'Your booking ' . $booking['booking_number'] . ' with ' . $business['name'];
        notify_email($this->biz_id, (string)$booking['customer_email'], (string)$booking['customer_name'], $subject, 'booking_update', $vars,
                     'booking:' . (int)$booking['id'] . ':' . $state, 'shopper.booking');
    }
}
