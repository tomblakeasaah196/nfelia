<?php
/**
 * Signatures and verify codes on documents (app/helpers/signing.php,
 * docs/handoff/Signatures.md), against the database and the running app:
 * Settings › Signatures, signing when a document is made and again when it
 * changes, the switch on a document's page, the customer signing a quote
 * or a delivery note (or accepting with a tap when it isn't signed), seals,
 * the QR code on pages and PDFs, and nfelia.com/verify.
 *   bash tests/notifications/run.sh docs/sign
 */
require __DIR__ . '/../notifications/lib.php';
t_guard();
fixture_reset();
clean_slate();

const ADA = 20, BISI = 21, CHIDI = 22, BIZ = 2;
$db = get_db();

/** A drawing from a signature pad: a PNG of its own size, so each person's differs. */
function drawing(int $w): string
{
    $im = imagecreatetruecolor($w, 2);
    imagefill($im, 0, 0, imagecolorallocate($im, 20, 10, 40));
    ob_start();
    imagepng($im);
    imagedestroy($im);
    return 'data:image/png;base64,' . base64_encode((string)ob_get_clean());
}
define('ADA_PEN', drawing(4));
define('NGOZI_PEN', drawing(5));
define('RECEIVER_PEN', drawing(6));

function make_order(array $in, int $by = ADA): array
{
    return order_create(get_db(), BIZ, $in + ['channel' => 'manual', 'fulfillment' => 'pickup', 'method' => 'cash', 'paid_now' => 0], $by);
}

function invoice_of(int $order_id): array
{
    return rows("SELECT * FROM invoices WHERE order_id = ? ORDER BY id DESC LIMIT 1", [$order_id])[0] ?? [];
}

/** The signatures on a document, oldest first. */
function sigs(string $type, int $id, ?string $party = null): array
{
    return rows("SELECT * FROM doc_signatures WHERE doc_type = ? AND doc_id = ?" . ($party ? " AND party = ?" : '') . " ORDER BY id",
                $party ? [$type, $id, $party] : [$type, $id]);
}

function standing(string $type, int $id, string $party = 'business'): ?array
{
    return doc_latest(get_db(), $type, $id, $party);
}

/* ══ Settings ════════════════════════════════════════════════ */

section('Nothing is signed until someone wants it');
$s = signing_settings_for($db, BIZ);
ok(!array_filter(array_column($s, 'on')) && array_unique(array_column($s, 'style')) === ['stamp'], 'out of the box: no kind of paper is signed, and the style is the stamp');
$o = make_order(['customer_id' => 200, 'items' => [['name' => 'Wig revamp', 'quantity' => 1, 'unit_price' => 40000]]]);
$inv = invoice_of((int)$o['id']);
ok($inv && (int)$inv['sign_on'] === 0 && !sigs('invoice', (int)$inv['id']), 'an invoice made then is not signed: no automatic signature');
$state = doc_sign_state($db, 'invoice', (int)$inv['id']);
ok(preg_match('/^[0-9A-HJKMNP-TV-Z]{10}$/', $state['code']) === 1 && $state['display'] === substr($state['code'], 0, 5) . '-' . substr($state['code'], 5),
   'but it has its verify code, like K7Q2M-XA9WT');
eq(doc_sign_state($db, 'invoice', (int)$inv['id'])['code'], $state['code'], 'one code per document, the same every time');
ok(str_starts_with($state['qr'], 'data:image/png;base64,') && strlen($state['qr']) > 200, 'and its QR code, as a picture for the page and the PDF');

section('Settings › Signatures: owners and admins choose');
$ada = signin('ada@adashair.test');
$ada->post('signing/settings', ['signing' => [
    'quote' => ['on' => '1', 'style' => 'drawn'], 'invoice' => ['on' => '1', 'style' => 'stamp'], 'receipt' => ['style' => 'stamp'],
    'delivery' => ['on' => '1', 'style' => 'stamp'], 'deed' => ['on' => '1', 'style' => 'drawn'],
]]);
$s = signing_settings_for($db, BIZ);
ok($s['quote'] === ['on' => true, 'style' => 'drawn'] && $s['invoice'] === ['on' => true, 'style' => 'stamp'] && $s['receipt']['on'] === false
   && $s['delivery']['on'] && $s['deed'] === ['on' => true, 'style' => 'drawn'], 'Ada sets which papers are signed, and how, kind by kind');
