<?php
/**
 * Nfelia — Storefront looks engine
 *
 * One renderer draws every shop and the ten looks are data. A shop's settings
 * (its look, colours, fonts, sections and the words in them) are JSON kept on
 * its businesses row:
 *
 *   storefront_draft      what the owner is editing in Shop Studio
 *   storefront_published  what shoppers see
 *
 * A shop that has never published is drawn from the older columns (tagline,
 * about_text, delivery_info…) by storefront_settings_from_legacy(), so it
 * looks the way its owner left it.
 *
 * The brand set in Settings (app/helpers/brand.php) leads: font '' and the
 * colour set 'brand' follow it, and fall back to the look's own fonts and
 * colours while the business has no brand. Shop Studio can still pick
 * something else for the shop alone.
 *
 * A shop has pages: the home page, its full list at /shop, its contact page
 * at /contact and its news at /news (sf_section_page says where each section
 * lives). Every look has them; looks with 'cover' and 'backdrop' (Atelier)
 * also open on a full-screen slideshow and keep a faint photo behind the page.
 *
 * Every colour that reaches a page goes through sf_palette(), which darkens or
 * lightens what it must so text stays at 4.5:1 or better. assets/js/studio.js
 * repeats the same sums so colour changes show instantly in the preview; the
 * server's answer always wins.
 *
 * Views: views/storefront/. Styles: assets/css/storefront.css. Behaviour:
 * assets/js/storefront.js.
 */

// 2: the Settings brand leads (font '', colours 'brand'), hero slides and
// page background, the announcement's end date and the map
const STOREFRONT_SETTINGS_VERSION = 2;
const STOREFRONT_MAX_SETTINGS_BYTES = 65536;
const STOREFRONT_IMAGE_WIDTHS = [320, 640, 960, 1280];
// Link-preview copies (JPEG): this wide at most, and small enough that
// WhatsApp shows them (it skips images much over 300 KB).
const STOREFRONT_OG_WIDTH = 1200;
const STOREFRONT_OG_MAX_BYTES = 290000;
// Copies for emails (JPEG, which every mail app shows): small and light,
// as an order email can carry a photo per line.
const STOREFRONT_MAIL_WIDTH = 240;
const STOREFRONT_MAIL_MAX_BYTES = 40000;
// Uploads that get WebP copies: photos shown on shops. GIFs keep their
// animation, so they are sent as they are.
const STOREFRONT_VARIANT_PATH = '(?:products|heroes|logos|storefront)/\d+/[A-Za-z0-9_-][A-Za-z0-9_.-]*\.(?:png|jpe?g|webp)';
// Square browser-tab and home-screen icons made from a shop's logo (PNG):
// the tab icon and Apple's home-screen size.
const STOREFRONT_ICON_SIZES = [32, 180];

/* ──────────────────────────────────────────
 *  REGISTRIES
 * ────────────────────────────────────────── */

/**
 * The ten looks. Each adds to STOREFRONT_LOOKS (name, for, thumbnail colours):
 *   pair     its own font pair (storefront_fonts)
 *   colours  its own colour set
 *   band     a second colour that is part of the look (Market's price-list
 *            yellow, Volt's price stickers…); it stays when the colour set
 *            changes
 *   shape    corner radii for the hero, tiles, buttons, cards, chips, fields
 *   hero     how the top of the page is laid out: 'cover' (Atelier's
 *            photos taking turns), 'turntable' (Buka's dishes on a table
 *            that turns), 'stories' (Glow's shoppable stories), 'finder'
 *            (Shea's skin finder), 'board' (Market's painted price board),
 *            'spotlight' (Volt's devices under a spotlight) and the looks' own
 *   items    how the catalogue draws each product
 *   tiles    how "New in" draws each product
 *   off      sections that start switched off in this look
 *   labels   default words, used until the owner types their own
 *   slides   how many hero photos the look shows (the 'cover' hero plays them
 *            in turn; 'stories' opens with them)
 *   backdrop the page background photo, faint behind every page
 */
function storefront_looks(): array {
    static $looks = null;
    if ($looks !== null) {
        return $looks;
    }
    $looks = [
        'atelier' => [
            'kind'    => 'Fashion',
            'pair'    => 'bodoni',
            'colours' => ['ground' => '#F6F6F3', 'ink' => '#161A33', 'muted' => '#4A4E66', 'accent' => '#2E3F9E', 'on' => '#FFFFFF', 'motif' => '#DCE2F5', 'line' => '#DCDCE3', 'surface' => '#E8E8E2'],
            'band'    => null,
            'shape'   => ['hero' => '0px', 'tile' => '0px', 'btn' => '0px', 'card' => '2px', 'chip' => '0px', 'field' => '0px'],
            'hero'    => 'cover',
            'items'   => 'editorial',
            'tiles'   => 'portrait',
            'slides'  => 5,
            'backdrop' => true,
            'labels'  => ['button' => 'Shop the collection', 'catalogue' => 'All pieces', 'quote' => 'Something made for you',
                          'about' => 'Our story', 'location' => 'Visit us'],
        ],
        'buka' => [
            'kind'    => 'Food and drinks',
            'pair'    => 'agbalumo',
            'colours' => ['ground' => '#FDFDFB', 'ink' => '#1E2A3A', 'muted' => '#4E5868', 'accent' => '#2C4BAF', 'on' => '#FFFFFF', 'motif' => '#F3C690', 'line' => '#E3E6EC', 'surface' => '#F3F5F9'],
            'band'    => '#E58A2B',
            'shape'   => ['hero' => '50%', 'tile' => '50%', 'btn' => '999px', 'card' => '20px', 'chip' => '999px', 'field' => '14px'],
            'hero'    => 'turntable',
            'items'   => 'menu',
            'tiles'   => 'plate',
            'labels'  => ['button' => 'See the full menu', 'catalogue' => 'Menu', 'quote' => 'Party and bulk orders'],
        ],
        'glow' => [
            'kind'    => 'Beauty',
            'pair'    => 'gloock',
            'colours' => ['ground' => '#F6F2F7', 'ink' => '#2A1630', 'muted' => '#5C4A60', 'accent' => '#8E3B8A', 'on' => '#FFFFFF', 'motif' => '#EBD3EA', 'line' => '#E6DCE8'],
            'band'    => null,
            'shape'   => ['hero' => '28px', 'tile' => '18px', 'btn' => '999px', 'card' => '24px', 'chip' => '999px', 'field' => '14px'],
            'hero'    => 'stories',
            'items'   => 'pricerow',
            'tiles'   => 'rounded',
            'slides'  => 5,
            'off'     => ['featured'],   // this look leads with its list
            'labels'  => ['button' => 'See prices', 'catalogue' => 'Prices', 'quote' => 'Custom requests'],
        ],
        'volt' => [
            'kind'    => 'Gadgets',
            'pair'    => 'archivo',
            'colours' => ['ground' => '#EEF1F4', 'ink' => '#0E1B2B', 'muted' => '#4A5566', 'accent' => '#C2410C', 'on' => '#FFFFFF', 'motif' => '#FFD9C2', 'line' => '#D9DFE6', 'surface' => '#E3E8EE', 'card' => '#FFFFFF'],
            'band'    => '#F26B1D',
            'shape'   => ['hero' => '14px', 'tile' => '10px', 'btn' => '10px', 'card' => '16px', 'chip' => '6px', 'field' => '12px'],
            'hero'    => 'spotlight',
            'items'   => 'spec',
            'tiles'   => 'sticker',
            'labels'  => ['button' => 'Shop now', 'catalogue' => 'Shop', 'quote' => 'Custom and bulk orders'],
        ],
        'market' => [
            'kind'    => 'Wholesale',
            'pair'    => 'barlow',
            'colours' => ['ground' => '#FFFFFF', 'ink' => '#14213D', 'muted' => '#4B5568', 'accent' => '#1D3F91', 'on' => '#FFFFFF', 'motif' => '#FFD23F', 'line' => '#E3E7EF', 'surface' => '#F4F6FA'],
            'band'    => '#FFD23F',
            'shape'   => ['hero' => '6px', 'tile' => '4px', 'btn' => '8px', 'card' => '10px', 'chip' => '8px', 'field' => '8px'],
            'hero'    => 'board',
            'items'   => 'pricelist',
            'tiles'   => 'square',
            'off'     => ['featured'],   // this look leads with its list
            'labels'  => ['button' => 'See the price list', 'catalogue' => 'Price list', 'quote' => 'Buying in bulk?'],
        ],
        'stage' => [
            'kind'    => 'Events',
            'pair'    => 'unbounded',
            'colours' => ['ground' => '#0E3B36', 'ink' => '#F3EEE2', 'muted' => '#C9D3CD', 'accent' => '#D4A73C', 'on' => '#0E3B36', 'motif' => '#0E3B36', 'line' => '#2A5A53'],
            'band'    => null,
            'shape'   => ['hero' => '18px', 'tile' => '12px', 'btn' => '999px', 'card' => '22px', 'chip' => '999px', 'field' => '12px'],
            'hero'    => 'arch',
            'items'   => 'package',
            'tiles'   => 'arch',
            'labels'  => ['button' => 'See packages', 'catalogue' => 'Packages', 'quote' => 'Plan your event'],
        ],
        'harvest' => [
            'kind'    => 'Farm produce',
            'pair'    => 'stencil',
            'colours' => ['ground' => '#EEF0E6', 'ink' => '#1F2A1C', 'muted' => '#4D5A48', 'accent' => '#2F6B3A', 'on' => '#FFFFFF', 'motif' => '#F2B530', 'line' => '#D5D9CB', 'surface' => '#E2E5D8', 'card' => '#FFFFFF'],
            'band'    => '#F2B530',
            'shape'   => ['hero' => '18px', 'tile' => '4px', 'btn' => '4px', 'card' => '6px', 'chip' => '4px', 'field' => '4px'],
            'hero'    => 'stamp',
            'items'   => 'crate',
            'tiles'   => 'crate',
            'labels'  => ['button' => 'Shop now', 'catalogue' => 'Fresh produce', 'quote' => 'Bulk orders', 'delivery' => 'Pickup and delivery'],
        ],
        'shea' => [
            'kind'    => 'Skincare',
            'pair'    => 'young',
            'colours' => ['ground' => '#FFFFFF', 'ink' => '#2A1E17', 'muted' => '#5E5048', 'accent' => '#A3194E', 'on' => '#FFFFFF', 'motif' => '#F6F0E6', 'line' => '#E8DFD2', 'surface' => '#F6F0E6', 'card' => '#F6F0E6'],
            'band'    => null,
            'shape'   => ['hero' => '22px', 'tile' => '16px', 'btn' => '999px', 'card' => '20px', 'chip' => '999px', 'field' => '12px'],
            'hero'    => 'finder',
            'items'   => 'shelf',
            'tiles'   => 'rounded',
            'labels'  => ['button' => 'Shop now', 'catalogue' => 'Shop', 'quote' => 'Custom and bulk orders'],
        ],
        'haven' => [
            'kind'    => 'Shortlets',
            'pair'    => 'familjen',
            'colours' => ['ground' => '#EEF3F6', 'ink' => '#0F1F2E', 'muted' => '#4A5866', 'accent' => '#0B6E79', 'on' => '#FFFFFF', 'motif' => '#E8D5B5', 'line' => '#D6DEE4', 'surface' => '#DCE6EC', 'card' => '#FFFFFF'],
            'band'    => '#E8D5B5',
            'shape'   => ['hero' => '22px', 'tile' => '12px', 'btn' => '14px', 'card' => '18px', 'chip' => '8px', 'field' => '12px'],
            'hero'    => 'facade',
            'items'   => 'listing',
            'tiles'   => 'rounded',
            'labels'  => ['button' => 'See what we offer', 'catalogue' => 'Listings', 'quote' => 'Ask for a quote'],
        ],
        'desk' => [
            'kind'    => 'Home services',
            'pair'    => 'gabarito',
            'colours' => ['ground' => '#F7F7FB', 'ink' => '#1A1633', 'muted' => '#514D6B', 'accent' => '#5B3FD1', 'on' => '#FFFFFF', 'motif' => '#FFC857', 'line' => '#E3E2EE', 'surface' => '#EEEDF6', 'card' => '#FFFFFF'],
            'band'    => '#FFC857',
            'shape'   => ['hero' => '24px', 'tile' => '14px', 'btn' => '16px', 'card' => '18px', 'chip' => '999px', 'field' => '14px'],
            'hero'    => 'estimator',
            'items'   => 'included',
            'tiles'   => 'rounded',
            'off'     => ['featured'],   // this look leads with its list
            'labels'  => ['button' => 'See services', 'catalogue' => 'Services', 'quote' => 'Get a quote'],
        ],
    ];
    foreach ($looks as $key => &$look) {
        $look += STOREFRONT_LOOKS[$key] + ['off' => [], 'slides' => 1, 'backdrop' => false];
        $look['labels'] += ['featured' => 'New in', 'about' => 'About us', 'delivery' => 'Delivery and payment',
                            'faq' => 'Questions people ask', 'video' => 'Watch', 'location' => 'Find us'];
    }
    unset($look);
    return $looks;
}

/**
 * The ten font pairs. The heading font carries the look; the body font is
 * chosen to sit with it. scale sizes headings so every pair fills about the
 * same space (Unbounded is wide, the stencil is narrow). query is the
 * Google Fonts request: a shop page loads only its own pair.
 */
