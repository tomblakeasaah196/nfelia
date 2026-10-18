<?php
/**
 * Nfelia Ops, sales: leads from the home page, by hand, from stalled
 * sign-ups and ending trials; the pipeline; whose and follow-ups (reminded
 * by the real cron); campaigns (who they reach, a test, sending once, a few
 * at a time, unsubscribing); and who may do what.
 *   bash tests/notifications/run.sh ops/sales
 */
require __DIR__ . '/lib.php';
t_guard();
fixture_reset();
clean_slate();
logs_read_up_to_now();
exec_sql("DELETE FROM auth_throttle");

const GRACE = 1, SAM = 2, LOLA = 3;
$grace = as_ops('grace@nfelia.test');
$sam   = as_ops('sam@nfelia.test');
$lola  = as_ops('lola@nfelia.test');

/** A lead's row. */
function lead(int $id): array
{
    return rows("SELECT * FROM leads WHERE id = ?", [$id])[0] ?? [];
}

function lead_notes(int $id): array
{
    return array_column(rows("SELECT body FROM ops_notes WHERE subject_type = 'lead' AND subject_id = ? ORDER BY id", [$id]), 'body');
}

/** A time on the database's clock, as a datetime-local field sends it. */
function form_time(string $interval): string
{
    return (string)q("SELECT DATE_FORMAT(NOW() + INTERVAL {$interval}, '%Y-%m-%dT%H:%i')");
}

/* ── Leads that write in ────────────────────────────────────── */

section('A message from the home page becomes a lead');

$visitor = new Http();
$visitor->get('');
$visitor->post('public-feedback', ['enterprise' => 'Mama Put Kitchen', 'contact' => 'Funmi@MamaPut.test', 'message' => 'How much is it a month?']);
$web = (int)q("SELECT id FROM leads WHERE source = 'website' ORDER BY id DESC LIMIT 1");
ok($web > 0, 'a lead is made');
$l = lead($web);
eq([$l['stage'], $l['email'], $l['business_name']], ['new', 'funmi@mamaput.test', 'Mama Put Kitchen'], 'New, with the email to reply on');
eq((int)$l['feedback_id'], (int)q("SELECT MAX(id) FROM general_feedback"), 'linked to the message');
ok($l['next_follow_up_at'] !== null, 'and due a follow-up now');
eq(lead_notes($web), ['From the home page: How much is it a month?'], 'the message is its first note');
eq(ops_told('lead'), [GRACE, LOLA], 'Admin and Sales are told, not Support');
eq((string)q("SELECT url FROM ops_alerts WHERE kind = 'lead' AND staff_id = ?", [LOLA]), "leads/{$web}", 'the alert opens the lead');

$visitor->post('public-feedback', ['enterprise' => 'Mama Put', 'contact' => 'funmi@mamaput.test', 'message' => 'Also, do you do receipts?']);
eq((int)q("SELECT COUNT(*) FROM leads"), 1, 'writing again from the same email adds no second lead');
eq(lead_notes($web)[1] ?? '', 'Wrote again from the home page: Also, do you do receipts?', 'but a note on the first');
eq(count(ops_bell(LOLA)), 2, 'and Sales hears about it again');

$lola->get(o("leads/{$web}"));
eq($lola->status, 200, 'Sales opens the lead');
ok(str_contains($lola->body, 'How much is it a month?') && str_contains($lola->body, 'Also, do you do receipts?'), 'and reads both messages');
ok(str_contains($lola->body, 'mailto:funmi@mamaput.test'), 'with a button to email back');

/* ── Leads the team adds ────────────────────────────────────── */

section('Adding someone the team reached out to');

$lola->get(o('leads'));
$lola->post(o('leads'), ['name' => 'Bola', 'business_name' => '', 'phone' => '', 'email' => 'not an email']);
eq((int)q("SELECT COUNT(*) FROM leads"), 1, 'a name alone is not enough: a phone or an email too');
$lola->get(o('leads'));
ok(str_contains($lola->body, 'Give at least their name'), 'and she is told so');

