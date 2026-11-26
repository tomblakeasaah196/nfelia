<?php
/**
 * Nfelia — IndexNow: telling search engines the day a page is new or has
 * changed, instead of waiting for them to come round (indexnow.org). Bing
 * and the other engines that take part share what they are sent; Google
 * does not take part and reads the sitemaps instead.
 *
 * THE KEY proves the pages are ours. It is public by design: every host we
 * send addresses for serves it, as plain text holding just the key, at
 * /<key>.txt (INDEXNOW_KEY below):
 *
 *   nfelia.com        index.php (BEING FOUND) calls indexnow_serve_key_file().
 *   name.nfelia.com   a shop host must do the same, first thing in
 *                     StorefrontController::handle(), for every shop:
 *
 *                         if (indexnow_serve_key_file($path)) {
 *                             exit;   // the key file has been sent
 *                         }
 *
 *                     It sends the key with its own headers and returns true
 *                     for /<key>.txt (with or without the leading slash);
 *                     for any other path it sends nothing and returns false.
 *
 * WHAT IS SENT: indexnow_tick(), from the cron (cron/planner.php) at most
 * once a day, compares every address in nfelia.com's sitemaps and in every
 * paid shop's sitemap (with when each last changed, or a fingerprint of what
 * a product page shows) with what it sent before (storage/indexnow_state.json),
 * and sends the new, changed and removed ones, and every address of a shop
 * host that is gone (stopped paying, or renamed). The first run sends
 * everything. A host that could not be sent is tried again
 * the next day. It does nothing on a local or test copy (APP_ENV local or
 * testing) unless forced.
 */

/** The IndexNow key: 32 hex characters, fixed. It is public (see above); changing it means every host's key file changes too. */
const INDEXNOW_KEY = '5ab392d63ed9551b26cb58d0b502d558';

/** Where addresses are sent: shared by every engine that takes part. */
const INDEXNOW_ENDPOINT = 'https://api.indexnow.org/indexnow';

/** The most addresses one request may carry. */
const INDEXNOW_MAX_URLS = 10000;

function indexnow_key(): string {
    return INDEXNOW_KEY;
}

/** The key file's full address on a host, e.g. https://ivieluxe.nfelia.com/<key>.txt. */
function indexnow_key_location(string $scheme, string $host): string {
    return $scheme . '://' . $host . '/' . INDEXNOW_KEY . '.txt';
}

/**
 * Serve the key file when $path is /<key>.txt: sends it (text/plain, the
 * key alone) and returns true, so the caller stops there. Any other path:
 * sends nothing, returns false. Works on nfelia.com and on every shop host.
 */
function indexnow_serve_key_file(string $path): bool {
    if (trim($path, '/') !== INDEXNOW_KEY . '.txt') {
        return false;
    }
    if (!headers_sent()) {
        header('Content-Type: text/plain; charset=utf-8', true);
        header('Cache-Control: public, max-age=86400', true);
        header('X-Robots-Tag: noindex', true);
    }
    echo INDEXNOW_KEY;
    return true;
}

/**
 * Send addresses to IndexNow: one request per host (each host's own key file
 * proves it is ours), at most INDEXNOW_MAX_URLS a request. Short timeouts; a
 * failure is written to the error log and counted, never thrown.
 *
 * Returns ['sent' => addresses taken (200 or 202), 'failed' => not taken,
 *          'skipped' => not a web address, 'requests' => n,
 *          'hosts' => [host => last HTTP status, 0 when none came back]].
 */
function indexnow_submit(array $urls, string $endpoint = INDEXNOW_ENDPOINT): array {
    $out = ['sent' => 0, 'failed' => 0, 'skipped' => 0, 'requests' => 0, 'hosts' => []];
    $by_host = [];
    foreach (array_unique(array_map('strval', $urls)) as $url) {
        $parts  = parse_url($url);
        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        $host   = strtolower((string)($parts['host'] ?? ''));
        if ($host === '' || !in_array($scheme, ['http', 'https'], true)) {
            $out['skipped']++;
            continue;
        }
        $hostport = $host . (isset($parts['port']) ? ':' . (int)$parts['port'] : '');
        $by_host[$host]['location'] ??= indexnow_key_location($scheme, $hostport);
        $by_host[$host]['urls'][] = $url;
    }
    foreach ($by_host as $host => $group) {
        foreach (array_chunk($group['urls'], INDEXNOW_MAX_URLS) as $chunk) {
            $out['requests']++;
            [$status, $error] = indexnow_post($endpoint, [
                'host'        => $host,
                'key'         => INDEXNOW_KEY,
                'keyLocation' => $group['location'],
                'urlList'     => $chunk,
            ]);
            $out['hosts'][$host] = $status;
            if ($status === 200 || $status === 202) {
                $out['sent'] += count($chunk);
            } else {
                $out['failed'] += count($chunk);
                error_log('[Nfelia] IndexNow ' . $host . ': ' . count($chunk) . ' address(es) not taken, '
                        . ($status ? 'HTTP ' . $status : 'no answer') . ($error !== '' ? ' (' . $error . ')' : ''));
            }
        }
    }
    return $out;
}

