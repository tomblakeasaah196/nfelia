# 5. Money and records: debts, expenses, suppliers, reports, records

What a read-through of the money side claimed, and what each claim turned
out to be. Line numbers are from before the fixes.

The phone layout fix for stock purchase lines (11) is in
`assets/css/src/pages.css` and in the rebuilt `assets/css/app.css`.

Profit & Loss now counts cash in and cash out. See "Decided" at the end:
film that screen, Home's money and the Records Book from this version.

## Fixed

| # | Claim | Change | Files |
|---|-------|--------|-------|
| 1 | Switching "Owed to me" / "I owe" keeps the person picked, and both hidden ids are sent, so a customer's id can become a debt's supplier. | Switching sides clears the pick, the search and the results. Only the hidden id for the chosen side is sent (the other is disabled). The server already validated and stored only the id for the chosen type, so it is unchanged. | `views/debts/form.php` |
| 2 | Due date and payment date aren't validated. | Both are checked with `planner_valid_date`. New messages: "Pick the day it is due, or leave it empty." and "Pick the day it was paid." | `app/controllers/DebtController.php` |
| 3 | A debt's page says "Overdue" / "days overdue"; the list says "Late". | It now says "Late" and "N days late" (with "1 day" for one day), counted with `planner_today()` / `planner_days_between()` as the list counts. The WhatsApp reminder uses the same count. | `views/debts/view.php` |
| 4 | A "partial" payment of 0, or of the whole amount or more, is accepted; the payment method isn't validated. | Part payment must be above 0 ("Put in how much you have paid so far, or choose Unpaid.") and below the amount ("That is the whole amount, so choose Paid in Full."). The method must be one of the form's choices ("Choose how you paid from the list."), and an unpaid expense stores none. The expense date is checked as well, since a bad one reached the database. | `app/controllers/ExpenseController.php` |
| 5 | The expense form's "+ Add New Supplier" needs only a name; the Suppliers page needs a phone or email and checks for a duplicate phone. | **Chosen: a name alone is enough everywhere; a phone, when given, can belong to only one supplier.** The Suppliers page no longer asks for a phone or email (its "Phone or email is required" line is gone). The inline add now checks for a duplicate phone and names the supplier: "Mama Bisi already has this number. Search for them above." | `app/controllers/SupplierController.php`, `views/suppliers/form.php`, `app/controllers/ExpenseController.php` |
| 6 | A failed expense save shows the raw exception. | The owner sees "We couldn't save that expense, and nothing was changed. Please try again." The exception goes to Ops → Errors with its trace (`errors_report_throwable`). | `app/controllers/ExpenseController.php` |
| 7 | A loss shows as e.g. "250% margin". | A gap now reads "For every ₦100 that came in, ₦350 went out". The cash basis that followed (see "Decided") dropped "margin" altogether. | `views/reports/profit_loss.php` |
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

## Decided

**What Profit & Loss counts: cash in, cash out, with stock bought on its own
line.** Tom chose this in October 2026, as recommended here. Before, Money
In was payments received but Money Out was every expense *recorded*, paid or
not, so a vendor who restocked on credit saw "Loss" though no money had
left.

Now:

- **Money Out is what was actually paid in the period**, by
  `money_out()` in `app/helpers/money.php`, beside `money_in()`. It has two
  parts:
  - what was paid on an expense when it was recorded, on the expense's
    date;
  - later payments on a part-paid expense, made by paying off its supplier
    debt, on the day of each payment (`debt_settlements.settlement_date`).
    The expense's own "paid" figure includes these, so they are taken off
    its first part and are never counted twice.
- **Unpaid expenses are not money out.** They show under **Money You Owe**,
  which now has two parts: *Unpaid expenses* (every expense with something
  unpaid, with a supplier or not) and *Other debts* (debts typed in on the
  Debts page). Both note: "Not in Money Out until you pay it."
