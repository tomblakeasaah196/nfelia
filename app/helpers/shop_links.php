<?php
/**
 * Nfelia — Shop links
 *
 * Every shop has its own address, name.nfelia.com. The base host is read from
 * APP_URL, so a staging copy or a renamed domain needs no code change.
 *
 * Everything that decides whether a name can be used lives here, so the setup
 * screen, the live check, the rename form and the admin review always agree.
 */

const SHOP_NAME_MIN = 3;
const SHOP_NAME_MAX = 30;

// Requests for lookalike names land in the founder account's notifications
// and wait in Admin → Shop names (see FOUNDER_BUSINESS_ID in app/helpers/auth.php).
const SHOP_REVIEWER_BUSINESS_ID = 1;

/**
 * Names that can never be a shop: the mail and cPanel hosts that already live
 * under nfelia.com, and words people would expect to belong to Nfelia itself.
 */
const SHOP_RESERVED_NAMES = [
    'www', 'mail', 'webmail', 'email', 'smtp', 'imap', 'pop', 'pop3', 'mx', 'ftp', 'sftp',
    'cpanel', 'whm', 'webdisk', 'cpcalendars', 'cpcontacts', 'autodiscover', 'autoconfig',
    'ns1', 'ns2', 'ns3', 'ns4', 'dns',
    'nfelia', 'app', 'api', 'admin', 'administrator', 'root', 'system', 'support', 'help', 'helpdesk',
    'contact', 'billing', 'pay', 'payment', 'payments', 'checkout', 'invoice', 'invoices', 'receipt', 'receipts',
    'status', 'blog', 'news', 'docs', 'dashboard', 'login', 'logout', 'signin', 'signup', 'register',
    'secure', 'security', 'account', 'accounts', 'verify', 'verification', 'auth', 'oauth', 'sso',
    'shop', 'shops', 'store', 'stores', 'biz', 'business', 'directory', 'marketplace',
    'dev', 'staging', 'test', 'testing', 'demo', 'beta', 'sandbox', 'preview',
    'static', 'cdn', 'assets', 'media', 'img', 'images', 'files', 'uploads', 'download', 'downloads',
    'webhook', 'webhooks', 'cron', 'git', 'm', 'mobile', 'go', 'link', 'links',
    'about', 'legal', 'terms', 'privacy',
];

/**
 * Names that look like a bank, fintech, telco, government body or an official
 * account are not refused: they wait for the founder to approve them, because
 * a real shop may well be called "Glow Promo".
 *
 * SHOP_REVIEW_WORDS count anywhere in the name ("gtbankpromo").
 * SHOP_REVIEW_PARTS only count as a whole hyphen-separated part ("mtn-promo"),
 * because inside other words they are everywhere: "glo" in "glowbytemi",
 * "army" in "harmony", "bank" in "bankole".
 */
const SHOP_REVIEW_WORDS = [
    // Banks and fintechs
    'gtbank', 'guaranty', 'zenith', 'accessbank', 'firstbank', 'unionbank', 'fidelity', 'fcmb',
    'sterling', 'stanbic', 'ecobank', 'wema', 'polaris', 'keystone', 'unitybank', 'jaiz', 'providus',
    'kuda', 'opay', 'palmpay', 'moniepoint', 'paystack', 'flutterwave', 'interswitch', 'quickteller',
    'remita', 'paga', 'fairmoney', 'piggyvest', 'cowrywise', 'mastercard', 'banking',
    // Telcos
    'airtel', 'globacom', '9mobile', 'etisalat',
    // Government
    'efcc', 'nimc', 'nnpc', 'frsc', 'nafdac', 'police', 'immigration', 'government', 'ministry',
    'federal', 'senate', 'presidency',
    // Platforms people impersonate
    'whatsapp', 'facebook', 'instagram', 'tiktok', 'google', 'microsoft', 'jumia', 'konga',
    // Scam bait
    'official', 'promo', 'giveaway', 'lottery', 'jackpot', 'palliative', 'helpline', 'customercare',
    'careline', 'password', 'crypto', 'forex', 'verified',
    // Nfelia itself ("nfelia-support")
    'nfelia',
];

const SHOP_REVIEW_PARTS = [
    'gtb', 'fbn', 'uba', 'alat', 'visa', 'verve', 'bank', 'mtn', 'glo', 'ntel', 'cbn', 'firs', 'npf',
    'army', 'navy', 'inec', 'ncc', 'gov', 'govt', 'customs', 'apple', 'loan', 'loans', 'grant',
    'refund', 'bonus', 'reward', 'bvn', 'nin', 'otp', 'wallet', 'investment',
];

