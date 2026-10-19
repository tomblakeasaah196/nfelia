<?php
/**
 * Small helpers shared by the main screens (Home, Sales, the invoice and
 * receipt pages, Clients, Stock): addresses for filters, dates in words, and
 * how a document stands, said the same way everywhere.
 */

/** APP_URL/path with only the filters that are set (never the router's own url=). */
function screen_url(string $path, array $params = []): string
{
    $params = array_filter($params, fn($v) => $v !== null && $v !== '' && $v !== false);
    unset($params['url']);
    return APP_URL . '/' . ltrim($path, '/') . ($params ? '?' . http_build_query($params) : '');
}

/** "Today", "Yesterday", "12 Oct", or "12 Oct 2025" for another year (Lagos time). */
function screen_date(?string $date): string
{
    if (!$date) return '';
    $day   = substr($date, 0, 10);
    $today = planner_today();
    if ($day === $today) return 'Today';
    if ($day === planner_add_days($today, -1)) return 'Yesterday';
    $ts = strtotime($day);
    return date(substr($day, 0, 4) === substr($today, 0, 4) ? 'j M' : 'j M Y', $ts);
}

/** How an invoice stands: [words, tag class]. */
function screen_invoice_tag(string $status): array
{
    return match ($status) {
        'draft'          => ['Draft', 'is-line'],
        'sent'           => ['Sent', ''],
        'partially_paid' => ['Part paid', 'is-warn'],
        'paid'           => ['Paid', 'is-in'],
        'overdue'        => ['Overdue', 'is-owed'],
        'cancelled'      => ['Cancelled', 'is-line'],
        default          => [ucfirst(str_replace('_', ' ', $status)), ''],
    };
}

/** How a quote stands: [words, tag class]. */
function screen_quote_tag(string $status): array
{
    return match ($status) {
        'draft'             => ['Draft', 'is-line'],
        'sent'              => ['Sent', ''],
        'in_negotiation'    => ['Customer replied', 'is-warn'],
        'pending_signature' => ['Waiting to sign', 'is-warn'],
        'accepted'          => ['Accepted', 'is-in'],
        'declined'          => ['Declined', 'is-owed'],
        'expired'           => ['Expired', 'is-line'],
        'converted'         => ['Ordered', 'is-accent'],
        default             => [ucfirst(str_replace('_', ' ', $status)), ''],
    };
}

/** "Bank transfer", "POS", "Cash". */
function screen_method(?string $method): string
{
    return match ((string)$method) {
        'pos'           => 'POS',
        'bank_transfer' => 'Bank transfer',
        'mobile_money'  => 'Mobile money',
        ''              => '',
        default         => ucfirst(str_replace('_', ' ', (string)$method)),
    };
}
