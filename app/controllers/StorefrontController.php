<?php
/**
 * Nfelia — Storefront (shop links)
 *
 * Serves name.nfelia.com. index.php hands over every request whose host is a
 * shop link, before any session starts, so shoppers never get a cookie.
 *
 *   GET  /                  the shop's home page
 *   GET  /shop              everything it sells, with search and categories
 *   GET  /contact           the request form, every way to reach it, the map
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
 *   GET  /llms.txt          for AI assistants (paid shops only, shop_llms_txt)
 *   GET  /{key}.txt         the IndexNow key file (app/helpers/indexnow.php)
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
require_once __DIR__ . '/../helpers/shop_seo.php';
// IndexNow tells search engines about changed pages; the shop still works
// without it
if (is_file(__DIR__ . '/../helpers/indexnow.php')) {
    require_once __DIR__ . '/../helpers/indexnow.php';
}

class StorefrontController extends PublicController
{
    /** The shop this request is for, so its notices wear its colour (notice()). */
    private ?array $shop = null;

    public function handle(string $label, string $path): void
    {
        // IndexNow's key file, the same on every host Nfelia serves
        if (function_exists('indexnow_serve_key_file') && indexnow_serve_key_file($path)) {
            exit;
        }
        header('Cache-Control: no-store');
        $valid    = (bool)preg_match('/^[a-z0-9-]{1,63}$/', $label);
        $business = $valid ? shop_find_by_name($label) : null;
        $method   = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        errors_context_set(['source' => 'shop', 'business_id' => $business ? (int)$business['id'] : null]);

        // An old name on hold sends people to the shop's new address. A
        // shopper's own order, review and booking links keep working on it
        // even while the shop page is switched off, as on the new name (W12).
        if (!$business && $valid && ($holder = shop_name_holder($label))) {
            if ((int)$holder['landing_enabled']) {
                $this->moved($holder, $path, $method);
                return;
            }
            if (preg_match('#^(order|review|booking)/[a-f0-9]{32}#', $path)) {
                $business = $holder;
            }
        }
        $this->shop = $business;

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
        if ($path === 'llms.txt' && (!$business || !(int)$business['landing_enabled'])) {
            $this->plain_missing();
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
        // AI assistants: what a paid shop sells and how to buy. Trial and
        // closed shops have nothing to say.
        if ($path === 'llms.txt') {
            if ($visibility !== 'live' || !in_array($method, ['GET', 'HEAD'], true)) {
                $this->plain_missing();
            }
            header('X-Robots-Tag: noindex');   // for assistants, not a page in search results
            $this->plain(shop_llms_txt($business), 'text/plain');
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

        if (in_array($path, ['shop', 'contact'], true) && in_array($method, ['GET', 'HEAD'], true)) {
            if ($visibility === 'closed') {
                $this->closed($business);
                return;
            }
            $this->shop_page($business, $path === 'shop' ? 'catalogue' : 'contact', $visibility);
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

        if ($path === 'stays/free' && in_array($method, ['GET', 'HEAD'], true)) {
            if ($visibility === 'closed') {
                json_response(['error' => 'This shop is closed for now, so it can\'t take bookings. You can still reach it on WhatsApp.'], 403);
            }
            $this->stays_free($business);
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
     * A renamed shop's old address: pages go to the new one; an order from a
     * page opened before the rename is told to reload rather than lost in a
     * redirect. A 301, so search engines move what they know to the new
     * address during the hold (a 302 tells them to keep showing the old
     * one); max-age=0 keeps browsers from remembering it once the hold ends
     * and the name can be someone else's.
     */
    private function moved(array $holder, string $path, string $method): void
    {
        $target = shop_url($holder['subdomain'], $path);
        if (!in_array($method, ['GET', 'HEAD'], true)) {
            json_response(['error' => 'This shop has a new address: ' . $target . '. Reload the page and try again.'], 410);
        }
        header('Cache-Control: public, max-age=0, s-maxage=300');
        header('Location: ' . $target, true, 301);
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
        // Where it came from, so the team can tell a shop reviewing itself (W14)
        $stmt = $this->db->prepare("
            UPDATE reviews SET rating = ?, comment = ?, status = 'published', submitted_at = NOW(), ip = ?, user_agent = ?
            WHERE id = ? AND status = 'invited'
        ");
        $stmt->execute([$rating, $comment !== '' ? $comment : null, client_ip(),
                        mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255) ?: null, (int)$review['id']]);
        if ($stmt->rowCount() !== 1) {
            return;   // posted twice at once: the first one counts
        }
        notify('review.new', (int)$business['id'], [
            'order_id' => (int)$review['order_id'], 'order_number' => $review['order_number'],
            'who' => review_display_name((string)$review['customer_name']), 'rating' => $rating, 'comment' => $comment,
        ], ['business' => $business, 'dedupe' => (string)$review['id']]);
        storefront_purge((int)$business['id']);
    }

    /**
     * The shop's full list (/shop) or its contact page (/contact). Cached
     * like the shop. A shop that switched its list off shows everything it
     * has on the home page, so /shop goes there.
     */
    private function shop_page(array $business, string $kind, string $visibility): void
    {
        $settings = storefront_settings($business, 'published');
        if ($kind === 'catalogue' && !(sf_section($settings, 'catalogue')['on'] ?? false)) {
            header('Cache-Control: public, max-age=0, s-maxage=300');
            header('Location: ' . shop_url($business['subdomain']), true, 302);
            exit;
        }
        header('Cache-Control: public, max-age=0, s-maxage=300');
        if ($visibility !== 'live') {
            header('X-Robots-Tag: noindex, nofollow');
        }
        storefront_render($business, $settings, 'live', $visibility, ['kind' => $kind]);
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

    /**
     * A plain-text 404, for files only paid shops have (/llms.txt).
     */
    private function plain_missing(): void
    {
        http_response_code(404);
        header('Cache-Control: public, max-age=0, s-maxage=300');
        header('Content-Type: text/plain; charset=utf-8');
        header('X-Robots-Tag: noindex, nofollow');
        echo "Not found\n";
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
        // A step towards booking, never a page for search results (as its meta says)
        header('X-Robots-Tag: ' . ($visibility === 'live' ? 'noindex, follow' : 'noindex, nofollow'));
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
     * Haven's first screen asks which places are free (/stays/free, JSON,
     * never cached: it changes with every booking):
     *   ?month=2026-11                    each date's number of places free
     *                                     that night (-1: none can be booked)
     *   ?checkin=2026-11-02&checkout=…    each place: free or why not, and
     *                                     for those free, what the stay costs
     *                                     (booking_night_price, the deposit,
     *                                     the caution fee), as place_booking
     *                                     would charge it
     */
    private function stays_free(array $business): void
    {
        header('Cache-Control: no-store');
        $settings  = storefront_settings($business, 'published');
        $catalogue = storefront_catalogue($business, $settings);
        $stays     = array_values(array_filter($catalogue['items'], fn($i) => ($i['booking']['mode'] ?? '') === 'nights' && empty($i['sold_out'])));
        $month = (string)($_GET['month'] ?? '');
        if (preg_match('/^\d{4}-\d{2}$/', $month)) {
            $days = [];
            foreach ($stays as $item) {
                foreach (booking_month($this->db, $business, $item, $item['booking'], $month) as $d => $state) {
                    $days[$d] = max($days[$d] ?? -1, 0) + ($state === 'open' ? 1 : 0);
                    if ($state === 'closed' && ($days[$d] ?? 0) === 0) {
                        $days[$d] = -1;
                    }
                }
            }
            json_response(['month' => $month, 'days' => (object)$days]);
        }
        $in  = (string)($_GET['checkin'] ?? '');
        $out = (string)($_GET['checkout'] ?? '');
        if (!booking_valid_date($in) || !booking_valid_date($out)) {
            json_response(['error' => 'Ask for a month (month=2026-11) or a stay (checkin=2026-11-02&checkout=2026-11-05).'], 422);
        }
        $answer = [];
        foreach ($stays as $item) {
            $info = $item['booking'];
            // Taken, or dates no place can be booked for (too far ahead, too long)
            if ($out > $in && booking_taken_nights($this->db, (int)$item['id'], $in, planner_add_days($out, -1))) {
                $answer[] = ['id' => $item['id'], 'free' => false, 'why' => 'taken'];
                continue;
            }
            $check = booking_check($this->db, $business, $item, $info, ['checkin' => $in, 'checkout' => $out]);
            if (!$check['ok']) {
                $answer[] = ['id' => $item['id'], 'free' => false, 'why' => 'dates', 'error' => $check['error']];
                continue;
            }
            $nights = (int)$check['units'];
            $each   = booking_night_price($info, (float)$item['price'], $nights);
            $total  = round($each * $nights, 2);
            $answer[] = ['id' => $item['id'], 'free' => true, 'nights' => $nights, 'each' => $each, 'total' => $total,
                         'deposit' => booking_deposit($info, $total), 'caution' => $info['caution']];
        }
        json_response(['checkin' => $in, 'checkout' => $out, 'stays' => $answer]);
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
        $this->public_form_guard('booking', $bid, $email);
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM bookings WHERE business_id = ? AND REGEXP_REPLACE(customer_phone, '[^0-9]', '') = ? AND created_at >= CURDATE()");
        $stmt->execute([$bid, preg_replace('/\D/', '', $phone)]);
        if ((int)$stmt->fetchColumn() >= 5) {
            json_response(['error' => 'Too many bookings today. Please try again tomorrow, or ask on WhatsApp.'], 429);
        }

        // The deposit's proof, if any (only for transfers to the shop's own accounts)
        $bank_id = (int)($_POST['bank_id'] ?? 0);
        $banks   = array_column($this->booking_banks($bid), null, 'id');
        $method  = $channel === 'site' && isset($banks[$bank_id]) ? 'bank_transfer' : 'none';
        $proof   = null;
        if ($method === 'bank_transfer' && !empty($_FILES['payment_proof']['tmp_name'])) {
            // Private: only the shop sees it (BookingController::proof)
            $file = bin2hex(random_bytes(16)) . '.jpg';
            if (save_image($_FILES['payment_proof'], private_file_dir('booking_proofs', $bid) . '/' . $file, 1200, 'jpg')) {
                $proof = 'storage/private/booking_proofs/' . $bid . '/' . $file;
            }
        }
        // A booking that doesn't go through leaves no file behind
        $drop_proof = function () use (&$proof) {
            if ($proof) {
                @unlink(BASE_PATH . '/' . $proof);
            }
        };

        $this->db->beginTransaction();
        try {
            // One booking at a time per shop, so two people can't take the same time
            $this->db->prepare("SELECT id FROM businesses WHERE id = ? FOR UPDATE")->execute([$bid]);
            $slot = booking_check($this->db, $business, $item, $info, $_POST);
            if (!$slot['ok']) {
                $this->db->rollBack();
                $drop_proof();
                json_response(['error' => $slot['error']], 409);
            }
            // A longer stay may pay less a night; the caution fee is paid with the booking, refundable
            $unit    = booking_night_price($info, (float)$item['price'], (int)$slot['units']);
            $total   = round($unit * $slot['units'], 2);
            $deposit = booking_deposit($info, $total);
            $caution = $info['caution'];

            // ADA-BKG-008, carrying on from the old BKG-0007 numbers
            $number = generate_doc_number($this->db, (int)$bid, 'BKG');
            $token  = shop_order_token();

            // The customer by the phone's last 10 digits, however it was typed, as shop orders do
            $customer_id = order_match_customer($this->db, (int)$bid, $name, $phone, $email);

            $this->db->prepare("
                INSERT INTO bookings (business_id, product_id, product_name, booking_number, public_token, kind, starts_at, ends_at, units,
                                      unit_price, total, deposit_due, caution_fee, currency_code, customer_id, customer_name, customer_phone,
                                      customer_email, customer_note, channel, payment_method, payment_proof_path)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([$bid, $item['id'], mb_substr($item['name'], 0, 150), $number, $token, $info['mode'], $slot['starts_at'], $slot['ends_at'],
                         $slot['units'], $unit, $total, $deposit, $caution, business_currency_code($business), $customer_id, $name, $phone,
                         $email ?: null, $note ?: null, $channel, $method, $proof]);
            $booking_id = (int)$this->db->lastInsertId();
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $drop_proof();
            error_log('Booking failed: ' . $e->getMessage());
            json_response(['error' => 'We couldn\'t send your booking just now. Please try again, or book on WhatsApp.'], 500);
        }

        $booking = ['booking_number' => $number, 'public_token' => $token, 'kind' => $info['mode'], 'starts_at' => $slot['starts_at'],
                    'ends_at' => $slot['ends_at'], 'units' => $slot['units'], 'product_name' => $item['name']];
        $when = booking_when($booking);
        $url  = booking_url($business, $booking);
        notify('booking.new', $bid, [
            'booking_id' => $booking_id, 'booking_number' => $number, 'item' => $item['name'], 'when' => $when,
            'customer' => $name, 'phone' => $phone, 'deposit' => (float)$deposit, 'caution' => (float)$caution,
        ], ['business' => $business, 'dedupe' => (string)$booking_id]);
        if ($email !== '' && $url) {
            $symbol = (string)($business['currency_symbol'] ?? '');
            $vars = ['state' => 'received', 'first_name' => mail_first_name($name), 'shop_name' => (string)$business['name'],
                     'booking_number' => $number, 'service' => $item['name'], 'when' => $when, 'booking_url' => $url,
                     'total' => sf_money($total, $symbol), 'deposit' => $deposit > 0 ? sf_money($deposit, $symbol) : '',
                     'caution' => $caution > 0 ? sf_money($caution, $symbol) : '', 'message' => ''];
            notify_email($bid, $email, $name, 'Your booking ' . $number . ' with ' . $business['name'], 'booking_update', $vars,
                         'booking:' . $booking_id . ':received', 'shopper.booking');
        }
        json_response(['success' => true, 'booking_number' => $number, 'booking_url' => $url, 'when' => $when,
                       'total' => $total, 'deposit' => $deposit, 'caution' => $caution, 'channel' => $channel]);
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
            SELECT * FROM subscriptions WHERE business_id = ?
        ");
        $stmt->execute([$business_id]);
        return $stmt->fetch() ?: null;
    }

    private function no_shop_here(): void
    {
        // Nfelia's own colour: a shop switched off looks like no shop at all
        $this->notice([
            'title'        => 'There\'s no shop at this address',
            'body'         => 'Check the link for a typo, or ask the shop for its address.',
            'action_url'   => APP_URL,
            'action_label' => 'Go to Nfelia',
            'colour'       => null,
        ]);
    }

    /**
     * A notice on a shop's own address, in the shop's colour as its pages
     * show it (views/public/notice.php). One that says its own 'colour'
     * keeps it.
     */
    protected function notice(array $notice): void
    {
        if ($this->shop && !array_key_exists('colour', $notice)) {
            $notice['colour'] = storefront_resolve(storefront_settings($this->shop), $this->shop)['palette']['accent'];
        }
        parent::notice($notice);
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
