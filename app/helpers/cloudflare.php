<?php
/**
 * Nfelia — Cloudflare cache purge
 *
 * Shop pages are cached at Cloudflare for up to five minutes (the Cache Rule
 * is written out in docs/handoff/Shop studio step 2.md). When an owner
 * publishes, edits a product or changes what the shop shows, the shop's own
 * hostname is purged so shoppers see the change straight away.
 *
 * Needs two values in .env (see .env.example):
 *   CLOUDFLARE_API_TOKEN  a token allowed only Zone → Cache Purge on nfelia.com
 *   CLOUDFLARE_ZONE_ID    the zone ID shown on the nfelia.com overview page
 * Without them nothing is purged and shops catch up within five minutes.
 * A failed purge is logged and never shown: it costs freshness, not the
 * owner's save.
 */

/**
 * Purge everything Cloudflare holds for these hostnames. Returns false when
 * purging is switched off or Cloudflare said no.
 */
function cloudflare_purge_hosts(array $hosts): bool {
    $token = trim((string)($_ENV['CLOUDFLARE_API_TOKEN'] ?? ''));
    $zone  = trim((string)($_ENV['CLOUDFLARE_ZONE_ID'] ?? ''));
    if ($token === '' && $zone === '') {
        return false;
    }
    if ($token === '' || !preg_match('/^[a-f0-9]{32}$/', $zone)) {
        error_log('[Nfelia] Cloudflare purge skipped: set both CLOUDFLARE_API_TOKEN and CLOUDFLARE_ZONE_ID (a 32-character zone ID).');
        return false;
    }
    $hosts = array_values(array_unique(array_filter(
        array_map('strtolower', $hosts),
        fn($host) => (bool)preg_match('/^[a-z0-9-]+(\.[a-z0-9-]+)+$/', $host)
    )));
    if (!$hosts || !function_exists('curl_init')) {
        return false;
    }

    $ok = true;
    foreach (array_chunk($hosts, 30) as $chunk) {   // Cloudflare takes 30 hosts per call
        $ch = curl_init('https://api.cloudflare.com/client/v4/zones/' . $zone . '/purge_cache');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode(['hosts' => $chunk]),
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $token, 'Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT        => 4,
        ]);
        $body   = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error  = curl_error($ch);
        curl_close($ch);

        $json = is_string($body) ? json_decode($body, true) : null;
        if ($status !== 200 || empty($json['success'])) {
            $ok = false;
            error_log('[Nfelia] Cloudflare purge failed for ' . implode(', ', $chunk) . ': HTTP ' . $status
                . ($error !== '' ? ' (' . $error . ')' : '')
                . ' ' . substr((string)json_encode($json['errors'] ?? null), 0, 300));
        }
    }
    return $ok;
}

/**
 * Purge a business's shop once this response has reached the owner, so a
 * save never waits on Cloudflare. Pass old names too after a rename. Several
 * calls in one request become one purge.
 */
function storefront_purge(int $business_id, array $also_names = []): void {
    static $hosts = [];
    static $scheduled = false;

    if (trim((string)($_ENV['CLOUDFLARE_API_TOKEN'] ?? '')) === '') {
        return;
    }
    try {
        $stmt = get_db()->prepare("SELECT subdomain FROM businesses WHERE id = ?");
        $stmt->execute([$business_id]);
        $names = array_merge([(string)$stmt->fetchColumn()], $also_names);
    } catch (PDOException $e) {
        error_log('[Nfelia] storefront_purge: ' . $e->getMessage());
        return;
    }
    foreach (array_filter($names) as $name) {
        $hosts[] = $name . '.' . shop_base_host();
    }
    if (!$scheduled && $hosts) {
        $scheduled = true;
        after_response(function () use (&$hosts) {
            cloudflare_purge_hosts($hosts);
        });
    }
}