$tomorrow = form_time('1 DAY');
$lola->post(o('leads'), ['name' => 'Bola Ade', 'business_name' => 'Bola Fabrics', 'phone' => '0803 555 0199', 'email' => '', 'source' => 'event',
                         'stage' => 'contacted', 'follow_up' => $tomorrow, 'follow_up_note' => 'Send the price list', 'note' => 'Met at the Balogun market fair.']);
$bola = (int)q("SELECT id FROM leads WHERE business_name = 'Bola Fabrics'");
eq(ops_to($lola), "leads/{$bola}", 'added, and opened');
$l = lead($bola);
eq([$l['source'], $l['stage'], (int)$l['owner_id'], (int)$l['created_by']], ['event', 'contacted', LOLA, LOLA], 'from an event, Contacted, and hers');
eq(substr((string)$l['next_follow_up_at'], 0, 16), str_replace('T', ' ', $tomorrow), 'with the follow-up she chose');
eq($l['follow_up_note'], 'Send the price list', 'and what about');
ok($l['last_contact_at'] !== null, 'contacted, so the contact is noted');
eq(lead_notes($bola), ['Met at the Balogun market fair.'], 'her note is kept');
eq(count(audit_rows('lead.added')), 1, 'the audit log has it');

$lola->post(o('leads'), ['name' => 'Bola again', 'phone' => '+234 803 555 0199', 'source' => 'social']);
eq((int)q("SELECT COUNT(*) FROM leads"), 2, 'the same number written another way adds no second lead');
eq(ops_to($lola), "leads/{$bola}", 'she is shown the one there is');

$grace->get(o('leads'));
$grace->post(o('leads'), ['name' => 'Chika Obi', 'business_name' => 'Chika Cakes', 'email' => 'chika@cakes.test', 'source' => 'walk_in', 'stage' => 'new']);
$chika = (int)q("SELECT id FROM leads WHERE business_name = 'Chika Cakes'");
$l = lead($chika);
eq([$l['stage'], $l['last_contact_at'], $l['next_follow_up_at']], ['new', null, null], 'a New lead: not contacted, no follow-up');

/* ── The list and the board ─────────────────────────────────── */

section('The list and the board');

$lola->get(o('leads'));
foreach (['Mama Put Kitchen', 'Bola Fabrics', 'Chika Cakes'] as $name) {
    ok(str_contains($lola->body, e($name)), "the list shows {$name}");
}
$lola->get(o('leads?q=0803555'));
ok(str_contains($lola->body, 'Bola Fabrics') && !str_contains($lola->body, 'Chika Cakes'), 'search finds a lead by phone');
$lola->get(o('leads?owner=me'));
ok(str_contains($lola->body, 'Bola Fabrics') && !str_contains($lola->body, 'Chika Cakes'), '"Mine" shows her own');
$lola->get(o('leads?source=walk_in'));
ok(str_contains($lola->body, 'Chika Cakes') && !str_contains($lola->body, 'Bola Fabrics'), 'filtered by where they came from');
$lola->get(o('leads?due=1'));
ok(str_contains($lola->body, 'Mama Put Kitchen') && str_contains($lola->body, 'Bola Fabrics') && !str_contains($lola->body, 'Chika Cakes'), '"Follow up by tomorrow" shows who is due');
$lola->get(o('leads?view=board'));
eq($lola->status, 200, 'the board opens');
ok((bool)preg_match('/aria-label="Contacted".*?Bola Fabrics/s', $lola->body) && (bool)preg_match('/aria-label="New".*?Chika Cakes/s', $lola->body), 'with each lead in its stage\'s column');

/* ── The pipeline ───────────────────────────────────────────── */

section('Moving along the pipeline');

