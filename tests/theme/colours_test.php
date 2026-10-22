<?php
/**
 * Every business sees its own colour, not Nfelia's (app/helpers/theme.php).
 * Text and arithmetic only, no database and no server, so it runs on its own:
 *   php tests/theme/colours_test.php
 *
 * Inside the app, in Shop Studio, on a shop's notices and in the emails a
 * business sends, colour comes from the business: the Tailwind colours
 * accent, on-accent, accent-text and accent-soft (tailwind.config.js), the
 * CSS variables of the same names, or an email's $biz_* colours. Nfelia's
 * wine (#8B004A, its tint #F8EEF3, its deep #5E0032) and gold (#B69F5E)
 * belong to Nfelia's own pages: signing in, the website, Ops and Nfelia's
 * own emails. Tasks & Calendar and Shop Studio kept Nfelia's wine long after
 * the rest of the app took the business's colour; this fails when a fixed
 * wine or gold comes back anywhere a business's colour should be.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$root  = dirname(__DIR__, 2);
$fails = 0;
require $root . '/app/helpers/theme.php';
require $root . '/app/helpers/planner.php';

function ok(bool $cond, string $what): void
{
    global $fails;
    if ($cond) {
        echo "  ok  {$what}\n";
        return;
    }
    $fails++;
    echo "FAIL  {$what}\n";
}

// Nfelia's own pages and emails, where wine and gold are Nfelia's to use.
const NFELIA_OWN = [
    'views/auth/', 'views/onboarding/', 'views/home/', 'views/ops/', 'views/directory/', 'views/verify/',
    'views/404.php', 'views/500.php', 'views/partials/nf_splash.php', 'assets/js/home.js',
    'views/emails/welcome.php', 'views/emails/verification.php', 'views/emails/signin_link.php',
    'views/emails/password_reset.php', 'views/emails/password_changed.php', 'views/emails/subscription_activated.php',
    'views/emails/price_notice.php', 'views/emails/ops_',
];

/** Each fixed wine or gold in a file, as "line: text", ignoring a fallback for a business with no colour (?? '#8B004A'). */
function fixed_colours(string $text): array
{
    $hex   = '#(?:8B004A|5E0032|F8EEF3|B69F5E)\b';
    $found = [];
    foreach (preg_split('/\R/', $text) as $i => $line) {
        $line = (string)preg_replace("/\?\?\s*'#[0-9A-Fa-f]{6}'/", '', $line);
        if (preg_match('/-brand-(?:burgundy|gold)\b/', $line)                                   // a Tailwind class
            || preg_match('/-\[' . $hex . '\]/i', $line)                                       // a Tailwind value
            || preg_match('/(?<![\w-])(?:color|background(?:-color)?|border(?:-[a-z]+)*|outline(?:-color)?|fill|stroke|accent-color|box-shadow)\s*:[^;"{}]*' . $hex . '/i', $line)) {
            $found[] = ($i + 1) . ': ' . trim(mb_substr($line, 0, 140));
        }
    }
    return $found;
}

$files = [];
foreach (['views', 'assets/js', 'assets/css/src'] as $dir) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if (in_array($file->getExtension(), ['php', 'js', 'css'], true)) {
            $files[] = substr($file->getPathname(), strlen($root) + 1);
        }
    }
}
$files[] = 'assets/css/studio.css';
sort($files);

$checked = 0;
foreach ($files as $path) {
    foreach (NFELIA_OWN as $own) {
        if (str_starts_with($path, $own)) {
            continue 2;
        }
    }
    $checked++;
    foreach (fixed_colours((string)file_get_contents($root . '/' . $path)) as $hit) {
        ok(false, "{$path} takes the business's colour, not Nfelia's fixed wine or gold ({$hit})");
    }
}
ok($checked > 50, "the app's pages, scripts and styles were read ({$checked} files)");
ok(fixed_colours('<a class="text-brand-burgundy">') && fixed_colours('style="color:#B69F5E"') && fixed_colours('class="bg-[#F8EEF3]"')
   && !fixed_colours("color: <?= e(\$business['primary_color'] ?? '#8B004A') ?>;"),
   'and the check knows a fixed colour from a fallback');

