<?php
/**
 * Nfelia — Storefront view helpers
 *
 * The small pieces the section partials share: product photos or their
 * placeholders, prices, paragraphs, the look motifs, and one function per
 * catalogue style. $ctx comes from views/storefront/shell.php.
 *
 * Placeholders are patterns in the shop's own colours with the product's
 * initials. They never pretend to be a photo.
 */

/**
 * Owner text as paragraphs: a blank line starts a new one, a single line
 * break stays a break.
 */
function sf_paragraphs(string $text, string $class = ''): string {
    $out = '';
    foreach (preg_split('/\n{2,}/', trim($text)) as $para) {
        $para = trim($para);
        if ($para !== '') {
            $out .= '<p' . ($class !== '' ? ' class="' . e($class) . '"' : '') . '>' . nl2br(e($para), false) . '</p>';
        }
    }
    return $out;
}

/**
 * " each", " per kg", " per bundle" (one of them: unit_word()), or '' when
 * the product has no unit.
 */
function sf_unit_text(string $unit): string {
    $unit = trim($unit);
    if ($unit === '') {
        return '';
    }
    if (in_array(strtolower($unit), ['pc', 'pcs', 'piece', 'pieces', 'unit', 'units', 'each', 'item', 'items', 'ea'], true)) {
        return ' each';
    }
    return ' per ' . unit_word($unit, 1);
}

/**
 * The least a shopper can pay for one: every choice group has an option
 * picked (the first, until they change it), so it is the price plus the
 * cheapest option of each group. Booked products don't use their choices.
 */
function sf_lowest_price(array $item): float {
    $low = $item['price'];
    if (empty($item['booking'])) {
        foreach ($item['variants'] as $group) {
            if (!empty($group['deltas'])) {
                $low += min(array_map(fn($option) => (float)($group['deltas'][$option] ?? 0), $group['options']));
            }
        }
    }
    return max(0.0, round($low, 2));
}

/**
 * The price a tile shows: "From ₦80,000" when the choices move it off the
 * product's own price, else that price; "Sold out" when it is
 * (shop_sold_out). Text; null when there is none.
 */
function sf_price_text(array $item, string $currency): ?string {
    if (!empty($item['sold_out'])) {
        return 'Sold out';
    }
    if ($item['price'] <= 0) {
        return null;
    }
    $low = sf_lowest_price($item);
    return $low > 0 && $low != $item['price'] ? 'From ' . sf_money($low, $currency) : sf_money($item['price'], $currency);
}

/**
 * A product's price as HTML: the amount, then its unit in a smaller span.
 * A price of zero reads "Ask for the price"; one the choices change starts
 * "From" with the lowest. A sold-out product reads "Sold out" instead.
 */
function sf_price_html(array $item, array $ctx, bool $with_unit = true): string {
    if (!empty($item['sold_out'])) {
        return '<span class="sf-amount sf-amount--ask">Sold out</span>';
    }
    if ($item['price'] <= 0) {
        return '<span class="sf-amount sf-amount--ask">Ask for the price</span>';
    }
    $unit = $with_unit ? sf_unit_text($item['unit']) : '';
    $low  = sf_lowest_price($item);
    $from = $low > 0 && $low != $item['price'];
    return ($from ? '<span class="sf-unit">From </span>' : '')
         . '<span class="sf-amount">' . e(sf_money($from ? $low : $item['price'], $ctx['currency'])) . '</span>'
         . ($unit !== '' ? '<span class="sf-unit">' . e($unit) . '</span>' : '');
}

/**
 * The attributes every product block carries, so storefront.js can filter,
 * search and open it.
 */
function sf_item_attrs(array $item, string $class): string {
    return 'class="' . e($class) . '" data-item="' . (int)$item['id'] . '"'
         . ' data-cat="' . e(mb_strtolower($item['category'])) . '"'
         . ' data-text="' . e(mb_strtolower($item['name'] . ' ' . $item['category'] . ' ' . mb_substr($item['description'], 0, 200))) . '"';
}

/**
 * The link to a product. On live shops it is the product's own page, which
 * storefront.js opens as a sheet over the shop (a new tab or a search engine
 * gets the page); previews only open the sheet. $hidden copies are for photos
 * next to a name link, so screen readers hear each product once.
 */
function sf_open_link(array $item, string $inner, string $class = '', bool $hidden = false): string {
    $href = sf_live_links() ? sf_product_path($item) : '#item-' . (int)$item['id'];
    return '<a href="' . e($href) . '" data-open="' . (int)$item['id'] . '"'
         . ($class !== '' ? ' class="' . e($class) . '"' : '')
         . ($hidden ? ' tabindex="-1" aria-hidden="true"' : '') . '>' . $inner . '</a>';
}

/**
 * A product's first photo, or a placeholder in the shop's colours.
 */
