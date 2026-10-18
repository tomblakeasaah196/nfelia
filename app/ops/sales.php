<?php
/**
 * Sales in Nfelia Ops: leads, the pipeline, follow-ups and campaigns.
 *
 * Leads come in by themselves from the home page's form
 * (ops_lead_from_website), from sign-ups that stalled in setup and trials
 * about to end (ops_sales_cron), and the team adds the rest by hand.
 * Each moves through LEAD_STAGES. A lead with a follow-up time reminds its
 * owner when the time comes (bell and phone); one whose business starts
 * paying moves to Paying by itself.
 *
 * Campaigns: an email to a group of leads or clients. The people are
 * listed when Send is pressed (campaign_sends) and the cron hands them to
 * the outbox CAMPAIGN_PER_HOUR an hour, so the mail server is never
 * flooded and nothing looks like spam. Every one carries an unsubscribe
 * link (email_opt_outs); account, billing and security emails still go.
 */

require_once __DIR__ . '/alerts.php';

const LEAD_STAGES = [
    'new'       => ['label' => 'New',       'hint' => 'Not spoken to yet'],
    'contacted' => ['label' => 'Contacted', 'hint' => 'Spoken to, interested'],
    'demo'      => ['label' => 'Demo',      'hint' => 'Shown Nfelia, or booked to see it'],
    'trial'     => ['label' => 'Trial',     'hint' => 'Using the free trial'],
    'paying'    => ['label' => 'Paying',    'hint' => 'A paying client'],
    'lost'      => ['label' => 'Lost',      'hint' => 'Not for now'],
];
const LEAD_SOURCES = [
    'website' => 'Home page message', 'signup' => 'Signed up, stalled', 'trial' => 'Trial ending', 'manual' => 'We reached out',
    'referral' => 'Referred', 'event' => 'Event or market', 'social' => 'Social media', 'walk_in' => 'Walked in or called',
];
const LEAD_OPEN = ['new', 'contacted', 'demo', 'trial'];

/** Campaign emails handed to the mail server per hour (cPanel mail allows a few hundred). */
function campaign_per_hour(): int
{
    return max(12, (int)($_ENV['CAMPAIGN_PER_HOUR'] ?? 200));
}

