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

        // An old name on hold sends people to the shop's new address
        if (!$business && $valid && ($holder = shop_name_holder($label)) && (int)$holder['landing_enabled']) {
            $this->moved($holder, $path, $method);
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

        // Names waiting for review, retired names and shops whose owner
        // switched the page off all look the same from outside.
        if (!$business || !(int)$business['landing_enabled']) {
            $this->no_shop_here();
            return;
        }

        $visibility = shop_visibility($this->subscription((int)$business['id']));

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
        $who = review_display_name((string)$review['customer_name']);
        planner_notify(
            $this->db,
            (int)$business['id'],
            null,
            'New review: ' . $rating . ' star' . ($rating === 1 ? '' : 's'),
            $who . ' rated order ' . $review['order_number'] . ' ' . $rating . ' out of 5' . ($comment !== '' ? ': "' . mb_strimwidth($comment, 0, 140, '…') . '"' : '.'),
            'orders/view/' . (int)$review['order_id']
        );
        storefront_purge((int)$business['id']);
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