/**
 * One POST of JSON to IndexNow: [HTTP status (0 when no answer), error].
 * Five seconds to connect, fifteen in all.
 */
function indexnow_post(string $endpoint, array $body, int $timeout = 15): array {
    $json = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return [0, 'could not encode the request'];
    }
    try {
        if (function_exists('curl_init')) {
            $ch = curl_init($endpoint);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $json,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json; charset=utf-8'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT        => $timeout,
                CURLOPT_USERAGENT      => 'Nfelia IndexNow (+' . (defined('APP_URL') ? APP_URL : 'https://nfelia.com') . ')',
            ]);
            $answer = curl_exec($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $error  = $answer === false ? curl_error($ch) : ($status >= 300 ? trim(mb_substr((string)$answer, 0, 200)) : '');
            return [$status, $error];
        }
        $context = stream_context_create(['http' => [
            'method'        => 'POST',
            'header'        => "Content-Type: application/json; charset=utf-8\r\n",
            'content'       => $json,
            'timeout'       => $timeout,
            'ignore_errors' => true,
        ]]);
        $answer = @file_get_contents($endpoint, false, $context);
        $status = 0;
        foreach (array_reverse($http_response_header ?? []) as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m)) {
                $status = (int)$m[1];
                break;
            }
        }
        return [$status, $answer === false ? 'no answer' : ($status >= 300 ? trim(mb_substr((string)$answer, 0, 200)) : '')];
    } catch (Throwable $e) {
        return [0, $e->getMessage()];
    }
}

/** What indexnow_tick() needs besides what every caller has (the cron loads only its own). */
function indexnow_load_helpers(): void {
    foreach (['functions', 'billing', 'prices', 'shop_links', 'bookings', 'storefront', 'seo', 'site'] as $helper) {
        require_once __DIR__ . '/' . $helper . '.php';
    }
}

/**
 * Send IndexNow what is new or changed since the last time, at most once a
 * day: nfelia.com's own sitemaps and every paid shop's (seo_main_urls,
 * shop_sitemap_urls). Never throws for one shop or one host: what could not
 * be read or sent is kept as it was and tried again the next day.
 *
 * $options:
 *   force       run on a local or test copy, and again on a day it has run
 *   dry_run     work out what would be sent, send nothing, change nothing;
 *               the result's 'indexnow_list' holds [host => [urls]]
 *   state_file  where what was sent is kept (default storage/indexnow_state.json)
 *   endpoint    where to send (default INDEXNOW_ENDPOINT)
 *   budget      seconds this run may take in all (default 60); shops not
 *               reached wait for the next run
 *
 * Returns counts for the cron's summary line ([] when it did not run):
 * ['indexnow_sent' => n, 'indexnow_failed' => n, 'indexnow_hosts' => n].
 */
