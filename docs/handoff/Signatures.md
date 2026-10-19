# Signatures and the QR code on every document (handoff)

Every quote, invoice, receipt, delivery note and deed now carries a **verify code and a QR code**
at its foot, on its page and in its PDF. Anyone who scans it lands on **nfelia.com/verify/<code>**,
which says the document is genuine, what it says, who signed it, and whether it changed since.

Signatures are no longer printed on everything. The blank "Authorized By", "Client
Acknowledgement" and "Received By" lines are gone. A document is signed only when someone wants
it, and only by the people who should sign it. Every signature keeps a fingerprint (a hash) of
what was signed and a seal, so a signature can be checked.

## What Tom decided

| # | Topic | Decision |
|---|---|---|
| 1 | Where the box lives | Both. Settings › Signatures turns signing on or off per kind of document (the default), and each document can be switched on its own page. |
| 2 | How the business signs | Per kind, in Settings: a stamp ("Digitally signed for … by …") or the signer's drawn signature, drawn once and reused. The fingerprint and seal are always there. |
| 3 | Who signs for the business | Owners and admins. |
| 4 | Quotes | The customer signs (draws, sealed) only when the quote is signed. Otherwise a plain "Accept quote" button. |
| 5 | Invoices and receipts | The business only, when signed. "Client Acknowledgement" and "Received By" are gone. |
| 6 | Delivery notes | Only when signed, and then both sides sign: the business with its style, the receiver by drawing. Otherwise no signatures: the receiver taps "Confirm received". |
| 7 | Deeds | The business only, when signed. |
| 8 | Changes after signing | Signed again automatically in the name of whoever changed it, when they are an owner or admin. Verify lists every version. When staff change it, it shows "Changed since it was signed" until an owner or admin signs it again. |
| 9 | The QR code | On invoice and receipt PDFs (asked for), and also on every document's page and PDF: quotes, delivery notes and deeds too. |

## What a document shows

At the foot of each document, `views/partials/doc_signs.php` (pages) and `doc_signs_pdf.php` (PDFs):

- **The business's signature**, when it is signed: the signer's drawing, or a bordered stamp, then
  "Signed for {business}", the signer's name and role, the date, and the seal's first eight
  characters ("Seal 4A44·1225"). After a change: "Signed again (version N)". When it changed and
  nobody who may sign has signed it again: "Changed since it was signed", in amber.
- **The customer's signature**, when there is one: "Accepted and signed by" on a quote, "Received
  and signed by" on a delivery note.
- **The QR code and the code**, always: "Check this invoice is genuine, and who signed it: scan
  the code or open nfelia.com/verify. Code Q8CJX-ZXCSK".
- In the app only, for owners and admins: "Set to be signed, and not signed for {business} yet"
  when staff made it.

A service business's deed is its certificate of completion, and it is called that everywhere
(`doc_label()`).

## Where you switch it

- **Settings › Signatures** (`views/settings/signatures.php`, owners and admins): per kind, a
  switch (signed by default or not) and Stamp / Drawn signature. Below it, **Your signature**: draw
  it once on the pad, draw it again, or remove it. Until you draw one, papers set to "drawn" carry
  the stamp.
- **On a document**: "Sign this invoice", "Sign it again" (after a change), "Take the signature
  off". These sit in the More sheet on invoices and receipts, as buttons at the top of quotes and
  delivery notes, and on the deed page for its owner (`views/partials/doc_sign_control.php`, posting
  to `signing/sign`). A quote or delivery note the customer signed can't be taken off.
- **On the forms**: the quote form and the delivery form have a "Signed quote" / "Signed delivery
  note" box, and the "Issue a deed" form a "Signed deed" switch, each starting as Settings say.
  Invoices and receipts follow Settings when made and are switched on their own page.

## When a document is signed

`app/helpers/signing.php`:

- **Made** (`doc_made()`): the document is set to signed or not, as its form's box or Settings
  say. When it is signed and whoever made it is an owner or admin, it is signed at once in their
  name. A document made by staff, or by the shop on its own, waits for an owner or admin. Nothing is
  ever signed by "the system". Hooked into every place a document is made: orders (invoice,
  delivery note, receipts), the invoice form, receipts from payments, booking deposits, the quote
  and delivery forms, and deeds.
- **Changed** (`doc_changed()`): when the content of a signed document changes, it is signed again
  as a new version, in the editor's name when they may sign. The earlier version stops standing
  (`superseded_at`) but is kept. Hooked into order changes (items, pay-by date), the invoice form,
  the quote form, the delivery form, and an edited invoice's open delivery notes.
- **What counts as the content** (`doc_facts()`): what was given to the customer. That means the
  number, order, dates, customer, lines, totals, notes and terms. It never includes what happens
  afterwards: payments, a delivery's progress, a quote's answer. Paying an invoice never "changes" it.
- **Switched** (`doc_switch()`): sign, sign again, or take off, from the document's page.

