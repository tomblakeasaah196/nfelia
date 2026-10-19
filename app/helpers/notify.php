<?php
/**
 * Notifications: everything Nfelia tells someone goes through one door.
 *
 *   notify('order.new', $business_id, ['order_id' => 12, ...], ['actor' => $user_id]);
 *
 * 1. Who hears: the event's audience (notify_events()), less whoever caused
 *    it. Owners and admins hear about everything; staff hear about their
 *    work (orders, bookings, stock, their tasks) and never see amounts.
 * 2. The bell: one row per person, always, with their own read and unread.
 * 3. Phone and email: as each person chose for the event's topic
 *    (More > Notifications; NOTIFY_TOPICS has the defaults). No phone buzz
 *    in their quiet hours: it waits and goes when they end, unless it would
 *    be too late to be useful by then. Billing and security emails always go.
 * 4. Phone and email wait in notify_outbox: sent straight after the page
 *    has answered, retried by cron/planner.php when a send fails, and given
 *    up on after a few tries, with the reason kept.
 *
 * Every link, in every channel, is notifications/open/{id}: it marks the
 * alert read and opens the exact page, through sign-in first when needed.
 *
 * notify() never throws and never undoes the thing it reports: call it after
 * the work is saved (after commit), and a failure is only logged.
 */

/** Who can hear what. */
const NOTIFY_OWNER = ['owner'];
const NOTIFY_BOSS  = ['owner', 'admin'];
const NOTIFY_TEAM  = ['owner', 'admin', 'staff'];


/**
 * What a person chooses about, with the defaults. 'locked' emails cannot be
 * switched off. 'roles' decides who sees the switch at all.
 */
const NOTIFY_TOPICS = [
    'orders'    => ['label' => 'Shop orders & quote requests', 'hint' => 'New orders and quote requests from your shop.',             'push' => true, 'email' => true,  'locked' => false, 'roles' => NOTIFY_TEAM],
    'bookings'  => ['label' => 'Bookings',                     'hint' => 'New bookings from your shop.',                              'push' => true, 'email' => true,  'locked' => false, 'roles' => NOTIFY_TEAM],
    'money'     => ['label' => 'Money in',                     'hint' => 'Payments and sales, as they are recorded.',                 'push' => true, 'email' => false, 'locked' => false, 'roles' => NOTIFY_BOSS],
    'customers' => ['label' => 'Customer replies',             'hint' => 'Quotes accepted or declined, comments, signed deliveries, reviews.', 'push' => true, 'email' => false, 'locked' => false, 'roles' => NOTIFY_BOSS],
    'overdue'   => ['label' => 'Overdue & expired',            'hint' => 'Invoices past their due date, quotes nobody answered.',     'push' => true, 'email' => false, 'locked' => false, 'roles' => NOTIFY_BOSS],
    'stock'     => ['label' => 'Low stock',                    'hint' => 'Once a day, when something is running low.',               'push' => true, 'email' => false, 'locked' => false, 'roles' => NOTIFY_TEAM],
    'reminders' => ['label' => 'Task reminders',               'hint' => 'At the time you set on a task or appointment.',             'push' => true, 'email' => true,  'locked' => false, 'roles' => NOTIFY_TEAM],
    'morning'   => ['label' => 'Morning plan',                 'hint' => 'Your day at 7am, on days with something planned.',          'push' => true, 'email' => true,  'locked' => false, 'roles' => NOTIFY_TEAM],
    'tasks'     => ['label' => 'Tasks from your team',         'hint' => 'A task given to you or taken off you, or one you gave finished.', 'push' => true, 'email' => false, 'locked' => false, 'roles' => NOTIFY_TEAM],
    'billing'   => ['label' => 'Billing',                      'hint' => 'Your Nfelia plan: payments, renewals, the end of your trial.', 'push' => true, 'email' => true, 'locked' => true, 'roles' => NOTIFY_OWNER],
    'account'   => ['label' => 'Your shop & Nfelia',           'hint' => 'Shop link decisions, support replies, referral rewards.',   'push' => true, 'email' => true,  'locked' => false, 'roles' => NOTIFY_BOSS],
    'team'      => ['label' => 'Your team',                    'hint' => 'Someone joins or leaves your team.',                        'push' => true, 'email' => false, 'locked' => false, 'roles' => NOTIFY_BOSS],
    'security'  => ['label' => 'Sign-ins & security',          'hint' => 'A new device signs in, or your password, email or passkeys change.', 'push' => true, 'email' => true, 'locked' => true, 'roles' => NOTIFY_TEAM],
];

/** The person's own choices, as stored on users. */
const NOTIFY_FIELDS = ['quiet_hours', 'quiet_from', 'quiet_to', 'hide_amounts'];

/* ═══════════════════════════════════════════════════════════════════
 *  THE EVENTS
 * ═══════════════════════════════════════════════════════════════════
 * Each: topic, to (owner | boss | team | user), urgency, ttl
 * (seconds a push stays worth delivering), url, tag (a newer push with the
 * same tag replaces the older one on the phone), text (title and body;
 * $m formats money and is null when this person must not see amounts),
 * private (the phone title for people who hide amounts), and for email:
 * lines (label => value rows), button, template (default 'notification';
 * 'needs' names the vars a special template can't do without, else the
 * plain one is used) and brand ('business' or 'nfelia').
 */