function sf_item_media(array $item, string $sizes, bool $eager = false): string {
    foreach ($item['images'] as $rel) {
        $img = sf_img($rel, $sizes, '', 'sf-media__img', $eager);
        if ($img !== '') {
            return '<span class="sf-media">' . $img . '</span>';
        }
    }
    return '<span class="sf-media sf-ph sf-ph--' . ((int)$item['id'] % 4) . '"><span class="sf-ph__mark">' . e(sf_initials($item['name'])) . '</span></span>';
}

/**
 * "Add" for products that need no choices; others open their sheet so the
 * shopper picks a size or colour first. storefront.js decides which.
 * Products people book get "Book" instead, to their booking page.
 */
function sf_add_button(array $item, string $label, string $class = 'sf-btn sf-btn--sm', bool $icon = true): string {
    // Booked, not bought: the button goes to its booking page
    if ($item['booking'] ?? null) {
        $aria = $label === '' ? ' aria-label="Book ' . e($item['name']) . '"' : '';
        $href = sf_live_links() ? booking_page_path($item) : '#item-' . (int)$item['id'];
        return '<a class="' . e($class) . '" href="' . e($href) . '" data-book="' . (int)$item['id'] . '"' . $aria . '>'
             . ($icon ? sf_icon('calendar', 18) : '') . ($label !== '' ? '<span>Book</span>' : '') . '</a>';
    }
    // Sold out: a switched-off button where there are words, none where
    // there is only a "+" (the price already says "Sold out")
    if (!empty($item['sold_out'])) {
        return $label === '' ? '' : '<button type="button" class="' . e($class) . '" disabled><span>Sold out</span></button>';
    }
    $aria = $label === '' ? ' aria-label="Add ' . e($item['name']) . ' to your bag"' : '';
    return '<button type="button" class="' . e($class) . '" data-add="' . (int)$item['id'] . '"' . $aria . '>'
         . ($icon ? sf_icon('plus', 18) : '') . ($label !== '' ? '<span>' . e($label) . '</span>' : '') . '</button>';
}

/**
 * The proof-of-payment picker, in the bag and on a booking: a box that
 * reads "Add a screenshot of your transfer", then shows the picture picked,
 * its name and "Change" (storefront.js fills that in). The file box is the
 * same one as ever (payment_proof, the same pictures, sent with the form),
 * only out of sight inside the label, so a tap anywhere opens it.
 */
function sf_proof_field(string $hint): string {
    return '<label class="sf-field sf-proof" data-proof>'
         . '<span class="sf-field__label">Proof of payment <span class="sf-field__opt">(optional)</span></span>'
         . '<span class="sf-field__hint">' . e($hint) . '</span>'
         . '<span class="sf-proof__box">'
         . '<input type="file" name="payment_proof" accept="image/jpeg,image/png,image/webp" class="sf-proof__input">'
         . '<span class="sf-proof__add" data-proof-empty>' . sf_icon('image', 22) . '<span>Add a screenshot of your transfer</span></span>'
         . '<span class="sf-proof__picked" data-proof-picked hidden>'
         . '<img class="sf-proof__thumb" data-proof-thumb alt="" width="44" height="44">'
         . '<span class="sf-proof__name" data-proof-name></span>'
         . '<span class="sf-proof__change" aria-hidden="true">Change</span>'
         . '</span></span></label>';
}

/**
 * A WhatsApp link that asks about one product.
 */
function sf_wa_ask(array $item, array $ctx, string $class = 'sf-iconbtn'): string {
    if ($ctx['wa'] === '') {
        return '';
    }
    $text = 'Hello ' . $ctx['business']['name'] . ', I found ' . $item['name'] . ' on your shop. Is it available?';
    return '<a class="' . e($class) . '" href="https://wa.me/' . e($ctx['wa']) . '?text=' . e(rawurlencode($text)) . '" target="_blank" rel="noopener"'
         . ' aria-label="Ask about ' . e($item['name']) . ' on WhatsApp">' . sf_icon('whatsapp', 20) . '</a>';
}

/**
 * Swatches for a colour, fabric or print group: real colours where the name
 * is one, patterns in the shop's accent where it isn't.
 */
function sf_swatches(?array $group, int $limit = 5): string {
    if (!$group || !$group['options']) {
        return '';
    }
    $out = '<p class="sf-swatches">';
    foreach (array_slice($group['options'], 0, $limit) as $i => $option) {
        $out .= $option['hex']
            ? '<span class="sf-swatch" style="--sw:' . e($option['hex']) . '" title="' . e($option['label']) . '"></span>'
            : '<span class="sf-swatch sf-swatch--p' . ($i % 4) . '" title="' . e($option['label']) . '"></span>';
    }
    $count = count($group['options']);
    $noun  = mb_strtolower($group['name']);
    $noun  = $count === 1 ? $noun : (str_ends_with($noun, 's') ? $noun : $noun . 's');
    return $out . '<span class="sf-swatches__count">' . $count . ' ' . e($noun) . '</span></p>';
}

/**
 * A few short options as chips.
 */
