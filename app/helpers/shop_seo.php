<?php
/**
 * Nfelia — client shops (name.nfelia.com) in search engines and AI
 * assistants: the shop's country and words for its pages, its structured
 * data (schema.org, one @graph per page) and its /llms.txt.
 *
 * views/storefront/shell.php draws the head of every shop page from these;
 * StorefrontController serves /llms.txt from shop_llms_txt().
 *
 * Only what the shop really has goes in: its own country and currency, the
 * photos, prices and stock it shows, the contact details its owner chose to
 * show. Nothing is guessed to fill a field. In particular:
 *   - availability only when the shop counts stock (shop_sold_out on, goods);
 *   - no shipping or return-policy data: shops set delivery as free text and
 *     have no return policy setting;
 *   - no ratings: reviews are of a whole order, not of one product, and the
 *     shop's own reviews never go on the shop (Google calls that
 *     self-serving).
 *
 * Which shops search engines and AI assistants may read at all is decided
 * elsewhere: shop_visibility() (paid shops only), shop_robots() and the
 * noindex on every page of a trial or closed shop.
 */

/* ──────────────────────────────────────────
 *  THE SHOP'S PLACE AND WORDS
 * ────────────────────────────────────────── */

/**
 * The shop's country, ['iso' => 'GH', 'name' => 'Ghana']: its own
 * (businesses.country_id), else the one its currency belongs to. Nigeria
 * when neither says, as for business_currency_code().
 */
function shop_country(array $business): array {
    static $by_id = [];
    $names = ['NG' => 'Nigeria', 'GH' => 'Ghana', 'KE' => 'Kenya', 'CM' => 'Cameroon'];
    $country_id = (int)($business['country_id'] ?? 0);
    if ($country_id) {
        if (!array_key_exists($country_id, $by_id)) {
            $by_id[$country_id] = null;
            try {
                $stmt = get_db()->prepare("SELECT iso_code, name FROM countries WHERE id = ?");
                $stmt->execute([$country_id]);
                $row = $stmt->fetch();
                if ($row && preg_match('/^[A-Za-z]{2}$/', (string)$row['iso_code'])) {
                    $iso = strtoupper((string)$row['iso_code']);
                    $by_id[$country_id] = ['iso' => $iso, 'name' => trim((string)$row['name']) ?: ($names[$iso] ?? $iso)];
                }
            } catch (PDOException $e) {
                error_log('[Nfelia] shop_country: ' . $e->getMessage());
            }
        }
        if ($by_id[$country_id]) {
            return $by_id[$country_id];
        }
    }
    // XAF is several countries' money; of them, Nfelia works in Cameroon only
    $iso = ['NGN' => 'NG', 'GHS' => 'GH', 'KES' => 'KE', 'XAF' => 'CM'][business_currency_code($business)] ?? 'NG';
    return ['iso' => $iso, 'name' => $names[$iso]];
}

/** og:locale for the shop's pages: en_NG, en_GH, en_KE, en_CM… */
function shop_og_locale(array $business): string {
    return 'en_' . shop_country($business)['iso'];
}

/**
 * The town the shop says it is in: the one set in Shop Studio (where it is,
 * for the directory and the shop's data), else the one on its row.
 */
function shop_city(array $business, array $settings): string {
    $city = trim((string)($settings['seo']['city'] ?? ''));
    return $city !== '' ? $city : trim((string)($business['city'] ?? ''));
}

/**
 * What the shop sells, in the directory's words ("Hair and beauty"), or ''
 * when its category says nothing ('other').
 */
function shop_category_name(array $business): string {
    $category = (string)($business['category'] ?? '');
    if ($category === '' || $category === 'other') {
        return '';
    }
    return directory_categories()[$category]['name'] ?? '';
}

/**
 * One line of text for a meta description: spaces tidied, cut at a word
 * under $max characters with an ellipsis.
 */
function shop_seo_clip(string $text, int $max = 158): string {
    $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    if (mb_strlen($text) <= $max) {
        return $text;
    }
    $cut = mb_substr($text, 0, $max - 1);
    $space = mb_strrpos($cut, ' ');
    if ($space !== false && $space > $max * 0.6) {
        $cut = mb_substr($cut, 0, $space);
    }
    return rtrim($cut, " ,;:.-–—") . '…';
}

