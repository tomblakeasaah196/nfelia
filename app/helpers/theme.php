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

/** @return array{accent:string, accent_rgb:string, on_accent:string, accent_text:string, accent_soft:string, glow:string, glow_2:string, secondary_rgb:string} */
function theme_tokens(?string $primary, ?string $secondary): array
{
    $primary = theme_valid_hex($primary);
    if ($primary === null || strtolower($primary) === THEME_UNSET_COLOUR) {
        $primary = THEME_NFELIA;
    }
    $secondary = theme_valid_hex($secondary) ?? '#B69F5E';

    $rgb = theme_hex_to_rgb($primary);
    [$l, $c, $h] = theme_rgb_to_oklch($rgb);
    $white = [255, 255, 255];
    $ink   = theme_hex_to_rgb(THEME_INK);

    // Text and icons in the brand colour: darken until they read on white.
    $text = $rgb;
    for ($i = 0, $tl = $l; theme_contrast($text, $white) < 4.5 && $i < 50; $i++) {
        $tl -= 0.02;
        $text = theme_oklch_to_rgb($tl, $c, $h);
    }

    return [
        'accent'        => theme_rgb_to_hex($rgb),
        'accent_rgb'    => implode(' ', $rgb),
        'on_accent'     => theme_contrast($rgb, $white) >= theme_contrast($rgb, $ink) ? '#FFFFFF' : THEME_INK,
        'accent_text'   => theme_rgb_to_hex($text),
        'accent_soft'   => theme_rgb_to_hex(theme_oklch_to_rgb(0.955, min(0.04, $c * 0.35), $h)),
        'glow'          => theme_rgb_to_hex(theme_oklch_to_rgb(0.958, min(0.045, $c * 0.4), $h)),
        'glow_2'        => theme_rgb_to_hex(theme_oklch_to_rgb(0.975, min(0.03, $c * 0.25), $h + 0.35)),
        'secondary_rgb' => implode(' ', theme_hex_to_rgb($secondary)),
    ];
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
    $A = $C * cos($h);
    $B = $C * sin($h);
    $l = ($L + 0.3963377774 * $A + 0.2158037573 * $B) ** 3;
    $m = ($L - 0.1055613458 * $A - 0.0638541728 * $B) ** 3;
    $s = ($L - 0.0894841775 * $A - 1.2914855480 * $B) ** 3;
    $rgb = [
        4.0767416621 * $l - 3.3077115913 * $m + 0.2309699292 * $s,
        -1.2684380046 * $l + 2.6097574011 * $m - 0.3413193965 * $s,
        -0.0041960863 * $l - 0.7034186147 * $m + 1.7076147010 * $s,
    ];
    return array_map(fn($v) => max(0.0, min(255.0, theme_gamma(max(0.0, min(1.0, $v))))), $rgb);
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