function storefront_fonts(): array {
    return [
        'bodoni'    => ['name' => 'Fashion house', 'head' => 'Bodoni Moda',           'body' => 'Jost',            'head_stack' => "'Bodoni Moda', Didot, 'Bodoni 72', Georgia, serif",          'body_stack' => "'Jost', 'Avenir Next', system-ui, sans-serif",  'weight' => 500, 'stretch' => '100%', 'scale' => 1.00, 'query' => 'family=Bodoni+Moda:opsz,wght@6..96,400..700&family=Jost:wght@400;500;600'],
        'agbalumo'  => ['name' => 'Street food',   'head' => 'Agbalumo',              'body' => 'Figtree',         'head_stack' => "'Agbalumo', 'Cooper Black', Georgia, serif",                 'body_stack' => "'Figtree', system-ui, sans-serif",              'weight' => 400, 'stretch' => '100%', 'scale' => 0.90, 'query' => 'family=Agbalumo&family=Figtree:wght@400;500;600;700'],
        'gloock'    => ['name' => 'Soft glam',     'head' => 'Gloock',                'body' => 'Mulish',          'head_stack' => "'Gloock', Georgia, serif",                                   'body_stack' => "'Mulish', system-ui, sans-serif",               'weight' => 400, 'stretch' => '100%', 'scale' => 0.95, 'query' => 'family=Gloock&family=Mulish:wght@400;500;600;700;800'],
        'archivo'   => ['name' => 'Clean tech',    'head' => 'Archivo',               'body' => 'Archivo',         'head_stack' => "'Archivo', 'Arial Black', system-ui, sans-serif",           'body_stack' => "'Archivo', system-ui, sans-serif",              'weight' => 800, 'stretch' => '125%', 'scale' => 0.74, 'query' => 'family=Archivo:wdth,wght@62..125,400..800'],
        'barlow'    => ['name' => 'Price list',    'head' => 'Barlow Condensed',      'body' => 'Barlow',          'head_stack' => "'Barlow Condensed', 'Arial Narrow', sans-serif",            'body_stack' => "'Barlow', system-ui, sans-serif",               'weight' => 700, 'stretch' => '100%', 'scale' => 0.92, 'query' => 'family=Barlow+Condensed:wght@500;600;700&family=Barlow:wght@400;500;600;700'],
        'unbounded' => ['name' => 'Big night',     'head' => 'Unbounded',             'body' => 'Hanken Grotesk',  'head_stack' => "'Unbounded', 'Arial Black', system-ui, sans-serif",         'body_stack' => "'Hanken Grotesk', system-ui, sans-serif",       'weight' => 600, 'stretch' => '100%', 'scale' => 0.66, 'query' => 'family=Unbounded:wght@500;600;700&family=Hanken+Grotesk:wght@400;500;600;700'],
        'stencil'   => ['name' => 'Farm stencil',  'head' => 'Big Shoulders Stencil', 'body' => 'Work Sans',       'head_stack' => "'Big Shoulders Stencil', 'Arial Narrow', Impact, sans-serif", 'body_stack' => "'Work Sans', system-ui, sans-serif",          'weight' => 800, 'stretch' => '100%', 'scale' => 1.08, 'query' => 'family=Big+Shoulders+Stencil:wght@700;800&family=Work+Sans:wght@400;500;600;700'],
        'young'     => ['name' => 'Handmade',      'head' => 'Young Serif',           'body' => 'Albert Sans',     'head_stack' => "'Young Serif', Georgia, serif",                              'body_stack' => "'Albert Sans', system-ui, sans-serif",          'weight' => 400, 'stretch' => '100%', 'scale' => 0.84, 'query' => 'family=Young+Serif&family=Albert+Sans:wght@400;500;600;700'],
        'familjen'  => ['name' => 'Architect',     'head' => 'Familjen Grotesk',      'body' => 'Instrument Sans', 'head_stack' => "'Familjen Grotesk', system-ui, sans-serif",                 'body_stack' => "'Instrument Sans', system-ui, sans-serif",      'weight' => 700, 'stretch' => '100%', 'scale' => 0.80, 'query' => 'family=Familjen+Grotesk:wght@600;700&family=Instrument+Sans:wght@400;500;600'],
        'gabarito'  => ['name' => 'Friendly pro',  'head' => 'Gabarito',              'body' => 'Rethink Sans',    'head_stack' => "'Gabarito', system-ui, sans-serif",                         'body_stack' => "'Rethink Sans', system-ui, sans-serif",         'weight' => 800, 'stretch' => '100%', 'scale' => 0.95, 'query' => 'family=Gabarito:wght@600;700;800&family=Rethink+Sans:wght@400;500;600'],
    ];
}

/**
 * The stylesheet for one font pair.
 */
function storefront_fonts_url(string $pair): string {
    $fonts = storefront_fonts();
    return 'https://fonts.googleapis.com/css2?' . ($fonts[$pair] ?? $fonts['bodoni'])['query'] . '&display=swap';
}

/**
 * Colour sets any look can wear, besides its own and the ones read from a
 * logo in Shop Studio.
 */
function storefront_colour_sets(): array {
    return [
        'calm'  => ['name' => 'Calm',  'ground' => '#F3F6F5', 'ink' => '#17302B', 'muted' => '#4C5F5A', 'accent' => '#2F6B5E', 'on' => '#FFFFFF', 'motif' => '#CFE3DD', 'line' => '#DCE5E2'],
        'bold'  => ['name' => 'Bold',  'ground' => '#FFFFFF', 'ink' => '#1A1530', 'muted' => '#514B66', 'accent' => '#C62F3A', 'on' => '#FFFFFF', 'motif' => '#FAD4D6', 'line' => '#E8E5EE'],
        'earth' => ['name' => 'Earth', 'ground' => '#F1EFEA', 'ink' => '#2E2418', 'muted' => '#5F5446', 'accent' => '#7A5A2B', 'on' => '#FFFFFF', 'motif' => '#E8D9BE', 'line' => '#E0DBD2'],
        'night' => ['name' => 'Night', 'ground' => '#12141C', 'ink' => '#EDEFF5', 'muted' => '#B8BDCB', 'accent' => '#7FA7FF', 'on' => '#12141C', 'motif' => '#12141C', 'line' => '#2A2E3C'],
    ];
}

/**
 * Section types. 'pin' keeps a section at the top or bottom of the page;
 * the rest can be moved. 'switch' => false means it is always shown.
 * 'available' => false would keep a type known here but never stored, shown
 * or offered (for sections still being built).
 *
 * Field kinds: line (one line), text (paragraphs), bool, youtube, image,
 * media (a list of photos and videos), backdrop (a photo, '' for the first
 * slide, or 'none'), date (YYYY-MM-DD or ''), lat, lng, zoom, products (a
 * list of product ids), faq (question and answer pairs), skins (short
 * labels), routine (a product id with its step and skin types, each), money
 * (an amount, 0 for none), breaks (a product id, from how many, and the
 * price each from then on).
 */
function storefront_section_types(): array {
    return [
        'announcement' => ['name' => 'Announcement bar', 'hint' => 'One line across the top of every page of your shop', 'pin' => 'top', 'switch' => true, 'on' => false, 'available' => true,
            'fields' => ['text'  => ['kind' => 'line', 'max' => 120, 'label' => 'Message'],
                         'until' => ['kind' => 'date', 'label' => 'Hide it after']]],
        'hero' => ['name' => 'Top of your shop', 'hint' => 'Your headline, a line or two and your photos', 'pin' => 'top', 'switch' => false, 'on' => true, 'available' => true,
            'fields' => [
                'headline'   => ['kind' => 'line', 'max' => 90, 'label' => 'Headline'],
                'text'       => ['kind' => 'text', 'max' => 300, 'label' => 'A line or two under it'],
                'button'     => ['kind' => 'line', 'max' => 30, 'label' => 'Button'],
                'badge'      => ['kind' => 'line', 'max' => 40, 'label' => 'Sticker'],
                'image'      => ['kind' => 'image', 'label' => 'Photo or video'],   // the first slide, kept for older readers
                'slides'     => ['kind' => 'media', 'max' => 5, 'label' => 'Photos and videos'],
                'background' => ['kind' => 'backdrop', 'label' => 'Page background'],
                // Buka's turntable and Glow's stories: the products they show
                'picks'      => ['kind' => 'products', 'max' => 8, 'label' => 'Products to show at the top'],
                // Shea's skin finder: the skin types shoppers pick from, and
                // which step of a routine each product is, for which skins
                'skins'      => ['kind' => 'skins', 'max' => 6, 'label' => 'Skin types'],
                'routine'    => ['kind' => 'routine', 'max' => 500, 'label' => 'Routine steps'],
                // Market's order: the least an order can be, where delivery
                // turns free, and lower prices for buying more
                'min_order'  => ['kind' => 'money', 'label' => 'Minimum order'],
                'free_from'  => ['kind' => 'money', 'label' => 'Free delivery from'],
                'breaks'     => ['kind' => 'breaks', 'max' => 300, 'label' => 'Prices for buying more'],
            ]],
        'featured' => ['name' => 'New in', 'hint' => 'A few products at the top. Pick them, or we show your newest.', 'pin' => null, 'switch' => true, 'on' => true, 'available' => true,
            'fields' => ['title' => ['kind' => 'line', 'max' => 50, 'label' => 'Title'],
                         'products' => ['kind' => 'products', 'max' => 8, 'label' => 'Products to show']]],
        'catalogue' => ['name' => 'Everything you sell', 'hint' => 'Your products and services on your shop\'s own page, with a search and a filter by category. Your home page shows your categories, with a link to it.', 'pin' => null, 'switch' => true, 'on' => true, 'available' => true,
            'fields' => ['title' => ['kind' => 'line', 'max' => 50, 'label' => 'Title'],
                         'only' => ['kind' => 'products', 'max' => 500, 'label' => 'Only show these']]],
        'about' => ['name' => 'About us', 'hint' => '', 'pin' => null, 'switch' => true, 'on' => true, 'available' => true,
            'fields' => ['title' => ['kind' => 'line', 'max' => 50, 'label' => 'Title'],
                         'text' => ['kind' => 'text', 'max' => 2000, 'label' => 'Your story']]],
        'video' => ['name' => 'Video', 'hint' => 'A YouTube video, loaded only when someone taps play', 'pin' => null, 'switch' => true, 'on' => false, 'available' => true,
            'fields' => ['title' => ['kind' => 'line', 'max' => 50, 'label' => 'Title'],
                         'url' => ['kind' => 'youtube', 'label' => 'YouTube link']]],
        'delivery' => ['name' => 'Delivery and payment', 'hint' => 'Shown on your shop\'s page, under your products.', 'pin' => null, 'switch' => true, 'on' => true, 'available' => true,
            'fields' => ['title' => ['kind' => 'line', 'max' => 50, 'label' => 'Title'],
                         'text' => ['kind' => 'text', 'max' => 500, 'label' => 'How you deliver'],
                         'show_bank' => ['kind' => 'bool', 'label' => 'Show my payment accounts']]],
        'faq' => ['name' => 'Questions people ask', 'hint' => 'Shown on your contact page.', 'pin' => null, 'switch' => true, 'on' => false, 'available' => true,
            'fields' => ['title' => ['kind' => 'line', 'max' => 50, 'label' => 'Title'],
                         'items' => ['kind' => 'faq', 'max' => 10, 'label' => 'Questions']]],
        'quote' => ['name' => 'Custom and bulk orders', 'hint' => 'A form people fill in to ask for a price, on your contact page. Your home page shows a button to it.', 'pin' => null, 'switch' => true, 'on' => true, 'available' => true,
            'fields' => [
                'title'             => ['kind' => 'line', 'max' => 50, 'label' => 'Title'],
                'text'              => ['kind' => 'text', 'max' => 300, 'label' => 'A line under the title'],
                'company_name'      => ['kind' => 'bool', 'label' => 'Ask for their company'],
                'quantity_range'    => ['kind' => 'bool', 'label' => 'Ask how many they need'],
                'budget'            => ['kind' => 'bool', 'label' => 'Ask for their budget'],
                'timeline'          => ['kind' => 'bool', 'label' => 'Ask when they need it'],
                'delivery_location' => ['kind' => 'bool', 'label' => 'Ask where to deliver'],
            ]],
        'reviews' => ['name' => 'Customer reviews', 'hint' => 'Stars and words from people whose orders were delivered or collected. They show once the first one arrives.', 'pin' => null, 'switch' => true, 'on' => true, 'available' => true,
            'fields' => ['title' => ['kind' => 'line', 'max' => 50, 'label' => 'Title']]],
        'booking' => ['name' => 'Bookings', 'hint' => 'Services people book: they pick a free time and pay the deposit. Shows once a product can be booked.', 'pin' => null, 'switch' => true, 'on' => true, 'available' => true,
            'fields' => ['title' => ['kind' => 'line', 'max' => 50, 'label' => 'Title'],
                         'text' => ['kind' => 'text', 'max' => 300, 'label' => 'A line under the title']]],
        'news'    => ['name' => 'News and offers', 'hint' => 'Your newest posts. Write them in News and offers; they show once the first one is published.', 'pin' => null, 'switch' => true, 'on' => true, 'available' => true,
            'fields' => ['title' => ['kind' => 'line', 'max' => 50, 'label' => 'Title']]],
        'location' => ['name' => 'Map and directions', 'hint' => 'Your address on a map at the bottom of your shop, with a Get directions button. It shows once you place your pin.', 'pin' => 'bottom', 'switch' => true, 'on' => true, 'available' => true,
            'fields' => [
                'title' => ['kind' => 'line', 'max' => 50, 'label' => 'Title'],
                'note'  => ['kind' => 'text', 'max' => 200, 'label' => 'How to find you, like a landmark'],
                'lat'   => ['kind' => 'lat'],
                'lng'   => ['kind' => 'lng'],
                'zoom'  => ['kind' => 'zoom'],
            ]],
        'contact' => ['name' => 'Contact and footer', 'hint' => 'Your WhatsApp, socials and address', 'pin' => 'bottom', 'switch' => false, 'on' => true, 'available' => true,
            'fields' => [
                'text'         => ['kind' => 'text', 'max' => 200, 'label' => 'A short line, like your opening hours'],
                'instagram'    => ['kind' => 'handle', 'label' => 'Instagram'],
                'facebook'     => ['kind' => 'handle', 'label' => 'Facebook'],
                'tiktok'       => ['kind' => 'handle', 'label' => 'TikTok'],
                'twitter'      => ['kind' => 'handle', 'label' => 'X (Twitter)'],
                'show_address' => ['kind' => 'bool', 'label' => 'Show my address'],
                'show_phone'   => ['kind' => 'bool', 'label' => 'Show my phone number'],
                'show_email'   => ['kind' => 'bool', 'label' => 'Show my email'],
            ]],
    ];
}

