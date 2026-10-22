<?php
/**
 * Nfelia — A business's brand: its fonts and colours
 *
 * Owners set them once in Settings › Business › Branding:
 *
 *   brand_font_head   the primary font, for headlines (a key of brand_font_library())
 *   brand_font_body   the secondary font, for everything people read
 *   primary_color     the main brand colour (buttons, links, highlights)
 *   secondary_color   the second brand colour (usually the dark one)
 *
 * They lead everywhere the business faces its customers: the shop (unless the
 * owner picks something else for it in Shop Studio) and the invoice, receipt,
 * quotation and delivery links customers open. PDFs keep their print fonts.
 *
 * A business that never chose fonts has no brand fonts (both columns NULL),
 * and one whose primary colour is still the column's default (#1A1A2E, which
 * nobody chose; see THEME_UNSET_COLOUR) has no brand colours. Its shop then
 * keeps the look's own fonts and colours, as before.
 */

/**
 * Every font owners can pick. All are on Google Fonts and free to use.
 *   name      the family's name on Google Fonts, exactly
 *   kind      how the picker groups it
 *   head      the weight headlines use, or null when it is not offered for headlines
 *   body      offered for text
 *   weights   the weights a fixed-weight family has, or
 *   range     [lightest, boldest] for a variable family, which has every weight between
 *   axes      a Google Fonts axis spec to use instead of plain weights ({w} is the weight)
 *   scale     headline size, so every font fills about the same space
 *   fallback  what shows until the font arrives, and where it can't
 */