Signing never stops what it is part of. A failure (say, the moment between a deploy and its
migration) is reported to Ops › Errors, and the order or page carries on without it
(`signing_safely()`).

## The customer

- **A quote that is signed**: its page asks the customer to type their name and draw ("Sign &
  Accept"). Their drawing is kept privately and sealed (`doc_customer_sign()`). A tap alone is
  refused. **A quote that isn't signed**: "Accept quote", one tap, nothing drawn. The business
  hears "Ngozi accepted it" or "Ngozi signed and accepted it". Either way the quote becomes its
  order, as before.
- **A delivery note that is signed**: the sign-off link asks for a name and a drawing ("Sign and
  confirm"). **One that isn't**: a name and "Confirm received". A drawing sent anyway is not kept.
  The business hears "Delivery received" or "Delivery signed". The delivery's last status is now
  called **Received** (it was "Signed Off"), since a receiver can now confirm without signing.

## Seals, codes and nfelia.com/verify

- **`doc_codes`**: one code per document, made the first time it is shown. It has 10 characters in
  the Records Book's alphabet and never repeats a Records Book's code. Shown as `K7Q2M-XA9WT`.
- **`doc_signatures`**: one row per signature: the party (business or customer), who, their role,
  the style, the drawing, the document's facts and their SHA-256 (`content_hash`), the time, the IP,
  and the **seal**. The seal is an HMAC-SHA256 of all of that, made with a secret that is not in the
  database. A row edited in the database no longer matches its seal.
- **The secret** is made the first time and kept in `storage/private/seal.key` (deploys never
  touch `storage/`). `SEAL_KEY` in `.env` overrides it, only to share one key between servers.
  **Back this file up with `storage/`.** If it is lost or changed, no earlier seal checks out.
- **nfelia.com/verify/<code>** (`doc_verify_lookup()`, the `document` mode of
  `views/verify/page.php`) shows:
  - "Genuine and signed", "Genuine, changed since it was signed", "Genuine, since removed", or "A
    signature doesn't check out".
  - The document's key facts, as the Records Book shows them. The customer's name is masked.
  - Every signature, newest first, with its version, style and seal. Each one says either "Seal
    checks out. Nothing has changed since it was signed" or "Changed since it was signed: the items,
    the total", or "Signed again on …", "Taken off on …", or "it was altered after it was signed".

## Drawings

A drawing from a pad (`signing_store_drawing()`) must be a real PNG. It is redrawn by GD (so only an
image is ever stored), cropped to the ink, and kept in `storage/private/signatures/{business}/`
under a name made from its content. A document keeps the drawing it was signed with even after its
signer draws a new one. Pages and PDFs show drawings as data: URIs; nothing under `storage/` is
ever served directly.

A purge (Settings › Purge) also removes the purged documents' signatures and codes, and deletes
the drawings nothing uses any more (`doc_forget_missing()`).

## What changed for documents signed before

The migration (`db/migrations/20261025090000_signatures.sql`) marks as signed only the quotes
someone actually signed (the old "Authorize" or a customer's acceptance) and the delivery notes
signed for. Those keep showing their old signatures, without a seal. Nothing else was ever really
signed (the lines were blank), so nothing else is marked signed. The old "Authorize" button and
its drawing box on quotes are gone, replaced by the switch above. `quotations/sign_internal` no
longer exists.

## Files

- `app/helpers/signing.php`: everything above.
- `app/controllers/SigningController.php`: `signing/settings`, `signing/mine`, `signing/sign`
  (owners and admins).
- `views/partials/doc_signs.php`, `doc_signs_pdf.php`, `doc_sign_control.php`;
  `views/settings/signatures.php`.
- Pages and PDFs: invoices, receipts and quotes (app, customer page, PDF), deliveries (app,
  customer page, sign-off), deeds (page and PDF).
- `views/verify/page.php` and `app/controllers/VerifyController.php`: the document mode.
- `db/migrations/20261025090000_signatures.sql`.

## Tests

`tests/docs/sign_test.php` (`bash tests/notifications/run.sh docs/sign`, in CI) has 98 checks.
They cover:

- nothing signed out of the box, but every document with its code and QR;
- Settings for owners and admins only, and drawing one's signature;
- signing as a document is made: by owners and admins, not staff; stamp or drawing as Settings say;
- the quote form's box;
- the invoice's page and PDF (no blank lines), and the receipt unsigned;
- the switch, refused to staff and to other businesses;
- signing again on a change, by the editor or not, and the pay-by date (a payment changes nothing);
- taking a signature off;
- both quote flows and both delivery flows, with the business's notices;
- seals and a tampered row;
- verify: versions, what changed, masked names, codes typed any way, and unknown codes;
- deeds, old signatures, and a purge.

`flows` now signs a delivery note that is set to be signed, and sends real PNG drawings: a
signature that isn't a picture is refused now.
