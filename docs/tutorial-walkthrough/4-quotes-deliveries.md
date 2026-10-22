# Tutorial walkthrough 4: quotes and deliveries

What we found while mapping quotes and deliveries for the hair vendor's tutorial videos, and what
was done. Each lead from the first read-through was checked against the code. Numbers match the
original list.

## Fixed

| # | Claim | Change | File |
|---|---|---|---|
| 1 | A quote waiting for a signature (`pending_signature`) hid the customer's Accept/Sign bar, though the server takes the answer. | The page now offers an answer in exactly the states the server takes: draft, sent, in negotiation, waiting to sign. (The draft half is under Decided.) | `views/quotations/public.php` |
| 2 | A quote past its "Valid until" day could still be accepted. | Accepting or signing after that day is refused: "This quote was valid until 9 Nov 2026. Please ask the business for a fresh quote." The page says the same in an amber note, and the Accept button becomes **Ask for a fresh quote**, which opens the Discussion tab. Commenting and declining still work, so the customer can ask. An expired quote says "This quote has expired" instead of "already answered". The quote counts as valid through its last day, the same as the cron. | `app/controllers/PublicController.php` (`document_action`), `views/quotations/public.php` |
| 3 | The owner's "Make it an order" showed on a quote still in negotiation or waiting to sign, skipping the customer's yes. | This is on purpose: the owner can accept for the customer, say after a call. `order_from_quote()` takes these states, and ORDERS_HUB says "owner marks it accepted". The button now says **Accept and make the order**, the same as on a sent quote. On an accepted quote whose order wasn't made, it still says **Make it an order**. | `views/quotations/view.php` |
| 4 | After accepting, the customer saw a grey "converted" badge. | A quote that became its order reads **Accepted**, in green. The badge is in sentence case, not capitals. | `views/quotations/public.php` |
| 5 | Mixed words: "Request for Engagement", "Proposal", "invoice". | The customer sees "Quote" in the page title and header, plus **Decline this quote** and **Accept quote**. The owner sees **New quote**, **Edit quote**, **Save changes**, "Quote saved." and "Quotes". The list now says "2 accepted, not yet orders", and its empty state says "it becomes an order". On WhatsApp the customer gets "your *quote*". The flash messages and the customer's error messages now say "quote" too. An old quote turned into an invoice before orders existed now reads "Made into an invoice", the same words `order_from_quote()` uses. The printed document's own title stays "Quotation", the same as the PDF. | `views/quotations/form.php`, `public.php`, `index.php`, `view.php`, `app/controllers/QuotationController.php`, `PublicController.php` |
| 6 | The on-screen total didn't cap the discount, so it could go below zero. | The form's sums now use the discount up to the subtotal, the same cap the server applies. | `views/quotations/form.php` |
| 7 | The client list is required but hidden while "+ New Client" is open, so the browser silently refused to send the form. | The list is required only while it shows. Saving while a new client is half added now says "Save the new client first, or tap Cancel to pick one from the list." and puts the cursor in the name field. | `views/quotations/form.php` |
| 8 | An apostrophe in the signer's name showed as `&#039;`. | The name goes into the script as JSON (`json_encode` with the `JSON_HEX_*` flags), not HTML-escaped. | `views/quotations/public.php` |
| 9 | The delivery form's script broke on an address written on two lines. | The invoice number, customer, recipient name, phone and address, and the items, all go into the script as JSON with the usual `JSON_HEX_*` flags. Checked with an address that has a line break and an apostrophe. | `views/deliveries/form.php` |
| 10 | When the owner tapped "Mark Received", the customer's link still showed the form, then said "already received". | A received delivery now shows its Received state, whoever marked it. If there is no signer's name, it says "*Business* marked this delivery received." and shows the day it was delivered. | `views/deliveries/signoff.php` |
| 12 | The tracking link wasn't checked, so a `javascript:` link would be saved and shown. | Saving now needs the link to start with http:// or https://: "Start the tracking link with https:// (or leave it empty)." The owner's page and the customer's page only show a link that starts with http(s), which covers links saved before this change. | `app/controllers/DeliveryController.php`, `views/deliveries/view.php`, `views/deliveries/public.php` |
| 13 | `user-scalable=no` stopped customers from zooming. | Removed it, with `maximum-scale`, from both pages. The signature pads still don't scroll while someone draws, because they keep `touch-action: none`. | `views/quotations/public.php`, `views/deliveries/signoff.php` |
| 14 | The WhatsApp link always used the code 234. | It now uses `wa_digits()`, which picks the business's own country code, as the invoice and delivery pages do. | `views/quotations/view.php` |
| 15 | A failed save showed the raw error. | The detail goes to the error log (`errors_report_throwable`, Ops › Errors). The owner sees "That quote didn't save. Please try again." | `app/controllers/QuotationController.php` |
| 16 | At 384px the delivery page's header buttons (Edit, Sign this delivery note, Preview) ran off the screen. | The row wraps now (`flex-wrap`, as on the quote page). | `views/deliveries/view.php` |

