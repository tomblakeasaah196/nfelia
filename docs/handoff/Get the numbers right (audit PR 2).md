# Get the numbers right: audit PR 2 (handoff)

The second of the three fix PRs from `docs/AUDIT_2026-10.md`. Every document gets a number that never
clashes, money is applied once and counts the same on Home and in the profit and loss, a client's totals
follow their invoices, stock moves once and comes back exactly, and orders, invoices, deliveries, quotes
and bookings can't be moved on twice. Items: M2–M23, S2–S7, S9–S14, S16–S18, D1, D2, D5, D6.

## What Tom decided (and where it lives)

| Topic | Decision | In the code |
|---|---|---|
| Client totals | Worked out from invoices, payments, direct receipts and manual debts after every money change | `customer_refresh_totals()` in `app/helpers/money.php`; the migration brings every client in line once |
| Cancelling a paid invoice | Blocked until the money is refunded; "Record a refund" is in the invoice's More sheet | `invoice_cancel()`, `invoice_record_refund()` in `app/helpers/invoices.php` |
| Booking deposits | Confirming with "I have the deposit" ticked makes a receipt | `BookingController::confirm`, `bookings.receipt_id` |

## Calls I made that Tom may want to change

- **Out-of-stock goods can still be ordered on the shop.** The shop never shows stock, and many owners
  don't count it, so refusing would turn away real orders. Instead the owner's order page says
  "Out of stock" or "Only 2 in stock" on each line before they approve. Checkout now refuses only what the
  shop page doesn't sell: another shop's product, a switched-off or deleted one, a bookable service, or a
  product left off the shop's list.
- **A refund is a payment of minus the amount** on the invoice, so money in drops in the month it was
  refunded and the invoice goes back to owing. It has no receipt.
- **The deposit box is ticked by default** when the shopper attached a payment screenshot, and unticked
  when they didn't.
- **Deleting a receipt for an invoice payment** removes only the paperwork; the payment stays on the
  invoice. Deleting a walk-in sale's receipt puts its stock back and takes its money off.
- **An unpaid expense with a supplier is a payable as soon as it is saved** (it used to appear only when
  the Debts page was opened). Paying either one updates the other.
- **One live delivery per invoice.** A failed one can be tried again unless its invoice was cancelled or
  has another delivery.
- **Goods switched to a service** have their stock cleared with a line in the stock history.
- **Taking off more stock than there is** is refused, with a pointer to "Set Exact Quantity" for
  correcting the count. A count can now be set to 0.
- **"Need restocking"** counts only products still on sale, on both Home and the Stock page.
- **Purge** skips a client who still has invoices, quotes or receipts (and says so) instead of failing the
  whole batch.
- **New document counters** start after the highest number the business already used for that kind of
  document, so a number is never repeated.
- **WhatsApp buttons** turn a local number into an international one with the business's own country code
  (a Cameroon business's clients get 237), not always 234.

## What changed

**One number generator (M4, M22, D5).** `generate_doc_number()` (`app/helpers/functions.php`) joins the
caller's transaction and locks the counter row, so nothing else can take the same number and a failed save
gives its number back. Invoices, quotes and receipts no longer call the stored procedure that isn't in the
repo. Expenses get numbers again (`EXP`), so they save.

**Money in (M2, M17, M15).** `money_in()` (`app/helpers/money.php`) is payments by their date plus direct
sales by their receipt date. Home, the profit and loss, its six-month trend and its PDF all use it.
"If you collect everything" adds only debts that didn't come from an invoice. A receipt made from a payment
is made once (the payment row is locked) and shows the balance as it was after that payment.

**Payments (M3, M9, M13, M14, M6).** `invoice_record_payment()` locks the invoice, rounds to kobo, refuses
more than the balance and keeps its overdue debt in step. Recording a settlement on a debt now saves it:
an invoice's debt becomes a payment on the invoice, a manual debt a settlement, a supplier's payable updates
its expense.

**Invoices (M11, M18, M20, S2, S5, D1, D2).** Send and Cancel claim the invoice's state first, so a double
tap does nothing the second time. Stock moves by what was actually moved for the invoice
(`stock_settle()` in `app/helpers/stock.php`), never by its current lines, so an invoice that never took
stock puts none back. Editing a sent invoice moves only the difference and updates its open delivery note.
Discounts stay between 0 and the subtotal, VAT between 0 and 100, and every line is rounded before adding up
(quotes too). After a mistake, the invoice, quote, client, supplier, expense and delivery forms keep what was
typed and still post to their own record. New invoices and quotes start at the business's VAT rate.

**Debts (M12).** An overdue invoice raises one debt, ever: a written-off one doesn't come back.

**Orders (M7, S4, S10, S17).** Approving is one transaction that claims the order first: one invoice, stock
moved once, a real payment and receipt for a paid order, under the order's own client (or one matched by
phone, never a deleted one). Cancelling an order cancels its invoice (stock back, delivery closed), and
cancelling the invoice cancels the order.

**Quotes (M8).** Converting claims the quote first, so it makes one invoice.

**Bookings (M21, S7, S14).** Confirm, decline, cancel and done each move a booking on only from the state
it was in. A fixed deposit stays for a service priced 0, as its page says.

**Deliveries (S3, S11, S16).** A delivery note can be made by hand again. Sign-off works once, and never on
a cancelled delivery. A deleted delivery no longer drives the shopper's tracking page or review invite.

**Stock (S6, S9, S12, S13, S18).** Every stock change locks the product row first. Negative prices, costs,
stock and low-stock levels are refused on the product form, quick add and import.

**Purge (D6).** Purging an invoice also removes its payments, receipts, debts and deliveries, puts its stock
back, clears links from orders, deeds and tasks, and redoes the client's totals. Purging a receipt clears
the orders, bookings and deeds that pointed at it; a quote takes its activity with it; a product its photos.
Files are deleted only after the records are gone.

## What deploys automatically

Merging to `main` deploys everything, including the migration `20261019090000_numbers_right.sql`: `EXP`
document numbers, receipts without a client (walk-in sales), `bookings.receipt_id`, and one recalculation
of every client's totals. Nothing to do by hand.

## Not in this PR

- Shop website polish, the review link on the owner's pages, reminders and the rest: PR 3.
- Editing an invoice made from a shop order leaves the order itself as the shopper placed it (its tracking
  page shows what they asked for); the delivery note follows the invoice.

## How it was tested

- `docs/handoff/testkit/numbers_test.js`: 54 checks in Chromium against the local app, one or more per fix
  above, including the "at the same moment" cases (two invoices, two payments, two sends, two approvals, two
  converts, two confirms, confirm and decline, two sign-offs, two stock changes), run three times.
- The existing kits pass: `lockdown_test.js` 78, `invoice_edit_stock_test.js` 15, `order_stock_test.js` 13
  (now refunds the paid order before cancelling its invoice, and checks the order is cancelled too),
  `landing_test.js` 51, `shop_links_test.py` 119, `storefront_test.py` 148, `bookings_test.py` 77,
  `seo_test.py` 46, `selling_test.py` 135, and `ci_step_{2,3,4,5}.sh`.
- The migration was applied to the local database twice (it is safe to run again).
