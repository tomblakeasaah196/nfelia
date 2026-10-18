<?php
/**
 * Nfelia — Storefront (shop links)
 *
 * Serves name.nfelia.com. index.php hands over every request whose host is a
 * shop link, before any session starts, so shoppers never get a cookie.
 *
 *   GET  /                  the shop
 *   GET  /p/{id}-{name}     a product's own page (other names are sent on)
 *   POST /checkout          place an order (proof of payment or WhatsApp)
 *   POST /quote-request     bulk or custom order request
 *   GET  /order/{token}     an order's private tracking page
 *   GET  /review/{token}    the review form for a finished order
 *   POST /review/{token}    post the review
 *   GET  /book/{id}-{name}  book a product: pick a time, pay the deposit
 *   GET  /book/{id}/free    free days of a month (?month=) or times of a day (?date=)
 *   POST /book/{id}         send a booking
 *   GET  /booking/{token}   a booking's private page (and /calendar.ics)
 *   GET  /news              the shop's news and offers
 *   GET  /news/{id}-{title} one post
 *   GET  /robots.txt, /sitemap.xml   for search engines (paid shops only)
 *
 * Anything else is a 404.
 *
 * Whether the shop is shown comes from its subscription (shop_visibility):
 * trial shops are open but kept out of search engines, and a shop whose trial
 * or grace period is over shows "closed for now" with its WhatsApp button.
 *
 * A name a shop gave up when it renamed stays with that shop for 90 days
 * (shop_name_holds): visits are sent on to its new address.
 *
 * Only the shop page, product pages and redirects may be cached, for five
 * minutes (render_shop, moved); order and review pages, notices and
 * everything else are marked no-store. Order and review links keep working
 * when the shop is closed or switched off: the shopper already ordered.
 */
class StorefrontController extends PublicController
{
    public function handle(string $label, string $path): void
    {
        header('Cache-Control: no-store');
        $valid    = (bool)preg_match('/^[a-z0-9-]{1,63}$/', $label);
        $business = $valid ? shop_find_by_name($label) : null;
        $method   = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        errors_context_set(['source' => 'shop', 'business_id' => $business ? (int)$business['id'] : null]);

        // An old name on hold sends people to the shop's new address
        if (!$business && $valid && ($holder = shop_name_holder($label)) && (int)$holder['landing_enabled']) {
            $this->moved($holder, $path, $method);
            return;
        }

        if ($business && preg_match('#^booking/([a-f0-9]{32})(/calendar\.ics)?$#', $path, $m) && in_array($method, ['GET', 'HEAD'], true)) {
            $this->booking_page($business, $m[1], !empty($m[2]));
            return;
        }
        if ($business && preg_match('#^(order|review)/([a-f0-9]{32})$#', $path, $m)) {
            if ($m[1] === 'order' && in_array($method, ['GET', 'HEAD'], true)) {
                $this->order_page($business, $m[2]);
                return;
            }
            if ($m[1] === 'review' && in_array($method, ['GET', 'HEAD', 'POST'], true)) {
                $this->review_page($business, $m[2], $method);
                return;
            }
        }

        if ($path === 'robots.txt' && (!$business || !(int)$business['landing_enabled'])) {
            $this->plain("User-agent: *\nDisallow: /\n", 'text/plain');
        }

        // Names waiting for review, retired names and shops whose owner
        // switched the page off all look the same from outside.
        if (!$business || !(int)$business['landing_enabled']) {
            $this->no_shop_here();
            return;
        }

        $visibility = shop_visibility($this->subscription((int)$business['id']));

        // Search engines: paid shops are open to them, others are not
        if ($path === 'robots.txt') {
            $this->plain(shop_robots($business, $visibility), 'text/plain');
        }
        if ($path === 'sitemap.xml') {
            if ($visibility !== 'live') {
                $this->notice(['title' => 'This page isn\'t here', 'body' => 'Everything ' . $business['name'] . ' sells is on the shop\'s front page.']);
                return;
            }
            $this->plain(shop_sitemap($this->db, $business), 'application/xml');
        }

        if (preg_match('#^news(?:/(\d{1,10})(?:-([a-z0-9-]{0,80}))?)?$#', $path, $m) && in_array($method, ['GET', 'HEAD'], true)) {
            if ($visibility === 'closed') {
                $this->closed($business);
                return;
            }
            $this->news_page($business, isset($m[1]) ? (int)$m[1] : 0, $path, $visibility);
            return;
        }

        if (preg_match('#^p/(\d{1,10})(?:-([a-z0-9-]{0,80}))?$#', $path, $m) && in_array($method, ['GET', 'HEAD'], true)) {
            if ($visibility === 'closed') {
                $this->closed($business);
                return;
            }
            $this->product_page($business, (int)$m[1], $path, $visibility);
            return;
        }

        if ($path === '' && in_array($method, ['GET', 'HEAD'], true)) {
            if ($visibility === 'closed') {
                $this->closed($business);
                return;
            }
            $this->render_shop($business, $visibility);
            return;
        }

        if (preg_match('#^book/(\d{1,10})(?:(/free)|-([a-z0-9-]{0,80}))?$#', $path, $m)) {
            if ($visibility === 'closed') {
                if ($method === 'POST' || !empty($m[2])) {
                    json_response(['error' => 'This shop is closed for now, so it can\'t take bookings. You can still reach it on WhatsApp.'], 403);
                }
                $this->closed($business);
                return;
            }
            $this->booking_route($business, (int)$m[1], $path, !empty($m[2]), $method, $visibility);
            return;
        }

        if (in_array($path, ['checkout', 'quote-request'], true) && $method === 'POST') {
            if ($visibility === 'closed') {
                json_response(['error' => 'This shop is closed for now, so it can\'t take orders. You can still reach it on WhatsApp.'], 403);
            }
            $path === 'checkout' ? $this->place_order($business) : $this->send_quote_request($business);
            return;
        }

        $this->notice([
            'title'        => 'This page isn\'t here',
            'body'         => 'Everything ' . $business['name'] . ' sells is on the shop\'s front page.',
            'action_url'   => shop_url($business['subdomain']),
            'action_label' => 'Go to the shop',
        ]);
    }