function sf_chips(array $labels, string $class = 'sf-chips'): string {
    if (!$labels) {
        return '';
    }
    $out = '<ul class="' . e($class) . '">';
    foreach ($labels as $label) {
        $out .= '<li>' . e($label) . '</li>';
    }
    return $out . '</ul>';
}

/**
 * The chips a product shows: its first plain variant group's options, or its
 * unit.
 */
function sf_item_chips(array $item): array {
    if ($item['chips']) {
        return $item['chips'];
    }
    if ($item['sizes']) {
        return array_slice($item['sizes']['options'], 0, 3);
    }
    return $item['unit'] !== '' ? [ucfirst(trim(sf_unit_text($item['unit'])))] : [];
}

/**
 * Five stars filled to $rating (4.5 fills four and a half), read out as
 * "Rated 4.5 out of 5".
 */
function sf_stars(float $rating, int $size = 18, string $class = ''): string {
    $row = str_repeat('<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 2.8l2.8 5.8 6.3.9-4.6 4.4 1.1 6.3L12 17.2l-5.6 3 1.1-6.3-4.6-4.4 6.3-.9z"/></svg>', 5);
    $pct = max(0, min(100, $rating / 5 * 100));
    $said = rtrim(rtrim(number_format($rating, 1), '0'), '.');
    return '<span class="sf-starline' . ($class !== '' ? ' ' . e($class) : '') . '" role="img" aria-label="Rated ' . e($said) . ' out of 5">'
         . '<span class="sf-starline__base">' . $row . '</span>'
         . '<span class="sf-starline__fill" style="width:' . round($pct, 1) . '%">' . $row . '</span></span>';
}

/**
 * Empty-section prompt, shown only in previews.
 */
function sf_gap(array $ctx, string $text): string {
    return $ctx['mode'] === 'live' ? '' : '<p class="sf-gap">' . e($text) . '</p>';
}

/**
 * The link to the request form on the contact page. With a product's name,
 * the form opens with "I would like a price for …" already written
 * (storefront.js reads it from the address).
 */
function sf_quote_href(string $about = ''): string {
    return sf_page_href('contact', $about !== '' ? '#quote=' . rawurlencode($about) : '#sf-quote');
}

/**
 * One slide of a cover. The first photo loads at once; the others wait in
 * data- attributes until storefront.js is about to show them, so a shopper
 * on data pays only for what they see. Videos play only on good
 * connections, as before.
 */
function sf_cover_media(string $rel, int $index): string {
    $url = sf_media_url($rel);
    if (str_ends_with($rel, '.mp4')) {
        return '<video class="sf-cover__video" muted loop playsinline preload="none" data-src="' . e($url) . '" aria-hidden="true"></video>';
    }
    if ($index === 0) {
        return str_ends_with($rel, '.gif')
            ? '<img class="sf-cover__img" src="' . e($url) . '" alt="" fetchpriority="high">'
            : sf_img($rel, '100vw', '', 'sf-cover__img', true);
    }
    $size = sf_image_size($rel);
    $set  = str_ends_with($rel, '.gif') ? [] : sf_image_srcset($rel);
    return '<img class="sf-cover__img" data-src="' . e($url) . '"' . ($set ? ' data-srcset="' . e(implode(', ', $set)) . '" sizes="100vw"' : '')
         . ($size ? ' width="' . $size[0] . '" height="' . $size[1] . '"' : '') . ' alt="" decoding="async">';
}

/**
 * A device's condition as a badge (sf_device_facts): new is filled, used
 * from abroad is in the look's second colour, used here is outlined.
 */
function sf_grade_badge(string $grade): string {
    if ($grade === '') {
        return '';
    }
    $kind = match ($grade) { 'Brand new' => 'new', 'Nigerian used', 'Refurbished' => 'local', default => 'abroad' };
    return '<p class="sf-grade sf-grade--' . $kind . '">' . e($grade) . '</p>';
}

/**
 * Battery health as a meter with its number and a word for it, or nothing
 * when the owner hasn't written one ("Battery 86%").
 */
function sf_battery_meter(?int $level): string {
    if ($level === null) {
        return '';
    }
    $word = $level >= 85 ? 'strong' : ($level >= 75 ? 'good' : 'weak');
    return '<p class="sf-batterymeter sf-batterymeter--' . $word . '"><span class="sf-battery" style="--level: ' . $level . '%" aria-hidden="true"></span>'
         . '<span>Battery health <strong>' . $level . '%</strong> · ' . $word . '</span></p>';
}

/**
 * What a product comes as when nobody picks: the first option of each of its
 * choices ("Size: 250g, Scent: Unscented", as the bag writes it) and the
 * price with them. The skin finder adds routines this way.
 */
function sf_default_choice(array $item): array {
    $picked = [];
    $price  = $item['price'];
    foreach ($item['variants'] as $group) {
        $first = $group['options'][0];
        $picked[] = $group['name'] . ': ' . $first;
        $price += (float)($group['deltas'][$first] ?? 0);
    }
    return ['variant' => implode(', ', $picked), 'label' => implode(', ', array_map(fn($g) => $g['options'][0], $item['variants'])),
            'price' => max(0.0, round($price, 2))];
}