$chidi = signin('chidi@adashair.test');
$chidi->post('signing/settings', ['signing' => ['receipt' => ['on' => '1']]]);
ok($chidi->status === 403 && signing_settings_for($db, BIZ)['receipt']['on'] === false, 'staff can\'t: only owners and admins sign for the business');
$ada->get('settings');
ok(str_contains($ada->body, 'id="signatures"') && str_contains($ada->body, 'Drawn signature'), 'the Signatures card is on Ada\'s Settings');
$chidi->get('settings');
ok($chidi->status === 200 && !str_contains($chidi->body, 'id="signatures"'), 'and not on Chidi\'s');

section('Ada draws her signature once');
$ada->post('signing/mine', ['signature' => 'data:image/png;base64,bm90IGEgcGljdHVyZQ==']);
ok(q("SELECT signature_path FROM users WHERE id = ?", [ADA]) === null, 'something that isn\'t a picture is refused');
$ada->post('signing/mine', ['signature' => ADA_PEN]);
$mine = (string)q("SELECT signature_path FROM users WHERE id = ?", [ADA]);
ok(preg_match('#^storage/private/signatures/2/sig-[0-9a-f]{40}\.png$#', $mine) === 1 && is_file(BASE_PATH . '/' . $mine),
   'a picture is kept privately, named after its content');
ok(!str_starts_with($mine, 'uploads/'), 'never in the public uploads');

/* ══ Signed when made ════════════════════════════════════════ */

section('A paper is signed as it is made, by whoever made it');
$o = make_order(['customer_id' => 200, 'items' => [['name' => 'Body Wave 18in', 'quantity' => 2, 'unit_price' => 65000]], 'fulfillment' => 'delivery',
                 'deliver_by' => planner_add_days(planner_today(), 3), 'delivery_address' => '12 Allen Avenue', 'paid_now' => 50000]);
$inv = invoice_of((int)$o['id']);
$inv_id = (int)$inv['id'];
$b = standing('invoice', $inv_id);
ok((int)$inv['sign_on'] === 1 && $b && $b['signer_name'] === 'Ada Obi' && $b['signer_role'] === 'Owner' && (int)$b['user_id'] === ADA,
   'the invoice Ada made is signed in her name, as owner');
ok($b && $b['style'] === 'stamp' && $b['image_path'] === null && (int)$b['version'] === 1, 'with the stamp: invoices are set to it');
ok($b && doc_seal_ok($b) && strlen($b['seal']) === 64 && hash_equals($b['content_hash'], doc_hash(doc_facts($db, 'invoice', $inv_id))),
   'sealed, and holding the fingerprint of what was signed');
$facts = json_decode($b['facts'], true);
ok(($facts['total'] ?? null) === '130000.00' && ($facts['customer'] ?? null) === 'Ngozi Eze' && count($facts['lines'] ?? []) === 1,
   'what was signed is kept with it: the total, the customer, the lines');
$rec_id = (int)q("SELECT id FROM receipts WHERE order_id = ?", [$o['id']]);
ok($rec_id && (int)q("SELECT sign_on FROM receipts WHERE id = ?", [$rec_id]) === 0 && !sigs('receipt', $rec_id), 'its receipt is not signed: receipts are off');
$del_id = (int)q("SELECT id FROM deliveries WHERE order_id = ?", [$o['id']]);
ok($del_id && (int)q("SELECT sign_on FROM deliveries WHERE id = ?", [$del_id]) === 1 && standing('delivery', $del_id)['signer_name'] === 'Ada Obi',
   'its delivery note is signed: delivery notes are on');

$o2 = make_order(['customer_id' => 200, 'items' => [['name' => 'Closure unit', 'quantity' => 1, 'unit_price' => 50000]]], CHIDI);
$inv2 = invoice_of((int)$o2['id']);
ok((int)$inv2['sign_on'] === 1 && !sigs('invoice', (int)$inv2['id']), 'Chidi\'s invoice is set to be signed, but staff can\'t sign it');
$st = doc_sign_state($db, 'invoice', (int)$inv2['id'], ADA);
ok($st['on'] && !$st['business'] && $st['can_sign'], 'it waits for Ada, who may sign it');
ok(!doc_sign_state($db, 'invoice', (int)$inv2['id'], CHIDI)['can_sign'], 'Chidi may not');