    /**
     * A renamed shop's old address: pages go to the new one (302, so nothing
     * is remembered once the hold ends); an order from a page opened before
     * the rename is told to reload rather than lost in a redirect.
     */
    private function moved(array $holder, string $path, string $method): void
    {
        $target = shop_url($holder['subdomain'], $path);
        if (!in_array($method, ['GET', 'HEAD'], true)) {
            json_response(['error' => 'This shop has a new address: ' . $target . '. Reload the page and try again.'], 410);
        }
        header('Cache-Control: public, max-age=0, s-maxage=300');
        header('Location: ' . $target, true, 302);
        exit;
    }

    /**
     * A product's page. Products the shop doesn't show (switched off,
     * deleted, or left out of "only show these") have none. The address is
     * the product's id and name; any other name, or none, is sent on to it.
     */
    private function product_page(array $business, int $id, string $path, string $visibility): void
    {
        $settings  = storefront_settings($business, 'published');
        $catalogue = storefront_catalogue($business, $settings);
        $item = null;
        foreach ($catalogue['items'] as $candidate) {
            if ($candidate['id'] === $id) {
                $item = $candidate;
                break;
            }
        }
        if (!$item) {
            $this->notice([
                'title'        => 'This isn\'t in the shop any more',
                'body'         => 'It may have sold out or been taken down. Everything ' . $business['name'] . ' sells now is on the shop\'s front page.',
                'action_url'   => shop_url($business['subdomain']),
                'action_label' => 'See the shop',
            ]);
            return;
        }

        header('Cache-Control: public, max-age=0, s-maxage=300');
        $canonical = sf_product_path($item);
        if ('/' . $path !== $canonical) {
            header('Location: ' . shop_url($business['subdomain'], $canonical), true, 301);
            exit;
        }
        if ($visibility !== 'live') {
            header('X-Robots-Tag: noindex, nofollow');
        }
        storefront_render($business, $settings, 'live', $visibility, ['kind' => 'product', 'item' => $item], $catalogue);
    }

    /**
     * An order's tracking page. Finished orders get their review link here
     * too, the first time the page is opened after the order is done.
     */
    private function order_page(array $business, string $token): void
    {
        $this->private_headers();
        $order = shop_order_by_token($this->db, (int)$business['id'], $token);
        if (!$order) {
            $this->notice([
                'title'     => 'We couldn\'t find this order',
                'body'      => 'Check that you opened the whole link from your message. If it still doesn\'t work, ask ' . $business['name'] . '.',
                'shop_name' => $business['name'],
                'whatsapp'  => shop_whatsapp_digits($business),
            ]);
            return;
        }
        if (!$order['review'] && shop_order_is_done($order, $order['delivery'])) {
            $order['review'] = review_invite($this->db, (int)$order['id']);
        }
        storefront_render($business, storefront_settings($business, 'published'), 'live', 'unlisted', ['kind' => 'order', 'order' => $order]);
    }