function brand_font_library(): array {
    static $fonts = null;
    if ($fonts !== null) {
        return $fonts;
    }
    $serif = "Georgia, 'Times New Roman', serif";
    $sans  = "system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif";
    $narrow = "'Arial Narrow', 'Roboto Condensed', sans-serif";
    $heavy = "'Arial Black', system-ui, sans-serif";
    $fonts = [
        // Serif
        'playfair'        => ['name' => 'Playfair Display',    'kind' => 'Serif',     'head' => 600, 'body' => false, 'range' => [400, 900], 'scale' => 0.95, 'fallback' => $serif],
        'eb-garamond'     => ['name' => 'EB Garamond',         'kind' => 'Serif',     'head' => 500, 'body' => true,  'range' => [400, 800], 'scale' => 1.05, 'fallback' => "Garamond, $serif"],
        'cormorant'       => ['name' => 'Cormorant Garamond',  'kind' => 'Serif',     'head' => 600, 'body' => false, 'weights' => [300, 400, 500, 600, 700], 'scale' => 1.12, 'fallback' => "Garamond, $serif"],
        'bodoni-moda'     => ['name' => 'Bodoni Moda',         'kind' => 'Serif',     'head' => 500, 'body' => false, 'range' => [400, 900], 'axes' => 'opsz,wght@6..96,{w}', 'scale' => 1.00, 'fallback' => "Didot, 'Bodoni 72', $serif"],
        'dm-serif'        => ['name' => 'DM Serif Display',    'kind' => 'Serif',     'head' => 400, 'body' => false, 'weights' => [400], 'scale' => 0.95, 'fallback' => $serif],
        'fraunces'        => ['name' => 'Fraunces',            'kind' => 'Serif',     'head' => 600, 'body' => false, 'range' => [100, 900], 'scale' => 0.92, 'fallback' => $serif],
        'prata'           => ['name' => 'Prata',               'kind' => 'Serif',     'head' => 400, 'body' => false, 'weights' => [400], 'scale' => 0.88, 'fallback' => $serif],
        'cinzel'          => ['name' => 'Cinzel',              'kind' => 'Serif',     'head' => 600, 'body' => false, 'range' => [400, 900], 'scale' => 0.80, 'fallback' => "Trajan Pro, $serif"],
        'marcellus'       => ['name' => 'Marcellus',           'kind' => 'Serif',     'head' => 400, 'body' => false, 'weights' => [400], 'scale' => 0.90, 'fallback' => $serif],
        'italiana'        => ['name' => 'Italiana',            'kind' => 'Serif',     'head' => 400, 'body' => false, 'weights' => [400], 'scale' => 1.00, 'fallback' => $serif],
        'abril'           => ['name' => 'Abril Fatface',       'kind' => 'Serif',     'head' => 400, 'body' => false, 'weights' => [400], 'scale' => 0.88, 'fallback' => $serif],
        'gloock'          => ['name' => 'Gloock',              'kind' => 'Serif',     'head' => 400, 'body' => false, 'weights' => [400], 'scale' => 0.95, 'fallback' => $serif],
        'young-serif'     => ['name' => 'Young Serif',         'kind' => 'Serif',     'head' => 400, 'body' => false, 'weights' => [400], 'scale' => 0.84, 'fallback' => $serif],
        'lora'            => ['name' => 'Lora',                'kind' => 'Serif',     'head' => 600, 'body' => true,  'range' => [400, 700], 'scale' => 0.92, 'fallback' => $serif],
        'source-serif'    => ['name' => 'Source Serif 4',      'kind' => 'Serif',     'head' => 600, 'body' => true,  'range' => [200, 900], 'scale' => 0.92, 'fallback' => $serif],
        'crimson-pro'     => ['name' => 'Crimson Pro',         'kind' => 'Serif',     'head' => 600, 'body' => true,  'range' => [200, 900], 'scale' => 1.05, 'fallback' => $serif],
        'libre-baskerville' => ['name' => 'Libre Baskerville', 'kind' => 'Serif',     'head' => 700, 'body' => true,  'weights' => [400, 700], 'scale' => 0.86, 'fallback' => "Baskerville, $serif"],

        // Sans serif
        'inter'           => ['name' => 'Inter',               'kind' => 'Sans serif', 'head' => 700, 'body' => true, 'range' => [100, 900], 'scale' => 0.86, 'fallback' => $sans],
        'dm-sans'         => ['name' => 'DM Sans',             'kind' => 'Sans serif', 'head' => 700, 'body' => true, 'range' => [100, 1000], 'scale' => 0.88, 'fallback' => $sans],
        'questrial'       => ['name' => 'Questrial',           'kind' => 'Sans serif', 'head' => 400, 'body' => true, 'weights' => [400], 'scale' => 0.86, 'fallback' => $sans],
        'outfit'          => ['name' => 'Outfit',              'kind' => 'Sans serif', 'head' => 600, 'body' => true, 'range' => [100, 900], 'scale' => 0.90, 'fallback' => $sans],
        'roboto'          => ['name' => 'Roboto',              'kind' => 'Sans serif', 'head' => 700, 'body' => true, 'range' => [100, 900], 'scale' => 0.86, 'fallback' => $sans],
        'lato'            => ['name' => 'Lato',                'kind' => 'Sans serif', 'head' => 700, 'body' => true, 'weights' => [100, 300, 400, 700, 900], 'scale' => 0.88, 'fallback' => $sans],
        'jost'            => ['name' => 'Jost',                'kind' => 'Sans serif', 'head' => 500, 'body' => true, 'range' => [100, 900], 'scale' => 0.92, 'fallback' => "'Avenir Next', $sans"],
        'figtree'         => ['name' => 'Figtree',             'kind' => 'Sans serif', 'head' => 700, 'body' => true, 'range' => [300, 900], 'scale' => 0.88, 'fallback' => $sans],
        'mulish'          => ['name' => 'Mulish',              'kind' => 'Sans serif', 'head' => 800, 'body' => true, 'range' => [200, 1000], 'scale' => 0.88, 'fallback' => $sans],
        'montserrat'      => ['name' => 'Montserrat',          'kind' => 'Sans serif', 'head' => 700, 'body' => true, 'range' => [100, 900], 'scale' => 0.82, 'fallback' => $sans],
        'poppins'         => ['name' => 'Poppins',             'kind' => 'Sans serif', 'head' => 600, 'body' => true, 'weights' => [100, 200, 300, 400, 500, 600, 700, 800, 900], 'scale' => 0.84, 'fallback' => $sans],
        'plus-jakarta'    => ['name' => 'Plus Jakarta Sans',   'kind' => 'Sans serif', 'head' => 700, 'body' => true, 'range' => [200, 800], 'scale' => 0.88, 'fallback' => $sans],
        'manrope'         => ['name' => 'Manrope',             'kind' => 'Sans serif', 'head' => 700, 'body' => true, 'range' => [200, 800], 'scale' => 0.88, 'fallback' => $sans],
        'nunito-sans'     => ['name' => 'Nunito Sans',         'kind' => 'Sans serif', 'head' => 800, 'body' => true, 'range' => [200, 1000], 'scale' => 0.88, 'fallback' => $sans],
        'open-sans'       => ['name' => 'Open Sans',           'kind' => 'Sans serif', 'head' => 700, 'body' => true, 'range' => [300, 800], 'scale' => 0.86, 'fallback' => $sans],
        'source-sans'     => ['name' => 'Source Sans 3',       'kind' => 'Sans serif', 'head' => 700, 'body' => true, 'range' => [200, 900], 'scale' => 0.92, 'fallback' => $sans],
        'ibm-plex-sans'   => ['name' => 'IBM Plex Sans',       'kind' => 'Sans serif', 'head' => 600, 'body' => true, 'weights' => [100, 200, 300, 400, 500, 600, 700], 'scale' => 0.88, 'fallback' => $sans],
        'karla'           => ['name' => 'Karla',               'kind' => 'Sans serif', 'head' => 700, 'body' => true, 'range' => [200, 800], 'scale' => 0.90, 'fallback' => $sans],
        'work-sans'       => ['name' => 'Work Sans',           'kind' => 'Sans serif', 'head' => 600, 'body' => true, 'range' => [100, 900], 'scale' => 0.88, 'fallback' => $sans],
        'hanken'          => ['name' => 'Hanken Grotesk',      'kind' => 'Sans serif', 'head' => 700, 'body' => true, 'range' => [100, 900], 'scale' => 0.88, 'fallback' => $sans],
        'albert-sans'     => ['name' => 'Albert Sans',         'kind' => 'Sans serif', 'head' => 700, 'body' => true, 'range' => [100, 900], 'scale' => 0.88, 'fallback' => $sans],
        'instrument-sans' => ['name' => 'Instrument Sans',     'kind' => 'Sans serif', 'head' => 600, 'body' => true, 'range' => [400, 700], 'scale' => 0.88, 'fallback' => $sans],
        'rethink-sans'    => ['name' => 'Rethink Sans',        'kind' => 'Sans serif', 'head' => 700, 'body' => true, 'range' => [400, 800], 'scale' => 0.88, 'fallback' => $sans],
        'barlow'          => ['name' => 'Barlow',              'kind' => 'Sans serif', 'head' => 700, 'body' => true, 'weights' => [100, 200, 300, 400, 500, 600, 700, 800, 900], 'scale' => 0.88, 'fallback' => $sans],
        'archivo'         => ['name' => 'Archivo',             'kind' => 'Sans serif', 'head' => 800, 'body' => true, 'range' => [100, 900], 'scale' => 0.86, 'fallback' => $sans],
        'space-grotesk'   => ['name' => 'Space Grotesk',       'kind' => 'Sans serif', 'head' => 600, 'body' => false, 'range' => [300, 700], 'scale' => 0.88, 'fallback' => $sans],
        'bricolage'       => ['name' => 'Bricolage Grotesque', 'kind' => 'Sans serif', 'head' => 700, 'body' => false, 'range' => [200, 800], 'scale' => 0.88, 'fallback' => $sans],
        'syne'            => ['name' => 'Syne',                'kind' => 'Sans serif', 'head' => 700, 'body' => false, 'range' => [400, 800], 'scale' => 0.86, 'fallback' => $sans],
        'familjen'        => ['name' => 'Familjen Grotesk',    'kind' => 'Sans serif', 'head' => 700, 'body' => false, 'range' => [400, 700], 'scale' => 0.80, 'fallback' => $sans],
        'gabarito'        => ['name' => 'Gabarito',            'kind' => 'Sans serif', 'head' => 800, 'body' => false, 'range' => [400, 900], 'scale' => 0.95, 'fallback' => $sans],

        // Display: tall, heavy or wide letters for headlines only
        'unbounded'       => ['name' => 'Unbounded',           'kind' => 'Display',   'head' => 600, 'body' => false, 'range' => [200, 900], 'scale' => 0.66, 'fallback' => $heavy],
        'barlow-condensed' => ['name' => 'Barlow Condensed',   'kind' => 'Display',   'head' => 700, 'body' => false, 'weights' => [100, 200, 300, 400, 500, 600, 700, 800, 900], 'scale' => 0.96, 'fallback' => $narrow],
        'oswald'          => ['name' => 'Oswald',              'kind' => 'Display',   'head' => 600, 'body' => false, 'range' => [200, 700], 'scale' => 0.94, 'fallback' => $narrow],
        'bebas'           => ['name' => 'Bebas Neue',          'kind' => 'Display',   'head' => 400, 'body' => false, 'weights' => [400], 'scale' => 1.10, 'fallback' => "Impact, $narrow"],
        'anton'           => ['name' => 'Anton',               'kind' => 'Display',   'head' => 400, 'body' => false, 'weights' => [400], 'scale' => 0.92, 'fallback' => "Impact, $narrow"],
        'big-shoulders'   => ['name' => 'Big Shoulders Display', 'kind' => 'Display', 'head' => 800, 'body' => false, 'range' => [100, 900], 'scale' => 1.08, 'fallback' => "Impact, $narrow"],
        'agbalumo'        => ['name' => 'Agbalumo',            'kind' => 'Display',   'head' => 400, 'body' => false, 'weights' => [400], 'scale' => 0.90, 'fallback' => "'Cooper Black', Georgia, serif"],

        // Script and monospace
        'great-vibes'     => ['name' => 'Great Vibes',         'kind' => 'Script',    'head' => 400, 'body' => false, 'weights' => [400], 'scale' => 1.22, 'fallback' => "'Brush Script MT', cursive"],
        'roboto-mono'     => ['name' => 'Roboto Mono',         'kind' => 'Monospace', 'head' => 600, 'body' => false, 'range' => [100, 700], 'scale' => 0.78, 'fallback' => "ui-monospace, Menlo, monospace"],
    ];
    return $fonts;
}