$lola->get(o("leads/{$bola}"));
$lola->post(o("leads/{$bola}/stage"), ['stage' => 'demo']);
eq(lead($bola)['stage'], 'demo', 'Contacted → Demo');
$a = audit_rows('lead.stage');
eq(json_decode((string)end($a)['data'], true), ['from' => 'contacted', 'to' => 'demo'], 'written down, from and to');

$lola->post(o("leads/{$chika}/stage"), ['stage' => 'lost', 'reason' => '']);
eq(lead($chika)['stage'], 'new', 'Lost needs a reason');
$lola->post(o("leads/{$chika}/stage"), ['stage' => 'lost', 'reason' => 'Uses a notebook, happy with it']);
$l = lead($chika);
eq([$l['stage'], $l['lost_reason']], ['lost', 'Uses a notebook, happy with it'], 'with one, it is Lost');
$lola->get(o("leads/{$chika}"));
ok(str_contains($lola->body, 'Lost: Uses a notebook, happy with it'), 'the reason shows on the lead');
$lola->get(o('leads'));
ok(!str_contains($lola->body, 'Chika Cakes'), 'and it leaves the open list');
$lola->get(o('leads?stage=lost'));
ok(str_contains($lola->body, 'Chika Cakes'), 'but is there under Lost');
$lola->post(o('leads'), ['name' => 'Chika Obi', 'email' => 'chika@cakes.test', 'source' => 'social', 'stage' => 'contacted']);
eq((int)q("SELECT COUNT(*) FROM leads WHERE email = 'chika@cakes.test'"), 2, 'a lost lead can come back as a new one');
$chika2 = (int)q("SELECT MAX(id) FROM leads WHERE email = 'chika@cakes.test'");

section('Notes, and changing details');

$grace->get(o("leads/{$web}"));
$grace->post(o("leads/{$web}/notes"), ['body' => 'Called Funmi: three branches, wants it for all.', 'contacted' => '1']);
$l = lead($web);
eq($l['stage'], 'contacted', 'a note saying "I spoke to them" moves a New lead to Contacted');
ok($l['last_contact_at'] !== null, 'and notes the contact');
$grace->post(o("leads/{$web}"), ['name' => 'Funmi Bello', 'business_name' => 'Mama Put Kitchen', 'email' => 'funmi@mamaput.test', 'phone' => '0802 111 2222', 'source' => 'website']);
eq([lead($web)['name'], lead($web)['phone']], ['Funmi Bello', '0802 111 2222'], 'details can be changed');

/* ── Whose, and follow-ups ──────────────────────────────────── */

section('Whose lead it is');

$grace->post(o("leads/{$web}/owner"), ['owner_id' => LOLA]);
eq((int)lead($web)['owner_id'], LOLA, 'Grace gives the lead to Lola');
eq(last_bell(LOLA)['kind'] ?? '', 'lead.assigned', 'Lola is told');
eq(last_bell(LOLA)['title'] ?? '', 'Grace Admin gave you a lead', 'by whom');
$grace->post(o("leads/{$web}/owner"), ['owner_id' => SAM]);
eq((int)lead($web)['owner_id'], LOLA, 'a lead cannot go to someone outside Sales');
$grace->post(o("leads/{$web}/owner"), ['owner_id' => 4]);
eq((int)lead($web)['owner_id'], LOLA, 'nor to someone who left');

section('Follow-ups');

$lola->get(o("leads/{$web}"));
$lola->post(o("leads/{$web}/follow-up"), ['follow_up' => '']);
ok(lead($web)['follow_up_note'] === null, 'a follow-up needs a time');
$lola->post(o("leads/{$web}/follow-up"), ['follow_up' => form_time('2 DAY'), 'follow_up_note' => 'Demo for the three branches']);
$l = lead($web);
eq($l['follow_up_note'], 'Demo for the three branches', 'set, with what about');
$lola->post(o("leads/{$web}/follow-up"), ['done' => '1']);
$l = lead($web);
eq([$l['next_follow_up_at'], $l['follow_up_note']], [null, null], '"Done" clears it');

