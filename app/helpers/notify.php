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
 *    in their quiet hours. Billing and security emails always go.
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

/** Platform alerts go to this business (the Nfelia team) until ops.nfelia.com exists. */
const NOTIFY_PLATFORM_BUSINESS_ID = 1;

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
    'platform'  => ['label' => 'Nfelia team',                  'hint' => 'Payments to review, support tickets, shop names, messages.', 'push' => true, 'email' => false, 'locked' => false, 'roles' => NOTIFY_TEAM],
];

/** The person's own choices, as stored on users. */
const NOTIFY_FIELDS = ['quiet_hours', 'quiet_from', 'quiet_to', 'hide_amounts'];

/* ═══════════════════════════════════════════════════════════════════
 *  THE EVENTS
 * ═══════════════════════════════════════════════════════════════════
 * Each: topic, to (owner | boss | team | user | platform), urgency, ttl
 * (seconds a push stays worth delivering), url, tag (a newer push with the
 * same tag replaces the older one on the phone), text (title and body;
 * $m formats money and is null when this person must not see amounts),
 * private (the phone title for people who hide amounts), and for email:
 * lines (label => value rows), button, template (default 'notification')
 * and brand ('business' or 'nfelia').
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
        'payment.invoice' => [
            'topic' => 'money', 'to' => 'boss', 'urgency' => 'high',
            'url' => fn($d) => 'invoices/view/' . (int)$d['invoice_id'],
            'tag' => fn($d) => 'pay-inv-' . (int)$d['invoice_id'],
            'text' => fn($d, $m) => [
                $m($d['amount']) . ' received · ' . $d['invoice_number'],
                trim(($d['customer'] ?: 'A customer') . ' paid' . (!empty($d['method']) ? ' by ' . $d['method'] : '') . '. '
                    . ((float)$d['balance'] > 0 ? $m($d['balance']) . ' still owed.' : 'Paid in full.')),
            ],
            'private' => 'Payment received',
            'lines' => fn($d, $m) => ['Invoice' => $d['invoice_number'], 'Customer' => $d['customer'], 'Amount' => $m($d['amount']), 'Still owed' => $m($d['balance'])],
            'button' => 'Open the invoice',
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
                'Delivery' => $d['fulfillment'] === 'delivery' ? ($d['address'] ?: 'Delivery') : 'Pickup',
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
        'quote.accepted' => [
            'topic' => 'customers', 'to' => 'boss', 'urgency' => 'high',
            'url' => fn($d) => 'quotations/view/' . (int)$d['quotation_id'],
            'tag' => fn($d) => 'quote-' . (int)$d['quotation_id'],
            'text' => fn($d, $m) => ['Quote accepted: ' . $d['quotation_number'],
                                     $d['customer'] . ' signed and accepted it' . ($m && isset($d['total']) ? ', ' . $m($d['total']) : '') . '.'],
            'private' => 'A quote was accepted',
            'button' => 'Open the quote',
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
            'url' => fn($d) => 'deliveries/view/' . (int)$d['delivery_id'],
            'tag' => fn($d) => 'delivery-' . (int)$d['delivery_id'],
            'text' => fn($d, $m) => ['Delivery signed: ' . $d['delivery_number'], 'Received and signed by ' . $d['signer'] . '.'],
            'private' => 'A delivery was signed',
            'button' => 'Open the delivery',
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
            'url' => fn($d) => 'invoices/view/' . (int)$d['invoice_id'],
            'tag' => fn($d) => 'overdue-' . (int)$d['invoice_id'],
            'text' => fn($d, $m) => ['Overdue: ' . $d['invoice_number'],
                                     ($d['customer'] ?: 'A customer') . ' owes ' . $m($d['balance']) . ', ' . $pl((int)$d['days'], 'day', 'days') . ' past due.'],
            'private' => 'An invoice is overdue',
            'button' => 'Open the invoice',
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
            'template' => 'task_reminder',
        ],
        'morning.plan' => [
            'topic' => 'morning', 'to' => 'user', 'ttl' => 6 * 3600,
            'url' => fn($d) => 'tasks',
            'tag' => fn($d) => 'morning',
            'text' => fn($d, $m) => [$d['title'], $d['line']],
            'private' => 'Your plan for today',
            'template' => 'morning_plan',
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
            'text' => fn($d, $m) => ['Your subscription is due',
                                     'Your paid time ended on ' . $day($d['period_end']) . '. Pay by ' . $day($d['grace_ends']) . ' to keep using Nfelia without a break.'],
            'private' => 'Subscription due',
            'button' => 'Pay now',
        ],
        'billing.ended' => [
            'topic' => 'billing', 'to' => 'owner', 'brand' => 'nfelia', 'urgency' => 'high',
            'url' => fn($d) => 'settings?tab=billing', 'tag' => fn($d) => 'billing',
            'text' => fn($d, $m) => ['Your subscription has ended',
                                     'Pay to carry on where you left off. Everything you added is still here.'],
            'private' => 'Subscription ended',
            'button' => 'Pay now',
        ],
        'billing.renewal_soon' => [
            'topic' => 'billing', 'to' => 'owner', 'brand' => 'nfelia',
            'url' => fn($d) => 'settings?tab=billing', 'tag' => fn($d) => 'billing',
            'text' => fn($d, $m) => ['Your subscription ends ' . notify_when_text((int)$d['days']),
                                     'Pay before ' . $day($d['period_end']) . ' to keep going without a break.'],
            'private' => 'Subscription ending soon',
            'button' => 'Pay now',
        ],
        'billing.trial_ending' => [
            'topic' => 'billing', 'to' => 'owner', 'brand' => 'nfelia',
            'url' => fn($d) => 'settings?tab=billing', 'tag' => fn($d) => 'billing',
            'text' => fn($d, $m) => ['Your free trial ends ' . notify_when_text((int)$d['days']),
                                     'Choose a plan before ' . $day($d['trial_ends']) . ' to keep using Nfelia.'],
            'private' => 'Free trial ending soon',
            'button' => 'Choose a plan',
        ],
        'billing.trial_ended' => [
            'topic' => 'billing', 'to' => 'owner', 'brand' => 'nfelia', 'urgency' => 'high',
            'url' => fn($d) => 'settings?tab=billing', 'tag' => fn($d) => 'billing',
            'text' => fn($d, $m) => ['Your free trial has ended', 'Choose a plan to carry on. Everything you added is still here.'],
            'private' => 'Free trial ended',
            'button' => 'Choose a plan',
        ],
        'billing.momo_submitted' => [
            'topic' => 'billing', 'to' => 'owner', 'brand' => 'nfelia',
            'url' => fn($d) => 'settings?tab=billing', 'tag' => fn($d) => 'billing',
            'text' => fn($d, $m) => ['Payment received for checking',
                                     'We got your ' . $d['amount_text'] . ' mobile money payment and will check it within 24 hours. Nfelia stays open meanwhile.'],
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

        /* ── Sign-ins and security (always by email too) ──────── */
        'security.new_device' => [
            'topic' => 'security', 'to' => 'user', 'brand' => 'nfelia', 'urgency' => 'high',
            'url' => fn($d) => 'settings?tab=profile', 'tag' => fn($d) => 'security',
            'text' => fn($d, $m) => ['New sign-in to your account',
                                     $d['device'] . ', ' . $d['when'] . '. If this wasn’t you, change your password now.'],
            'private' => 'New sign-in to your account',
            'button' => 'Check your account',
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

        /* ── The Nfelia team (business 1 until ops.nfelia.com) ── */
        'platform.momo_submitted' => [
            'topic' => 'platform', 'to' => 'platform', 'urgency' => 'high',
            'url' => fn($d) => 'momo/review#payment-' . (int)$d['payment_id'], 'tag' => fn($d) => 'momo-' . (int)$d['payment_id'],
            'text' => fn($d, $m) => ['MoMo payment to review', $d['business'] . ' sent ' . $d['amount_text'] . ' by mobile money.'],
            'private' => 'MoMo payment to review',
        ],
        'platform.ticket' => [
            'topic' => 'platform', 'to' => 'platform',
            'url' => fn($d) => 'admin/support#ticket-' . (int)$d['ticket_id'], 'tag' => fn($d) => 'ticket-' . (int)$d['ticket_id'],
            'text' => fn($d, $m) => ['New support ticket', $d['business'] . ': ' . notify_excerpt($d['subject'], 120)],
            'private' => 'New support ticket',
        ],
        'platform.shop_name' => [
            'topic' => 'platform', 'to' => 'platform',
            'url' => fn($d) => 'admin/shop-names', 'tag' => fn($d) => 'shop-name',
            'text' => fn($d, $m) => ['Shop name to review', $d['business'] . ' asked for ' . $d['host'] . '.'],
            'private' => 'Shop name to review',
        ],
        'platform.prospect' => [
            'topic' => 'platform', 'to' => 'platform',
            'url' => fn($d) => 'admin/feedback#prospects', 'tag' => fn($d) => 'prospect',
            'text' => fn($d, $m) => ['Message from the home page', $d['name'] . ' (' . $d['contact'] . '): ' . notify_excerpt($d['message'], 140)],
            'private' => 'Message from the home page',
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
        if ($to === 'platform') {
            $business_id = NOTIFY_PLATFORM_BUSINESS_ID;
        }
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

        foreach ($people as $person) {
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

            if (in_array('push', $channels, true) && $want['push'] && push_enabled()) {
                $hide = !empty($person['hide_amounts']);
                $message = [
                    'title' => $hide ? $def['private'] : $title,
                    'body'  => $hide ? 'Tap to see it in Nfelia.' : notify_excerpt($body, 240),
                    'url'   => $open,
                    'tag'   => $tag,
                    'id'    => $nid,
                ];
                $quiet = empty($opts['loud']) && notify_quiet($person);
                notify_queue($db, [
                    'business_id' => $business_id, 'notification_id' => $nid, 'user_id' => $uid, 'event' => $event,
                    'channel' => 'push', 'payload' => $message, 'urgency' => $def['urgency'] ?? 'normal',
                    'ttl' => $def['ttl'] ?? 86400,
                    'status' => $quiet ? 'skipped' : 'pending', 'error' => $quiet ? 'quiet hours' : null,
                ]);
            }

            if (in_array('email', $channels, true) && $want['email'] && filter_var($person['email'], FILTER_VALIDATE_EMAIL)) {
                $lines = isset($def['lines']) ? array_filter(($def['lines'])($data, $sees ? $money : null), fn($v) => $v !== null && $v !== '') : [];
                notify_queue($db, [
                    'business_id' => $business_id, 'notification_id' => $nid, 'user_id' => $uid, 'event' => $event,
                    'channel' => 'email', 'recipient' => $person['email'], 'recipient_name' => $person['full_name'],
                    'payload' => [
                        'subject'  => $title,
                        'template' => $def['template'] ?? 'notification',
                        'brand'    => ($def['brand'] ?? 'business') === 'nfelia' ? null : $business_id,
                        'vars'     => array_merge([
                            'subject'      => $title,
                            'headline'     => $title,
                            'message'      => $body,
                            'lines'        => $lines,
                            'button'       => $def['button'] ?? 'Open in Nfelia',
                            'open_url'     => APP_URL . '/' . $open,
                            'prefs_url'    => APP_URL . '/more#notifications',
                            'topic_label'  => $topic ? NOTIFY_TOPICS[$topic]['label'] : null,
                            'always'       => $topic === null || !empty(NOTIFY_TOPICS[$topic]['locked']),
                        ], $opts['vars'] ?? []),
                    ],
                ]);
            }
        }
        return $made;
    } catch (Throwable $e) {
        error_log("Nfelia notify {$event}: " . $e->getMessage());
        return [];
    }
}