/**
 * The five pairs Settings offered before, as brand fonts. Businesses still on
 * the first one (Inter + DM Sans, the default) never chose it.
 */
const BRAND_FONT_PAIRS = [
    'modern_sans'     => ['inter', 'dm-sans'],
    'corporate_serif' => ['eb-garamond', 'questrial'],
    'bold_display'    => ['syne', 'outfit'],
    'classic_tech'    => ['roboto-mono', 'roboto'],
    'elegant_serif'   => ['playfair', 'lato'],
];

/** Fonts offered for headlines (head) or for text (body), in picker order. */
function brand_fonts_for(string $role): array {
    return array_filter(brand_font_library(), fn($f) => $role === 'head' ? $f['head'] !== null : $f['body']);
}

/** A font's CSS stack: its own name first, then what stands in for it. */
function brand_font_stack(array $font): string {
    return "'" . $font['name'] . "', " . $font['fallback'];
}

/** Does the family have this weight? A variable family has every weight in its range. */
function brand_font_has(array $font, int $weight): bool {
    return isset($font['range'])
        ? $weight >= $font['range'][0] && $weight <= $font['range'][1]
        : in_array($weight, $font['weights'], true);
}

/** The weights a role needs: the headline weight, or 400 to 700 for text, where the family has them. */
function brand_font_weights(array $font, string $role): array {
    $want = $role === 'head' ? [(int)$font['head']] : array_values(array_filter([400, 500, 600, 700], fn($w) => brand_font_has($font, $w)));
    return $want ?: [400];
}