/* ──────────────────────────────────────────
 *  SETTINGS
 * ────────────────────────────────────────── */

/**
 * The look a shop starts in: the one chosen at setup, else one that suits
 * its category.
 */
function storefront_default_look(array $business): string {
    $look = (string)($business['storefront_look'] ?? '');
    if (isset(STOREFRONT_LOOKS[$look])) {
        return $look;
    }
    return STOREFRONT_LOOK_BY_CATEGORY[$business['category'] ?? ''] ?? 'atelier';
}

/**
 * Settings for a shop that has never been published, from the columns the
 * old Landing Page screen wrote. It draws the shop much as it was: the same
 * words, socials, quote form and featured products.
 */
function storefront_settings_from_legacy(array $business): array {
    $rfq      = json_decode((string)($business['rfq_fields'] ?? ''), true) ?: [];
    $featured = json_decode((string)($business['featured_product_ids'] ?? ''), true) ?: [];
    $about    = trim((string)($business['about_text'] ?? ''));
    $youtube  = trim((string)($business['youtube_url'] ?? ''));
    $hero     = (string)($business['hero_image_path'] ?? '');

    return storefront_normalise([
        'v'        => STOREFRONT_SETTINGS_VERSION,
        'look'     => storefront_default_look($business),
        'colours'  => ['set' => 'brand'],
        'font'     => '',
        'sections' => [
            ['type' => 'announcement', 'on' => false, 'text' => ''],
            ['type' => 'hero', 'on' => true, 'headline' => trim((string)($business['tagline'] ?? '')) ?: (string)$business['name'],
             'text' => '', 'button' => '', 'badge' => '', 'image' => $hero, 'slides' => $hero !== '' ? [$hero] : []],
            // The old screen's "featured products" chose what the shop
            // showed at all, so they become the catalogue's list
            ['type' => 'featured', 'on' => !in_array('featured', storefront_looks()[storefront_default_look($business)]['off'], true), 'title' => '', 'products' => []],
            ['type' => 'catalogue', 'on' => true, 'title' => '', 'only' => is_array($featured) ? $featured : []],
            ['type' => 'about', 'on' => $about !== '', 'title' => '', 'text' => $about],
            ['type' => 'video', 'on' => sf_youtube_id($youtube) !== null, 'title' => '', 'url' => $youtube],
            ['type' => 'delivery', 'on' => true, 'title' => '', 'text' => (string)($business['delivery_info'] ?? ''), 'show_bank' => true],
            ['type' => 'faq', 'on' => false, 'title' => '', 'items' => []],
            ['type' => 'quote', 'on' => true, 'title' => '', 'text' => '',
             'company_name' => !empty($rfq['company_name']), 'quantity_range' => !empty($rfq['quantity_range']),
             'budget' => !empty($rfq['budget']), 'timeline' => !empty($rfq['timeline']),
             'delivery_location' => !empty($rfq['delivery_location'])],
            ['type' => 'contact', 'on' => true, 'text' => '',
             'instagram' => (string)($business['social_instagram'] ?? ''), 'facebook' => (string)($business['social_facebook'] ?? ''),
             'tiktok' => (string)($business['social_tiktok'] ?? ''), 'twitter' => (string)($business['social_twitter'] ?? ''),
             'show_address' => true, 'show_phone' => true, 'show_email' => true],
        ],
    ], $business);
}

/**
 * The settings a shop shows: 'published' for shoppers, 'draft' for Shop
 * Studio. A draft falls back to the published settings, and both fall back
 * to the older columns.
 */
function storefront_settings(array $business, string $which = 'published'): array {
    $json = $which === 'draft' ? ($business['storefront_draft'] ?? null) : null;
    if ($json === null || $json === '') {
        $json = $business['storefront_published'] ?? null;
    }
    $raw = ($json === null || $json === '') ? null : json_decode((string)$json, true);
    return is_array($raw) ? storefront_normalise($raw, $business) : storefront_settings_from_legacy($business);
}

/**
 * Clean settings from anywhere (Shop Studio, a stored version, the database)
 * into the one shape the renderer reads. Unknown keys are dropped, every text
 * is trimmed and cut to length, and missing sections come back with their
 * defaults, so a settings blob can never break a page.
 */
function storefront_normalise(array $raw, array $business): array {
    $raw   = sf_upgrade_settings($raw, $business);
    $looks = storefront_looks();
    $look  = is_string($raw['look'] ?? null) && isset($looks[$raw['look']]) ? $raw['look'] : storefront_default_look($business);
    $font  = is_string($raw['font'] ?? null) && isset(storefront_fonts()[$raw['font']]) ? $raw['font'] : '';

    $types    = storefront_section_types();
    $sections = [];
    foreach (is_array($raw['sections'] ?? null) ? $raw['sections'] : [] as $section) {
        $type = is_array($section) ? ($section['type'] ?? null) : null;
        if (!is_string($type) || !isset($types[$type]) || !$types[$type]['available'] || isset($sections[$type])) {
            continue;
        }
        $sections[$type] = sf_clean_section($type, $section, (int)$business['id']);
    }
    foreach ($types as $type => $def) {
        if ($def['available'] && !isset($sections[$type])) {
            $on = $def['on'] && !in_array($type, $looks[$look]['off'], true);
            $sections[$type] = sf_clean_section($type, ['on' => $on] + sf_section_defaults($type), (int)$business['id']);
        }
    }

    // Pinned sections keep their place whatever order arrives; the footer is last
    $top    = array_values(array_filter($sections, fn($s) => $types[$s['type']]['pin'] === 'top'));
    $middle = array_values(array_filter($sections, fn($s) => $types[$s['type']]['pin'] === null));
    $bottom = array_values(array_filter($sections, fn($s) => $types[$s['type']]['pin'] === 'bottom'));
    usort($top, fn($a, $b) => $a['type'] === 'announcement' ? -1 : ($b['type'] === 'announcement' ? 1 : 0));
    usort($bottom, fn($a, $b) => ($a['type'] === 'contact') <=> ($b['type'] === 'contact'));

    return [
        'v'        => STOREFRONT_SETTINGS_VERSION,
        'look'     => $look,
        'colours'  => sf_clean_colours(is_array($raw['colours'] ?? null) ? $raw['colours'] : []),
        'font'     => $font,
        'sections' => array_merge($top, $middle, $bottom),
        'seo'      => sf_clean_seo(is_array($raw['seo'] ?? null) ? $raw['seo'] : [], $business),
    ];
}

/**
 * Settings stored before version 2 (October 2026), brought up to date when
 * read. The brand set in Settings now leads: a shop whose owner chose brand
 * fonts or colours switches to them, whatever was picked in Shop Studio
 * before (the owner's call), in a set as dark or light as the shop was. The
 * look's own colours, which every shop had until its owner picked others,
 * become 'brand', which keeps showing them until there is a brand.
 */
function sf_upgrade_settings(array $raw, array $business): array {
    if ((int)($raw['v'] ?? 1) >= 2) {
        return $raw;
    }
    if (brand_fonts($business) !== null) {
        $raw['font'] = '';
    }
    $was = sf_clean_colours(is_array($raw['colours'] ?? null) ? $raw['colours'] : []);
    if (brand_colours($business) !== null) {
        $look = is_string($raw['look'] ?? null) && isset(storefront_looks()[$raw['look']]) ? $raw['look'] : storefront_default_look($business);
        $dark = sf_palette(sf_colour_set(['look' => $look, 'colours' => $was], $business))['dark'];
        $raw['colours'] = ['set' => 'brand', 'mode' => $dark ? 'dark' : 'light'];
    } elseif ($was['set'] === 'look') {
        $raw['colours'] = ['set' => 'brand'];
    }
    $raw['v'] = 2;
    return $raw;
}

/**
 * Which page of the shop a section is on: the home page, the full list
 * ('catalogue', at /shop) or the contact page (/contact). The announcement
 * and the footer are on every page. The home page also shows a taste of the
 * list, the "made for you" button and the map.
 */
function sf_section_page(string $type): string {
    return match ($type) {
        'catalogue', 'delivery' => 'catalogue',
        'quote', 'faq'          => 'contact',
        'announcement', 'contact' => 'all',
        default                 => 'home',
    };
}

/**
 * How the shop shows in Google and in link previews, and whether it is in
 * Nfelia's shop directory:
 *   title, description  override the words drawn from the hero (empty: use those)
 *   directory           list the shop at nfelia.com/shops (paid shops only)
 *   city                where it is, for the directory and the shop's data
 */
function sf_clean_seo(array $raw, array $business): array {
    $city = sf_clean_line($raw['city'] ?? null, 60);
    return [
        'title'       => sf_clean_line($raw['title'] ?? null, 70),
        'description' => sf_clean_line(preg_replace('/\s+/u', ' ', is_string($raw['description'] ?? null) ? $raw['description'] : '') ?? '', 170),
        'directory'   => sf_bool($raw['directory'] ?? false),
        'city'        => array_key_exists('city', $raw) ? $city : sf_guess_city((string)($business['address'] ?? '')),
    ];
}

/**
 * Cities people search for, for the directory and the city picker.
 */
const NG_CITIES = ['Aba', 'Abakaliki', 'Abeokuta', 'Abuja', 'Ado-Ekiti', 'Akure', 'Asaba', 'Awka', 'Bauchi', 'Benin City',
    'Calabar', 'Enugu', 'Gombe', 'Ibadan', 'Ijebu-Ode', 'Ikeja', 'Ilorin', 'Jos', 'Kaduna', 'Kano', 'Katsina', 'Lafia', 'Lagos',
    'Lekki', 'Lokoja', 'Maiduguri', 'Makurdi', 'Minna', 'Nnewi', 'Ogbomoso', 'Onitsha', 'Osogbo', 'Owerri', 'Port Harcourt',
    'Sokoto', 'Umuahia', 'Uyo', 'Warri', 'Yenagoa', 'Yola', 'Zaria'];

/**
 * A city named in an address ("Bodija, Ibadan" → "Ibadan"), or ''. Parts
 * of Lagos (Ikeja, Lekki) count as Lagos.
 */
function sf_guess_city(string $address): string {
    $text = strtolower($address);
    foreach (NG_CITIES as $city) {
        if (preg_match('/\b' . preg_quote(strtolower($city), '/') . '\b/', $text)) {
            return in_array($city, ['Ikeja', 'Lekki'], true) ? 'Lagos' : $city;
        }
    }
    foreach (['victoria island', 'ikoyi', 'yaba', 'surulere', 'ajah', 'idumota', 'lagos island', 'computer village'] as $area) {
        if (str_contains($text, $area)) {
            return 'Lagos';
        }
    }
    return '';
}

function sf_city_slug(string $city): string {
    return $city === '' ? '' : sf_product_slug($city);
}

/**
 * The directory's spelling of a city from NG_CITIES, or '' for anything
 * else. Only these get /shops/{city} pages, so a typed city can't make an
 * indexable page of its own (W17); the shop still shows what was typed.
 */
function sf_known_city(?string $city): string {
    foreach (NG_CITIES as $known) {
        if (strcasecmp($known, trim((string)$city)) === 0) {
            return in_array($known, ['Ikeja', 'Lekki'], true) ? 'Lagos' : $known;
        }
    }
    return '';
}

/**
 * Field values a new section starts with.
 */
function sf_section_defaults(string $type): array {
    return match ($type) {
        'delivery' => ['show_bank' => true],
        'contact'  => ['show_address' => true, 'show_phone' => true, 'show_email' => true],
        default    => [],
    };
}

function sf_clean_section(string $type, array $raw, int $business_id): array {
    $def   = storefront_section_types()[$type];
    $clean = ['type' => $type, 'on' => $def['switch'] ? sf_bool($raw['on'] ?? $def['on']) : true];
    foreach ($def['fields'] as $key => $field) {
        $value = $raw[$key] ?? null;
        $clean[$key] = match ($field['kind']) {
            'line'     => sf_clean_line($value, $field['max']),
            'text'     => sf_clean_text($value, $field['max']),
            'bool'     => sf_bool($value ?? (sf_section_defaults($type)[$key] ?? false)),
            'youtube'  => sf_youtube_id((string)(is_string($value) ? $value : '')) !== null ? sf_clean_line($value, 300) : '',
            'image'    => sf_clean_media_path($value, $business_id),
            'media'    => sf_clean_media_list($value, $business_id, $field['max']),
            'backdrop' => in_array($value, ['', 'none'], true) ? $value : sf_clean_photo_path($value, $business_id),
            'date'     => is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && checkdate((int)substr($value, 5, 2), (int)substr($value, 8, 2), (int)substr($value, 0, 4)) ? $value : '',
            'lat'      => is_numeric($value) && abs((float)$value) <= 90 ? round((float)$value, 6) : null,
            'lng'      => is_numeric($value) && abs((float)$value) <= 180 ? round((float)$value, 6) : null,
            'zoom'     => is_numeric($value) ? max(3, min(19, (int)$value)) : 16,
            'products' => sf_clean_ids($value, $field['max']),
            'faq'      => sf_clean_faq($value, $field['max']),
            'skins'    => sf_clean_skins($value, $field['max']),
            'routine'  => sf_clean_routine($value, $field['max']),
            'money'    => is_numeric($value) && (float)$value > 0 ? min(1000000000.0, round((float)$value, 2)) : 0.0,
            'breaks'   => sf_clean_breaks($value, $field['max']),
            'handle'   => sf_social_handle(is_string($value) ? $value : ''),
            default    => null,
        };
    }
    if ($type === 'hero') {
        // The slides lead; an older setting has only the one photo. The first
        // slide stays in 'image' for whatever still reads that.
        if (!$clean['slides'] && $clean['image'] !== '') {
            $clean['slides'] = [$clean['image']];
        }
        $clean['image'] = $clean['slides'][0] ?? '';
        // A product's skin types are ones the finder offers
        foreach ($clean['routine'] as &$tag) {
            $tag['skins'] = array_values(array_intersect($tag['skins'], $clean['skins']));
        }
        unset($tag);
    }
    if ($type === 'location' && ($clean['lat'] === null || $clean['lng'] === null)) {
        $clean['lat'] = $clean['lng'] = null;
    }
    return $clean;
}