/* ── The cron: reminders, and leads that come by themselves ─── */

section('The cron reminds whoever follows a lead up (a real run)');

$phone = ops_phone(LOLA);
exec_sql("UPDATE leads SET next_follow_up_at = NOW() - INTERVAL 1 MINUTE, follow_up_note = 'Send the price list' WHERE id = ?", [$bola]);
exec_sql("UPDATE leads SET next_follow_up_at = NOW() - INTERVAL 1 MINUTE, owner_id = NULL WHERE id = ?", [$chika2]);
exec_sql("UPDATE leads SET next_follow_up_at = NOW() + INTERVAL 1 HOUR WHERE id = ?", [$web]);
exec_sql("DELETE FROM ops_alerts");
$out = cron_at('10:00');
ok(str_contains($out, 'follow_ups=2'), 'the cron reports two follow-ups due' . (str_contains($out, 'follow_ups=2') ? '' : ": {$out}"));
$mine = rows("SELECT * FROM ops_alerts WHERE kind = 'lead.follow_up' AND url = ?", ["leads/{$bola}"]);
eq(array_map('intval', array_column($mine, 'staff_id')), [LOLA], 'Bola\'s follow-up goes to Lola alone (hers)');
eq([$mine[0]['title'] ?? '', $mine[0]['body'] ?? ''], ['Follow up: Bola Fabrics', 'Send the price list · 0803 555 0199'], 'saying who, what about, and the number');
$nobodys = rows("SELECT staff_id FROM ops_alerts WHERE kind = 'lead.follow_up' AND url = ?", ["leads/{$chika2}"]);
eq(array_map('intval', array_column($nobodys, 'staff_id')), [GRACE, LOLA], 'a lead nobody has reminds all of Sales');
ok(!rows("SELECT 1 FROM ops_alerts WHERE url = ?", ["leads/{$web}"]), 'one not due yet waits');
ok((bool)array_filter(pushes_to($phone), fn($p) => ($p['title'] ?? '') === 'Follow up: Bola Fabrics'), 'Lola\'s phone gets it too');

cron_at('10:05');
eq((int)q("SELECT COUNT(*) FROM ops_alerts WHERE kind = 'lead.follow_up'"), 3, 'the next run does not remind again');
$lola->get(o("leads/{$bola}"));
$lola->post(o("leads/{$bola}/follow-up"), ['follow_up' => form_time('0 MINUTE'), 'follow_up_note' => 'Call back about the price']);
cron_at('10:10');
eq((int)q("SELECT COUNT(*) FROM ops_alerts WHERE kind = 'lead.follow_up' AND url = ?", ["leads/{$bola}"]), 2, 'a new follow-up time reminds again');

section('Stalled sign-ups and ending trials become leads; paying ones move by themselves');