/**
 * One family's part of a Google Fonts request, for these weights. A family
 * with one weight is asked for by name alone.
 */
function brand_font_query(array $font, array $weights): string {
    $family = str_replace(' ', '+', $font['name']);
    sort($weights);
    $weights = array_values(array_unique($weights));
    if (($font['weights'] ?? null) === [400]) {
        return $family;
    }
    if (isset($font['axes'])) {
        return $family . ':' . substr($font['axes'], 0, strpos($font['axes'], '@') + 1)
             . implode(';', array_map(fn($w) => str_replace('{w}', (string)$w, substr($font['axes'], strpos($font['axes'], '@') + 1)), $weights));
    }
    return $family . ':wght@' . implode(';', $weights);
}

/**
 * The stylesheet for a headline font and a text font. The same family in
 * both roles is asked for once, with every weight either needs.
 */
function brand_fonts_url(string $head_key, string $body_key): string {
    $lib  = brand_font_library();
    $head = $lib[$head_key] ?? $lib['inter'];
    $body = $lib[$body_key] ?? $lib['dm-sans'];
    if ($head['name'] === $body['name']) {
        return 'https://fonts.googleapis.com/css2?family='
             . brand_font_query($body, array_merge(brand_font_weights($head, 'head'), brand_font_weights($body, 'body'))) . '&display=swap';
    }
    return 'https://fonts.googleapis.com/css2?family=' . brand_font_query($head, brand_font_weights($head, 'head'))
         . '&family=' . brand_font_query($body, brand_font_weights($body, 'body')) . '&display=swap';
}

