# 5. Money and records: debts, expenses, suppliers, reports, records

What a read-through of the money side claimed, and what each claim turned
out to be. Line numbers are from before the fixes.

One thing to do before filming: the phone layout fix for stock purchase
lines (11) is in `assets/css/src/pages.css` only. It shows once
`npm run build:css` rebuilds `assets/css/app.css`.

## Fixed

| # | Claim | Change | Files |
|---|-------|--------|-------|
| 1 | Switching "Owed to me" / "I owe" keeps the person picked, and both hidden ids are sent, so a customer's id can become a debt's supplier. | Switching sides clears the pick, the search and the results. Only the hidden id for the chosen side is sent (the other is disabled). The server already validated and stored only the id for the chosen type, so it is unchanged. | `views/debts/form.php` |
| 2 | Due date and payment date aren't validated. | Both are checked with `planner_valid_date`. New messages: "Pick the day it is due, or leave it empty." and "Pick the day it was paid." | `app/controllers/DebtController.php` |
| 3 | A debt's page says "Overdue" / "days overdue"; the list says "Late". | It now says "Late" and "N days late" (with "1 day" for one day), counted with `planner_today()` / `planner_days_between()` as the list counts. The WhatsApp reminder uses the same count. | `views/debts/view.php` |
| 4 | A "partial" payment of 0, or of the whole amount or more, is accepted; the payment method isn't validated. | Part payment must be above 0 ("Put in how much you have paid so far, or choose Unpaid.") and below the amount ("That is the whole amount, so choose Paid in Full."). The method must be one of the form's choices ("Choose how you paid from the list."), and an unpaid expense stores none. The expense date is checked as well, since a bad one reached the database. | `app/controllers/ExpenseController.php` |
| 5 | The expense form's "+ Add New Supplier" needs only a name; the Suppliers page needs a phone or email and checks for a duplicate phone. | **Chosen: a name alone is enough everywhere; a phone, when given, can belong to only one supplier.** The Suppliers page no longer asks for a phone or email (its "Phone or email is required" line is gone). The inline add now checks for a duplicate phone and names the supplier: "Mama Bisi already has this number. Search for them above." | `app/controllers/SupplierController.php`, `views/suppliers/form.php`, `app/controllers/ExpenseController.php` |
| 6 | A failed expense save shows the raw exception. | The owner sees "We couldn't save that expense, and nothing was changed. Please try again." The exception goes to Ops → Errors with its trace (`errors_report_throwable`). | `app/controllers/ExpenseController.php` |
| 7 | A loss shows as e.g. "250% margin". | A profit still reads "40% margin · For every ₦100 earned, you kept ₦40". A loss now reads "For every ₦100 that came in, ₦350 went out". | `views/reports/profit_loss.php` |
| 8 | "Invoices issued" includes cancelled invoices. | Cancelled invoices and drafts (which were never issued) are left out, matching the Records Book (`app/helpers/records.php`). | `app/controllers/ReportController.php` |
| 10 | Only Records Books refresh by themselves; a plain copy stays on "Making it…". | The page now checks for updates while a book *or* a plain copy is being made. When either is ready (or fails), the page reloads. | `views/records/index.php` |
| 11 | At 384px a stock purchase line's total cell is about 60px wide. | On a phone, quantity and unit cost share the second row (half each) and the line total gets the third row, beside the bin (about 256px). Wider screens are unchanged. | `views/expenses/form.php` (class `nf-buy-line`), `assets/css/src/pages.css` |

## Not a bug

| # | Claim | Why |
|---|-------|-----|
| 1 (server half) | "On the server use only the id that matches the chosen type." | `save_debt` already did: it checks a customer only for "Owed to me" and a supplier only for "I owe", and stores only that one. The bug was the form carrying the pick across. |
| 2 (notes) | Debt notes are read but never saved. | The debt form has no notes field, so nothing a person types is lost. `$notes` in `save_debt` is an unused leftover. The `debts.note` column holds system remarks such as "[Invoice cancelled]", and no screen shows it. Adding a notes box would be a small new feature, not a fix. |
| 4 (browser checks) | The expense form's `submitForm` (`el.submit()`) skips the browser's required checks. | The browser runs its required checks *before* it fires the `submit` event. An invalid form never reaches `submitForm`, so the checks still apply. `el.submit()` only skips checking a second time. |
| 9 | Nothing links to the server PDF; the button builds the PDF in the browser from a CDN. | The CDN is allowed: `.htaccess`'s policy lists `https://cdnjs.cloudflare.com` in `script-src`, the policy is report-only for now, and `php tests/ops/policy_test.php` passes for `views/reports/profit_loss.php`. The CSS has no `color-mix()` or `oklch()` colours, which html2canvas can't read. So the browser way works. `/reports/profit-loss-pdf` is still routed and would give a tidier, branded PDF. Pointing the button at it is a choice, not a fix. `docs/ops/SECURITY.md` §2.4 already plans to serve html2pdf.js from `assets/vendor`. |

## Needs a decision

**What Profit & Loss counts (ReportController ~39 vs ~42).** Money In is
payments *received* in the period. Money Out is every expense *recorded* in
the period, whether it was paid or not, and including stock bought. A vendor
who restocks 20 bundles on credit this week sees "Loss", though no money
left and the hair is still on the shelf.

The options:

- **Cash in, cash out (recommended for now).** Count Money Out as what was
  actually paid in the period, as Money In already is. Show stock bought as
  its own line ("Spent on stock") under the headline, so a restock week reads
  "you put ₦X into stock" rather than "you lost ₦X". This is how a market
  trader already thinks about money: what came in and what went out. Debts
  and unpaid expenses already appear below it under "Money you owe".
  One gap: a part-paid expense's later payments are dated on the debt
  (`debt_settlements.settlement_date`), not on the expense, so the query
  must read them from there.
- **Profit on what was sold.** Sales minus the cost of the items sold
  (`products.cost_price` × quantity sold) minus running costs (expenses
  other than stock). This is the true profit, and stock bought counts only
  when it sells. It is more work, and it is wrong wherever a cost price is
  missing or out of date.
- **Leave it as it is, with clearer labels.** Rename the cards "Money in
  (paid to you)" and "Spending recorded (paid or not)". This is the cheapest
  option, but a restock still reads as a loss.

My view: cash in and out, with stock bought on its own line, now. Add
"profit on what you sold" later as a second figure, once cost prices are
reliably filled in. Either way, the owner should decide before the tutorial
films this screen, because the headline number changes.
