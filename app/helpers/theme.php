<?php
/**
 * The signed-in app's colours for one business.
 *
 * The business's brand colour becomes the accent. Everything the shell needs
 * is worked out from it here, on the server, so pages arrive already
 * coloured: a shade for text and icons that passes WCAG AA (4.5:1) on white,
 * the colour for text on a filled button, a pale tint for selected states,
 * and the faint glow behind the white background.
 *
 * Colours are mixed in OKLCH, where lightness looks even across hues, so a
 * yellow brand and a navy brand get tints of the same visual weight.
 */

const THEME_INK          = '#1F0A14';
const THEME_NFELIA       = '#8B004A';
const THEME_CREAM        = '#F7F3EA';
// Businesses created before sign-up set a colour still have the column's
// default. Nobody chose it, so they get Nfelia's own colour in the app.
const THEME_UNSET_COLOUR = '#1a1a2e';

/** @return array{accent:string, accent_rgb:string, on_accent:string, accent_text:string, accent_text_rgb:string, accent_soft:string, glow:string, glow_2:string, secondary_rgb:string} */
function theme_tokens(?string $primary, ?string $secondary): array
{
    $primary = theme_valid_hex($primary);
    if ($primary === null || strtolower($primary) === THEME_UNSET_COLOUR) {
        $primary = THEME_NFELIA;
    }
    $secondary = theme_valid_hex($secondary) ?? '#B69F5E';

    $rgb = theme_hex_to_rgb($primary);
    [, $c, $h] = theme_rgb_to_oklch($rgb);
    $white = [255, 255, 255];
    $ink   = theme_hex_to_rgb(THEME_INK);
    $soft  = theme_rgb_to_hex(theme_oklch_to_rgb(0.955, min(0.04, $c * 0.35), $h));

    // Text and icons in the brand colour: darkened until they read on white,
    // on the cream background people can choose, and on the pale tint behind
    // a selected chip.
    $text = theme_readable($primary, ['#FFFFFF', THEME_CREAM, $soft]);

    return [
        'accent'          => theme_rgb_to_hex($rgb),
        'accent_rgb'      => implode(' ', $rgb),
        'on_accent'       => theme_contrast($rgb, $white) >= theme_contrast($rgb, $ink) ? '#FFFFFF' : THEME_INK,
        'accent_text'     => $text,
        'accent_text_rgb' => implode(' ', theme_hex_to_rgb($text)),
        'accent_soft'     => $soft,
        'glow'            => theme_rgb_to_hex(theme_oklch_to_rgb(0.958, min(0.045, $c * 0.4), $h)),
        'glow_2'          => theme_rgb_to_hex(theme_oklch_to_rgb(0.975, min(0.03, $c * 0.25), $h + 0.35)),
        'secondary_rgb'   => implode(' ', theme_hex_to_rgb($secondary)),
    ];
}

/**
 * $hex darkened, keeping its hue and strength, until words in it read on
 * every one of $grounds (4.5:1): white, the cream background people can
 * choose, a pale tint. A colour that already reads comes back as it is.
 */
function theme_readable(string $hex, array $grounds, float $ratio = 4.5): string
{
    $grounds = array_map('theme_hex_to_rgb', $grounds);
    $rgb = theme_hex_to_rgb($hex);
    [$l, $c, $h] = theme_rgb_to_oklch($rgb);
    for ($i = 0; $i < 120 && min(array_map(fn($g) => theme_contrast($rgb, $g), $grounds)) < $ratio; $i++) {
        $l -= 0.01;
        $rgb = theme_hex_to_rgb(theme_rgb_to_hex(theme_oklch_to_rgb($l, $c, $h)));
    }
    return theme_rgb_to_hex($rgb);
}

function theme_valid_hex(?string $hex): ?string
{
    $hex = trim((string)$hex);
    return preg_match('/^#[0-9a-fA-F]{6}$/', $hex) ? $hex : null;
}

/** @return int[] */
function theme_hex_to_rgb(string $hex): array
{
    $n = hexdec(ltrim($hex, '#'));
    return [($n >> 16) & 255, ($n >> 8) & 255, $n & 255];
}