function notify_events(): array
{
    static $events = null;
    if ($events !== null) return $events;

    $q   = fn($s, $n = 90) => '“' . notify_excerpt((string)$s, $n) . '”';
    $pl  = fn(int $n, string $one, string $many) => $n . ' ' . ($n === 1 ? $one : $many);
    $day = fn(?string $date) => $date ? date('j M Y', strtotime($date)) : '';

    $events = [
        /* ── Money in ─────────────────────────────────────────── */
        // An order's invoice opens its order, where the money is managed
        'payment.invoice' => [
            'topic' => 'money', 'to' => 'boss', 'urgency' => 'high',
            'url' => fn($d) => !empty($d['order_id']) ? 'orders/view/' . (int)$d['order_id'] . '#money' : 'invoices/view/' . (int)$d['invoice_id'],
            'tag' => fn($d) => 'pay-inv-' . (int)$d['invoice_id'],
            'text' => fn($d, $m) => [
                $m($d['amount']) . ' received · ' . $d['invoice_number'],
                trim(($d['customer'] ?: 'A customer') . ' paid' . (!empty($d['method']) ? ' by ' . $d['method'] : '')
                    . (!empty($d['order_number']) ? ' for order ' . $d['order_number'] : '') . '. '
                    . ((float)$d['balance'] > 0 ? $m($d['balance']) . ' still owed.' : 'Paid in full.')),
            ],
            'private' => 'Payment received',
            'lines' => fn($d, $m) => ['Invoice' => $d['invoice_number'], 'Order' => $d['order_number'] ?? '', 'Customer' => $d['customer'],
                                      'Amount' => $m($d['amount']), 'Still owed' => $m($d['balance'])],
            'button' => fn($d) => !empty($d['order_id']) ? 'Open the order' : 'Open the invoice',
        ],
        'payment.receipt' => [
            'topic' => 'money', 'to' => 'boss', 'urgency' => 'high',
            'url' => fn($d) => 'receipts/view/' . (int)$d['receipt_id'],
            'tag' => fn($d) => 'pay-rec-' . (int)$d['receipt_id'],
            'text' => fn($d, $m) => [
                $m($d['amount']) . ' received',
                !empty($d['payer']) ? 'Receipt ' . $d['receipt_number'] . ' for ' . $d['payer'] . '.' : 'A sale on the spot, receipt ' . $d['receipt_number'] . '.',
            ],
            'private' => 'Payment received',
            'button' => 'Open the receipt',
        ],
        'payment.order' => [
            'topic' => 'money', 'to' => 'boss', 'urgency' => 'high',
            'url' => fn($d) => 'orders/view/' . (int)$d['order_id'],
            'tag' => fn($d) => 'pay-ord-' . (int)$d['order_id'],
            'text' => fn($d, $m) => [
                $m($d['amount']) . ' received',
                'Shop order ' . $d['order_number'] . ' from ' . $d['customer'] . ', paid and approved.',
            ],
            'private' => 'Payment received',
            'button' => 'Open the order',
        ],
        'payment.debt' => [
            'topic' => 'money', 'to' => 'boss', 'urgency' => 'high',
            'url' => fn($d) => 'debts/view/' . (int)$d['debt_id'],
            'tag' => fn($d) => 'pay-debt-' . (int)$d['debt_id'],
            'text' => fn($d, $m) => [
                $m($d['amount']) . ' received',
                ($d['customer'] ?: 'A customer') . ' paid towards what they owe. '
                    . ((float)$d['balance'] > 0 ? $m($d['balance']) . ' still owed.' : 'They have paid it all.'),
            ],
            'private' => 'Payment received',
            'button' => 'Open the debt',
        ],
        // Money going back: the customer filled in where their refund should
        // go (the link from refund_ask() in app/helpers/orders.php)
        'refund.details' => [
            'topic' => 'money', 'to' => 'boss', 'urgency' => 'high',
            'url' => fn($d) => 'orders/view/' . (int)$d['order_id'],
            'tag' => fn($d) => 'refund-' . (int)$d['order_id'],
            'text' => fn($d, $m) => [
                'Refund details from ' . ($d['customer'] ?: 'your customer'),
                'They sent where to send ' . $m($d['amount']) . ' for order ' . $d['order_number'] . '. Pay it, then record the refund.',
            ],
            'private' => 'Refund details received',
            'lines' => fn($d, $m) => ['Order' => $d['order_number'], 'Customer' => $d['customer'], 'Amount' => $m($d['amount']),
                                      'Send to' => $d['payee'] ?? ''],
            'button' => 'Open the order',
        ],

        /* ── Shop orders, quote requests, bookings ────────────── */
        'order.new' => [
            'topic' => 'orders', 'to' => 'team', 'urgency' => 'high',
            'url' => fn($d) => 'orders/view/' . (int)$d['order_id'],
            'tag' => fn($d) => 'order-' . (int)$d['order_id'],
            'text' => fn($d, $m) => [
                $m ? 'New order: ' . $m($d['total']) : 'New order ' . $d['order_number'],
                ($d['channel'] === 'whatsapp'
                    ? $d['customer'] . ' sent an order on WhatsApp'
                    : $d['customer'] . ' ordered ' . $pl((int)$d['items'], 'item', 'items') . ' on your shop')
                . ($d['fulfillment'] === 'delivery' ? ' for delivery' : ' for pickup') . ' · ' . $d['order_number'],
            ],
            'private' => 'New shop order',
            'lines' => fn($d, $m) => [
                'Order' => $d['order_number'], 'Customer' => $d['customer'], 'Phone' => $d['phone'] ?? '',
                'Items' => (string)(int)$d['items'], 'Total' => $m ? $m($d['total']) : '',
                'Delivery' => $d['fulfillment'] === 'delivery' ? (($d['address'] ?? '') ?: 'Delivery') : 'Pickup',
                'Note' => $d['note'] ?? '',
            ],
            'button' => 'Open the order',
        ],
        'quote_request.new' => [
            'topic' => 'orders', 'to' => 'team', 'urgency' => 'high',
            'url' => fn($d) => 'quotations/requests/' . (int)$d['request_id'],
            'tag' => fn($d) => 'qreq-' . (int)$d['request_id'],
            'text' => fn($d, $m) => ['Quote request from ' . $d['name'], notify_excerpt($d['message'], 140)],
            'private' => 'New quote request',
            'lines' => fn($d, $m) => ['Name' => $d['name'], 'Phone' => $d['phone'], 'Email' => $d['email'] ?? '', 'Message' => $d['message']],
            'button' => 'Open the request',
        ],
        'booking.new' => [
            'topic' => 'bookings', 'to' => 'team', 'urgency' => 'high',
            'url' => fn($d) => 'bookings/view/' . (int)$d['booking_id'],
            'tag' => fn($d) => 'booking-' . (int)$d['booking_id'],
            'text' => fn($d, $m) => [
                'New booking: ' . $d['item'],
                $d['customer'] . ' booked for ' . $d['when'] . '.'
                    . ($m && (float)($d['deposit'] ?? 0) > 0 ? ' Deposit ' . $m($d['deposit']) . '.' : '') . ' · ' . $d['booking_number'],
            ],
            'private' => 'New booking',
            'lines' => fn($d, $m) => ['Booking' => $d['booking_number'], 'For' => $d['item'], 'When' => $d['when'], 'Customer' => $d['customer'],
                                      'Phone' => $d['phone'] ?? '', 'Deposit' => $m && (float)($d['deposit'] ?? 0) > 0 ? $m($d['deposit']) : ''],
            'button' => 'Open the booking',
        ],

        /* ── Customers acting on what you sent ────────────────── */
        // A signed quote becomes its order (order_from_quote()): that is where to go next
        'quote.accepted' => [
            'topic' => 'customers', 'to' => 'boss', 'urgency' => 'high',
            'url' => fn($d) => !empty($d['order_id']) ? 'orders/view/' . (int)$d['order_id'] : 'quotations/view/' . (int)$d['quotation_id'],
            'tag' => fn($d) => 'quote-' . (int)$d['quotation_id'],
            'text' => fn($d, $m) => ['Quote accepted: ' . $d['quotation_number'],
                                     $d['customer'] . (!empty($d['unsigned']) ? ' accepted it' : ' signed and accepted it') . ($m && isset($d['total']) ? ', ' . $m($d['total']) : '') . '.'
                                     . (!empty($d['order_number']) ? ' Order ' . $d['order_number'] . ' is made.' : '')],
            'private' => 'A quote was accepted',
            'button' => fn($d) => !empty($d['order_id']) ? 'Open the order' : 'Open the quote',
        ],
        'quote.declined' => [
            'topic' => 'customers', 'to' => 'boss',
            'url' => fn($d) => 'quotations/view/' . (int)$d['quotation_id'],
            'tag' => fn($d) => 'quote-' . (int)$d['quotation_id'],
            'text' => fn($d, $m) => ['Quote declined: ' . $d['quotation_number'],
                                     $d['customer'] . ' declined it.' . (!empty($d['reason']) ? ' ' . $q($d['reason']) : '')],
            'private' => 'A quote was declined',
            'button' => 'Open the quote',
        ],
        'quote.comment' => [
            'topic' => 'customers', 'to' => 'boss',
            'url' => fn($d) => 'quotations/view/' . (int)$d['quotation_id'],
            'tag' => fn($d) => 'quote-' . (int)$d['quotation_id'],
            'text' => fn($d, $m) => ['New comment on ' . $d['quotation_number'], $d['customer'] . ': ' . $q($d['comment'])],
            'private' => 'New comment on a quote',
            'button' => 'Open the quote',
        ],
        'delivery.signed' => [
            'topic' => 'customers', 'to' => 'boss',
            'url' => fn($d) => !empty($d['order_id']) ? 'orders/view/' . (int)$d['order_id'] . '#delivery' : 'deliveries/view/' . (int)$d['delivery_id'],
            'tag' => fn($d) => 'delivery-' . (int)$d['delivery_id'],
            // "unsigned": the receiver confirmed with a tap (a delivery note that isn't signed)
            'text' => fn($d, $m) => [(!empty($d['unsigned']) ? 'Delivery received: ' : 'Delivery signed: ') . $d['delivery_number'],
                                     (!empty($d['unsigned']) ? 'Received by ' : 'Received and signed by ') . $d['signer'] . (!empty($d['order_number']) ? ', order ' . $d['order_number'] : '') . '.'],
            'private' => 'A delivery was received',
            'button' => fn($d) => !empty($d['order_id']) ? 'Open the order' : 'Open the delivery',
        ],
        'review.new' => [
            'topic' => 'customers', 'to' => 'boss',
            'url' => fn($d) => 'orders/view/' . (int)$d['order_id'] . '#review',
            'tag' => fn($d) => 'review-' . (int)$d['order_id'],
            'text' => fn($d, $m) => ['New review: ' . (int)$d['rating'] . ' out of 5',
                                     $d['who'] . ' on order ' . $d['order_number'] . (!empty($d['comment']) ? ': ' . $q($d['comment']) : '.')],
            'private' => 'New review',
            'button' => 'Open the order',
        ],

        /* ── Overdue and expired ──────────────────────────────── */
        'invoice.overdue' => [
            'topic' => 'overdue', 'to' => 'boss',
            'url' => fn($d) => !empty($d['order_id']) ? 'orders/view/' . (int)$d['order_id'] . '#money' : 'invoices/view/' . (int)$d['invoice_id'],
            'tag' => fn($d) => 'overdue-' . (int)$d['invoice_id'],
            'text' => fn($d, $m) => ['Overdue: ' . $d['invoice_number'],
                                     ($d['customer'] ?: 'A customer') . ' owes ' . $m($d['balance'])
                                     . (!empty($d['order_number']) ? ' on order ' . $d['order_number'] : '')
                                     . ', ' . $pl((int)$d['days'], 'day', 'days') . ' past due.'],
            'private' => 'An invoice is overdue',
            'button' => fn($d) => !empty($d['order_id']) ? 'Open the order' : 'Open the invoice',
        ],
        'invoice.overdue_many' => [
            'topic' => 'overdue', 'to' => 'boss',
            'url' => fn($d) => 'invoices?status=overdue',
            'tag' => fn($d) => 'overdue-many',
            'text' => fn($d, $m) => [(int)$d['count'] . ' invoices are overdue', $m($d['total']) . ' to collect. Each one is in your notifications.'],
            'private' => 'Invoices are overdue',
            'button' => 'See overdue invoices',
        ],
        'quote.expired' => [
            'topic' => 'overdue', 'to' => 'boss',
            'url' => fn($d) => 'quotations/view/' . (int)$d['quotation_id'],
            'tag' => fn($d) => 'quote-' . (int)$d['quotation_id'],
            'text' => fn($d, $m) => ['Quote expired: ' . $d['quotation_number'],
                                     ($d['customer'] ?: 'The customer') . ' didn’t answer by ' . $day($d['expiry_date']) . '. Follow up, or send a new quote.'],
            'private' => 'A quote expired',
            'button' => 'Open the quote',
        ],

        /* ── Stock ─────────────────────────────────────────────── */
        'stock.low' => [
            'topic' => 'stock', 'to' => 'team',
            'url' => fn($d) => 'products?stock=restock',
            'tag' => fn($d) => 'low-stock',
            'text' => fn($d, $m) => [$d['title'], $d['body']],
            'private' => 'Stock is running low',
            'button' => 'See what to restock',
        ],

        /* ── Tasks and the day ────────────────────────────────── */
        'task.reminder' => [
            'topic' => 'reminders', 'to' => 'user', 'urgency' => 'high', 'ttl' => 3 * 3600,
            'url' => fn($d) => 'tasks?task=' . (int)$d['task_id'],
            'tag' => fn($d) => 'task-' . (int)$d['task_id'],
            'text' => fn($d, $m) => [$d['title'], $d['line']],
            'private' => 'Reminder',
            'template' => 'task_reminder', 'needs' => ['item', 'when'],
        ],
        'morning.plan' => [
            'topic' => 'morning', 'to' => 'user', 'ttl' => 6 * 3600,
            'url' => fn($d) => 'tasks',
            'tag' => fn($d) => 'morning',
            'text' => fn($d, $m) => [$d['title'], $d['line']],
            'private' => 'Your plan for today',
            'template' => 'morning_plan', 'needs' => ['first_name', 'day', 'summary', 'today'],
        ],
        'task.assigned' => [
            'topic' => 'tasks', 'to' => 'user',
            'url' => fn($d) => 'tasks?task=' . (int)$d['task_id'],
            'tag' => fn($d) => 'task-' . (int)$d['task_id'],
            'text' => fn($d, $m) => ['New task for you', $d['from'] . ' gave you: ' . $d['title'] . (!empty($d['when']) ? ' · ' . $d['when'] : '')],
            'private' => 'New task for you',
            'button' => 'Open the task',
        ],
        'task.done' => [
            'topic' => 'tasks', 'to' => 'user',
            'url' => fn($d) => 'tasks?task=' . (int)$d['task_id'],
            'tag' => fn($d) => 'task-' . (int)$d['task_id'],
            'text' => fn($d, $m) => ['Done: ' . $d['title'], $d['by'] . ' finished the task you gave them.'],
            'private' => 'A task was finished',
            'button' => 'Open the task',
        ],
        'task.unassigned' => [
            'topic' => 'tasks', 'to' => 'user',
            'url' => fn($d) => 'tasks',
            'tag' => fn($d) => 'task-' . (int)$d['task_id'],
            'text' => fn($d, $m) => ['Task moved: ' . $d['title'],
                                     !empty($d['to_name']) ? $d['by'] . ' gave it to ' . $d['to_name'] . ' instead.' : $d['by'] . ' took it off your list.'],
            'private' => 'A task was moved',
            'button' => 'Open my tasks',
        ],

        /* ── Billing (the business's own Nfelia plan) ─────────── */
        'billing.paid' => [
            'topic' => 'billing', 'to' => 'owner', 'brand' => 'nfelia',
            'url' => fn($d) => 'settings?tab=billing', 'tag' => fn($d) => 'billing',
            'text' => fn($d, $m) => ['Subscription paid',
                                     (!empty($d['amount_text']) ? $d['amount_text'] . ' received. ' : '') . (!empty($d['period_end']) ? 'You’re covered until ' . $day($d['period_end']) . '.' : 'Thank you.')],
            'private' => 'Subscription paid',
            'button' => 'Open billing',
        ],
        'billing.failed' => [
            'topic' => 'billing', 'to' => 'owner', 'brand' => 'nfelia', 'urgency' => 'high',
            'url' => fn($d) => 'settings?tab=billing', 'tag' => fn($d) => 'billing',
            'text' => fn($d, $m) => ['Your payment didn’t go through',
                                     'We couldn’t take your Nfelia payment. Pay within 3 days to keep your account open.'],
            'private' => 'A payment didn’t go through',
            'button' => 'Pay now',
        ],
        'billing.cancelled' => [
            'topic' => 'billing', 'to' => 'owner', 'brand' => 'nfelia',
            'url' => fn($d) => 'settings?tab=billing', 'tag' => fn($d) => 'billing',
            'text' => fn($d, $m) => ['Subscription cancelled',
                                     !empty($d['access_until']) ? 'You keep full access until ' . $day($d['access_until']) . '. Resubscribe any time.' : 'Resubscribe any time to carry on.'],
            'private' => 'Subscription cancelled',
            'button' => 'Open billing',
        ],
        'billing.due' => [
            'topic' => 'billing', 'to' => 'owner', 'brand' => 'nfelia', 'urgency' => 'high',
            'url' => fn($d) => 'settings?tab=billing', 'tag' => fn($d) => 'billing',
            'text' => fn($d, $m) => ['Your paid month has ended',
                                     'It ended on ' . $day($d['period_end']) . '. Pay by ' . $day($d['grace_ends']) . ' to keep Nfelia and your website open.'],
            'private' => 'Paid month ended',
            'button' => 'Pay now',
        ],
        'billing.ended' => [
            'topic' => 'billing', 'to' => 'owner', 'brand' => 'nfelia', 'urgency' => 'high',
            'url' => fn($d) => 'settings?tab=billing', 'tag' => fn($d) => 'billing',
            'text' => fn($d, $m) => ['Nfelia is paused',
                                     'Pay for a month to open Nfelia and your website again. Everything you added is still here.'],
            'private' => 'Nfelia is paused',
            'button' => 'Pay now',
        ],
        'billing.renewal_soon' => [
            'topic' => 'billing', 'to' => 'owner', 'brand' => 'nfelia',
            'url' => fn($d) => 'settings?tab=billing', 'tag' => fn($d) => 'billing',
            'text' => fn($d, $m) => ['Your paid month ends ' . notify_when_text((int)$d['days']),
                                     'Pay for the next month in Billing to keep Nfelia and your website open.'],
            'private' => 'Paid month ending soon',
            'button' => 'Pay now',
        ],
        'billing.trial_ending' => [
            'topic' => 'billing', 'to' => 'owner', 'brand' => 'nfelia',
            'url' => fn($d) => 'settings?tab=billing', 'tag' => fn($d) => 'billing',
            'text' => fn($d, $m) => ['Your free trial ends ' . notify_when_text((int)$d['days']),
                                     'Pay for a month before ' . $day($d['trial_ends']) . ' to keep using Nfelia.'],
            'private' => 'Free trial ending soon',
            'button' => 'Choose a plan',
        ],
        'billing.trial_ended' => [
            'topic' => 'billing', 'to' => 'owner', 'brand' => 'nfelia', 'urgency' => 'high',
            'url' => fn($d) => 'settings?tab=billing', 'tag' => fn($d) => 'billing',
            'text' => fn($d, $m) => ['Your free trial has ended', 'Pay for a month to carry on. Everything you added is still here.'],
            'private' => 'Free trial ended',
            'button' => 'Choose a plan',
        ],
        'billing.momo_submitted' => [
            'topic' => 'billing', 'to' => 'owner', 'brand' => 'nfelia',
            'url' => fn($d) => 'settings?tab=billing', 'tag' => fn($d) => 'billing',
            'text' => fn($d, $m) => ['Payment received for checking',
                                     'We got your ' . $d['amount_text'] . ' mobile money payment and will check it within 24 hours. We’ll tell you as soon as it’s approved.'],
            'private' => 'Payment received for checking',
            'button' => 'Open billing',
        ],
        'billing.momo_rejected' => [
            'topic' => 'billing', 'to' => 'owner', 'brand' => 'nfelia', 'urgency' => 'high',
            'url' => fn($d) => 'momo/checkout', 'tag' => fn($d) => 'billing',
            'text' => fn($d, $m) => ['We couldn’t confirm your payment',
                                     'Your mobile money payment wasn’t approved' . (!empty($d['reason']) ? ': ' . rtrim(notify_excerpt($d['reason'], 160), '.') . '.' : '.')
                                        . ' Send it again with a receipt that shows your name or number, the exact amount and the reference.'],
            'private' => 'Payment not approved',
            'button' => 'Send it again',
        ],
        'billing.activated' => [
            'topic' => 'billing', 'to' => 'owner', 'brand' => 'nfelia',
            'url' => fn($d) => 'settings?tab=billing', 'tag' => fn($d) => 'billing',
            'text' => fn($d, $m) => ['Subscription active',
                                     'The Nfelia team activated your subscription' . (!empty($d['period_end']) ? ' until ' . $day($d['period_end']) : '') . '.'],
            'private' => 'Subscription active',
            'button' => 'Open billing',
        ],
        'billing.renewals_stopped' => [
            'topic' => 'billing', 'to' => 'owner', 'brand' => 'nfelia',
            'url' => fn($d) => 'settings?tab=billing', 'tag' => fn($d) => 'billing',
            'text' => fn($d, $m) => ['Monthly payments have changed',
                                     'Your card won’t be charged automatically any more. Each month, pay in Billing and choose which staff the month covers.'],
            'private' => 'Monthly payments have changed',
            'button' => 'Open billing',
        ],
        'billing.suspended' => [
            'topic' => 'billing', 'to' => 'owner', 'brand' => 'nfelia', 'urgency' => 'high',
            'url' => fn($d) => 'settings?tab=billing', 'tag' => fn($d) => 'billing',
            'text' => fn($d, $m) => ['Your account is on hold', 'Open billing to restore it, or contact Nfelia support.'],
            'private' => 'Account on hold',
            'button' => 'Open billing',
        ],
        'billing.trial_extended' => [
            'topic' => 'billing', 'to' => 'owner', 'brand' => 'nfelia',
            'url' => fn($d) => 'settings?tab=billing', 'tag' => fn($d) => 'billing',
            'text' => fn($d, $m) => ['Free trial extended',
                                     'We added ' . $pl((int)$d['days'], 'day', 'days') . '. Your trial now ends ' . $day($d['trial_ends']) . '.'],
            'private' => 'Free trial extended',
            'button' => 'Open billing',
        ],

        'billing.price_change' => [
            'topic' => 'billing', 'to' => 'owner', 'brand' => 'nfelia',
            'url' => fn($d) => 'settings?tab=billing', 'tag' => fn($d) => 'billing',
            'text' => fn($d, $m) => [!empty($d['up']) ? 'Your Nfelia price changes on ' . $day($d['from']) : 'Nfelia costs less from ' . $day($d['from']),
                                     implode('. ', array_map(fn($l) => $l['what'] . ': ' . $l['old'] . ' → ' . $l['new'], (array)($d['lines'] ?? []))) . '.'
                                     . (!empty($d['up']) ? ' Months that start before then stay at today\'s price.' : '')],
            'private' => 'News about your Nfelia price',
            'button' => 'Open billing',
        ],

        /* ── Your records (the Records Book) ──────────────────── */
        'records.paid' => [
            'topic' => 'billing', 'to' => 'owner', 'brand' => 'nfelia',
            'url' => fn($d) => 'records', 'tag' => fn($d) => 'records',
            'text' => fn($d, $m) => ['Payment received for your Records Book', (!empty($d['amount_text']) ? $d['amount_text'] . ' received. ' : '') . 'We are making it now, and will tell you when it is ready.'],
            'private' => 'Payment received',
            'button' => 'Open Your records',
        ],
        'records.ready' => [
            'topic' => 'account', 'to' => 'owner', 'brand' => 'nfelia',
            'url' => fn($d) => 'records', 'tag' => fn($d) => 'records-' . (int)$d['book_id'],
            'text' => fn($d, $m) => ['Your Records Book is ready', 'All your records in one Excel workbook, in your colours, with a code anyone can check. Download it from Your records.'],
            'private' => 'Your Records Book is ready',
            'button' => 'Download it',
        ],
        'records.raw_ready' => [
            'topic' => 'account', 'to' => 'owner', 'brand' => 'nfelia',
            'url' => fn($d) => 'records', 'tag' => fn($d) => 'records-' . (int)$d['book_id'],
            'text' => fn($d, $m) => ['Your plain copy is ready', 'Your records as CSV files in a ZIP. Download it from Your records within ' . (defined('RECORDS_RAW_DAYS') ? RECORDS_RAW_DAYS : 7) . ' days.'],
            'private' => 'Your plain copy is ready',
            'button' => 'Download it',
        ],
        'records.failed' => [
            'topic' => 'account', 'to' => 'owner', 'brand' => 'nfelia',
            'url' => fn($d) => 'records', 'tag' => fn($d) => 'records-' . (int)$d['book_id'],
            'text' => fn($d, $m) => ['Your Records Book needs another try', !empty($d['big'])
                ? 'You have a lot of records! Choose a shorter period (a year, say) and make it again. Nothing was charged for this one.'
                : 'Something went wrong while we made it. The Nfelia team has been told and is on it.'],
            'private' => 'Your Records Book needs another try',
            'button' => 'Open Your records',
        ],
        'records.team_built' => [
            'topic' => 'security', 'to' => 'owner', 'brand' => 'nfelia', 'urgency' => 'high',
            'url' => fn($d) => 'records', 'tag' => fn($d) => 'records-' . (int)$d['book_id'],
            'text' => fn($d, $m) => ['Nfelia made a Records Book of your records',
                                     $d['staff'] . ' from Nfelia made it: ' . notify_excerpt($d['reason'], 160) . '.'
                                     . (!empty($d['sent']) ? ' It is in Your records for you to download.' : ' Questions? Reply to support.')],
            'private' => 'Nfelia made a Records Book of your records',
            'button' => 'Open Your records',
        ],
        'records.request' => [
            'topic' => 'security', 'to' => 'owner', 'brand' => 'nfelia', 'urgency' => 'high',
            'url' => fn($d) => 'records/request/' . (int)$d['request_id'], 'tag' => fn($d) => 'record-request-' . (int)$d['request_id'],
            'text' => fn($d, $m) => [$d['who'] . ' asked for your records',
                                     'They want ' . $d['what'] . ': ' . notify_excerpt($d['reason'], 160) . '. Nothing is shared unless you say yes.'],
            'private' => 'Someone asked for your records',
            'lines' => fn($d, $m) => ['Who' => $d['who'], 'They are' => $d['role'], 'Their email' => $d['email'], 'They want' => $d['what'], 'Why' => $d['reason']],
            'button' => 'Approve or decline',
        ],

        /* ── Your shop and Nfelia ─────────────────────────────── */
        'shop.link_live' => [
            'topic' => 'account', 'to' => 'boss', 'brand' => 'nfelia',
            'url' => fn($d) => 'landing-page', 'tag' => fn($d) => 'shop-link',
            'text' => fn($d, $m) => ['Your shop link is live',
                                     $d['host'] . ' is approved. ' . (!empty($d['changed'])
                                        ? 'Your old link sends people to it for the next 90 days, so update it wherever you’ve shared it.'
                                        : 'Share it with your customers.')],
            'private' => 'Your shop link is live',
            'button' => 'Open your shop',
        ],
        'shop.link_refused' => [
            'topic' => 'account', 'to' => 'owner', 'brand' => 'nfelia',
            'url' => fn($d) => !empty($d['has_current']) ? 'landing-page' : 'shop-setup', 'tag' => fn($d) => 'shop-link',
            'text' => fn($d, $m) => [!empty($d['has_current']) ? 'Your shop link stays the same' : 'Please choose another shop name',
                                     'We couldn’t approve ' . $d['host'] . '. ' . (!empty($d['has_current']) ? 'Your shop keeps its current link.' : 'Choose another name to open your shop.')],
            'private' => 'News about your shop link',
            'button' => 'Open your shop',
        ],
        'support.reply' => [
            'topic' => 'account', 'to' => 'user', 'brand' => 'nfelia',
            'url' => fn($d) => 'contact-support#ticket-' . (int)$d['ticket_id'], 'tag' => fn($d) => 'ticket-' . (int)$d['ticket_id'],
            'text' => fn($d, $m) => ['Nfelia support replied', 'About ' . $q($d['subject'], 60) . ': ' . notify_excerpt($d['reply'], 140)],
            'private' => 'Nfelia support replied',
            'lines' => fn($d, $m) => ['Your message' => $d['subject'], 'Our reply' => $d['reply']],
            'button' => 'Read the reply',
        ],
        'referral.rewarded' => [
            'topic' => 'account', 'to' => 'owner', 'brand' => 'nfelia',
            'url' => fn($d) => 'referrals', 'tag' => fn($d) => 'referral',
            'text' => fn($d, $m) => ['You earned a referral reward',
                                     $d['referred'] . ' joined and paid. You get ' . $m($d['reward']) . ' off your next bill.'],
            'private' => 'You earned a referral reward',
            'button' => 'See your referrals',
        ],

        /* ── Your team ─────────────────────────────────────────── */
        'team.joined' => [
            'topic' => 'team', 'to' => 'boss',
            'url' => fn($d) => 'settings?tab=staff', 'tag' => fn($d) => 'team',
            'text' => fn($d, $m) => [$d['name'] . ' joined your team', 'Added as ' . $d['role'] . ' by ' . $d['by'] . '.'],
            'private' => 'Someone joined your team',
        ],
        'team.removed' => [
            'topic' => 'team', 'to' => 'boss',
            'url' => fn($d) => 'settings?tab=staff', 'tag' => fn($d) => 'team',
            'text' => fn($d, $m) => [$d['name'] . ' left your team', 'Removed by ' . $d['by'] . '. They can no longer sign in.'],
            'private' => 'Someone left your team',
        ],
        // The mail server said an address doesn't exist (app/helpers/bounces.php)
        'email.bounced' => [
            'topic' => 'team', 'to' => 'owner',
            'url' => fn($d) => !empty($d['self']) ? 'settings?tab=profile' : 'settings?tab=staff',
            'tag' => fn($d) => 'bounce-user-' . (int)$d['user_id'],
            'text' => fn($d, $m) => ['Emails to ' . $d['name'] . ' don’t arrive',
                                     'The mail server says ' . $d['email'] . ' doesn’t exist. Change it in Settings, under '
                                     . (!empty($d['self']) ? 'Profile.' : 'Team.')],
            'private' => 'An email address doesn’t work',
        ],
        'email.bounced_shopper' => [
            'topic' => 'customers', 'to' => 'owner',
            'url' => fn($d) => $d['kind'] === 'booking' ? 'bookings/view/' . (int)$d['booking_id'] . '#email' : 'orders/view/' . (int)$d['order_id'] . '#email',
            'tag' => fn($d) => 'bounce-' . $d['kind'] . '-' . (int)$d[$d['kind'] . '_id'],
            'text' => fn($d, $m) => ['Ask ' . $d['customer'] . ' for their email',
                                     'Emails about ' . $d['kind'] . ' ' . $d['number'] . ' don’t arrive: the mail server says '
                                     . $d['email'] . ' doesn’t exist. Ask them on WhatsApp.'],
            'private' => 'A customer’s email doesn’t work',
            'button' => fn($d) => $d['kind'] === 'booking' ? 'Open the booking' : 'Open the order',
        ],

        /* ── Sign-ins and security (always by email too) ──────── */
        'security.new_device' => [
            'topic' => 'security', 'to' => 'user', 'brand' => 'nfelia', 'urgency' => 'high',
            'url' => fn($d) => 'settings?tab=profile', 'tag' => fn($d) => 'security',
            'text' => fn($d, $m) => ['New sign-in to your account',
                                     $d['device'] . ', ' . $d['when'] . '. If this wasn’t you, change your password now.'],
            'private' => 'New sign-in to your account',
            'button' => 'Check your account',
        ],
        'security.view_as' => [
            'topic' => 'security', 'to' => 'user', 'brand' => 'nfelia', 'urgency' => 'high',
            'url' => fn($d) => 'settings?tab=profile', 'tag' => fn($d) => 'security',
            'text' => fn($d, $m) => ['Nfelia support looked at your account',
                                     $d['staff'] . ' from Nfelia opened your account to see what you see, read-only, for 30 minutes: ' . notify_excerpt($d['reason'], 160)
                                     . '. Nothing was changed. Questions? Reply to support.'],
            'private' => 'Nfelia support looked at your account',
            'button' => 'Open Nfelia',
        ],
        'security.password_changed' => [
            'topic' => 'security', 'to' => 'user', 'brand' => 'nfelia', 'urgency' => 'high',
            'url' => fn($d) => 'settings?tab=profile', 'tag' => fn($d) => 'security',
            'text' => fn($d, $m) => ['Your password was changed', 'If you didn’t do this, reset your password now from the sign-in page.'],
            'private' => 'Your password was changed',
            'button' => 'Check your account',
        ],
        'security.email_changed' => [
            'topic' => 'security', 'to' => 'user', 'brand' => 'nfelia', 'urgency' => 'high',
            'url' => fn($d) => 'settings?tab=profile', 'tag' => fn($d) => 'security',
            'text' => fn($d, $m) => ['Your sign-in email changed', 'It is now ' . $d['new_email'] . '. If you didn’t do this, contact Nfelia support now.'],
            'private' => 'Your sign-in email changed',
            'button' => 'Check your account',
        ],
        'security.passkey_added' => [
            'topic' => 'security', 'to' => 'user', 'brand' => 'nfelia',
            'url' => fn($d) => 'settings?tab=profile', 'tag' => fn($d) => 'security',
            'text' => fn($d, $m) => ['Passkey added', 'A passkey on ' . $d['device'] . ' can now sign in to your account. If this wasn’t you, remove it now.'],
            'private' => 'Passkey added',
            'button' => 'Check your account',
        ],
        'security.role_changed' => [
            'topic' => 'security', 'to' => 'user', 'brand' => 'nfelia',
            'url' => fn($d) => 'dashboard', 'tag' => fn($d) => 'security',
            'text' => fn($d, $m) => ['Your role changed', $d['by'] . ' made you ' . $d['role'] . ' at ' . $d['business'] . '.'],
            'private' => 'Your role changed',
        ],

        /* ── A phone that stopped getting alerts ──────────────── */
        'push.lost' => [
            'topic' => 'account', 'to' => 'user', 'brand' => 'nfelia',
            'url' => fn($d) => 'more#notifications', 'tag' => fn($d) => 'push-lost',
            'text' => fn($d, $m) => ['Phone alerts stopped on ' . $d['device'],
                                     'That phone no longer gets Nfelia alerts (the browser dropped them). Open Nfelia on it: alerts come back on by themselves, or tap Turn on in More › Notifications.'],
            'private' => 'Phone alerts stopped',
            'button' => 'Open notification settings',
        ],

        /* ── Checking it works (More > Notifications) ─────────── */
        'system.test' => [
            'topic' => null, 'to' => 'user', 'urgency' => 'high', 'ttl' => 600,
            'url' => fn($d) => 'notifications', 'tag' => fn($d) => 'nfelia-test',
            'text' => fn($d, $m) => ['Notifications work', 'This is how Nfelia tells you about orders, payments and reminders.'],
            'private' => 'Notifications work',
            'button' => 'Open Nfelia',
        ],
    ];
    return $events;
}