/** A lead with the same email or phone that is still open, if any. */
function ops_lead_match(PDO $db, ?string $email, ?string $phone): ?array
{
    $email = $email ? strtolower(trim($email)) : null;
    $digits = $phone ? substr(preg_replace('/\D/', '', $phone), -10) : null;
    if (!$email && (!$digits || strlen($digits) < 7)) {
        return null;
    }
    $stmt = $db->prepare("SELECT * FROM leads WHERE stage <> 'lost' AND ((? IS NOT NULL AND email = ?) OR (? IS NOT NULL AND RIGHT(REGEXP_REPLACE(phone, '[^0-9]', ''), 10) = ?))
                          ORDER BY id DESC LIMIT 1");
    $stmt->execute([$email, $email, $digits, $digits]);
    return $stmt->fetch() ?: null;
}

/** A note on a lead (staff null: Nfelia itself). */
function ops_lead_note(PDO $db, int $lead_id, ?int $staff_id, string $body): void
{
    $db->prepare("INSERT INTO ops_notes (subject_type, subject_id, staff_id, body) VALUES ('lead', ?, ?, ?)")->execute([$lead_id, $staff_id, mb_substr($body, 0, 4000)]);
}

/**
 * A message from the home page's form: a new lead (or a note on the one
 * already open for that email or phone), and Sales is told. Never throws.
 */
function ops_lead_from_website(int $feedback_id, string $name, string $contact, string $message): ?int
{
    try {
        $db = get_db();
        $email = filter_var($contact, FILTER_VALIDATE_EMAIL) ? strtolower($contact) : null;
        $phone = !$email && preg_match('/\d{7,}/', preg_replace('/\D/', '', $contact)) ? mb_substr($contact, 0, 40) : null;
        if ($lead = ops_lead_match($db, $email, $phone)) {
            ops_lead_note($db, (int)$lead['id'], null, 'Wrote again from the home page: ' . $message);
            $db->prepare("UPDATE leads SET next_follow_up_at = COALESCE(next_follow_up_at, NOW()) WHERE id = ?")->execute([$lead['id']]);
            $id = (int)$lead['id'];
        } else {
            $db->prepare("INSERT INTO leads (name, business_name, email, phone, source, stage, feedback_id, next_follow_up_at) VALUES (?, ?, ?, ?, 'website', 'new', ?, NOW())")
               ->execute([mb_substr($name, 0, 120), mb_substr($name, 0, 160), $email, $phone, $feedback_id]);
            $id = (int)$db->lastInsertId();
            ops_lead_note($db, $id, null, 'From the home page: ' . $message);
        }
        ops_alert($db, 'lead', 'Message from the home page', $name . ' (' . ($contact !== '' ? $contact : 'no contact given') . '): ' . notify_excerpt($message, 140),
                  'leads/' . $id, 'prospect:' . $feedback_id, ['permission' => 'sales']);
        return $id;
    } catch (Throwable $e) {
        errors_report_throwable($e, ['job' => 'Lead from the home page']);
        return null;
    }
}

/* ═══════════════════════════════════════════════════════════════════
 *  THE CRON: LEADS THAT COME IN BY THEMSELVES, REMINDERS, CAMPAIGNS
 * ═══════════════════════════════════════════════════════════════════ */

function ops_sales_cron(PDO $db, DateTimeImmutable $now): array
{
    $done = ['leads_new' => 0, 'leads_paying' => 0, 'follow_ups' => 0, 'campaign_queued' => 0];

    // Signed up 1 to 14 days ago and never finished setting up.
    $stalled = $db->query("
        SELECT b.id, b.name, b.email, b.phone, u.full_name, u.email AS owner_email, u.phone AS owner_phone
        FROM businesses b
        JOIN users u ON u.business_id = b.id AND u.role = 'owner' AND u.is_active = 1
        LEFT JOIN leads l ON l.business_id = b.id
        WHERE b.onboarding_complete = 0 AND b.created_at < NOW() - INTERVAL 1 DAY AND b.created_at > NOW() - INTERVAL 14 DAY AND l.id IS NULL
        GROUP BY b.id LIMIT 50")->fetchAll();
    foreach ($stalled as $b) {
        $done['leads_new'] += ops_lead_for_business($db, $b, 'signup', 'new', 'Signed up but has not finished setting up.');
    }

    // A free trial ending within 3 days.
    $trials = $db->query("
        SELECT b.id, b.name, b.email, b.phone, u.full_name, u.email AS owner_email, u.phone AS owner_phone, s.trial_ends_at
        FROM subscriptions s
        JOIN businesses b ON b.id = s.business_id AND b.is_active = 1
        JOIN users u ON u.business_id = b.id AND u.role = 'owner' AND u.is_active = 1
        WHERE s.status = 'trial' AND s.trial_ends_at BETWEEN CURDATE() AND CURDATE() + INTERVAL 3 DAY
        GROUP BY b.id LIMIT 100")->fetchAll();
    foreach ($trials as $b) {
        $lead = $db->query("SELECT * FROM leads WHERE business_id = " . (int)$b['id'])->fetch();
        $why = 'Free trial ends ' . date('l j M', strtotime($b['trial_ends_at'])) . '.';
        if (!$lead) {
            $done['leads_new'] += ops_lead_for_business($db, $b, 'trial', 'trial', $why);
        } elseif (in_array($lead['stage'], ['new', 'contacted', 'demo'], true)) {
            $db->prepare("UPDATE leads SET stage = 'trial', stage_changed_at = NOW(), next_follow_up_at = COALESCE(next_follow_up_at, NOW()) WHERE id = ?")->execute([$lead['id']]);
            ops_lead_note($db, (int)$lead['id'], null, $why . ' Moved to Trial.');
        }
    }

    // Leads whose business now pays: Paying, by itself.
    $paying = $db->query("SELECT l.id FROM leads l JOIN subscriptions s ON s.business_id = l.business_id
                          WHERE s.status = 'active' AND l.stage <> 'paying'")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($paying as $id) {
        $db->prepare("UPDATE leads SET stage = 'paying', stage_changed_at = NOW(), next_follow_up_at = NULL WHERE id = ?")->execute([$id]);
        ops_lead_note($db, (int)$id, null, 'Started paying. Moved to Paying.');
        $done['leads_paying']++;
    }

    // Follow-ups whose time has come: the lead's owner is told, or all of Sales.
    $due = $db->query("SELECT * FROM leads WHERE next_follow_up_at <= NOW() AND stage IN ('new', 'contacted', 'demo', 'trial')
                       AND (follow_up_reminded_at IS NULL OR follow_up_reminded_at < next_follow_up_at) LIMIT 100")->fetchAll();
    foreach ($due as $l) {
        $title = 'Follow up: ' . ($l['business_name'] ?: $l['name']);
        $body  = ($l['follow_up_note'] ?: LEAD_STAGES[$l['stage']]['label'] . ', ' . (LEAD_SOURCES[$l['source']] ?? $l['source'])) . ($l['phone'] ? ' · ' . $l['phone'] : '');
        $key   = 'follow-up:' . $l['id'] . ':' . $l['next_follow_up_at'];
        if ($l['owner_id']) {
            ops_alert_one($db, (int)$l['owner_id'], 'lead.follow_up', $title, $body, 'leads/' . $l['id'], $key);
        } else {
            ops_alert($db, 'lead.follow_up', $title, $body, 'leads/' . $l['id'], $key, ['permission' => 'sales']);
        }
        $db->prepare("UPDATE leads SET follow_up_reminded_at = NOW() WHERE id = ?")->execute([$l['id']]);
        $done['follow_ups']++;
    }

    $done['campaign_queued'] = ops_campaign_drip($db);
    return $done;
}

/** A lead for a business that signed up. Returns 1 when made. */
function ops_lead_for_business(PDO $db, array $b, string $source, string $stage, string $note): int
{
    try {
        $db->prepare("INSERT INTO leads (name, business_name, email, phone, source, stage, business_id, next_follow_up_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())")
           ->execute([mb_substr((string)($b['full_name'] ?: $b['name']), 0, 120), mb_substr((string)$b['name'], 0, 160),
                      strtolower((string)($b['owner_email'] ?: $b['email'])) ?: null, ($b['owner_phone'] ?: $b['phone']) ?: null, $source, $stage, (int)$b['id']]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') return 0;   // made a moment ago
        throw $e;
    }
    ops_lead_note($db, (int)$db->lastInsertId(), null, $note);
    return 1;
}

/* ═══════════════════════════════════════════════════════════════════
 *  CAMPAIGNS
 * ═══════════════════════════════════════════════════════════════════ */

/** Who a campaign can go to: the choices on its page. */
const CAMPAIGN_CLIENT_GROUPS = [
    'trial_ending' => 'Clients whose trial ends this week',
    'trial'        => 'Clients on a free trial',
    'past_due'     => 'Clients whose payment is due',
    'cancelled'    => 'Clients who cancelled',
    'active'       => 'Paying clients',
    'stalled'      => 'Clients who never finished setting up',
];

/**
 * Everyone an audience reaches, once each, with a working email, leaving
 * out whoever unsubscribed: [email => [email, name, business, lead_id, business_id]].
 *   audience: {"leads": ["new", "contacted", ...], "clients": ["trial", ...]}
 */
function ops_campaign_people(PDO $db, array $audience): array
{
    $people = [];
    $add = function (?string $email, ?string $name, ?string $business, ?int $lead, ?int $biz) use (&$people) {
        $email = strtolower(trim((string)$email));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || isset($people[$email])) return;
        $people[$email] = ['email' => $email, 'name' => $name, 'business' => $business, 'lead_id' => $lead, 'business_id' => $biz];
    };
    $stages = array_values(array_intersect((array)($audience['leads'] ?? []), array_keys(LEAD_STAGES)));
    if ($stages) {
        $stmt = $db->prepare("SELECT id, name, business_name, email, business_id FROM leads WHERE stage IN (" . implode(',', array_fill(0, count($stages), '?')) . ")");
        $stmt->execute($stages);
        foreach ($stmt->fetchAll() as $l) $add($l['email'], $l['name'], $l['business_name'], (int)$l['id'], $l['business_id'] ? (int)$l['business_id'] : null);
    }
    foreach (array_intersect((array)($audience['clients'] ?? []), array_keys(CAMPAIGN_CLIENT_GROUPS)) as $group) {
        $where = match ($group) {
            'trial_ending' => "s.status = 'trial' AND s.trial_ends_at BETWEEN CURDATE() AND CURDATE() + INTERVAL 7 DAY",
            'stalled'      => "b.onboarding_complete = 0",
            'past_due'     => "(" . ops_plan_sql('due') . " OR " . ops_plan_sql('paused') . ")",   // in their grace days, or paused unpaid
            default        => ops_plan_sql($group),
        };
        foreach ($db->query("SELECT b.id, b.name, u.full_name, u.email FROM businesses b
                             JOIN users u ON u.business_id = b.id AND u.role = 'owner' AND u.is_active = 1
                             LEFT JOIN subscriptions s ON s.business_id = b.id
                             WHERE b.is_active = 1 AND {$where}")->fetchAll() as $c) {
            $add($c['email'], $c['full_name'], $c['name'], null, (int)$c['id']);
        }
    }
    if ($people) {
        $emails = array_keys($people);
        $stmt = $db->prepare("SELECT email FROM email_opt_outs WHERE email IN (" . implode(',', array_fill(0, count($emails), '?')) . ")");
        $stmt->execute($emails);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $out) unset($people[strtolower($out)]);
    }
    return $people;
}

/** The variables of one campaign email (the template views/emails/ops_campaign.php). */
function ops_campaign_vars(array $campaign, array $person, string $unsubscribe_url): array
{
    $first = preg_split('/\s+/u', trim((string)($person['name'] ?? '')))[0] ?? '';
    $fill  = fn(string $s) => strtr($s, ['{first_name}' => $first !== '' ? $first : 'there', '{business}' => (string)($person['business'] ?: 'your business')]);
    return [
        'subject'     => $fill($campaign['subject']),
        'body'        => $fill($campaign['body']),
        'button_text' => $campaign['button_text'] ?: null,
        'button_url'  => $campaign['button_url'] ?: null,
        'unsubscribe' => $unsubscribe_url,
    ];
}

/**
 * Hand waiting campaign emails to the outbox: about a twelfth of the hourly
 * allowance each 5-minute run. A campaign with nobody left waiting is sent.
 */
function ops_campaign_drip(PDO $db, ?int $limit = null): int
{
    $limit ??= max(1, (int)ceil(campaign_per_hour() / 12));
    $rows = $db->query("SELECT cs.*, c.subject, c.body, c.button_text, c.button_url FROM campaign_sends cs JOIN campaigns c ON c.id = cs.campaign_id
                        WHERE cs.status = 'waiting' ORDER BY cs.id LIMIT " . (int)$limit)->fetchAll();
    $queued = 0;
    foreach ($rows as $r) {
        $out = $db->prepare("SELECT 1 FROM email_opt_outs WHERE email = ?");
        $out->execute([$r['email']]);
        if ($out->fetchColumn()) {
            $db->prepare("UPDATE campaign_sends SET status = 'skipped' WHERE id = ?")->execute([$r['id']]);
            continue;
        }
        $unsubscribe = APP_URL . '/unsubscribe/' . $r['id'] . '-' . $r['token'];
        $vars = ops_campaign_vars($r, $r, $unsubscribe);
        $key = 'campaign:' . $r['campaign_id'] . ':' . $r['email'];
        // One-click unsubscribe from the mail app itself (RFC 8058): it posts to the same link.
        $id = notify_email(null, $r['email'], (string)($r['name'] ?? ''), $vars['subject'], 'ops_campaign', $vars, $key, 'campaign',
                           ['List-Unsubscribe' => '<' . $unsubscribe . '>', 'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click']);
        if (!$id) {
            // Queued already by a run that stopped before noting it.
            $same = $db->prepare("SELECT id FROM notify_outbox WHERE dedupe_key = ? AND channel = 'email' LIMIT 1");
            $same->execute([mb_substr($key, 0, 150)]);
            $id = (int)$same->fetchColumn() ?: null;
        }
        $db->prepare("UPDATE campaign_sends SET status = ?, outbox_id = ?, queued_at = NOW() WHERE id = ?")->execute([$id ? 'queued' : 'failed', $id, $r['id']]);
        if ($id) $queued++;
    }
    // Sent: nobody left waiting.
    $db->exec("UPDATE campaigns c SET status = 'sent' WHERE status = 'sending' AND NOT EXISTS (SELECT 1 FROM campaign_sends cs WHERE cs.campaign_id = c.id AND cs.status = 'waiting')");
    // How each went, kept here: the outbox forgets after a month.
    $db->exec("UPDATE campaign_sends cs JOIN notify_outbox o ON o.id = cs.outbox_id SET cs.status = o.status WHERE cs.status = 'queued' AND o.status IN ('sent', 'failed')");
    return $queued;
}

/** Turn a lead's stage into a chip. */
function ops_lead_stage_chip(string $stage): string
{
    $tone = match ($stage) { 'paying' => 'ok', 'lost' => 'muted', 'trial' => 'warn', 'new' => 'accent', default => '' };
    return '<span class="ops-chip' . ($tone ? ' ops-chip-' . $tone : '') . '">' . e(LEAD_STAGES[$stage]['label'] ?? $stage) . '</span>';
}