/**
 * The ten storefront looks. Keys match businesses.storefront_look. The colours,
 * font and shape feed the thumbnails on the setup screen; the full looks are
 * drawn on the Storefront Concepts board.
 */
const STOREFRONT_LOOKS = [
    'atelier' => ['name' => 'Atelier', 'for' => 'Fashion and tailoring',          'ground' => '#F6F6F3', 'ink' => '#161A33', 'accent' => '#2E3F9E', 'font' => 'Bodoni Moda',          'shape' => '0'],
    'buka'    => ['name' => 'Buka',    'for' => 'Food and drinks',                'ground' => '#FDFDFB', 'ink' => '#1E2A3A', 'accent' => '#2C4BAF', 'font' => 'Agbalumo',             'shape' => '50%'],
    'glow'    => ['name' => 'Glow',    'for' => 'Beauty and salons',              'ground' => '#F6F2F7', 'ink' => '#2A1630', 'accent' => '#8E3B8A', 'font' => 'Gloock',               'shape' => '10px'],
    'volt'    => ['name' => 'Volt',    'for' => 'Gadgets and repairs',            'ground' => '#EEF1F4', 'ink' => '#0E1B2B', 'accent' => '#C2410C', 'font' => 'Archivo',              'shape' => '6px'],
    'market'  => ['name' => 'Market',  'for' => 'Wholesale and provisions',       'ground' => '#FFFFFF', 'ink' => '#14213D', 'accent' => '#1D3F91', 'font' => 'Barlow Condensed',     'shape' => '3px'],
    'stage'   => ['name' => 'Stage',   'for' => 'Events and creative work',       'ground' => '#0E3B36', 'ink' => '#F3EEE2', 'accent' => '#D4A73C', 'font' => 'Unbounded',            'shape' => '15px 15px 3px 3px'],
    'harvest' => ['name' => 'Harvest', 'for' => 'Farm produce',                   'ground' => '#EEF0E6', 'ink' => '#1F2A1C', 'accent' => '#2F6B3A', 'font' => 'Big Shoulders Stencil', 'shape' => '4px'],
    'shea'    => ['name' => 'Shea',    'for' => 'Skincare and perfume oils',      'ground' => '#FFFFFF', 'ink' => '#2A1E17', 'accent' => '#A3194E', 'font' => 'Young Serif',          'shape' => '12px'],
    'haven'   => ['name' => 'Haven',   'for' => 'Shortlets and property',         'ground' => '#EEF3F6', 'ink' => '#0F1F2E', 'accent' => '#0B6E79', 'font' => 'Familjen Grotesk',     'shape' => '2px'],
    'desk'    => ['name' => 'Desk',    'for' => 'Home and professional services', 'ground' => '#F7F7FB', 'ink' => '#1A1633', 'accent' => '#5B3FD1', 'font' => 'Gabarito',             'shape' => '8px'],
];

// The look a new shop starts in, from the category chosen at sign-up.
const STOREFRONT_LOOK_BY_CATEGORY = [
    'fashion'          => 'atelier',
    'food_beverage'    => 'buka',
    'tech_repair'      => 'volt',
    'wholesale_retail' => 'market',
    'distribution'     => 'market',
    'creative'         => 'stage',
    'services'         => 'desk',
    'other'            => 'atelier',
];

/**
 * The host shops hang off: nfelia.com in production.
 */
function shop_base_host(): string {
    static $host = null;
    if ($host === null) {
        $host = strtolower((string)parse_url(APP_URL, PHP_URL_HOST));
        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }
    }
    return $host;
}

/**
 * A shop's address, e.g. https://bisiindigo.nfelia.com, plus an optional path.
 */
function shop_url(string $name, string $path = ''): string {
    $scheme = parse_url(APP_URL, PHP_URL_SCHEME) ?: 'https';
    $port   = parse_url(APP_URL, PHP_URL_PORT);
    return $scheme . '://' . $name . '.' . shop_base_host() . ($port ? ':' . $port : '')
         . ($path === '' ? '' : '/' . ltrim($path, '/'));
}

/**
 * The shop part of a request's host: "bisi" for bisi.nfelia.com. Null for the
 * main site (nfelia.com and www.nfelia.com) and for any unrelated host, so
 * those carry on exactly as before.
 */