/* ═══════════════════════════════════════════════════════════════════
 *  SENDING
 * ═══════════════════════════════════════════════════════════════════ */

/**
 * Tell people something happened. Returns the bell rows made (one per
 * person; none when nobody should hear, or they already heard).
 *
 * $opts:
 *   actor     the person who did it: they already know, so they are left out
 *   self      true to tell the actor too (confirmations, tests)
 *   users     who, for events that go to named people ('to' => 'user')
 *   dedupe    a key for this happening ("order:12"): nobody hears it twice
 *   business  the business row, when the caller has it (currency, branding)
 *   vars      extra variables for the event's email template
 *   channels  only these channels this time (['push'] for a test)
 *   loud      true to ignore quiet hours (a test the person asked for)
 */
function notify(string $event, int $business_id, array $data = [], array $opts = []): array
{
    try {
        $def = notify_events()[$event] ?? null;
        if (!$def) {
            throw new InvalidArgumentException("unknown event {$event}");
        }
        $db = get_db();
        $to = $def['to'];
        $business = $opts['business'] ?? null;
        if (!$business || (int)($business['id'] ?? 0) !== $business_id) {
            $stmt = $db->prepare("SELECT id, name, currency_symbol, logo_path, primary_color, tagline, email FROM businesses WHERE id = ?");
            $stmt->execute([$business_id]);
            $business = $stmt->fetch() ?: null;
        }
        if (!$business) {
            return [];
        }

        $people = notify_recipients($db, $business_id, $to, $opts['users'] ?? []);
        $actor  = isset($opts['actor']) ? (int)$opts['actor'] : 0;
        if ($actor && empty($opts['self'])) {
            $people = array_filter($people, fn($p) => (int)$p['id'] !== $actor);
        }
        if (!$people) {
            return [];
        }

        $prefs    = notify_prefs_for($db, array_column($people, 'id'));
        $url      = notify_safe_path(($def['url'])($data));
        $tag      = isset($def['tag']) ? ($def['tag'])($data) : $event;
        $topic    = $def['topic'];
        $channels = $opts['channels'] ?? ['push', 'email'];
        $symbol   = (string)($business['currency_symbol'] ?? '₦');
        $money    = fn($amount) => notify_money_text((float)$amount, $symbol);
        $dedupe   = isset($opts['dedupe']) ? mb_substr($event . ':' . $opts['dedupe'], 0, 150) : null;
        $insert   = $db->prepare("
            INSERT IGNORE INTO notifications (business_id, user_id, type, event, title, message, action_url, data, dedupe_key)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $made = [];
        $trouble = null;   // one person's failure never silences the others

        foreach ($people as $person) {
          try {
            $uid  = (int)$person['id'];
            $sees = in_array($person['role'], NOTIFY_BOSS, true);
            [$title, $body] = ($def['text'])($data, $sees ? $money : null);
            $title = mb_substr(trim($title), 0, 255);
            $body  = trim($body);

            $insert->execute([$business_id, $uid, mb_substr($event, 0, 50), $event, $title, $body, $url ?: null,
                              json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $dedupe]);
            if ($insert->rowCount() !== 1) {
                continue;   // this person already heard about it
            }
            $nid = (int)$db->lastInsertId();
            $made[] = $nid;
            $open = 'notifications/open/' . $nid;
            $want = $topic === null ? ['push' => true, 'email' => true] : $prefs[$uid][$topic] ?? notify_topic_default($topic);

            if (in_array('push', $channels, true) && !$want['push'] && push_enabled()) {
                // Off for this kind, by their choice: written down as skipped,
                // so "why didn't my phone buzz?" (More > Notifications) can say so.
                notify_queue($db, [
                    'business_id' => $business_id, 'notification_id' => $nid, 'user_id' => $uid, 'event' => $event, 'channel' => 'push',
                    'payload' => ['title' => $title], 'urgency' => $def['urgency'] ?? 'normal', 'ttl' => $def['ttl'] ?? 86400,
                    'status' => 'skipped', 'error' => 'phone alerts for ' . strtolower((string)(NOTIFY_TOPICS[$topic]['label'] ?? $topic)) . ' are off',
                ]);
            }
            if (in_array('push', $channels, true) && $want['push'] && push_enabled()) {
                $hide = !empty($person['hide_amounts']);
                $message = [
                    'title' => $hide ? $def['private'] : $title,
                    'body'  => $hide ? 'Tap to see it in Nfelia.' : notify_excerpt($body, 240),
                    'url'   => $open,
                    'tag'   => $tag,
                    'id'    => $nid,
                ];
                // In their quiet hours the phone stays silent now, and buzzes
                // once when they end, unless by then it would be too late to
                // be useful (a reminder is for a moment).
                $ttl  = (int)($def['ttl'] ?? 86400);
                $hold = empty($opts['loud']) ? notify_quiet_until($person) : null;
                $late = $hold !== null && $hold->getTimestamp() >= planner_now()->getTimestamp() + $ttl;
                notify_queue($db, [
                    'business_id' => $business_id, 'notification_id' => $nid, 'user_id' => $uid, 'event' => $event,
                    'channel' => 'push', 'payload' => $message, 'urgency' => $def['urgency'] ?? 'normal',
                    'ttl' => $ttl,
                    'status' => $late ? 'skipped' : 'pending',
                    'error'  => $late ? 'quiet hours' : ($hold ? 'held for quiet hours' : null),
                    'next_attempt_at' => $hold && !$late ? $hold->format('Y-m-d H:i:s') : null,
                ]);
            }

            if (in_array('email', $channels, true) && $want['email'] && filter_var($person['email'], FILTER_VALIDATE_EMAIL)) {
                $lines = isset($def['lines']) ? array_filter(($def['lines'])($data, $sees ? $money : null), fn($v) => $v !== null && $v !== '') : [];
                $template = $def['template'] ?? 'notification';
                if (array_diff($def['needs'] ?? [], array_keys($opts['vars'] ?? []))) {
                    $template = 'notification';
                }
                // Queued after this person's push: notify_flush() sends every
                // phone before any email, so no phone waits on a mail server.
                $wait = (int)($opts['email_after'] ?? 0);
                notify_queue($db, [
                    'business_id' => $business_id, 'notification_id' => $nid, 'user_id' => $uid, 'event' => $event,
                    'channel' => 'email', 'recipient' => $person['email'], 'recipient_name' => $person['full_name'],
                    'next_attempt_at' => $wait > 0 ? planner_now()->modify('+' . $wait . ' seconds')->format('Y-m-d H:i:s') : null,
                    'error' => $wait > 0 ? 'waiting ' . intdiv($wait, 3600) . ' h' : null,
                    'payload' => [
                        'subject'  => $title,
                        'template' => $template,
                        'brand'    => ($def['brand'] ?? 'business') === 'nfelia' ? null : $business_id,
                        'vars'     => array_merge([
                            'subject'      => $title,
                            'headline'     => $title,
                            'message'      => $body,
                            'lines'        => $lines,
                            'button'       => ($def['button'] ?? null) instanceof Closure ? ($def['button'])($data) : ($def['button'] ?? 'Open in Nfelia'),
                            'open_url'     => APP_URL . '/' . $open,
                            'prefs_url'    => APP_URL . '/more#notifications',
                            'topic_label'  => $topic ? NOTIFY_TOPICS[$topic]['label'] : null,
                            'always'       => $topic === null || !empty(NOTIFY_TOPICS[$topic]['locked']),
                        ], $opts['vars'] ?? []),
                    ],
                ]);
            }
          } catch (Throwable $e) {
            $trouble = $e;
          }
        }
        if ($trouble) {
            notify_trouble($event, $trouble);
        }
        return $made;
    } catch (Throwable $e) {
        notify_trouble($event, $e);
        return [];
    }
}

/** A failure inside notify(): logged, and written down for the Nfelia team when the error log is on. */
function notify_trouble(string $event, Throwable $e): void
{
    error_log("Nfelia notify {$event}: " . $e->getMessage());
    if (function_exists('errors_report_throwable')) {
        try { errors_report_throwable($e, ['job' => 'notify ' . $event]); } catch (Throwable $ignored) {}
    }
}

/**
 * An email to someone who isn't signed up (a shopper, a customer, an old
 * address): no bell row, the same outbox and retries. $dedupe keeps the
 * same email from going twice. $business_id brands it, or null for Nfelia.
 */
function notify_email(?int $business_id, string $to, string $name, string $subject, string $template, array $vars, ?string $dedupe = null, string $event = 'email', array $headers = []): ?int
{
    try {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return null;
        }
        return notify_queue(get_db(), [
            'business_id' => $business_id, 'event' => $event, 'channel' => 'email',
            'recipient' => $to, 'recipient_name' => $name, 'dedupe_key' => $dedupe,
            'payload' => ['subject' => $subject, 'template' => $template, 'brand' => $business_id, 'vars' => $vars + ['subject' => $subject]]
                       + ($headers ? ['headers' => $headers] : []),
        ]);
    } catch (Throwable $e) {
        error_log("Nfelia notify_email {$template}: " . $e->getMessage());
        return null;
    }
}

