<?php
/**
 * What a Records Book can hold: every sheet, its columns, and where its rows
 * come from. app/helpers/records.php builds the workbook and the plain copy
 * from this list, the Ops wizard and Your records offer it, and the verify
 * page reads a record's facts with it, so a sheet is added in one place.
 *
 * Each sheet:
 *   title    the tab (Excel allows 31 characters)
 *   table    the Excel table's name: totals at the top of the sheet use it
 *   group    where the wizard lists it (RECORDS_GROUPS)
 *   about    one line on the Contents page
 *   noun     [one, many], for "128 invoices"
 *   sql      rows, with ? for the business and {period:column} where a
 *            period applies (records_sheet_sql() fills both in)
 *   cols     [header, key, type, width]; types are in records_cell()
 *   sum      columns the line under the title adds up (with SUBTOTAL, so
 *            it follows the filters)
 *   lines    the sheet of its lines, which comes with it
 *   verify   the letter in its records' verify codes (null: none)
 *   label    what one record is called on the verify page
 *   link     its live page on Nfelia, read-only (quotes, orders and
 *            bookings have none: their pages let whoever holds the link act)
 *   dated    false: always the whole list, whatever the period
 *   alias    (verifiable sheets) the SQL's name for its table, to read one record
 *   parent   (lines sheets) the column that names the record the line is on
 */

const RECORDS_GROUPS = [
    'people'  => 'People',
    'selling' => 'Selling',
    'money'   => 'Money',
    'stock'   => 'Stock',
    'more'    => 'Planning and your shop',
];

/** Letters in verify codes, and the sheet each comes from. */
const RECORDS_VERIFY = [
    'I' => 'invoices', 'R' => 'receipts', 'P' => 'payments', 'Q' => 'quotes', 'O' => 'orders',
    'D' => 'deliveries', 'E' => 'expenses', 'T' => 'debts', 'B' => 'bookings', 'A' => 'deeds', 'F' => 'refunds',
];