/**
 * A little drawing for a skin type in the skin finder, from its words.
 */
function sf_skin_icon(string $skin): string {
    $words = mb_strtolower($skin);
    return match (true) {
        (bool)preg_match('/combin|combo|mixed|t-zone/u', $words)             => 'drop-half',
        (bool)preg_match('/dry|flak|rough|ashy/u', $words)                   => 'cracked',
        (bool)preg_match('/oil|shin|greas/u', $words)                         => 'drop',
        (bool)preg_match('/spot|pigment|dark|uneven|blemish|mark/u', $words) => 'spots',
        (bool)preg_match('/acne|break|pimple/u', $words)                      => 'flame',
        (bool)preg_match('/dull|glow|bright|tired|sun/u', $words)             => 'sun',
        (bool)preg_match('/normal|all|every/u', $words)                       => 'sparkle',
        default                                                               => 'leaf',
    };
}

/**
 * "8am", "9:30pm" from "08:00", "21:30". As clock() in storefront.js.
 */
function sf_clock(string $hhmm): string {
    [$h, $m] = array_map('intval', explode(':', $hhmm) + [1 => 0]);
    return ($h % 12 ?: 12) . ($m ? ':' . sprintf('%02d', $m) : '') . ($h < 12 ? 'am' : 'pm');
}

/**
 * Today's opening hours in words as the page is drawn ("Open today, 8am to
 * 9pm", "Closed today"); storefront.js then says whether the shop is open
 * right now, as pages are kept for a while.
 */
function sf_hours_today(array $hours): string {
    $today = $hours[strtolower(date('D'))] ?? null;
    return $today ? 'Open today, ' . sf_clock($today['open']) . ' to ' . sf_clock($today['close']) : 'Closed today';
}

/**
 * The faint photo behind every page (looks with a backdrop): a small copy is
 * plenty, since only a sixth of it shows through.
 */
function sf_backdrop(string $rel): string {
    $set = array_values(array_filter(sf_image_srcset($rel), fn($entry) => (int)substr($entry, strrpos($entry, ' ') + 1) <= 960));
    $src = $set ? substr(end($set), 0, strrpos(end($set), ' ')) : sf_media_url($rel);
    return '<img src="' . e($src) . '"' . ($set ? ' srcset="' . e(implode(', ', $set)) . '" sizes="100vw"' : '')
         . ' alt="" decoding="async" fetchpriority="low">';
}

/* ── Catalogue styles ─────────────────────── */

function sf_item_editorial(array $item, array $ctx): string {
    return '<article ' . sf_item_attrs($item, 'sf-item sf-item--editorial') . '>'
         . sf_open_link($item, sf_item_media($item, '(min-width: 1080px) 260px, (min-width: 720px) 30vw, 46vw'), 'sf-item__media', true)
         . '<h3 class="sf-item__name">' . sf_open_link($item, e($item['name'])) . '</h3>'
         . '<p class="sf-item__price">' . sf_price_html($item, $ctx) . '</p>'
         . sf_swatches($item['swatches'])
         . '</article>';
}

function sf_item_menu(array $item, array $ctx): string {
    $tag = $item['unit'] !== '' ? '<span class="sf-tag">' . e(ucfirst(trim(sf_unit_text($item['unit'])))) . '</span>' : '';
    return '<article ' . sf_item_attrs($item, 'sf-item sf-item--menu') . '>'
         . sf_open_link($item, sf_item_media($item, '80px'), 'sf-item__media sf-plate', true)
         . '<div class="sf-item__body">'
         . '<h3 class="sf-item__name">' . sf_open_link($item, e($item['name'])) . '</h3>'
         . ($item['description'] !== '' ? '<p class="sf-item__desc">' . e($item['description']) . '</p>' : '')
         . $tag
         . '<div class="sf-item__foot"><span class="sf-item__price">' . sf_price_html($item, $ctx, false) . '</span>'
         . sf_add_button($item, '', 'sf-add sf-add--round') . '</div>'
         . '</div></article>';
}

function sf_item_pricerow(array $item, array $ctx): string {
    return '<article ' . sf_item_attrs($item, 'sf-item sf-item--pricerow') . '>'
         . '<div class="sf-item__body">'
         . '<h3 class="sf-item__name">' . sf_open_link($item, e($item['name'])) . '</h3>'
         . sf_chips(sf_item_chips($item), 'sf-chips sf-chips--tint')
         . '</div>'
         . '<div class="sf-item__side"><span class="sf-item__price">' . sf_price_html($item, $ctx, false) . '</span>'
         . sf_add_button($item, 'Add', 'sf-btn sf-btn--outline sf-btn--sm', false) . '</div>'
         . '</article>';
}