/**
 * Hero photos and videos, each belonging to this business, without repeats.
 */
function sf_clean_media_list(mixed $value, int $business_id, int $max): array {
    $paths = [];
    foreach (is_array($value) ? $value : [] as $one) {
        $path = sf_clean_media_path($one, $business_id);
        if ($path !== '' && !in_array($path, $paths, true)) {
            $paths[] = $path;
            if (count($paths) >= $max) {
                break;
            }
        }
    }
    return $paths;
}

/**
 * A still photo of this business's (no video or GIF), for the page background.
 */
function sf_clean_photo_path(mixed $value, int $business_id): string {
    $path = sf_clean_media_path($value, $business_id);
    return preg_match('/\.(png|jpe?g|webp)$/', $path) ? $path : '';
}

/**
 * A colour choice: the brand's colours from Settings (light or dark; the
 * look's own while there is no brand), the look's own set, one of the four
 * sets, three colours read from the logo, or the owner's own accent over
 * another set.
 */
function sf_clean_colours(array $raw): array {
    $set = is_string($raw['set'] ?? null) ? $raw['set'] : 'brand';
    if ($set === 'brand') {
        $mode = $raw['mode'] ?? '';
        return ['set' => 'brand', 'mode' => in_array($mode, ['light', 'dark'], true) ? $mode : ''];
    }
    if ($set === 'look' || isset(storefront_colour_sets()[$set])) {
        return ['set' => $set];
    }
    if ($set === 'logo') {
        $ground = sf_hex($raw['ground'] ?? null);
        $ink    = sf_hex($raw['ink'] ?? null);
        $accent = sf_hex($raw['accent'] ?? null);
        if ($ground && $ink && $accent) {
            return ['set' => 'logo', 'name' => sf_clean_line($raw['name'] ?? '', 20) ?: 'Logo',
                    'ground' => $ground, 'ink' => $ink, 'accent' => $accent];
        }
    }
    if ($set === 'custom' && ($accent = sf_hex($raw['accent'] ?? null))) {
        $base = is_string($raw['base'] ?? null) && isset(storefront_colour_sets()[$raw['base']]) ? $raw['base'] : 'look';
        return ['set' => 'custom', 'base' => $base, 'accent' => $accent];
    }
    return ['set' => 'brand', 'mode' => ''];
}

function sf_bool(mixed $value): bool {
    if (is_string($value)) {
        return !in_array(strtolower(trim($value)), ['', '0', 'false', 'off', 'no'], true);
    }
    return (bool)$value;
}

/**
 * One line of text: no line breaks, no control characters, no runs of spaces.
 */
function sf_clean_line(mixed $value, int $max): string {
    if (!is_string($value) && !is_int($value) && !is_float($value)) {
        return '';
    }
    $value = preg_replace('/[\p{Cc}\x{202A}-\x{202E}\x{2066}-\x{2069}]+/u', ' ', (string)$value) ?? '';
    $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    return mb_substr($value, 0, $max);
}

/**
 * Paragraphs: line breaks kept (at most one empty line in a row), other
 * control characters dropped.
 */
function sf_clean_text(mixed $value, int $max): string {
    if (!is_string($value)) {
        return '';
    }
    $value = str_replace(["\r\n", "\r"], "\n", $value);
    $value = preg_replace('/[^\P{Cc}\n]+|[\x{202A}-\x{202E}\x{2066}-\x{2069}]+/u', '', $value) ?? '';
    $value = preg_replace('/[ \t\x{00A0}]+/u', ' ', $value) ?? '';
    $value = preg_replace('/ *\n */', "\n", $value) ?? '';
    $value = preg_replace('/\n{3,}/', "\n\n", $value) ?? '';
    return trim(mb_substr(trim($value), 0, $max));
}

function sf_clean_ids(mixed $value, int $max): array {
    $ids = [];
    foreach (is_array($value) ? $value : [] as $id) {
        $id = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id !== false && !in_array($id, $ids, true)) {
            $ids[] = $id;
            if (count($ids) >= $max) {
                break;
            }
        }
    }
    return $ids;
}

function sf_clean_faq(mixed $value, int $max): array {
    $items = [];
    foreach (is_array($value) ? $value : [] as $item) {
        if (!is_array($item)) {
            continue;
        }
        $q = sf_clean_line($item['q'] ?? '', 160);
        $a = sf_clean_text($item['a'] ?? '', 800);
        if ($q === '' && $a === '') {
            continue;
        }
        $items[] = ['q' => $q, 'a' => $a];
        if (count($items) >= $max) {
            break;
        }
    }
    return $items;
}

/**
 * The skin finder's skin types: short labels, each once.
 */
function sf_clean_skins(mixed $value, int $max): array {
    $skins = [];
    foreach (is_array($value) ? $value : [] as $one) {
        $label = sf_clean_line($one, 24);
        if ($label !== '' && !in_array(mb_strtolower($label), array_map('mb_strtolower', $skins), true)) {
            $skins[] = $label;
            if (count($skins) >= $max) {
                break;
            }
        }
    }
    return $skins;
}

/**
 * The owner's word on products in the skin finder: each product once, with
 * its step of a routine ('none' keeps it out) and the skin types it is for
 * (none: every skin).
 */