section('The quote form\'s box, and Ada\'s drawn signature');
$ada->get('quotations/create');
ok(str_contains($ada->body, 'name="sign_on" value="1" checked'), 'a new quote\'s box starts as Settings say (signed)');
$ada->post('quotations/create', ['sign_box' => '1', 'sign_on' => '1', 'customer_id' => '200', 'issue_date' => planner_today(),
    'item_description' => ['Bridal styling'], 'item_quantity' => ['1'], 'item_unit_price' => ['80000'], 'item_discount' => ['0'], 'item_product_id' => ['0']]);
$q_signed = (int)q("SELECT MAX(id) FROM quotations WHERE business_id = ?", [BIZ]);
$qs = standing('quote', $q_signed);
ok((int)q("SELECT sign_on FROM quotations WHERE id = ?", [$q_signed]) === 1 && $qs && $qs['style'] === 'drawn' && $qs['image_path'] === $mine,
   'Ada\'s quote is signed with her drawing: quotes are set to a drawn signature');
$ada->post('quotations/create', ['sign_box' => '1', 'customer_id' => '200', 'issue_date' => planner_today(),
    'item_description' => ['Wig install'], 'item_quantity' => ['1'], 'item_unit_price' => ['30000'], 'item_discount' => ['0'], 'item_product_id' => ['0']]);
$q_plain = (int)q("SELECT MAX(id) FROM quotations WHERE business_id = ?", [BIZ]);
ok($q_plain !== $q_signed && (int)q("SELECT sign_on FROM quotations WHERE id = ?", [$q_plain]) === 0 && !sigs('quote', $q_plain),
   'with the box left off, the quote is not signed');

/* ══ On the page and the PDF ═════════════════════════════════ */

section('The invoice: signature and QR code, no blank lines');
$tok = (string)q("SELECT token FROM invoices WHERE id = ?", [$inv_id]);
$code = doc_code($db, BIZ, 'invoice', $inv_id);
$cust = new Http();
$cust->get('doc/' . $tok);
ok($cust->status === 200 && str_contains($cust->body, 'data-verify-code="' . $code . '"') && str_contains($cust->body, doc_code_display($code)),
   'the customer\'s page carries the code and its QR code');
ok(str_contains($cust->body, 'Digitally signed for Ada&#039;s Hair &amp; Co') && str_contains($cust->body, 'Ada Obi, Owner')
   && str_contains($cust->body, 'Seal ' . doc_seal_short($b['seal'])), 'and the stamp: who signed, for the business, and the seal');
ok(!str_contains($cust->body, 'Authorized By') && !str_contains($cust->body, 'Client Acknowledgement'), 'no blank "Authorized By" or "Client Acknowledgement" lines');
$cust->get('doc/' . $tok . '?format=pdf');
ok($cust->status === 200 && str_starts_with($cust->body, '%PDF') && substr_count($cust->body, '/Subtype /Image') >= 1, 'the PDF is made, with the QR code in it');
$sign = doc_sign_state($db, 'invoice', $inv_id);
$business = rows("SELECT * FROM businesses WHERE id = ?", [BIZ])[0];
ob_start();
require BASE_PATH . '/views/partials/doc_signs_pdf.php';
$pdf_html = ob_get_clean();
ok(str_contains($pdf_html, 'Digitally signed for') && str_contains($pdf_html, doc_code_display($code)) && str_contains($pdf_html, 'data:image/png;base64,'),
   'the PDF\'s block says who signed, with the code and the QR picture');

section('The receipt: not signed, so no signature at all');
$rtok = (string)q("SELECT token FROM receipts WHERE id = ?", [$rec_id]);
$cust->get('doc/' . $rtok);
ok($cust->status === 200 && str_contains($cust->body, 'data-verify-code=') && !str_contains($cust->body, 'data-sign-business'), 'its code and QR, and no signature');
ok(!str_contains($cust->body, 'Received By') && !str_contains($cust->body, 'Authorized By'), 'no blank "Received By" line: nobody signs a receipt but the business');
$cust->get('doc/' . $rtok . '?format=pdf');
ok($cust->status === 200 && str_starts_with($cust->body, '%PDF'), 'its PDF is made');

