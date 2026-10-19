# The Records Book, nfelia.com/verify, and Prices (handoff)

A business's records, all of them, in one Excel workbook made in its own colours: the **Records
Book**. Owners make it themselves in the app; the Nfelia team makes it in Ops when a client asks.
Every book, and every invoice, receipt and payment in it, carries a code anyone can check at
**nfelia.com/verify**, where a bank or a landlord can also ask for the full document (the business
decides). Alongside it, **Ops → Prices**: everything Nfelia charges, changed in one place and
applied everywhere, with notice for clients already paying.

## What Tom decided

| # | Topic | Decision |
|---|---|---|
| 1 | Who makes a book | Owners in the app (Your records), and the team in Ops. |
| 2 | Who pays | Free for paying clients, once in each paid month. Otherwise (trial, lapsed, a second one) ₦7,500, a price per country that Ops can change. |
| 3 | The plain copy | Free for everyone, always (the data protection copy): every record as CSV files in a ZIP. Everyone is pushed toward the Records Book, which is also on the home page as a problem it solves, with real pictures of the workbook. |
| 4 | Ops roles | Support makes a book and it goes to the owner; only an Admin can also download it. |
| 5 | Prices | One Ops page for everything charged (a month, each extra person, a Records Book), per country, applied everywhere: the website, sign-up, Billing, Paystack, Mobile Money. |
| 6 | Clients already paying | A notice date, 30 days on by default: a month that starts before it keeps today's price. One button tells clients, in an email written not to drive them away. |
| 7 | Codes and verify | A code (and QR) on the book now, and on every record in it; on invoice and receipt PDFs in a follow-up PR. |
| 8 | Verify page | Shows it is genuine and its key facts, never a customer's phone, email or address. |
| 9 | Asking for the document | The person asks with a reason and confirms their email; only Ops Admins see it; one click asks the client (in the app, on the phone, by email); the client approves or declines; approving sends a download link by itself; the record shows who decided. |
| 10 | The orders hub's records | Refunds and Kept money get their own sheets. Each order's Log stays in the plain copy only. |
| 11 | What an approved ask sends | The record and its money trail: an invoice with its order number, items and each payment with its receipt; an order with its items, its invoice, each payment with its receipt, and its delivery notes. |

## What a Records Book is

`app/helpers/records_book.php` (class `RecordsBook`, PhpSpreadsheet), from the sheets in
`app/helpers/records_sheets.php`:

- **Cover**: the Nfelia logo at the top, the business's name and colours, how long it has been with
  Nfelia, its details and links, four figures, the book's code with a QR code to nfelia.com/verify,
  its logo at the foot, "Powered by Nfelia" and links back to nfelia.com.
- **Contents**: every sheet in the book, linked, with how many records; kinds with nothing in them
  are left out and listed on one line.
- **At a glance**: the totals, every month in a table (invoiced, money in, spent), and a chart.
- **One sheet per kind of record** (customers, quotes, orders, invoices, bookings, deliveries,
  receipts, payments, refunds, kept money, expenses, debts, products, stock movements, tasks…), each
  an Excel table with filters, a line at the top that adds up what the filters show, banded rows, a
  link to each document ("Open ↗") and each record's own verify code. Lines (an invoice's items) have
  their own sheets.
- **About this book**: what it is, how to check it, what the codes mean.

Styles are written onto the cells, not left to table styles, so it looks the same in Excel, Google
Sheets, LibreOffice and Numbers. Customer names on the verify page are masked ("Amaka C.").

**Orders** (the orders hub, `docs/ORDERS_HUB.md`): every sale is an order, so the Orders sheet holds
them all (from the shop and WhatsApp, typed in, from a quote, quick sales) with its stage and payment
in the app's words (`order_stage()`, `order_pay_tag()`) and what was paid on its invoice, which owns
the money. Every invoice, receipt, payment, delivery note, debt and task names its order, and so does
its verify page. In Payments, money given back is a minus ("Given back"), and kept money moving
between a customer's own orders says so ("Kept for next order", "Kept money used") and is left out of
money in, as `money_in()` does. Refunds lists each slip with its reference and where it went (bank or
network, never the account number); Kept money lists each amount kept and used, and Customers shows
what is kept for each. The hidden "Walk-in customer" of quick sales is not listed as a customer.

