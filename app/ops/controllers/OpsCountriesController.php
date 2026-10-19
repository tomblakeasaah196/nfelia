<?php
/**
 * Countries Nfelia sells in (Admins): the trial, and how a business there
 * pays (Paystack, or mobile money to a number with instructions). Nigeria,
 * the first market, is always on. A country's starting prices are set when
 * it is added; after that they change under Prices, with notice
 * (OpsPricesController).
 *
 *   GET  countries
 *   POST countries                   add one, with its starting prices
 *   POST countries/{id}              change its trial and payment details
 *   POST countries/{id}/toggle       offer it at sign-up, or not
 */
class OpsCountriesController
{
    private const METHODS = ['paystack' => 'Paystack (card)', 'mobile_money' => 'Mobile money', 'manual' => 'Manual (bank transfer)', 'stripe' => 'Stripe', 'other' => 'Other'];

    public function index(): void
    {
        require_once BASE_PATH . '/app/helpers/prices.php';
        $db = get_db();
        $rows = $db->query("SELECT c.*, (SELECT COUNT(*) FROM businesses b WHERE b.country_id = c.id) AS businesses FROM countries c ORDER BY c.is_active DESC, c.name")->fetchAll();
        foreach ($rows as &$c) {
            $c['prices'] = prices_now($db, $c);
        }
        unset($c);
        ops_render('countries', ['rows' => $rows, 'methods' => self::METHODS], 'Countries');
    }

    public function add(): void
    {
        $f = $this->fields();
        $name = trim((string)($_POST['name'] ?? ''));
        $iso  = strtoupper(trim((string)($_POST['iso_code'] ?? '')));
        $code = strtoupper(trim((string)($_POST['currency_code'] ?? '')));
        $month = max(0, (float)($_POST['monthly_price'] ?? 0));
        $seat  = max(0, (float)($_POST['extra_seat_price'] ?? 0));
        $book  = max(0, (float)($_POST['book_price'] ?? 0));
        if ($name === '' || !preg_match('/^[A-Z]{2}$/', $iso) || !preg_match('/^[A-Z]{3}$/', $code) || $f['currency_symbol'] === '') {
            ops_flash('error', 'Give the name, a 2-letter country code, a 3-letter currency code and its symbol.');
            ops_redirect('countries');
        }
        if ($month <= 0 || $seat <= 0 || $book <= 0) {
            ops_flash('error', 'Give all three prices: a month, each extra person, and a Records Book.');
            ops_redirect('countries');
        }
        try {
            get_db()->prepare("INSERT INTO countries (name, iso_code, flag_emoji, currency_name, currency_code, currency_symbol, payment_method, payment_instructions,
                                                      momo_number, monthly_price, extra_seat_price, book_price, trial_days, is_active)
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
                    ->execute([$name, $iso, $f['flag_emoji'], trim((string)($_POST['currency_name'] ?? '')), $code, $f['currency_symbol'], $f['payment_method'],
                               $f['payment_instructions'], $f['momo_number'], $month, $seat, $book, $f['trial_days'], !empty($_POST['is_active']) ? 1 : 0]);
        } catch (PDOException $e) {
            if ($e->getCode() !== '23000') throw $e;
            ops_flash('error', 'There is already a country with the code ' . $iso . '.');
            ops_redirect('countries');
        }
        ops_audit('country.added', 'Added ' . $name . ' (' . $f['currency_symbol'] . number_format($month) . ' a month, ' . self::METHODS[$f['payment_method']] . ')');
        ops_flash('success', $name . ' added.');
        ops_redirect('countries');
    }

    public function edit(string $id): void
    {
        $db = get_db();
        $before = $db->query("SELECT * FROM countries WHERE id = " . (int)$id)->fetch();
        if (!$before) ops_not_found();
        $f = $this->fields();
        $db->prepare("UPDATE countries SET flag_emoji = COALESCE(?, flag_emoji), currency_symbol = COALESCE(NULLIF(?, ''), currency_symbol), payment_method = ?,
                             payment_instructions = ?, momo_number = ?, trial_days = ?, updated_at = NOW() WHERE id = ?")
           ->execute([$f['flag_emoji'], $f['currency_symbol'], $f['payment_method'], $f['payment_instructions'], $f['momo_number'], $f['trial_days'], (int)$id]);
        ops_audit('country.changed', 'Changed ' . $before['name'] . ': ' . $f['trial_days'] . '-day trial, ' . self::METHODS[$f['payment_method']],
                  ['data' => ['before' => array_intersect_key($before, $f)]]);
        ops_flash('success', $before['name'] . ' saved. New sign-ups and payments use it.');
        ops_redirect('countries');
    }

    public function toggle(string $id): void
    {
        $db = get_db();
        $c = $db->query("SELECT * FROM countries WHERE id = " . (int)$id)->fetch();
        if (!$c) ops_not_found();
        if ($c['iso_code'] === 'NG') {
            ops_flash('error', 'Nigeria is always offered: it is the first market.');
            ops_redirect('countries');
        }
        $on = $c['is_active'] ? 0 : 1;
        $db->prepare("UPDATE countries SET is_active = ?, updated_at = NOW() WHERE id = ?")->execute([$on, $c['id']]);
        ops_audit('country.toggled', ($on ? 'Offered ' : 'Stopped offering ') . $c['name'] . ' at sign-up');
        ops_flash('success', $c['name'] . ($on ? ' is offered at sign-up.' : ' is no longer offered at sign-up. Businesses already there carry on.'));
        ops_redirect('countries');
    }

    private function fields(): array
    {
        $method = (string)($_POST['payment_method'] ?? 'paystack');
        return [
            'flag_emoji'           => trim((string)($_POST['flag_emoji'] ?? '')) ?: null,
            'currency_symbol'      => trim((string)($_POST['currency_symbol'] ?? '')),
            'payment_method'       => isset(self::METHODS[$method]) ? $method : 'other',
            'payment_instructions' => trim((string)($_POST['payment_instructions'] ?? '')) ?: null,
            'momo_number'          => trim((string)($_POST['momo_number'] ?? '')) ?: null,
            'trial_days'           => max(1, min(90, (int)($_POST['trial_days'] ?? 14))),
        ];
    }
}
