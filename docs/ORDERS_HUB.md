# Orders hub: build spec

Every sale starts as an order. The order page is the heart of sales: its invoice, payments and
receipts, delivery, tasks, messages and history all hang off it. This file is the contract the
work is built against. Decisions are Tom's (October 2026); "Calls" are defaults Tom may change.

## 1. Decisions

| # | Topic | Decision |
|---|---|---|
| 1 | Numbers | Each document's own number leads (RNA-INV-0032). The order number is shown as a reference on every document and message that belongs to an order ("Order RNA-ORD-0012"). |
| 2 | Every sale is an order | Yes. Quick sale = one screen, makes order + invoice + receipt, already done. Receipts are made automatically for every payment. |
| 3 | Prefix | Editable any time in Settings, 2 to 5 letters or digits. Only new numbers change; counting carries on. After a rename we suggest a new one. |
| 4 | Old records | Keep every number as issued. Link past shop orders to their documents. Old standalone invoices show "No order". |
| 5 | Navigation | Orders replaces Sales in the bottom bar. The Orders tab has switches: Orders · Quotes · Invoices · Receipts. |
| 6 | Invoice | Made automatically when the order is confirmed. Pay-by date and reminders can be set so balances are tracked in Debts. |
| 7 | Per order | One invoice, many payments (each with its own receipt), many deliveries. |
| 8 | Order page | One page, next step on top. A Log button opens the full timeline (chat-style). |
| 9 | Words | "Pay later" for the choice; "Deposit paid" and "Balance ₦70,000" for tags. No "credit", no "receivable". |
| 10 | Part payment | A balance lands in Debts straight away, even before the goods leave (the website promises "who owes me"). Pay-by date defaults to the delivery or collection date, else 7 days. |
| 11 | Order changes | Edit the order any time before it is done. Invoice keeps its number and updates; the deposit stays; stock adjusts; balance recalculates. If more was paid than the new total: "Give it back" or "Keep for next order". The log keeps the old version. |
| 12 | Debts name | Keep "Debts". Inside, "Owed to me" and "I owe" replace Receivable and Payable. |
| 13 | Kept money | Offered on the customer's next order: "Ada has ₦10,000 kept with you · Use it". |
| 14 | Refunds | Owner or admin. One tap "Ask for account details" emails the customer (and offers a WhatsApp message) a link to a small form for their bank or mobile money details. When the owner has paid, they record the refund with proof: a screenshot or receipt (image or PDF) or a transaction reference. Cash needs no proof. The refund slip shows the reference or proof and goes to the customer. |

Calls (defaults taken without asking):

- Owners see the customer's own step words: New, Confirmed, Ready for pickup / On its way, Collected / Delivered, plus Turned down and Cancelled. Payment is a separate tag: Paid, Deposit paid, Not paid.
- Orders typed in by the owner start Confirmed. Shop orders still wait for Confirm.
- An accepted quote (owner marks it accepted, or the customer signs online) makes the order automatically.
- A walk-in quick sale uses one hidden "Walk-in customer" per business. Pay later or part payment needs a real name and phone (nobody can chase a walk-in).
- Bookings take the prefix too (RNA-BKG-004) but stay separate from orders.
- Staff can create, confirm and move orders forward, and record payments. Refunds, keeping money, cancelling and changing prices are for owners and admins.
- Number format stays the generator's own: PREFIX-TYPE-NNN, at least 3 digits (RNA-ORD-013), as the marketing page already shows (IVL-ORD-018).

## 2. Data model

One migration, `db/migrations/20261023090000_orders_hub.sql`. Every statement is safe to run twice
(the convention in `20261021090000_numbers_right.sql`: `ADD COLUMN IF NOT EXISTS`, `ADD INDEX IF NOT
EXISTS`, `MODIFY` to the final shape, `CREATE TABLE IF NOT EXISTS`, updates guarded by `WHERE ... IS NULL`).
No foreign keys (the existing links have none; purge cleans links itself).