function sf_clean_routine(mixed $value, int $max): array {
    $tags = [];
    foreach (is_array($value) ? $value : [] as $one) {
        $id = is_array($one) ? filter_var($one['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
        if ($id === false || isset($tags[$id])) {
            continue;
        }
        $step = is_string($one['step'] ?? null) && (isset(SF_ROUTINE_STEPS[$one['step']]) || $one['step'] === 'none') ? $one['step'] : 'none';
        $tags[$id] = ['id' => $id, 'step' => $step, 'skins' => sf_clean_skins($one['skins'] ?? [], 6)];
        if (count($tags) >= $max) {
            break;
        }
    }
    return array_values($tags);
}

/**
 * Market's prices for buying more: per product up to three steps, each
 * "from this many, this much each", in order. A step needs at least two and
 * a price above nothing; one repeating an earlier quantity is dropped.
 */
function sf_clean_breaks(mixed $value, int $max): array {
    $steps = [];
    foreach (is_array($value) ? $value : [] as $one) {
        if (!is_array($one)) {
            continue;
        }
        $id   = filter_var($one['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $from = filter_var($one['from'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 2, 'max_range' => 999]]);
        $price = is_numeric($one['price'] ?? null) ? round((float)$one['price'], 2) : 0.0;
        if ($id === false || $from === false || $price <= 0 || $price > 1000000000 || isset($steps[$id][$from])) {
            continue;
        }
        if (count($steps[$id] ?? []) >= 3) {
            continue;
        }
        $steps[$id][$from] = $price;
        if (array_sum(array_map('count', $steps)) >= $max) {
            break;
        }
    }
    $clean = [];
    foreach ($steps as $id => $by_from) {
        ksort($by_from);
        foreach ($by_from as $from => $price) {
            $clean[] = ['id' => $id, 'from' => $from, 'price' => $price];
        }
    }
    return $clean;
}

/**
 * A hero photo or video belongs to this business: one uploaded in Shop
 * Studio, or one from the old Landing Page screen.
 */
function sf_clean_media_path(mixed $value, int $business_id): string {
    if (!is_string($value) || $value === '') {
        return '';
    }
    $pattern = '#^uploads/(storefront|heroes)/' . $business_id . '/[A-Za-z0-9_.-]{1,80}\.(png|jpe?g|webp|gif|mp4)$#';
    return preg_match($pattern, $value) && !str_contains($value, '..') ? $value : '';
}

/**
 * Keep the handle from whatever was pasted: "@bisi.indigo",
 * "instagram.com/bisi.indigo/" and "bisi.indigo" all give "bisi.indigo".
 */
function sf_social_handle(string $raw): string {
    $raw = trim($raw);
    if (preg_match('#^(?:https?://)?(?:www\.|m\.)?(?:instagram\.com|facebook\.com|fb\.com|tiktok\.com|x\.com|twitter\.com)/(?:@)?([^/?\#]+)#i', $raw, $m)) {
        $raw = $m[1];
    }
    $raw = ltrim($raw, '@');
    return preg_match('/^[A-Za-z0-9._-]{1,60}$/', $raw) ? $raw : '';
}

/**
 * The 11-character id from any common YouTube link, or null.
 */
function sf_youtube_id(string $url): ?string {
    if (preg_match('#^(?:https?://)?(?:www\.|m\.)?(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/)|youtu\.be/)([A-Za-z0-9_-]{11})(?:[?&\#].*)?$#', trim($url), $m)) {
        return $m[1];
    }
    return null;
}

/**
 * One section of a settings array, or null.
 */
function sf_section(array $settings, string $type): ?array {
    foreach ($settings['sections'] as $section) {
        if ($section['type'] === $type) {
            return $section;
        }
    }
    return null;
}

/* ──────────────────────────────────────────
 *  COLOURS
 * ────────────────────────────────────────── */

/**
 * '#abc', 'ABCDEF' or '#AbCdEf' as '#ABCDEF'; null for anything else.
 */
function sf_hex(mixed $value): ?string {
    if (!is_string($value)) {
        return null;
    }
    $hex = ltrim(trim($value), '#');
    if (preg_match('/^[0-9a-fA-F]{3}$/', $hex)) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    return preg_match('/^[0-9a-fA-F]{6}$/', $hex) ? '#' . strtoupper($hex) : null;
}

function sf_rgb(string $hex): array {
    $n = hexdec(substr($hex, 1));
    return [($n >> 16) & 255, ($n >> 8) & 255, $n & 255];
}

/**
 * Mix two colours: $t = 0 gives $a, $t = 1 gives $b.
 */
function sf_mix(string $a, string $b, float $t): string {
    $ra = sf_rgb($a);
    $rb = sf_rgb($b);
    $out = '#';
    for ($i = 0; $i < 3; $i++) {
        $out .= sprintf('%02X', (int)round($ra[$i] + ($rb[$i] - $ra[$i]) * $t));
    }
    return $out;
}

/**
 * Relative luminance, as WCAG defines it.
 */
function sf_luminance(string $hex): float {
    $sum = 0.0;
    foreach (sf_rgb($hex) as $i => $c) {
        $c /= 255;
        $c = $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        $sum += [0.2126, 0.7152, 0.0722][$i] * $c;
    }
    return $sum;
}

function sf_contrast(string $a, string $b): float {
    $la = sf_luminance($a);
    $lb = sf_luminance($b);
    return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
}

/**
 * $fg moved towards black (on a light background) or white (on a dark one)
 * just far enough to reach $min against $bg.
 */
function sf_readable(string $fg, string $bg, float $min = 4.5): string {
    if (sf_contrast($fg, $bg) >= $min) {
        return $fg;
    }
    $target = sf_contrast('#000000', $bg) >= sf_contrast('#FFFFFF', $bg) ? '#000000' : '#FFFFFF';
    for ($i = 1; $i <= 25; $i++) {
        $try = sf_mix($fg, $target, $i / 25);
        if (sf_contrast($try, $bg) >= $min) {
            return $try;
        }
    }
    return $target;
}

/**
 * An accent and the text colour that goes on it. White text (or the set's
 * own) is kept where it can be: the accent is darkened until white reads at
 * 4.5:1. A very light accent, like a yellow, takes dark text instead, which
 * keeps its colour.
 */
function sf_accent_pair(string $accent, string $ground, string $ink, ?string $on = null): array {
    $dark_text = sf_luminance($ink) < sf_luminance($ground) ? $ink : $ground;
    $on        = sf_hex($on) ?? (sf_luminance($ground) < 0.2 ? $dark_text : '#FFFFFF');
    if (sf_contrast($on, $accent) >= 4.5) {
        return [$accent, $on];
    }
    $other = $on === $dark_text ? '#FFFFFF' : $dark_text;
    if (sf_luminance($accent) > 0.4 && sf_contrast($other, $accent) >= 4.5) {
        return [$accent, $other];
    }
    $target = sf_luminance($on) > sf_luminance($accent) ? '#000000' : '#FFFFFF';
    for ($i = 1; $i <= 25; $i++) {
        $try = sf_mix($accent, $target, $i / 25);
        if (sf_contrast($on, $try) >= 4.5) {
            return [$try, $on];
        }
    }
    return [$target, $on];
}

/**
 * Every colour a page needs, from a set that may give only ground, ink and
 * accent. Text colours are checked against the ground and the cards they sit
 * on; the band (a look's second colour) gets whichever of ink or white reads
 * on it.
 */
function sf_palette(array $set, ?string $band = null): array {
    $ground = sf_hex($set['ground'] ?? null) ?? '#FFFFFF';
    $dark   = sf_contrast('#FFFFFF', $ground) > sf_contrast('#000000', $ground);
    $ink    = sf_readable(sf_hex($set['ink'] ?? null) ?? ($dark ? '#F5F5F5' : '#1A1A1A'), $ground, 7.0);

    $surface = sf_hex($set['surface'] ?? null) ?? ($dark ? sf_mix($ground, '#FFFFFF', 0.07) : sf_mix($ground, $ink, 0.045));
    $card    = sf_hex($set['card'] ?? null)
            ?? ($dark ? $surface : (sf_luminance($ground) > 0.96 ? $surface : '#FFFFFF'));

    // An accent too close to the ground (white on white) would hide buttons
    $accent = sf_readable(sf_hex($set['accent'] ?? null) ?? $ink, $ground, 1.5);
    [$accent, $on] = sf_accent_pair($accent, $ground, $ink, $set['on'] ?? null);

    $muted = sf_hex($set['muted'] ?? null) ?? sf_mix($ink, $ground, 0.32);
    $muted = sf_readable(sf_readable($muted, $ground), $card);
    $accent_text = sf_readable(sf_readable($accent, $ground), $card);

    $line  = sf_hex($set['line'] ?? null) ?? sf_mix($ground, $ink, 0.13);
    $motif = sf_hex($set['motif'] ?? null) ?? ($on === '#FFFFFF' ? sf_mix($accent, '#FFFFFF', 0.8) : sf_mix($accent, $on, 0.6));

    $band = sf_hex($band) ?? sf_mix($accent, $ground, 0.78);
    $band_ink = sf_contrast($ink, $band) >= sf_contrast('#FFFFFF', $band) ? $ink : '#FFFFFF';
    if (sf_contrast($band_ink, $band) < 4.5) {
        $band_ink = sf_contrast('#000000', $band) >= sf_contrast('#FFFFFF', $band) ? '#000000' : '#FFFFFF';
    }

    return [
        'ground'      => $ground,
        'ink'         => sf_readable($ink, $card),
        'muted'       => $muted,
        'accent'      => $accent,
        'on'          => $on,
        'accent_text' => $accent_text,
        'line'        => $line,
        'field'       => sf_readable($line, $ground, 3.0),
        'surface'     => $surface,
        'card'        => $card,
        'motif'       => $motif,
        'band'        => $band,
        'band_ink'    => $band_ink,
        'tint'        => sf_mix($accent, $ground, 0.87),
        'dark'        => $dark,
    ];
}

/**
 * The colour set a settings array asks for, before contrast checks. 'brand'
 * needs the business: its colours from Settings, else the look's own.
 */
function sf_colour_set(array $settings, array $business = []): array {
    $look   = storefront_looks()[$settings['look']] ?? storefront_looks()['atelier'];
    $c      = $settings['colours'];
    $sets   = storefront_colour_sets();
    $chosen = fn(string $key) => $key === 'look' ? $look['colours'] : $sets[$key];

    if ($c['set'] === 'brand') {
        $brand = brand_colours($business);
        if ($brand === null) {
            return $look['colours'];
        }
        // Light unless the owner chose dark, or the look itself is dark
        $mode = ($c['mode'] ?? '') !== '' ? $c['mode'] : (sf_palette($look['colours'])['dark'] ? 'dark' : 'light');
        return brand_colour_set($brand, $mode);
    }

    return match ($c['set']) {
        'logo'   => ['ground' => $c['ground'], 'ink' => $c['ink'], 'accent' => $c['accent']],
        // The owner's own accent over another set: what follows from the old
        // accent (text on it, its pattern colour) is worked out again
        'custom' => array_diff_key(['accent' => $c['accent']] + $chosen($c['base']), ['on' => 1, 'motif' => 1]),
        default  => $chosen($c['set']),
    };
}

/**
 * Everything a page needs to draw these settings: the look, the font pair,
 * the checked colours and the CSS custom properties that carry them. With
 * the business, font '' and colours 'brand' follow its brand from Settings.
 */
function storefront_resolve(array $settings, array $business = []): array {
    $looks = storefront_looks();
    $look  = $looks[$settings['look']] ?? $looks['atelier'];
    $fonts = storefront_fonts();
    $brand_pair = $settings['font'] === '' && $business ? brand_font_pair($business) : null;
    $pair  = $brand_pair ? 'brand' : ($settings['font'] !== '' ? $settings['font'] : $look['pair']);
    $font  = $brand_pair ?? ($fonts[$pair] ?? $fonts['bodoni']);

    $set = sf_colour_set($settings, $business);
    // A look's own second colour stays with it; a brand brings its own
    $palette = sf_palette($set, $look['band'] ?? ($settings['colours']['set'] === 'brand' ? ($set['band'] ?? null) : null));
    if ($look['backdrop']) {
        // Text over the faint page photo: still 4.5:1 where the photo is
        // darkest (on a light ground) or lightest (on a dark one)
        $worst = sf_mix($palette['ground'], $palette['dark'] ? '#FFFFFF' : '#000000', 0.16);
        $palette['muted']       = sf_readable($palette['muted'], $worst);
        $palette['accent_text'] = sf_readable($palette['accent_text'], $worst);
    }
    // Market's painted letters: its second colour, made readable on the
    // accent. The painted shadows show only where they stand apart: a dark
    // drop under light letters, the second colour under the prices
    $sign = sf_readable($palette['band'], $palette['accent']);
    $vars = [
        '--sf-ground'      => $palette['ground'],
        '--sf-ink'         => $palette['ink'],
        '--sf-muted'       => $palette['muted'],
        '--sf-accent'      => $palette['accent'],
        '--sf-on'          => $palette['on'],
        '--sf-accent-text' => $palette['accent_text'],
        '--sf-line'        => $palette['line'],
        '--sf-field'       => $palette['field'],
        '--sf-surface'     => $palette['surface'],
        '--sf-card'        => $palette['card'],
        '--sf-motif'       => $palette['motif'],
        '--sf-band'        => $palette['band'],
        '--sf-band-ink'    => $palette['band_ink'],
        '--sf-tint'        => $palette['tint'],
        '--sf-sign'        => $sign,
        '--sf-sign-shade'  => sf_luminance($sign) > sf_luminance($palette['accent']) ? ($palette['dark'] ? $palette['ground'] : $palette['ink']) : 'transparent',
        '--sf-price-shade' => sf_contrast($palette['band'], $palette['accent_text']) >= 3 ? $palette['band'] : 'transparent',
        // The dark of the palette, for fades over photos, and the light words on it
        '--sf-scrim'       => $palette['dark'] ? $palette['ground'] : $palette['ink'],
        '--sf-paper'       => $palette['dark'] ? $palette['ink'] : $palette['ground'],
        '--sf-head'        => $font['head_stack'],
        '--sf-body'        => $font['body_stack'],
        '--sf-head-weight' => (string)$font['weight'],
        '--sf-head-stretch'=> $font['stretch'],
        '--sf-scale'       => (string)$font['scale'],
        '--sf-r-hero'      => $look['shape']['hero'],
        '--sf-r-tile'      => $look['shape']['tile'],
        '--sf-r-btn'       => $look['shape']['btn'],
        '--sf-r-card'      => $look['shape']['card'],
        '--sf-r-chip'      => $look['shape']['chip'],
        '--sf-r-field'     => $look['shape']['field'],
    ];

    return [
        'look'      => $settings['look'],
        'def'       => $look,
        'pair'      => $pair,
        'font'      => $font,
        'palette'   => $palette,
        'vars'      => $vars,
        'fonts_url' => $brand_pair ? $brand_pair['url'] : storefront_fonts_url($pair),
        'classes'   => 'sf look-' . $settings['look'] . ' font-' . $pair . ($palette['dark'] ? ' is-dark' : ' is-light'),
    ];
}

/**
 * The custom properties as a :root rule. Values come from the registries and
 * sf_hex(), never from what an owner typed, but are filtered once more.
 */
function storefront_css_vars(array $vars): string {
    $css = '';
    foreach ($vars as $name => $value) {
        $css .= $name . ':' . preg_replace('/[^A-Za-z0-9#%.,\' -]/', '', (string)$value) . ';';
    }
    return ':root{' . $css . '}';
}

/* ──────────────────────────────────────────
 *  CATALOGUE
 * ────────────────────────────────────────── */

/**
 * Whether a product is sold out on its shop: only when the owner switched on
 * "Show items as sold out when their stock runs out" (businesses.shop_sold_out,
 * Shop Studio › More), and only goods, as services and things people book
 * are never sold out. Options share the product's one stock count, so the
 * product's count decides. The shop, its structured data and checkout
 * (PublicController::place_order) all ask here.
 */
function shop_sold_out(array $business, array $product): bool {
    return !empty($business['shop_sold_out'])
        && empty($product['is_service'])
        && (string)($product['booking_mode'] ?? 'none') === 'none'
        && (float)($product['stock_qty'] ?? 0) <= 0;
}

/**
 * The products a shop shows, with their photos and choices, its categories
 * and its bank accounts. Products the owner switched off or deleted never
 * appear; when the catalogue section lists products, only those do. Each
 * says whether it is sold out (shop_sold_out).
 */
function storefront_catalogue(array $business, array $settings): array {
    $db  = get_db();
    $bid = (int)$business['id'];

    $stmt = $db->prepare("
        SELECT id, name, description, category, image_path, sell_price, unit, is_service, stock_qty, created_at, updated_at,
               booking_mode, booking_minutes, booking_capacity, deposit_type, deposit_value
        FROM products
        WHERE business_id = ? AND deleted_at IS NULL AND is_active = 1
        ORDER BY name ASC
        LIMIT " . (int)MAX_PRODUCTS);
    $stmt->execute([$bid]);
    $rows = $stmt->fetchAll();

    $only = sf_section($settings, 'catalogue')['only'] ?? [];
    if ($only) {
        $rows = array_values(array_filter($rows, fn($r) => in_array((int)$r['id'], $only, true)));
    }

    $images = [];
    $stmt = $db->prepare("
        SELECT product_id, image_path FROM product_images
        WHERE business_id = ?
        ORDER BY product_id, is_cover DESC, sort_order ASC, id ASC
    ");
    $stmt->execute([$bid]);
    foreach ($stmt->fetchAll() as $img) {
        $images[(int)$img['product_id']][] = $img['image_path'];
    }

    $variants = [];
    $stmt = $db->prepare("
        SELECT g.id AS group_id, g.product_id, g.name AS group_name, o.label, o.price_delta
        FROM product_variant_groups g
        LEFT JOIN product_variant_options o ON o.variant_group_id = g.id
        WHERE g.business_id = ?
        ORDER BY g.product_id, g.sort_order, g.id, o.sort_order, o.id
    ");
    $stmt->execute([$bid]);
    foreach ($stmt->fetchAll() as $row) {
        $pid = (int)$row['product_id'];
        $gid = (int)$row['group_id'];
        // 'deltas' maps an option's label to its price change; only options that
        // actually move the price are listed, so most groups carry none.
        $variants[$pid][$gid] ??= ['name' => $row['group_name'], 'options' => [], 'deltas' => []];
        if ($row['label'] !== null && $row['label'] !== '') {
            $variants[$pid][$gid]['options'][] = $row['label'];
            $delta = (float)$row['price_delta'];
            if ($delta != 0.0) {
                $variants[$pid][$gid]['deltas'][$row['label']] = $delta;
            }
        }
    }

    $breaks = storefront_breaks($settings);
    $items = [];
    $categories = [];   // in the order their first product was added
    $first_id = [];
    $updated = null;
    foreach ($rows as $row) {
        $pid = (int)$row['id'];
        $paths = $images[$pid] ?? [];
        if (!$paths && $row['image_path']) {
            $paths = [$row['image_path']];
        }
        $groups = array_values(array_filter($variants[$pid] ?? [], fn($g) => $g['options']));
        $item = [
            'id'          => $pid,
            'name'        => (string)$row['name'],
            'description' => trim((string)$row['description']),
            'category'    => trim((string)$row['category']),
            'unit'        => trim((string)$row['unit']),
            'price'       => (float)$row['sell_price'],
            'is_service'  => (bool)$row['is_service'],
            'created_at'  => (string)$row['created_at'],
            'images'      => array_values(array_filter($paths, fn($p) => is_string($p) && $p !== '')),
            'variants'    => $groups,
            'booking'     => booking_info($row),   // null unless people book it
            'sold_out'    => shop_sold_out($business, $row),
        ];
        $item += sf_item_details($item);
        // Each step cheaper than the last, and than the price itself: a price
        // the owner changed since may have overtaken a step
        $item['breaks'] = [];
        $below = $item['price'];
        foreach ($breaks[$pid] ?? [] as [$from, $each]) {
            if ($each < $below) {
                $item['breaks'][] = ['from' => $from, 'price' => $each];
                $below = $each;
            }
        }
        $items[] = $item;
        if ($item['category'] !== '') {
            $first_id[$item['category']] = min($first_id[$item['category']] ?? PHP_INT_MAX, $pid);
        }
        if ($updated === null || $row['updated_at'] > $updated) {
            $updated = $row['updated_at'];
        }
    }
    asort($first_id);
    $categories = array_keys($first_id);

    $stmt = $db->prepare("
        SELECT id, kind, bank_name, account_name, account_number, is_default
        FROM bank_accounts
        WHERE business_id = ? AND is_active = 1 AND deleted_at IS NULL
        ORDER BY is_default DESC, sort_order ASC, id ASC
    ");
    $stmt->execute([$bid]);
    $banks = $stmt->fetchAll();

    $prices = array_filter(array_column($items, 'price'), fn($p) => $p > 0);

    return [
        'items'        => $items,
        'categories'   => array_map('strval', $categories),
        'banks'        => $banks,
        'min_price'    => $prices ? min($prices) : null,
        'updated_at'   => $updated,
        'has_products' => (bool)array_filter($items, fn($i) => !$i['is_service']),
        'has_services' => (bool)array_filter($items, fn($i) => $i['is_service']),
        'bookable'     => array_values(array_filter($items, fn($i) => $i['booking'] !== null)),
    ];
}

/**
 * How people pay into the shop's accounts, in words for "Pay by ...":
 * Mobile Money, bank transfer, or both (Settings › Payment accounts).
 */
function sf_pay_way(array $banks): string {
    $kinds = array_unique(array_column($banks, 'kind'));
    $momo  = in_array('mobile_money', $kinds, true);
    $bank  = in_array('bank', $kinds, true) || !$kinds;
    return $momo && $bank ? 'Mobile Money or bank transfer' : ($momo ? 'Mobile Money' : 'bank transfer');
}

/**
 * What the looks read from a product beyond its name and price:
 *   swatches  a colour, fabric or print group, for Atelier's swatches
 *   sizes     a size, weight or volume group, for Shea's size buttons
 *   chips     a few options to show as chips (Volt's specs, Haven's
 *             amenities): the first group that is neither of the above
 *   lines     the description, line by line, for Desk's "what's included"
 */
function sf_item_details(array $item): array {
    $swatches = null;
    $sizes    = null;
    $chips    = [];
    foreach ($item['variants'] as $group) {
        $name = strtolower($group['name']);
        if ($swatches === null && preg_match('/colou?r|fabric|print|pattern|shade|finish|material|cloth/', $name)) {
            $swatches = ['name' => $group['name'], 'options' => array_map(
                fn($label) => ['label' => $label, 'hex' => sf_swatch_colour($label)],
                $group['options']
            )];
        } elseif ($sizes === null && preg_match('/size|weight|volume|capacity|pack|length|litre|liter|ml|kg/', $name)) {
            $sizes = $group;
        } elseif (!$chips) {
            $chips = array_slice($group['options'], 0, 3);
        }
    }

    $lines = [];
    foreach (preg_split('/\n+/', $item['description']) as $line) {
        $line = trim(preg_replace('/^[\s\-–—•*·✓✔]+/u', '', $line) ?? '');
        if ($line !== '') {
            $lines[] = $line;
        }
    }

    return ['swatches' => $swatches, 'sizes' => $sizes, 'chips' => $chips, 'lines' => count($lines) > 1 ? array_slice($lines, 0, 6) : []];
}

/**
 * A colour for a swatch whose name is a colour ("Navy", "wine red"), or null
 * for names like "Olokun" that get a pattern instead.
 */
function sf_swatch_colour(string $label): ?string {
    static $names = [
        'black' => '#1B1B1B', 'white' => '#F7F7F5', 'cream' => '#F1E7D0', 'ivory' => '#F4EEDC', 'beige' => '#D9C6A5',
        'grey' => '#8C8F94', 'gray' => '#8C8F94', 'silver' => '#BFC3C8', 'charcoal' => '#3A3D42',
        'red' => '#B3261E', 'wine' => '#6E1A2D', 'maroon' => '#6B1B24', 'burgundy' => '#6D1A36', 'pink' => '#E58FAE',
        'peach' => '#F2B48C', 'orange' => '#E2711D', 'coral' => '#E8675A', 'yellow' => '#F2C53D', 'mustard' => '#C99A2E',
        'gold' => '#C9A13B', 'brown' => '#7A4E2D', 'tan' => '#B98B5E', 'khaki' => '#A89A6A', 'olive' => '#6B6B2A',
        'green' => '#2E7D4F', 'emerald' => '#1F7A55', 'mint' => '#9ED9C0', 'teal' => '#1E7B7A', 'turquoise' => '#2BB3B1',
        'blue' => '#2D5DA8', 'navy' => '#1C2B4A', 'indigo' => '#2E3F9E', 'sky' => '#7DB7E8', 'purple' => '#6A3D9A',
        'lilac' => '#B9A2D8', 'lavender' => '#B8A9D9', 'violet' => '#6B3FA0', 'magenta' => '#B0307A', 'nude' => '#D6A98A',
    ];
    $words = preg_split('/[^a-z]+/', strtolower($label), -1, PREG_SPLIT_NO_EMPTY);
    foreach (array_reverse($words) as $word) {
        if (isset($names[$word])) {
            return $names[$word];
        }
    }
    return null;
}

/**
 * The products for "New in": the owner's picks in their order, else the
 * newest few once a shop has enough that a short list helps.
 */
function storefront_featured(array $catalogue, array $section, int $count = 4): array {
    $by_id = array_column($catalogue['items'], null, 'id');
    if ($section['products']) {
        return array_values(array_filter(array_map(fn($id) => $by_id[$id] ?? null, $section['products'])));
    }
    if (count($catalogue['items']) < 6) {
        return [];
    }
    $items = $catalogue['items'];
    usort($items, fn($a, $b) => [$b['created_at'], $b['id']] <=> [$a['created_at'], $a['id']]);
    return array_slice($items, 0, $count);
}

/**
 * What Volt's spotlight shows: the owner's picks, else the devices on sale
 * (not services, nothing people book), with a photo first, newest first.
 */
function storefront_devices(array $catalogue, array $hero, int $max = 8): array {
    $goods = ['items' => array_values(array_filter($catalogue['items'], fn($i) => !$i['is_service'] && $i['booking'] === null))];
    return storefront_hero_picks($goods, $hero, $max);
}

/**
 * Market's prices for buying more, by product: [[from, price each], …],
 * fewest first. Only Market sells this way; other looks get none, so a shop
 * that changes look never shows one price and charges another.
 */
function storefront_breaks(array $settings): array {
    if (($settings['look'] ?? '') !== 'market') {
        return [];
    }
    $by_product = [];
    foreach (sf_section($settings, 'hero')['breaks'] ?? [] as $step) {
        $by_product[$step['id']][] = [$step['from'], $step['price']];
    }
    return $by_product;
}

/**
 * What one costs when this many are bought: the lowest step reached, never
 * more than the product's own price. As priceAt() in storefront.js; checkout
 * (PublicController::place_order) charges this.
 */
function sf_price_at(float $price, array $breaks, int $qty): float {
    foreach ($breaks as $step) {
        [$from, $each] = isset($step['from']) ? [$step['from'], $step['price']] : $step;
        if ($qty >= $from) {
            $price = min($price, (float)$each);
        }
    }
    return $price;
}

/**
 * What a phone buyer checks first, read from what the owner already writes:
 * the device's condition ("UK used", from its name, options or words), its
 * battery health ("Battery 86%") and a few facts for Volt's spotlight: its
 * options and the short phrases of its description.
 *   ['grade' => 'UK used' or '', 'battery' => 86 or null, 'specs' => [...]]
 */
function sf_device_facts(array $item): array {
    $options = [];
    foreach ($item['variants'] as $group) {
        $options = array_merge($options, $group['options']);
    }
    $words = ' ' . mb_strtolower($item['name'] . ' ' . $item['description'] . ' ' . implode(' ', $options)) . ' ';
    $grade = match (true) {
        (bool)preg_match('/\b(brand[- ]?new|sealed|new in (the )?box)\b/u', $words)       => 'Brand new',
        (bool)preg_match('/\b(uk|london)[- ]used\b/u', $words)                          => 'UK used',
        (bool)preg_match('/\bus[- ]used\b/u', $words)                                   => 'US used',
        (bool)preg_match('/\b(foreign[- ]used|tokunbo)\b/u', $words)                    => 'Foreign used',
        (bool)preg_match('/\b(nigerian|naija|9ja|fairly|locally)[- ]used\b/u', $words)  => 'Nigerian used',
        (bool)preg_match('/\b(refurbished|renewed)\b/u', $words)                        => 'Refurbished',
        default                                                                        => '',
    };
    $battery = preg_match('/battery(?:\s*health)?\s*[:\-]?\s*(\d{2,3})\s*%/u', $words, $m) && (int)$m[1] <= 100 ? (int)$m[1] : null;
    $is_grade = fn(string $text) => (bool)preg_match('/\b(new|used|sealed|tokunbo|refurbished|renewed)\b/iu', $text);

    // Storage and memory in the name ("iPhone 12, 128GB") come first
    $specs = preg_match_all('/\b\d{1,4}\s?(?:GB|TB)(?:\s?RAM)?\b/iu', $item['name'], $m) ? array_map(fn($x) => strtoupper(str_replace(' ', '', $x)), $m[0]) : [];
    foreach ($item['variants'] as $group) {
        $name = mb_strtolower($group['name']);
        $opts = array_values(array_filter($group['options'], fn($o) => !$is_grade($o)));
        if (!$opts || preg_match('/condition|grade/u', $name)) {
            continue;
        }
        if (preg_match('/colou?r/u', $name)) {
            $specs[] = count($opts) === 1 ? $opts[0] : count($opts) . ' colours';
        } elseif (preg_match('/spec|feature|detail/u', $name) || count($opts) === 1) {
            array_push($specs, ...$opts);
        } else {
            $specs[] = implode(' or ', array_slice($opts, 0, 2));
        }
    }
    foreach (preg_split('/[.;\n]+/u', $item['description']) as $phrase) {
        $phrase = trim($phrase, " \t-–•*");
        $known = array_filter($specs, fn($spec) => mb_stripos($phrase, $spec) !== false);
        if ($phrase !== '' && mb_strlen($phrase) <= 26 && !$is_grade($phrase) && !$known) {
            $specs[] = $phrase;
        }
    }
    $specs = array_values(array_unique(array_filter($specs, fn($s) => mb_strlen($s) <= 26)));
    // One that another says already ("128GB" in "128GB or 256GB") goes
    $specs = array_values(array_filter($specs, fn($a) => !array_filter($specs, fn($b) => $b !== $a && mb_stripos($b, $a) !== false)));
    return ['grade' => $grade, 'battery' => $battery, 'specs' => array_slice($specs, 0, 4)];
}

/**
 * What Buka's turntable and Glow's stories show: the owner's picks in their
 * order, else what is on sale with a photo first, newest first.
 */
function storefront_hero_picks(array $catalogue, array $hero, int $max = 8): array {
    $by_id = array_column($catalogue['items'], null, 'id');
    if ($hero['picks']) {
        return array_slice(array_values(array_filter(array_map(fn($id) => $by_id[$id] ?? null, $hero['picks']))), 0, $max);
    }
    $items = array_values(array_filter($catalogue['items'], fn($i) => !$i['sold_out']));
    usort($items, fn($a, $b) => [!$b['images'] ? 0 : 1, $b['created_at'], $b['id']] <=> [!$a['images'] ? 0 : 1, $a['created_at'], $a['id']]);
    return array_slice($items, 0, $max);
}

/**
 * The steps of a skin routine, in the order they are used, with what the
 * skin finder says about each.
 */
const SF_ROUTINE_STEPS = [
    'cleanse'    => ['name' => 'Cleanse',    'why' => 'Washes off the day'],
    'tone'       => ['name' => 'Tone',       'why' => 'Calms and balances'],
    'treat'      => ['name' => 'Treat',      'why' => 'Works on what bothers you'],
    'moisturise' => ['name' => 'Moisturise', 'why' => 'Locks the water in'],
    'protect'    => ['name' => 'Protect',    'why' => 'Guards against the sun'],
];

/**
 * A product's step in a routine, guessed from its name and category until
 * the owner says: soap cleanses, a toner tones, a serum treats, a butter
 * moisturises, sunscreen protects. Perfumes, services and anything else:
 * 'none'.
 */
function sf_routine_guess(array $item): string {
    if (!empty($item['is_service'])) {
        return 'none';
    }
    $text = mb_strtolower($item['name'] . ' · ' . ($item['category'] ?? ''));
    return match (true) {
        (bool)preg_match('/perfume|fragrance|cologne|\boud\b|scent|incense|candle|diffuser|deodor/u', $text)            => 'none',
        (bool)preg_match('/sunscreen|sun ?block|sun cream|\bspf\b/u', $text)                                            => 'protect',
        (bool)preg_match('/soap|cleans|face wash|body wash|\bwash\b|scrub|exfoliat/u', $text)                           => 'cleanse',
        (bool)preg_match('/toner|tonic|\bmist\b|astringent/u', $text)                                                    => 'tone',
        (bool)preg_match('/serum|treatment|\bspots?\b|\bmask\b|ampoule|essence|face oil|facial oil|\bclay\b/u', $text) => 'treat',
        (bool)preg_match('/cream|butter|lotion|balm|moistur|\boil\b|\bgel\b|whipped/u', $text)                           => 'moisturise',
        default                                                                                                          => 'none',
    };
}

/**
 * The skin types a product's words mention, of those offered ("For
 * sensitive skin" → Sensitive). Only a guess: it puts the product first for
 * those skins without keeping it from the others.
 */
function sf_skins_guess(array $item, array $skins): array {
    $text = ' ' . mb_strtolower($item['name'] . ' ' . ($item['category'] ?? '') . ' ' . ($item['description'] ?? '')) . ' ';
    return array_values(array_filter($skins, function ($skin) use ($text) {
        $words = trim(preg_replace('/\bskin\b/u', '', mb_strtolower($skin)) ?? '');
        $words = $words === '' ? mb_strtolower($skin) : $words;
        return (bool)preg_match('/\b' . preg_quote(rtrim($words, 's'), '/') . 's?\b/u', $text);
    }));
}

/**
 * The skin types the finder offers: the owner's, else the shop's
 * categories that name one ("Dry skin", "Sensitive"), else five common ones.
 */
function storefront_skins(array $catalogue, array $hero): array {
    if ($hero['skins']) {
        return $hero['skins'];
    }
    $named = array_values(array_filter($catalogue['categories'], fn($c) => (bool)preg_match(
        '/\bskin\b|^(dry|oily|combination|sensitive|normal|mature|acne|eczema|dull|dark spots?|hyperpigmentation)\b/iu', $c)));
    return count($named) >= 2 ? array_slice($named, 0, 6) : ['Dry', 'Oily', 'Combination', 'Sensitive', 'Dark spots'];
}

/**
 * Shea's skin finder: for each skin type, a routine from what the shop
 * sells, one product per step in the order they are used, with the others
 * that fit each step to swap in. A product the owner placed is only for the
 * skins they ticked (none ticked: every skin); a guessed one goes first for
 * the skins its words mention and still fits the rest. Sold-out products
 * stay out. Empty when fewer than two products have a step, so the look
 * shows its shelf instead.
 *
 * Returns ['skins' => [label, …], 'routines' => [label => [['step' => key,
 * 'items' => [item, …]], …]]]: only skins with at least one step.
 */
function storefront_routines(array $catalogue, array $hero): array {
    $skins = storefront_skins($catalogue, $hero);
    $tags  = array_column($hero['routine'], null, 'id');
    $placed = [];
    foreach ($catalogue['items'] as $item) {
        if ($item['sold_out'] || $item['booking'] !== null) {
            continue;
        }
        $tag = $tags[$item['id']] ?? null;
        $step = $tag ? $tag['step'] : sf_routine_guess($item);
        if ($step !== 'none') {
            $placed[] = ['item' => $item, 'step' => $step, 'skins' => $tag ? $tag['skins'] : sf_skins_guess($item, $skins), 'strict' => $tag !== null];
        }
    }
    if (count($placed) < 2) {
        return ['skins' => [], 'routines' => []];
    }
    $routines = [];
    foreach ($skins as $skin) {
        $steps = [];
        foreach (array_keys(SF_ROUTINE_STEPS) as $step) {
            $fits = array_values(array_filter($placed, fn($p) => $p['step'] === $step
                && (!$p['strict'] || !$p['skins'] || in_array($skin, $p['skins'], true))));
            if (!$fits) {
                continue;
            }
            // For this skin first, then for every skin, then the rest; photos first
            usort($fits, fn($a, $b) => [in_array($skin, $a['skins'], true) ? 0 : ($a['skins'] ? 2 : 1), $a['item']['images'] ? 0 : 1, $a['item']['name']]
                                   <=> [in_array($skin, $b['skins'], true) ? 0 : ($b['skins'] ? 2 : 1), $b['item']['images'] ? 0 : 1, $b['item']['name']]);
            $steps[] = ['step' => $step, 'items' => array_slice(array_column($fits, 'item'), 0, 5)];
        }
        if ($steps) {
            $routines[$skin] = $steps;
        }
    }
    return ['skins' => array_keys($routines), 'routines' => $routines];
}

/**
 * The shop's opening hours, from its booking hours (Bookings › Settings),
 * when the owner has set them: per day ['open' => '08:00', 'close' => '21:00']
 * or null when closed. Null when there are none.
 */
function sf_opening_hours(array $business): ?array {
    if (empty($business['booking_settings'])) {
        return null;
    }
    $hours = booking_settings($business)['hours'];
    return array_filter($hours) ? $hours : null;
}

/* ──────────────────────────────────────────
 *  OUTPUT HELPERS
 * ────────────────────────────────────────── */

/**
 * ₦38,000, or ₦1,250.50 when there are kobo. Longer symbols get a space.
 */
function sf_money(float $amount, string $symbol): string {
    $symbol = trim($symbol) ?: '₦';
    $number = number_format($amount, fmod($amount, 1.0) == 0.0 ? 0 : 2);
    return $symbol . (mb_strlen($symbol) > 1 ? ' ' : '') . $number;
}

/**
 * A file under uploads/ as a full URL on the main site, where it is cached
 * once for every shop.
 */
function sf_media_url(string $rel): string {
    return APP_URL . '/' . implode('/', array_map('rawurlencode', explode('/', $rel)));
}

/**
 * Delete an uploaded photo for good: the file, its resized and link-preview
 * copies (served for a year), and Cloudflare's cached copies of all of them
 * (W5). Paths outside uploads/ are left alone.
 */
function storefront_forget_image(?string $rel): void {
    $rel = ltrim((string)$rel, '/');
    if ($rel === '' || !str_starts_with($rel, 'uploads/') || str_contains($rel, '..')) {
        return;
    }
    $gone = [$rel];
    if (preg_match('#^uploads/(' . STOREFRONT_VARIANT_PATH . ')$#i', $rel, $m)) {
        foreach ([...STOREFRONT_IMAGE_WIDTHS, 'full'] as $w) {
            $gone[] = 'uploads/cache/' . $w . '/' . $m[1] . '.webp';
        }
        $gone[] = 'uploads/cache/og/' . $m[1] . '.jpg';
        $gone[] = 'uploads/cache/mail/' . $m[1] . '.jpg';
        foreach (STOREFRONT_ICON_SIZES as $size) {
            $gone[] = 'uploads/cache/icon/' . $size . '/' . $m[1] . '.png';
        }
    }
    foreach ($gone as $path) {
        if (is_file(BASE_PATH . '/' . $path)) {
            @unlink(BASE_PATH . '/' . $path);
        }
    }
    if (function_exists('cloudflare_purge_files') && function_exists('after_response')) {
        $urls = array_map('sf_media_url', $gone);
        after_response(fn() => cloudflare_purge_files($urls));
    }
}

/**
 * A product's name for its address: "Adire Kaftan (Blue)" → "adire-kaftan-blue".
 */
function sf_product_slug(string $name): string {
    $ascii = function_exists('transliterator_transliterate')
        ? (transliterator_transliterate('Any-Latin; Latin-ASCII', $name) ?: $name)
        : (@iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: $name);
    $slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($ascii)) ?? '', '-');
    if (strlen($slug) > 60) {
        $slug = rtrim(substr($slug, 0, 60), '-');
        $slug = str_contains($slug, '-') ? substr($slug, 0, strrpos($slug, '-')) : $slug;
    }
    return $slug !== '' ? $slug : 'item';
}

/**
 * A product's page on its shop: /p/12-adire-kaftan. The number is what
 * counts; an old or missing name is sent on to this one.
 */
function sf_product_path(array $item): string {
    return '/p/' . (int)$item['id'] . '-' . sf_product_slug((string)$item['name']);
}

/**
 * Whether product links on the page being drawn go to product pages (live
 * shops) or only open the product sheet (Shop Studio's previews, which
 * aren't on the shop's address). shell.php sets it for each page.
 */
function sf_live_links(?bool $set = null): bool {
    static $live = true;
    if ($set !== null) {
        $live = $set;
    }
    return $live;
}

/**
 * The JPEG copy of a photo that WhatsApp, Facebook and X show in link
 * previews (they don't all read WebP), with its size: [url, width, height],
 * or null when the photo can't have one. Made on first request, like the
 * WebP copies.
 */
function sf_og_image(string $rel): ?array {
    $size = sf_image_size($rel);
    if (!$size || !preg_match('#^uploads/(' . STOREFRONT_VARIANT_PATH . ')$#i', $rel, $m)) {
        return null;
    }
    $width = min(STOREFRONT_OG_WIDTH, $size[0]);
    return [sf_media_url('uploads/cache/og/' . $m[1] . '.jpg'), $width, max(1, (int)round($size[1] * $width / $size[0]))];
}

/**
 * Width and height of an uploaded image, or null when it is missing or not
 * an image. Read once per request.
 */
function sf_image_size(string $rel): ?array {
    static $sizes = [];
    if (!array_key_exists($rel, $sizes)) {
        $sizes[$rel] = null;
        $path = BASE_PATH . '/' . $rel;
        if (str_starts_with($rel, 'uploads/') && !str_contains($rel, '..') && is_file($path)) {
            $info = @getimagesize($path);
            if ($info && $info[0] > 0 && $info[1] > 0) {
                $sizes[$rel] = [(int)$info[0], (int)$info[1]];
            }
        }
    }
    return $sizes[$rel];
}

/**
 * The WebP copies of an image at the widths that suit it, smallest first:
 * ['url 320w', …] ending with a full-size one. index.php makes each copy the
 * first time it is asked for (storefront_serve_variant).
 */
function sf_image_srcset(string $rel): array {
    $size = sf_image_size($rel);
    if (!$size || !preg_match('#^uploads/(' . STOREFRONT_VARIANT_PATH . ')$#i', $rel, $m)) {
        return [];
    }
    $set = [];
    foreach (STOREFRONT_IMAGE_WIDTHS as $width) {
        if ($width < $size[0]) {
            $set[] = sf_media_url('uploads/cache/' . $width . '/' . $m[1] . '.webp') . ' ' . $width . 'w';
        }
    }
    $set[] = sf_media_url('uploads/cache/full/' . $m[1] . '.webp') . ' ' . $size[0] . 'w';
    return $set;
}

/**
 * An uploaded image with WebP copies, its real width and height (so nothing
 * jumps while it loads), lazy loading, and the original as the fallback.
 * Returns '' when the file is missing, so callers can draw a placeholder.
 */
function sf_img(string $rel, string $sizes, string $alt = '', string $class = '', bool $eager = false): string {
    $size = sf_image_size($rel);
    if (!$size) {
        return '';
    }
    $srcset = sf_image_srcset($rel);
    $img = '<img src="' . e(sf_media_url($rel)) . '" width="' . $size[0] . '" height="' . $size[1] . '" alt="' . e($alt) . '"'
         . ($class !== '' ? ' class="' . e($class) . '"' : '')
         . ($eager ? ' fetchpriority="high"' : ' loading="lazy"') . ' decoding="async">';
    if (!$srcset) {
        return $img;
    }
    return '<picture><source type="image/webp" srcset="' . e(implode(', ', $srcset)) . '" sizes="' . e($sizes) . '">' . $img . '</picture>';
}

/**
 * Up to two initials for placeholders and monograms, from the words that
 * start with a letter ("Zobo, 1 litre" gives "ZL"); digits only when there
 * are no letters at all.
 */
function sf_initials(string $name): string {
    $words = preg_split('/[\s&\-_.,]+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY);
    $letters = '';
    $digits  = '';
    foreach ($words as $word) {
        if (preg_match('/^[^\p{L}\p{N}]*(\p{L})/u', $word, $m) && mb_strlen($letters) < 2) {
            $letters .= mb_strtoupper($m[1]);
        } elseif ($digits === '' && preg_match('/\p{N}/u', $word, $m)) {
            $digits = $m[0];
        }
    }
    return $letters !== '' ? $letters : ($digits !== '' ? $digits : 'N');
}

/**
 * Small line icons, drawn in currentColor.
 */
function sf_icon(string $name, int $size = 20, string $class = ''): string {
    static $paths = [
        'bag'       => '<path d="M6 7h12l-1 13H7L6 7z"/><path d="M9 7a3 3 0 0 1 6 0"/>',
        'basket'    => '<path d="M3 9h18l-2 10H5L3 9z"/><path d="M8 9l3-5M16 9l-3-5M9 13v3M15 13v3"/>',
        'list'      => '<path d="M8 6h12M8 12h12M8 18h12M4 6h.01M4 12h.01M4 18h.01"/>',
        'clipboard' => '<rect x="5" y="5" width="14" height="16" rx="2"/><path d="M9 3h6v4H9z"/><path d="M9 12h6M9 16h4"/>',
        'whatsapp'  => '<path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.6A8 8 0 1 1 21 12z"/><path d="M9.2 9.3c.3 1.9 2.3 4.2 4.6 4.6l.9-1 1.6.8-.4 1.3c-2.9.4-6.5-3-6.4-6.2l1.2-.5.8 1.6-1 .9" stroke-width="1.4"/>',
        'instagram' => '<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><path d="M17.5 6.5h.01"/>',
        'facebook'  => '<path d="M14 8h3V4h-3a4 4 0 0 0-4 4v3H7v4h3v6h4v-6h3l1-4h-4V8z"/>',
        'tiktok'    => '<path d="M14 4v10.5a3.5 3.5 0 1 1-3.5-3.5"/><path d="M14 4c.4 2.6 2.2 4.3 5 4.5"/>',
        'x'         => '<path d="M4 4l16 16M20 4L4 20"/>',
        'phone'     => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/>',
        'mail'      => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
        'pin'       => '<path d="M12 21s7-6.2 7-11.5A7 7 0 0 0 5 9.5C5 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
        'truck'     => '<path d="M3 7h11v9H3zM14 10h4l3 3v3h-7"/><circle cx="7" cy="17.5" r="1.8"/><circle cx="17" cy="17.5" r="1.8"/>',
        'card'      => '<rect x="3" y="6" width="18" height="13" rx="2.5"/><path d="M3 10h18M16 15h2"/>',
        'shield'    => '<path d="M12 3l7 3v6c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6l7-3z"/><path d="M9 12l2 2 4-4"/>',
        'plus'      => '<path d="M12 5v14M5 12h14"/>',
        'minus'     => '<path d="M5 12h14"/>',
        'close'     => '<path d="M6 6l12 12M18 6L6 18"/>',
        'search'    => '<circle cx="11" cy="11" r="6.5"/><path d="M20 20l-4.2-4.2"/>',
        'check'     => '<path d="M5 12.5l4.5 4.5L19 7.5"/>',
        'chevron'   => '<path d="M6 9l6 6 6-6"/>',
        'left'      => '<path d="M15 18l-6-6 6-6"/>',
        'right'     => '<path d="M9 6l6 6-6 6"/>',
        'play'      => '<path d="M8 5.5v13l11-6.5z" fill="currentColor"/>',
        'clock'     => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
        'store'     => '<path d="M3 21V9l9-5 9 5v12"/><path d="M9 21v-7h6v7"/>',
        'tag'       => '<path d="M3 12V4h8l10 10-8 8L3 12z"/><circle cx="7.5" cy="8.5" r="1.5"/>',
        'sparkle'   => '<path d="M12 3l1.8 4.7L18.5 9.5l-4.7 1.8L12 16l-1.8-4.7L5.5 9.5l4.7-1.8z"/>',
        'grid'      => '<rect x="4" y="4" width="7" height="7" rx="1.5"/><rect x="13" y="4" width="7" height="7" rx="1.5"/><rect x="4" y="13" width="7" height="7" rx="1.5"/><rect x="13" y="13" width="7" height="7" rx="1.5"/>',
        'tool'      => '<path d="M14.5 6.5a4 4 0 0 0 5 5L12 19a2.1 2.1 0 0 1-3-3l7.5-7.5a4 4 0 0 0-2-2z"/>',
        'share'     => '<path d="M12 15V3M7.5 7.5L12 3l4.5 4.5"/><path d="M5 12v7a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-7"/>',
        'image'     => '<rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="9" cy="9" r="2"/><path d="M21 15.5l-5-5L6 20.5"/>',
        'star'      => '<path d="M12 2.8l2.8 5.8 6.3.9-4.6 4.4 1.1 6.3L12 17.2l-5.6 3 1.1-6.3-4.6-4.4 6.3-.9z"/>',
        'calendar'  => '<rect x="3.5" y="5" width="17" height="15.5" rx="2.5"/><path d="M3.5 10h17M8 3v4M16 3v4"/>',
        'menu'      => '<path d="M4 7h16M4 12h16M4 17h10"/>',
        'pause'     => '<path d="M9 6v12M15 6v12"/>',
        'route'     => '<circle cx="6" cy="18" r="2.2"/><path d="M8.2 18H15a3 3 0 0 0 0-6H9a3 3 0 0 1 0-6h6.5"/><path d="M15 3.5L18 6l-3 2.5"/>',
        'swap'      => '<path d="M4 8h13l-3.5-3.5M20 16H7l3.5 3.5"/>',
        'turn'      => '<path d="M20 12a8 8 0 1 1-2.4-5.7"/><path d="M20 4v4h-4"/>',
        // The skin finder's skin types
        'drop'      => '<path d="M12 3.5s6 6.6 6 10.9a6 6 0 0 1-12 0C6 10.1 12 3.5 12 3.5z"/>',
        'drop-half' => '<path d="M12 3.5s6 6.6 6 10.9a6 6 0 0 1-12 0C6 10.1 12 3.5 12 3.5z"/><path d="M12 3.5v16.9" /><path d="M12 9.5h3.2M12 13h5.6M12 16.5h5" stroke-width="1.4"/>',
        'cracked'   => '<circle cx="12" cy="12" r="8.5"/><path d="M12.5 3.6l-2 4.4 3.2 2.4-2.9 3.6 1.8 2.6-1 3.8M13.7 10.4l4.6-1.6M10.8 14l-4.9.6" stroke-width="1.5"/>',
        'leaf'      => '<path d="M5 19c0-8 5-13 14-14 0 9-5 14-13 14"/><path d="M5 19c3-4 6-6.5 9.5-8"/>',
        'spots'     => '<circle cx="12" cy="12" r="8.5"/><circle cx="9" cy="10" r="1.3" fill="currentColor"/><circle cx="14.5" cy="9" r="0.9" fill="currentColor"/><circle cx="13.5" cy="14.5" r="1.6" fill="currentColor"/>',
        'sun'       => '<circle cx="12" cy="12" r="4"/><path d="M12 3v2M12 19v2M3 12h2M19 12h2M5.6 5.6l1.4 1.4M17 17l1.4 1.4M5.6 18.4L7 17M17 7l1.4-1.4"/>',
        'flame'     => '<path d="M12 21a6 6 0 0 0 6-6c0-4-3-6-4-10-2 2-3 4-3 6-1-1-2-2-2-3.5C7 10 6 12.5 6 15a6 6 0 0 0 6 6z"/>',
    ];
    return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"'
         . ($class !== '' ? ' class="' . e($class) . '"' : '') . '>' . ($paths[$name] ?? '') . '</svg>';
}

/**
 * The address customers see at the top: the first line of the business
 * address, kept short.
 */
function sf_short_address(array $business): string {
    $first = trim(strtok(str_replace(["\r\n", "\r"], "\n", (string)($business['address'] ?? '')), "\n") ?: '');
    return mb_strlen($first) > 60 ? rtrim(mb_substr($first, 0, 58)) . '…' : $first;
}

/* ──────────────────────────────────────────
 *  RENDER
 * ────────────────────────────────────────── */

/**
 * Draw a shop page. $mode is 'live' for shoppers, 'preview' for Shop Studio
 * and 'look' for the full-size previews on the setup screen. Previews can't
 * place orders and show empty sections as gaps to fill.
 *
 * $page says which page: ['kind' => 'shop'] (the home page, the default),
 *   ['kind' => 'catalogue']                                everything it sells (/shop)
 *   ['kind' => 'contact']                                  the contact page (/contact)
 *   ['kind' => 'news', 'posts' => …]                       its news (/news)
 * or on live shops
 *   ['kind' => 'product', 'item' => …]                     a product's page
 *   ['kind' => 'order', 'order' => …]                      a tracking page
 *   ['kind' => 'review', 'review' => …, 'error', 'comment'] the review form
 * Pass $catalogue when the caller already has it.
 */
function storefront_render(array $business, array $settings, string $mode = 'live', string $visibility = 'live', array $page = [], ?array $catalogue = null): void {
    $page     += ['kind' => 'shop'];
    $theme     = storefront_resolve($settings, $business);
    $catalogue = $catalogue ?? storefront_catalogue($business, $settings);
    require BASE_PATH . '/views/storefront/shell.php';
}

/**
 * The address of one of the shop's pages ('home', 'catalogue', 'contact',
 * 'news'), for links between them: the live shop's own paths, Shop Studio's
 * preview of the draft, or nothing to follow in the setup screen's look
 * previews. shell.php sets the mode for each page it draws.
 */
function sf_page_href(string $page, string $hash = ''): string {
    $mode = sf_render_mode();
    if ($mode === 'live') {
        $path = ['catalogue' => '/shop', 'contact' => '/contact', 'news' => '/news'][$page] ?? '/';
    } elseif ($mode === 'preview') {
        $path = APP_URL . '/landing-page/preview' . ($page !== 'home' ? '?page=' . $page : '');
    } else {
        return $hash !== '' ? $hash : '#';
    }
    return $path . $hash;
}

/** The mode of the page being drawn: 'live', 'preview' or 'look'. */
function sf_render_mode(?string $set = null): string {
    static $mode = 'live';
    if ($set !== null) {
        $mode = $set;
    }
    return $mode;
}

/* ──────────────────────────────────────────
 *  IMAGE COPIES — /uploads/cache/{width}/{path}.webp
 *                 /uploads/cache/og/{path}.jpg (link previews)
 *                 /uploads/cache/icon/{size}/{path}.png (shop favicons)
 * ────────────────────────────────────────── */

/**
 * Make (once) and send a WebP copy of an uploaded image, or the JPEG copy
 * for link previews. Afterwards the file is on disk and the web server sends
 * it without PHP. Only photos shown on shops are copied: product photos,
 * hero media, logos and Shop Studio uploads. Anything else is a 404.
 */
function storefront_serve_variant(string $url_path): void {
    if (preg_match('#^uploads/cache/icon/(' . implode('|', STOREFRONT_ICON_SIZES) . ')/(logos/\d+/[A-Za-z0-9_-][A-Za-z0-9_.-]*\.(?:png|jpe?g|webp))\.png$#i', $url_path, $m)
        && !str_contains($url_path, '..') && is_file(BASE_PATH . '/uploads/' . $m[2])) {
        storefront_serve_icon(BASE_PATH . '/uploads/' . $m[2], BASE_PATH . '/' . $url_path, $url_path, (int)$m[1]);
    }
    if (preg_match('#^uploads/cache/(og|mail)/(' . STOREFRONT_VARIANT_PATH . ')\.jpg$#i', $url_path, $m)) {
        $size = strtolower($m[1]);
        $rel  = $m[2];
    } elseif (preg_match('#^uploads/cache/(320|640|960|1280|full)/(' . STOREFRONT_VARIANT_PATH . ')\.webp$#i', $url_path, $m)) {
        $size = $m[1];
        $rel  = $m[2];
    } else {
        http_response_code(404);
        exit;
    }
    if (str_contains($url_path, '..')) {
        http_response_code(404);
        exit;
    }
    $source = BASE_PATH . '/uploads/' . $rel;
    $target = BASE_PATH . '/' . $url_path;
    if (!is_file($source) || !function_exists('imagewebp')) {
        http_response_code(404);
        exit;
    }
    if ($size === 'og') {
        storefront_serve_og($source, $target, $url_path);
    }
    if ($size === 'mail') {
        storefront_serve_og($source, $target, $url_path, STOREFRONT_MAIL_WIDTH, STOREFRONT_MAIL_MAX_BYTES);
    }

    if (!is_file($target)) {
        $info = @getimagesize($source);
        $src = match ($info['mime'] ?? '') {
            'image/png'  => @imagecreatefrompng($source),
            'image/jpeg' => @imagecreatefromjpeg($source),
            'image/webp' => @imagecreatefromwebp($source),
            default      => false,
        };
        if (!$src) {
            http_response_code(404);
            exit;
        }
        $w = imagesx($src);
        $h = imagesy($src);
        $width = $size === 'full' ? $w : min($w, (int)$size);
        if ($width !== $w) {
            $height = max(1, (int)round($h * $width / $w));
            $dst = imagecreatetruecolor($width, $height);
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $width, $height, $w, $h);
            imagedestroy($src);
            $src = $dst;
        } else {
            imagepalettetotruecolor($src);
            imagealphablending($src, false);
            imagesavealpha($src, true);
        }
        if (!is_dir(dirname($target))) {
            @mkdir(dirname($target), 0755, true);
        }
        // Write to a temporary name first, so a half-written file is never served
        $tmp = $target . '.' . bin2hex(random_bytes(4)) . '.tmp';
        $ok  = @imagewebp($src, $tmp, 80);
        imagedestroy($src);
        if (!$ok || !@rename($tmp, $target)) {
            @unlink($tmp);
            error_log('[Nfelia] WebP copy failed: ' . $url_path);
            http_response_code(404);
            exit;
        }
    }

    header('Content-Type: image/webp');
    header('Content-Length: ' . filesize($target));
    header('Cache-Control: public, max-age=31536000');
    readfile($target);
    exit;
}

/**
 * Make (once) and send a square PNG icon of a shop's logo: the whole logo
 * centred with a little room around it, so a wide logo is shrunk, never
 * squashed or cropped. The tab icon keeps the logo's see-through parts; the
 * home-screen one sits on white, as iPhones fill see-through parts black.
 */
function storefront_serve_icon(string $source, string $target, string $url_path, int $size): void {
    if (!is_file($target)) {
        $info = @getimagesize($source);
        $src = match ($info['mime'] ?? '') {
            'image/png'  => @imagecreatefrompng($source),
            'image/jpeg' => @imagecreatefromjpeg($source),
            'image/webp' => @imagecreatefromwebp($source),
            default      => false,
        };
        if (!$src) {
            http_response_code(404);
            exit;
        }
        $w = imagesx($src);
        $h = imagesy($src);
        $pad   = $size >= 64 ? (int)round($size * 0.1) : 1;
        $scale = ($size - 2 * $pad) / max($w, $h);
        $dw = max(1, (int)round($w * $scale));
        $dh = max(1, (int)round($h * $scale));

        $dst = imagecreatetruecolor($size, $size);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $ground = $size >= 64
            ? imagecolorallocate($dst, 255, 255, 255)
            : imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefill($dst, 0, 0, $ground);
        imagealphablending($dst, true);
        imagecopyresampled($dst, $src, intdiv($size - $dw, 2), intdiv($size - $dh, 2), 0, 0, $dw, $dh, $w, $h);
        imagedestroy($src);

        if (!is_dir(dirname($target))) {
            @mkdir(dirname($target), 0755, true);
        }
        $tmp = $target . '.' . bin2hex(random_bytes(4)) . '.tmp';
        $ok  = @imagepng($dst, $tmp, 9);
        imagedestroy($dst);
        if (!$ok || !@rename($tmp, $target)) {
            @unlink($tmp);
            error_log('[Nfelia] Shop icon failed: ' . $url_path);
            http_response_code(404);
            exit;
        }
    }

    header('Content-Type: image/png');
    header('Content-Length: ' . filesize($target));
    header('Cache-Control: public, max-age=31536000');
    readfile($target);
    exit;
}

/**
 * Make (once) a JPEG copy of a photo at $target: at most $max_width wide,
 * see-through parts on white, squeezed until it is under $max_bytes. The
 * link-preview and email copies, and the photos in deed PDFs. False when the
 * photo can't be read or the copy can't be written.
 */
function storefront_make_jpeg(string $source, string $target, int $max_width, int $max_bytes): bool {
    if (!is_file($target)) {
        $info = @getimagesize($source);
        $src = match ($info['mime'] ?? '') {
            'image/png'  => @imagecreatefrompng($source),
            'image/jpeg' => @imagecreatefromjpeg($source),
            'image/webp' => @imagecreatefromwebp($source),
            default      => false,
        };
        if (!$src) {
            return false;
        }
        $w = imagesx($src);
        $h = imagesy($src);
        $width  = min($max_width, $w);
        $height = max(1, (int)round($h * $width / $w));
        $dst = imagecreatetruecolor($width, $height);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        imagealphablending($dst, true);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $width, $height, $w, $h);
        imagedestroy($src);
        imageinterlace($dst, true);

        if (!is_dir(dirname($target))) {
            @mkdir(dirname($target), 0755, true);
        }
        $tmp = $target . '.' . bin2hex(random_bytes(4)) . '.tmp';
        $ok  = false;
        foreach ([82, 68, 55, 42] as $quality) {
            $ok = @imagejpeg($dst, $tmp, $quality);
            clearstatcache(true, $tmp);
            if (!$ok || filesize($tmp) <= $max_bytes) {
                break;
            }
        }
        imagedestroy($dst);
        if (!$ok || !@rename($tmp, $target)) {
            @unlink($tmp);
            return false;
        }
    }
    return true;
}

/**
 * Make (once) and send the JPEG copy of a photo for link previews (and the
 * small one for emails, STOREFRONT_MAIL_WIDTH): at most STOREFRONT_OG_WIDTH wide, see-through parts on white, and squeezed until it
 * is under STOREFRONT_OG_MAX_BYTES (the size never changes, so the width and
 * height on product pages stay true).
 */
function storefront_serve_og(string $source, string $target, string $url_path, int $max_width = STOREFRONT_OG_WIDTH, int $max_bytes = STOREFRONT_OG_MAX_BYTES): void {
    if (!storefront_make_jpeg($source, $target, $max_width, $max_bytes)) {
        error_log('[Nfelia] Link preview copy failed: ' . $url_path);
        http_response_code(404);
        exit;
    }

    header('Content-Type: image/jpeg');
    header('Content-Length: ' . filesize($target));
    header('Cache-Control: public, max-age=31536000');
    readfile($target);
    exit;
}