    /**
     * The review form. A review is posted once, then shows on the shop; the
     * link afterwards just says thank you. The page works without a script:
     * a plain form, then a redirect back here.
     */
    private function review_page(array $business, string $token, string $method): void
    {
        $this->private_headers();
        $stmt = $this->db->prepare("
            SELECT r.*, o.order_number, o.public_token
            FROM reviews r
            JOIN orders o ON o.id = r.order_id
            WHERE r.business_id = ? AND r.token = ?
            LIMIT 1
        ");
        $stmt->execute([(int)$business['id'], $token]);
        $review = $stmt->fetch();
        if (!$review) {
            $this->notice([
                'title' => 'This review link doesn\'t work',
                'body'  => 'Check that you opened the whole link from your message.',
                'action_url'   => shop_url($business['subdomain']),
                'action_label' => 'Go to the shop',
            ]);
            return;
        }

        $page = ['kind' => 'review', 'review' => $review, 'error' => '', 'comment' => '', 'rating' => 0];
        if ($method === 'POST') {
            $here = shop_url($business['subdomain'], '/review/' . $token);
            if ($review['status'] === 'invited') {
                $rating  = (int)($_POST['rating'] ?? 0);
                $comment = trim(str_replace(["\r\n", "\r"], "\n", (string)($_POST['comment'] ?? '')));
                $comment = mb_substr(preg_replace("/\n{3,}/", "\n\n", $comment) ?? '', 0, 600);
                if ($rating < 1 || $rating > 5) {
                    http_response_code(422);
                    $page['error']   = 'Tap a star to rate your order, from 1 to 5.';
                    $page['comment'] = $comment;
                    storefront_render($business, storefront_settings($business, 'published'), 'live', 'unlisted', $page);
                    return;
                }
                $this->publish_review($business, $review, $rating, $comment);
            }
            header('Location: ' . $here, true, 303);
            exit;
        }
        storefront_render($business, storefront_settings($business, 'published'), 'live', 'unlisted', $page);
    }

    private function publish_review(array $business, array $review, int $rating, string $comment): void
    {
        $stmt = $this->db->prepare("
            UPDATE reviews SET rating = ?, comment = ?, status = 'published', submitted_at = NOW()
            WHERE id = ? AND status = 'invited'
        ");
        $stmt->execute([$rating, $comment !== '' ? $comment : null, (int)$review['id']]);
        if ($stmt->rowCount() !== 1) {
            return;   // posted twice at once: the first one counts
        }
        notify('review.new', (int)$business['id'], [
            'order_id' => (int)$review['order_id'], 'order_number' => $review['order_number'],
            'who' => review_display_name((string)$review['customer_name']), 'rating' => $rating, 'comment' => $comment,
        ], ['business' => $business, 'dedupe' => (string)$review['id']]);
        storefront_purge((int)$business['id']);
    }

    /* ── News and offers ─────────────────────────────── */

    /**
     * The shop's posts (/news) or one post (/news/{id}-{title}, other names
     * sent on). Cached like the shop; publishing a post purges it.
     */
    private function news_page(array $business, int $id, string $path, string $visibility): void
    {
        $settings = storefront_settings($business, 'published');
        if ($id) {
            $post = shop_post($this->db, (int)$business['id'], $id);
            if (!$post) {
                $this->notice([
                    'title'        => 'This post isn\'t here any more',
                    'body'         => 'It may have been taken down. ' . $business['name'] . '\'s news is on the shop.',
                    'action_url'   => shop_url($business['subdomain'], '/news'),
                    'action_label' => 'See the news',
                ]);
                return;
            }
            header('Cache-Control: public, max-age=0, s-maxage=300');
            $canonical = shop_post_path($post);
            if ('/' . $path !== $canonical) {
                header('Location: ' . shop_url($business['subdomain'], $canonical), true, 301);
                exit;
            }
            if ($visibility !== 'live') {
                header('X-Robots-Tag: noindex, nofollow');
            }
            storefront_render($business, $settings, 'live', $visibility, ['kind' => 'post', 'post' => $post]);
            return;
        }
        $posts = shop_posts($this->db, (int)$business['id'], 60);
        if (!$posts) {
            $this->notice([
                'title'        => 'No news yet',
                'body'         => 'Everything ' . $business['name'] . ' sells is on the shop\'s front page.',
                'action_url'   => shop_url($business['subdomain']),
                'action_label' => 'Go to the shop',
            ]);
            return;
        }
        header('Cache-Control: public, max-age=0, s-maxage=300');
        if ($visibility !== 'live') {
            header('X-Robots-Tag: noindex, nofollow');
        }
        storefront_render($business, $settings, 'live', $visibility, ['kind' => 'news', 'posts' => $posts]);
    }

    /**
     * A plain-text or XML answer for search engines, cached for an hour.
     */
    private function plain(string $body, string $type): void
    {
        header('Cache-Control: public, max-age=3600, s-maxage=3600');
        header('Content-Type: ' . $type . '; charset=utf-8');
        echo $body;
        exit;
    }

    /* ── Bookings ────────────────────────────────────── */

    /**
     * /book/{id}…: the booking page (GET), its free days and times (GET
     * /free), or a booking sent from it (POST /book/{id}). Only products the
     * shop shows and that are set up for booking can be booked.
     */
    private function booking_route(array $business, int $id, string $path, bool $free, string $method, string $visibility): void
    {
        $settings  = storefront_settings($business, 'published');
        $catalogue = storefront_catalogue($business, $settings);
        $item = null;
        foreach ($catalogue['items'] as $candidate) {
            if ($candidate['id'] === $id && $candidate['booking']) {
                $item = $candidate;
                break;
            }
        }
        if (!$item) {
            if ($method === 'POST' || $free) {
                json_response(['error' => 'This can\'t be booked any more. Reload the shop to see what can.'], 404);
            }
            $this->notice([
                'title'        => 'This can\'t be booked here',
                'body'         => 'It may have been taken down or changed. Everything ' . $business['name'] . ' offers is on the shop\'s front page.',
                'action_url'   => shop_url($business['subdomain']),
                'action_label' => 'See the shop',
            ]);
            return;
        }

        if ($free && in_array($method, ['GET', 'HEAD'], true)) {
            $this->booking_free($business, $item);
            return;
        }
        if ($method === 'POST' && $path === 'book/' . $id) {
            $this->place_booking($business, $item);
            return;
        }
        if (!in_array($method, ['GET', 'HEAD'], true) || $free) {
            json_response(['error' => 'Not found.'], 404);
        }

        $canonical = booking_page_path($item);
        header('Cache-Control: public, max-age=0, s-maxage=300');
        if ('/' . $path !== $canonical) {
            header('Location: ' . shop_url($business['subdomain'], $canonical), true, 301);
            exit;
        }
        $booking_settings = booking_settings($business);
        storefront_render($business, $settings, 'live', $visibility, [
            'kind' => 'book', 'item' => $item, 'settings' => $booking_settings, 'window' => booking_window($booking_settings),
        ], $catalogue);
    }

    /**
     * Free days of a month (?month=2026-10) or free times of a day
     * (?date=2026-10-18), as JSON. Never cached: they change with every
     * booking.
     */
    private function booking_free(array $business, array $item): void
    {
        $month = (string)($_GET['month'] ?? '');
        $date  = (string)($_GET['date'] ?? '');
        if (preg_match('/^\d{4}-\d{2}$/', $month)) {
            json_response(['month' => $month, 'days' => booking_month($this->db, $business, $item, $item['booking'], $month)]);
        }
        if ($item['booking']['mode'] === 'slot' && booking_valid_date($date)) {
            json_response(['date' => $date, 'times' => array_map(
                fn($t) => ['t' => $t, 'label' => booking_time_text($t)],
                booking_slots($this->db, $business, $item['booking'], $date)
            )]);
        }
        json_response(['error' => 'Ask for a month (month=2026-10) or a day (date=2026-10-18).'], 422);
    }

    /**
     * A booking request: the time is checked again while the shop's other
     * bookings wait (a lock on the shop), then held as 'pending' until the
     * owner confirms it. Prices come from the products table, never from the
     * page.
     */
    private function place_booking(array $business, array $item): void
    {
        $bid     = (int)$business['id'];
        $info    = $item['booking'];
        $channel = ($_POST['channel'] ?? '') === 'whatsapp' ? 'whatsapp' : 'site';
        $name    = trim((string)($_POST['customer_name'] ?? ''));
        $phone   = trim((string)($_POST['customer_phone'] ?? ''));
        $email   = trim((string)($_POST['customer_email'] ?? ''));
        $note    = mb_substr(trim((string)($_POST['customer_note'] ?? '')), 0, 1000);

        if ($name === '' || $phone === '') {
            json_response(['error' => 'Type your name and a phone number the shop can reach you on.'], 422);
        }
        if (($problem = $this->contact_problem($name, $phone, $email)) !== null) {
            json_response(['error' => $problem], 422);
        }
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM bookings WHERE business_id = ? AND customer_phone = ? AND created_at >= CURDATE()");
        $stmt->execute([$bid, $phone]);
        if ((int)$stmt->fetchColumn() >= 5) {
            json_response(['error' => 'Too many bookings today. Please try again tomorrow, or ask on WhatsApp.'], 429);
        }

        // The deposit's proof, if any (only for transfers to the shop's own accounts)
        $bank_id = (int)($_POST['bank_id'] ?? 0);
        $banks   = array_column($this->booking_banks($bid), null, 'id');
        $method  = $channel === 'site' && isset($banks[$bank_id]) ? 'bank_transfer' : 'none';
        $proof   = null;
        if ($method === 'bank_transfer' && !empty($_FILES['payment_proof']['tmp_name'])) {
            $dir = BASE_PATH . '/uploads/booking_proofs/' . $bid;
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $file = time() . '_' . bin2hex(random_bytes(4)) . '.png';
            if (save_image($_FILES['payment_proof'], $dir . '/' . $file, 1200)) {
                $proof = 'uploads/booking_proofs/' . $bid . '/' . $file;
            }
        }

        $this->db->beginTransaction();
        try {
            // One booking at a time per shop, so two people can't take the same time
            $this->db->prepare("SELECT id FROM businesses WHERE id = ? FOR UPDATE")->execute([$bid]);
            $slot = booking_check($this->db, $business, $item, $info, $_POST);
            if (!$slot['ok']) {
                $this->db->rollBack();
                json_response(['error' => $slot['error']], 409);
            }
            $total   = round($item['price'] * $slot['units'], 2);
            $deposit = booking_deposit($info, $total);

            $this->db->prepare("UPDATE businesses SET booking_number_seq = booking_number_seq + 1 WHERE id = ?")->execute([$bid]);
            $stmt = $this->db->prepare("SELECT booking_number_seq FROM businesses WHERE id = ?");
            $stmt->execute([$bid]);
            $number = 'BKG-' . str_pad((string)$stmt->fetchColumn(), 4, '0', STR_PAD_LEFT);
            $token  = shop_order_token();

            $stmt = $this->db->prepare("SELECT id FROM customers WHERE business_id = ? AND phone = ? AND deleted_at IS NULL LIMIT 1");
            $stmt->execute([$bid, $phone]);
            $customer_id = (int)$stmt->fetchColumn();
            if (!$customer_id) {
                $this->db->prepare("INSERT INTO customers (business_id, full_name, phone, email, notes) VALUES (?, ?, ?, ?, ?)")
                         ->execute([$bid, $name, $phone, $email ?: null, 'Auto-created from a booking']);
                $customer_id = (int)$this->db->lastInsertId();
            }

            $this->db->prepare("
                INSERT INTO bookings (business_id, product_id, product_name, booking_number, public_token, kind, starts_at, ends_at, units,
                                      unit_price, total, deposit_due, currency_code, customer_id, customer_name, customer_phone,
                                      customer_email, customer_note, channel, payment_method, payment_proof_path)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([$bid, $item['id'], mb_substr($item['name'], 0, 150), $number, $token, $info['mode'], $slot['starts_at'], $slot['ends_at'],
                         $slot['units'], $item['price'], $total, $deposit, business_currency_code($business), $customer_id, $name, $phone,
                         $email ?: null, $note ?: null, $channel, $method, $proof]);
            $booking_id = (int)$this->db->lastInsertId();
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('Booking failed: ' . $e->getMessage());
            json_response(['error' => 'We couldn\'t send your booking just now. Please try again, or book on WhatsApp.'], 500);
        }

        $booking = ['booking_number' => $number, 'public_token' => $token, 'kind' => $info['mode'], 'starts_at' => $slot['starts_at'],
                    'ends_at' => $slot['ends_at'], 'units' => $slot['units'], 'product_name' => $item['name']];
        $when = booking_when($booking);
        $url  = booking_url($business, $booking);
        notify('booking.new', $bid, [
            'booking_id' => $booking_id, 'booking_number' => $number, 'item' => $item['name'], 'when' => $when,
            'customer' => $name, 'phone' => $phone, 'deposit' => (float)$deposit,
        ], ['business' => $business, 'dedupe' => (string)$booking_id]);
        if ($email !== '' && $url) {
            $symbol = (string)($business['currency_symbol'] ?? '');
            $vars = ['state' => 'received', 'first_name' => preg_split('/\s+/u', $name)[0], 'shop_name' => (string)$business['name'],
                     'booking_number' => $number, 'service' => $item['name'], 'when' => $when, 'booking_url' => $url,
                     'total' => sf_money($total, $symbol), 'deposit' => $deposit > 0 ? sf_money($deposit, $symbol) : '', 'message' => ''];
            notify_email($bid, $email, $name, 'Your booking ' . $number . ' with ' . $business['name'], 'booking_update', $vars,
                         'booking:' . $booking_id . ':received', 'shopper.booking');
        }
        json_response(['success' => true, 'booking_number' => $number, 'booking_url' => $url, 'when' => $when,
                       'total' => $total, 'deposit' => $deposit, 'channel' => $channel]);
    }

    private function booking_banks(int $business_id): array
    {
        $stmt = $this->db->prepare("SELECT id FROM bank_accounts WHERE business_id = ? AND is_active = 1 AND deleted_at IS NULL");
        $stmt->execute([$business_id]);
        return $stmt->fetchAll();
    }

    /**
     * A booking's private page, or its calendar file.
     */
    private function booking_page(array $business, string $token, bool $ics): void
    {
        $this->private_headers();
        $stmt = $this->db->prepare("SELECT * FROM bookings WHERE business_id = ? AND public_token = ? LIMIT 1");
        $stmt->execute([(int)$business['id'], $token]);
        $booking = $stmt->fetch();
        if (!$booking) {
            $this->notice([
                'title'     => 'We couldn\'t find this booking',
                'body'      => 'Check that you opened the whole link from your message. If it still doesn\'t work, ask ' . $business['name'] . '.',
                'shop_name' => $business['name'],
                'whatsapp'  => shop_whatsapp_digits($business),
            ]);
            return;
        }
        if ($ics) {
            header('Content-Type: text/calendar; charset=utf-8');
            header('Content-Disposition: attachment; filename="booking-' . strtolower($booking['booking_number']) . '.ics"');
            echo booking_ics($booking, $business);
            exit;
        }
        storefront_render($business, storefront_settings($business, 'published'), 'live', 'unlisted', ['kind' => 'booking', 'booking' => $booking]);
    }

    /**
     * Order and review pages are one shopper's: never cached, never in
     * search results, and their address isn't passed on to other sites.
     */
    private function private_headers(): void
    {
        header('Cache-Control: no-store');
        header('X-Robots-Tag: noindex, nofollow');
        header('Referrer-Policy: same-origin');
    }

    private function subscription(int $business_id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT status, trial_ends_at, current_period_end, grace_ends_at, last_payment_date
            FROM subscriptions WHERE business_id = ?
        ");
        $stmt->execute([$business_id]);
        return $stmt->fetch() ?: null;
    }

    private function no_shop_here(): void
    {
        $this->notice([
            'title'        => 'There\'s no shop at this address',
            'body'         => 'Check the link for a typo, or ask the shop for its address.',
            'action_url'   => APP_URL,
            'action_label' => 'Go to Nfelia',
        ]);
    }

    private function closed(array $business): void
    {
        $whatsapp = shop_whatsapp_digits($business);
        $this->notice([
            'status'    => 200,
            'shop_name' => $business['name'],
            'title'     => 'This shop is closed for now',
            'body'      => $whatsapp
                ? 'You can still reach ' . $business['name'] . ' on WhatsApp.'
                : 'Please check back soon.',
            'whatsapp'  => $whatsapp,
        ]);
    }
}