- **Stock bought stays inside Money Out.** The card shows a **Spent on
  stock** line under the amount. I chose *inside* so the headline stays the
  true cash that left. Leaving stock out would also have overstated profit
  for a reseller: her biggest cost is stock, and with stock outside, every
  sale would count as nearly pure profit.
- **The third card is "Left after spending"** (money in less money out), the
  same words as Home. It is no longer "Profit" or "Loss", and there is no
  "margin" on a cash figure. It reads three ways (`money_left_reads()`):
  - **up** (green): "For every ₦100 that came in, you kept ₦40".
  - **stock** (amber): more went out than came in, but stock bought covers
    the gap. "₦180,000 of it went into stock you can still sell". The
    summary starts "Money into stock." and says what was left not counting
    stock.
  - **down** (red): more went out, and not only on stock. "For every ₦100
    that came in, ₦300 went out".
- Throughout the screen, the Day by Day chart, **Where the Money Went** (now
  money out by category, "% of money out") and the PDF at
  `/reports/profit-loss-pdf` follow the same basis and words.
- **Everywhere else agrees:**
  - Home's *Spent this month* and *Left after spending* use `money_out()`.
    Home adds *Of it, on stock* when there was any, and takes the red off
    a gap that stock covers.
  - The Records Book's *Spent* tile and its month by month use the same
    function.
  - The Expenses list's first card is now *All Recorded*, not *Total
    Spent*. It is every expense recorded, paid or not, so it differs from
    Money Out on purpose. *Paid Out* beside it is all-time money out.
- **Pinned by tests:**
  - `tests/reports/period_test.php` (no database) covers how the result
    reads.
  - `tests/reports/money_test.php` (`bash tests/notifications/run.sh
    reports/money`, added to CI) walks through a month: rent paid, stock
    part paid then paid off in two payments across two months, a bill
    unpaid, one expense deleted. It checks the figures on the P&L screen,
    the PDF, Home and the Records Book.

Where it deliberately differs, or still has a gap:

- **The Debts page's "I owe"** counts debts only. An unpaid expense with no
  supplier has no debt, so it is under Money You Owe in the report but not
  on the Debts page.
- **Payments on debts typed in by hand** (either way) are in neither Money
  In nor Money Out. `money_in()` already left out payments on hand-typed
  "owed to me" debts, so the two sides match. If they should count, change
  both together.
- **An expense paid off by editing it** (changing it to "Paid in Full")
  rather than through its debt has no date for the later payment. That
  payment counts on the expense's own date.
- **The `v_dashboard_summary` view's `total_expenses_this_month`** is still
  the recorded figure. Nothing reads it now (Home uses `money_out()`).
- **Later: "profit on what you sold"** (sales less the cost price of what was
  sold, less running costs) is a second figure for later, once cost prices
  are reliably filled in. Not built.

## Round 3: sentence case

These screens no longer use all-caps labels: debts (add and view), expenses
(list, add and view), suppliers (list, add and view), the profit and loss
screen, and Purge data. They now use the app's own styles:

- labels at 14px, as on the order form
- section headings at 16px
- table heads at 13px
- tags in the same words as the lists: Paid, Part paid, Unpaid, Late, Paid
  off, Written off
- the main buttons in the app's button style

Nothing is under 12px.

The wording changed too:

- Text written in capitals or Title Case now reads in sentence case.
- Payment methods print as "POS", "Bank transfer" and so on, and category
  names in sentence case. The old CSS capitals used to hide raw values like
  "pos".
- The Purge page's capitalised jargon is now plain words ("Delete for good",
  "Purge log"). Its heading matches the menu: "Purge data".
- The debt page's tag now matches the debts list ("Part paid", not
  "Settling").

At 384px, each page renders without sideways scroll. This was checked
offline in Chromium, with sample data and long amounts. The expense page's
three amounts now stack on a phone. Before, ₦1,250,000.00 made that page 9px
too wide.

Left as it was:

- The P&L PDF template is unchanged, as asked.
- One error message in `ExpenseController` still says "choose Paid in
  Full". The option now reads "Paid in full", so the message's capitals no
  longer match it. That file wasn't in this round's list.