function sf_item_spec(array $item, array $ctx): string {
    $sticker = $item['price'] > 0 ? '<span class="sf-sticker">' . e(sf_price_text($item, $ctx['currency'])) . '</span>' : '';
    $facts = $item['is_service'] ? ['grade' => '', 'battery' => null] : sf_device_facts($item);
    return '<article ' . sf_item_attrs($item, 'sf-item sf-item--spec') . '>'
         . '<div class="sf-item__visual">' . sf_open_link($item, sf_item_media($item, '120px'), 'sf-item__media', true) . $sticker . '</div>'
         . '<div class="sf-item__body">'
         . '<div class="sf-item__tags">' . ($item['category'] !== '' ? '<span class="sf-tag">' . e($item['category']) . '</span>' : '') . sf_grade_badge($facts['grade']) . '</div>'
         . '<h3 class="sf-item__name">' . sf_open_link($item, e($item['name'])) . '</h3>'
         . sf_battery_meter($facts['battery'])
         . ($item['price'] > 0 ? '' : '<p class="sf-item__price">' . sf_price_html($item, $ctx) . '</p>')
         . sf_chips(sf_item_chips($item), 'sf-chips sf-chips--spec')
         . '<div class="sf-item__actions">' . sf_add_button($item, 'Add to bag', 'sf-btn sf-btn--ink sf-btn--sm sf-btn--grow', false) . sf_wa_ask($item, $ctx) . '</div>'
         . '</div></article>';
}

function sf_item_pricelist(array $item, array $ctx): string {
    $unit = sf_unit_text($item['unit']);
    return '<div ' . sf_item_attrs($item, 'sf-item sf-item--pricelist') . '>'
         . '<div class="sf-item__body"><h3 class="sf-item__name">' . sf_open_link($item, e($item['name'])) . '</h3>'
         . ($unit !== '' ? '<p class="sf-item__unit">' . e(ucfirst(trim($unit))) . '</p>' : '') . sf_buy_more($item, $ctx) . '</div>'
         . '<span class="sf-item__price">' . sf_price_html($item, $ctx, false) . sf_price_move($item) . '</span>'
         . '<div class="sf-item__act">' . sf_qty_control($item, 'Add to order') . '</div>'
         . '</div>';
}

/**
 * Market's prices for buying more under a product ("5+ ₦9,300 · 20+
 * ₦9,000 each"), or nothing.
 */
function sf_buy_more(array $item, array $ctx): string {
    if (empty($item['breaks']) || !empty($item['sold_out'])) {
        return '';
    }
    $steps = array_map(fn($b) => '<span>' . (int)$b['from'] . '+ <strong>' . e(sf_money($b['price'], $ctx['currency'])) . '</strong></span>', $item['breaks']);
    return '<p class="sf-buymore">' . sf_icon('tag', 14) . implode('', $steps) . '<span class="sf-sr"> each</span></p>';
}

/**
 * Where Market says a price moved since the shopper last looked (filled
 * in by storefront.js, "Since your last visit").
 */
function sf_price_move(array $item): string {
    return '<span class="sf-move" data-move="' . (int)$item['id'] . '" hidden></span>';
}

/**
 * How Market adds a product: "Add" opens the calculator to type how many,
 * then a stepper shows how many are in the order, its number a button back
 * to the calculator. Products with choices open their sheet; booked ones
 * book (sf_add_button).
 */
function sf_qty_control(array $item, string $label): string {
    if ($item['variants'] || $item['booking'] || !empty($item['sold_out'])) {
        return $label === '' ? sf_add_button($item, '', 'sf-add sf-add--round') : sf_add_button($item, $label, 'sf-btn sf-btn--outline sf-btn--sm');
    }
    $name = e($item['name']);
    // No label: a round "+", as on the price board
    $add = $label === ''
        ? '<button type="button" class="sf-add sf-add--round" data-calc-open="' . (int)$item['id'] . '" aria-label="Add ' . $name . '">' . sf_icon('plus', 20) . '</button>'
        : '<button type="button" class="sf-btn sf-btn--outline sf-btn--sm" data-calc-open="' . (int)$item['id'] . '">' . sf_icon('plus', 18) . '<span>' . e($label) . '</span></button>';
    return $add
         . '<div class="sf-stepper" data-stepper="' . (int)$item['id'] . '" hidden>'
         . '<button type="button" data-step="-1" aria-label="One fewer ' . $name . '">' . sf_icon('minus', 18) . '</button>'
         . '<button type="button" class="sf-stepper__qty" data-calc-open="' . (int)$item['id'] . '" aria-label="How many ' . $name . ': type it"><output aria-live="polite">0</output></button>'
         . '<button type="button" data-step="1" aria-label="One more ' . $name . '">' . sf_icon('plus', 18) . '</button>'
         . '</div>';
}