section('In the app: the sign switch, for owners and admins');
$ada->get('invoices/view/' . $inv_id);
ok(str_contains($ada->body, 'Take the signature off') && str_contains($ada->body, 'data-doc-signs'), 'Ada sees the signature and can take it off');
$ada->get('invoices/view/' . (int)$inv2['id']);
ok(str_contains($ada->body, 'Sign this invoice') && str_contains($ada->body, 'data-sign-waiting'), 'on Chidi\'s invoice: it is waiting for her, and she can sign it');
$chidi->get('invoices/view/' . (int)$inv2['id']);
ok($chidi->status === 200 && !str_contains($chidi->body, 'signing/sign') && !str_contains($chidi->body, 'data-sign-waiting'), 'Chidi sees no switch');
$chidi->post('signing/sign', ['type' => 'invoice', 'id' => (string)$inv2['id'], 'on' => '1', 'back' => 'invoices/view/' . $inv2['id']]);
ok($chidi->status === 403 && !sigs('invoice', (int)$inv2['id']), 'and can\'t sign it by posting');
$bisi = signin('bisi@adashair.test');
$bisi->post('signing/sign', ['type' => 'invoice', 'id' => (string)$inv2['id'], 'on' => '1', 'back' => 'invoices/view/' . $inv2['id']]);
$b2 = standing('invoice', (int)$inv2['id']);
ok($b2 && $b2['signer_name'] === 'Bisi Admin' && $b2['signer_role'] === 'Admin' && $bisi->to() === 'invoices/view/' . $inv2['id'], 'Bisi, an admin, signs it in her name');
$bisi->post('signing/sign', ['type' => 'invoice', 'id' => (string)$inv2['id'], 'on' => '1', 'back' => 'https://evil.test/x']);
ok($bisi->to() === 'dashboard', 'the way back stays on Nfelia');
$kola = signin('kola@kolaprints.test');
$kola->post('signing/sign', ['type' => 'invoice', 'id' => (string)$inv2['id'], 'on' => '0', 'back' => 'dashboard']);
ok(standing('invoice', (int)$inv2['id']) !== null, 'another business\'s owner can\'t touch it');

/* ══ Changed after signing ═══════════════════════════════════ */

section('A change signs it again, in the name of whoever changed it');
order_update_lines($db, BIZ, (int)$o['id'], [['name' => 'Body Wave 18in', 'quantity' => 3, 'unit_price' => 65000]], 0, BISI, '₦');
$all = sigs('invoice', $inv_id, 'business');
$now = standing('invoice', $inv_id);
ok(count($all) === 2 && $now['signer_name'] === 'Bisi Admin' && (int)$now['version'] === 2 && $all[0]['superseded_at'] !== null,
   'Bisi changed the items: version 2 is signed by her, and version 1 stops standing');
ok(!doc_sign_state($db, 'invoice', $inv_id)['changed'], 'so it reads as signed, not as changed');
order_update_lines($db, BIZ, (int)$o['id'], [['name' => 'Body Wave 18in', 'quantity' => 4, 'unit_price' => 65000]], 0, CHIDI, '₦');
ok(count(sigs('invoice', $inv_id, 'business')) === 2, 'Chidi changes it: staff can\'t sign, so no new signature');
$st = doc_sign_state($db, 'invoice', $inv_id);
ok($st['changed'] && $st['business']['signer_name'] === 'Bisi Admin', 'and it shows as changed since it was signed');
$cust->get('doc/' . $tok);
ok(str_contains($cust->body, 'Changed since it was signed'), 'the customer sees that too');
$ada->post('signing/sign', ['type' => 'invoice', 'id' => (string)$inv_id, 'on' => '1', 'back' => 'invoices/view/' . $inv_id]);
$now = standing('invoice', $inv_id);
ok((int)$now['version'] === 3 && $now['signer_name'] === 'Ada Obi' && !doc_sign_state($db, 'invoice', $inv_id)['changed'], 'Ada signs it again: version 3, as it is now');
$before = count(sigs('invoice', $inv_id));
order_set_pay_by($db, BIZ, (int)$o['id'], planner_add_days(planner_today(), 9), ADA);
ok(count(sigs('invoice', $inv_id)) === $before + 1 && json_decode(standing('invoice', $inv_id)['facts'], true)['due'] === planner_add_days(planner_today(), 9),
   'a new pay-by date is on the invoice, so it is signed again');
