# Orders hub (handoff)

Every sale is now an order, and the order page is where a sale is run: its invoice, payments and receipts,
delivery, tasks, messages, refunds and history. One order number follows the sale everywhere: Tasks,
Calendar, WhatsApp, invoices, receipts, delivery notes, the customer's pages and PDFs, alerts and Debts.
This fixes the bug that started it: an order showed `ORD-0001` while Tasks and its WhatsApp message said
"your order CAS-INV-002". The spec, with the data model and money rules, is `docs/ORDERS_HUB.md`.

## What Tom decided (and where it lives)

| Topic | Decision | In the code |
|---|---|---|
| Numbers | Each document's own number leads (CAS-INV-403). The order number is referenced next to it wherever possible (Order CAS-ORD-704) | `order_number_of()` in `PublicController`; the meta lines in `views/invoices/view.php`, `views/receipts/view.php`, `views/deliveries/view.php`; public pages and PDF templates |
| Every sale is an order | Yes. Quick sale makes the order, invoice and receipt in one go. Orders replaced Sales in the bottom bar | `OrderController::quick`, `order_create()`; `views/layouts/app.php` (tabs and the + sheet) |
| Prefix | Taken from the business name, changeable any time (2 to 5 letters or digits). Only new numbers change, counting carries on, and a new prefix is suggested after a rename | Settings › Document numbers (`views/settings/index.php` `#numbers`); `SettingsController::doc_prefix`, `doc_prefix_keep`; `doc_prefix_problem()`, `derive_prefix()` |
| Old records | Keep their numbers. Past shop orders are linked to their documents; old standalone invoices show "No order" | the backfill in `db/migrations/20261023090000_orders_hub.sql` |
| Orders tab | Holds the Orders · Quotes · Invoices · Receipts lists | `views/partials/orders_head.php` |
| Invoice | Made when the order is confirmed, with a pay-by date and reminders, so balances show in Debts. Choice reads "Pay later", tag reads "Balance ₦70,000" | `order_confirm()`, `order_set_pay_by()`, `order_sync_debt()` |
| Per order | One invoice, many payments (each with its receipt), many deliveries | `order_record_payment()` |
| Order changes midway | Edit the order. The invoice keeps its number, the deposit stays, and if more was paid than the new total the owner gives it back or keeps it for the next order | `order_update_lines()`, `order_overpaid()`, `order_keep_overpaid()` |
| Order page | One page with the next step on top; a Log button opens the chat-style timeline | `views/orders/view.php`, `order_next_action()`, `order_events` |
| Part payment | The balance is in Debts straight away, even before the goods leave | `order_sync_debt()` |
| Debts | Keep the name "Debts"; inside, "Owed to me" and "I owe" replace receivable and payable | `views/debts/*`, `DebtController` |
| Kept money | Offered on the customer's next order with one tap | `kept_balance()`, `order_use_kept()`; the callout on the order page and the form |
| Refunds | Owner or admin. One tap emails the customer a link to give their bank or mobile money details; the owner records the refund with a screenshot, a receipt (image or PDF) or a transaction reference (cash needs none), and the refund slip shows it | `refund_ask()`, `refund_save_details()`, `refund_record()`; `PublicController::refund`; `views/public/refund.php` |

## Calls I made that Tom may want to change

- **Orders typed in by the owner start Confirmed.** Shop orders still wait for Confirm.
- **An accepted quote makes its order automatically**, whether the owner marks it accepted or the customer
  signs online. The alert for a signed quote says "Order CAS-ORD-713 is made" and opens the order.
- **A walk-in quick sale** uses one hidden "Walk-in customer" per business. Pay later or part payment needs a
  name and a phone, unless an existing client is picked.
- **Staff can create, confirm and move orders forward, and record payments.** Refunds, keeping money,
  cancelling and changing items or prices are for owners and admins. The prefix is the owner's alone.
- **Number format** stays the generator's own: PREFIX-TYPE-NNN with at least 3 digits (CAS-ORD-704).
- **Bookings take the prefix too** (CAS-BKG-004) but stay separate from orders.
- **Alerts keep the document number up front** ("₦30,000 received · CAS-INV-403"), name the order in the
  message, and open the order's money section.
- **Purge:** purging an order takes everything it holds (invoice, payments, receipts, delivery notes, debt,
  refunds, kept money, log). An order's invoice can't be purged on its own; the purge says which order to
  purge instead. Old standalone invoices purge as before.
- **What can't be deleted on its own any more:** a receipt for a payment on an order (it stays with its
  payment) and a quote that made an order (it stays as that order's record).
- **The old "receipt only" sale form is gone.** Its links open Quick sale, and New invoice opens New order.
- **Client pages** open on the client's orders, each led by its order number, with a note when money is kept
  for their next order.

## What changed

- **Order core** (`app/helpers/orders.php`): creating, confirming, paying, editing, moving forward, cancelling
  and turning down an order; kept money; refunds; lists and counts for the screens; quote to order. Every
  change is logged in `order_events` (the Log). Stock moves only under the invoice.
- **Screens** (`OrderController`, `views/orders/*`, `assets/js/orders.js`, `assets/css/src/orders.css`): the
  Orders list (search; All, To confirm, In progress, Balance and Done; the amount still owed), the order
  page, New order and Quick sale (one form), Edit items. Long explanations sit behind (i) icons; icon
  buttons show their name on a long press.
- **Tasks and Calendar** (`app/helpers/planner.php`, `TasksController`): deliveries, collections and money due
  speak in order numbers and open the order. "Hello Tom-Blake, this is RN Afriluxe. Your order CAS-ORD-704
  will be delivered tomorrow."
- **Documents**: invoice, receipt and delivery pages, public pages and PDFs show "Order: CAS-ORD-704";
  "Commercial Invoice" now reads "Invoice". Payments on an order's invoice make their receipt and go
  through the order.
- **Home**: the latest orders, search finds orders, and "still owed" opens the orders with a balance.
- **Debts**: rewritten for phones, with Owed to me / I owe; an order's debt opens the order.
- **Clients**: an Orders tab, New order, the kept money note; the walk-in customer is hidden from lists and
  pickers.
- **Alerts**: payment, overdue and signed-quote alerts name the order and open it.

## What deploys automatically

Merging to `main` deploys the code and runs `20261023090000_orders_hub.sql`. It adds the new columns and
tables, seeds the ORD, BKG and RFD counters from the old sequences (so numbers never repeat), and links
past shop orders to their invoices, receipts, delivery notes, debts and tasks. It is safe to run again.
Nothing to do by hand. Existing numbers stay as they are; new ones use each business's prefix.

## How it was tested

<!-- filled in after the final run -->
