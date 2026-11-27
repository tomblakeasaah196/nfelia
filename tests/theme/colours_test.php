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
 *
 * A business that never chose a colour still has the column's default
 * (#1A1A2E, a navy nobody picked) and gets Nfelia's colours, in the app and
 * on its documents alike. Its invoices, receipts and quotes went out navy
 * while the app showed it wine, because each document read the colour
 * itself; documents now ask brand_doc_colours().
 *
 * Words in a business's colour read (4.5:1) wherever they sit: on white, on
 * the cream background people can choose and on the pale tint behind a
 * selected chip, in the app and on documents. A light gold read on white
 * but not on cream, and documents wrote words in the colour as it is.
 *
 * Android launches the installed app on the manifest's background_color and
 * keeps it for its bottom bar, the same for every business: white, not
 * Nfelia's wine, with the opening fading in from it.
 *
 * And the pages no link check reaches: a missing page seen by someone signed
 * in, the installed app's "No connection" page, and the QR code that checks
 * a document is genuine all take the business's colour.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$root  = dirname(__DIR__, 2);
$fails = 0;
require $root . '/app/helpers/theme.php';
require $root . '/app/helpers/planner.php';
require $root . '/app/helpers/storefront.php';
require $root . '/app/helpers/brand.php';
require $root . '/app/helpers/qr.php';   // qr_inks() needs no QR library

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
    'views/500.php', 'views/partials/nf_splash.php', 'assets/js/home.js',
    'views/emails/welcome.php', 'views/emails/welcome_fr.php', 'views/emails/verification.php', 'views/emails/signin_link.php',
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

// No page reads a business's stored colour itself: documents ask
// brand_doc_colours() and the app theme_tokens(), which both know a colour
// nobody chose. Settings and onboarding show and save the stored value.
foreach ($files as $path) {
    if (!str_starts_with($path, 'views/') || str_starts_with($path, 'views/settings/') || str_starts_with($path, 'views/onboarding/')) {
        continue;
    }
    foreach (preg_split('/\R/', (string)file_get_contents($root . '/' . $path)) as $i => $line) {
        if (preg_match("/\['(?:primary|secondary)_color'\]/", $line) && !str_contains($line, 'theme_tokens(')) {
            ok(false, "{$path} line " . ($i + 1) . " asks brand_doc_colours() or theme_tokens() for the business's colour, not the stored value (" . trim(mb_substr($line, 0, 100)) . ')');
        }
    }
}

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
$grounds = ['white' => '#FFFFFF', 'cream' => THEME_CREAM];
foreach (['#C08436', '#F4C430', '#D4AF37', '#E0A526', '#FF7F50', '#F48FB1', '#4FC3F7', '#66BB6A', '#B39DDB', '#9CA3AF', '#00B3A4', '#8B004A', '#1F3A93', '#7C3AED'] as $brand) {
    $t    = theme_tokens($brand, null);
    $text = theme_hex_to_rgb($t['accent_text']);
    $low  = min(array_map(fn($g) => theme_contrast($text, theme_hex_to_rgb($g)), $grounds + ['the tint' => $t['accent_soft']]));
    [, , $h0] = theme_rgb_to_oklch(theme_hex_to_rgb($brand));
    [, , $h1] = theme_rgb_to_oklch($text);
    // Within 15 degrees: a screen can't show a deep yellow at full strength, so it leans a little to amber
    ok($low >= 4.5 && abs(rad2deg(atan2(sin($h1 - $h0), cos($h1 - $h0)))) < 15,
       sprintf('%s writes words in %s, %.2f:1 at worst on white, cream and its tint, in its own hue', $brand, $t['accent_text'], $low));
}
foreach (['#8B004A', '#1F3A93', '#7C3AED', '#00764A'] as $brand) {
    ok(theme_tokens($brand, null)['accent_text'] === $brand, "{$brand} already reads everywhere, so its words are the colour itself");
}
ok($t['accent_text_rgb'] === implode(' ', theme_hex_to_rgb($t['accent_text'])), 'whose channels Tailwind reads as --accent-text-rgb');
ok(theme_tokens('#1A1A2E', null)['accent'] === THEME_NFELIA, 'a colour nobody chose is Nfelia\'s wine');

// Documents: the brand colours, else Nfelia's
$unset = brand_doc_colours(['primary_color' => '#1A1A2E', 'secondary_color' => '#C27A62']);
ok($unset['primary'] === THEME_NFELIA && $unset['secondary'] === '#B69F5E' && $unset['text'] === THEME_NFELIA && $unset['on'] === '#FFFFFF',
   'a business that never chose a colour gets Nfelia\'s wine and gold on its documents, not navy');
$doc = brand_doc_colours(['primary_color' => '#c08436', 'secondary_color' => '#3B2414']);
ok($doc['primary'] === '#C08436' && $doc['secondary'] === '#3B2414', 'and one that chose gets its own');
foreach (['text' => $doc['text'], 'secondary_text' => $doc['secondary_text'], "the wine's secondary_text" => $unset['secondary_text']] as $what => $hex) {
    $low = min(array_map(fn($g) => theme_contrast(theme_hex_to_rgb($hex), theme_hex_to_rgb($g)), $grounds));
    ok($low >= 4.5, sprintf('a document\'s %s, %s, reads on white and cream (%.2f:1)', $what, $hex, $low));
}
ok($doc['text'] !== $doc['primary'] && $doc['on'] === THEME_INK, 'a light gold document writes its words in a deeper gold, and ink on its gold bars');
ok($doc['secondary_text'] === '#3B2414', 'a dark secondary is written as it is');

// No document writes words in the fill colour itself, or white on it: words
// take text (or secondary_text) and words on a fill take on. The public pages
// built with Tailwind say brand-text and on-brand.
$docs = ['views/invoices/public.php', 'views/receipts/public.php', 'views/quotations/public.php', 'views/deliveries/public.php',
         'views/deliveries/signoff.php', 'views/deeds/view.php', 'views/deeds/pdf_template.php', 'views/invoices/pdf_template.php',
         'views/receipts/pdf_template.php', 'views/quotations/pdf_template.php', 'views/invoices/view.php', 'views/receipts/view.php',
         'views/quotations/view.php', 'views/reports/profit_loss_pdf.php'];
foreach ($docs as $path) {
    foreach (preg_split('/\R/', (string)file_get_contents($root . '/' . $path)) as $i => $line) {
        $fill = '\$(?:primary|primary_color|secondary|accent|doc_colours\[\'(?:primary|secondary)\'\])';
        if (preg_match('/(?<![\w-])color:\s*<\?=\s*(?:e\()?' . $fill . '\)?\s*\?>/', $line)
            || preg_match('/(?<![\w:-])text-brand(?:-primary|-secondary)?(?![\w-])/', $line)
            || preg_match('/(?<![\w-])bg-brand(?:-primary)?(?![\w-])[^"]*\btext-white\b|\btext-white\b[^"]*(?<![\w-])bg-brand(?:-primary)?(?![\w-])/', $line)
            || preg_match('/background(?:-color)?:\s*<\?=\s*(?:e\()?' . $fill . '\)?\s*\?>;?\s*color:\s*#(?:fff|ffffff)\b/i', $line)) {
            ok(false, "{$path} line " . ($i + 1) . ' writes words in text, secondary_text or on, not the fill colour or white (' . trim(mb_substr($line, 0, 110)) . ')');
        }
    }
}
ok(count($docs) === 14, 'the documents were read (' . count($docs) . ' files)');
$public = (string)file_get_contents($root . '/assets/css/public.css');
foreach (['.text-brand-text{', '.text-on-brand{', '.focus\:border-brand-text:focus{'] as $rule) {
    ok(str_contains($public, $rule), "assets/css/public.css is built with {$rule}..}");
}
// A delivery note's status badge sits on the brand colour: without its pale
// background its blue or green words sat on the brand itself
preg_match_all("/'color'\s*=>\s*'([^']+)'/", (string)file_get_contents($root . '/app/controllers/DeliveryController.php'), $badges);
$missing = array_filter(array_unique(explode(' ', implode(' ', $badges[1]))), fn($class) => !str_contains($public, ".{$class}{"));
ok(count($badges[1]) >= 6 && !$missing, 'assets/css/public.css has every delivery status badge colour' . ($missing ? ' (missing ' . implode(', ', $missing) . ')' : ''));

// Shop Studio's links also sit on its stage, the darkest of its greys
ok(str_contains((string)file_get_contents($root . '/views/studio/index.php'), "theme_readable(\$chrome['accent_text']"),
   'views/studio/index.php deepens its link colour until it reads on its stage');

// Appointments on the calendar: the business's colour, kept apart from the other dots
ok(planner_appointment_colour(['primary_color' => THEME_NFELIA]) === THEME_NFELIA, 'wine appointments stay wine');
foreach (['#C08436', '#F4C430', '#B54708', '#00764A', '#026AA2', '#0F766E', '#DC2626', '#5B4650', '#9CA3AF', '#777777', '#E5E7EB', '#111111', '#7C3AED'] as $brand) {
    $dot  = planner_appointment_colour(['primary_color' => $brand]);
    $near = min(array_map(fn($o) => theme_distance(theme_hex_to_rgb($dot), theme_hex_to_rgb($o)), PLANNER_DOT_COLOURS));
    ok($near >= 0.12 && theme_contrast(theme_hex_to_rgb($dot), theme_hex_to_rgb(THEME_CREAM)) >= 4.5,
       sprintf('%s appointments are %s, %.2f from the nearest other dot and readable on white and cream', $brand, $dot, $near));
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

// The installed app's launch colour: Android shows it while the app starts and
// keeps it for its bottom bar under every business's app, so it is white, and
// the opening, which is wine, fades in from white there. An iPhone launches
// on its own launch screens, which are wine, so its opening starts wine.
$manifest = json_decode((string)file_get_contents($root . '/site.webmanifest'), true);
ok(($manifest['background_color'] ?? '') === '#FFFFFF' && ($manifest['theme_color'] ?? '') === '#FFFFFF', 'the installed app launches on white, so Android\'s bottom bar is white');
$head = (string)file_get_contents($root . '/views/partials/nf_splash_head.php');
ok(str_contains($head, "'standalone' in navigator") && str_contains($head, 'nf-launch-light') && str_contains($head, "'#FFFFFF'"),
   'away from an iPhone or iPad, the opening is told it starts on white, under a white status bar');
foreach (['views/layouts/app.php', 'views/auth/login.php'] as $page) {
    ok(str_contains((string)file_get_contents($root . '/' . $page), "if (\$splash) require BASE_PATH . '/views/partials/nf_splash_head.php';"), "{$page} decides the opening's first colour in <head>");
}
$splash = (string)file_get_contents($root . '/views/partials/nf_splash.php');
ok(preg_match('/html\.nf-launch-light \.nf-splash \{ animation: nf-from-light 300ms/', $splash) && str_contains($splash, '@keyframes nf-from-light { from { background-color: #FFFFFF; } }'),
   'and the opening fades in from white over 300ms');

// A missing page, for someone signed in: their colour and their dashboard
$missing = (string)file_get_contents($root . '/views/404.php');
ok(str_contains($missing, "'colour'       => theme_tokens(\$business['primary_color'] ?? null, null)['accent']") && str_contains($missing, "require BASE_PATH . '/views/public/notice.php'")
   && str_contains($missing, "'/dashboard'"), 'views/404.php shows someone signed in their business\'s colour and the way to their dashboard');

// The web server shows it by itself too (.htaccess ErrorDocument), with none of the app loaded
$alone = (string)shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/views/404.php') . ' 2>&1');
ok(str_contains($alone, "<h1>This page isn&#039;t here</h1>") && str_contains($alone, '--brand: #8B004A') && str_contains($alone, 'href="/"') && str_contains($alone, '</html>'),
   'and it is whole when the web server shows it by itself: Nfelia\'s, with the way home');

// The installed app's "No connection" page: the colours the app saved on the phone
$offline = (string)file_get_contents($root . '/offline.html');
$shell   = (string)file_get_contents($root . '/assets/js/shell.js');
ok(str_contains($offline, "localStorage.getItem('nf.colours')") && str_contains($shell, "store.set('nf.colours'"),
   'offline.html takes the colours assets/js/shell.js saves on the phone');
ok(str_contains($offline, '.btn-primary { background: var(--accent); color: var(--on-accent); }') && str_contains($offline, 'color: var(--accent-text); box-shadow'),
   'and its buttons are drawn from them');
ok((bool)preg_match("/var VERSION = '[^']+';/", (string)file_get_contents($root . '/sw.js')), 'sw.js has a VERSION to bump when offline.html changes');

// The QR code that checks a document: the business's colour, deep enough for a phone
ok(qr_inks('#8B004A') === [QR_INK, QR_DEEP] && qr_inks(null) === [QR_INK, QR_DEEP] && qr_inks('#1A1A2E') === [QR_INK, QR_DEEP],
   'a wine business, and one that never chose a colour, keep Nfelia\'s QR inks');
foreach (['#C08436', '#F4C430', '#1F3A93', '#00764A', '#9CA3AF', '#E5E7EB', '#7C3AED'] as $brand) {
    [$ink, $deep] = qr_inks($brand);
    [, , $h0] = theme_rgb_to_oklch(theme_hex_to_rgb(theme_tokens($brand, null)['accent']));
    [, $c1, $h1] = theme_rgb_to_oklch($ink);
    $turn = abs(rad2deg(atan2(sin($h1 - $h0), cos($h1 - $h0))));
    // A grey has no hue to keep; a deep yellow leans to amber, as a screen can't show it strong
    ok(theme_contrast($ink, [255, 255, 255]) >= 7 && theme_contrast($deep, [255, 255, 255]) > theme_contrast($ink, [255, 255, 255]) && ($c1 < 0.03 || $turn < 25),
       sprintf('%s documents get a QR code in %s, %.1f:1 on white, corners in %s', $brand, theme_rgb_to_hex($ink), theme_contrast($ink, [255, 255, 255]), theme_rgb_to_hex($deep)));
}
ok(str_contains((string)file_get_contents($root . '/app/helpers/signing.php'), 'doc_qr_uri($code, $colour)'), 'app/helpers/signing.php draws each document\'s QR code in its business\'s colour');

echo $fails ? "\n{$fails} failed\n" : "\nall passed\n";
exit($fails ? 1 : 0);