/**
 * An email to someone who isn't signed up (a shopper, a customer, an old
 * address): no bell row, the same outbox and retries. $dedupe keeps the
 * same email from going twice. $business_id brands it, or null for Nfelia.
 */
function notify_email(?int $business_id, string $to, string $name, string $subject, string $template, array $vars, ?string $dedupe = null, string $event = 'email'): ?int
{
    try {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return null;
        }
        return notify_queue(get_db(), [
            'business_id' => $business_id, 'event' => $event, 'channel' => 'email',
            'recipient' => $to, 'recipient_name' => $name, 'dedupe_key' => $dedupe,
            'payload' => ['subject' => $subject, 'template' => $template, 'brand' => $business_id, 'vars' => $vars + ['subject' => $subject]],
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
    $db->prepare("
        INSERT IGNORE INTO notify_outbox
            (business_id, notification_id, user_id, event, channel, recipient, recipient_name, dedupe_key,
             payload, urgency, status, last_error, expires_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, " . ($ttl > 0 ? "NOW() + INTERVAL {$ttl} SECOND" : 'NULL') . ")
    ")->execute([
        $row['business_id'] ?? null, $row['notification_id'] ?? null, $row['user_id'] ?? null, $row['event'],
        $row['channel'], $row['recipient'] ?? null, $row['recipient_name'] ?? null,
        isset($row['dedupe_key']) ? mb_substr($row['dedupe_key'], 0, 150) : null,
        json_encode($row['payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        $row['urgency'] ?? 'normal', $status, $row['error'] ?? null,
    ]);
    $id = (int)$db->lastInsertId();
    if ($id && $status === 'pending') {
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
const NOTIFY_MAX_ATTEMPTS  = ['push' => 4, 'email' => 6];

/**
 * Send waiting pushes and emails: these ids, or everything due. Each row is
 * claimed first, so the page's own send and the cron never both send it.
 * Returns counts by outcome.
 */
function notify_flush(?array $ids = null, int $limit = 200): array
{
    $done = ['sent' => 0, 'retry' => 0, 'failed' => 0, 'skipped' => 0];
    try {
        $db = get_db();
        if ($ids === null) {
            $stmt = $db->prepare("
                SELECT id FROM notify_outbox
                WHERE status IN ('pending', 'sending') AND next_attempt_at <= NOW()
                ORDER BY id LIMIT " . max(1, $limit));
            $stmt->execute();
            $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }
        // A row is 'sending' while one process has it. If that process died,
        // the claim lapses after 10 minutes and the row is tried again.
        $claim = $db->prepare("
            UPDATE notify_outbox SET status = 'sending', attempts = attempts + 1, next_attempt_at = NOW() + INTERVAL 10 MINUTE
            WHERE id = ? AND status IN ('pending', 'sending') AND next_attempt_at <= NOW()");
        $load = $db->prepare("SELECT o.*, (o.expires_at IS NOT NULL AND o.expires_at < NOW()) AS expired FROM notify_outbox o WHERE id = ?");

        foreach (array_unique(array_map('intval', $ids)) as $id) {
            $claim->execute([$id]);
            if ($claim->rowCount() !== 1) continue;
            $load->execute([$id]);
            $row = $load->fetch();
            if (!$row) continue;
            if ($row['expired']) {
                notify_outcome($db, $row, 'skipped', 'too late to be useful');
                $done['skipped']++;
                continue;
            }
            try {
                [$outcome, $error] = $row['channel'] === 'push' ? notify_send_push($db, $row) : notify_send_email($db, $row);
            } catch (Throwable $e) {
                [$outcome, $error] = ['retry', mb_substr($e->getMessage(), 0, 255)];
            }
            $done[notify_outcome($db, $row, $outcome, $error)]++;
        }
    } catch (Throwable $e) {
        error_log('Nfelia notify_flush: ' . $e->getMessage());
    }
    return $done;
}

/** Record how a send went. 'retry' becomes 'failed' once the tries run out. */
function notify_outcome(PDO $db, array $row, string $outcome, ?string $error): string
{
    $attempts = (int)$row['attempts'];   // already counts this try
    if ($outcome === 'retry' && $attempts >= NOTIFY_MAX_ATTEMPTS[$row['channel']]) {
        $outcome = 'failed';
    }
    if ($outcome === 'retry') {
        $wait = NOTIFY_RETRY_MINUTES[$attempts] ?? 360;
        $db->prepare("UPDATE notify_outbox SET status = 'pending', next_attempt_at = NOW() + INTERVAL {$wait} MINUTE, last_error = ? WHERE id = ?")
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
 * Sent if any device took it. A device that has gone (404/410) is
 * forgotten; one that keeps refusing is forgotten after 5 refusals.
 */
function notify_send_push(PDO $db, array $row): array
{
    if (!push_enabled()) {
        return ['skipped', 'phone notifications are switched off on this server'];
    }
    $stmt = $db->prepare("SELECT * FROM push_subscriptions WHERE user_id = ?");
    $stmt->execute([$row['user_id']]);
    $subs = $stmt->fetchAll();
    if (!$subs) {
        return ['skipped', 'no phone has notifications on'];
    }

    $message = json_decode($row['payload'], true) ?: [];
    $message['badge'] = notify_unread_count($db, (int)$row['user_id']);
    $ttl = $row['expires_at'] ? max(60, strtotime($row['expires_at']) - time()) : 86400;

    $sent = 0;
    $errors = [];
    foreach ($subs as $sub) {
        $status = webpush_send($sub, $message, $row['urgency'], $ttl);
        if ($status >= 200 && $status < 300) {
            $sent++;
            $db->prepare("UPDATE push_subscriptions SET failures = 0, last_sent_at = NOW() WHERE id = ?")->execute([$sub['id']]);
        } elseif ($status === 404 || $status === 410) {
            $db->prepare("DELETE FROM push_subscriptions WHERE id = ?")->execute([$sub['id']]);
            $errors[] = 'a phone turned notifications off';
        } elseif ($status === 0 || $status === 429 || $status >= 500) {
            // Our network, or the push service is busy: not the phone's fault, try again.
            $errors[] = $status === 0 ? 'push service unreachable' : "push service answered {$status}";
        } else {
            $db->prepare("UPDATE push_subscriptions SET failures = failures + 1 WHERE id = ?")->execute([$sub['id']]);
            $db->prepare("DELETE FROM push_subscriptions WHERE id = ? AND failures >= 5")->execute([$sub['id']]);
            $errors[] = "push service refused it ({$status})";
        }
    }
    if ($sent > 0) {
        return ['sent', $errors ? implode('; ', array_unique($errors)) : null];
    }
    $transient = array_filter($errors, fn($e) => str_starts_with($e, 'push service unreachable') || str_starts_with($e, 'push service answered'));
    return [$transient ? 'retry' : 'failed', implode('; ', array_unique($errors)) ?: 'no phone took it'];
}

/** One email, rendered now from what was queued. */
function notify_send_email(PDO $db, array $row): array
{
    $p = json_decode($row['payload'], true) ?: [];
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
    return send_mail($row['recipient'], (string)$row['recipient_name'], (string)($p['subject'] ?? 'Nfelia'), $html, true)
        ? ['sent', null]
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
        'team', 'platform', 'user' => NOTIFY_TEAM,
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
        if ($key === 'platform' && (int)($u['business_id'] ?? 0) !== NOTIFY_PLATFORM_BUSINESS_ID) continue;
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