function indexnow_tick(PDO $db, array $options = []): array {
    $force    = !empty($options['force']);
    $dry_run  = !empty($options['dry_run']);
    $file     = (string)($options['state_file'] ?? BASE_PATH . '/storage/indexnow_state.json');
    $endpoint = (string)($options['endpoint'] ?? INDEXNOW_ENDPOINT);
    $budget   = (float)($options['budget'] ?? 60);
    $deadline = microtime(true) + $budget;
    // Reading the shops stops at 60% of the time, so there is always time
    // left to send what was found
    $collect_until = microtime(true) + 0.6 * $budget;

    if (!$force && in_array(APP_ENV, ['local', 'testing'], true)) {
        return [];
    }
    // Once a day: the record is written at the end of every run, so its date says whether today's has happened
    clearstatcache(true, $file);
    if (!$force && is_file($file) && date('Y-m-d', (int)filemtime($file)) === date('Y-m-d')) {
        return [];
    }
    // A run that cannot keep its record would send everything again every
    // five minutes: nothing is sent until the record can be written
    $dir = dirname($file);
    if (!$dry_run && ((!is_dir($dir) && !@mkdir($dir, 0755, true)) || !is_writable($dir) || (is_file($file) && !is_writable($file)))) {
        error_log('[Nfelia] IndexNow: ' . $file . ' cannot be written; nothing sent');
        return ['indexnow_failed' => 1];
    }
    indexnow_load_helpers();

    $before = [];
    if (is_file($file)) {
        $read = json_decode((string)@file_get_contents($file), true);
        $before = is_array($read['hosts'] ?? null) ? $read['hosts'] : [];
    }
    $host_of = fn(string $url) => strtolower((string)parse_url($url, PHP_URL_HOST));

    // What the sitemaps hold today, by host. A host that could not be read
    // (an error, or out of time) keeps what was sent before.
    $now = [];
    $keep = [];
    try {
        foreach (seo_main_urls($db) as $url => $at) {
            $now[$host_of($url)][$url] = $at;
        }
    } catch (Throwable $e) {
        error_log('[Nfelia] IndexNow: nfelia.com\'s own addresses: ' . $e->getMessage());
        $keep[$host_of(APP_URL)] = true;
    }
    try {
        $shops = seo_live_shops($db);
    } catch (Throwable $e) {
        error_log('[Nfelia] IndexNow: paid shops: ' . $e->getMessage());
        $shops = null;   // nothing known about shops today: every shop host keeps its record
    }
    foreach ($shops ?? [] as $row) {
        $host = $host_of(shop_url((string)$row['subdomain']));
        if (microtime(true) > $collect_until) {
            $keep[$host] = true;
            continue;
        }
        try {
            $business = shop_find_by_name((string)$row['subdomain']);
            if (!$business) {
                $keep[$host] = true;
                continue;
            }
            // What the page shows when the sitemap says ('shows': products
            // have no trustworthy date, see shop_sitemap_urls), else its date
            foreach (shop_sitemap_urls($db, $business) as $u) {
                $now[$host][$u[0]] = (string)($u[2]['shows'] ?? (sitemap_date($u[1] ?? null) ?? ''));
            }
        } catch (Throwable $e) {
            error_log('[Nfelia] IndexNow: shop ' . $row['subdomain'] . ': ' . $e->getMessage());
            unset($now[$host]);
            $keep[$host] = true;
        }
    }

    // Hosts sent before and gone today (a shop that stopped paying, or a
    // renamed shop's old address): every address they had is sent once
    // more, so the engines come and find it gone or moved (indexnow.org asks
    // for removed and redirected addresses too). Only when today's list of
    // shops is known; a host that was only out of time or failed is kept.
    if ($shops !== null) {
        foreach ($before as $host => $urls) {
            if (!isset($now[$host]) && !isset($keep[$host])) {
                $now[$host] = [];
            }
        }
    }

    $after = [];
    $list  = [];
    $done  = ['indexnow_sent' => 0, 'indexnow_failed' => 0, 'indexnow_hosts' => 0];
    // nfelia.com's own first, so a slow day with many shops still sends them
    uksort($now, fn($a, $b) => ($b === $host_of(APP_URL)) <=> ($a === $host_of(APP_URL)));
    foreach ($now as $host => $urls) {
        $old     = $before[$host] ?? [];
        // New and changed addresses, and the ones no longer there
        $changed = array_merge(
            array_keys(array_filter($urls, fn($at, $url) => !array_key_exists($url, $old) || (string)$old[$url] !== (string)$at, ARRAY_FILTER_USE_BOTH)),
            array_keys(array_diff_key($old, $urls))
        );
        if (!$changed) {
            if ($urls) {
                $after[$host] = $urls;
            }
            continue;
        }
        $list[$host] = $changed;
        if ($dry_run) {
            continue;
        }
        if (microtime(true) > $deadline) {
            if ($old) {
                $after[$host] = $old;   // not sent this time: the difference stays for the next run
            }
            continue;
        }
        $done['indexnow_hosts']++;
        $sent = indexnow_submit($changed, $endpoint);
        $done['indexnow_sent']   += $sent['sent'];
        $done['indexnow_failed'] += $sent['failed'] + $sent['skipped'];
        if ($sent['failed'] === 0) {
            if ($urls) {
                $after[$host] = $urls;   // a host that is gone and was told so is dropped
            }
        } elseif ($old) {
            $after[$host] = $old;
        }
    }
    foreach (array_keys($keep) as $host) {
        if (isset($before[$host]) && !isset($after[$host])) {
            $after[$host] = $before[$host];
        }
    }
    if ($shops === null) {
        foreach ($before as $host => $urls) {
            $after[$host] ??= $urls;
        }
    }

    if ($dry_run) {
        return $done + ['indexnow_would' => array_sum(array_map('count', $list)), 'indexnow_list' => $list];
    }
    // Hosts no longer here (a shop that stopped paying) are dropped once
    // their addresses have been sent, so they are sent again in full if
    // they come back.
    $json = json_encode(['written' => date('c'), 'hosts' => $after], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    $tmp  = $file . '.' . getmypid() . '.tmp';
    if ($json === false || @file_put_contents($tmp, $json) === false || !@rename($tmp, $file)) {
        @unlink($tmp);
        error_log('[Nfelia] IndexNow: could not write ' . $file);
        @touch($file);   // still counts as today's run, so a broken disk does not mean a send every five minutes
    }
    return $done;
}