$paid_before = count(sigs('invoice', $inv_id));
order_record_payment($db, BIZ, (int)$o['id'], 10000, planner_today(), 'cash', null, null, ADA, '₦');
ok(count(sigs('invoice', $inv_id)) === $paid_before && !doc_sign_state($db, 'invoice', $inv_id)['changed'], 'a payment changes nothing that was signed');

section('Taking a signature off, and putting it back');
$ada->post('signing/sign', ['type' => 'invoice', 'id' => (string)$inv_id, 'on' => '0', 'back' => 'invoices/view/' . $inv_id]);
$st = doc_sign_state($db, 'invoice', $inv_id);
ok((int)q("SELECT sign_on FROM invoices WHERE id = ?", [$inv_id]) === 0 && !$st['business'] && !$st['on'], 'off: the invoice is unsigned');
ok((int)q("SELECT COUNT(*) FROM doc_signatures WHERE doc_type = 'invoice' AND doc_id = ? AND superseded_at IS NULL", [$inv_id]) === 0, 'and no signature stands');
$cust->get('doc/' . $tok);
ok(!str_contains($cust->body, 'data-sign-business') && str_contains($cust->body, 'data-verify-code='), 'its page keeps the QR code, without a signature');
order_update_lines($db, BIZ, (int)$o['id'], [['name' => 'Body Wave 18in', 'quantity' => 6, 'unit_price' => 65000]], 0, ADA, '₦');
ok(standing('invoice', $inv_id) === null, 'an unsigned invoice that changes stays unsigned');
$ada->post('signing/sign', ['type' => 'invoice', 'id' => (string)$inv_id, 'on' => '1', 'back' => 'invoices/view/' . $inv_id]);
ok(standing('invoice', $inv_id)['signer_name'] === 'Ada Obi' && (int)q("SELECT sign_on FROM invoices WHERE id = ?", [$inv_id]) === 1, 'signed again on a tap');

/* ══ The customer ════════════════════════════════════════════ */

section('A quote that isn\'t signed: the customer accepts with a tap');
$ptok = (string)q("SELECT token FROM quotations WHERE id = ?", [$q_plain]);
exec_sql("UPDATE quotations SET status = 'sent' WHERE id = ?", [$q_plain]);
$cust->get('doc/' . $ptok);
ok(str_contains($cust->body, 'Accept quote') && !str_contains($cust->body, 'Sign & Accept') && !str_contains($cust->body, 'id="sig-canvas"'),
   'its page has an "Accept quote" button and nothing to draw');
clean_slate();
$cust->post('doc/' . $ptok, ['action' => 'accept']);
ok(($cust->data()['success'] ?? false) === true && in_array(q("SELECT status FROM quotations WHERE id = ?", [$q_plain]), ['accepted', 'converted'], true),
   'one tap accepts it');
ok(!sigs('quote', $q_plain, 'customer') && q("SELECT signed_by_name FROM quotations WHERE id = ?", [$q_plain]) === null, 'and nobody signed anything');
eq(rows("SELECT message FROM notifications WHERE event = 'quote.accepted' AND user_id = ?", [ADA])[0]['message'] ?? null,
   'Ngozi Eze accepted it, ₦30,000. Order ' . q("SELECT order_number FROM orders WHERE quotation_id = ?", [$q_plain]) . ' is made.',
   'Ada hears "accepted", not "signed"');