function sf_item_package(array $item, array $ctx): string {
    $media = $item['images'] ? sf_open_link($item, sf_item_media($item, '120px'), 'sf-item__media', true) : '';
    return '<article ' . sf_item_attrs($item, 'sf-item sf-item--package') . '>'
         . '<div class="sf-item__top"><h3 class="sf-item__name">' . sf_open_link($item, e($item['name'])) . '</h3>'
         . '<span class="sf-item__price">' . sf_price_html($item, $ctx) . '</span></div>'
         . ($media !== '' || $item['description'] !== ''
             ? '<div class="sf-item__row">' . $media . ($item['description'] !== '' ? '<p class="sf-item__desc">' . e($item['description']) . '</p>' : '') . '</div>'
             : '')
         . '<div class="sf-item__actions">' . sf_open_link($item, 'See details', 'sf-link') . sf_add_button($item, 'Add to bag', 'sf-link sf-link--button', false) . '</div>'
         . '</article>';
}

function sf_item_crate(array $item, array $ctx): string {
    $unit = sf_unit_text($item['unit']);
    return '<article ' . sf_item_attrs($item, 'sf-item sf-item--crate') . '>'
         . sf_open_link($item, sf_item_media($item, '80px'), 'sf-item__media', true)
         . '<div class="sf-item__body">'
         . '<h3 class="sf-item__name">' . sf_open_link($item, e($item['name'])) . '</h3>'
         . ($item['description'] !== '' ? '<p class="sf-item__desc">' . e($item['description']) . '</p>' : '')
         . '<div class="sf-item__foot"><span class="sf-item__price">' . sf_price_html($item, $ctx, false)
         . ($unit !== '' && $item['price'] > 0 && empty($item['sold_out']) ? '<small>' . e(trim($unit)) . '</small>' : '') . '</span>'
         . sf_add_button($item, 'Add') . '</div>'
         . '</div></article>';
}

function sf_item_shelf(array $item, array $ctx): string {
    $sizes = '';
    $only_sizes = $item['sizes'] && count($item['variants']) === 1;
    if ($item['sizes'] && count($item['sizes']['options']) <= 4) {
        $sizes = '<div class="sf-sizes" role="group" aria-label="' . e($item['sizes']['name']) . '">';
        foreach ($item['sizes']['options'] as $i => $option) {
            $sizes .= '<button type="button" data-size="' . e($option) . '" aria-pressed="' . ($i === 0 ? 'true' : 'false') . '">' . e($option) . '</button>';
        }
        $sizes .= '</div>';
    }
    $meta = trim($item['category'] . ($item['unit'] !== '' && !$only_sizes ? ' · ' . trim(sf_unit_text($item['unit'])) : ''), ' ·');
    return '<article ' . sf_item_attrs($item, 'sf-item sf-item--shelf') . '>'
         . sf_open_link($item, sf_item_media($item, '100px'), 'sf-item__media', true)
         . '<div class="sf-item__body">'
         . '<h3 class="sf-item__name">' . sf_open_link($item, e($item['name'])) . '</h3>'
         . ($meta !== '' ? '<p class="sf-item__meta">' . e($meta) . '</p>' : '')
         . sf_chips($item['chips'], 'sf-chips sf-chips--ground')
         . $sizes
         . '<div class="sf-item__foot"><span class="sf-item__price">' . sf_price_html($item, $ctx, false) . '</span>'
         . sf_add_button($item, 'Add', 'sf-btn sf-btn--sm', false) . '</div>'
         . '</div></article>';
}

function sf_item_listing(array $item, array $ctx): string {
    return '<article ' . sf_item_attrs($item, 'sf-item sf-item--listing') . '>'
         . sf_open_link($item, sf_item_media($item, '100px'), 'sf-item__media', true)
         . '<div class="sf-item__body">'
         . '<h3 class="sf-item__name">' . sf_open_link($item, e($item['name'])) . '</h3>'
         . ($item['category'] !== '' ? '<p class="sf-item__meta">' . e($item['category']) . '</p>' : '')
         . sf_chips($item['chips'], 'sf-chips sf-chips--ground')
         . '<p class="sf-item__price">' . sf_price_html($item, $ctx) . '</p>'
         . '</div></article>';
}

function sf_item_included(array $item, array $ctx): string {
    $body = '';
    if ($item['lines']) {
        $body = '<ul class="sf-checks">';
        foreach ($item['lines'] as $line) {
            $body .= '<li>' . sf_icon('check', 18) . '<span>' . e($line) . '</span></li>';
        }
        $body .= '</ul>';
    } elseif ($item['description'] !== '') {
        $body = '<p class="sf-item__desc">' . e($item['description']) . '</p>';
    }
    $ask = $ctx['quote_on']
        ? '<a class="sf-btn sf-btn--outline sf-btn--sm" href="' . e(sf_quote_href($item['name'])) . '" data-quote="' . e($item['name']) . '">Ask for a quote</a>'
        : '';
    return '<article ' . sf_item_attrs($item, 'sf-item sf-item--included') . '>'
         . '<div class="sf-item__top">' . ($item['images'] ? sf_open_link($item, sf_item_media($item, '56px'), 'sf-item__media', true) : '<span class="sf-item__icon">' . sf_icon($item['is_service'] ? 'sparkle' : 'tag', 22) . '</span>')
         . '<h3 class="sf-item__name">' . sf_open_link($item, e($item['name'])) . '</h3>'
         . '<span class="sf-item__price">' . sf_price_html($item, $ctx) . '</span></div>'
         . $body
         . '<div class="sf-item__actions">' . sf_add_button($item, 'Add to bag', 'sf-btn sf-btn--sm', false) . $ask . '</div>'
         . '</article>';
}