function records_sheets(): array
{
    static $sheets = null;
    if ($sheets !== null) return $sheets;

    $doc  = fn(string $token) => $token !== '' ? APP_URL . '/doc/' . $token : null;
    $mask = fn(?string $name) => records_mask_name($name);

    $sheets = [
        /* ── People ─────────────────────────────────────────── */
        'customers' => [
            'title' => 'Customers', 'table' => 'Customers', 'group' => 'people', 'dated' => false,
            'about' => 'Everyone you sell to, with what they bought, paid and still owe, and money kept for their next order.',
            'noun'  => ['customer', 'customers'],
            // The hidden "Walk-in customer" of quick sales is left out, as in Clients
            'sql'   => "SELECT c.id, c.full_name AS name, c.business_name AS company, c.phone, c.whatsapp, c.email, c.address,
                               c.total_invoiced AS invoiced, c.total_received AS received, c.outstanding_balance AS owed,
                               (SELECT NULLIF(SUM(k.amount), 0) FROM kept_money k WHERE k.business_id = c.business_id AND k.customer_id = c.id) AS kept,
                               c.created_at AS added, c.notes
                        FROM customers c WHERE c.business_id = ? AND c.deleted_at IS NULL AND c.is_walk_in = 0 ORDER BY c.full_name, c.id",
            'cols'  => [['Name', 'name', 'text', 26], ['Company', 'company', 'text', 22], ['Phone', 'phone', 'phone', 16], ['WhatsApp', 'whatsapp', 'phone', 16],
                        ['Email', 'email', 'text', 26], ['Address', 'address', 'long', 32], ['Invoiced', 'invoiced', 'money', 15], ['Received', 'received', 'money', 15],
                        ['Still owes', 'owed', 'money', 15], ['Kept for them', 'kept', 'money', 14], ['Added', 'added', 'date', 13], ['Notes', 'notes', 'long', 36]],
            'sum'   => ['invoiced', 'received', 'owed', 'kept'],
        ],
        'suppliers' => [
            'title' => 'Suppliers', 'table' => 'Suppliers', 'group' => 'people', 'dated' => false,
            'about' => 'Who you buy from, with what you bought, paid and still owe them.',
            'noun'  => ['supplier', 'suppliers'],
            'sql'   => "SELECT s.id, s.full_name AS name, s.business_name AS company, s.phone, s.whatsapp, s.email, s.address,
                               s.total_purchased AS bought, s.total_paid AS paid, s.outstanding_balance AS owed, s.created_at AS added, s.notes
                        FROM suppliers s WHERE s.business_id = ? AND s.deleted_at IS NULL ORDER BY s.full_name, s.id",
            'cols'  => [['Name', 'name', 'text', 26], ['Company', 'company', 'text', 22], ['Phone', 'phone', 'phone', 16], ['WhatsApp', 'whatsapp', 'phone', 16],
                        ['Email', 'email', 'text', 26], ['Address', 'address', 'long', 32], ['Bought', 'bought', 'money', 15], ['Paid', 'paid', 'money', 15],
                        ['You still owe', 'owed', 'money', 15], ['Added', 'added', 'date', 13], ['Notes', 'notes', 'long', 36]],
            'sum'   => ['bought', 'paid', 'owed'],
        ],
        'team' => [
            'title' => 'Team', 'table' => 'Team', 'group' => 'people', 'dated' => false,
            'about' => 'The people who use your Nfelia, and when they last signed in.',
            'noun'  => ['person', 'people'],
            'sql'   => "SELECT u.id, u.full_name AS name, u.email, u.phone, u.role, IF(u.is_active = 1, 'Active', 'Left') AS state,
                               u.created_at AS joined, u.last_login_at AS last_seen
                        FROM users u WHERE u.business_id = ? ORDER BY u.is_active DESC, FIELD(u.role, 'owner', 'admin', 'staff'), u.full_name",
            'cols'  => [['Name', 'name', 'text', 26], ['Email', 'email', 'text', 28], ['Phone', 'phone', 'phone', 16], ['Role', 'role', 'role', 12],
                        ['Status', 'state', 'status', 12], ['Joined', 'joined', 'date', 13], ['Last signed in', 'last_seen', 'datetime', 18]],
        ],

        /* ── Selling ────────────────────────────────────────── */
        'quotes' => [
            'title' => 'Quotes', 'table' => 'Quotes', 'group' => 'selling', 'lines' => 'quote_lines',
            'about' => 'Every quote, what it came to and how the customer answered.',
            'noun'  => ['quote', 'quotes'], 'verify' => 'Q', 'label' => 'Quote', 'alias' => 'q',
            'sql'   => "SELECT q.id, q.doc_number AS number, q.issue_date AS issued, q.expiry_date AS expires, c.full_name AS customer, q.status,
                               q.subtotal, q.discount_amount AS discount, q.tax_amount AS tax, q.total, q.signed_by_name AS signed_by, q.signed_at,
                               u.full_name AS made_by, q.notes
                        FROM quotations q LEFT JOIN customers c ON c.id = q.customer_id LEFT JOIN users u ON u.id = q.user_id
                        WHERE q.business_id = ? {period:q.issue_date} ORDER BY q.issue_date, q.id",
            'cols'  => [['Number', 'number', 'text', 16], ['Date', 'issued', 'date', 13], ['Good until', 'expires', 'date', 13], ['Customer', 'customer', 'text', 24],
                        ['Status', 'status', 'quote_status', 16], ['Subtotal', 'subtotal', 'money', 14], ['Discount', 'discount', 'money', 12], ['Tax', 'tax', 'money', 12],
                        ['Total', 'total', 'money', 15], ['Signed by', 'signed_by', 'text', 20], ['Signed on', 'signed_at', 'date', 13], ['Made by', 'made_by', 'text', 18],
                        ['Notes', 'notes', 'long', 30]],
            'sum'   => ['total'],
            'facts' => fn($r) => ['Number' => $r['number'], 'Date' => $r['issued'], 'Customer' => $mask($r['customer']), 'Amount' => $r['total'], 'Status' => records_status('quote_status', $r['status'])],
        ],
        'quote_lines' => [
            'title' => 'Quote lines', 'table' => 'QuoteLines', 'group' => 'selling', 'line_of' => 'quotes', 'parent' => 'qi.quotation_id',
            'about' => 'What each quote was for, one line per item.',
            'noun'  => ['line', 'lines'],
            'sql'   => "SELECT qi.id, q.doc_number AS number, q.issue_date AS issued, qi.description, p.sku, qi.quantity AS qty, qi.unit_price AS price,
                               qi.discount_amount AS discount, qi.subtotal AS line_total
                        FROM quotation_items qi JOIN quotations q ON q.id = qi.quotation_id LEFT JOIN products p ON p.id = qi.product_id
                        WHERE q.business_id = ? {period:q.issue_date} ORDER BY q.issue_date, q.id, qi.sort_order, qi.id",
            'cols'  => [['Quote', 'number', 'text', 16], ['Date', 'issued', 'date', 13], ['Item', 'description', 'long', 40], ['SKU', 'sku', 'text', 12],
                        ['Qty', 'qty', 'qty', 9], ['Price', 'price', 'money', 14], ['Discount', 'discount', 'money', 12], ['Line total', 'line_total', 'money', 15]],
            'sum'   => ['line_total'],
        ],
        'orders' => [
            'title' => 'Orders', 'table' => 'Orders', 'group' => 'selling', 'lines' => 'order_lines',
            'about' => 'Every sale: from your shop and WhatsApp, typed in, from a quote, and quick sales, with what was paid and how each went.',
            'noun'  => ['order', 'orders'], 'verify' => 'O', 'label' => 'Order', 'alias' => 'o',
            'sql'   => records_orders_sql(),
            'cols'  => [['Number', 'number', 'text', 15], ['Placed', 'placed', 'datetime', 17], ['Customer', 'customer', 'text', 22], ['Phone', 'phone', 'phone', 15],
                        ['Email', 'email', 'text', 24], ['From', 'channel', 'channel', 12], ['Delivery or pickup', 'fulfilment', 'fulfilment', 13],
                        ['Address', 'address', 'long', 28], ['Stage', 'stage', 'order_stage', 16], ['Payment', 'pay', 'order_pay', 13],
                        ['Subtotal', 'subtotal', 'money', 14], ['Discount', 'discount', 'money', 12], ['Total', 'total', 'money', 15], ['Paid', 'paid', 'money', 15],
                        ['Still owed', 'balance', 'money', 15], ['Invoice', 'invoice', 'text', 15], ['Receipts', 'receipts', 'long', 20],
                        ['Delivery notes', 'deliveries', 'long', 17], ['From quote', 'from_quote', 'text', 15], ['Done', 'done', 'datetime', 17],
                        ['Why it was cancelled', 'cancel_reason', 'long', 26], ['Their note', 'note', 'long', 28], ['Your note', 'own_note', 'long', 28]],
            'sum'   => ['total', 'paid', 'balance'],
            'facts' => fn($r) => ['Number' => $r['number'], 'Date' => substr((string)$r['placed'], 0, 10), 'Customer' => $mask($r['customer']),
                                  'Amount' => $r['total'], 'Paid' => $r['paid'], 'Still owed' => $r['balance'], 'Status' => records_status('order_stage', $r['stage'])],
        ],
        'order_lines' => [
            'title' => 'Order lines', 'table' => 'OrderLines', 'group' => 'selling', 'line_of' => 'orders', 'parent' => 'oi.order_id',
            'about' => 'What each order was for, one line per item.',
            'noun'  => ['line', 'lines'],
            'sql'   => records_order_lines_sql(),
            'cols'  => [['Order', 'number', 'text', 15], ['Placed', 'placed', 'date', 13], ['Item', 'product_name', 'long', 34], ['Choice', 'variant', 'text', 18],
                        ['Qty', 'qty', 'qty', 9], ['Price', 'price', 'money', 14], ['Line total', 'line_total', 'money', 15], ['Note', 'item_note', 'text', 24]],
            'sum'   => ['line_total'],
        ],
        'invoices' => [
            'title' => 'Invoices', 'table' => 'Invoices', 'group' => 'selling', 'lines' => 'invoice_lines',
            'about' => 'Every invoice: what it came to, what was paid and what is still owed.',
            'noun'  => ['invoice', 'invoices'], 'verify' => 'I', 'label' => 'Invoice', 'alias' => 'i',
            'sql'   => "SELECT i.id, i.doc_number AS number, o.order_number AS order_no, i.issue_date AS issued, i.due_date AS due, c.full_name AS customer,
                               c.phone AS customer_phone, i.status, i.subtotal, i.discount_amount AS discount, i.tax_amount AS tax, i.total, i.amount_paid AS paid,
                               i.balance_due AS balance, i.deliver_by, i.delivered_at, q.doc_number AS from_quote, u.full_name AS made_by, i.payment_terms AS terms,
                               i.notes, i.token
                        FROM invoices i LEFT JOIN customers c ON c.id = i.customer_id LEFT JOIN quotations q ON q.id = i.quotation_id
                        LEFT JOIN users u ON u.id = i.user_id LEFT JOIN orders o ON o.id = i.order_id AND o.business_id = i.business_id
                        WHERE i.business_id = ? {period:i.issue_date} ORDER BY i.issue_date, i.id",
            'cols'  => [['Number', 'number', 'text', 16], ['Order', 'order_no', 'text', 15], ['Date', 'issued', 'date', 13], ['Due', 'due', 'date', 13], ['Customer', 'customer', 'text', 24],
                        ['Phone', 'customer_phone', 'phone', 15], ['Status', 'status', 'invoice_status', 12], ['Subtotal', 'subtotal', 'money', 14],
                        ['Discount', 'discount', 'money', 12], ['Tax', 'tax', 'money', 12], ['Total', 'total', 'money', 15], ['Paid', 'paid', 'money', 15],
                        ['Still owed', 'balance', 'money', 15], ['Deliver by', 'deliver_by', 'date', 13], ['Delivered', 'delivered_at', 'date', 13],
                        ['From quote', 'from_quote', 'text', 15], ['Made by', 'made_by', 'text', 18], ['Terms', 'terms', 'text', 18], ['Notes', 'notes', 'long', 30]],
            'sum'   => ['total', 'paid', 'balance'],
            'link'  => fn($r) => $doc((string)$r['token']),
            'facts' => fn($r) => ['Number' => $r['number']] + records_order_fact($r)
                                 + ['Date' => $r['issued'], 'Customer' => $mask($r['customer']), 'Amount' => $r['total'],
                                    'Paid' => $r['paid'], 'Still owed' => $r['balance'], 'Status' => records_status('invoice_status', $r['status'])],
        ],
        'invoice_lines' => [
            'title' => 'Invoice lines', 'table' => 'InvoiceLines', 'group' => 'selling', 'line_of' => 'invoices', 'parent' => 'ii.invoice_id',
            'about' => 'What each invoice was for, one line per item.',
            'noun'  => ['line', 'lines'],
            'sql'   => "SELECT ii.id, i.doc_number AS number, i.issue_date AS issued, ii.description, p.sku, ii.quantity AS qty, ii.unit_price AS price,
                               ii.discount_amount AS discount, ii.subtotal AS line_total
                        FROM invoice_items ii JOIN invoices i ON i.id = ii.invoice_id LEFT JOIN products p ON p.id = ii.product_id
                        WHERE i.business_id = ? {period:i.issue_date} ORDER BY i.issue_date, i.id, ii.sort_order, ii.id",
            'cols'  => [['Invoice', 'number', 'text', 16], ['Date', 'issued', 'date', 13], ['Item', 'description', 'long', 40], ['SKU', 'sku', 'text', 12],
                        ['Qty', 'qty', 'qty', 9], ['Price', 'price', 'money', 14], ['Discount', 'discount', 'money', 12], ['Line total', 'line_total', 'money', 15]],
            'sum'   => ['line_total'],
        ],
        'bookings' => [
            'title' => 'Bookings', 'table' => 'Bookings', 'group' => 'selling',
            'about' => 'Every booking made on your shop, with its time, deposit and answer.',
            'noun'  => ['booking', 'bookings'], 'verify' => 'B', 'label' => 'Booking', 'alias' => 'b',
            'sql'   => "SELECT b.id, b.booking_number AS number, b.created_at AS booked_on, b.product_name AS what, b.starts_at AS starts, b.ends_at AS ends,
                               b.units, b.total, b.deposit_due AS deposit, b.customer_name AS customer, b.customer_phone AS phone, b.customer_email AS email,
                               b.status, b.channel, b.customer_note AS note, b.owner_message
                        FROM bookings b WHERE b.business_id = ? {period:b.starts_at} ORDER BY b.starts_at, b.id",
            'cols'  => [['Number', 'number', 'text', 13], ['Booked on', 'booked_on', 'date', 13], ['What', 'what', 'text', 24], ['Starts', 'starts', 'datetime', 17],
                        ['Ends', 'ends', 'datetime', 17], ['Nights or units', 'units', 'int', 10], ['Total', 'total', 'money', 14], ['Deposit', 'deposit', 'money', 13],
                        ['Customer', 'customer', 'text', 22], ['Phone', 'phone', 'phone', 15], ['Email', 'email', 'text', 24], ['Status', 'status', 'booking_status', 12],
                        ['From', 'channel', 'channel', 11], ['Their note', 'note', 'long', 26], ['Your answer', 'owner_message', 'long', 26]],
            'sum'   => ['total', 'deposit'],
            'facts' => fn($r) => ['Number' => $r['number'], 'Date' => substr((string)$r['starts'], 0, 10), 'Customer' => $mask($r['customer']),
                                  'Amount' => $r['total'], 'Status' => records_status('booking_status', $r['status'])],
        ],
        'deliveries' => [
            'title' => 'Deliveries', 'table' => 'Deliveries', 'group' => 'selling', 'lines' => 'delivery_lines',
            'about' => 'Delivery notes: who took the goods, when they arrived and who signed for them.',
            'noun'  => ['delivery', 'deliveries'], 'verify' => 'D', 'label' => 'Delivery note', 'alias' => 'd',
            'sql'   => "SELECT d.id, d.doc_number AS number, o.order_number AS order_no, d.created_at AS made, COALESCE(c.full_name, d.recipient_name) AS customer,
                               d.recipient_name AS recipient, d.recipient_phone AS phone, d.delivery_address AS address, d.status, d.provider, d.rider_name AS rider,
                               d.rider_phone, d.dispatch_date AS dispatched, d.actual_delivery AS delivered, d.signoff_name AS signed_by, d.signoff_timestamp AS signed_at,
                               i.doc_number AS invoice, d.tracking_number AS tracking, d.notes, d.token
                        FROM deliveries d LEFT JOIN customers c ON c.id = d.customer_id LEFT JOIN invoices i ON i.id = d.invoice_id
                        LEFT JOIN orders o ON o.id = d.order_id AND o.business_id = d.business_id
                        WHERE d.business_id = ? AND d.deleted_at IS NULL {period:d.created_at} ORDER BY d.created_at, d.id",
            'cols'  => [['Number', 'number', 'text', 15], ['Order', 'order_no', 'text', 15], ['Made', 'made', 'date', 13], ['Customer', 'customer', 'text', 22], ['Received by', 'recipient', 'text', 20],
                        ['Phone', 'phone', 'phone', 15], ['Address', 'address', 'long', 30], ['Status', 'status', 'delivery_status', 15], ['Sent with', 'provider', 'provider', 14],
                        ['Rider', 'rider', 'text', 18], ['Rider phone', 'rider_phone', 'phone', 15], ['Sent out', 'dispatched', 'date', 13], ['Arrived', 'delivered', 'datetime', 17],
                        ['Signed by', 'signed_by', 'text', 20], ['Signed at', 'signed_at', 'datetime', 17], ['Invoice', 'invoice', 'text', 15],
                        ['Tracking', 'tracking', 'text', 16], ['Notes', 'notes', 'long', 28]],
            'link'  => fn($r) => (string)$r['token'] !== '' ? APP_URL . '/delivery/' . $r['token'] : null,
            'facts' => fn($r) => ['Number' => $r['number']] + records_order_fact($r)
                                 + ['Date' => substr((string)$r['made'], 0, 10), 'Customer' => $mask($r['customer']),
                                    'Status' => records_status('delivery_status', $r['status']), 'Signed for' => $r['signed_at'] ? 'Yes, ' . substr((string)$r['signed_at'], 0, 10) : 'Not yet'],
        ],
        'delivery_lines' => [
            'title' => 'Delivery lines', 'table' => 'DeliveryLines', 'group' => 'selling', 'line_of' => 'deliveries', 'parent' => 'di.delivery_id',
            'about' => 'What each delivery carried.',
            'noun'  => ['line', 'lines'],
            'sql'   => "SELECT di.id, d.doc_number AS number, d.created_at AS made, di.description, di.quantity AS qty, di.unit, di.notes
                        FROM delivery_items di JOIN deliveries d ON d.id = di.delivery_id
                        WHERE d.business_id = ? AND d.deleted_at IS NULL {period:d.created_at} ORDER BY d.created_at, d.id, di.sort_order, di.id",
            'cols'  => [['Delivery', 'number', 'text', 15], ['Made', 'made', 'date', 13], ['Item', 'description', 'long', 40], ['Qty', 'qty', 'qty', 9],
                        ['Unit', 'unit', 'text', 10], ['Notes', 'notes', 'long', 28]],
        ],
        'deeds' => [
            'title' => 'Deeds', 'table' => 'Deeds', 'group' => 'selling',
            'about' => 'Deeds of acquisition you gave customers for what they bought.',
            'noun'  => ['deed', 'deeds'], 'verify' => 'A', 'label' => 'Deed of acquisition', 'alias' => 'd',
            'sql'   => "SELECT d.id, d.doc_number AS number, d.issued_at AS issued, c.full_name AS customer, d.total_amount AS total,
                               i.doc_number AS invoice, r.doc_number AS receipt, d.token
                        FROM deeds d LEFT JOIN customers c ON c.id = d.customer_id LEFT JOIN invoices i ON i.id = d.invoice_id
                        LEFT JOIN receipts r ON r.id = d.receipt_id
                        WHERE d.business_id = ? {period:d.issued_at} ORDER BY d.issued_at, d.id",
            'cols'  => [['Number', 'number', 'text', 16], ['Issued', 'issued', 'date', 13], ['Customer', 'customer', 'text', 24], ['Total', 'total', 'money', 15],
                        ['Invoice', 'invoice', 'text', 15], ['Receipt', 'receipt', 'text', 15]],
            'sum'   => ['total'],
            'link'  => fn($r) => (string)$r['token'] !== '' ? APP_URL . '/deed/' . $r['token'] : null,
            'facts' => fn($r) => ['Number' => $r['number'], 'Date' => $r['issued'], 'Customer' => $mask($r['customer']), 'Amount' => $r['total']],
        ],
        'quote_requests' => [
            'title' => 'Quote requests', 'table' => 'QuoteRequests', 'group' => 'selling',
            'about' => 'People who asked for a price on your shop.',
            'noun'  => ['request', 'requests'],
            'sql'   => "SELECT q.id, q.created_at AS received, q.name, q.phone, q.email, q.message, q.status, q.notes
                        FROM quote_requests q WHERE q.business_id = ? {period:q.created_at} ORDER BY q.created_at, q.id",
            'cols'  => [['Received', 'received', 'datetime', 17], ['Name', 'name', 'text', 22], ['Phone', 'phone', 'phone', 15], ['Email', 'email', 'text', 24],
                        ['What they asked', 'message', 'long', 44], ['Status', 'status', 'request_status', 12], ['Your notes', 'notes', 'long', 28]],
        ],
        'reviews' => [
            'title' => 'Reviews', 'table' => 'Reviews', 'group' => 'selling',
            'about' => 'What shoppers said after their order was done.',
            'noun'  => ['review', 'reviews'],
            'sql'   => "SELECT r.id, COALESCE(r.submitted_at, r.created_at) AS on_date, r.customer_name AS customer, r.rating, r.rating AS stars, r.comment, r.status,
                               o.order_number AS order_number
                        FROM reviews r LEFT JOIN orders o ON o.id = r.order_id
                        WHERE r.business_id = ? {period:r.created_at} ORDER BY r.created_at, r.id",
            'cols'  => [['Date', 'on_date', 'date', 13], ['Customer', 'customer', 'text', 22], ['Rating', 'rating', 'int', 9], ['Stars', 'stars', 'stars', 12],
                        ['What they said', 'comment', 'long', 50], ['Status', 'status', 'review_status', 13], ['Order', 'order_number', 'text', 13]],
        ],

        /* ── Money ──────────────────────────────────────────── */
        'payments' => [
            'title' => 'Payments', 'table' => 'Payments', 'group' => 'money',
            'about' => 'Money paid on your invoices, each with its receipt. Money given back is a minus; kept money moved between a customer\'s own orders is not new money.',
            'noun'  => ['payment', 'payments'], 'verify' => 'P', 'label' => 'Payment', 'alias' => 'p',
            'sql'   => "SELECT p.id, p.payment_date AS paid_on, i.doc_number AS invoice, o.order_number AS order_no,
                               (SELECT r.doc_number FROM receipts r WHERE r.business_id = p.business_id AND r.payment_id = p.id ORDER BY r.id LIMIT 1) AS receipt,
                               c.full_name AS customer,
                               CASE WHEN p.method = 'kept' THEN IF(p.amount < 0, 'kept_out', 'kept_in') WHEN p.amount < 0 THEN 'refund' ELSE 'payment' END AS what,
                               p.amount, p.method, p.reference, p.note, u.full_name AS by_name
                        FROM payments p JOIN invoices i ON i.id = p.invoice_id LEFT JOIN customers c ON c.id = i.customer_id LEFT JOIN users u ON u.id = p.user_id
                        LEFT JOIN orders o ON o.id = i.order_id AND o.business_id = i.business_id
                        WHERE p.business_id = ? {period:p.payment_date} ORDER BY p.payment_date, p.id",
            'cols'  => [['Date', 'paid_on', 'date', 13], ['Invoice', 'invoice', 'text', 16], ['Order', 'order_no', 'text', 15], ['Receipt', 'receipt', 'text', 16],
                        ['Customer', 'customer', 'text', 24], ['What', 'what', 'pay_what', 19], ['Amount', 'amount', 'money', 15], ['How', 'method', 'method', 15],
                        ['Reference', 'reference', 'text', 18], ['Note', 'note', 'long', 28], ['Recorded by', 'by_name', 'text', 18]],
            'sum'   => ['amount'],
            'facts' => fn($r) => ['Invoice' => $r['invoice']] + records_order_fact($r)
                                 + ['Receipt' => $r['receipt'] ?: '', 'Date' => $r['paid_on'], 'Customer' => $mask($r['customer']), 'Amount' => $r['amount'],
                                    'How' => records_status('method', $r['method'])],
        ],
        'receipts' => [
            'title' => 'Receipts', 'table' => 'Receipts', 'group' => 'money', 'lines' => 'receipt_lines',
            'about' => 'Every receipt you gave: one for each payment, and for sales on the spot.',
            'noun'  => ['receipt', 'receipts'], 'verify' => 'R', 'label' => 'Receipt', 'alias' => 'r',
            'sql'   => "SELECT r.id, r.doc_number AS number, o.order_number AS order_no, r.issue_date AS issued, COALESCE(c.full_name, 'Walk-in customer') AS customer,
                               i.doc_number AS invoice, r.amount, r.method, r.balance_remaining AS balance, r.note, u.full_name AS by_name, r.token
                        FROM receipts r LEFT JOIN customers c ON c.id = r.customer_id LEFT JOIN invoices i ON i.id = r.invoice_id LEFT JOIN users u ON u.id = r.user_id
                        LEFT JOIN orders o ON o.id = r.order_id AND o.business_id = r.business_id
                        WHERE r.business_id = ? {period:r.issue_date} ORDER BY r.issue_date, r.id",
            'cols'  => [['Number', 'number', 'text', 16], ['Order', 'order_no', 'text', 15], ['Date', 'issued', 'date', 13], ['Customer', 'customer', 'text', 24],
                        ['For invoice', 'invoice', 'text', 16], ['Amount', 'amount', 'money', 15], ['How', 'method', 'method', 15], ['Left to pay', 'balance', 'money', 14],
                        ['Note', 'note', 'long', 28], ['Made by', 'by_name', 'text', 18]],
            'sum'   => ['amount'],
            'link'  => fn($r) => $doc((string)$r['token']),
            'facts' => fn($r) => ['Number' => $r['number']] + records_order_fact($r)
                                 + ['Date' => $r['issued'], 'Customer' => $mask($r['customer']), 'Amount' => $r['amount'],
                                    'How' => records_status('method', $r['method']), 'For invoice' => $r['invoice'] ?: 'A sale on the spot'],
        ],
        'receipt_lines' => [
            'title' => 'Receipt lines', 'table' => 'ReceiptLines', 'group' => 'money', 'line_of' => 'receipts', 'parent' => 'ri.receipt_id',
            'about' => 'What was sold on each receipt for a sale on the spot.',
            'noun'  => ['line', 'lines'],
            'sql'   => "SELECT ri.id, r.doc_number AS number, r.issue_date AS issued, ri.description, p.sku, ri.quantity AS qty, ri.unit_price AS price, ri.subtotal AS line_total
                        FROM receipt_items ri JOIN receipts r ON r.id = ri.receipt_id LEFT JOIN products p ON p.id = ri.product_id
                        WHERE r.business_id = ? {period:r.issue_date} ORDER BY r.issue_date, r.id, ri.sort_order, ri.id",
            'cols'  => [['Receipt', 'number', 'text', 16], ['Date', 'issued', 'date', 13], ['Item', 'description', 'long', 40], ['SKU', 'sku', 'text', 12],
                        ['Qty', 'qty', 'qty', 9], ['Price', 'price', 'money', 14], ['Line total', 'line_total', 'money', 15]],
            'sum'   => ['line_total'],
        ],
        'refunds' => [
            'title' => 'Refunds', 'table' => 'Refunds', 'group' => 'money',
            'about' => 'Money you gave back on an order: its slip number, how it went and the reference, or that proof is kept.',
            'noun'  => ['refund', 'refunds'], 'verify' => 'F', 'label' => 'Refund', 'alias' => 'f',
            // Where the customer asked for it to go, but never their account number
            'sql'   => "SELECT f.id, f.doc_number AS number, o.order_number AS order_no, f.created_at AS started, f.paid_at AS paid_on,
                               COALESCE(c.full_name, o.customer_name) AS customer, f.amount, f.status, f.method, f.reference, f.proof_path IS NOT NULL AS proof,
                               CONCAT_WS(': ', CASE f.payee_kind WHEN 'bank' THEN 'Bank' WHEN 'momo' THEN 'Mobile money' END, NULLIF(f.payee_bank, '')) AS paid_to,
                               i.doc_number AS invoice, f.reason, u.full_name AS by_name
                        FROM refunds f LEFT JOIN orders o ON o.id = f.order_id AND o.business_id = f.business_id
                        LEFT JOIN invoices i ON i.id = f.invoice_id AND i.business_id = f.business_id
                        LEFT JOIN customers c ON c.id = f.customer_id LEFT JOIN users u ON u.id = f.user_id
                        WHERE f.business_id = ? {period:COALESCE(f.paid_at, f.created_at)} ORDER BY COALESCE(f.paid_at, f.created_at), f.id",
            'cols'  => [['Number', 'number', 'text', 15], ['Order', 'order_no', 'text', 15], ['Asked on', 'started', 'date', 13], ['Given back', 'paid_on', 'date', 13],
                        ['Customer', 'customer', 'text', 22], ['Amount', 'amount', 'money', 14], ['Status', 'status', 'refund_status', 18], ['How', 'method', 'method', 14],
                        ['Reference', 'reference', 'text', 18], ['Proof kept', 'proof', 'yes', 10], ['Sent to', 'paid_to', 'text', 20], ['For invoice', 'invoice', 'text', 15],
                        ['Why', 'reason', 'long', 28], ['By', 'by_name', 'text', 18]],
            'sum'   => ['amount'],
            'facts' => fn($r) => ['Number' => $r['number'] ?: ''] + records_order_fact($r)
                                 + ['Date' => substr((string)($r['paid_on'] ?: $r['started']), 0, 10), 'Customer' => $mask($r['customer']), 'Amount' => $r['amount'],
                                    'Status' => records_status('refund_status', $r['status'])],
        ],
        'kept' => [
            'title' => 'Kept money', 'table' => 'KeptMoney', 'group' => 'money',
            'about' => 'Money a customer paid over and you kept for their next order: plus when kept, minus when used or given back.',
            'noun'  => ['entry', 'entries'],
            'sql'   => "SELECT k.id, k.created_at AS on_date, c.full_name AS customer, o.order_number AS order_no, k.amount, k.note, u.full_name AS by_name
                        FROM kept_money k LEFT JOIN customers c ON c.id = k.customer_id LEFT JOIN orders o ON o.id = k.order_id AND o.business_id = k.business_id
                        LEFT JOIN users u ON u.id = k.user_id
                        WHERE k.business_id = ? {period:k.created_at} ORDER BY k.created_at, k.id",
            'cols'  => [['Date', 'on_date', 'date', 13], ['Customer', 'customer', 'text', 24], ['Order', 'order_no', 'text', 15], ['Amount', 'amount', 'money', 15],
                        ['What happened', 'note', 'long', 34], ['By', 'by_name', 'text', 18]],
            'sum'   => ['amount'],
        ],
        'expenses' => [
            'title' => 'Expenses', 'table' => 'Expenses', 'group' => 'money', 'lines' => 'expense_lines',
            'about' => 'What you spent, on what, and what is still to pay.',
            'noun'  => ['expense', 'expenses'], 'verify' => 'E', 'label' => 'Expense', 'alias' => 'e',
            'sql'   => "SELECT e.id, e.doc_number AS number, e.expense_date AS spent_on, e.category, e.description, s.full_name AS supplier,
                               e.amount, e.amount_paid AS paid, e.balance_due AS balance, e.payment_status AS status, e.payment_method AS method, e.reference, e.notes
                        FROM expenses e LEFT JOIN suppliers s ON s.id = e.supplier_id
                        WHERE e.business_id = ? AND e.deleted_at IS NULL {period:e.expense_date} ORDER BY e.expense_date, e.id",
            'cols'  => [['Number', 'number', 'text', 15], ['Date', 'spent_on', 'date', 13], ['Category', 'category', 'category', 16], ['What for', 'description', 'long', 34],
                        ['Supplier', 'supplier', 'text', 20], ['Amount', 'amount', 'money', 15], ['Paid', 'paid', 'money', 14], ['Still to pay', 'balance', 'money', 14],
                        ['Status', 'status', 'expense_status', 11], ['How', 'method', 'method', 14], ['Reference', 'reference', 'text', 16], ['Notes', 'notes', 'long', 26]],
            'sum'   => ['amount', 'paid', 'balance'],
            'facts' => fn($r) => ['Number' => $r['number'], 'Date' => $r['spent_on'], 'Category' => records_status('category', $r['category']),
                                  'Amount' => $r['amount'], 'Status' => records_status('expense_status', $r['status'])],
        ],
        'expense_lines' => [
            'title' => 'Expense lines', 'table' => 'ExpenseLines', 'group' => 'money', 'line_of' => 'expenses', 'parent' => 'ei.expense_id',
            'about' => 'What each expense bought, one line per item.',
            'noun'  => ['line', 'lines'],
            'sql'   => "SELECT ei.id, e.doc_number AS number, e.expense_date AS spent_on, ei.description, ei.quantity AS qty, ei.unit_cost AS price, ei.total AS line_total
                        FROM expense_items ei JOIN expenses e ON e.id = ei.expense_id
                        WHERE e.business_id = ? AND e.deleted_at IS NULL {period:e.expense_date} ORDER BY e.expense_date, e.id, ei.id",
            'cols'  => [['Expense', 'number', 'text', 15], ['Date', 'spent_on', 'date', 13], ['Item', 'description', 'long', 40], ['Qty', 'qty', 'qty', 9],
                        ['Cost each', 'price', 'money', 14], ['Line total', 'line_total', 'money', 15]],
            'sum'   => ['line_total'],
        ],
        'debts' => [
            'title' => 'Debts', 'table' => 'Debts', 'group' => 'money', 'lines' => 'debt_payments',
            'about' => 'Money owed to you and money you owe: balances on orders and invoices, and debts recorded by hand.',
            'noun'  => ['debt', 'debts'], 'verify' => 'T', 'label' => 'Debt', 'alias' => 'd',
            'sql'   => "SELECT d.id, d.created_at AS recorded, d.type, d.description, COALESCE(c.full_name, s.full_name, d.counterparty_name) AS who,
                               COALESCE(c.phone, s.phone, d.counterparty_phone) AS phone, d.total_amount AS total, d.amount_settled AS settled, d.balance,
                               d.due_date AS due, d.status, i.doc_number AS invoice, o.order_number AS order_no, d.note
                        FROM debts d LEFT JOIN customers c ON c.id = d.customer_id LEFT JOIN suppliers s ON s.id = d.supplier_id
                        LEFT JOIN invoices i ON i.id = d.invoice_id LEFT JOIN orders o ON o.id = d.order_id AND o.business_id = d.business_id
                        WHERE d.business_id = ? {period:d.created_at} ORDER BY d.created_at, d.id",
            'cols'  => [['Recorded', 'recorded', 'date', 13], ['Which way', 'type', 'debt_type', 13], ['What for', 'description', 'long', 32], ['Who', 'who', 'text', 22],
                        ['Phone', 'phone', 'phone', 15], ['Total', 'total', 'money', 14], ['Paid so far', 'settled', 'money', 14], ['Still owed', 'balance', 'money', 14],
                        ['Due', 'due', 'date', 13], ['Status', 'status', 'debt_status', 13], ['Invoice', 'invoice', 'text', 15], ['Order', 'order_no', 'text', 15],
                        ['Note', 'note', 'long', 26]],
            'sum'   => ['total', 'settled', 'balance'],
            'facts' => fn($r) => ['Which way' => records_status('debt_type', $r['type'])] + records_order_fact($r)
                                 + ['Recorded' => substr((string)$r['recorded'], 0, 10), 'Who' => $mask($r['who']),
                                    'Amount' => $r['total'], 'Still owed' => $r['balance'], 'Status' => records_status('debt_status', $r['status'])],
        ],
        'debt_payments' => [
            'title' => 'Debt payments', 'table' => 'DebtPayments', 'group' => 'money', 'line_of' => 'debts', 'parent' => 'ds.debt_id',
            'about' => 'Each payment made towards a debt.',
            'noun'  => ['payment', 'payments'],
            'sql'   => "SELECT ds.id, ds.settlement_date AS paid_on, d.description AS debt, COALESCE(c.full_name, s.full_name, d.counterparty_name) AS who, d.type,
                               ds.amount, ds.method, ds.reference, ds.note
                        FROM debt_settlements ds JOIN debts d ON d.id = ds.debt_id LEFT JOIN customers c ON c.id = d.customer_id
                        LEFT JOIN suppliers s ON s.id = d.supplier_id
                        WHERE d.business_id = ? {period:d.created_at} ORDER BY ds.settlement_date, ds.id",
            'cols'  => [['Date', 'paid_on', 'date', 13], ['Debt', 'debt', 'long', 30], ['Who', 'who', 'text', 22], ['Which way', 'type', 'debt_type', 13],
                        ['Amount', 'amount', 'money', 14], ['How', 'method', 'method', 14], ['Reference', 'reference', 'text', 16], ['Note', 'note', 'long', 26]],
            'sum'   => ['amount'],
        ],
        'banks' => [
            'title' => 'Bank accounts', 'table' => 'BankAccounts', 'group' => 'money', 'dated' => false,
            'about' => 'The accounts your invoices ask customers to pay into.',
            'noun'  => ['account', 'accounts'],
            'sql'   => "SELECT b.id, b.bank_name AS bank, b.account_number, b.account_name, IF(b.is_default = 1, 'Yes', '') AS main
                        FROM bank_accounts b WHERE b.business_id = ? AND b.deleted_at IS NULL AND b.is_active = 1 ORDER BY b.sort_order, b.id",
            'cols'  => [['Bank', 'bank', 'text', 24], ['Account number', 'account_number', 'phone', 16], ['Account name', 'account_name', 'text', 30], ['Main account', 'main', 'text', 12]],
        ],

        /* ── Stock ──────────────────────────────────────────── */
        'products' => [
            'title' => 'Products', 'table' => 'Products', 'group' => 'stock', 'dated' => false,
            'about' => 'What you sell, at what price, and how much is in stock.',
            'noun'  => ['product or service', 'products and services'],
            'sql'   => "SELECT p.id, p.sku, p.name, p.category, p.is_service, p.unit, p.sell_price AS price, p.cost_price AS cost, p.stock_qty AS stock,
                               p.low_stock_threshold AS low_at, p.booking_mode, IF(p.is_active = 1, 'On sale', 'Hidden') AS state, p.description, p.created_at AS added
                        FROM products p WHERE p.business_id = ? AND p.deleted_at IS NULL ORDER BY p.category, p.name, p.id",
            'cols'  => [['Name', 'name', 'text', 30], ['SKU', 'sku', 'text', 12], ['Category', 'category', 'text', 16], ['Kind', 'is_service', 'kind', 10],
                        ['Unit', 'unit', 'text', 9], ['Price', 'price', 'money', 14], ['Cost', 'cost', 'money', 14], ['Margin', 'margin', 'formula_money', 14],
                        ['In stock', 'stock', 'qty', 10], ['Low at', 'low_at', 'qty', 9], ['Stock value', 'stock_value', 'formula_money', 15],
                        ['Booked as', 'booking_mode', 'booking_mode', 12], ['Status', 'state', 'status', 10], ['Description', 'description', 'long', 40], ['Added', 'added', 'date', 13]],
            'formulas' => ['margin' => '{price}-{cost}', 'stock_value' => 'IF({kind}="Service",0,{stock}*{cost})'],
            'sum'   => ['stock_value'],
        ],
        'stock_moves' => [
            'title' => 'Stock movements', 'table' => 'StockMovements', 'group' => 'stock',
            'about' => 'Every time stock went in or out, and why.',
            'noun'  => ['movement', 'movements'],
            'sql'   => "SELECT m.id, m.movement_date AS moved_on, p.name AS product, m.type, IF(m.stock_after >= m.stock_before, m.quantity, NULL) AS qty_in,
                               IF(m.stock_after < m.stock_before, m.quantity, NULL) AS qty_out, m.stock_after AS after_qty, m.unit_cost, m.note, u.full_name AS by_name
                        FROM stock_movements m LEFT JOIN products p ON p.id = m.product_id LEFT JOIN users u ON u.id = m.user_id
                        WHERE m.business_id = ? {period:m.movement_date} ORDER BY m.movement_date, m.id",
            'cols'  => [['Date', 'moved_on', 'date', 13], ['Product', 'product', 'text', 30], ['What happened', 'type', 'stock_type', 15], ['In', 'qty_in', 'qty', 9],
                        ['Out', 'qty_out', 'qty', 9], ['Stock after', 'after_qty', 'qty', 11], ['Cost each', 'unit_cost', 'money', 13], ['Why', 'note', 'long', 34],
                        ['By', 'by_name', 'text', 18]],
        ],

        /* ── Planning and your shop ─────────────────────────── */
        'tasks' => [
            'title' => 'Tasks and calendar', 'table' => 'TasksCalendar', 'group' => 'more',
            'about' => 'Your to-dos, appointments and job plans, done or not.',
            'noun'  => ['item', 'items'],
            'sql'   => "SELECT t.id, t.kind, t.title, t.due_date AS due, t.due_time AS at_time, t.end_time AS until_time, c.full_name AS customer,
                               o.order_number AS order_no, i.doc_number AS invoice, t.is_starred AS starred, t.repeat_every AS repeats, a.full_name AS assigned_to,
                               t.done_at, d.full_name AS done_by, pt.title AS part_of, t.notes
                        FROM tasks t LEFT JOIN customers c ON c.id = t.customer_id LEFT JOIN invoices i ON i.id = t.invoice_id
                        LEFT JOIN orders o ON o.id = t.order_id AND o.business_id = t.business_id
                        LEFT JOIN users a ON a.id = t.assigned_to LEFT JOIN users d ON d.id = t.done_by LEFT JOIN tasks pt ON pt.id = t.parent_id
                        WHERE t.business_id = ? AND t.deleted_at IS NULL {period:COALESCE(t.due_date, t.created_at)}
                        ORDER BY COALESCE(t.due_date, DATE(t.created_at)), t.due_time, t.id",
            'cols'  => [['Kind', 'kind', 'task_kind', 12], ['What', 'title', 'long', 34], ['Day', 'due', 'date', 13], ['From', 'at_time', 'time', 8], ['To', 'until_time', 'time', 8],
                        ['Customer', 'customer', 'text', 20], ['Order', 'order_no', 'text', 15], ['Invoice', 'invoice', 'text', 15], ['Starred', 'starred', 'yes', 9],
                        ['Repeats', 'repeats', 'repeat', 11], ['For', 'assigned_to', 'text', 18], ['Done', 'done_at', 'datetime', 17], ['Done by', 'done_by', 'text', 18],
                        ['Part of', 'part_of', 'text', 22], ['Notes', 'notes', 'long', 30]],
        ],
        'posts' => [
            'title' => 'Shop news', 'table' => 'ShopNews', 'group' => 'more',
            'about' => 'News and offers you posted on your shop.',
            'noun'  => ['post', 'posts'],
            'sql'   => "SELECT p.id, p.title, p.status, p.published_at AS published, p.created_at AS written, p.body
                        FROM shop_posts p WHERE p.business_id = ? AND p.deleted_at IS NULL {period:p.created_at} ORDER BY p.created_at, p.id",
            'cols'  => [['Title', 'title', 'text', 34], ['Status', 'status', 'post_status', 12], ['Published', 'published', 'date', 13], ['Written', 'written', 'date', 13],
                        ['Post', 'body', 'long', 60]],
        ],
    ];
    return $sheets;
}

