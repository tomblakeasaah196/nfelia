<?php
/**
 * Nfelia — Settings Controller
 * Handles: Business Profile, User Profile, Staff Management, Billing.
 * Tabbed interface — all settings in one place.
 *
 * NFELIA FIX — Billing pricing now dynamic per country — 2026-06-04
 *
 * The currency follows the business's country (Tom, October 2026): Settings
 * shows it and never takes a symbol from the form.
 */
class SettingsController {

    private $db;
    private $biz_id;

    // Brand fonts come from brand_font_library() (app/helpers/brand.php). The
    // old font_pairing column is no longer offered; brand_doc_fonts() still
    // reads it for businesses that never picked brand fonts.

    public function __construct() {
        if (!is_logged_in()) { redirect('login'); }
        $this->db     = get_db();
        $this->biz_id = auth_business_id();
    }

    /** The business's own country (onboarding, or More › Country and currency), or null when it has none. */
    private function business_country(): ?array {
        $stmt = $this->db->prepare("
            SELECT c.name, c.iso_code, c.flag_emoji, c.currency_name, c.currency_code, c.currency_symbol
            FROM businesses b JOIN countries c ON c.id = b.country_id
            WHERE b.id = ?
        ");
        $stmt->execute([$this->biz_id]);
        return $stmt->fetch() ?: null;
    }

    /* ══════════════════════════════════════════
     *  MAIN SETTINGS PAGE (tabbed)
     * ══════════════════════════════════════════ */
    public function index() {
        $user     = auth_user();
        $business = auth_business();

        // Refresh full business data from DB (session may not have all columns)
        $stmt = $this->db->prepare("SELECT * FROM businesses WHERE id = ?");
        $stmt->execute([$this->biz_id]);
        $fresh = $stmt->fetch();
        if ($fresh) {
            $_SESSION['business'] = array_merge($_SESSION['business'] ?? [], $fresh);
            $business = $_SESSION['business'];
        }

        $tab = trim($_GET['tab'] ?? 'business');

        if (!in_array($tab, ['business', 'profile', 'staff', 'billing'])) {
            $tab = 'business';
        }

        if ($tab === 'staff' && ($user['role'] ?? 'staff') !== 'owner') {
            $tab = 'profile';
        }

        if ($tab === 'billing' && ($user['role'] ?? 'staff') !== 'owner') {
            $tab = 'profile';
        }

        // Load subscription
        $subscription = $this->get_subscription();

        // Load staff for staff tab
        $staff = [];
        if ($tab === 'staff') {
            $stmt = $this->db->prepare("
                SELECT id, full_name, email, avatar_path, role, created_at, last_login_at
                FROM users
                WHERE business_id = ? AND is_active = 1
                ORDER BY FIELD(role, 'owner', 'admin', 'staff'), full_name ASC
            ");
            $stmt->execute([$this->biz_id]);
            $staff = $stmt->fetchAll();
        }

        // Payment history + country pricing for billing tab
        $payment_history = [];
        $min_extra_seats = 0;
        $country_pricing = null;
        if ($tab === 'billing') {
            $stmt = $this->db->prepare("
                SELECT * FROM subscription_history
                WHERE business_id = ? AND event_type IN ('charge.success', 'invoice.payment_failed', 'subscription.disable', 'manual_activation')
                ORDER BY created_at DESC LIMIT 20
            ");
            $stmt->execute([$this->biz_id]);
            $payment_history = $stmt->fetchAll();

            $stmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE business_id = ? AND is_active = 1");
            $stmt->execute([$this->biz_id]);
            $min_extra_seats = max(0, (int)$stmt->fetchColumn() - 1);

            // Load country-specific pricing for this business
            require_once __DIR__ . '/CountryController.php';
            $country_pricing = CountryController::get_country_for_business($this->db, $this->biz_id);
        }

        // Paying: prices, who a month can cover, when it would run, and any
        // Mobile Money receipt still being checked
        $prices = $team = $waiting_payment = null;
        $access = billing_access($subscription);
        [$next_start, $next_end] = billing_next_period($subscription);
        if ($tab === 'billing') {
            $prices = billing_prices($this->db, $this->biz_id);
            $team   = billing_team($this->db, $this->biz_id);
            $stmt   = $this->db->prepare("SELECT * FROM subscription_payments WHERE business_id = ? AND status = 'pending_verification' ORDER BY id DESC LIMIT 1");
            $stmt->execute([$this->biz_id]);
            $waiting_payment = $stmt->fetch() ?: null;
        }

        // Fallback pricing if no country is set
        if ($tab === 'billing' && !$country_pricing) {
            $country_pricing = [
                'currency_symbol'  => '₦',
                'currency_code'    => 'NGN',
                'monthly_price'    => 10000,
                'extra_seat_price' => 5000,
                'name'             => 'Nigeria',
                'flag_emoji'       => '🇳🇬',
            ];
        }

        // The currency Business details shows, from the country
        $country = $tab === 'business' ? $this->business_country() : null;

        $title     = "Settings";
        $view_file = BASE_PATH . '/views/settings/index.php';
        require_once BASE_PATH . '/views/layouts/app.php';
    }

    /* ──────────────────────────────────────────
     *  UPDATE BUSINESS PROFILE — POST
     * ────────────────────────────────────────── */
    /**
     * POST settings/doc-prefix: the letters every order and document number
     * starts with. Only new numbers change; each kind keeps counting on
     * (CAS-INV-031, then RNA-INV-032), so nothing already sent is wrong.
     */
    public function doc_prefix() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('settings'); return; }
        csrf_verify();
        if ((auth_user()['role'] ?? 'staff') !== 'owner') {
            flash('error', 'Only the business owner can change the document numbers.');
            redirect('settings#numbers');
            return;
        }
        $prefix = strtoupper(trim((string)($_POST['doc_prefix'] ?? '')));
        if (($problem = doc_prefix_problem($prefix)) !== null) {
            flash('error', $problem);
            redirect('settings#numbers');
            return;
        }
        $stmt = $this->db->prepare("SELECT doc_prefix FROM businesses WHERE id = ?");
        $stmt->execute([$this->biz_id]);
        $old = (string)$stmt->fetchColumn();
        if ($prefix === $old) {
            flash('info', 'Your numbers already start with ' . $prefix . '.');
            redirect('settings#numbers');
            return;
        }
        // A prefix chosen by hand answers the name as it is now: no suggestion until a rename
        $stmt = $this->db->prepare("SELECT name FROM businesses WHERE id = ?");
        $stmt->execute([$this->biz_id]);
        $this->db->prepare("UPDATE businesses SET doc_prefix = ?, prefix_nudge_seen = ? WHERE id = ?")
                 ->execute([$prefix, derive_prefix((string)$stmt->fetchColumn()), $this->biz_id]);
        update_business_session(['doc_prefix' => $prefix]);
        flash('success', 'New numbers start with ' . $prefix . ', like ' . $prefix . '-ORD-' . $this->next_number('ORD') . '. Papers already sent keep ' . $old . '.');
        redirect('settings#numbers');
    }

    /** POST settings/doc-prefix-keep: after a rename, keep the prefix as it is (the suggestion stops showing). */
    public function doc_prefix_keep() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('settings'); return; }
        csrf_verify();
        if ((auth_user()['role'] ?? 'staff') === 'owner') {
            $stmt = $this->db->prepare("SELECT name FROM businesses WHERE id = ?");
            $stmt->execute([$this->biz_id]);
            $this->db->prepare("UPDATE businesses SET prefix_nudge_seen = ? WHERE id = ?")
                     ->execute([derive_prefix((string)$stmt->fetchColumn()), $this->biz_id]);
        }
        redirect('settings#numbers');
    }

    /** The next number of a kind, as its digits ("013"). */
    private function next_number(string $type): string {
        $stmt = $this->db->prepare("SELECT last_number FROM document_counters WHERE business_id = ? AND doc_type = ?");
        $stmt->execute([$this->biz_id, $type]);
        return str_pad((string)((int)$stmt->fetchColumn() + 1), 3, '0', STR_PAD_LEFT);
    }

    /** The biggest logo that can be sent, in MB: save_image() takes 20, or less when the server takes less. */
    public static function logo_max_mb(): int {
        $server = upload_limit_mb();
        return $server ? min(20, $server) : 20;
    }

    public function update_business() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('settings'); return; }
        csrf_verify();   // a logo too big for the whole form is told there

        $user = auth_user();
        if (($user['role'] ?? 'staff') !== 'owner') {
            flash('error', 'Only the business owner can update business settings.');
            redirect('settings');
            return;
        }

        $name            = trim($_POST['business_name'] ?? '');
        $tagline         = trim($_POST['tagline'] ?? '');
        $phone           = trim($_POST['phone'] ?? '');
        // The shop's WhatsApp buttons use it before the phone (shop_whatsapp_digits)
        $whatsapp        = trim($_POST['whatsapp'] ?? '');
        $email           = trim($_POST['email'] ?? '');
        $address         = trim($_POST['address'] ?? '');
        $primary_color   = trim($_POST['primary_color'] ?? '#1a1a2e');
        $secondary_color = trim($_POST['secondary_color'] ?? '#B69F5E');   // as at sign-up
        // The kind of business, from BUSINESS_CATEGORIES; left as it is when not sent
        $category        = array_key_exists((string)($_POST['category'] ?? ''), BUSINESS_CATEGORIES) ? (string)$_POST['category'] : null;
        // What the business sells, as at onboarding; it names the deed
        // (services: a certificate of completion). Left as it is when not sent.
        $business_type   = in_array($_POST['business_type'] ?? '', ['product', 'service', 'mixed'], true) ? $_POST['business_type'] : null;
        // Brand fonts from the library (app/helpers/brand.php); empty keeps none
        $fonts     = brand_font_library();
        $font_head = trim((string)($_POST['brand_font_head'] ?? ''));
        $font_body = trim((string)($_POST['brand_font_body'] ?? ''));
        $font_head = isset($fonts[$font_head]) && $fonts[$font_head]['head'] !== null ? $font_head : null;
        $font_body = isset($fonts[$font_body]) && $fonts[$font_body]['body'] ? $font_body : null;

        if ($name === '') {
            flash('error', 'Business name is required.');
            redirect('settings?tab=business');
            return;
        }
        if ($p = phone_problem($phone) ?? phone_problem($whatsapp)) {   // D4
            flash('error', $p);
            redirect('settings?tab=business');
            return;
        }

        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $primary_color)) $primary_color = '#1a1a2e';
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $secondary_color)) $secondary_color = '#B69F5E';

        // The currency is the country's, never what the form says; a business
        // with no country keeps the one it has
        $country = $this->business_country();

        $logo_path  = null;
        $logo_error = $_FILES['logo']['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($logo_error !== UPLOAD_ERR_OK && $logo_error !== UPLOAD_ERR_NO_FILE) {
            flash('error', in_array($logo_error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
                ? 'Nothing was saved: that logo is over ' . self::logo_max_mb() . ' MB. Pick a smaller picture and save again.'
                : 'Nothing was saved: your logo didn\'t arrive. Pick it again and save.');
            redirect('settings?tab=business');
            return;
        }
        if ($logo_error === UPLOAD_ERR_OK) {
            $dir = BASE_PATH . '/uploads/logos/' . $this->biz_id;
            if (!is_dir($dir)) mkdir($dir, 0755, true);

            $filename = 'logo_' . time() . '.png';
            if (save_image($_FILES['logo'], $dir . '/' . $filename, 1024)) {
                $logo_path = 'uploads/logos/' . $this->biz_id . '/' . $filename;
            } else {
                flash('error', 'Nothing was saved: we couldn\'t use that logo. Pick a JPG, PNG or WebP picture up to ' . self::logo_max_mb() . ' MB.');
                redirect('settings?tab=business');
                return;
            }
        }

        $remove_logo = isset($_POST['remove_logo']);

        // A rename: remember what the old name suggested, so the Document
        // numbers card offers a new prefix only because the name changed
        $stmt_was = $this->db->prepare("SELECT name, prefix_nudge_seen, category, storefront_look FROM businesses WHERE id = ?");
        $stmt_was->execute([$this->biz_id]);
        $was = $stmt_was->fetch() ?: [];
        if ($name !== '' && ($was['name'] ?? '') !== $name && ($was['prefix_nudge_seen'] ?? null) === null) {
            $this->db->prepare("UPDATE businesses SET prefix_nudge_seen = ? WHERE id = ?")
                     ->execute([derive_prefix((string)$was['name']), $this->biz_id]);
        }

        $sql = "UPDATE businesses SET
                    name = ?, tagline = ?, phone = ?, whatsapp = ?, email = ?,
                    address = ?, primary_color = ?, secondary_color = ?,
                    brand_font_head = ?, brand_font_body = ?";
        $params = [$name, $tagline ?: null, $phone ?: null, $whatsapp ?: null, $email ?: null,
                   $address ?: null, $primary_color, $secondary_color,
                   $font_head, $font_body];

        $session_updates = [
            'name'            => $name,
            'primary_color'   => $primary_color,
            'secondary_color' => $secondary_color,
            'brand_font_head' => $font_head,
            'brand_font_body' => $font_body,
            'tagline'         => $tagline,
            'address'         => $address, 
            'phone'           => $phone,
            'whatsapp'        => $whatsapp,
            'email'           => $email
        ];

        if ($country && $country['currency_symbol'] !== '') {
            $sql .= ", currency_symbol = ?";
            $params[] = $country['currency_symbol'];
            $session_updates['currency_symbol'] = $country['currency_symbol'];
        }

        if ($business_type !== null) {
            $sql .= ", business_type = ?";
            $params[] = $business_type;
            $session_updates['business_type'] = $business_type;
        }

        if ($category !== null) {
            $sql .= ", category = ?";
            $params[] = $category;
            $session_updates['category'] = $category;
            // The category only suggests a look to a shop that never chose
            // one (storefront_default_look). Shop Studio stays in charge of
            // the look, so such a shop keeps the one it shows today.
            if ($category !== ($was['category'] ?? null) && !isset(STOREFRONT_LOOKS[(string)($was['storefront_look'] ?? '')])) {
                $look = storefront_default_look($was);
                $sql .= ", storefront_look = ?";
                $params[] = $look;
                $session_updates['storefront_look'] = $look;
            }
        }

        if ($logo_path) {
            $sql .= ", logo_path = ?";
            $params[] = $logo_path;
            $session_updates['logo_path'] = $logo_path;
        } elseif ($remove_logo) {
            $sql .= ", logo_path = NULL";
            $session_updates['logo_path'] = null;
        }

        $sql .= " WHERE id = ?";
        $params[] = $this->biz_id;

        $stmtOld = $this->db->prepare("SELECT logo_path FROM businesses WHERE id = ?");
        $stmtOld->execute([$this->biz_id]);
        $old_logo = (string)$stmtOld->fetchColumn();

        $this->db->prepare($sql)->execute($params);

        // A replaced or removed logo, and its copies, stop being public (W5)
        if (($logo_path || $remove_logo) && $old_logo !== '' && $old_logo !== $logo_path) {
            storefront_forget_image($old_logo);
        }

        update_business_session($session_updates);
        storefront_purge((int)$this->biz_id);   // the shop shows the name, logo and contact details

        flash('success', 'Business profile updated.');
        redirect('settings?tab=business');
    }

    /* ──────────────────────────────────────────
     *  PAYMENT ACCOUNTS — POST (views/settings/payment_accounts.php)
     *  The bank accounts and Mobile Money numbers customers pay into: the
     *  shop, its checkout and bookings, invoices and quotes. Owner only: a
     *  changed number changes where the money goes.
     * ────────────────────────────────────────── */
    public const MAX_PAY_ACCOUNTS = 10;

    /** POST settings/save-account[/id]: add an account, or change one. */
    public function save_account($id = null) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('settings#payments'); return; }
        csrf_verify();
        if ((auth_user()['role'] ?? 'staff') !== 'owner') {
            flash('error', 'Only the business owner can change the accounts customers pay into.');
            redirect('settings#payments');
            return;
        }
        $was = $id !== null ? $this->pay_account((int)$id) : null;
        if ($id !== null && !$was) {
            flash('error', 'That account is no longer on your list.');
            redirect('settings#payments');
            return;
        }
        $id = $was ? (int)$was['id'] : null;

        [$account, $problem] = $this->pay_account_from_post();
        $active = $this->pay_accounts_count();
        if ($problem === null && !$id && $active >= self::MAX_PAY_ACCOUNTS) {
            $problem = 'You have ' . self::MAX_PAY_ACCOUNTS . ' accounts, the most there can be. Remove one you no longer use, then add this one.';
        }
        if ($problem === null) {
            $stmt = $this->db->prepare("
                SELECT bank_name FROM bank_accounts
                WHERE business_id = ? AND is_active = 1 AND deleted_at IS NULL AND id <> ?
                  AND REPLACE(REPLACE(account_number, ' ', ''), '-', '') = ?
                LIMIT 1
            ");
            $stmt->execute([$this->biz_id, $id ?? 0, preg_replace('/[\s-]/', '', $account['account_number'])]);
            if (($same = $stmt->fetchColumn()) !== false) {
                $problem = 'That number is already on your list, as ' . $same . '. Change that one instead.';
            }
        }
        if ($problem !== null) {
            // Back to the open sheet, with what was typed and why it wasn't saved
            $_SESSION['pay_draft'] = ['for' => $id ?: 'new', 'problem' => $problem] + $account;
            redirect('settings?tab=business#sheet:nf-pay-' . ($id ?: 'new'));
            return;
        }

        if ($id) {
            $this->db->prepare("
                UPDATE bank_accounts SET kind = ?, bank_name = ?, account_number = ?, account_name = ?
                WHERE id = ? AND business_id = ? AND deleted_at IS NULL
            ")->execute([$account['kind'], $account['bank_name'], $account['account_number'], $account['account_name'], $id, $this->biz_id]);
        } else {
            $stmt = $this->db->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM bank_accounts WHERE business_id = ? AND deleted_at IS NULL");
            $stmt->execute([$this->biz_id]);
            $this->db->prepare("
                INSERT INTO bank_accounts (business_id, kind, bank_name, account_number, account_name, is_default, sort_order)
                VALUES (?, ?, ?, ?, ?, 0, ?)
            ")->execute([$this->biz_id, $account['kind'], $account['bank_name'], $account['account_number'], $account['account_name'],
                         min(127, (int)$stmt->fetchColumn())]);
            $id = (int)$this->db->lastInsertId();
        }
        // The first account is the main one; another becomes it only when asked
        if ($account['is_default'] || $active === 0) {
            $this->pay_make_main($id);
        }
        $this->pay_keep_a_main();
        storefront_purge((int)$this->biz_id);   // the shop shows them

        flash('success', $was ? 'Saved ' . $account['bank_name'] . '.'
            : 'Added ' . $account['bank_name'] . '. Customers see it on your shop, and you can pick it on invoices and quotes.');
        redirect('settings#payments');
    }

    /** POST settings/remove-account/id: customers stop seeing it. Papers already sent keep showing it. */
    public function remove_account($id = null) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('settings#payments'); return; }
        csrf_verify();
        if ((auth_user()['role'] ?? 'staff') !== 'owner') {
            flash('error', 'Only the business owner can change the accounts customers pay into.');
            redirect('settings#payments');
            return;
        }
        $account = $this->pay_account((int)$id);
        if (!$account) {
            flash('info', 'That account was already removed.');
            redirect('settings#payments');
            return;
        }
        $this->db->prepare("UPDATE bank_accounts SET deleted_at = NOW(), is_default = 0 WHERE id = ? AND business_id = ?")
                 ->execute([(int)$account['id'], $this->biz_id]);
        $this->pay_keep_a_main();
        storefront_purge((int)$this->biz_id);

        flash('success', 'Removed ' . $account['bank_name'] . ' ' . $account['account_number'] . '. Invoices and quotes already sent still show it.');
        redirect('settings#payments');
    }

    /** One of this business's accounts still on its list. */
    private function pay_account(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM bank_accounts WHERE id = ? AND business_id = ? AND is_active = 1 AND deleted_at IS NULL");
        $stmt->execute([$id, $this->biz_id]);
        return $stmt->fetch() ?: null;
    }

    private function pay_accounts_count(): int {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM bank_accounts WHERE business_id = ? AND is_active = 1 AND deleted_at IS NULL");
        $stmt->execute([$this->biz_id]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Examples for the account sheet and its messages, from the business's
     * country (its ISO code, '' when not known): a Lagos shop is paid into
     * Opay or GTBank, not Orange Money. No number example when not known.
     * @return array{bank: string, momo: string, number: string}
     */
    public static function pay_examples(string $iso): array {
        return match (strtoupper($iso)) {
            'NG'    => ['bank' => 'GTBank', 'momo' => 'Opay, Moniepoint or PalmPay', 'number' => '0803 123 4567'],
            'CM'    => ['bank' => 'Ecobank', 'momo' => 'MTN MoMo or Orange Money', 'number' => '670 32 59 25'],
            default => ['bank' => 'Ecobank', 'momo' => 'MTN MoMo or Airtel Money', 'number' => ''],
        };
    }

    /** The business's country as its ISO code ("NG"), or '' when not known. */
    private function country_iso(): string {
        $stmt = $this->db->prepare("SELECT c.iso_code FROM businesses b JOIN countries c ON c.id = b.country_id WHERE b.id = ?");
        $stmt->execute([$this->biz_id]);
        return strtoupper((string)$stmt->fetchColumn());
    }

    /**
     * The account as typed, tidied, and what stops it being saved (null when
     * nothing does).
     * @return array{0: array{kind: string, bank_name: string, account_number: string, account_name: string, is_default: bool}, 1: ?string}
     */
    private function pay_account_from_post(): array {
        $tidy = fn(string $key) => trim((string)preg_replace('/\s+/u', ' ', (string)($_POST[$key] ?? '')));
        $account = [
            'kind'           => ($_POST['kind'] ?? '') === 'mobile_money' ? 'mobile_money' : 'bank',
            'bank_name'      => $tidy('bank_name'),
            'account_number' => $tidy('account_number'),
            'account_name'   => $tidy('account_name'),
            'is_default'     => !empty($_POST['is_default']),
        ];
        $momo    = $account['kind'] === 'mobile_money';
        $digits  = preg_replace('/[\s-]/', '', $account['account_number']);
        $iso     = $this->country_iso();
        $example = self::pay_examples($iso);
        $problem = match (true) {
            $account['bank_name'] === ''           => $momo ? 'Say which Mobile Money it is, like ' . $example['momo'] . '.' : 'Say which bank the account is with.',
            mb_strlen($account['bank_name']) > 100 => 'Keep the name of the ' . ($momo ? 'network' : 'bank') . ' under 100 characters.',
            $account['account_number'] === ''      => $momo ? 'Type the Mobile Money number.' : 'Type the account number.',
            $momo && !preg_match('/^\+?\d{6,15}$/', $digits)
                => 'Type the Mobile Money number with digits only' . ($example['number'] !== '' ? ', like ' . $example['number'] : '') . '.',
            !$momo && (strlen($account['account_number']) > 40 || !preg_match('/^[A-Za-z0-9 -]+$/', $account['account_number']))
                => 'Type the account number with letters, digits and spaces only, up to 40.',
            // Nigerian account numbers (NUBAN) are 10 digits; Opay and PalmPay
            // use the phone number without its first 0
            !$momo && $iso === 'NG' && !preg_match('/^\d{10}$/', $digits)
                => strlen($digits) === 11 && $digits[0] === '0'
                    ? 'That looks like a phone number. Nigerian account numbers have 10 digits: for Opay or PalmPay, leave off the first 0.'
                    : 'Nigerian account numbers have 10 digits' . (ctype_digit($digits) ? ', and this one has ' . strlen($digits) : '') . '. Check it and type it again.',
            $account['account_name'] === ''        => 'Type the name on the account, so customers can see it is yours.',
            mb_strlen($account['account_name']) > 100 => 'Keep the name on the account under 100 characters.',
            default => null,
        };
        return [$account, $problem];
    }

    private function pay_make_main(int $id): void {
        $this->db->prepare("UPDATE bank_accounts SET is_default = (id = ?) WHERE business_id = ? AND deleted_at IS NULL")
                 ->execute([$id, $this->biz_id]);
    }

    /**
     * Whenever there are accounts, exactly one is the main one: the first in
     * line (onboarding done twice left some businesses with two).
     */
    private function pay_keep_a_main(): void {
        $stmt = $this->db->prepare("
            SELECT id FROM bank_accounts
            WHERE business_id = ? AND is_active = 1 AND deleted_at IS NULL
            ORDER BY is_default DESC, sort_order ASC, id ASC
            LIMIT 1
        ");
        $stmt->execute([$this->biz_id]);
        if ($first = (int)$stmt->fetchColumn()) {
            $this->pay_make_main($first);
        }
    }

    /* ──────────────────────────────────────────
     *  UPDATE USER PROFILE — POST
     * ────────────────────────────────────────── */
    public function update_profile() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('settings?tab=profile'); return; }
        csrf_verify();

        $user = auth_user();

        $full_name = trim($_POST['full_name'] ?? '');
        $email     = trim($_POST['email'] ?? '');

        if ($full_name === '' || $email === '') {
            flash('error', 'Name and email are required.');
            redirect('settings?tab=profile');
            return;
        }

        // Check email uniqueness
        $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ? AND id != ? AND is_active = 1");
        $stmt->execute([$email, $user['id']]);
        if ($stmt->fetch()) {
            flash('error', 'This email is already in use.');
            redirect('settings?tab=profile');
            return;
        }

        $avatar_path = null;
        $base64_data = $_POST['avatar_base64'] ?? '';

        // Priority 1: Handle Cropper.js Base64 Payload
        if (!empty($base64_data)) {
            $parts = explode(',', $base64_data);

            if (count($parts) === 2) {
                $raw_data = base64_decode($parts[1]);

                if ($raw_data !== false) {
                    $img_info = @getimagesizefromstring($raw_data);
                    if (!$img_info || !in_array($img_info['mime'], ['image/jpeg', 'image/png', 'image/webp'])) {
                        flash('error', 'Invalid image data. Please try again.');
                        redirect('settings?tab=profile');
                        return;
                    }

                    if (strlen($raw_data) > 512 * 1024) {
                        flash('error', 'Avatar too large. Max 512KB.');
                        redirect('settings?tab=profile');
                        return;
                    }

                    $dir = BASE_PATH . '/uploads/avatars/' . $this->biz_id;
                    if (!is_dir($dir)) mkdir($dir, 0755, true);

                    $filename = 'avatar_' . $user['id'] . '_' . time() . '.jpg';
                    $filepath = $dir . '/' . $filename;

                    if (file_put_contents($filepath, $raw_data)) {
                        $avatar_path = 'uploads/avatars/' . $this->biz_id . '/' . $filename;
                    } else {
                        flash('error', 'Failed to save cropped avatar to the server.');
                        redirect('settings?tab=profile');
                        return;
                    }
                }
            }
        } 
        // Priority 2: Fallback to standard upload if JS failed
        elseif (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $dir = BASE_PATH . '/uploads/avatars/' . $this->biz_id;
            if (!is_dir($dir)) mkdir($dir, 0755, true);

            $filename = 'avatar_' . $user['id'] . '_' . time() . '.jpg';
            if (save_image($_FILES['avatar'], $dir . '/' . $filename, 512)) {
                $avatar_path = 'uploads/avatars/' . $this->biz_id . '/' . $filename;
            } else {
                flash('error', 'Avatar upload failed.');
                redirect('settings?tab=profile');
                return;
            }
        }

        $remove_avatar = isset($_POST['remove_avatar']);

        $stmt = $this->db->prepare("SELECT email FROM users WHERE id = ?");
        $stmt->execute([$user['id']]);
        $old_email = (string)$stmt->fetchColumn();

        $sql    = "UPDATE users SET full_name = ?, email = ?";
        $params = [$full_name, $email];

        if ($avatar_path) {
            $sql .= ", avatar_path = ?";
            $params[] = $avatar_path;
        } elseif ($remove_avatar) {
            $sql .= ", avatar_path = NULL";
        }

        $sql .= " WHERE id = ?";
        $params[] = $user['id'];

        $this->db->prepare($sql)->execute($params);

        // A changed sign-in email: the new address, the person's phones and
        // the OLD address all hear, so a stolen account is noticed.
        if (strcasecmp($old_email, $email) !== 0) {
            notify('security.email_changed', $this->biz_id, ['new_email' => $email], ['users' => [(int)$user['id']], 'dedupe' => (string)time()]);
            notify_email(null, $old_email, $full_name, 'Your Nfelia sign-in email was changed', 'notification', [
                'headline' => 'Your sign-in email was changed',
                'message'  => 'Your Nfelia account now signs in with ' . $email . ' instead of this address. If you didn’t do this, contact Nfelia support straight away.',
                'lines'    => ['New email' => $email, 'When' => planner_now()->format('j M Y, g:ia')],
                'button'   => 'Contact support',
                'open_url' => 'mailto:support@nfelia.com',
                'always'   => true,
            ], 'email-changed:' . $user['id'] . ':' . time(), 'security.email_changed_old');
        }

        $_SESSION['user']['full_name'] = $full_name;
        $_SESSION['user']['email']     = $email;
        if ($avatar_path) $_SESSION['user']['avatar_path'] = $avatar_path;
        if ($remove_avatar) unset($_SESSION['user']['avatar_path']);

        flash('success', 'Profile updated successfully.');
        redirect('settings?tab=profile');
    }

    /* ──────────────────────────────────────────
     *  CHANGE PASSWORD — POST
     * ────────────────────────────────────────── */
    public function change_password() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('settings?tab=profile'); return; }
        csrf_verify();

        $user = auth_user();

        $current  = $_POST['current_password'] ?? '';
        $new_pass = $_POST['new_password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';

        if ($problem = new_password_problem($new_pass, $confirm)) {
            flash('error', $problem);
            redirect('settings?tab=profile');
            return;
        }

        $stmt = $this->db->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$user['id']]);
        $hash = $stmt->fetchColumn();

        if (!password_verify($current, $hash)) {
            flash('error', 'Current password is incorrect.');
            redirect('settings?tab=profile');
            return;
        }

        $this->db->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([
            password_hash($new_pass, PASSWORD_BCRYPT, ['cost' => 12]),
            $user['id']
        ]);
        // Every other browser and kept device is signed out; this one carries on.
        session_epoch_bump((int)$user['id'], true);
        // Told on every device and by email, in case it wasn't them.
        notify('security.password_changed', $this->biz_id, [], ['users' => [(int)$user['id']], 'dedupe' => (string)time()]);

        flash('success', 'Password changed successfully.');
        redirect('settings?tab=profile');
    }

    /* ──────────────────────────────────────────
     *  STAFF: ADD MEMBER — POST
     * ────────────────────────────────────────── */
    public function add_staff() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('settings?tab=staff'); return; }
        csrf_verify();

        $user = auth_user();
        if (($user['role'] ?? 'staff') !== 'owner') {
            flash('error', 'Only the business owner can manage staff.');
            redirect('settings?tab=staff');
            return;
        }

        $subscription = $this->get_subscription();
        $max_seats = billing_seats($subscription);   // a free trial includes one staff member

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE business_id = ? AND is_active = 1");
        $stmt->execute([$this->biz_id]);
        $current_staff = (int)$stmt->fetchColumn();

        if ($current_staff >= $max_seats) {
            flash('error', billing_access($subscription) === 'trial'
                ? 'Your free trial includes ' . TRIAL_STAFF_SEATS . ' staff member' . (TRIAL_STAFF_SEATS === 1 ? '' : 's') . '. To add more, pay for a month with more seats under Billing.'
                : 'Every seat you pay for is taken (' . $max_seats . ' people, with you). Add seats under Billing to add someone new.');
            redirect('settings?tab=staff');
            return;
        }

        $full_name = trim($_POST['staff_name'] ?? '');
        $email     = trim($_POST['staff_email'] ?? '');
        $password  = trim($_POST['staff_password'] ?? '');

        if ($full_name === '' || $email === '' || $password === '') {
            flash('error', 'Name, email, and password are required.');
            redirect('settings?tab=staff');
            return;
        }

        if ($problem = new_password_problem($password)) {
            flash('error', $problem);
            redirect('settings?tab=staff');
            return;
        }

        $stmt = $this->db->prepare("SELECT id, business_id, is_active, role FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $existing = $stmt->fetch();

        if ($existing && ((int)$existing['is_active'] || $existing['role'] === 'owner')) {
            if ((int)$existing['business_id'] === $this->biz_id) {
                flash('error', 'This email is already part of your team.');
            } else {
                flash('error', 'This email is already registered. Ask them to log in or use a different email.');
            }
            redirect('settings?tab=staff');
            return;
        }

        $role = in_array($_POST['staff_role'] ?? '', ['admin', 'staff']) ? $_POST['staff_role'] : 'staff';
        $this->db->beginTransaction();
        try {
            if ($existing && (int)$existing['business_id'] === $this->biz_id) {
                // Someone who was removed comes back: same login, new password
                $this->db->prepare("
                    UPDATE users SET full_name = ?, password_hash = ?, role = ?, is_active = 1
                    WHERE id = ? AND business_id = ?
                ")->execute([
                    $full_name, password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]), $role,
                    (int)$existing['id'], $this->biz_id,
                ]);
            } else {
                if ($existing) {
                    free_removed_email($this->db, (int)$existing['id']);   // removed from another business
                }
                $this->db->prepare("
                    INSERT INTO users (business_id, full_name, email, password_hash, role, created_at)
                    VALUES (?, ?, ?, ?, ?, NOW())
                ")->execute([
                    $this->biz_id, $full_name, $email,
                    password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
                    $role
                ]);
            }
            $member_id = $existing && (int)$existing['business_id'] === $this->biz_id ? (int)$existing['id'] : (int)$this->db->lastInsertId();
            $this->db->commit();

            // The rest of the team hears; the new member gets a welcome with
            // how to sign in (never the password: the owner tells them that).
            notify('team.joined', $this->biz_id, ['name' => $full_name, 'role' => $role, 'by' => planner_first_name($user['full_name'])],
                   ['actor' => (int)$user['id'], 'dedupe' => (string)$member_id]);
            $business = auth_business();
            notify_email(null, $email, $full_name, 'You’ve joined ' . $business['name'] . ' on Nfelia', 'notification', [
                'headline' => 'Welcome to ' . $business['name'],
                'message'  => planner_first_name($user['full_name']) . ' added you to their team on Nfelia as ' . ($role === 'admin' ? 'an admin' : 'staff')
                            . '. Sign in with this email address and the password ' . planner_first_name($user['full_name'])
                            . ' gives you, or choose “Email me a sign-in link”.',
                'lines'    => ['Business' => $business['name'], 'Your email' => $email],
                'button'   => 'Sign in to Nfelia',
                'open_url' => APP_URL . '/login',
                'always'   => true,
            ], 'staff-welcome:' . $member_id, 'team.welcome');

            flash('success', $full_name . ' added to your team. We emailed them how to sign in.');
        } catch (\Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            error_log('Add staff failed: ' . $e->getMessage());
            flash('error', 'Failed to add team member. Please try again.');
        }

        redirect('settings?tab=staff');
    }

    /* ──────────────────────────────────────────
     *  STAFF: REMOVE MEMBER — POST
     * ────────────────────────────────────────── */
    public function remove_staff($id = null) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('settings?tab=staff'); return; }
        csrf_verify();

        $user = auth_user();
        if (($user['role'] ?? 'staff') !== 'owner') {
            flash('error', 'Only the business owner can manage staff.');
            redirect('settings?tab=staff');
            return;
        }

        $staff_id = (int)$id;
        if ($staff_id === (int)$user['id']) {
            flash('error', 'You cannot remove yourself.');
            redirect('settings?tab=staff');
            return;
        }

        $stmt = $this->db->prepare("
            SELECT full_name FROM users
            WHERE id = ? AND business_id = ? AND is_active = 1
        ");
        $stmt->execute([$staff_id, $this->biz_id]);
        $staff_member = $stmt->fetch();

        if (!$staff_member) {
            flash('error', 'Team member not found.');
            redirect('settings?tab=staff');
            return;
        }

        $this->db->prepare("UPDATE users SET is_active = 0 WHERE id = ? AND business_id = ?")->execute([$staff_id, $this->biz_id]);
        // Their phones stop getting the business's alerts, and "keep me
        // signed in" stops working for them.
        $this->db->prepare("DELETE FROM push_subscriptions WHERE user_id = ?")->execute([$staff_id]);
        $this->db->prepare("DELETE FROM sessions WHERE user_id = ?")->execute([$staff_id]);
        // Their open tasks come to the owner, so nothing is stranded (P1)
        $handed = planner_hand_back_tasks($this->db, $this->biz_id);
        notify('team.removed', $this->biz_id, ['name' => $staff_member['full_name'], 'by' => planner_first_name($user['full_name'])],
               ['actor' => (int)$user['id'], 'dedupe' => $staff_id . ':' . time()]);

        flash('success', $staff_member['full_name'] . ' removed from your team.'
            . ($handed ? ' ' . $handed . ' open task' . ($handed === 1 ? ' is' : 's are') . ' now on your list.' : ''));
        redirect('settings?tab=staff');
    }

    /* ──────────────────────────────────────────
     *  STAFF: SEND PASSWORD RESET LINK — POST
     *  The link goes to the staff member's own inbox; the owner never sees
     *  or chooses their password.
     * ────────────────────────────────────────── */
    public function send_staff_reset($id = null) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('settings?tab=staff'); return; }
        csrf_verify();

        $user = auth_user();
        if (($user['role'] ?? 'staff') !== 'owner') {
            flash('error', 'Only the business owner can manage staff.');
            redirect('settings?tab=staff');
            return;
        }

        $staff_id = (int)$id;
        if ($staff_id === (int)$user['id']) {
            flash('error', 'To change your own password, use Change Password on your profile.');
            redirect('settings?tab=staff');
            return;
        }

        $stmt = $this->db->prepare("
            SELECT id, full_name, email FROM users
            WHERE id = ? AND business_id = ? AND is_active = 1
        ");
        $stmt->execute([$staff_id, $this->biz_id]);
        $staff_member = $stmt->fetch();

        if (!$staff_member) {
            flash('error', 'Team member not found.');
            redirect('settings?tab=staff');
            return;
        }

        if (!throttle_allows('staff_reset', (string)$staff_id, 3, 60)) {
            flash('error', 'A reset link was already sent to ' . $staff_member['full_name'] . ' recently. Ask them to check their inbox and spam folder.');
            redirect('settings?tab=staff');
            return;
        }

        // A day rather than an hour: the staff member may not see it at once.
        $token = account_token_issue($staff_id, 'reset', 60 * 24);
        $sent  = send_branded_mail(
            $staff_member['email'], $staff_member['full_name'],
            'Password Reset — Nfelia Workspace',
            'password_reset',
            [
                'reset_url'     => APP_URL . '/reset-password/' . $token,
                'expires_label' => '24 hours',
                'requested_by'  => $user['full_name'] ?? 'Your workspace owner',
            ],
            ['name' => 'Nfelia', 'primary_color' => '#8B004A']
        );

        if ($sent) {
            flash('success', 'Reset link sent to ' . $staff_member['email'] . '.');
        } else {
            flash('error', 'The email could not be sent. Please try again shortly.');
        }
        redirect('settings?tab=staff');
    }

    /* ──────────────────────────────────────────
     *  GENERATE AUTO-LOGO SVG
     * ────────────────────────────────────────── */
    public function generate_logo() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('settings?tab=business'); return; }
        csrf_verify();

        // The owner's alone, like the rest of the business's look (update_business)
        if ((auth_user()['role'] ?? 'staff') !== 'owner') {
            flash('error', 'Only the business owner can update business settings.');
            redirect('settings');
            return;
        }

        $stmt = $this->db->prepare("SELECT name, primary_color, secondary_color, logo_path FROM businesses WHERE id = ?");
        $stmt->execute([$this->biz_id]);
        $business = $stmt->fetch();

        $name      = $business['name'] ?? 'BIZ';
        ['primary' => $primary, 'secondary' => $secondary] = brand_doc_colours($business ?: []);   // the brand colours, else Nfelia's

        $words = preg_split('/\s+/', trim($name));
        $initials = '';
        foreach ($words as $w) {
            $initials .= mb_strtoupper(mb_substr($w, 0, 1));
            if (mb_strlen($initials) >= 2) break;
        }
        if ($initials === '') $initials = 'N';

        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="400" height="400" viewBox="0 0 400 400">
    <defs>
        <linearGradient id="bg" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" style="stop-color:{$primary};stop-opacity:1" />
            <stop offset="100%" style="stop-color:{$secondary};stop-opacity:1" />
        </linearGradient>
    </defs>
    <rect width="400" height="400" rx="80" fill="url(#bg)"/>
    <text x="200" y="200" text-anchor="middle" dominant-baseline="central"
          font-family="'Inter','Helvetica','Arial',sans-serif" font-weight="800"
          font-size="160" fill="white" letter-spacing="-5">{$initials}</text>