## Not a bug

| # | Claim | Why |
|---|---|---|
| 11 | The customer can sign for a delivery that is still "Pending Dispatch". | This is meant to work. The sign-off guard (S11) lists `pending_dispatch` on purpose next to the "on its way" states, and the owner's page gives out the sign-off link from the moment the note is made. In practice "Pending" often just means nobody tapped Dispatched: the vendor handed it over in person, or the rider left before the status changed. The person signing has the goods in hand, so turning them away would be wrong. Signing fills in the delivered time (`actual_delivery`). |

## Decided

Tom decided these. Each one is built, and tests cover it in `tests/notifications/flows_test.php` and `flows_shop.php`.

1. **A draft whose link is shared becomes Sent.** Customers can still answer a draft, which was the other half of claim 1. When the owner taps WhatsApp, Copy Link or Share PDF on a draft, the page posts to the new `quotations/mark-shared/{id}`. That moves the draft to Sent, so the list counts it as waiting. It moves only draft → sent, and once: any other quote stays as it is. The change goes in the quote's history as "Sent to the customer.", marked as already read, so the customer's Discussion tab shows no new badge. The page then shows Sent and drops Mark Sent without a reload. Tapping a share button counts as sharing, even if the owner then closes WhatsApp without sending.
   Files: `app/controllers/QuotationController.php` (`mark_shared()`), `views/quotations/view.php`.

2. **The owner can edit a quote that is waiting to sign.** This lets them give it a new valid-until date and send it again. The states that can be edited are in one list, `QuotationController::EDITABLE`: draft, sent, in negotiation and waiting to sign. The page's Edit button uses the same list. Editing a quote that is waiting to sign is logged in its history like any other change, and the quote stays waiting to sign.
   Files: `app/controllers/QuotationController.php`, `views/quotations/view.php`.

3. **Quote requests match customers the way shop orders do.** `send_quote_request()` calls `order_match_customer()`, which matches on the phone's last ten digits. "+234 803 111 0000" is now the same customer as "08031110000", where it used to make a duplicate. The request's page uses the same rule to find that customer, so **Write a quote** starts a quote addressed to them. A customer made this way no longer gets the old "Auto-created from landing page quote request" note.
   Files: `app/controllers/PublicController.php` (`send_quote_request()` only), `app/controllers/QuotationController.php` (`requests()`).

4. **Refusals in plain words.** The quote's `update_status()` now says "A quote that is “Accepted” can't be changed to “Sent”.", using the words the quotes list shows. Before, it said "Invalid status transition from ACCEPTED TO SENT". The delivery's `update_status()` had the same problem, with raw codes like "pending_dispatch to delivered". It now says "A delivery that is “Pending Dispatch” can't be marked “Delivered”."
   Files: `app/controllers/QuotationController.php`, `app/controllers/DeliveryController.php`.

## Also seen (not changed)

- The owner can still accept a quote past its date for the customer. That is left as the owner's call.