/**
 * The orders sheet's rows: every sale, as the orders hub keeps it
 * (app/helpers/orders.php). An order's invoice and delivery note are found
 * the way the Orders list finds them (order__list_sql()), so its stage reads
 * as order_stage() says it and its payment as order_pay_tag() does: the
 * invoice owns the money, the order only points at it.
 */
function records_orders_sql(): string
{
    return "SELECT o.id, o.order_number AS number, o.created_at AS placed, o.customer_name AS customer, o.customer_phone AS phone, o.customer_email AS email,
                   o.channel, o.fulfillment_type AS fulfilment, o.delivery_address AS address,
                   CASE WHEN o.status = 'rejected' THEN 'turned_down'
                        WHEN o.status = 'cancelled' THEN 'cancelled'
                        WHEN o.status IN ('pending', 'pending_verification') THEN 'new'
                        WHEN o.status = 'fulfilled' OR COALESCE(d.status, '') IN ('delivered', 'signed_off') THEN CONCAT('done_', o.fulfillment_type)
                        WHEN o.status = 'ready' OR COALESCE(d.status, '') IN ('dispatched', 'in_transit') THEN CONCAT('ready_', o.fulfillment_type)
                        ELSE 'confirmed' END AS stage,
                   CASE WHEN i.id IS NULL OR i.status = 'cancelled' THEN NULL
                        WHEN i.amount_paid > i.total THEN 'over' WHEN i.amount_paid >= i.total THEN 'paid'
                        WHEN i.amount_paid > 0 THEN 'part' ELSE 'none' END AS pay,
                   o.subtotal, o.discount_amount AS discount, o.total, i.amount_paid AS paid, i.balance_due AS balance, i.doc_number AS invoice,
                   (SELECT GROUP_CONCAT(r.doc_number ORDER BY r.id SEPARATOR ', ') FROM receipts r
                    WHERE r.business_id = o.business_id AND r.order_id = o.id) AS receipts,
                   (SELECT GROUP_CONCAT(n.doc_number ORDER BY n.id SEPARATOR ', ') FROM deliveries n
                    WHERE n.business_id = o.business_id AND n.deleted_at IS NULL AND (n.order_id = o.id OR n.id = o.delivery_id)) AS deliveries,
                   q.doc_number AS from_quote, o.done_at AS done, o.cancel_reason, o.customer_note AS note, o.notes AS own_note
            FROM orders o
            LEFT JOIN invoices i ON i.id = (SELECT i2.id FROM invoices i2 WHERE i2.business_id = o.business_id AND (i2.order_id = o.id OR i2.id = o.invoice_id)
                                            ORDER BY i2.status = 'cancelled', i2.id DESC LIMIT 1)
            LEFT JOIN deliveries d ON d.id = (SELECT d2.id FROM deliveries d2 WHERE d2.business_id = o.business_id AND d2.deleted_at IS NULL
                                              AND (d2.order_id = o.id OR d2.id = o.delivery_id) ORDER BY d2.status = 'failed', d2.id DESC LIMIT 1)
            LEFT JOIN quotations q ON q.id = o.quotation_id AND q.business_id = o.business_id
            WHERE o.business_id = ? {period:o.created_at} ORDER BY o.created_at, o.id";
}