/**
 * More > Notifications, "Send a test": one push or email to yourself, sent
 * now so the answer says what happened. Returns [ok, what to tell them].
 */
function notify_test(int $business_id, int $user_id, string $channel): array
{
    $ids = notify('system.test', $business_id, [], ['users' => [$user_id], 'actor' => $user_id, 'self' => true, 'loud' => true, 'channels' => [$channel]]);
    if (!$ids) {
        return [false, 'The test could not be made. Try again.'];
    }
    $db = get_db();
    $stmt = $db->prepare("SELECT id, recipient FROM notify_outbox WHERE notification_id = ? AND channel = ?");
    $stmt->execute([$ids[0], $channel]);
    $row = $stmt->fetch();
    if (!$row) {
        return [false, $channel === 'push' ? 'Phone alerts are not switched on for Nfelia yet.' : 'Your account has no email address.'];
    }
    notify_flush([(int)$row['id']]);
    $stmt = $db->prepare("SELECT status, last_error FROM notify_outbox WHERE id = ?");
    $stmt->execute([$row['id']]);
    $sent = $stmt->fetch();
    if ($sent['status'] === 'sent') {
        return [true, $channel === 'push' ? 'Sent. Check your phone.' : 'Sent to ' . $row['recipient'] . '. Check your inbox.'];
    }
    if ($channel === 'push' && $sent['last_error'] === 'no phone has notifications on') {
        return [false, 'No phone has alerts on yet. Tap Turn on, on the phone you want them on.'];
    }
    return [false, $sent['status'] === 'pending'
        ? 'Not delivered yet (' . $sent['last_error'] . '). Nfelia will try again in a few minutes.'
        : 'Not delivered: ' . $sent['last_error'] . '.'];
}