**Codes**: a book's code is 10 letters and digits in Crockford's base 32 (no I, L, O or U), shown as
`K7Q2M-XA9WT`; a record's is the book's code, a dash, a letter for its kind (I invoice, R receipt,
P payment, Q quote, O order, F refund…) and its id in the same alphabet: `K7Q2MXA9WT-I5C`. Typed codes are
read however they are written (spaces, dashes, lower case, O for 0). A book keeps a ledger
(`storage/private/records/<business>/<id>.ledger.json.gz`) of each record's facts as they were, so
verify can say what has changed since. The file's SHA-256 is kept so verify can say whether an
uploaded file is exactly the one Nfelia made.

**Size**: a book holds at most `RECORDS_MAX_CELLS` cells (600,000 by default, `.env`), about 700 bytes
of memory each while it is made; the build asks PHP for 1 GB where the host allows. Past the limit
the owner is asked to choose a shorter period.

**Kept**: a book 12 months, a plain copy 7 days, then the cron deletes the file. A code from a deleted
book still says it was Nfelia's, but no longer what it said.

## Where it shows

- **App → Your records** (`/records`, owners; open even when the plan has lapsed): make a book
  (choose the period and kinds of records), past books, the plain copy, and requests from people
  who checked a code. Linked from the menu (under Data) and from Billing. Paid books go through
  Paystack (`PaystackController::start_book()`) or a Mobile Money receipt (`kind = 'book'`) that Ops
  approves under Payments to check.
- **Ops → Records Books** (`exports`: Admin and Support): find a client, choose what goes in, say
  why. The owner is told who made it and why, every time, and it is in the audit log. Support's
  books always go to the owner's Your records; only Admins (`exports.download`) can download one,
  and that is logged too. The client page has "Make a Records Book" and lists their books.
- **Ops → Document requests** (`requests`: Admins only): requests from nfelia.com/verify, waiting
  first. "Ask …" asks the client; "Turn down" (with a note the person sees) ends junk without
  bothering them. Requests nobody answers close after 14 days; an approved link lasts 7 days. What
  it downloads is the record as it is today with its money trail
  (`records_extract_trail_rows()`): an invoice's payments, each with its receipt or refund slip; an
  order's invoice, payments and delivery notes. The client's page and both emails say so first.
- **nfelia.com/verify** (`VerifyController`, no sign-in): type or scan a code, or upload a book to
  check it is unchanged. Rate-limited, never cached or indexed, and a honeypot field keeps robots out.
- **Ops → Prices** (`settings`: Admins): each country's prices today, changes to come, every change
  made. A change has two dates: new sign-ups and the website pay it from the first; businesses
  that joined before pay it for whatever starts on or after the second (30 days on by default). A
  change can be called off until clients already here pay it. Its page shows the email as the
  busiest client in the audience would get it, "Send me a test", and "Tell clients" (paying, on
  trial, stopped paying), once. Countries no longer has its own price boxes: a new country is
  added with its three starting prices, and changes go through Prices.
- **Home page** (`views/home/index.php`): a new, wide "Sound familiar?" card (“Bring your records for
  the loan.”) with two real phone screens (Your records, and an invoice's code checked at
  nfelia.com/verify), and a section, "Your records, in one Excel book", with the example shop's real
  workbook open in a spreadsheet program (the Cover, At a glance and Receipts sheets as tabs). Prices
  on the page, the terms and the help page's answers come from Prices.

## Prices, everywhere

`prices_for()` in `app/helpers/prices.php` is the one answer. `billing_prices()` (Billing, Paystack,
Mobile Money, Ops) asks it with the date the month being paid for starts, so a month that starts
before the notice date keeps the old price and a seat added to a running month is priced with that
month. The website, the terms and the help page use `prices_site()` (Nigeria, someone signing up
today). Billing shows a note when a change is coming for that business. If a deploy ever stops
before its migration, prices fall back to the countries table rather than breaking Billing.