/** The lines of those orders, in the order they were typed. */
function records_order_lines_sql(): string
{
    return "SELECT oi.id, o.order_number AS number, o.created_at AS placed, oi.product_name, oi.variant_selection AS variant, oi.quantity AS qty,
                   oi.unit_price AS price, oi.line_total, oi.item_note
            FROM order_items oi JOIN orders o ON o.id = oi.order_id
            WHERE o.business_id = ? {period:o.created_at} ORDER BY o.created_at, o.id, oi.sort_order, oi.id";
}

/**
 * The order a record belongs to, as a verify fact: each document's own
 * number leads and its order is named next to it (the orders hub's rule).
 * Nothing for records made without an order.
 */
function records_order_fact(array $row): array
{
    return !empty($row['order_no']) ? ['Order' => (string)$row['order_no']] : [];
}

/**
 * What a record's extract holds besides the record itself, in words for the
 * business deciding whether to share it ('' when nothing more). Tom's call:
 * the record and its money trail, so one yes answers "was it really paid?".
 */
function records_extract_trail(?string $key): string
{
    return match ((string)$key) {
        'invoices' => 'its items, and each payment with its receipt',
        'orders'   => 'its items, its invoice, each payment with its receipt, and its delivery notes',
        default    => !empty(records_sheets()[(string)$key]['lines']) ? 'its items' : '',
    };
}