/** Put one push or email in the outbox, to go after the page has answered. */
function notify_queue(PDO $db, array $row): ?int
{
    $status = $row['status'] ?? 'pending';
    $ttl    = (int)($row['ttl'] ?? 0);
    $later  = !empty($row['next_attempt_at']) ? (string)$row['next_attempt_at'] : null;   // Lagos time, the database's own
    $db->prepare("
        INSERT IGNORE INTO notify_outbox
            (business_id, notification_id, user_id, staff_id, event, channel, recipient, recipient_name, dedupe_key,
             payload, urgency, status, last_error, expires_at, next_attempt_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, " . ($ttl > 0 ? "NOW() + INTERVAL {$ttl} SECOND" : 'NULL') . ", " . ($later ? '?' : 'NOW()') . ")
    ")->execute(array_merge([
        $row['business_id'] ?? null, $row['notification_id'] ?? null, $row['user_id'] ?? null, $row['staff_id'] ?? null, $row['event'],
        $row['channel'], $row['recipient'] ?? null, $row['recipient_name'] ?? null,
        isset($row['dedupe_key']) ? mb_substr($row['dedupe_key'], 0, 150) : null,
        json_encode($row['payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        $row['urgency'] ?? 'normal', $status, $row['error'] ?? null,
    ], $later ? [$later] : []));
    $id = (int)$db->lastInsertId();
    if ($id && $status === 'pending' && !$later) {
        notify_flush_later($id);
    }
    return $id ?: null;
}