</svg>
SVG;

        $upload_dir = BASE_PATH . '/uploads/logos/' . $this->biz_id;
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

        // A new name each time, like every upload: phones and browsers keep
        // uploads for a month (bin/uploads.htaccess), so a logo redrawn under
        // the old name went on showing the old initials and colours there.
        $filename = 'logo_autogen_' . time() . '_' . bin2hex(random_bytes(4)) . '.svg';
        file_put_contents($upload_dir . '/' . $filename, $svg);
        $relative_path = 'uploads/logos/' . $this->biz_id . '/' . $filename;

        $this->db->prepare("UPDATE businesses SET logo_path = ? WHERE id = ?")
                  ->execute([$relative_path, $this->biz_id]);
        // A replaced logo, and its copies, stop being public (W5)
        $old_logo = (string)($business['logo_path'] ?? '');
        if ($old_logo !== '' && $old_logo !== $relative_path) {
            storefront_forget_image($old_logo);
        }
        update_business_session(['logo_path' => $relative_path]);
        storefront_purge((int)$this->biz_id);

        flash('success', 'Auto-logo generated from your business initials.');
        redirect('settings?tab=business');
    }

    /* ──────────────────────────────────────────
     *  STAFF: UPDATE ROLE — POST (AJAX)
     * ────────────────────────────────────────── */
    public function update_role($id = null) {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'POST only']); exit;
        }

        if (!hash_equals((string)($_SESSION['csrf_token'] ?? ''), (string)($_POST['csrf_token'] ?? ''))) {
            http_response_code(403);
            echo json_encode(['error' => 'Security session expired. Reload the page and try again.']); exit;
        }

        $user = auth_user();
        if (($user['role'] ?? 'staff') !== 'owner') {
            http_response_code(403);
            echo json_encode(['error' => 'Only the business owner can change roles.']); exit;
        }

        $staff_id = (int)$id;
        $new_role = trim($_POST['role'] ?? '');
        $valid_roles = ['admin', 'staff'];

        if (!in_array($new_role, $valid_roles)) {
            echo json_encode(['error' => 'Invalid role.']); exit;
        }

        if ($staff_id === (int)$user['id']) {
            echo json_encode(['error' => 'You cannot change your own role.']); exit;
        }

        $stmt = $this->db->prepare("SELECT id, role FROM users WHERE id = ? AND business_id = ? AND is_active = 1");
        $stmt->execute([$staff_id, $this->biz_id]);
        $member = $stmt->fetch();

        if (!$member) {
            echo json_encode(['error' => 'Team member not found.']); exit;
        }

        if ($member['role'] === 'owner') {
            echo json_encode(['error' => 'Cannot change the owner role.']); exit;
        }

        $this->db->prepare("UPDATE users SET role = ? WHERE id = ? AND business_id = ?")
                  ->execute([$new_role, $staff_id, $this->biz_id]);

        if ($member['role'] !== $new_role) {
            notify('security.role_changed', $this->biz_id, [
                'role' => $new_role === 'admin' ? 'an admin' : 'staff', 'by' => planner_first_name($user['full_name']),
                'business' => auth_business()['name'],
            ], ['users' => [$staff_id], 'dedupe' => $staff_id . ':' . $new_role . ':' . time()]);
        }

        echo json_encode(['success' => true, 'role' => $new_role]);
        exit;
    }
    
    /* ══════════════════════════════════════════
     *  PRIVATE HELPERS
     * ══════════════════════════════════════════ */

    private function get_subscription(): ?array {
        $stmt = $this->db->prepare("SELECT * FROM subscriptions WHERE business_id = ?");
        $stmt->execute([$this->biz_id]);
        return $stmt->fetch() ?: null;
    }

}