/**
 * The money trail itself, as [title, columns, rows] parts after the record
 * and its lines (RecordsBook::extract()): an invoice's payments, each with
 * its receipt, or a refund with its slip; an order's invoice, the payments
 * on it and its delivery notes. Parts with nothing in them are left out.
 */
function records_extract_trail_rows(PDO $db, int $business_id, string $key, int $id): array
{
    $rows = function (string $sql, array $args) use ($db) { $s = $db->prepare($sql); $s->execute($args); return $s->fetchAll(); };
    $invoice_id = $key === 'invoices' ? $id : 0;
    $parts = [];
    if ($key === 'orders') {
        $invoice = $rows("SELECT i.id, i.doc_number AS number, i.issue_date AS issued, i.due_date AS due, i.total, i.amount_paid AS paid, i.balance_due AS balance, i.status
                          FROM invoices i
                          WHERE i.business_id = ? AND (i.order_id = ? OR i.id = (SELECT o.invoice_id FROM orders o WHERE o.id = ? AND o.business_id = ?))
                          ORDER BY i.status = 'cancelled', i.id DESC LIMIT 1", [$business_id, $id, $id, $business_id]);
        $invoice_id = (int)($invoice[0]['id'] ?? 0);
        $parts[] = ['Its invoice', [['Invoice', 'number', 'text'], ['Date', 'issued', 'date'], ['Pay by', 'due', 'date'], ['Total', 'total', 'money'],
                                    ['Paid', 'paid', 'money'], ['Still owed', 'balance', 'money'], ['Status', 'status', 'invoice_status']], $invoice];
    }
    if ($invoice_id) {
        $parts[] = ['Payments', [['Date', 'paid_on', 'date'], ['What', 'what', 'pay_what'], ['Amount', 'amount', 'money'], ['How', 'method', 'method'],
                                 ['Reference', 'reference', 'text'], ['Receipt / slip', 'paper', 'text']],
            $rows("SELECT p.payment_date AS paid_on, p.amount, p.method, p.reference,
                          CASE WHEN p.method = 'kept' THEN IF(p.amount < 0, 'kept_out', 'kept_in') WHEN p.amount < 0 THEN 'refund' ELSE 'payment' END AS what,
                          COALESCE((SELECT r.doc_number FROM receipts r WHERE r.business_id = p.business_id AND r.payment_id = p.id ORDER BY r.id LIMIT 1),
                                   (SELECT f.doc_number FROM refunds f WHERE f.business_id = p.business_id AND f.payment_id = p.id ORDER BY f.id LIMIT 1)) AS paper
                   FROM payments p WHERE p.business_id = ? AND p.invoice_id = ? ORDER BY p.payment_date, p.id", [$business_id, $invoice_id])];
    }
    if ($key === 'orders') {
        $parts[] = ['Delivery notes', [['Number', 'number', 'text'], ['Status', 'status', 'delivery_status'], ['Sent out', 'dispatched', 'date'],
                                       ['Arrived', 'delivered', 'date'], ['Signed by', 'signed_by', 'text'], ['Signed on', 'signed_at', 'date']],
            $rows("SELECT d.doc_number AS number, d.status, d.dispatch_date AS dispatched, d.actual_delivery AS delivered, d.signoff_name AS signed_by,
                          d.signoff_timestamp AS signed_at
                   FROM deliveries d
                   WHERE d.business_id = ? AND d.deleted_at IS NULL
                     AND (d.order_id = ? OR d.id = (SELECT o.delivery_id FROM orders o WHERE o.id = ? AND o.business_id = ?))
                   ORDER BY d.id", [$business_id, $id, $id, $business_id])];
    }
    return array_values(array_filter($parts, fn($part) => $part[2]));
}

/**
 * A sheet's SQL and its arguments: the business, then the period (inclusive
 * days) wherever {period:column} asks for it.
 */
function records_sheet_sql(array $sheet, int $business_id, ?string $from, ?string $to): array
{
    $args = [$business_id];
    $sql = preg_replace_callback('/\{period:([^}]+)\}/', function ($m) use ($from, $to, &$args, $sheet) {
        if (($sheet['dated'] ?? true) === false || ($from === null && $to === null)) {
            return '';
        }
        $sql = '';
        if ($from !== null) { $sql .= ' AND DATE(' . $m[1] . ') >= ?'; $args[] = $from; }
        if ($to !== null)   { $sql .= ' AND DATE(' . $m[1] . ') <= ?'; $args[] = $to; }
        return $sql;
    }, $sheet['sql']);
    return [$sql, $args];
}

/**
 * One record of a sheet ($id), or the lines of one record when the sheet is
 * a lines sheet: the same SQL with the period swapped for the record.
 */
function records_sheet_sql_one(array $sheet, int $business_id, int $id): array
{
    $match = isset($sheet['line_of']) ? $sheet['parent'] : $sheet['alias'] . '.id';
    $sql = preg_replace('/\{period:[^}]+\}/', ' AND ' . $match . ' = ?', $sheet['sql'], 1);
    return [$sql, [$business_id, $id]];
}

/**
 * The words Nfelia uses on its own screens for a stored value, so the book
 * says what the app says: invoice and quote statuses, payment methods,
 * expense categories, couriers.
 */
function records_status(string $kind, mixed $value): string
{
    $v = (string)$value;
    if ($v === '') return '';
    static $loaded = false;
    if (!$loaded) {
        // Class constants only: nothing in these controllers runs.
        require_once BASE_PATH . '/app/controllers/ExpenseController.php';
        require_once BASE_PATH . '/app/controllers/DeliveryController.php';
        $loaded = true;
    }
    return match ($kind) {
        'invoice_status'  => function_exists('screen_invoice_tag') ? screen_invoice_tag($v)[0] : ucfirst(str_replace('_', ' ', $v)),
        'quote_status'    => function_exists('screen_quote_tag') ? screen_quote_tag($v)[0] : ucfirst(str_replace('_', ' ', $v)),
        'method'          => $v === 'kept' ? 'Kept money' : (function_exists('screen_method') ? screen_method($v) : ucfirst(str_replace('_', ' ', $v))),
        'category'        => ExpenseController::CATEGORIES[$v] ?? ucfirst(str_replace('_', ' ', $v)),
        'provider'        => DeliveryController::PROVIDERS[$v] ?? $v,
        'delivery_status' => DeliveryController::STATUSES[$v]['label'] ?? ucfirst(str_replace('_', ' ', $v)),
        // An order's stage and payment, as order_stage() and order_pay_tag() word them
        'order_stage'     => ['new' => 'New', 'confirmed' => 'Confirmed', 'ready_pickup' => 'Ready for pickup', 'ready_delivery' => 'On its way',
                              'done_pickup' => 'Collected', 'done_delivery' => 'Delivered', 'turned_down' => 'Turned down', 'cancelled' => 'Cancelled'][$v] ?? ucfirst($v),
        'order_pay'       => ['over' => 'Paid more', 'paid' => 'Paid', 'part' => 'Deposit paid', 'none' => 'Not paid'][$v] ?? $v,
        'pay_what'        => ['payment' => 'Payment', 'refund' => 'Given back', 'kept_out' => 'Kept for next order', 'kept_in' => 'Kept money used'][$v] ?? $v,
        'refund_status'   => ['asked' => 'Waiting for their details', 'details_in' => 'Details in, to send', 'paid' => 'Given back', 'cancelled' => 'Called off'][$v] ?? ucfirst($v),
        'booking_status'  => ['pending' => 'Waiting', 'confirmed' => 'Confirmed', 'declined' => 'Declined', 'cancelled' => 'Cancelled', 'completed' => 'Done'][$v] ?? ucfirst($v),
        'expense_status'  => ['paid' => 'Paid', 'partial' => 'Part paid', 'unpaid' => 'Not paid'][$v] ?? ucfirst($v),
        'debt_status'     => ['active' => 'Open', 'settling' => 'Being paid', 'cleared' => 'Cleared', 'written_off' => 'Written off'][$v] ?? ucfirst($v),
        'debt_type'       => ['receivable' => 'Owed to you', 'payable' => 'You owe'][$v] ?? $v,
        'request_status'  => ['new' => 'New', 'reviewed' => 'Seen', 'converted' => 'Quoted', 'archived' => 'Archived'][$v] ?? ucfirst($v),
        'review_status'   => ['invited' => 'Asked', 'published' => 'Published', 'hidden' => 'Hidden'][$v] ?? ucfirst($v),
        'post_status'     => ['draft' => 'Draft', 'published' => 'Published'][$v] ?? ucfirst($v),
        'stock_type'      => ['stock_in' => 'Stock in', 'stock_out' => 'Stock out', 'adjustment' => 'Count corrected', 'sale' => 'Sold', 'return' => 'Returned'][$v] ?? $v,
        'channel'         => ['cart_pop' => 'Shop', 'site' => 'Shop', 'whatsapp' => 'WhatsApp', 'manual' => 'Typed in', 'quote' => 'From a quote',
                              'quick' => 'Quick sale'][$v] ?? ucfirst($v),
        'fulfilment'      => ['delivery' => 'Delivery', 'pickup' => 'Pickup'][$v] ?? ucfirst($v),
        'task_kind'       => ['task' => 'Task', 'appointment' => 'Appointment'][$v] ?? ucfirst($v),
        'repeat'          => ['day' => 'Every day', 'week' => 'Every week', 'month' => 'Every month'][$v] ?? $v,
        'role'            => ['owner' => 'Owner', 'admin' => 'Admin', 'staff' => 'Staff'][$v] ?? ucfirst($v),
        'booking_mode'    => ['none' => '', 'slot' => 'A time', 'nights' => 'Nights', 'day' => 'A day'][$v] ?? $v,
        'kind'            => $v === '1' ? 'Service' : 'Product',
        'yes'             => $v === '1' ? 'Yes' : '',
        default           => $v,
    };
}

/**
 * A name as the verify page shows it: the first name and the initial of the
 * last ("Adaeze N."), enough to recognise, never the full name of someone's
 * customer. A name of one word is shown as it is.
 */
function records_mask_name(?string $name): string
{
    $parts = array_values(array_filter(preg_split('/\s+/u', trim((string)$name)) ?: [], fn($p) => $p !== ''));
    if (count($parts) < 2) return $parts[0] ?? '';
    return $parts[0] . ' ' . mb_strtoupper(mb_substr(end($parts), 0, 1)) . '.';
}
