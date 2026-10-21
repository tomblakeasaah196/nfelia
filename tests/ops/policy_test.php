<?php
/**
 * The content policy in .htaccess against what the views actually load
 * (docs/ops/SECURITY.md §6). Pure text, no database and no server, so it
 * runs on its own:
 *   php tests/ops/policy_test.php
 *
 * Every script and stylesheet a view pulls from another site has to be
 * named in the policy. One that is not becomes a "Content policy" error in
 * Ops → Errors the moment someone opens the page, and the report-only
 * trial never gets the two clean weeks it needs before it is enforced.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$root  = dirname(__DIR__, 2);
$fails = 0;

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

/** The report-only policy from .htaccess, as directive => its sources. */
function policy_from_htaccess(string $root): array
{
    $text = (string)file_get_contents($root . '/.htaccess');
    if (!preg_match('/Content-Security-Policy-Report-Only\s+"([^"]+)"/', $text, $m)) {
        return [];
    }
    $out = [];
    foreach (explode(';', $m[1]) as $part) {
        $bits = preg_split('/\s+/', trim($part), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($bits) {
            $out[array_shift($bits)] = $bits;
        }
    }
    return $out;
}

/** Every script and stylesheet the views pull from another site. */
function foreign_assets(string $root): array
{
    $found = [];
    $dir   = new RecursiveDirectoryIterator($root . '/views', FilesystemIterator::SKIP_DOTS);
    foreach (new RecursiveIteratorIterator($dir) as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        $html  = (string)file_get_contents($file->getPathname());
        $where = substr($file->getPathname(), strlen($root) + 1);

        if (preg_match_all('#<script\b[^>]*\bsrc="(https://[^"]+)"#i', $html, $m)) {
            foreach ($m[1] as $url) {
                $found[] = ['script-src', $url, $where];
            }
        }
        // A <link> is only a stylesheet when it says so; preconnect and
        // icon links load nothing the policy governs here.
        if (preg_match_all('#<link\b[^>]*>#i', $html, $m)) {
            foreach ($m[0] as $tag) {
                if (preg_match('/\brel="[^"]*stylesheet/i', $tag)
                    && preg_match('#\bhref="(https://[^"]+)"#i', $tag, $h)) {
                    $found[] = ['style-src', $h[1], $where];
                }
            }
        }
    }
    sort($found);
    return $found;
}

/** Does the policy let this directive load from this address? */
function policy_allows(array $policy, string $directive, string $url): bool
{
    $sources = $policy[$directive] ?? $policy['default-src'] ?? [];
    $host    = (string)parse_url($url, PHP_URL_HOST);
    foreach ($sources as $source) {
        // 'self', 'unsafe-inline', data:, blob: name no host.
        if ($source === '' || $source[0] === "'" || !str_contains($source, '.')) {
            continue;
        }
        $allowed = (string)preg_replace('#^https?://#', '', $source);
        if ($allowed === $host) {
            return true;
        }
        if (str_starts_with($allowed, '*.') && str_ends_with($host, substr($allowed, 1))) {
            return true;
        }
    }
    return false;
}

$policy = policy_from_htaccess($root);
ok($policy !== [], 'the report-only policy is in .htaccess');
ok(($policy['report-uri'] ?? []) === ['https://nfelia.com/api/csp'],
    'and every page reports what it would block to https://nfelia.com/api/csp (a shop\'s own address has no /api)');

$assets = foreign_assets($root);
ok($assets !== [], 'the views load something from another site, so there is anything to check');
foreach ($assets as [$directive, $url, $where]) {
    ok(policy_allows($policy, $directive, $url),
        $directive . ' allows ' . parse_url($url, PHP_URL_HOST) . ', which ' . $where . ' loads');
}

// Two the views cannot show. Cloudflare adds its analytics beacon to every
// page at the edge, so it is in no file here; and the cropper on Settings
// reads the photo just chosen back off its blob: address to find which way
// up it was taken.
ok(policy_allows($policy, 'script-src', 'https://static.cloudflareinsights.com/beacon.min.js'),
    "script-src allows Cloudflare's own beacon, which the edge adds to every page");
ok(in_array('blob:', $policy['connect-src'] ?? [], true),
    'connect-src allows blob:, which the cropper reads the chosen photo from');

// A shop page is at name.nfelia.com, where 'self' is the shop. It takes its
// stylesheet and script from the main address and sends its script errors
// there, so the policy must name that address or every shop breaks the day
// the policy is enforced.
$shell = (string)file_get_contents($root . '/views/storefront/shell.php');
ok((bool)preg_match('/\$asset\s*=\s*fn\(string \$path\)\s*=>\s*APP_URL\b/', $shell),
    'shop pages take their files from the main address (views/storefront/shell.php)');
foreach ([['style-src', 'https://nfelia.com/assets/css/storefront.css'], ['script-src', 'https://nfelia.com/assets/js/storefront.js'],
          ['connect-src', 'https://nfelia.com/api/errors']] as [$directive, $url]) {
    ok(policy_allows($policy, $directive, $url), $directive . ' lets a shop page reach ' . preg_replace('#^https://#', '', $url));
}

// The main site is only served from the address its files come from.
$www_rule = <<<'TXT'
RewriteCond %{HTTP_HOST} ^www\.nfelia\.com$ [NC]
RewriteCond %{REQUEST_URI} !^/\.well-known/
RewriteRule ^(.*)$ https://nfelia.com/$1 [R=301,L]
TXT;
ok(str_contains(str_replace("\r\n", "\n", (string)file_get_contents($root . '/.htaccess')), $www_rule),
    'www.nfelia.com is sent to https://nfelia.com, except certificate checks under .well-known/');

echo $fails ? "\n{$fails} failed\n" : "\nall passed\n";
exit($fails ? 1 : 0);