section('A signed quote: the customer signs to accept it');
$stok = (string)q("SELECT token FROM quotations WHERE id = ?", [$q_signed]);
exec_sql("UPDATE quotations SET status = 'sent' WHERE id = ?", [$q_signed]);
$cust->get('doc/' . $stok);
ok(str_contains($cust->body, 'Sign & Accept') && str_contains($cust->body, 'id="sig-canvas"'), 'its page asks for a signature');
$cust->post('doc/' . $stok, ['action' => 'accept']);
ok($cust->status === 400 && q("SELECT status FROM quotations WHERE id = ?", [$q_signed]) === 'sent', 'a tap isn\'t enough');
$cust->post('doc/' . $stok, ['action' => 'sign', 'name' => 'Ngozi Eze', 'signature' => 'data:image/png;base64,bm90IGEgcGljdHVyZQ==']);
ok($cust->status === 400 && !sigs('quote', $q_signed, 'customer'), 'a signature that isn\'t a picture is refused');
$cust->post('doc/' . $stok, ['action' => 'sign', 'name' => 'Ngozi Eze', 'signature' => NGOZI_PEN]);
$c = standing('quote', $q_signed, 'customer');
ok($c && $c['signer_name'] === 'Ngozi Eze' && $c['style'] === 'drawn' && is_file(BASE_PATH . '/' . $c['image_path']) && doc_seal_ok($c) && $c['ip'] === '127.0.0.1',
   'her drawing is kept, sealed, with what she signed and where from');
ok(q("SELECT signed_by_name FROM quotations WHERE id = ?", [$q_signed]) === 'Ngozi Eze', 'the quote says who signed it (the Records Book shows it)');
$cust->get('doc/' . $stok . '?format=pdf');
ok($cust->status === 200 && substr_count($cust->body, '/Subtype /Image') >= 3, 'its PDF has both signatures and the QR code');
$ada->post('signing/sign', ['type' => 'quote', 'id' => (string)$q_signed, 'on' => '0', 'back' => 'quotations/view/' . $q_signed]);
ok(standing('quote', $q_signed) !== null && (int)q("SELECT sign_on FROM quotations WHERE id = ?", [$q_signed]) === 1, 'once the customer signed, it stays signed');

section('A delivery note that isn\'t signed: the receiver confirms with a tap');
$plain_del = (int)q("SELECT id FROM deliveries WHERE id = 601");
exec_sql("UPDATE deliveries SET status = 'dispatched', sign_on = 0 WHERE id = ?", [$plain_del]);
$soff = (string)q("SELECT signoff_token FROM deliveries WHERE id = ?", [$plain_del]);
$cust->get('sign/' . $soff);
ok(str_contains($cust->body, 'Confirm received') && !str_contains($cust->body, 'id="signature-pad"'), 'the receiver\'s page: a name and "Confirm received", nothing to draw');
clean_slate();
$cust->post('sign/' . $soff, ['signoff_name' => 'Ngozi Eze', 'signature_data' => RECEIVER_PEN]);
$d = rows("SELECT * FROM deliveries WHERE id = ?", [$plain_del])[0];
ok(($cust->data()['success'] ?? false) && $d['status'] === 'signed_off' && $d['signoff_name'] === 'Ngozi Eze' && $d['signoff_signature_path'] === null,
   'confirmed: received, by whom, and no signature kept even if one was sent');
ok(!sigs('delivery', $plain_del, 'customer'), 'nothing signed');
eq(rows("SELECT message FROM notifications WHERE event = 'delivery.signed' AND user_id = ?", [ADA])[0]['message'] ?? null,
   'Received by Ngozi Eze.', 'Ada hears it was received');
$cust->post('sign/' . $soff, ['signoff_name' => 'Ngozi Eze']);
ok(($cust->data()['success'] ?? true) === false, 'a second tap changes nothing');

section('A signed delivery note: both sides sign');
$del_id = 600;
exec_sql("UPDATE deliveries SET status = 'in_transit' WHERE id = ?", [$del_id]);
$bisi->post('signing/sign', ['type' => 'delivery', 'id' => (string)$del_id, 'on' => '1', 'back' => 'deliveries/view/' . $del_id]);
ok(standing('delivery', $del_id)['signer_name'] === 'Bisi Admin' && (int)q("SELECT sign_on FROM deliveries WHERE id = ?", [$del_id]) === 1,
   'Bisi signs the delivery note for the business');