function theme_rgb_to_hex(array $rgb): string
{
    return sprintf('#%02X%02X%02X', ...array_map(fn($v) => (int)round(max(0, min(255, $v))), $rgb));
}

function theme_linear(float $v): float
{
    $v /= 255;
    return $v <= 0.04045 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
}

function theme_gamma(float $v): float
{
    return 255 * ($v <= 0.0031308 ? 12.92 * $v : 1.055 * ($v ** (1 / 2.4)) - 0.055);
}

function theme_luminance(array $rgb): float
{
    return 0.2126 * theme_linear($rgb[0]) + 0.7152 * theme_linear($rgb[1]) + 0.0722 * theme_linear($rgb[2]);
}

/** WCAG contrast ratio between two sRGB colours. */
function theme_contrast(array $a, array $b): float
{
    $x = theme_luminance($a);
    $y = theme_luminance($b);
    return (max($x, $y) + 0.05) / (min($x, $y) + 0.05);
}

/** @return float[] [L, C, h in radians] */
function theme_rgb_to_oklch(array $rgb): array
{
    [$r, $g, $b] = array_map('theme_linear', $rgb);
    $l = (0.4122214708 * $r + 0.5363325363 * $g + 0.0514459929 * $b) ** (1 / 3);
    $m = (0.2119034982 * $r + 0.6806995451 * $g + 0.1073969566 * $b) ** (1 / 3);
    $s = (0.0883024619 * $r + 0.2817188376 * $g + 0.6299787005 * $b) ** (1 / 3);
    $L = 0.2104542553 * $l + 0.7936177850 * $m - 0.0040720468 * $s;
    $A = 1.9779984951 * $l - 2.4285922050 * $m + 0.4505937099 * $s;
    $B = 0.0259040371 * $l + 0.7827717662 * $m - 0.8086757660 * $s;
    return [$L, hypot($A, $B), atan2($B, $A)];
}

/** @return float[] sRGB 0..255, clamped to the gamut */
function theme_oklch_to_rgb(float $L, float $C, float $h): array
{
    return array_map(fn($v) => max(0.0, min(255.0, theme_gamma(max(0.0, min(1.0, $v))))), theme_oklch_to_linear($L, $C, $h));
}

/** @return float[] linear sRGB, each 0..1 when the colour is one a screen can show */
function theme_oklch_to_linear(float $L, float $C, float $h): array
{
    $A = $C * cos($h);
    $B = $C * sin($h);
    $l = ($L + 0.3963377774 * $A + 0.2158037573 * $B) ** 3;
    $m = ($L - 0.1055613458 * $A - 0.0638541728 * $B) ** 3;
    $s = ($L - 0.0894841775 * $A - 1.2914855480 * $B) ** 3;
    return [
        4.0767416621 * $l - 3.3077115913 * $m + 0.2309699292 * $s,
        -1.2684380046 * $l + 2.6097574011 * $m - 0.3413193965 * $s,
        -0.0041960863 * $l - 0.7034186147 * $m + 1.7076147010 * $s,
    ];
}

/** Can a screen show this colour as it is, without clamping it? */
function theme_oklch_fits(float $L, float $C, float $h): bool
{
    $rgb = theme_oklch_to_linear($L, $C, $h);
    return min($rgb) >= -0.0001 && max($rgb) <= 1.0001;
}

/** How far apart two sRGB colours look: the distance between them in OKLab. */
function theme_distance(array $a, array $b): float
{
    [$la, $ca, $ha] = theme_rgb_to_oklch($a);
    [$lb, $cb, $hb] = theme_rgb_to_oklch($b);
    return sqrt(($la - $lb) ** 2 + ($ca * cos($ha) - $cb * cos($hb)) ** 2 + ($ca * sin($ha) - $cb * sin($hb)) ** 2);
}