/**
 * A product in "New in": a tile in the look's own frame.
 */
function sf_tile(array $item, array $ctx, string $style): string {
    $sticker = $style === 'sticker' && $item['price'] > 0 ? '<span class="sf-sticker">' . e(sf_price_text($item, $ctx['currency'])) . '</span>' : '';
    $sizes = match ($style) {
        'plate'  => '(min-width: 720px) 200px, 30vw',
        default  => '(min-width: 1080px) 260px, (min-width: 720px) 30vw, 46vw',
    };
    return '<article ' . sf_item_attrs($item, 'sf-tile sf-tile--' . $style) . '>'
         . '<div class="sf-tile__visual">' . sf_open_link($item, sf_item_media($item, $sizes), 'sf-tile__media', true) . $sticker . '</div>'
         . '<h3 class="sf-tile__name">' . sf_open_link($item, e($item['name'])) . '</h3>'
         . ($sticker === '' ? '<p class="sf-tile__price">' . sf_price_html($item, $ctx) . '</p>' : '')
         . '</article>';
}

/* ── Look motifs ──────────────────────────── */

/**
 * The drawing a look shows where the owner has no photo yet. Colours come
 * from the shop's palette through CSS classes, so Shop Studio can change
 * them instantly.
 */
function sf_motif(string $look, array $ctx): string {
    $svg = fn(string $box, string $inner, string $class = '') =>
        '<svg class="sf-motif' . ($class !== '' ? ' ' . $class : '') . '" viewBox="' . $box . '" preserveAspectRatio="xMidYMid slice" aria-hidden="true" focusable="false">' . $inner . '</svg>';

    switch ($look) {
        case 'atelier':
            $dots = '';
            foreach ([21, 43, 65, 87, 109] as $x) {
                foreach ([181, 203, 225, 247, 269] as $y) {
                    $dots .= '<circle cx="' . $x . '" cy="' . $y . '" r="3.5"/>';
                }
            }
            return $svg('0 0 390 300',
                '<rect class="m-bg" width="390" height="300"/>'
                . '<path class="m-ln m-dash" d="M130 0V300M260 0V300M0 150H390"/>'
                . '<g class="m-ln">'
                . '<circle cx="65" cy="75" r="46"/><circle cx="65" cy="75" r="32"/><circle cx="65" cy="75" r="18"/>'
                . '<path d="M137 32l12-10 12 10 12-10 12 10 12-10 12 10 12-10 12 10 12-10 12 10M137 57l12-10 12 10 12-10 12 10 12-10 12 10 12-10 12 10 12-10 12 10M137 82l12-10 12 10 12-10 12 10 12-10 12 10 12-10 12 10 12-10 12 10M137 107l12-10 12 10 12-10 12 10 12-10 12 10 12-10 12 10 12-10 12 10M137 132l12-10 12 10 12-10 12 10 12-10 12 10 12-10 12 10 12-10 12 10"/>'
                . '<circle cx="325" cy="75" r="22"/>'
                . '<path d="M355 75h18M351 90l15.6 9M340 101l9 15.6M325 105v18M310 101l-9 15.6M299 90l-15.6 9M295 75h-18M299 60l-15.6-9M310 49l-9-15.6M325 45v-18M340 49l9-15.6M351 60l15.6-9"/>'
                . '<path d="M195 165l60 60-60 60-60-60zM195 183l42 42-42 42-42-42zM195 201l24 24-24 24-24-24z"/>'
                . '<path d="M262 186q16-12 32 0t32 0 32 0 32 0M262 208q16-12 32 0t32 0 32 0 32 0M262 230q16-12 32 0t32 0 32 0 32 0M262 252q16-12 32 0t32 0 32 0 32 0M262 274q16-12 32 0t32 0 32 0 32 0"/>'
                . '</g><g class="m-dot"><circle cx="65" cy="75" r="6"/><circle cx="325" cy="75" r="7"/><circle cx="195" cy="225" r="5"/>' . $dots . '</g>');

        case 'buka':
            $flowers = '';
            foreach ([[265, 216.5], [150, 283], [35, 216.5], [35, 83.5], [150, 17], [265, 83.5]] as [$x, $y]) {
                $flowers .= '<g transform="translate(' . $x . ' ' . $y . ')"><circle cx="3.6" r="2.6"/><circle cx="1.1" cy="3.4" r="2.6"/><circle cx="-2.9" cy="2.1" r="2.6"/><circle cx="-2.9" cy="-2.1" r="2.6"/><circle cx="1.1" cy="-3.4" r="2.6"/></g>';
            }
            return $svg('0 0 300 300',
                '<circle class="m-plate" cx="150" cy="150" r="143"/>'
                . '<circle class="m-rim" cx="150" cy="150" r="122"/>'
                . '<g class="m-flower">' . $flowers . '</g>'
                . '<text class="m-mono" x="150" y="150" text-anchor="middle" dominant-baseline="central">' . e(sf_initials($ctx['business']['name'])) . '</text>', 'sf-motif--plate');

        case 'glow':
            return $svg('0 0 350 230',
                '<rect class="m-soft" width="350" height="230"/>'
                . '<circle class="m-bg m-faint" cx="80" cy="190" r="120"/><circle class="m-bg m-faint" cx="300" cy="40" r="90"/>'
                . '<g class="m-ln m-accent"><path d="M40 230a135 135 0 0 1 270 0"/><path d="M75 230a100 100 0 0 1 200 0"/><path d="M110 230a65 65 0 0 1 130 0"/></g>'
                . '<g class="m-dot m-accent-fill"><circle cx="175" cy="70" r="5"/><circle cx="150" cy="58" r="3"/><circle cx="200" cy="58" r="3"/></g>');

        case 'stage':
            return $svg('0 0 350 340',
                '<rect class="m-surface" width="350" height="340"/>'
                . '<g class="m-ln m-accent"><path d="M40 340V170a135 135 0 0 1 270 0v170"/><path d="M75 340V175a100 100 0 0 1 200 0v165"/><path d="M110 340V180a65 65 0 0 1 130 0v160"/></g>'
                . '<g class="m-dot m-accent-fill"><circle cx="60" cy="70" r="3"/><circle cx="290" cy="58" r="4"/><circle cx="315" cy="120" r="2.5"/><circle cx="34" cy="132" r="2.5"/><circle cx="175" cy="24" r="3"/></g>'
                . '<rect class="m-ground" x="0" y="300" width="350" height="40"/>');

        case 'harvest':
            return $svg('0 0 350 250',
                '<rect class="m-surface" width="350" height="250"/>'
                . '<g class="m-plank"><rect x="0" y="12" width="350" height="50"/><rect x="0" y="72" width="350" height="50"/><rect x="0" y="132" width="350" height="50"/><rect x="0" y="192" width="350" height="50"/></g>'
                . '<g class="m-dot m-ink-fill"><circle cx="20" cy="37" r="3"/><circle cx="330" cy="37" r="3"/><circle cx="20" cy="97" r="3"/><circle cx="330" cy="97" r="3"/><circle cx="20" cy="157" r="3"/><circle cx="330" cy="157" r="3"/><circle cx="20" cy="217" r="3"/><circle cx="330" cy="217" r="3"/></g>'
                . '<g class="m-band-fill"><circle cx="120" cy="125" r="34"/><circle cx="186" cy="118" r="28"/><circle cx="240" cy="132" r="22"/></g>');

        case 'shea':
            return $svg('0 0 350 170',
                '<rect class="m-surface" width="350" height="170"/>'
                . '<path class="m-bg" d="M88 150c-30 0-44-26-30-52 12-22 30-34 30-60 0 26 18 38 30 60 14 26 0 52-30 52z"/>'
                . '<path class="m-soft-ink" d="M175 150c-24 0-36-21-24-42 10-17 24-27 24-48 0 21 14 31 24 48 12 21 0 42-24 42z"/>'
                . '<path class="m-bg m-faint" d="M262 150c-30 0-44-26-30-52 12-22 30-34 30-60 0 26 18 38 30 60 14 26 0 52-30 52z"/>');

        case 'haven':
            $windows = '';
            foreach ([88, 126, 164, 202, 240] as $i => $x) {
                foreach ([34, 62, 96, 124, 152] as $j => $y) {
                    $windows .= '<rect class="' . (($i + $j) % 3 === 0 ? 'm-band-fill' : 'm-glass') . '" x="' . $x . '" y="' . $y . '" width="30" height="18"/>';
                }
            }
            return $svg('0 0 350 200',
                '<rect class="m-surface" width="350" height="200"/>'
                . '<rect class="m-card m-ink-line" x="76" y="22" width="206" height="178"/>'
                . '<rect class="m-ink-fill" x="70" y="16" width="218" height="8"/>'
                . $windows
                . '<rect class="m-ink-fill" x="70" y="88" width="218" height="3"/><rect class="m-ink-fill" x="70" y="144" width="218" height="3"/>'
                . '<rect class="m-card m-ink-line" x="284" y="96" width="50" height="104"/>'
                . '<rect class="m-bg" x="166" y="172" width="26" height="28"/>'
                . '<rect class="m-line-fill" x="0" y="196" width="350" height="4"/>');

        default:
            return $svg('0 0 350 230', '<rect class="m-bg" width="350" height="230"/><g class="m-ln"><circle cx="175" cy="115" r="60"/><circle cx="175" cy="115" r="36"/></g>');
    }
}