**The email** (`views/emails/price_notice.php`) starts with what the business has built with Nfelia
(invoices, receipts, customers, money recorded), says plainly what changes and from when, gives the
reason, offers "pay for your next month now and it stays at today's price" only when that is true
for that client, and asks them to reply if it is hard. A lower price is told as good news. It is a
billing email, so it reaches clients who turned off news emails; each also gets a note in the bell.
The cron sends a few every 5 minutes, after campaigns, within `CAMPAIGN_PER_HOUR`.

## Tables (db/migrations/20261024090000_records_book.sql)

`records_books`, `record_requests`, `price_changes`, `price_notices`; `countries.book_price`
(Nigeria ₦7,500, other countries 75% of their month); `subscription_payments.kind` gains `book`, with
`book_id`. Safe to run twice. It runs after the orders hub's `20261023090000_orders_hub.sql`, whose
tables and columns the sheets read; the plain copy also takes `order_events`, `refunds` and
`kept_money`.

## Deploying

- **Composer** gains `phpoffice/phpspreadsheet` and `chillerlan/php-qrcode`. Before merging, check
  the server's PHP has what they need (Composer refuses to install otherwise and the deploy stops):
  `/usr/local/bin/ea-php83 -m` should list `zip`, `xml`, `xmlreader`, `xmlwriter`, `dom`, `gd`,
  `fileinfo`, `iconv`, `zlib` and `mbstring`. Add any that are missing in cPanel → EasyApache 4 →
  PHP Extensions (ea-php83).
- **Keep PHP's JIT off** on the server, as PHP 8.3 ships it: `/usr/local/bin/ea-php83 -i | grep
  opcache.jit_buffer_size` should say 0. In CI, PHP 8.3.35 with JIT on (setup-php's default) crashed
  the built-in web server every time it made a book, while the same build from the command line was
  fine; CI now serves the app without JIT. Should a page's build ever stop like that, the cron makes
  the book again within 20 minutes.
- `storage/private/records/` is made on first use, under `storage/` (closed to the web).
- The cron (`cron/planner.php`) now also makes books whose page's own build didn't run, deletes kept
  files past their date, closes unanswered requests, and sends price notices.

## Tests

- `tests/ops/records_test.php` (132 checks, `bash tests/notifications/run.sh ops/records`, in CI):
  the book and its contents, tenancy (nothing of another business), the free and paid rules, a
  Mobile Money book approved in Ops, the plain copy (no password hashes, no document links), Ops
  roles, verify (facts, no customer details, changes since, file check), the whole request flow,
  orders made through the orders hub (every paper naming its order, kept money counted once,
  refunds without account numbers, the walk-in customer left out, refund codes, the money trail an
  approved ask sends), and prices (new sign-ups, clients already here, Billing, the email, telling
  clients once, calling a change off).
- Merged with the orders hub, every suite passes locally against a database built the way CI builds
  it: engine 91, flows 295, cron 62, sw 17, ops/foundation 101, ops/monitor 117, ops/clients 104,
  ops/sales 134, orders/core 190, ops/records 132, ui 20, ops/ui 56.
- `tests/ops/ui_test.js` adds the Ops wizard, Prices and a price change on a phone.

## Pictures for the home page

Real app, example data: `docs/handoff/testkit/records_seed.php` (Ivie Luxe Hair, a year and a half of
records, every sale an order as the orders hub keeps them, with a refund and kept money), a book made
for her a month back, then `docs/handoff/testkit/shoot_records.js` (the two
phone screens) and `docs/handoff/testkit/shoot_workbook.sh` (the workbook in LibreOffice Calc on a
virtual screen). The receipts sheet is shown rather than invoices because the demo's customer phone
numbers are made up but could be someone's.

## Fixed on the way

- In a data sheet, the "Open" and "Verify" column titles were burgundy on the burgundy header, so
  they could not be seen: PhpSpreadsheet applies a style object to whatever is selected when it is
  changed, and the link colour was set after the header had been selected. Tested now.
- Counting a sheet's rows cut its SQL at the first FROM and ORDER BY, which broke once a sheet listed
  an order's receipts with a subquery; `records_count_sql()` now only reads the top level.

## Follow-ups

- QR codes and verify codes on invoice and receipt PDFs (Tom: the next PR).