$soff2 = (string)q("SELECT signoff_token FROM deliveries WHERE id = ?", [$del_id]);
$cust->get('sign/' . $soff2);
ok(str_contains($cust->body, 'id="signature-pad"') && str_contains($cust->body, 'Sign and confirm'), 'the receiver is asked to sign');
$cust->post('sign/' . $soff2, ['signoff_name' => 'Ngozi Eze']);
ok(($cust->data()['error'] ?? '') === 'Please draw your signature.' && q("SELECT status FROM deliveries WHERE id = ?", [$del_id]) === 'in_transit', 'a name alone isn\'t enough');
$cust->post('sign/' . $soff2, ['signoff_name' => 'Ngozi Eze', 'signature_data' => RECEIVER_PEN]);
$c = standing('delivery', $del_id, 'customer');
ok(($cust->data()['success'] ?? false) && $c && $c['signer_name'] === 'Ngozi Eze' && doc_seal_ok($c)
   && q("SELECT signoff_signature_path FROM deliveries WHERE id = ?", [$del_id]) === $c['image_path'], 'the receiver signed: sealed, beside Bisi\'s signature');
$st = doc_sign_state($db, 'delivery', $del_id);
ok($st['business']['signer_name'] === 'Bisi Admin' && $st['customer']['signer_name'] === 'Ngozi Eze' && $st['customer_words'] === 'Received and signed by',
   'the note carries both');
$ptk = (string)q("SELECT token FROM deliveries WHERE id = ?", [$del_id]);
$cust->get('delivery/' . $ptk);
ok(str_contains($cust->body, 'Received and signed by') && str_contains($cust->body, 'data-verify-code='), 'its page shows both, with its QR code');

/* ══ Seals and nfelia.com/verify ═════════════════════════════ */

section('nfelia.com/verify: genuine, who signed, what changed');
$v = new Http();
$v->get('verify/' . $code);
ok($v->status === 200 && str_contains($v->body, 'Genuine and signed') && str_contains($v->body, 'Invoice ' . $inv['doc_number']), 'the invoice\'s code: genuine, and signed');
ok(str_contains($v->body, 'Signed for Ada&#039;s Hair &amp; Co by Ada Obi, Owner') && str_contains($v->body, 'Seal checks out. Nothing has changed since it was signed'),
   'who signs for the business now, with the seal checked');
ok(str_contains($v->body, 'Signed for Ada&#039;s Hair &amp; Co by Bisi Admin, Admin') && str_contains($v->body, 'version 2'), 'every version is listed');
ok(!str_contains($v->body, 'Ngozi Eze'), 'the customer\'s name is never shown in full');
$v->get('verify/' . doc_code_display($code));
ok($v->status === 200 && str_contains($v->body, 'Genuine'), 'the code with its dash works too');
$v->get('verify/' . strtolower($code));
ok($v->status === 200, 'and in small letters');

$found = doc_verify_lookup($db, $code);
$v1 = $found['signatures'][0];
ok($v1['seal_ok'] && !$v1['standing'] && !$v1['unchanged'] && in_array('the items', $v1['what_changed'], true) && in_array('the total', $v1['what_changed'], true),
   'version 1 still checks out, and says what changed since: the items, the total');

$latest = standing('invoice', $inv_id);
order_update_lines($db, BIZ, (int)$o['id'], [['name' => 'Body Wave 18in', 'quantity' => 5, 'unit_price' => 65000]], 0, CHIDI, '₦');
$v->get('verify/' . $code);
ok(str_contains($v->body, 'Genuine, changed since it was signed') && str_contains($v->body, 'Changed since it was signed: the items'), 'changed by staff: verify says so');

exec_sql("UPDATE doc_signatures SET signer_name = 'Someone Else' WHERE id = ?", [$latest['id']]);
ok(!doc_seal_ok(standing('invoice', $inv_id)), 'a signature edited in the database no longer matches its seal');
$v->get('verify/' . $code);
ok(str_contains($v->body, 'A signature doesn\'t check out') && str_contains($v->body, 'it was altered after it was signed'), 'and verify says so');
exec_sql("UPDATE doc_signatures SET signer_name = 'Ada Obi' WHERE id = ?", [$latest['id']]);

$qcode = doc_code($db, BIZ, 'quote', $q_signed);
$v->get('verify/' . $qcode);
ok($v->status === 200 && str_contains($v->body, 'Accepted and signed by Ngozi Eze') && str_contains($v->body, 'drawn signature'), 'a quote\'s code: who accepted it, and how');
$rcode = doc_code($db, BIZ, 'receipt', $rec_id);
$v->get('verify/' . $rcode);
ok($v->status === 200 && str_contains($v->body, 'is not signed') && str_contains($v->body, 'Genuine'), 'a receipt that isn\'t signed: genuine, not signed');
$v->get('verify/ZZZZZ-ZZZZZ');
ok($v->status === 404 && str_contains($v->body, 'We can'), 'a code that is nobody\'s is not found');
ok(!q("SELECT COUNT(*) FROM records_books WHERE code IN (SELECT code FROM doc_codes)"), 'document codes never clash with Records Book codes');

