<?php
/**
 * Nfelia — Storefront (shop links)
 *
 * Serves name.nfelia.com. index.php hands over every request whose host is a
 * shop link, before any session starts, so shoppers never get a cookie.
 *
 *   GET  /               the shop
 *   POST /checkout       place an order (proof of payment or WhatsApp)
 *   POST /quote-request  bulk or custom order request
 *
 * Anything else is a 404 for now; product pages come with step 3.
 *
 * Whether the shop is shown comes from its subscription (shop_visibility):
 * trial shops are open but kept out of search engines, and a shop whose trial
 * or grace period is over shows "closed for now" with its WhatsApp button.
 */
class StorefrontController extends PublicController
{
    public function handle(string $label, string $path): void
    {
        $business = preg_match('/^[a-z0-9-]{1,63}$/', $label)
            ? shop_find_by_name($label)
            : null;

        // Names waiting for review, retired names and shops whose owner
        // switched the page off all look the same from outside.
        if (!$business || !(int)$business['landing_enabled']) {
            $this->no_shop_here();
            return;
        }

        $visibility = shop_visibility($this->subscription((int)$business['id']));
        $method     = $_SERVER['REQUEST_METHOD'] ?? 'GET';

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