// The app's frame and Shop Studio, which stands outside it, hand the
// business's colour to their stylesheets; the shop's notice page works it
// out from the colour it is given.
foreach (['views/layouts/app.php', 'views/studio/index.php'] as $page) {
    $html = (string)file_get_contents($root . '/' . $page);
    ok(preg_match('/--accent:\s*<\?=\s*\$\w+\[\'accent\'\]\s*\?>/', $html) && preg_match('/--accent-text:\s*<\?=\s*\$\w+\[\'accent_text\'\]\s*\?>/', $html),
       "{$page} sets --accent and --accent-text from the business's colour");
}
ok(str_contains((string)file_get_contents($root . '/views/public/notice.php'), "theme_tokens(\$notice['colour']"),
   'views/public/notice.php takes the colour a shop gives it');

// The app's Tailwind has no fixed wine or gold to reach for, and the built
// stylesheet has the business's colours the pages now use.
$config = (string)file_get_contents($root . '/tailwind.config.js');
ok(!preg_match('/^\s*(?:burgundy|gold)\s*:/m', $config), 'the app\'s Tailwind has no burgundy or gold colour');
$css = (string)file_get_contents($root . '/assets/css/app.css');
foreach (['.bg-accent{', '.text-on-accent{', '.text-accent-text{', '.bg-accent-soft{', '.focus\:border-accent-text:focus{', '.bg-\[var\(--pl-appointment\)\]{'] as $rule) {
    ok(str_contains($css, $rule), "assets/css/app.css is built with {$rule}..}");
}
ok(!str_contains($css, 'brand-burgundy') && !str_contains($css, 'brand-gold'), 'and nothing of brand-burgundy or brand-gold');

// The parts of a business's colour
$t = theme_tokens('#C08436', null);
ok($t['accent'] === '#C08436' && $t['on_accent'] === THEME_INK, 'a light gold gets ink, not white, on its buttons');
ok(theme_contrast(theme_hex_to_rgb($t['accent_text']), [255, 255, 255]) >= 4.5, 'and a text shade that reads on white');
ok($t['accent_text_rgb'] === implode(' ', theme_hex_to_rgb($t['accent_text'])), 'whose channels Tailwind reads as --accent-text-rgb');
ok(theme_tokens('#1A1A2E', null)['accent'] === THEME_NFELIA, 'a colour nobody chose is Nfelia\'s wine');

// Appointments on the calendar: the business's colour, kept apart from the other dots
ok(planner_appointment_colour(['primary_color' => THEME_NFELIA]) === THEME_NFELIA, 'wine appointments stay wine');
foreach (['#C08436', '#F4C430', '#B54708', '#00764A', '#026AA2', '#0F766E', '#DC2626', '#5B4650', '#9CA3AF', '#111111', '#7C3AED'] as $brand) {
    $dot  = planner_appointment_colour(['primary_color' => $brand]);
    $near = min(array_map(fn($o) => theme_distance(theme_hex_to_rgb($dot), theme_hex_to_rgb($o)), PLANNER_DOT_COLOURS));
    ok($near >= 0.12 && theme_contrast(theme_hex_to_rgb($dot), [255, 255, 255]) >= 4.5,
       sprintf('%s appointments are %s, %.2f from the nearest other dot and readable on white', $brand, $dot, $near));
}
$gold = planner_appointment_colour(['primary_color' => '#C08436']);
ok($gold !== theme_tokens('#C08436', null)['accent_text'], 'a gold that sits by the amber delivery dot is moved off it');
[, , $gold_h]  = theme_rgb_to_oklch(theme_hex_to_rgb($gold));
[, , $brand_h] = theme_rgb_to_oklch(theme_hex_to_rgb('#C08436'));
ok(abs(rad2deg($gold_h - $brand_h)) < 30, 'and stays close to the gold\'s own hue');

// Shop Studio's greys
foreach (['#FBF8F9', '#F1E8EC', '#E9DCE2', '#A8909C', '#8E7A85'] as $grey) {
    ok(theme_retint($grey, THEME_NFELIA) === $grey, "wine gets its own grey {$grey} back");
    [, $c] = theme_rgb_to_oklch(theme_hex_to_rgb(theme_retint($grey, '#777777')));
    ok($c < 0.005, "a grey brand gets a plain grey for {$grey}");
}

echo $fails ? "\n{$fails} failed\n" : "\nall passed\n";
exit($fails ? 1 : 0);