/* ══ Deeds, legacy and purge ═════════════════════════════════ */

section('A deed: the business signs, when the box says so');
$ada->post('deed/create', ['source' => 'invoice', 'source_id' => (string)$inv_id, 'provenance_text' => '', 'sign_box' => '1', 'sign_on' => '1']);
$deed = rows("SELECT * FROM deeds WHERE invoice_id = ? ORDER BY id DESC LIMIT 1", [$inv_id])[0] ?? [];
$ds = $deed ? standing('deed', (int)$deed['id']) : null;
ok($deed && (int)$deed['sign_on'] === 1 && $ds && $ds['style'] === 'drawn' && $ds['image_path'] === $mine, 'signed with Ada\'s drawing: deeds are set to drawn');
$cust->get('deed/' . $deed['token']);
ok(str_contains($cust->body, 'data-verify-code=') && str_contains($cust->body, 'Signed for') && !str_contains($cust->body, 'Authorized by'), 'its page: the signature and the QR code, no blank line');
$cust->get('deed/download/' . $deed['token']);
ok($cust->status === 200 && str_starts_with($cust->body, '%PDF'), 'its PDF is made');
$ada->post('deed/create', ['source' => 'invoice', 'source_id' => (string)$inv_id, 'provenance_text' => '', 'sign_box' => '1']);
$deed2 = rows("SELECT * FROM deeds WHERE invoice_id = ? ORDER BY id DESC LIMIT 1", [$inv_id])[0];
ok((int)$deed2['sign_on'] === 0 && !sigs('deed', (int)$deed2['id']), 'with the box off, the deed is not signed');

section('Quotes and delivery notes signed before keep their signatures');
exec_sql("UPDATE quotations SET sign_on = 1, owner_signature_data = ?, owner_signed_by_name = 'Ada Obi', owner_signed_at = NOW(),
          signature_data = ?, signed_by_name = 'Ngozi Eze', signed_at = NOW() WHERE id = 504", [ADA_PEN, NGOZI_PEN]);
$st = doc_sign_state($db, 'quote', 504);
ok($st['business']['legacy'] && $st['business']['signer_name'] === 'Ada Obi' && $st['customer']['signer_name'] === 'Ngozi Eze' && $st['business']['seal'] === '',
   'an old quote shows both signatures as they were, without a seal');
ok($st['business']['image'] === ADA_PEN && $st['customer']['image'] === NGOZI_PEN, 'with the drawings they were signed with');

section('A purge takes its documents\' signatures and codes with them');
$gone_quote = $q_signed;
$drawing = standing('quote', $gone_quote, 'customer')['image_path'];
exec_sql("DELETE FROM quotation_items WHERE quotation_id = ?", [$gone_quote]);
exec_sql("DELETE FROM quotation_activities WHERE quotation_id = ?", [$gone_quote]);
exec_sql("UPDATE orders SET quotation_id = NULL WHERE quotation_id = ?", [$gone_quote]);
exec_sql("UPDATE invoices SET quotation_id = NULL WHERE quotation_id = ?", [$gone_quote]);
exec_sql("DELETE FROM quotations WHERE id = ?", [$gone_quote]);
$v->get('verify/' . $qcode);
ok($v->status === 200 && str_contains($v->body, 'Genuine, since removed'), 'a deleted quote\'s code says it was removed');
$files = doc_forget_missing($db, BIZ);
ok(!sigs('quote', $gone_quote) && !q("SELECT COUNT(*) FROM doc_codes WHERE doc_type = 'quote' AND doc_id = ?", [$gone_quote]), 'purged: its signatures and code are gone');
ok(in_array($drawing, $files, true) && !in_array($mine, $files, true), 'and the customer\'s drawing is handed back to be deleted, never Ada\'s own');
ok(standing('invoice', $inv_id) !== null, 'other documents keep theirs');

done();
