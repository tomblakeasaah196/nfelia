<?php
/**
 * POST /api/csp — a Content-Security-Policy report from someone's browser.
 *
 * The policy is sent by .htaccess in report-only mode first (docs/ops/SECURITY.md
 * §6): nothing is blocked, the browser only says what would have been. Each
 * report becomes an error of kind "csp" (app/helpers/errors.php), grouped by
 * directive and blocked address, so Ops → Errors shows which pages load what
 * the policy does not know about. Both report shapes are read: the report-uri
 * body ({"csp-report": {...}}) and the Reporting API list
 * ([{"type": "csp-violation", "body": {...}}, ...]).
 *
 * Anyone can send one, so it is held to little: 16 KB, 60 a network in ten
 * minutes, at most five reports a request, only our own pages, and nothing
 * caused by a browser extension. Always answers 204: the page never waits on it.
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}
$raw = (string)file_get_contents('php://input', false, null, 0, 16384);
$in  = json_decode($raw, true);
http_response_code(204);
header_remove('Content-Type');
if (!is_array($in) || !throttle_allows('csp_reports', client_ip(), 60, 10)) {
    exit;
}

// One list of reports, whichever shape arrived.
$reports = [];
if (isset($in['csp-report']) && is_array($in['csp-report'])) {
    $reports[] = $in['csp-report'];
} elseif (array_is_list($in)) {
    foreach ($in as $item) {
        if (is_array($item) && ($item['type'] ?? '') === 'csp-violation' && is_array($item['body'] ?? null)) {
            $reports[] = $item['body'];
        }
    }
}
$reports = array_slice($reports, 0, 5);

// Ours: the app, a shop (name.<host>), Ops, or this machine when testing.
$base = shop_base_host();
$ours = function (string $url) use ($base): bool {
    $host = strtolower((string)parse_url($url, PHP_URL_HOST));
    return $host !== '' && ($host === $base || str_ends_with($host, '.' . $base) || $host === 'www.' . $base);
};
// The two shapes name the same things differently.
$pick = function (array $r, string $old, string $new): string {
    return mb_substr(trim((string)($r[$old] ?? $r[$new] ?? '')), 0, 1000);
};

foreach ($reports as $r) {
    $document  = $pick($r, 'document-uri', 'documentURL');
    $directive = $pick($r, 'effective-directive', 'effectiveDirective') ?: $pick($r, 'violated-directive', 'violatedDirective');
    $blocked   = $pick($r, 'blocked-uri', 'blockedURL');
    $source    = $pick($r, 'source-file', 'sourceFile');
    $line      = (int)($r['line-number'] ?? $r['lineNumber'] ?? 0);
    $sample    = mb_substr(trim((string)($r['script-sample'] ?? $r['sample'] ?? '')), 0, 200);
    $mode      = ($r['disposition'] ?? 'report') === 'enforce' ? 'blocked' : 'would block';

    if ($directive === '' || !$ours($document)
        || preg_match('#^(chrome|moz|safari(-web)?|ms-browser)-extension#i', $blocked . ' ' . $source)
        || preg_match('#webkit-masked-url|^about:|^chrome://#i', $blocked . ' ' . $source)) {
        continue;
    }
    // "inline" and "eval" are the browser's words for script in the page itself;
    // an address is reduced to its host, so one foreign site is one group.
    $where = $blocked === ''
        ? '(no address)'
        : (preg_match('#^[a-z][a-z0-9+.-]*:#i', $blocked) ? ((string)parse_url($blocked, PHP_URL_HOST) ?: $blocked) : $blocked);

    $page_host = strtolower((string)parse_url($document, PHP_URL_HOST));
    $shop      = shop_label_from_host($page_host);
    $business  = $shop !== null ? shop_find_by_name($shop) : null;
    [$path]    = errors_clean_path((string)parse_url($document, PHP_URL_PATH) . (parse_url($document, PHP_URL_QUERY) ? '?' . parse_url($document, PHP_URL_QUERY) : ''));

    errors_context_set(['not_slow' => true]);
    errors_capture([
        'kind'        => 'csp',
        'level'       => 'warning',
        'message'     => $directive . ' ' . $mode . ' ' . $where
                       . ($source !== '' ? "\nfrom " . $source . ($line ? ':' . $line : '') : '')
                       . ($sample !== '' ? "\nsample: " . $sample : '')
                       . ($blocked !== '' && $blocked !== $where ? "\naddress: " . $blocked : ''),
        'file'        => $directive . ' ' . $where,   // groups by what the policy met, not by the page
        'line'        => null,
        'trace'       => null,
        'source'      => $shop !== null ? 'shop' : (ops_is_host($page_host) ? 'ops' : 'browser'),
        'business_id' => $shop !== null ? ($business ? (int)$business['id'] : null) : (auth_business_id() ?: null),
        'user_id'     => $shop !== null ? null : (auth_user()['id'] ?? null),
        'method'      => 'GET',
        'path'        => mb_substr(($shop !== null || ops_is_host($page_host) ? $page_host . ' ' : '') . '/' . $path, 0, 255),
    ]);
}
exit;