function shop_label_from_host(string $host): ?string {
    $host = strtolower(trim($host));
    $host = preg_replace('/:\d+$/', '', $host);   // drop a port
    $host = rtrim($host, '.');
    $base = shop_base_host();
    if ($base === '' || !str_ends_with($host, '.' . $base)) {
        return null;
    }
    $label = substr($host, 0, -strlen('.' . $base));
    return ($label === '' || $label === 'www') ? null : $label;
}

/**
 * Lower-case a typed name and turn spaces, dots and underscores into hyphens,
 * so "Bisi Indigo" becomes "bisi-indigo". Tone marks and dots come off first,
 * so "Ṣadé Ọlá" becomes "sade-ola". The rules are checked afterwards.
 */
function shop_name_normalise(string $raw): string {
    $name = trim($raw);
    if (class_exists('Normalizer')) {
        $name = preg_replace('/\p{Mn}+/u', '', Normalizer::normalize($name, Normalizer::FORM_D) ?: $name);
    }
    return preg_replace('/[\s._]+/', '-', strtolower($name));
}

/**
 * Whether a name looks like a bank, telco, government body or official account.
 */
function shop_name_needs_review(string $name): bool {
    $compact = str_replace('-', '', $name);
    foreach (SHOP_REVIEW_WORDS as $word) {
        if (str_contains($compact, $word)) {
            return true;
        }
    }
    return (bool)array_intersect(explode('-', $name), SHOP_REVIEW_PARTS);
}

/**
 * Check a typed name for a business. Returns
 *   ['state' => ok|review|invalid|reserved|taken, 'name' => normalised, 'message' => …]
 * 'review' means the name is free but waits for the founder to approve it.
 * Names held by another shop, live, waiting for review or given up in a
 * rename less than 90 days ago (shop_name_holds), count as taken.
 */