/** Send what this request queued once the page has answered (cron sends it otherwise). */
function notify_flush_later(int $id): void
{
    static $ids = null;
    if (PHP_SAPI === 'cli' || !function_exists('after_response')) {
        return;
    }
    if ($ids === null) {
        $ids = [];
        after_response(function () use (&$ids) { notify_flush($ids); });
    }
    $ids[] = $id;
}

/** How long to wait before trying again, by attempts made so far. */
const NOTIFY_RETRY_MINUTES = [1 => 2, 2 => 10, 3 => 30, 4 => 120, 5 => 360];
/** Tries before giving up. A push with time left (expires_at) goes on past its number, up to NOTIFY_PUSH_MOST. */
const NOTIFY_MAX_ATTEMPTS  = ['push' => 4, 'email' => 6];
const NOTIFY_PUSH_MOST     = 10;

/**
 * Send waiting pushes and emails: these ids, or everything due. Each row is
 * claimed first, so the page's own send and the cron never both send it.
 * Returns counts by outcome.
 */
function notify_flush(?array $ids = null, int $limit = 200, int $seconds = 240): array
{
    $done = ['sent' => 0, 'retry' => 0, 'failed' => 0, 'skipped' => 0];
    $until = time() + max(10, $seconds);   // the rest waits for the next run, not for a slow mail server
    try {
        $db = get_db();
        if ($ids === null) {
            $stmt = $db->prepare("
                SELECT id FROM notify_outbox
                WHERE status IN ('pending', 'sending') AND next_attempt_at <= NOW()
                ORDER BY channel = 'email', id LIMIT " . max(1, $limit));   // phones first: a slow mail server can't hold them up (P3)
            $stmt->execute();
            $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }
        // A row is 'sending' while one process has it. If that process died,
        // the claim lapses after 10 minutes and the row is tried again.
        $claim = $db->prepare("
            UPDATE notify_outbox SET status = 'sending', attempts = attempts + 1, next_attempt_at = NOW() + INTERVAL 10 MINUTE
            WHERE id = ? AND status IN ('pending', 'sending') AND next_attempt_at <= NOW()");
        $load = $db->prepare("SELECT o.*, (o.expires_at IS NOT NULL AND o.expires_at < NOW()) AS expired,
                                     IF(o.expires_at IS NULL, NULL, TIMESTAMPDIFF(SECOND, NOW(), o.expires_at)) AS ttl_left
                              FROM notify_outbox o WHERE id = ?");

        foreach (array_unique(array_map('intval', $ids)) as $id) {
            if (time() > $until) break;
            try {
                $claim->execute([$id]);
                if ($claim->rowCount() !== 1) continue;
                $load->execute([$id]);
                $row = $load->fetch();
                if (!$row) continue;
                if ($row['expired']) {
                    // A push some phones took before the time ran out was sent.
                    $reached = $row['channel'] === 'push' && !empty((json_decode((string)$row['payload'], true) ?: [])['_done']);
                    $done[notify_outcome($db, $row, $reached ? 'sent' : 'skipped', $reached ? 'the other phones were too late' : 'too late to be useful')]++;
                    continue;
                }
                try {
                    [$outcome, $error] = $row['channel'] === 'push' ? notify_send_push($db, $row) : notify_send_email($db, $row);
                } catch (Throwable $e) {
                    [$outcome, $error] = ['retry', mb_substr($e->getMessage(), 0, 255)];
                }
                $done[notify_outcome($db, $row, $outcome, $error)]++;
            } catch (Throwable $e) {
                // One row's trouble (the database going away mid-send) never
                // stops the rest; its claim lapses and it is tried again.
                error_log('Nfelia notify_flush #' . $id . ': ' . $e->getMessage());
            }
        }
    } catch (Throwable $e) {
        error_log('Nfelia notify_flush: ' . $e->getMessage());
    }
    return $done;
}

/**
 * Record how a send went. 'retry' becomes 'failed' once the tries run out.
 * 'bounced' (the address doesn't exist) fails at once, and whoever can fix
 * the address is told (app/helpers/bounces.php).
 */
function notify_outcome(PDO $db, array $row, string $outcome, ?string $error): string
{
    $error    = $error === null ? null : mb_substr($error, 0, 255);   // the column's width: never a failed write over a long reason
    if ($outcome === 'bounced') {
        $db->prepare("UPDATE notify_outbox SET status = 'failed', last_error = ? WHERE id = ?")->execute([$error, $row['id']]);
        require_once __DIR__ . '/bounces.php';
        bounce_tell($db, $row);
        return 'failed';
    }
    $attempts = (int)$row['attempts'];   // already counts this try
    $push     = $row['channel'] === 'push';
    // A push is worth trying while its time has not run out (the push
    // service would hold it that long too), so a network or push-service
    // outage of an hour doesn't lose it; past its time, the flush skips it.
    $time_left = $push && !empty($row['expires_at']) && isset($row['ttl_left']) && (int)$row['ttl_left'] > 120 && $attempts < NOTIFY_PUSH_MOST;
    if ($outcome === 'retry' && $attempts >= NOTIFY_MAX_ATTEMPTS[$row['channel']] && !$time_left) {
        // A push some phones took, and one phone never did: it was sent.
        $outcome = $push && str_starts_with((string)$error, 'sent to ') ? 'sent' : 'failed';
    }
    if ($outcome === 'retry') {
        $wait = NOTIFY_RETRY_MINUTES[$attempts] ?? 360;
        $db->prepare("UPDATE notify_outbox SET status = 'pending', last_error = ?,
                          next_attempt_at = " . ($push && !empty($row['expires_at'])
                              ? "LEAST(NOW() + INTERVAL {$wait} MINUTE, expires_at - INTERVAL 1 MINUTE)"
                              : "NOW() + INTERVAL {$wait} MINUTE") . "
                      WHERE id = ?")
           ->execute([$error, $row['id']]);
    } else {
        $db->prepare("UPDATE notify_outbox SET status = ?, last_error = ?, sent_at = IF(? = 'sent', NOW(), sent_at) WHERE id = ?")
           ->execute([$outcome, $error, $outcome, $row['id']]);
        if ($outcome === 'failed') {
            error_log("Nfelia notify: gave up on {$row['channel']} #{$row['id']} ({$row['event']}): {$error}");
        }
    }
    return $outcome;
}

/**
 * One push to every device the person turned notifications on for.
 * Sent once every live device took it. A device that has gone (404/410),
 * or whose subscription can't be used, is forgotten; one that keeps
 * refusing is forgotten after 5 refusals. A fault on our side (the push
 * service rejects Nfelia's key: 401/403) is never held against a phone:
 * the send is retried and the Nfelia team is told. A device the network
 * failed to reach is tried again while the others are not sent twice
 * (the row's payload keeps `_done`).
 */
function notify_send_push(PDO $db, array $row): array
{
    if (!push_enabled()) {
        return ['skipped', 'phone notifications are switched off on this server'];
    }
    // Someone on the Nfelia Ops team (app/ops/alerts.php), or a person in a business.
    $ops   = !empty($row['staff_id']);
    if ($ops) require_once BASE_PATH . '/app/ops/alerts.php';
    $table = $ops ? 'ops_push_subscriptions' : 'push_subscriptions';
    $stmt  = $db->prepare("SELECT * FROM {$table} WHERE " . ($ops ? 'staff_id' : 'user_id') . " = ?");
    $stmt->execute([$ops ? $row['staff_id'] : $row['user_id']]);
    $subs = $stmt->fetchAll();
    if (!$subs) {
        // Kept as skipped, and sent after all if a phone turns alerts on
        // while this is still worth sending (notify_push_revive()).
        return ['skipped', 'no phone has notifications on'];
    }

    $message = json_decode($row['payload'], true) ?: [];
    $done    = array_map('intval', (array)($message['_done'] ?? []));   // devices that took it on an earlier try
    unset($message['_done']);
    $message['badge'] = $ops ? ops_alerts_unread($db, (int)$row['staff_id']) : notify_unread_count($db, (int)$row['user_id']);
    // Seconds the push service may hold it: from the row's expiry, on the
    // database's clock, the same one that decides "too late to be useful".
    $ttl = isset($row['ttl_left']) ? max(60, (int)$row['ttl_left']) : 86400;

    $sent = count($done);
    $errors = [];
    $transient = [];
    $ours = null;
    $lost = [];   // people whose phone row went in this send (push_device_dead)
    foreach ($subs as $sub) {
        if (in_array((int)$sub['id'], $done, true)) continue;
        $detail = null;
        $status = webpush_send($sub, $message, $row['urgency'], $ttl, $detail);
        $why    = $detail !== null && $detail !== '' ? ' (' . $detail . ')' : '';
        if ($status >= 200 && $status < 300) {
            $sent++;
            $done[] = (int)$sub['id'];
            $db->prepare("UPDATE {$table} SET failures = 0, last_sent_at = NOW() WHERE id = ?")->execute([$sub['id']]);
        } elseif ($status === 404 || $status === 410) {
            $lost = array_merge($lost, $ops ? [] : push_device_dead($db, (string)$sub['endpoint_hash'], 'the phone unsubscribed (' . $status . ')'));
            if ($ops) $db->prepare("DELETE FROM {$table} WHERE id = ?")->execute([$sub['id']]);
            $errors[] = 'a phone turned notifications off';
        } elseif ($status === PUSH_NOT_ALLOWED || $status === PUSH_BAD_KEYS) {
            // Nothing will ever reach this row: forget it, and say why.
            $lost = array_merge($lost, $ops ? [] : push_device_dead($db, (string)$sub['endpoint_hash'], 'unusable: ' . (string)$detail));
            if ($ops) $db->prepare("DELETE FROM {$table} WHERE id = ?")->execute([$sub['id']]);
            $errors[] = "a phone's subscription could not be used" . $why;
        } elseif ($status === PUSH_OUR_FAULT || $status === 401 || $status === 403) {
            // Our key or our server, not the phone: try again, and tell the team.
            $ours = ($status > 0 ? "the push service rejected Nfelia's key ({$status})" : 'Nfelia could not sign the push') . $why;
            $transient[] = $ours;
        } elseif ($status === 0 || $status === 408 || $status === 429 || $status >= 500) {
            // Our network, or the push service is busy: not the phone's fault, try again.
            $transient[] = ($status === 0 ? 'push service unreachable' : "push service answered {$status}") . $why;
        } elseif ($status === 400 && preg_match('/unauthorized|vapid|authorization|mismatch/i', (string)$detail)) {
            // FCM's way of saying this subscription was made for another key
            // (Nfelia's VAPID key changed): the phone makes a new one next time.
            $lost = array_merge($lost, $ops ? [] : push_device_dead($db, (string)$sub['endpoint_hash'], 'made for another key (400)'));
            if ($ops) $db->prepare("DELETE FROM {$table} WHERE id = ?")->execute([$sub['id']]);
            $errors[] = "a phone's subscription was made for another key" . $why;
        } else {
            $db->prepare("UPDATE {$table} SET failures = failures + 1 WHERE id = ?")->execute([$sub['id']]);
            if ((int)$sub['failures'] + 1 >= 5) {
                $lost = array_merge($lost, $ops ? [] : push_device_dead($db, (string)$sub['endpoint_hash'], 'refused 5 times (' . $status . ')'));
                if ($ops) $db->prepare("DELETE FROM {$table} WHERE id = ?")->execute([$sub['id']]);
            }
            $errors[] = "push service refused it ({$status})" . $why;
        }
    }
    if ($ours !== null) {
        notify_push_trouble($db, $ours);
    }
    if ($lost) {
        notify_push_lost($db, $lost);
    }
    $all = array_values(array_unique(array_merge($transient, $errors)));
    if ($transient) {
        // Some device may still take it: keep what was done, try the rest again.
        $message['_done'] = array_values(array_unique($done));
        $db->prepare("UPDATE notify_outbox SET payload = ? WHERE id = ?")
           ->execute([json_encode($message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $row['id']]);
        return ['retry', ($sent ? 'sent to ' . $sent . ' of ' . ($sent + count($transient)) . ' phones; ' : '') . implode('; ', $all)];
    }
    if ($sent > 0) {
        return ['sent', $all ? implode('; ', $all) : null];
    }
    // Every phone it had is gone: the same as having none, so a phone that
    // comes back in time still gets it (notify_push_revive()).
    $left = $db->prepare("SELECT COUNT(*) FROM {$table} WHERE " . ($ops ? 'staff_id' : 'user_id') . " = ?");
    $left->execute([$ops ? $row['staff_id'] : $row['user_id']]);
    if ((int)$left->fetchColumn() === 0) {
        return ['skipped', 'no phone has notifications on'];
    }
    return ['failed', implode('; ', $all) ?: 'no phone took it'];
}

/**
 * The push service won't take Nfelia's pushes at all (the VAPID keys, the
 * subject, the server's clock): logged, and the Nfelia Ops team hears once
 * a day, because no phone will buzz until it is fixed.
 */
function notify_push_trouble(PDO $db, string $why): void
{
    static $said = false;
    if ($said) return;
    $said = true;
    error_log('Nfelia push: ' . $why);
    try {
        if (!function_exists('ops_alert')) require_once BASE_PATH . '/app/ops/alerts.php';
        ops_alert($db, 'health.push', 'Phone notifications are failing', $why . '. Check VAPID_PUBLIC_KEY, VAPID_PRIVATE_KEY and VAPID_SUBJECT in .env, and the server clock.',
                  'health/sends?channel=push', 'push-trouble:' . date('Y-m-d'));
    } catch (Throwable $e) {
        error_log('Nfelia push trouble alert: ' . $e->getMessage());
    }
}

/**
 * A phone's subscription is gone for good (the push service said so, or it
 * could not be used). Everyone who had alerts on it and now has no phone
 * left is told: a bell row at once (the app's banner shows it, and the app
 * turns the phone back on by itself where it can), an email a day later if
 * no phone has come back by then, and the Nfelia team sees it in Ops.
 */
function notify_push_lost(PDO $db, array $lost): void
{
    try {
        $any = $db->prepare("SELECT COUNT(*) FROM push_subscriptions WHERE user_id = ?");
        foreach ($lost as $who) {
            $uid = (int)$who['user_id'];
            $any->execute([$uid]);
            if ((int)$any->fetchColumn() > 0) continue;   // another phone still buzzes
            $device = push_device_label($who['user_agent'] ?? null);
            notify('push.lost', (int)$who['business_id'], ['device' => $device], [
                'users' => [$uid], 'dedupe' => $uid . ':' . date('Y-m-d'), 'channels' => ['email'], 'email_after' => 24 * 3600,
            ]);
            if (!function_exists('ops_alert')) require_once BASE_PATH . '/app/ops/alerts.php';
            ops_alert($db, 'health.push_lost', 'A phone stopped getting alerts', $device . ' of person #' . $uid . ': ' . (string)($who['reason'] ?? 'the push service declared it gone')
                      . '. The app turns it back on when opened; if this keeps happening, look for a pattern.',
                      'health/sends?channel=push', 'push-lost:' . $uid . ':' . date('Y-m-d'), ['push' => false]);
        }
    } catch (Throwable $e) {
        error_log('Nfelia notify_push_lost: ' . $e->getMessage());
    }
}

/**
 * A phone just turned alerts on (or came back): pushes for this person that
 * were skipped for want of a phone, and are still worth sending, go now.
 * Returns the outbox ids revived. Only recent ones, and only a few: a first
 * phone must not be met with a day's worth of buzzing.
 */
function notify_push_revive(PDO $db, int $user_id): array
{
    try {
        $stmt = $db->prepare("
            SELECT id FROM notify_outbox
            WHERE user_id = ? AND channel = 'push' AND status = 'skipped' AND last_error = 'no phone has notifications on'
              AND (expires_at IS NULL OR expires_at > NOW()) AND created_at > NOW() - INTERVAL 6 HOUR
            ORDER BY id DESC LIMIT 10");
        $stmt->execute([$user_id]);
        $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        if (!$ids) return [];
        $db->prepare("UPDATE notify_outbox SET status = 'pending', attempts = 0, next_attempt_at = NOW(), last_error = 'a phone turned alerts on'
                      WHERE id IN (" . implode(',', $ids) . ") AND status = 'skipped'")->execute();
        foreach ($ids as $id) notify_flush_later($id);
        return $ids;
    } catch (Throwable $e) {
        error_log('Nfelia notify_push_revive: ' . $e->getMessage());
        return [];
    }
}

/** One email, rendered now from what was queued. */
function notify_send_email(PDO $db, array $row): array
{
    $p = json_decode($row['payload'], true) ?: [];
    // "Your phone stopped getting alerts" waits a day: if a phone is back by
    // then (the app turns one on by itself), there is nothing to say.
    if ($row['event'] === 'push.lost' && !empty($row['user_id'])) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM push_subscriptions WHERE user_id = ?");
        $stmt->execute([(int)$row['user_id']]);
        if ((int)$stmt->fetchColumn() > 0) return ['skipped', 'a phone is back on'];
    }
    $brand = [];
    if (!empty($p['brand'])) {
        $stmt = $db->prepare("SELECT id, name, logo_path, primary_color, tagline, email FROM businesses WHERE id = ?");
        $stmt->execute([(int)$p['brand']]);
        $brand = $stmt->fetch() ?: [];
    }
    $html = render_email($p['template'] ?? 'notification', $p['vars'] ?? [], $brand);
    if (str_starts_with($html, 'Error: email template')) {
        return ['failed', mb_substr($html, 0, 255)];
    }
    // A shop's own customer sees the shop's name, and a reply reaches the
    // shop. People signed up to Nfelia, and Ops, hear from Nfelia.
    $shopper = $brand && empty($row['user_id']) && empty($row['staff_id']);
    if (send_mail($row['recipient'], (string)$row['recipient_name'], (string)($p['subject'] ?? 'Nfelia'), $html, true, (array)($p['headers'] ?? []),
                  $shopper ? (string)$brand['name'] : '', $shopper ? (string)($brand['email'] ?? '') : '')) {
        return ['sent', null];
    }
    $bounced = mail_bounced();
    return $bounced !== null
        ? ['bounced', mb_substr('bounced: ' . $bounced, 0, 255)]
        : ['retry', 'the mail server did not accept it'];
}

/* ═══════════════════════════════════════════════════════════════════
 *  WHO, AND WHAT THEY CHOSE
 * ═══════════════════════════════════════════════════════════════════ */

/** Active people who should hear an event, with what sending needs. */
function notify_recipients(PDO $db, int $business_id, string $to, array $user_ids = []): array
{
    $roles = match ($to) {
        'owner'    => NOTIFY_OWNER,
        'boss'     => NOTIFY_BOSS,
        'team', 'user' => NOTIFY_TEAM,
        default    => throw new InvalidArgumentException("unknown audience {$to}"),
    };
    $sql = "SELECT id, role, full_name, email, quiet_hours, quiet_from, quiet_to, hide_amounts
            FROM users WHERE business_id = ? AND is_active = 1
              AND role IN (" . implode(',', array_fill(0, count($roles), '?')) . ")";
    $args = [$business_id, ...$roles];
    if ($to === 'user') {
        $user_ids = array_values(array_unique(array_filter(array_map('intval', $user_ids))));
        if (!$user_ids) return [];
        $sql .= " AND id IN (" . implode(',', array_fill(0, count($user_ids), '?')) . ")";
        $args = [...$args, ...$user_ids];
    }
    $stmt = $db->prepare($sql . " ORDER BY id");
    $stmt->execute($args);
    return $stmt->fetchAll();
}

function notify_topic_default(string $topic): array
{
    $t = NOTIFY_TOPICS[$topic];
    return ['push' => $t['push'], 'email' => $t['email']];
}

/** user_id => topic => ['push' => bool, 'email' => bool], defaults filled in. */
function notify_prefs_for(PDO $db, array $user_ids): array
{
    $out = [];
    foreach ($user_ids as $uid) {
        foreach (NOTIFY_TOPICS as $topic => $t) {
            $out[(int)$uid][$topic] = notify_topic_default($topic);
        }
    }
    if ($user_ids) {
        $stmt = $db->prepare("SELECT user_id, topic, push, email FROM notification_prefs WHERE user_id IN ("
                             . implode(',', array_fill(0, count($user_ids), '?')) . ")");
        $stmt->execute(array_values($user_ids));
        foreach ($stmt->fetchAll() as $r) {
            if (!isset(NOTIFY_TOPICS[$r['topic']])) continue;
            $out[(int)$r['user_id']][$r['topic']] = ['push' => (bool)$r['push'], 'email' => (bool)$r['email']];
        }
    }
    foreach ($out as $uid => $topics) {
        foreach (NOTIFY_TOPICS as $topic => $t) {
            if ($t['locked']) $out[$uid][$topic]['email'] = true;
        }
    }
    return $out;
}

/**
 * One person's choices, for More > Notifications: the topics their role
 * hears about, and their quiet hours and lock-screen setting.
 */
function notify_prefs(int $user_id): array
{
    $db = get_db();
    $stmt = $db->prepare("SELECT role, business_id, " . implode(', ', NOTIFY_FIELDS) . " FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $u = $stmt->fetch() ?: [];
    $mine = notify_prefs_for($db, [$user_id])[$user_id];
    $topics = [];
    foreach (NOTIFY_TOPICS as $key => $t) {
        if (!in_array($u['role'] ?? 'staff', $t['roles'], true)) continue;
        $topics[$key] = ['label' => $t['label'], 'hint' => $t['hint'], 'locked' => $t['locked']] + $mine[$key];
    }
    return [
        'topics'       => $topics,
        'quiet_hours'  => (bool)($u['quiet_hours'] ?? false),
        'quiet_from'   => substr((string)($u['quiet_from'] ?? '21:00'), 0, 5),
        'quiet_to'     => substr((string)($u['quiet_to'] ?? '07:00'), 0, 5),
        'hide_amounts' => (bool)($u['hide_amounts'] ?? false),
    ];
}

/** Save one person's choice for a topic. Locked emails stay on. */
function notify_set_pref(PDO $db, int $user_id, string $topic, ?bool $push, ?bool $email): void
{
    if (!isset(NOTIFY_TOPICS[$topic])) return;
    $now = notify_prefs_for($db, [$user_id])[$user_id][$topic];
    $push  = $push ?? $now['push'];
    $email = NOTIFY_TOPICS[$topic]['locked'] ? true : ($email ?? $now['email']);
    // Only a choice that differs from the default is kept, so a person who
    // never changed a topic gets whatever the default becomes later.
    if (['push' => $push, 'email' => $email] === notify_topic_default($topic)) {
        $db->prepare("DELETE FROM notification_prefs WHERE user_id = ? AND topic = ?")->execute([$user_id, $topic]);
        return;
    }
    $db->prepare("INSERT INTO notification_prefs (user_id, topic, push, email) VALUES (?, ?, ?, ?)
                  ON DUPLICATE KEY UPDATE push = VALUES(push), email = VALUES(email)")
       ->execute([$user_id, $topic, (int)$push, (int)$email]);
}

/** When this person's quiet hours end, if they cover this moment (Lagos time); null when they don't. */
function notify_quiet_until(array $user, ?DateTimeImmutable $now = null): ?DateTimeImmutable
{
    $now = $now ?? planner_now();
    if (!notify_quiet($user, $now)) {
        return null;
    }
    $to  = substr((string)$user['quiet_to'], 0, 8);
    $end = new DateTimeImmutable($now->format('Y-m-d') . ' ' . $to, planner_tz());
    return $end > $now ? $end : $end->modify('+1 day');
}

/** Whether this person's quiet hours cover this moment (Lagos time). */
function notify_quiet(array $user, ?DateTimeImmutable $now = null): bool
{
    if (empty($user['quiet_hours'])) {
        return false;
    }
    $t    = ($now ?? planner_now())->format('H:i:s');
    $from = substr((string)$user['quiet_from'], 0, 8);
    $to   = substr((string)$user['quiet_to'], 0, 8);
    if ($from === $to) {
        return false;
    }
    // 21:00 to 07:00 runs across midnight; 13:00 to 15:00 does not.
    return $from < $to ? ($t >= $from && $t < $to) : ($t >= $from || $t < $to);
}

/* ═══════════════════════════════════════════════════════════════════
 *  READING
 * ═══════════════════════════════════════════════════════════════════ */

/**
 * The bell rows a person sees. Rows from the engine are theirs alone. Rows
 * from before it (no event) were for the whole business: owners and admins
 * still see those, and everyone sees the old personal ones (reminders).
 * Returns [SQL, args] to AND into a WHERE.
 */
function notify_visible_sql(int $business_id, int $user_id, string $role): array
{
    $boss = in_array($role, NOTIFY_BOSS, true) ? 1 : 0;
    return [
        "business_id = ? AND (user_id = ? AND (event IS NOT NULL OR type = 'general')
            OR (event IS NULL AND ? = 1 AND (user_id IS NULL OR type <> 'general')))",
        [$business_id, $user_id, $boss],
    ];
}

/**
 * For the page's own checks (assets/js/bell.js, push.js): this person's
 * phones and the phones the push service has declared dead, each by the
 * first part of its endpoint's hash. The page compares its own phone and
 * saves or renews it when it should.
 */
function notify_phones_for_page(PDO $db, int $user_id): array
{
    $stmt = $db->prepare("SELECT LEFT(endpoint_hash, 16) FROM push_subscriptions WHERE user_id = ?");
    $stmt->execute([$user_id]);
    return ['devices' => $stmt->fetchAll(PDO::FETCH_COLUMN), 'dead' => push_dead_hashes($db)];
}

/** Unread bell rows of the engine, for the number on the app icon. */
function notify_unread_count(PDO $db, int $user_id): int
{
    $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0 AND event IS NOT NULL");
    $stmt->execute([$user_id]);
    return (int)$stmt->fetchColumn();
}

/* ═══════════════════════════════════════════════════════════════════
 *  SMALL THINGS
 * ═══════════════════════════════════════════════════════════════════ */

/**
 * A link that stays inside the app: "orders/view/12". Old rows hold
 * "/orders/view/12" or a full https:// address (sometimes of the old
 * domain); only the path and query are kept, so nothing can lead
 * off-site. Returns '' for anything unusable.
 */
function notify_safe_path(?string $url): string
{
    $url = trim((string)$url);
    if ($url === '') return '';
    if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $url)) {
        $parts = parse_url($url);
        if (!$parts) return '';
        $base = (string)(parse_url(APP_URL, PHP_URL_PATH) ?? '');
        $path = (string)($parts['path'] ?? '');
        if ($base !== '' && $base !== '/' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base));
        }
        $url = $path . (isset($parts['query']) ? '?' . $parts['query'] : '') . (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');
    }
    $url = ltrim($url, '/');
    if ($url === '' || str_contains($url, '\\') || str_contains($url, '//') || str_contains($url, '..') || preg_match('/[\x00-\x1F\x7F\s]/', $url)) {
        return '';
    }
    return preg_match('~^[A-Za-z0-9][A-Za-z0-9/_.\-]*(\?[A-Za-z0-9_.\-=&%+\[\]]*)?(#[A-Za-z0-9_\-]*)?$~', $url) ? $url : '';
}

/** "₦35,000" in the business's own currency. */
function notify_money_text(float $amount, ?string $symbol = null): string
{
    $symbol = $symbol ?? (function_exists('auth_business') ? (auth_business()['currency_symbol'] ?? '₦') : '₦');
    return $symbol . number_format($amount, fmod($amount, 1.0) == 0.0 ? 0 : 2);
}

/** The first $max characters, on a word boundary, never cutting a character in half. */
function notify_excerpt(string $text, int $max = 120): string
{
    $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    if (mb_strlen($text) <= $max) return $text;
    $cut = mb_substr($text, 0, $max - 1);
    $space = mb_strrpos($cut, ' ');
    return rtrim($space > $max * 0.6 ? mb_substr($cut, 0, $space) : $cut, " ,.;:") . '…';
}

/** "today", "tomorrow", "in 3 days". */
function notify_when_text(int $days): string
{
    return $days <= 0 ? 'today' : ($days === 1 ? 'tomorrow' : "in {$days} days");
}

/** "Just now", "12 min ago", "Today, 11:47am", "Yesterday, 9:05am", "3 Oct, 9:05am". */
function notify_when_ago(string $at): string
{
    $then = new DateTimeImmutable($at, planner_tz());
    $now  = planner_now();
    $secs = $now->getTimestamp() - $then->getTimestamp();
    if ($secs < 60)   return 'Just now';
    if ($secs < 3600) return intdiv($secs, 60) . ' min ago';
    $days = planner_days_between($then->format('Y-m-d'), $now->format('Y-m-d'));
    if ($days === 0) return 'Today, ' . strtolower($then->format('g:ia'));
    if ($days === 1) return 'Yesterday, ' . strtolower($then->format('g:ia'));
    return $then->format('j M') . ', ' . strtolower($then->format('g:ia'));
}

/**
 * How a phone alert went, in plain words for the person it was for
 * (More > Notifications): the outbox row's status and reason.
 */
function notify_push_words(array $o): string
{
    $why = (string)($o['last_error'] ?? '');
    return match ($o['status'] ?? '') {
        'sent'    => 'Sent to your phone' . ($why !== '' ? ' (' . $why . ')' : ''),
        'skipped' => match (true) {
            $why === 'quiet hours'                      => 'Not sent: your quiet hours, and it would have been too late by the end of them',
            $why === 'no phone has notifications on'    => 'Not sent: no phone had alerts on',
            $why === 'too late to be useful'            => 'Not sent: it was too late to be useful by the time it could go',
            $why === 'a phone is back on'               => 'Not needed: a phone is back on',
            str_starts_with($why, 'phone alerts for ')  => 'Not sent: ' . $why . ' (More › Notifications)',
            default                                     => 'Not sent' . ($why !== '' ? ': ' . $why : ''),
        },
        'failed'  => 'Not delivered after ' . (int)($o['attempts'] ?? 0) . ' tries' . ($why !== '' ? ': ' . $why : ''),
        'pending', 'sending' => match (true) {
            $why === 'held for quiet hours'      => 'Held back until your quiet hours end',
            (int)($o['attempts'] ?? 0) > 0       => 'Not delivered yet' . ($why !== '' ? ' (' . $why . ')' : '') . '. Nfelia will try again.',
            default                              => 'On its way',
        },
        default   => (string)($o['status'] ?? ''),
    };
}