exec_sql("INSERT INTO businesses (id, name, slug, doc_prefix, email, phone, country_id, onboarding_complete, onboarding_step, is_active, created_at)
          VALUES (4, 'Tunde Tailoring', 'tunde-tailoring', 'TUN', 'shop@tunde.test', '08077770000', 1, 0, 2, 1, NOW() - INTERVAL 3 DAY),
                 (5, 'Fresh Signup', 'fresh-signup', 'FRS', 'fresh@signup.test', NULL, 1, 0, 1, 1, NOW() - INTERVAL 2 HOUR)");
exec_sql("INSERT INTO users (id, business_id, role, full_name, email, phone, password_hash, is_email_verified, is_active)
          VALUES (40, 4, 'owner', 'Tunde Bakare', 'tunde@tunde.test', '08077771111', 'x', 1, 1),
                 (50, 5, 'owner', 'Fresh Person', 'fresh@person.test', NULL, 'x', 1, 1)");
exec_sql("UPDATE subscriptions SET trial_ends_at = CURDATE() + INTERVAL 2 DAY WHERE business_id = 3");
$out = cron_at('10:15');
$tunde = rows("SELECT * FROM leads WHERE business_id = 4")[0] ?? [];
eq([$tunde['source'] ?? '', $tunde['stage'] ?? '', $tunde['name'] ?? '', $tunde['email'] ?? '', $tunde['phone'] ?? ''],
   ['signup', 'new', 'Tunde Bakare', 'tunde@tunde.test', '08077771111'], 'a sign-up stalled for days becomes a New lead, with the owner\'s contact');
eq(lead_notes((int)($tunde['id'] ?? 0)), ['Signed up but has not finished setting up.'], 'saying why');
ok(!rows("SELECT 1 FROM leads WHERE business_id = 5"), 'one that signed up an hour ago is left to finish');
$kola = rows("SELECT * FROM leads WHERE business_id = 3")[0] ?? [];
eq([$kola['source'] ?? '', $kola['stage'] ?? ''], ['trial', 'trial'], 'a trial ending in two days becomes a Trial lead');
ok(str_starts_with(lead_notes((int)($kola['id'] ?? 0))[0] ?? '', 'Free trial ends '), 'saying when it ends');
ok(!rows("SELECT 1 FROM leads WHERE business_id = 2"), 'a paying client is not a lead');
ok((bool)rows("SELECT 1 FROM ops_alerts WHERE kind = 'lead.follow_up' AND url = ?", ['leads/' . ($tunde['id'] ?? 0)]), 'and Sales is reminded to call them');

cron_at('10:20');
eq((int)q("SELECT COUNT(*) FROM leads WHERE business_id IS NOT NULL"), 2, 'the next run makes no second lead');

exec_sql("UPDATE subscriptions SET status = 'active', plan = 'standard', current_period_end = CURDATE() + INTERVAL 30 DAY WHERE business_id = 3");
cron_at('10:25');
$kola = lead((int)$kola['id']);
eq([$kola['stage'], $kola['next_follow_up_at']], ['paying', null], 'when the trial turns into a paying plan, the lead moves to Paying');
ok(in_array('Started paying. Moved to Paying.', lead_notes((int)$kola['id']), true), 'with a note');
$lola->get(o(''));
ok(str_contains($lola->body, 'Won this month'), 'Sales\' home counts it as won this month');
$lola->get(o('leads/' . $kola['id']));
ok(str_contains($lola->body, 'Kola Prints') && str_contains($lola->body, ops_url('clients/3')), 'the lead links to the client');

/* ── Campaigns ──────────────────────────────────────────────── */

section('Writing a campaign');

exec_sql("UPDATE leads SET stage = 'contacted' WHERE id = ?", [$bola]);
exec_sql("UPDATE leads SET email = 'bola@fabrics.test' WHERE id = ?", [$bola]);
exec_sql("INSERT INTO leads (name, business_name, email, source, stage) VALUES ('Ada Again', 'Ada''s Hair', 'ADA@adashair.test', 'manual', 'contacted')");
// Contacted leads: Funmi, Bola, Chika (2nd), Ada (also a paying client). Paying clients: Tolu (Nfelia's own, an
// ordinary business now), Ada, Kola. Lost: Chika (1st, the same email).
$lola->get(o('campaigns/new'));
eq($lola->status, 200, 'Sales writes campaigns');
$lola->post(o('campaigns'), ['name' => 'Nope', 'subject' => '', 'body' => 'Short']);
eq((int)q("SELECT COUNT(*) FROM campaigns"), 0, 'a campaign needs a subject and a message');
$lola->post(o('campaigns'), ['name' => 'Half a button', 'subject' => 'Hi', 'body' => 'A proper message here.', 'button_text' => 'Look', 'button_url' => '']);
eq((int)q("SELECT COUNT(*) FROM campaigns"), 0, 'a button needs its address too');
$lola->post(o('campaigns'), [
    'name' => 'October offer', 'subject' => '{first_name}, your first month free',
    'body' => "Hello {first_name},\n\n{business} can try every part of Nfelia free for a month.\n\nReply to this email with any question.",
    'button_text' => 'Start free', 'button_url' => 'https://nfelia.com/register', 'leads' => ['contacted', 'bogus'], 'clients' => ['active'],
]);
$camp = (int)q("SELECT MAX(id) FROM campaigns");
eq(ops_to($lola), "campaigns/{$camp}", 'saved as a draft, and opened');
eq(json_decode((string)q("SELECT audience FROM campaigns WHERE id = ?", [$camp]), true), ['leads' => ['contacted'], 'clients' => ['active']], 'only real groups are kept');
$lola->get(o("campaigns/{$camp}"));
ok(str_contains($lola->body, 'Send to 6 people'), 'it reaches 6 people: each once, though Ada is both a lead and a client');
ok(str_contains($lola->body, e('srcdoc=')) || str_contains($lola->body, 'srcdoc="'), 'with a preview of the email');
ok(str_contains($lola->body, 'your first month free'), 'and its subject, filled in');

exec_sql("INSERT INTO email_opt_outs (email) VALUES ('funmi@mamaput.test')");
$lola->get(o("campaigns/{$camp}"));
ok(str_contains($lola->body, 'Send to 5 people'), 'whoever unsubscribed before is left out');

$lola->post(o("campaigns/{$camp}"), ['name' => 'October offer', 'subject' => '{first_name}, a month of Nfelia on us',
    'body' => "Hello {first_name},\n\n{business} can try every part of Nfelia free for a month.", 'button_text' => 'Start free',
    'button_url' => 'https://nfelia.com/register', 'leads' => ['contacted'], 'clients' => ['active']]);
eq((string)q("SELECT subject FROM campaigns WHERE id = ?", [$camp]), '{first_name}, a month of Nfelia on us', 'a draft can be changed');

section('A test to yourself');

$lola->get(o("campaigns/{$camp}"));
$lola->post(o("campaigns/{$camp}/test"));
$test = array_values(array_filter(mails_to('lola@nfelia.test'), fn($m) => str_starts_with($m['subject'], '[Test]')));
eq($test[0]['subject'] ?? '', '[Test] Lola, a month of Nfelia on us', 'Lola gets a test, with her own first name');
ok(str_contains($test[0]['text'] ?? '', 'Mama Put Kitchen can try every part'), 'and a sample business filled in');
eq((int)q("SELECT COUNT(*) FROM campaign_sends"), 0, 'nobody else gets anything');

section('Sending: once, a few at a time');

$lola->post(o("campaigns/{$camp}/send"));
eq((string)q("SELECT status FROM campaigns WHERE id = ?", [$camp]), 'sending', 'Send starts it');
eq((int)q("SELECT COUNT(*) FROM campaign_sends WHERE campaign_id = ? AND status = 'waiting'", [$camp]), 5, 'five people are waiting');
eq((int)q("SELECT COUNT(*) FROM notify_outbox WHERE event = 'campaign'"), 0, 'nothing is handed to the mail server yet');
eq(count(audit_rows('campaign.sent')), 1, 'the audit log has who sent it');
$grace->get(o("campaigns/{$camp}"));
$grace->post(o("campaigns/{$camp}/send"));
eq((int)q("SELECT COUNT(*) FROM campaign_sends WHERE campaign_id = ?", [$camp]), 5, 'pressing Send again (or someone else) sends nothing more');
$grace->post(o("campaigns/{$camp}"), ['name' => 'Changed', 'subject' => 'Changed', 'body' => 'Changed after sending.', 'leads' => ['contacted']]);
eq((string)q("SELECT name FROM campaigns WHERE id = ?", [$camp]), 'October offer', 'and a sent campaign cannot be changed');

$_ENV['CAMPAIGN_PER_HOUR'] = '24';
eq(ops_campaign_drip(get_db()), 2, 'at 24 an hour, each 5-minute run hands over 2');
$_ENV['CAMPAIGN_PER_HOUR'] = '1';
eq(campaign_per_hour(), 12, 'never fewer than 12 an hour, so a campaign always moves');
unset($_ENV['CAMPAIGN_PER_HOUR']);
eq(campaign_per_hour(), 200, '200 an hour unless set');
eq((int)q("SELECT COUNT(*) FROM campaign_sends WHERE campaign_id = ? AND status = 'waiting'", [$camp]), 3, 'three still wait');
eq((string)q("SELECT status FROM campaigns WHERE id = ?", [$camp]), 'sending', 'so it is still sending');
deliver();

$first = rows("SELECT * FROM campaign_sends WHERE campaign_id = ? AND status = 'queued' ORDER BY id LIMIT 1", [$camp])[0];
$mail = mails_to($first['email'])[0] ?? [];
$first_name = explode(' ', (string)$first['name'])[0];
eq($mail['subject'] ?? '', "{$first_name}, a month of Nfelia on us", 'each email is addressed to its person');
ok(str_contains($mail['text'] ?? '', ($first['business'] ?: 'your business') . ' can try every part'), 'with their business');
ok(str_contains($mail['html'] ?? '', 'https://nfelia.com/register') && str_contains($mail['text'] ?? '', 'Start free'), 'and the button');
ok(str_contains($mail['text'] ?? '', 'This email was sent by the Nfelia team.'), 'from the Nfelia team (not "Nfelia\'s workspace")');
$unsub = APP_URL . '/unsubscribe/' . $first['id'] . '-' . $first['token'];
ok(str_contains(html_entity_decode($mail['html'] ?? ''), $unsub), 'an unsubscribe link of its own');
eq([$mail['unsubscribe'] ?? '', $mail['unsubscribe_post'] ?? ''], ['<' . $unsub . '>', 'List-Unsubscribe=One-Click'], 'and one-click unsubscribe for mail apps');

section('Unsubscribing');

$reader = new Http();
$reader->get($unsub);
eq($reader->status, 200, 'the link opens');
ok(str_contains($reader->body, 'Unsubscribe?') && str_contains($reader->body, e($first['email'])), 'and asks first');
eq((int)q("SELECT COUNT(*) FROM email_opt_outs WHERE email = ?", [$first['email']]), 0, 'opening it alone (a mail scanner) changes nothing');
$reader->post($unsub);
ok(str_contains($reader->body, 'unsubscribed'), 'the button unsubscribes');
eq((int)q("SELECT campaign_id FROM email_opt_outs WHERE email = ?", [$first['email']]), $camp, 'from this campaign');
$reader->get(APP_URL . '/unsubscribe/' . $first['id'] . '-' . str_repeat('0', 32));
eq($reader->status, 404, 'a link with the wrong token does nothing');
$second = rows("SELECT * FROM campaign_sends WHERE campaign_id = ? AND status = 'queued' ORDER BY id DESC LIMIT 1", [$camp])[0];
(new Http())->post(APP_URL . '/unsubscribe/' . $second['id'] . '-' . $second['token'], ['List-Unsubscribe' => 'One-Click']);
eq((int)q("SELECT COUNT(*) FROM email_opt_outs WHERE email = ?", [$second['email']]), 1, 'a mail app\'s one-click unsubscribe works too');

$third = rows("SELECT * FROM campaign_sends WHERE campaign_id = ? AND status = 'waiting' ORDER BY id LIMIT 1", [$camp])[0];
exec_sql("INSERT INTO email_opt_outs (email) VALUES (?)", [$third['email']]);
ops_campaign_drip(get_db());
eq((string)q("SELECT status FROM campaign_sends WHERE id = ?", [$third['id']]), 'skipped', 'someone who unsubscribed while it was going out is skipped');
eq((int)q("SELECT COUNT(*) FROM campaign_sends WHERE campaign_id = ? AND status IN ('queued', 'sent')", [$camp]), 4, 'the rest are handed over');
eq((string)q("SELECT status FROM campaigns WHERE id = ?", [$camp]), 'sent', 'nobody left waiting: Sent');
deliver();

$lola->get(o("campaigns/{$camp}"));
ok((bool)preg_match('/Delivered<\/div><div class="value">4</', $lola->body), 'the campaign shows 4 delivered');
ok((bool)preg_match('/Unsubscribed<\/div><div class="value">2</', $lola->body), 'and 2 unsubscribed from it');
ops_campaign_drip(get_db());
eq((int)q("SELECT COUNT(*) FROM campaign_sends WHERE campaign_id = ? AND status = 'sent'", [$camp]), 4, 'how each went is kept with the campaign');
exec_sql("DELETE FROM notify_outbox WHERE event = 'campaign'");
$lola->get(o("campaigns/{$camp}"));
ok((bool)preg_match('/Delivered<\/div><div class="value">4</', $lola->body), 'so it still shows after the outbox forgets (a month on)');
$lola->get(o('campaigns'));
ok(str_contains($lola->body, 'October offer'), 'the list of campaigns has it');
$lola->get(o("leads/{$bola}"));
ok(str_contains($lola->body, 'October offer'), 'a lead\'s page shows the campaigns sent to them');

$lola->post(o('campaigns'), ['name' => 'November', 'subject' => 'Hi {first_name}', 'body' => 'Something new for {business}.', 'leads' => ['contacted'], 'clients' => ['active']]);
$next = ops_campaign_people(get_db(), ['leads' => ['contacted'], 'clients' => ['active']]);
ok(!isset($next[$first['email']]) && !isset($next[$second['email']]) && !isset($next['funmi@mamaput.test']), 'the next campaign leaves out everyone who unsubscribed');
ok(notify_email(null, $first['email'], 'x', 'Your receipt', 'notification', ['message' => 'x']) !== null, 'account emails still go to them');

/* ── Who may do what ────────────────────────────────────────── */

section('Who may do what');

$sam->get(o('leads'));
eq($sam->status, 403, 'Support cannot open leads');
$sam->get(o("leads/{$bola}"));
eq($sam->status, 403, 'nor one lead');
$sam->post(o('leads'), ['name' => 'Sneaky', 'phone' => '08000000001']);
eq((int)q("SELECT COUNT(*) FROM leads WHERE name = 'Sneaky'"), 0, 'nor add one');
$sam->post(o("leads/{$bola}/stage"), ['stage' => 'lost', 'reason' => 'Support should not']);
eq(lead($bola)['stage'], 'contacted', 'nor move one');
$sam->get(o('campaigns'));
eq($sam->status, 403, 'nor see campaigns');
$sam->post(o("campaigns/{$camp}/test"));
eq(count(array_filter(mails_to('sam@nfelia.test'), fn($m) => str_starts_with($m['subject'], '[Test]'))), 0, 'nor send one');
$sam->get(o(''));
ok(!str_contains($sam->body, ops_url('leads')) && !str_contains($sam->body, ops_url('campaigns')), 'Support\'s menu has no Sales');
$lola->get(o(''));
ok(str_contains($lola->body, ops_url('leads')) && str_contains($lola->body, ops_url('campaigns')), 'Sales\' menu has Leads and Campaigns');
ok(str_contains($lola->body, 'Follow-ups today'), 'and Sales\' home starts with today\'s follow-ups');
ok(!str_contains($lola->body, ops_url('errors')), 'but no Errors');
$lola->get(o('errors'));
eq($lola->status, 403, 'which Sales cannot open');
$grace->get(o('leads'));
eq($grace->status, 200, 'Admins see everything');

$errs = rows("SELECT title, file, line FROM error_groups WHERE level IN ('fatal', 'error', 'warning') AND kind <> 'slow'");
eq($errs, [], 'and none of it left an error behind');

done();