function shop_name_check(string $raw, int $business_id): array {
    $name   = shop_name_normalise($raw);
    $result = fn(string $state, string $message) => ['state' => $state, 'name' => $name, 'message' => $message];

    if ($name === '') {
        return $result('invalid', 'Type the name you want people to see.');
    }
    if (preg_match('/[^a-z0-9-]/', $name)) {
        return $result('invalid', 'Use letters, numbers and hyphens only.');
    }
    if (strlen($name) < SHOP_NAME_MIN) {
        return $result('invalid', 'Use at least ' . SHOP_NAME_MIN . ' characters.');
    }
    if (strlen($name) > SHOP_NAME_MAX) {
        return $result('invalid', 'Keep it to ' . SHOP_NAME_MAX . ' characters or fewer.');
    }
    if ($name[0] === '-' || str_ends_with($name, '-')) {
        return $result('invalid', 'Start and end with a letter or a number.');
    }
    if (str_contains($name, '--')) {
        return $result('invalid', 'Use one hyphen at a time.');
    }
    if (in_array($name, SHOP_RESERVED_NAMES, true)) {
        return $result('reserved', 'That name is kept for Nfelia. Try another one.');
    }

    $stmt = get_db()->prepare("
        SELECT id FROM businesses
        WHERE (subdomain = ? OR subdomain_requested = ?) AND id <> ?
        LIMIT 1
    ");
    $stmt->execute([$name, $name, $business_id]);
    if ($stmt->fetch()) {
        return $result('taken', 'Another shop already has this name.');
    }
    $holder = shop_name_holder($name);
    if ($holder && (int)$holder['id'] !== $business_id) {
        return $result('taken', 'Another shop used this name until recently. Try another one.');
    }

    if (shop_name_needs_review($name)) {
        return $result('review', 'This name needs a quick check by our team. Your link goes live as soon as it is approved.');
    }
    return $result('ok', $name . '.' . shop_base_host() . ' is free.');
}

/**
 * Up to $limit free names built from $seed (usually the business name).
 */
function shop_name_suggestions(string $seed, int $business_id, int $limit = 3): array {
    $base = trim(preg_replace('/-+/', '-', preg_replace('/[^a-z0-9-]/', '', shop_name_normalise($seed))), '-');
    $flat = str_replace('-', '', $base);
    if ($flat === '') {
        return [];
    }

    $suggestions = [];
    foreach (array_unique([$flat, $base, $flat . 'ng', $flat . 'shop', 'shop' . $flat, $flat . 'store']) as $candidate) {
        $candidate = trim(substr($candidate, 0, SHOP_NAME_MAX), '-');
        if (shop_name_check($candidate, $business_id)['state'] === 'ok') {
            $suggestions[] = $candidate;
            if (count($suggestions) >= $limit) {
                break;
            }
        }
    }
    return $suggestions;
}

/**
 * The business that gave up $name in a rename less than 90 days ago, or null.
 * Its old links keep reaching it until then.
 */
function shop_name_holder(string $name): ?array {
    try {
        $stmt = get_db()->prepare("
            SELECT b.* FROM shop_name_holds h
            JOIN businesses b ON b.id = h.business_id
            WHERE h.name = ? AND h.held_until > NOW() AND b.is_active = 1 AND b.subdomain IS NOT NULL
            LIMIT 1
        ");
        $stmt->execute([$name]);
        return $stmt->fetch() ?: null;
    } catch (PDOException $e) {
        // The table arrives with the 20261018120000 migration
        error_log('[Nfelia] shop_name_holder: ' . $e->getMessage());
        return null;
    }
}

/**
 * Keep the name a business is giving up, so its old links keep working for
 * 90 days and nobody else can take it meanwhile. Taking the name back (or
 * claiming any held name of its own) ends the hold.
 */
function shop_name_hold(int $business_id, string $old_name): void {
    get_db()->prepare("
        INSERT INTO shop_name_holds (name, business_id, held_until)
        VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 90 DAY))
        ON DUPLICATE KEY UPDATE business_id = VALUES(business_id), held_until = VALUES(held_until)
    ")->execute([$old_name, $business_id]);
}

function shop_name_release(int $business_id, string $name): void {
    get_db()->prepare("DELETE FROM shop_name_holds WHERE name = ? AND business_id = ?")->execute([$name, $business_id]);
}

/**
 * The business whose live shop link is $name, or null.
 */
function shop_find_by_name(string $name): ?array {
    $stmt = get_db()->prepare("SELECT * FROM businesses WHERE subdomain = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$name]);
    return $stmt->fetch() ?: null;
}

/**
 * Whether a shop is open to everyone, open but kept out of search engines, or
 * closed, from its row in subscriptions (null when it has none):
 *   'live'     paying (or in the few days' grace after a paid month), so
 *              indexed normally
 *   'unlisted' on trial: anyone with the link can shop, but search engines
 *              are told to skip it
 *   'closed'   trial over, grace over, or suspended
 * billing_access() (app/helpers/billing.php) decides, the same answer that
 * locks the owner out of the app, so both close on the same day.
 */
function shop_visibility(?array $subscription): string {
    if (!$subscription) {
        return 'unlisted';
    }
    require_once __DIR__ . '/billing.php';
    switch (billing_access($subscription)) {
        case 'paid':
        case 'grace':
            return 'live';
        case 'trial':
            return 'unlisted';
        default:
            return 'closed';
    }
}

/**
 * The number a shop's WhatsApp buttons open, digits only, in international
 * form. The WhatsApp number from onboarding wins over the business phone.
 * Launch is Nigeria only, so a local 0803… number becomes 234803….
 */
function shop_whatsapp_digits(array $business): string {
    $raw    = trim((string)($business['whatsapp'] ?? '')) ?: trim((string)($business['phone'] ?? ''));
    $digits = preg_replace('/\D/', '', $raw);
    if (str_starts_with($digits, '00')) {
        $digits = substr($digits, 2);
    }
    if (strlen($digits) === 14 && str_starts_with($digits, '2340')) {
        return '234' . substr($digits, 4);          // +234 (0) 803…
    }
    if (strlen($digits) === 11 && $digits[0] === '0') {
        return '234' . substr($digits, 1);          // 0803…
    }
    if (strlen($digits) === 10 && in_array($digits[0], ['7', '8', '9'], true)) {
        return '234' . $digits;                     // 803…
    }
    return $digits;
}

/**
 * The international dialling code of a business's country, digits only.
 * Nigeria (234) when the country isn't known.
 */
function business_dial_code(array $business): string {
    static $by_country = [];
    $codes = ['NG' => '234', 'CM' => '237', 'GH' => '233', 'KE' => '254', 'ZA' => '27', 'CI' => '225', 'SN' => '221',
              'BJ' => '229', 'TG' => '228', 'UG' => '256', 'TZ' => '255', 'RW' => '250', 'GA' => '241', 'TD' => '235',
              'CF' => '236', 'CG' => '242', 'CD' => '243', 'GQ' => '240', 'NE' => '227', 'BF' => '226', 'ML' => '223',
              'GM' => '220', 'SL' => '232', 'LR' => '231', 'ZM' => '260', 'ZW' => '263', 'ET' => '251', 'EG' => '20',
              'MA' => '212', 'GB' => '44', 'US' => '1'];
    // By the business's id: the signed-in session doesn't carry its country,
    // and the owner can change it
    $business_id = (int)($business['id'] ?? 0);
    if ($business_id && !array_key_exists($business_id, $by_country)) {
        $stmt = get_db()->prepare("SELECT c.iso_code FROM businesses b JOIN countries c ON c.id = b.country_id WHERE b.id = ?");
        $stmt->execute([$business_id]);
        $by_country[$business_id] = strtoupper((string)$stmt->fetchColumn());
    }
    return $codes[$by_country[$business_id] ?? 'NG'] ?? '234';
}

/**
 * A client's or supplier's phone as WhatsApp wants it: digits, in
 * international form. A local number (0… or short) takes the business's
 * own country code, not always Nigeria's (M23).
 */
function wa_digits(?string $phone, array $business): string {
    $digits = preg_replace('/\D/', '', (string)$phone);
    if (str_starts_with($digits, '00')) {
        return substr($digits, 2);
    }
    if ($digits === '') {
        return '';
    }
    $code = business_dial_code($business);
    if ($digits[0] === '0') {
        return $code . ltrim($digits, '0');
    }
    if (strlen($digits) <= 10 && !str_starts_with($digits, $code)) {
        return $code . $digits;
    }
    return $digits;
}

/**
 * A business's ISO currency code: its country's currency, else worked out
 * from the symbol it picked, else NGN.
 */
function business_currency_code(array $business): string {
    static $by_country = [];
    $country_id = (int)($business['country_id'] ?? 0);
    if ($country_id) {
        if (!array_key_exists($country_id, $by_country)) {
            $stmt = get_db()->prepare("SELECT currency_code FROM countries WHERE id = ?");
            $stmt->execute([$country_id]);
            $by_country[$country_id] = $stmt->fetchColumn() ?: null;
        }
        if ($by_country[$country_id]) {
            return strtoupper(substr($by_country[$country_id], 0, 3));
        }
    }
    return match (trim((string)($business['currency_symbol'] ?? ''))) {
        'FCFA', 'XAF'      => 'XAF',
        'GH₵', '¢', 'GHS'  => 'GHS',
        'KSh', 'KES'       => 'KES',
        '$', 'USD'         => 'USD',
        default            => 'NGN',
    };
}

/**
 * True while the signed-in business still has to choose its link or its look.
 * A name waiting for review counts as chosen. Once the link is live and a look
 * is set, the answer is kept in the session, so this costs a query per request
 * only until then.
 */
function shop_setup_needed(): bool {
    if (!empty($_SESSION['business']['shop_ready'])) {
        return false;
    }
    try {
        $stmt = get_db()->prepare("SELECT subdomain, subdomain_status, storefront_look FROM businesses WHERE id = ?");
        $stmt->execute([auth_business_id()]);
        $row = $stmt->fetch();
    } catch (PDOException $e) {
        // The columns arrive with the 20261018090000 migration. During the
        // seconds a deploy takes to apply it, let everyone carry on.
        error_log('[Nfelia] shop_setup_needed: ' . $e->getMessage());
        return false;
    }
    if (!$row) {
        return false;
    }
    $has_link = $row['subdomain'] !== null || $row['subdomain_status'] === 'pending';
    $has_look = $row['storefront_look'] !== null;
    if ($row['subdomain'] !== null && $has_look) {
        update_business_session(['shop_ready' => true]);
    }
    return !($has_link && $has_look);
}

/**
 * Leave a notification in a business's bell. $action_url is an app path such
 * as 'landing-page'; the bell puts APP_URL in front.
 */
function shop_notify(int $business_id, string $title, string $message, ?string $action_url = null): void {
    try {
        get_db()->prepare("
            INSERT INTO notifications (business_id, type, title, message, action_url, created_at)
            VALUES (?, 'general', ?, ?, ?, NOW())
        ")->execute([$business_id, $title, $message, $action_url === null ? null : ltrim($action_url, '/')]);
    } catch (PDOException $e) {
        error_log('[Nfelia] shop_notify failed: ' . $e->getMessage());
    }
}