/**
 * One stylesheet with every font in the library, cut down to the letters of
 * their names: what the Settings pickers need to show each name in its own
 * font. A few kilobytes per font, fetched the first time a picker opens.
 */
function brand_font_samples_url(): string {
    $families = [];
    $letters  = 'Aa';
    foreach (brand_font_library() as $font) {
        $families[$font['name']] = brand_font_query($font, [$font['head'] ?? 400]);
        $letters .= $font['name'];
    }
    $chars = implode('', array_unique(mb_str_split($letters)));
    return 'https://fonts.googleapis.com/css2?family=' . implode('&family=', $families)
         . '&text=' . rawurlencode($chars) . '&display=swap';
}

/**
 * The brand fonts a business chose, or null when it never chose any. Both
 * keys are always set: one picked alone gets a partner from its own pair.
 *
 * @return array{head:string, body:string}|null
 */
function brand_fonts(array $business): ?array {
    $lib  = brand_font_library();
    $head = (string)($business['brand_font_head'] ?? '');
    $body = (string)($business['brand_font_body'] ?? '');
    $head = isset($lib[$head]) && $lib[$head]['head'] !== null ? $head : '';
    $body = isset($lib[$body]) && $lib[$body]['body'] ? $body : '';
    if ($head === '' && $body === '') {
        return null;
    }
    return ['head' => $head !== '' ? $head : 'inter', 'body' => $body !== '' ? $body : ($lib[$head]['body'] ? $head : 'dm-sans')];
}

/**
 * The fonts for the pages customers open from an invoice, receipt,
 * quotation or delivery link: the brand fonts, else the pair the business
 * picked in the old Settings list (Inter + DM Sans when none).
 *
 * @return array{head:string, body:string, head_stack:string, body_stack:string, head_weight:int, url:string}
 */
function brand_doc_fonts(array $business): array {
    $keys = brand_fonts($business) ?? ['head' => BRAND_FONT_PAIRS[$business['font_pairing'] ?? ''][0] ?? 'inter',
                                        'body' => BRAND_FONT_PAIRS[$business['font_pairing'] ?? ''][1] ?? 'dm-sans'];
    $lib  = brand_font_library();
    $head = $lib[$keys['head']];
    $body = $lib[$keys['body']];
    return [
        'head'        => $head['name'],
        'body'        => $body['name'],
        'head_stack'  => brand_font_stack($head),
        'body_stack'  => brand_font_stack($body),
        'head_weight' => (int)$head['head'],
        'url'         => brand_fonts_url($keys['head'], $keys['body']),
    ];
}

/**
 * The brand fonts as a font pair the shop renderer reads (see
 * storefront_fonts()), or null when the business has none.
 */