/** The shop's social pages, as its footer links them. */
function shop_socials(array $contact): array {
    return array_filter([
        'instagram' => $contact['instagram'] ? 'https://instagram.com/' . $contact['instagram'] : null,
        'facebook'  => $contact['facebook'] ? 'https://facebook.com/' . $contact['facebook'] : null,
        'tiktok'    => $contact['tiktok'] ? 'https://tiktok.com/@' . $contact['tiktok'] : null,
        'twitter'   => $contact['twitter'] ? 'https://x.com/' . $contact['twitter'] : null,
    ]);
}

/**
 * A phone number in international form (+233241234567), for the shop's
 * data: a local number takes the shop's own country code (wa_digits). The
 * number as typed when it can't be read.
 */
function shop_phone_international(string $phone, array $business): string {
    $digits = wa_digits($phone, $business);
    return strlen($digits) >= 8 && strlen($digits) <= 15 ? '+' . $digits : trim($phone);
}

/**
 * The shop's WhatsApp number, digits only, as its buttons open it
 * (shop_whatsapp_digits). That one reads local numbers as Nigerian; for a
 * shop in another country a local number takes its own country's code.
 */
function shop_wa_number(array $business): string {
    $digits = shop_whatsapp_digits($business);
    if ($digits !== '' && business_dial_code($business) !== '234' && ($digits[0] === '0' || strlen($digits) <= 10)) {
        return wa_digits(trim((string)($business['whatsapp'] ?? '')) ?: trim((string)($business['phone'] ?? '')), $business);
    }
    return $digits;
}

/**
 * The words for a list of things: "a", "a and b", "a, b and c".
 */
function shop_seo_list(array $words): string {
    $words = array_values(array_filter(array_map('trim', $words), fn($w) => $w !== ''));
    if (count($words) < 2) {
        return $words[0] ?? '';
    }
    $last = array_pop($words);
    return implode(', ', $words) . ' and ' . $last;
}

/* ──────────────────────────────────────────
 *  STRUCTURED DATA (schema.org)
 * ────────────────────────────────────────── */

/** The @id of the shop's nodes, the same on every page of the shop. */
function shop_ld_id(string $shop_url, string $node): string {
    return rtrim($shop_url, '/') . '/#' . $node;
}

/**
 * The shop's schema.org type. A shop that shows where it is (its address,
 * its map pin or opening hours) is a local business, as specific as its
 * category allows; one that doesn't is an online store (it sells goods) or
 * an organisation, so Google doesn't look for an address it was never given.
 */
function shop_ld_type(array $business, bool $has_place, bool $sells_goods): string {
    if (!$has_place) {
        return $sells_goods ? 'OnlineStore' : 'Organization';
    }
    return [
        'fashion'          => 'ClothingStore',
        'beauty'           => 'HealthAndBeautyBusiness',   // salons, and also hair and make-up sellers
        'food_beverage'    => 'FoodEstablishment',
        'tech_repair'      => 'ElectronicsStore',
        'wholesale_retail' => 'Store',
    ][(string)($business['category'] ?? '')] ?? 'LocalBusiness';
}

/**
 * The WebSite node: the shop's name as its site name in Google, and the
 * address of its home page.
 */
function shop_ld_website(array $business, string $shop_url): array {
    return [
        '@type'      => 'WebSite',
        '@id'        => shop_ld_id($shop_url, 'website'),
        'url'        => $shop_url,
        'name'       => (string)$business['name'],
        'inLanguage' => 'en',
        'publisher'  => ['@id' => shop_ld_id($shop_url, 'business')],
    ];
}

/**
 * A short pointer to the shop's own node, for author, seller and provider.
 */
function shop_ld_ref(array $business, string $shop_url): array {
    return ['@id' => shop_ld_id($shop_url, 'business'), 'name' => (string)$business['name'], 'url' => $shop_url];
}