/**
 * A colour for one kind of thing among others told apart by colour, such as
 * the calendar's dots: $hex itself when it stands far enough from every one
 * of $others, else the nearest colour that does. First a deeper shade of the
 * same hue; when no shade will do, the hue turns a few degrees at a time,
 * either way, until one does. A grey, which has no hue to turn, goes deeper
 * still. A shade keeps as much of the colour's strength as a screen can show
 * at its lightness. Far enough is $gap in OKLab, about what the calendar's
 * own colours keep between them. Every colour tried reads on white and on the
 * cream background (4.5:1), so it also serves for a label.
 */
function theme_distinct(string $hex, array $others, float $gap = 0.12): string
{
    $cream  = theme_hex_to_rgb(THEME_CREAM);   // darker than white, so what reads on it reads on both
    $others = array_map('theme_hex_to_rgb', $others);
    [$l0, $c0, $h0] = theme_rgb_to_oklch(theme_hex_to_rgb($hex));
    // The colour at lightness $l and hue $h when it reads and stands apart, else null
    $try = function (float $l, float $h) use ($c0, $cream, $others, $gap): ?string {
        $c = $c0;
        while ($c > 0 && !theme_oklch_fits($l, $c, $h)) {
            $c -= 0.005;
        }
        $rgb = theme_hex_to_rgb(theme_rgb_to_hex(theme_oklch_to_rgb($l, max(0.0, $c), $h)));
        if (theme_contrast($rgb, $cream) < 4.5) {
            return null;
        }
        foreach ($others as $other) {
            if (theme_distance($rgb, $other) < $gap) {
                return null;
            }
        }
        return theme_rgb_to_hex($rgb);
    };
    for ($turn = 0; $turn <= 180; $turn += 3) {
        foreach (array_unique([$turn, -$turn]) as $t) {
            for ($l = $l0; $l >= $l0 - 0.2; $l -= 0.01) {
                if ($found = $try($l, $h0 + deg2rad($t))) {
                    return $found;
                }
            }
        }
    }
    for ($l = $l0 - 0.21; $l >= 0.2; $l -= 0.01) {
        if ($found = $try($l, $h0)) {
            return $found;
        }
    }
    return $hex;
}

/**
 * One of Nfelia's own wine-tinted greys (a line, a background) moved into the
 * business's colour: the same lightness, turned from wine's hue to the
 * business's, and as much of a tint as wine gives it, less when the business's
 * colour is greyer than wine. Wine gets its own greys back; a grey brand gets
 * plain greys.
 */
function theme_retint(string $grey, string $toward): string
{
    [$l, $c, $h]          = theme_rgb_to_oklch(theme_hex_to_rgb($grey));
    [, $wine_c, $wine_h]  = theme_rgb_to_oklch(theme_hex_to_rgb(THEME_NFELIA));
    [, $to_c, $to_h]      = theme_rgb_to_oklch(theme_hex_to_rgb($toward));
    return theme_rgb_to_hex(theme_oklch_to_rgb($l, $c * min(1.0, $to_c / $wine_c), $h - $wine_h + $to_h));
}

/**
 * The person's appearance choices on this device: text size and background.
 * Kept in a cookie rather than the account, the way a phone's own text size
 * is per device, and so the page is drawn right on the first byte.
 *
 * @return array{text:string, bg:string}
 */
function theme_appearance(): array
{
    parse_str(str_replace(';', '&', (string)($_COOKIE['nf_ui'] ?? '')), $ui);
    return [
        'text' => in_array($ui['text'] ?? '', ['small', 'standard', 'large'], true) ? $ui['text'] : 'standard',
        'bg'   => in_array($ui['bg'] ?? '', ['white', 'cream'], true) ? $ui['bg'] : 'white',
    ];
}

/**
 * A colour as the "r g b" channels Tailwind's rgb(var(--brand-rgb) / a)
 * expects (tailwind.public.config.js). Anything that isn't a 6-digit hex
 * colour becomes Nfelia's wine.
 */
function brand_rgb(?string $hex): string
{
    $hex = ltrim((string)$hex, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
        $hex = '8B004A';
    }
    return implode(' ', sscanf($hex, '%02x%02x%02x'));
}