function brand_font_pair(array $business): ?array {
    $keys = brand_fonts($business);
    if ($keys === null) {
        return null;
    }
    $lib  = brand_font_library();
    $head = $lib[$keys['head']];
    $body = $lib[$keys['body']];
    return [
        'name'       => 'Your brand fonts',
        'head'       => $head['name'],
        'body'       => $body['name'],
        'head_stack' => brand_font_stack($head),
        'body_stack' => brand_font_stack($body),
        'weight'     => (int)$head['head'],
        'stretch'    => '100%',
        'scale'      => (float)$head['scale'],
        'url'        => brand_fonts_url($keys['head'], $keys['body']),
        'keys'       => $keys,
    ];
}

/* ──────────────────────────────────────────
 *  COLOURS
 * ────────────────────────────────────────── */

/**
 * The business's two brand colours, or null when it never chose them.
 *
 * @return array{primary:string, secondary:string}|null
 */
function brand_colours(array $business): ?array {
    $primary = sf_hex($business['primary_color'] ?? null);
    if ($primary === null || strtolower($primary) === THEME_UNSET_COLOUR) {
        return null;
    }
    return ['primary' => $primary, 'secondary' => sf_hex($business['secondary_color'] ?? null) ?? sf_mix($primary, '#000000', 0.8)];
}

/**
 * The colours of a business's documents: the invoice, receipt, quotation,
 * delivery and deed pages customers open, their PDFs, and the app's own view
 * of them. The brand colours when the business chose them; Nfelia's wine and
 * gold when it never did, as the app shows it.
 *
 *   primary, secondary  the colours themselves, for bars, borders and fills
 *   text                words in the primary: darkened until they read on
 *                       white, cream and a pale tint (4.5:1), as in the app
 *   secondary_text      the same for the secondary
 *   on                  words on a fill of the primary: white or ink,
 *                       whichever reads
 *
 * @return array{primary:string, secondary:string, text:string, secondary_text:string, on:string}
 */
function brand_doc_colours(array $business): array {
    $colours = brand_colours($business) ?? ['primary' => THEME_NFELIA, 'secondary' => '#B69F5E'];
    $theme   = theme_tokens($colours['primary'], $colours['secondary']);
    return $colours + [
        'text'           => $theme['accent_text'],
        'secondary_text' => theme_readable($colours['secondary'], ['#FFFFFF', THEME_CREAM]),
        'on'             => $theme['on_accent'],
    ];
}

/**
 * A full colour set for the shop from the two brand colours, light or dark.
 * The primary colour is the accent. The secondary becomes the text colour
 * when it is dark enough (or the ground, in the dark set); otherwise it is
 * the look's second colour, and text is a deep shade of the primary.
 * sf_palette() then checks every pair for contrast.
 */
function brand_colour_set(array $colours, string $mode): array {
    $primary   = $colours['primary'];
    $secondary = $colours['secondary'];
    $dark_secondary = sf_luminance($secondary) < 0.05;

    if ($mode === 'dark') {
        $ground = $dark_secondary ? $secondary : sf_mix($primary, '#000000', 0.86);
        return [
            'ground'  => $ground,
            'ink'     => sf_mix($primary, '#FFFFFF', 0.94),
            'accent'  => $primary,
            'muted'   => sf_mix(sf_mix($primary, '#FFFFFF', 0.94), $ground, 0.3),
            'surface' => sf_mix($ground, '#FFFFFF', 0.07),
            'line'    => sf_mix($ground, '#FFFFFF', 0.16),
            'motif'   => sf_mix($primary, $ground, 0.55),
            'band'    => $dark_secondary ? sf_mix($primary, '#FFFFFF', 0.25) : $secondary,
        ];
    }
    $ink = $dark_secondary ? $secondary : sf_mix($primary, '#000000', 0.82);
    $ground = sf_mix($primary, '#FFFFFF', 0.955);
    return [
        'ground'  => $ground,
        'ink'     => $ink,
        'accent'  => $primary,
        'surface' => sf_mix($ground, $ink, 0.05),
        'card'    => '#FFFFFF',
        'line'    => sf_mix($ground, $ink, 0.13),
        'motif'   => sf_mix($primary, '#FFFFFF', 0.8),
        'band'    => $dark_secondary ? sf_mix($primary, $ground, 0.78) : $secondary,
    ];
}