/**
 * The shop itself. $o:
 *   description   the shop's own words for search engines
 *   sections      the published sections, by type
 *   catalogue     storefront_catalogue()
 *   currency      the shop's symbol, for priceRange
 *   city          shop_city()
 *   socials       shop_socials()
 *   list          true on the home page and the full list: the products
 *                 and services, by name and address
 *   list_max      how many of them
 */
function shop_ld_business(array $business, string $shop_url, array $o): array {
    $sections  = $o['sections'];
    $contact   = $sections['contact'];
    $location  = $sections['location'];
    $catalogue = $o['catalogue'];
    $map_on    = $location['on'] && $location['lat'] !== null;
    $address   = trim((string)($business['address'] ?? ''));
    $show_addr = $address !== '' && (!empty($contact['show_address']) || $map_on);

    // Opening hours, when the owner has set booking hours
    $hours = [];
    if (!empty($business['booking_settings'])) {
        foreach (booking_settings($business)['hours'] as $key => $open) {
            if ($open) {
                $hours[] = ['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => 'https://schema.org/' . BOOKING_DAYS[$key], 'opens' => $open['open'], 'closes' => $open['close']];
            }
        }
    }
    // A local business needs an address (Google requires one): a street
    // address shown, or at least the shop's city
    $city  = $o['city'] ?? '';
    $type  = shop_ld_type($business, ($show_addr || $map_on || $hours) && ($show_addr || $city !== ''), (bool)$catalogue['has_products']);
    $local = !in_array($type, ['OnlineStore', 'Organization'], true);

    $node = [
        '@type' => $type,
        '@id'   => shop_ld_id($shop_url, 'business'),
        'name'  => (string)$business['name'],
        'url'   => $shop_url,
    ];
    if (($o['description'] ?? '') !== '') {
        $node['description'] = $o['description'];
    }
    $logo = trim((string)($business['logo_path'] ?? ''));
    if ($logo !== '') {
        $node['logo'] = sf_media_url($logo);
    }
    // Pictures of the shop: its hero photos, then its logo
    $images = [];
    $hero = $sections['hero'];
    foreach (array_merge($hero['slides'], $hero['background'] !== '' && $hero['background'] !== 'none' ? [$hero['background']] : []) as $rel) {
        if (is_string($rel) && preg_match('/\.(png|jpe?g|webp)$/i', $rel) && sf_image_size($rel)) {
            $images[] = sf_media_url($rel);
        }
    }
    if ($logo !== '') {
        $images[] = sf_media_url($logo);
    }
    if ($images = array_values(array_unique($images))) {
        $node['image'] = count($images) === 1 ? $images[0] : $images;
    }
    // Only what the owner shows on the page
    if (!empty($business['phone']) && !empty($contact['show_phone'])) {
        $node['telephone'] = shop_phone_international((string)$business['phone'], $business);
    }
    if (!empty($business['email']) && !empty($contact['show_email'])) {
        $node['email'] = (string)$business['email'];
    }
    $country = shop_country($business)['iso'];
    if ($show_addr || $city !== '') {
        $node['address'] = array_filter([
            '@type'           => 'PostalAddress',
            'streetAddress'   => $show_addr ? preg_replace('/\s*\R\s*/u', ', ', $address) : null,
            'addressLocality' => $city !== '' ? $city : null,
            'addressCountry'  => $country,
        ]);
    }
    if ($map_on && $local) {   // a place has a position; an online store does not
        $node['geo'] = ['@type' => 'GeoCoordinates', 'latitude' => $location['lat'], 'longitude' => $location['lng']];
    }
    if ($local && $catalogue['min_price'] !== null) {
        $prices = array_filter(array_column($catalogue['items'], 'price'), fn($p) => $p > 0);
        $node['priceRange'] = sf_money(min($prices), $o['currency']) . (max($prices) > min($prices) ? ' – ' . sf_money(max($prices), $o['currency']) : '');
    }
    if ($local && $hours) {
        $node['openingHoursSpecification'] = $hours;
    }
    if (!empty($o['socials'])) {
        $node['sameAs'] = array_values($o['socials']);
    }
    // What it offers, by name and address. Each product's own page carries
    // its full data; lists of products as Product items are what Google
    // asks shops not to do.
    if (!empty($o['list']) && $catalogue['items']) {
        $max  = (int)($o['list_max'] ?? 20);
        $node['hasOfferCatalog'] = [
            '@type'           => 'OfferCatalog',
            'name'            => 'Products and services',
            'url'             => $shop_url . (empty($o['list_url']) ? '' : $o['list_url']),
            'numberOfItems'   => count($catalogue['items']),
            'itemListElement' => array_map(fn($item, $i) => [
                '@type'    => 'ListItem',
                'position' => $i + 1,
                'name'     => $item['name'],
                'url'      => $shop_url . sf_product_path($item),
            ], array_slice($catalogue['items'], 0, $max), array_keys(array_slice($catalogue['items'], 0, $max))),
        ];
    }
    if (!empty($sections['quote']['on'])) {
        $node['potentialAction'] = ['@type' => 'QuoteAction', 'target' => $shop_url . '/contact#sf-quote', 'name' => 'Ask for a quote'];
    }
    return $node;
}

/**
 * A product's or service's own data, for its page. Null for goods without
 * a price: Google needs an offer to show a product, and "ask for the price"
 * isn't one.
 *   $sku   a code the shop shows for it, else its number (the owner's own
 *          products.sku is never shown on the shop, so it is not sent)
 */
function shop_ld_product(array $business, array $item, string $canonical, string $shop_url, ?string $sku = null): ?array {
    $service = !empty($item['is_service']);
    $price   = (float)$item['price'];
    if (!$service && $price <= 0) {
        return null;
    }
    $node = [
        '@type' => $service ? 'Service' : 'Product',
        '@id'   => $canonical . '#product',
        'name'  => $item['name'],
        'url'   => $canonical,
    ];
    $about = trim(preg_replace('/\s+/u', ' ', (string)$item['description']) ?? '');
    if ($about !== '') {
        $node['description'] = mb_substr($about, 0, 5000);
    }
    $images = [];
    foreach ($item['images'] as $rel) {
        if (sf_image_size($rel)) {
            $images[] = sf_media_url($rel);
        }
    }
    if ($images) {
        $node['image'] = $images;
    }
    if ($item['category'] !== '') {
        $node['category'] = $item['category'];
    }
    if ($service) {
        $node['provider'] = shop_ld_ref($business, $shop_url);
    } else {
        // No brand: a shop often resells what others make, and Nfelia has no field for the maker
        $node['sku'] = ($sku !== null && trim($sku) !== '') ? trim($sku) : (string)(int)$item['id'];
    }
    if ($price > 0) {
        // The price the page shows: "From" the least the choices allow
        $low = function_exists('sf_lowest_price') ? sf_lowest_price($item) : $price;
        $offer = [
            '@type'         => 'Offer',
            'url'           => $canonical,
            'price'         => number_format($low > 0 ? $low : $price, 2, '.', ''),
            'priceCurrency' => business_currency_code($business),
            'seller'        => shop_ld_ref($business, $shop_url),
        ];
        // Stock says something only when the shop counts it (shop_sold_out)
        if (!empty($business['shop_sold_out']) && !$service && empty($item['booking'])) {
            $offer['availability'] = !empty($item['sold_out']) ? 'https://schema.org/OutOfStock' : 'https://schema.org/InStock';
        }
        $node['offers'] = $offer;
    }
    return $node;
}

/**
 * Breadcrumbs from [name, url] pairs, the shop's home page first.
 */
function shop_ld_breadcrumbs(array $trail, string $id): array {
    $items = [];
    foreach (array_values($trail) as $i => [$name, $url]) {
        $items[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $name, 'item' => $url];
    }
    return ['@type' => 'BreadcrumbList', '@id' => $id, 'itemListElement' => $items];
}

/* ──────────────────────────────────────────
 *  LLMS.TXT — what AI assistants read about a shop
 * ────────────────────────────────────────── */

/**
 * A paid shop's /llms.txt (llmstxt.org): who it is and where, what it sells
 * at what price, how to order and pay, and how to reach it. Only what the
 * shop shows on its own pages. The caller serves it to live shops only.
 * In the llmstxt.org shape: the facts (about, ordering, contact) as plain
 * text before the first "##", and "##" sections only for lists of links,
 * which is all the format's own tools keep from them.
 */
function shop_llms_txt(array $business): string {
    require_once BASE_PATH . '/views/storefront/parts/helpers.php';   // sf_unit_text, sf_lowest_price

    $settings  = storefront_settings($business, 'published');
    $catalogue = storefront_catalogue($business, $settings);
    $sections  = [];
    foreach ($settings['sections'] as $section) {
        $sections[$section['type']] = $section;
    }
    $shop_url = shop_url((string)$business['subdomain']);
    $name     = (string)$business['name'];
    $symbol   = trim((string)($business['currency_symbol'] ?? '')) ?: '₦';
    $code     = business_currency_code($business);
    $country  = shop_country($business);
    $city     = shop_city($business, $settings);
    $kind     = shop_category_name($business);
    $wa       = shop_wa_number($business);
    $contact  = $sections['contact'];
    $location = $sections['location'];
    $map_on   = $location['on'] && $location['lat'] !== null;
    $address  = trim(preg_replace('/\s*\R\s*/u', ', ', (string)($business['address'] ?? '')) ?? '');
    $list_on  = !empty($sections['catalogue']['on']) && $catalogue['items'];
    $one_line = fn(string $text) => trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    $md       = fn(string $text) => str_replace(['\\', '[', ']'], ['\\\\', '\\[', '\\]'], $one_line($text));
    $where    = $city !== '' ? $city . ', ' . $country['name'] : $country['name'];

    $out = '# ' . $one_line($name) . "\n\n";
    $tagline = $one_line((string)($business['tagline'] ?? ''));
    $out .= '> ' . $one_line($name) . ($kind !== '' ? ': ' . mb_strtolower($kind) : '') . ' in ' . $where . '.'
          . ($tagline !== '' ? ' ' . rtrim($tagline, '.') . '.' : '') . "\n\n";

    $hero_text = $one_line((string)$sections['hero']['text']);
    if ($hero_text !== '') {
        $out .= $hero_text . "\n\n";
    }
    $facts = ['Shop: ' . $shop_url, 'Where: ' . $where];
    if ($kind !== '') {
        $facts[] = 'What it sells: ' . $kind;
    }
    $facts[] = 'Prices: in ' . $code . ' (' . $symbol . ')';
    if ($wa !== '') {
        $facts[] = 'WhatsApp: https://wa.me/' . $wa;
    }
    $out .= implode('', array_map(fn($f) => '- ' . $f . "\n", $facts)) . "\n";

    $about = !empty($sections['about']['on']) ? trim((string)$sections['about']['text']) : '';
    if ($about !== '') {
        $out .= "**About**\n\n" . shop_seo_clip($about, 1200) . "\n\n";
    }

    // How to order and pay, as the shop's own pages work
    $banks = $catalogue['banks'];
    $how = [];
    if ($catalogue['items']) {
        $how[] = 'Online: open ' . $shop_url . ', add what you want to the ' . ($settings['look'] === 'market' ? 'order' : 'bag')
               . ' and check out in three steps: your details, pickup or delivery, then payment. You get a link to follow your order.';
        $how[] = $banks
            ? 'Paying: by ' . sf_pay_way($banks) . ' to the shop\'s account shown at checkout, with a screenshot of the transfer.'
            : 'Paying: the shop contacts you about payment after you order.';
    }
    if ($wa !== '') {
        $how[] = 'WhatsApp: ask about anything' . ($catalogue['items'] ? ', or send your order on WhatsApp instead of checking out' : ' or order') . ': https://wa.me/' . $wa;
    }
    if ($catalogue['bookable']) {
        $how[] = 'Bookings: services that can be booked have a booking page where you pick a free time and pay any deposit.';
    }
    if (!empty($sections['quote']['on'])) {
        $how[] = 'Custom and bulk orders: ask for a price at ' . $shop_url . '/contact';
    }
    if (!empty($sections['delivery']['on']) && trim((string)$sections['delivery']['text']) !== '') {
        $how[] = 'Delivery, in the shop\'s words: ' . shop_seo_clip((string)$sections['delivery']['text'], 500);
    }
    if ($how) {
        $out .= "**How to order and pay**\n\n" . implode('', array_map(fn($h) => '- ' . $h . "\n", $how)) . "\n";
    }

    // Reaching the shop: only what it shows
    $reach = [];
    if ($wa !== '') {
        $reach[] = 'WhatsApp: https://wa.me/' . $wa;
    }
    if (!empty($business['phone']) && !empty($contact['show_phone'])) {
        $reach[] = 'Phone: ' . shop_phone_international((string)$business['phone'], $business);
    }
    if (!empty($business['email']) && !empty($contact['show_email'])) {
        $reach[] = 'Email: ' . $one_line((string)$business['email']);
    }
    if ($address !== '' && (!empty($contact['show_address']) || $map_on)) {
        $reach[] = 'Address: ' . $address;
    }
    if ($map_on) {
        $reach[] = 'Map: ' . maps_directions_url((float)$location['lat'], (float)$location['lng']);
    }
    if (trim((string)$contact['text']) !== '') {
        $reach[] = 'Note: ' . $one_line((string)$contact['text']);
    }
    foreach (shop_socials($contact) as $network => $url) {
        $reach[] = ['instagram' => 'Instagram', 'facebook' => 'Facebook', 'tiktok' => 'TikTok', 'twitter' => 'X'][$network] . ': ' . $url;
    }
    $reach[] = 'Contact page: ' . $shop_url . '/contact';
    $out .= "**Contact**\n\n" . implode('', array_map(fn($r) => '- ' . $r . "\n", $reach)) . "\n";

    // What it sells, with prices and links
    if ($catalogue['items']) {
        $out .= "## Products and services\n\n";
        foreach (array_slice($catalogue['items'], 0, 300) as $item) {
            $price = (float)$item['price'];
            $low   = sf_lowest_price($item);
            $money = $price <= 0
                ? 'ask for the price'
                : ($low > 0 && $low != $price ? 'from ' . sf_money($low, $symbol) : sf_money($price, $symbol) . sf_unit_text((string)$item['unit']));
            $bits = [$money];
            if (!empty($item['sold_out'])) {
                $bits[] = 'sold out for now';
            }
            if ($item['booking']) {
                $bits[] = mb_strtolower(booking_summary($item['booking'], $price, $symbol), 'UTF-8');
                $bits[] = 'book online: ' . $shop_url . booking_page_path($item);
            }
            $desc = $one_line((string)$item['description']);
            $out .= '- [' . $md($item['name']) . '](' . $shop_url . sf_product_path($item) . '): ' . implode('; ', $bits)
                  . ($item['category'] !== '' ? ' (' . $md($item['category']) . ')' : '')
                  . ($desc !== '' ? '. ' . shop_seo_clip($desc, 200) : '') . "\n";
        }
        if (count($catalogue['items']) > 300) {
            $out .= '- Everything else: ' . $shop_url . ($list_on ? '/shop' : '') . "\n";
        }
        $out .= "\n";
    }

    // Its pages, and its newest news
    $pages = ['[Home](' . $shop_url . ')'];
    if ($list_on) {
        $pages[] = '[Everything it sells](' . $shop_url . '/shop)';
    }
    $pages[] = '[Contact](' . $shop_url . '/contact)';
    $posts = !empty($sections['news']['on']) ? shop_posts(get_db(), (int)$business['id'], 5) : [];
    if ($posts) {
        $pages[] = '[News and offers](' . $shop_url . '/news)';
    }
    $out .= "## Pages\n\n" . implode('', array_map(fn($p) => '- ' . $p . "\n", $pages));
    if ($posts) {
        $out .= "\n## Latest news\n\n";
        foreach ($posts as $post) {
            $out .= '- [' . $md((string)$post['title']) . '](' . $shop_url . shop_post_path($post) . '), '
                  . date('j F Y', strtotime((string)$post['published_at'])) . "\n";
        }
    }
    if (empty($business['hide_nfelia_credit'])) {
        $out .= "\n## Optional\n\n- [Nfelia](https://nfelia.com): this shop is made with Nfelia, an app small businesses use for their online shop, invoices, receipts and stock.\n";
    }
    return $out;
}