`orders`
- `channel` ENUM('cart_pop','whatsapp','manual','quote','quick') NOT NULL DEFAULT 'cart_pop'
- `status` ENUM('pending','pending_verification','approved','rejected','processing','ready','fulfilled','cancelled') NOT NULL DEFAULT 'pending'
  - `ready` = "Ready for pickup" (pickup) or "On its way" (delivery). `processing` is kept for old rows and means Confirmed.
- `customer_phone` VARCHAR(30) NULL DEFAULT NULL (walk-in sales have none)
- `subtotal`, `total` DECIMAL(15,2)
- ADD `quotation_id` INT NULL, `created_by` INT NULL, `notes` TEXT NULL (the owner's own note, never shown to the customer),
  `discount_amount` DECIMAL(15,2) NOT NULL DEFAULT 0, `edited_at` DATETIME NULL, `done_at` DATETIME NULL,
  `cancel_reason` VARCHAR(300) NULL, `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
- KEYs: (business_id, status, created_at), (business_id, customer_id), (quotation_id)

`order_items`: `product_name` VARCHAR(500) NOT NULL, `unit_price` DECIMAL(15,2), `quantity` DECIMAL(15,2) NOT NULL DEFAULT 1,
`line_total` DECIMAL(15,2), ADD `sort_order` INT NOT NULL DEFAULT 1.

`order_id` INT NULL plus KEY (business_id, order_id) on: `invoices`, `receipts`, `deliveries`, `debts`, `tasks`.

`payments.method` and `receipts.method`: add 'kept' (money moved between a customer's orders, never new money).

`document_counters.doc_type` ENUM('QUO','INV','REC','DEL','DEA','EXP','ORD','BKG','RFD').
Seed: `INSERT INTO document_counters (business_id, doc_type, last_number) SELECT id, 'ORD', order_number_seq FROM businesses
ON DUPLICATE KEY UPDATE last_number = GREATEST(last_number, VALUES(last_number))`; the same for BKG from `booking_number_seq`.
(Required: the generator's fallback `LIKE '%-ORD-%'` never matches the old 'ORD-0001', so an unseeded counter restarts at 1.)

`businesses`: ADD `prefix_nudge_seen` VARCHAR(6) NULL (the suggestion the owner already said no to).

`customers`: ADD `is_walk_in` TINYINT(1) NOT NULL DEFAULT 0. Hidden from Clients lists, search and pickers.

New tables:

```sql
order_events (id INT AI PK, business_id INT NOT NULL, order_id INT NOT NULL, kind VARCHAR(30) NOT NULL,
  text VARCHAR(500) NOT NULL, data TEXT NULL, user_id INT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY (order_id, id), KEY (business_id, created_at))
refunds (id INT AI PK, business_id INT NOT NULL, order_id INT NULL, invoice_id INT NULL, customer_id INT NULL,
  doc_number VARCHAR(30) NULL, status ENUM('asked','details_in','paid','cancelled') NOT NULL DEFAULT 'asked',
  amount DECIMAL(15,2) NOT NULL, method ENUM('cash','bank_transfer','mobile_money','pos','other') NULL,
  reference VARCHAR(100) NULL, proof_path VARCHAR(255) NULL, reason VARCHAR(300) NULL,
  token CHAR(32) NOT NULL, asked_at DATETIME NULL, asked_email TINYINT(1) NOT NULL DEFAULT 0,
  payee_kind ENUM('bank','momo') NULL, payee_name VARCHAR(150) NULL, payee_bank VARCHAR(100) NULL, payee_number VARCHAR(40) NULL,
  details_at DATETIME NULL, payment_id INT NULL, paid_at DATETIME NULL, user_id INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE (token), UNIQUE (business_id, doc_number), KEY (business_id, order_id))
kept_money (id INT AI PK, business_id INT NOT NULL, customer_id INT NOT NULL, order_id INT NULL, payment_id INT NULL,
  amount DECIMAL(15,2) NOT NULL,   -- + kept, - used or given back
  note VARCHAR(255) NULL, user_id INT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY (business_id, customer_id))
```

Backfill (each guarded by `order_id IS NULL`):
- `invoices.order_id` from `orders.invoice_id`
- `receipts.order_id` from `orders.receipt_id`, then from `receipts.invoice_id` of an order's invoice
- `deliveries.order_id` from `orders.delivery_id`, then from `deliveries.invoice_id` of an order's invoice
- `debts.order_id`, `tasks.order_id` from their `invoice_id` of an order's invoice

`orders.invoice_id`, `receipt_id`, `delivery_id` stay as mirrors (first of each) so older code and the tracking page keep working.

Add the three new tables to `FX_TABLES` in `tests/notifications/fixture.php` (deleted before `orders`).

## 3. Money rules (never broken)

- The invoice is the only owner of money. `orders.total` is never summed into money figures.
- `money_in()` and `money_in_by_month()` leave out payments with method 'kept' (kept money is not new money).
- Stock moves only under ('invoice', id) through `stock_settle()`. Never under an order reference.
- A payment on an order's invoice always makes a receipt (REC) with `order_id`, `invoice_id`, `payment_id`.
- `invoice_record_refund()` sets status 'paid' when what is left paid still covers the total (today it says part paid).
- One receivable debt per order invoice while it has a balance: `source='invoice'`, `invoice_id`, `order_id`,
  `total_amount = invoice total`, `amount_settled = amount paid`, `balance = balance due`, `due_date = pay-by date`,
  description "Balance on order RNA-ORD-012". Cleared at 0, written off when the invoice is cancelled.
  `invoice_sync_debt()` keeps working on it unchanged. Overdue-invoice sync must not make a second debt for the same invoice.

## 4. Numbering

- `generate_doc_number()` takes 'ORD', 'BKG', 'RFD' too. Seeding map: ORD → `orders.order_number`,
  BKG → `bookings.booking_number`, RFD → `refunds.doc_number`. Format unchanged: `PREFIX-TYPE-%03d`.
- Shop checkout (`PublicController::place_order`) and every new order use `generate_doc_number(..., 'ORD')` inside
  their transaction. Bookings use 'BKG'. `order_number_seq` and `booking_number_seq` are no longer bumped.
- `doc_prefix_problem(string $prefix): ?string`: 2 to 5 characters, A to Z and 0 to 9 only, not one of
  ORD INV REC DEL QUO EXP DEA BKG RFD. Plain-words message, or null when fine.
- `derive_prefix()` always returns something `doc_prefix_problem()` accepts (pad short names with X; change a reserved word).
- Settings › Business › "Document numbers" card (owner and admin): the prefix field, a live preview of the next
  order, invoice, receipt and delivery numbers, a Save button, and an (i) that explains in one sheet that papers
  already sent keep their number and counting carries on. After a rename, when `derive_prefix(new name)` differs from
  the prefix and from `prefix_nudge_seen`, the card shows "Your papers start with CAS. Use RNA?" [Use RNA] [Keep CAS].
- The session copy of `doc_prefix` is refreshed after a change.

## 5. Order references (document leads, order referenced)

Wherever a document has `order_id`, it shows "Order RNA-ORD-012" next to its own number:
- In the app: invoice, receipt, delivery note and refund slip pages (the meta line, linking to the order).
- For the customer: public invoice/receipt/delivery/sign-off pages and PDFs ("Order: RNA-ORD-012" under the number).
- Messages: WhatsApp and share texts ("Invoice RNA-INV-032 for order RNA-ORD-012"), emails, alerts (an "Order" line and
  the order as the link target), Tasks and Calendar, Debts.
- Order-level messages (on its way, ready, thank you, balance reminder) speak in the order number.
- No more "Auto-generated from order ..." text in customer-facing notes. Old rows are left as they are.

## 6. Order core: `app/helpers/orders.php`

Loaded by `index.php` and `tests/bootstrap.php`. Every write function runs inside the caller's transaction
when there is one, else its own; it locks the order row first (`SELECT ... FOR UPDATE`) so double taps do nothing twice.
Errors for the person are `DomainException` with plain words.

```php
order_find(PDO $db, int $biz, int $order_id, bool $lock = false): ?array
order_by_invoice(PDO $db, int $biz, int $invoice_id): ?array        // invoices.order_id, else orders.invoice_id
order_lines(PDO $db, int $order_id): array
order_invoice(PDO $db, int $biz, array $order): ?array             // its one invoice (latest not cancelled, else latest)
order_log(PDO $db, int $biz, int $order_id, string $kind, string $text, ?int $user_id = null, array $data = []): void
order_events(PDO $db, int $biz, int $order_id): array             // oldest first
order_walk_in_customer(PDO $db, int $biz): int                    // the hidden "Walk-in customer", made once
order_match_customer(PDO $db, int $biz, string $name, ?string $phone, ?string $email): int  // by phone digits, else new
fmt_qty(float $q): string                                         // "1", "2.5"

order_create(PDO $db, int $biz, array $in, int $user_id): array    // returns the order row
  $in: channel manual|quick|quote; customer_id?; customer_name; customer_phone?; customer_email?;
       items [[product_id?, name, quantity, unit_price, note?]]; discount (amount);
       fulfillment now|pickup|delivery; deliver_by? (Y-m-d); delivery_address?;
       paid_now (0..total); method cash|bank_transfer|mobile_money|pos|other; reference?;
       pay_by? (Y-m-d, only when a balance is left; default deliver_by, else +7 days); quotation_id?; note?; use_kept (bool)
  Rules: at least one item; quantity > 0; price >= 0; discount <= subtotal; a balance needs a real customer (not walk-in) with a phone.
  Makes the order (ORD number, public_token, status approved, created_by, currency from business_currency_code),
  then order_confirm() in the same transaction, then for fulfillment 'now' order_set_stage('done').

order_confirm(PDO $db, int $biz, int $order_id, int $user_id, array $opt = []): array
  $opt: payment_received (bool, shop orders with proof; default true when there is proof), paid_now, method, reference,
        deliver_mode immediate|date|null, deliver_by, deliver_show, pay_by, use_kept
  Claims pending/pending_verification → approved (manual orders are already approved and not yet invoiced).
  Makes the invoice (INV, status sent, order_id, due_date = pay_by when a balance is left else today, lines copied with
  sort_order, discount, totals, no notes, deliver_mode/deliver_by/deliver_show), takes stock off, records the shop
  payment or paid_now through order_record_payment(), applies kept money when asked, makes a delivery note for delivery
  orders (order_id, invoice_id, estimated_delivery = deliver_by), syncs the debt, mirrors ids on the order, logs,
  notifies payment.order (shop proof) and tells the shopper 'confirmed'.
  Returns ['invoice_id' => int, 'receipt_id' => ?int, 'delivery_id' => ?int].

order_record_payment(PDO $db, int $biz, int $order_id, float $amount, string $date, string $method, ?string $reference,
                     ?string $note, int $user_id, string $symbol): array   // ['payment_id','receipt_id','new_balance']
  invoice_record_payment() on its invoice, then the receipt (REC, order_id, balance_remaining), sync debt, log, mirror
  orders.receipt_id when empty. Alerts: 'payment.invoice' with order_number added (the actor is left out as today).

order_sync_debt(PDO $db, int $biz, int $order_id, ?string $pay_by = null): void
order_set_pay_by(PDO $db, int $biz, int $order_id, string $date, int $user_id): void

order_stage(array $order, ?array $delivery = null): array   // ['key','words','tone']
  key: new | confirmed | ready | done | turned_down | cancelled. Delivery note dispatched/in_transit counts as ready,
  delivered/signed_off as done. Words: New, Confirmed, Ready for pickup | On its way, Collected | Delivered, Turned down, Cancelled.
  tone is an nf-tag class: new is-warn, confirmed '', ready is-accent, done is-in, turned_down/cancelled is-line.
order_steps(array $order, ?array $delivery = null): array   // stepper: [['key','label','state' => done|now|next]]
  4 steps: New · Confirmed · Ready|On its way · Collected|Delivered.
order_pay_tag(?array $invoice): array   // ['words','tone','balance','paid','overpaid']
  Paid (is-in) · Deposit paid (is-warn) · Not paid (is-owed) · Paid more (is-warn, when paid > total). None while there is no invoice.
order_next_action(array $order, ?array $invoice, ?array $delivery): ?array  // ['key','label','icon']
  new → Confirm order · confirmed → Mark ready (pickup) / Send it out (delivery) · ready → Mark collected / Mark delivered ·
  done with a balance → Record payment · otherwise null.

order_set_stage(PDO $db, int $biz, int $order_id, string $to, int $user_id): void   // to: ready | done | undo_done
  ready: status ready; the delivery note goes dispatched (dispatch_date now). Tells the shopper on_the_way or ready.
  done: status fulfilled, done_at; delivery note delivered (actual_delivery now) unless already signed; the invoice's
        delivery promise is kept (planner_invoice_delivered); review_invite; tells the shopper delivered or collected.
  undo_done: back to confirmed (approved), clears done_at and the invoice's delivered_at (planner_invoice_delivered undo).
  Logs each.
order_from_invoice_delivered(PDO $db, int $biz, int $invoice_id, bool $undo, int $user_id): void
  The Tasks tick and the invoice page call this: an invoice with an order moves the order; else planner_invoice_delivered.
order_sync_from_delivery(PDO $db, int $biz, int $delivery_id): void
  After a delivery note's status changes elsewhere (delivery page, public sign-off): dispatched/in_transit → ready,
  delivered/signed_off → done (with review invite). Never moves a cancelled or turned-down order.

order_turn_down(PDO $db, int $biz, int $order_id, int $user_id, string $reason = ''): void   // pending only → rejected
order_cancel(PDO $db, int $biz, int $order_id, int $user_id, string $reason = ''): string    // '' when cancelled
  With money paid it refuses: "₦X was paid on this order. Give it back first, then cancel." The UI offers a refund.
  Calls invoice_cancel() (stock back, debt written off, delivery closed, tasks), status cancelled, cancel_reason, tells the shopper, logs.
  invoice_cancel() itself also cancels the order found through invoices.order_id (as it does today through orders.invoice_id).

order_update_lines(PDO $db, int $biz, int $order_id, array $items, float $discount, int $user_id, string $symbol): array
  Before done, not cancelled or turned down. Pending orders: lines and totals only. Confirmed: invoice lines replaced,
  invoice subtotal/total/balance/status worked out again (paid when paid >= total), stock settled to the new lines,
  delivery note lines replaced, debt synced, edited_at set, logged with the old lines in data.
  Returns ['overpaid' => float, 'total' => float].
order_overpaid(?array $invoice): float     // max(0, amount_paid - total)
order_keep_overpaid(PDO $db, int $biz, int $order_id, int $user_id, string $symbol): float
  A 'kept' payment of minus the extra on its invoice (amount_paid back to total), kept_money +extra, log.
kept_balance(PDO $db, int $biz, int $customer_id): float
order_use_kept(PDO $db, int $biz, int $order_id, int $user_id, string $symbol): float
  Applies min(kept balance, order balance) as a 'kept' payment (with its receipt, method kept), kept_money -amount, log.
order_unkeep(PDO $db, int $biz, int $order_id, int $user_id, string $symbol): float
  Undo a keep that was not used yet ("Give it back instead"): a 'kept' payment back on the order it came from, so the
  order is overpaid again and the refund flow takes over.

refund_open(PDO $db, int $biz, int $order_id): ?array          // latest asked or details_in
refund_ask(PDO $db, int $biz, int $order_id, float $amount, string $reason, int $user_id): array
  Makes the refund (asked, token); emails the customer the link when they have an email (template refund_details,
  dedupe per refund); returns the refund plus 'url' and 'wa_text'. Amount at most what was paid on the order.
refund_url(string $token): string                               // APP_URL . '/public/refund/' . $token
refund_save_details(PDO $db, string $token, array $in): ?string  // public form; error or null. Notifies 'refund.details'.
refund_record(PDO $db, int $biz, int $order_id, array $in, ?array $file, int $user_id, string $symbol): array
  $in: amount, method, reference?, reason?, refund_id?. Not cash → a reference or a proof file (jpg, png, webp, pdf,
  within MAX_UPLOAD_SIZE_MB, stored privately like payment proofs) is required:
  "Add the transfer reference or a screenshot, so it shows on the refund slip."
  invoice_record_refund() on the order's invoice, refund paid with an RFD number, payment_id, paid_at, log.
```

`shop_order_tell()` learns 'ready' (pickup: "Your order RNA-ORD-012 is ready for pickup"). `shop_order_steps()` reads
the 'ready' status: pickup gets a "Ready for pickup" step; delivery's "On its way" also follows `ready`.

## 7. Screens

House rules: the nf-* system (shell.css, pages.css), plain warm words, buttons start with a verb, nothing under 12px,
no all-caps, 44px taps, icons always with a visible label except the app bar, long help behind an (i) that opens a
small sheet, a named confirm sheet for anything destructive (never `confirm()`), works from 375px to 1280px.

### Bottom bar and navigation
- Tabs: Home · Orders · (+) · Tasks · More. Orders opens `orders`. Invoices, receipts, quotes and orders all belong to the Orders tab.
- `views/partials/orders_head.php` (replaces sales_head.php): title = the list's name (Orders, Quotes, Invoices, Receipts),
  one primary button (New order, New quote, New order, Quick sale), switches Orders · Quotes · Invoices · Receipts.
- (+) sheet: two large tiles first, New order and Quick sale; then New quote, Add task, Add customer, Add product, Add expense.
  "Create invoice" and "Record payment" leave the sheet (both happen inside an order).
- Sidebar: Orders first under "Your business"; it leaves the Storefront group.
- Home: "Latest orders" replaces "Latest invoices"; search finds orders by number, name and phone.
- `invoices/create` sends people to `orders/create` (keeping `?customer=`). A standalone receipt goes to `orders/quick`.

### Orders list (`orders`)
- Search (number, name, phone). Chips with counts: All · To confirm · In progress · Balance · Done.
- Row (`views/partials/order_row.php`): avatar initials, name, "RNA-ORD-012 · Today" with a small channel icon
  (bag = shop, WhatsApp, hand = typed in, bolt = quick sale, quote), amount, stage tag and pay tag. The whole row is a link.
- Empty state with New order and Quick sale.

### Order page (`orders/view/{id}`)
App bar: Back and the order number. Then:
1. Hero card: customer name (link) with stage tag and pay tag; the total, large; a one-line meta (channel and when, "from RNA-QUO-004");
   the paid bar with "₦30,000 paid · Balance ₦70,000 (i)"; a 4-dot stepper with short labels; ONE primary button from
   `order_next_action()`; when there is a balance and the primary isn't payment, a ghost "Record payment" under it.
   New shop orders also get a ghost "Turn down".
2. Action tiles (icon and label): WhatsApp · Call · Log · More.
3. Banners only when they apply: overpaid ("Ada paid ₦10,000 more than the new total" [Give it back] [Keep for next order]);
   kept money available ("Ada has ₦10,000 kept with you" [Use it]); refund in progress (waiting for details / details in with copy buttons / [Record refund]).
4. Folded rows, one line each until tapped (details/summary):
   - Items: "2 items · ₦100,000" → lines (qty × price, note) and "Change items" (owner/admin).
   - Money: "Invoice RNA-INV-032 · Balance ₦70,000" → the invoice row, each payment with its receipt number, refunds,
     kept money, pay-by date (change), Record payment, Give a refund.
   - Delivery or Pickup: "Tomorrow · Lekki" → date (change), address, delivery note and its status, sign-off link to share.
   - Tasks: "1 open" → the order's tasks and Add task.
   - Customer: phone, email, address, the shopper's note, the customer's own page link.
5. Sheets: Confirm order (date, pay-by, payment received switch with the proof), Record payment (amount prefilled with the
   balance, method chips, reference behind "Add reference"), Mark ready / done, Change date, Change items (its own page
   `orders/edit/{id}` is fine), Give a refund (cash: amount and reason; transfer/MoMo: Ask for account details first or
   record with proof), WhatsApp messages (ready texts for the step: confirmed, ready/on its way, balance reminder,
   thank you, share invoice, share receipt; each tap is logged through `orders/wa/{id}?m=key` then opens wa.me),
   More (Change items, Change date, Share invoice PDF, Copy customer's link, Cancel order, Turn down), Log (timeline,
   oldest at top, newest at the bottom like a chat, each event with time and who).

### New order and Quick sale (`orders/create`, `orders/quick`, POST `orders/save`)
One screen, top to bottom: Customer (search by name or phone, or add new: name and phone; Quick sale starts on
Walk-in) · Items (add from stock or type a name and price; qty stepper; remove) · Discount (behind "Add discount") ·
How will they get it? [Collect now] [Pickup later] [Delivery] (+ date chips Today / Tomorrow / Pick, address for delivery) ·
Payment [Paid now] [Part now] [Pay later] (+ amount for part, method chips Cash / Transfer / MoMo / POS, reference behind
"Add reference", pay-by date chips for a balance) · kept-money banner when the customer has some · Note (behind "Add a note").
A sticky bar at the bottom: the total and one button ("Save order", or "Paid ₦50,000" on Quick sale). Quick sale is the
same form preset to Walk-in, Collect now, Paid now, Cash, with Customer and How folded.
Drafts are kept in the browser (sessionStorage) so a phone call doesn't lose the order.

### Debts
Title Debts. Switches "Owed to me" and "I owe". No Receivable, Payable or Net Position words. Order debts read
"Ada Obi · Balance ₦70,000 · RNA-ORD-012 · by Fri 25 Oct" and open the order; settling one records an order payment
(with its receipt). Reminders quote the order number.

### Public refund form (`/public/refund/{token}`)
The shop's name and logo, "RN Afriluxe will send you ₦10,000 for order RNA-ORD-012. Where should it go?"
[Bank account] [Mobile money]; account name, bank or network, number; Send. Then a thank-you. Once paid, the same link
shows the refund slip: number, order, amount, method, reference, date, and the proof when there is one.

## 8. Wave B file ownership

Each agent changes only its own files. New CSS goes into a marked section at the end of `assets/css/src/pages.css`
named after the agent; run `npm run build:css` and commit `assets/css/app.css` only from the merge (the lead rebuilds).

| Agent | Owns |
|---|---|
| B1 Order screens | OrderController.php; views/orders/*; views/partials/order_row.php; assets/js/orders.js (new) |
| B2 Shell and settings | views/layouts/app.php; app/helpers/nav.php (tabs and groups; icons already added); views/partials/sales_head.php → orders_head.php; views/invoices/index.php, views/receipts/index.php, views/quotations/index.php (header only); views/more/index.php; DashboardController.php; views/dashboard/*; SettingsController.php; views/settings/*; assets/js/shell.js; assets/css/src/shell.css |
| B3 Tasks, alerts, debts, deliveries | app/helpers/planner.php; TasksController.php; views/tasks/*; assets/js/planner.js; app/helpers/notify.php; views/emails/* (except refund_details.php); app/helpers/shop_orders.php; DebtController.php; views/debts/*; DeliveryController.php; views/deliveries/index.php, form.php, view.php |
| B4 Documents and clients | QuotationController.php; views/quotations/view.php, form.php; InvoiceController.php; views/invoices/view.php, form.php; ReceiptController.php; views/receipts/view.php, form.php; CustomerController.php; views/customers/*; PurgeController.php; views/purge/*; app/helpers/screens.php |
| B5 Customer-facing | PublicController.php; views/invoices/public.php, pdf_template.php; views/receipts/public.php, pdf_template.php; views/quotations/public.php, pdf_template.php; views/deliveries/public.php, signoff.php; views/public/refund.php (new); views/emails/refund_details.php (new); StorefrontController.php; views/storefront/pages/order.php |
