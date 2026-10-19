<?php
/**
 * Prices (Admins): everything Nfelia charges in each country, and changing
 * it with notice (app/helpers/prices.php). A change has two dates: new
 * sign-ups and the website pay it from the first; clients already here
 * from the second (30 days on by default), and a month that starts before
 * then keeps today's price. When the team is ready, one press tells the
 * clients it reaches (views/emails/price_notice.php), a few at a time.
 *
 *   GET  prices                   today's prices, changes to come, past ones
 *   POST prices                   a new change
 *   GET  prices/{id}              one change: what, when, the email and who it reaches
 *   POST prices/{id}/cancel       call off a change clients don't pay yet
 *   POST prices/{id}/test         the email, to yourself
 *   POST prices/{id}/notify       tell the clients (once)
 */
class OpsPricesController
{
    private const NOTICE_DAYS = 30;

    private PDO $db;
    private array $me;

    public function __construct()
    {
        require_once BASE_PATH . '/app/helpers/prices.php';
        $this->db = get_db();
        $this->me = ops_staff();
    }

    public function index(): void
    {
        $countries = $this->db->query("SELECT c.*, (SELECT COUNT(*) FROM businesses b WHERE b.country_id = c.id AND b.is_active = 1) AS businesses
                                       FROM countries c ORDER BY c.iso_code <> 'NG', c.is_active DESC, c.name")->fetchAll();
        $rows = [];
        foreach ($countries as $c) {
            $rows[] = [
                'country' => $c,
                'now'     => prices_now($this->db, $c),
                'latest'  => prices_latest($this->db, $c),
                'coming'  => array_values(array_filter(prices_changes($this->db, (int)$c['id']), fn($ch) => prices_change_state($ch) !== 'live')),
            ];
        }
        $history = $this->db->query("SELECT pc.*, c.name AS country_name, c.flag_emoji, c.currency_symbol, s.full_name AS author,
                                            (SELECT COUNT(*) FROM price_notices pn WHERE pn.change_id = pc.id) AS told
                                     FROM price_changes pc JOIN countries c ON c.id = pc.country_id LEFT JOIN ops_staff s ON s.id = pc.created_by
                                     ORDER BY pc.id DESC LIMIT 30")->fetchAll();
        ops_render('prices/index', ['rows' => $rows, 'history' => $history, 'notice_days' => self::NOTICE_DAYS], 'Prices');
    }

    public function create(): void
    {
        $country = $this->country((int)($_POST['country'] ?? 0));
        $latest  = prices_latest($this->db, $country);
        $new = [];
        foreach (PRICE_ITEMS as $item => $def) {
            $raw = str_replace([',', ' ', $country['currency_symbol']], '', trim((string)($_POST[$item] ?? '')));
            if ($raw === '' || !is_numeric($raw) || (float)$raw <= 0) {
                ops_flash('error', $def['label'] . ' needs a price above nothing.');
                ops_redirect('prices');
            }
            $amount = round((float)$raw, 2);
            $new[$item] = abs($amount - $latest[$item]) < 0.005 ? null : $amount;
        }
        if (!array_filter($new, fn($v) => $v !== null)) {
            ops_flash('error', 'Those are already ' . $country['name'] . '\'s prices. Nothing to change.');
            ops_redirect('prices');
        }
        $today = date('Y-m-d');
        $new_day = $this->date($_POST['new_from'] ?? '') ?? $today;
        $existing = $this->date($_POST['existing_from'] ?? '') ?? date('Y-m-d', strtotime('+' . self::NOTICE_DAYS . ' days'));
        if ($new_day < $today || $existing < $new_day) {
            ops_flash('error', 'Choose dates from today on, and clients already here no earlier than new sign-ups.');
            ops_redirect('prices');
        }
        $reason = mb_substr(trim((string)($_POST['reason'] ?? '')), 0, 500);
        $this->db->prepare("INSERT INTO price_changes (country_id, month_price, seat_price, book_price, old_month, old_seat, old_book, new_from, existing_from, reason, created_by)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
                 ->execute([$country['id'], $new['month'], $new['seat'], $new['book'], $latest['month'], $latest['seat'], $latest['book'],
                            $new_day === $today ? date('Y-m-d H:i:s') : $new_day . ' 00:00:00', $existing, $reason ?: null, $this->me['id']]);
        $id = (int)$this->db->lastInsertId();
        ops_audit('prices.changed', 'Changed ' . $country['name'] . '\'s prices: ' . $this->summary($new, $latest, $country)
                  . '. New sign-ups from ' . date('j M Y', strtotime($new_day)) . ', clients already here from ' . date('j M Y', strtotime($existing)),
                  ['type' => 'price_change', 'id' => $id, 'data' => ['new' => $new, 'old' => $latest]]);
        ops_flash('success', 'Saved. Read the email below, send yourself a test, then tell clients when you are ready.');
        ops_redirect('prices/' . $id);
    }

    public function show(string $id): void
    {
        $change  = $this->change((int)$id);
        $country = $this->country((int)$change['country_id']);
        $chosen  = json_decode((string)$change['notice_audience'], true) ?: $this->default_groups($change);
        $groups  = [];
        foreach (PRICE_NOTICE_GROUPS as $g => $def) {
            $groups[$g] = $def + ['count' => count(prices_notice_people($this->db, $change, [$g])), 'on' => in_array($g, $chosen, true)];
        }
        $people = prices_notice_people($this->db, $change, $chosen);
        $sample = [0, ['name' => $this->me['full_name'], 'business' => 'your business']];
        if ($people) {
            // The email as the busiest of them reads it: what they built with Nfelia comes first
            $busiest = (int)$this->db->query("SELECT business_id FROM invoices WHERE business_id IN (" . implode(',', array_map('intval', array_keys($people))) . ")
                                              GROUP BY business_id ORDER BY COUNT(*) DESC LIMIT 1")->fetchColumn();
            $first = isset($people[$busiest]) ? $busiest : array_key_first($people);
            $sample = [$first, $people[$first]];
        }
        $vars = prices_notice_vars($this->db, $change, $country, (int)$sample[0], $this->first_name((string)$sample[1]['name']));
        $sent = $this->db->prepare("SELECT status, COUNT(*) AS n FROM price_notices WHERE change_id = ? GROUP BY status");
        $sent->execute([$change['id']]);
        ops_render('prices/show', [
            'change' => $change, 'country' => $country, 'state' => prices_change_state($change), 'groups' => $groups,
            'people' => $people, 'sample' => $sample[1], 'notice' => $vars, 'preview' => render_email('price_notice', $vars),
            'sent' => array_map('intval', array_column($sent->fetchAll(), 'n', 'status')),
            'hours' => (int)ceil(max(1, count($people)) / campaign_per_hour()),
        ], 'Price change · ' . $country['name']);
    }

    public function cancel(string $id): void
    {
        $change = $this->change((int)$id);
        if (in_array(prices_change_state($change), ['cancelled', 'live'], true)) {
            ops_flash('error', $change['cancelled_at'] ? 'It was already called off.' : 'Clients already pay it. Make a new change instead.');
            ops_redirect('prices/' . (int)$change['id']);
        }
        $this->db->prepare("UPDATE price_changes SET cancelled_at = NOW(), cancelled_by = ? WHERE id = ? AND cancelled_at IS NULL")->execute([$this->me['id'], $change['id']]);
        $this->db->prepare("UPDATE price_notices SET status = 'skipped' WHERE change_id = ? AND status = 'waiting'")->execute([$change['id']]);
        $country = $this->country((int)$change['country_id']);
        ops_audit('prices.cancelled', 'Called off the price change for ' . $country['name'] . ' (' . $this->summary($this->amounts($change), $this->amounts($change, 'old'), $country) . ')',
                  ['type' => 'price_change', 'id' => (int)$change['id']]);
        ops_flash('success', 'Called off. ' . $country['name'] . '\'s prices are as they were' . ($change['notice_sent_at'] ? '. Clients already told have not been told it is off: write to them.' : '.'));
        ops_redirect('prices');
    }

    public function test(string $id): void
    {
        $change  = $this->change((int)$id);
        $country = $this->country((int)$change['country_id']);
        $people  = prices_notice_people($this->db, $change, json_decode((string)$change['notice_audience'], true) ?: $this->default_groups($change));
        $vars = prices_notice_vars($this->db, $change, $country, (int)array_key_first($people), $this->first_name((string)$this->me['full_name']));
        $out = notify_email(null, $this->me['email'], $this->me['full_name'], '[Test] ' . $vars['subject'], 'price_notice', $vars, null, 'billing.price_change');
        $done = $out ? notify_flush([$out]) : ['sent' => 0];
        ops_flash($done['sent'] ? 'success' : 'error', $done['sent'] ? 'A test is on its way to ' . $this->me['email'] . '.' : 'The test could not be sent. Health → Failed sends says why.');
        ops_redirect('prices/' . (int)$change['id']);
    }

    public function notify(string $id): void
    {
        $change = $this->change((int)$id);
        $groups = array_values(array_intersect(array_keys(PRICE_NOTICE_GROUPS), (array)($_POST['groups'] ?? [])));
        $people = $groups ? prices_notice_people($this->db, $change, $groups) : [];
        if (!$people) {
            ops_flash('error', 'Nobody to tell: tick who it goes to.');
            ops_redirect('prices/' . (int)$change['id']);
        }
        // Once: whoever presses it first sends it.
        $claim = $this->db->prepare("UPDATE price_changes SET notice_sent_at = NOW(), notice_sent_by = ?, notice_audience = ? WHERE id = ? AND notice_sent_at IS NULL AND cancelled_at IS NULL");
        $claim->execute([$this->me['id'], json_encode($groups), $change['id']]);
        if ($claim->rowCount() !== 1) {
            ops_flash('error', $change['cancelled_at'] ? 'This change was called off.' : 'Clients were already told about this change.');
            ops_redirect('prices/' . (int)$change['id']);
        }
        $add = $this->db->prepare("INSERT IGNORE INTO price_notices (change_id, business_id, email, name) VALUES (?, ?, ?, ?)");
        foreach ($people as $business_id => $p) {
            $add->execute([$change['id'], $business_id, $p['email'], $p['name']]);
        }
        $n = count($people);
        $country = $this->country((int)$change['country_id']);
        ops_audit('prices.notice_sent', 'Told ' . $n . ' client' . ($n === 1 ? '' : 's') . ' in ' . $country['name'] . ' about the price change (' . implode(', ', array_map(fn($g) => PRICE_NOTICE_GROUPS[$g]['label'], $groups)) . ')',
                  ['type' => 'price_change', 'id' => (int)$change['id']]);
        $hours = (int)ceil($n / campaign_per_hour());
        ops_flash('success', 'Telling ' . $n . ' client' . ($n === 1 ? '' : 's') . ($hours > 1 ? ', over about ' . $hours . ' hours' : ' within the hour') . ', a few every 5 minutes. Each also gets a note in the app.');
        ops_redirect('prices/' . (int)$change['id']);
    }

    /* ── Shared ───────────────────────────────────────────── */

    private function change(int $id): array
    {
        $stmt = $this->db->prepare("SELECT pc.*, s.full_name AS author, t.full_name AS teller, x.full_name AS canceller FROM price_changes pc
                                    LEFT JOIN ops_staff s ON s.id = pc.created_by LEFT JOIN ops_staff t ON t.id = pc.notice_sent_by LEFT JOIN ops_staff x ON x.id = pc.cancelled_by
                                    WHERE pc.id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: ops_not_found();
    }

    private function country(int $id): array
    {
        $stmt = $this->db->prepare("SELECT * FROM countries WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: ops_not_found();
    }

    /** Price rises go to clients who pay or may pay; good news to everyone, it can bring people back. */
    private function default_groups(array $change): array
    {
        foreach ($this->amounts($change) as $item => $amount) {
            if ($amount !== null && $amount > (float)$change[PRICE_ITEMS[$item]['old']]) return ['paying', 'trial'];
        }
        return array_keys(PRICE_NOTICE_GROUPS);
    }

    /** [item => amount or null], the new prices of a change, or its old ones. */
    private function amounts(array $change, string $which = 'change'): array
    {
        $out = [];
        foreach (PRICE_ITEMS as $item => $def) {
            $out[$item] = $change[$def['change']] === null ? null : (float)$change[$def[$which]];
        }
        return $out;
    }

    /** "a month ₦10,000 → ₦12,000; a Records Book ₦7,500 → ₦5,000" */
    private function summary(array $new, array $old, array $country): string
    {
        $parts = [];
        foreach ($new as $item => $amount) {
            if ($amount !== null) $parts[] = PRICE_ITEMS[$item]['short'] . ' ' . prices_money((float)$old[$item], $country) . ' → ' . prices_money($amount, $country);
        }
        return implode('; ', $parts);
    }

    private function date($value): ?string
    {
        $value = trim((string)$value);
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $d && $d->format('Y-m-d') === $value ? $value : null;
    }

    private function first_name(string $name): string
    {
        return preg_split('/\s+/u', trim($name))[0] ?? '';
    }
}
