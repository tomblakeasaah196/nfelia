<?php
/**
 * An email from someone in Ops to one client: written in Ops (Clients ›
 * a client › Email), sent in Nfelia's own branded design, from "<name> from
 * Nfelia". Replies go to support@nfelia.com, and the team gets a copy it can
 * follow up from (the owner, 27 Nov 2026). The words go out as written; a
 * Polish button asks Gemini for a cleaner version that the writer may take.
 * What was sent is kept as a note on the client's page.
 *
 * .env (both optional):
 *   OPS_EMAIL_REPLY_TO  where replies go      (support@nfelia.com)
 *   OPS_EMAIL_COPY      who gets a blind copy (praxisjbs@gmail.com; "none" for nobody)
 */

const OPS_EMAIL_REPLY_TO_DEFAULT = 'support@nfelia.com';
const OPS_EMAIL_COPY_DEFAULT     = 'praxisjbs@gmail.com';

/** Ready-made starts. {first_name} and {business} are filled in for each client. */
const OPS_EMAIL_STARTS = [
    'blank' => ['label' => 'A blank email', 'subject' => '', 'body' => '', 'button' => 'none'],
    'check_in' => [
        'label'   => 'Checking in: any difficulties? A session?',
        'subject' => 'How is Nfelia going for {business}?',
        'body'    => "Hi {first_name},\n\n"
                   . "Thank you for choosing Nfelia for {business}. I'm checking in to see how you are getting on.\n\n"
                   . "Did anything get in your way while you were setting up, or was something unclear? Tell me in a reply and we will sort it out with you.\n\n"
                   . "If you'd like, we can also go through it together in a short session, at a time that suits you: pick one below.",
        'button'  => 'book',
    ],
];

/** The buttons an email can end with. */
function ops_email_buttons(): array
{
    return [
        'none'  => ['label' => 'No button', 'text' => '', 'url' => ''],
        'book'  => ['label' => 'Book a session with us', 'text' => 'Book a session with us', 'url' => APP_URL . '/book'],
        'login' => ['label' => 'Open my Nfelia', 'text' => 'Open my Nfelia', 'url' => APP_URL . '/login'],
    ];
}

function ops_email_reply_to(): string
{
    $v = trim((string)($_ENV['OPS_EMAIL_REPLY_TO'] ?? ''));
    return filter_var($v, FILTER_VALIDATE_EMAIL) ? $v : OPS_EMAIL_REPLY_TO_DEFAULT;
}

/** The blind copies: OPS_EMAIL_COPY (comma-separated), "none" for nobody. */
function ops_email_copies(): array
{
    $v = trim((string)($_ENV['OPS_EMAIL_COPY'] ?? ''));
    if (strtolower($v) === 'none') {
        return [];
    }
    $list = array_map('trim', explode(',', $v !== '' ? $v : OPS_EMAIL_COPY_DEFAULT));
    return array_values(array_filter($list, fn($a) => filter_var($a, FILTER_VALIDATE_EMAIL)));
}

/** Who at the client can be written to: active people with an email, the owner first. */
function ops_email_people(PDO $db, int $business_id): array
{
    $stmt = $db->prepare("SELECT id, full_name, email, role FROM users WHERE business_id = ? AND is_active = 1 AND email <> ''
                          ORDER BY FIELD(role, 'owner', 'admin', 'manager', 'staff'), full_name");
    $stmt->execute([$business_id]);
    return $stmt->fetchAll();
}

/** {first_name} and {business} for one person. */
function ops_email_fill(string $text, array $person, array $business): string
{
    $first = preg_split('/\s+/u', trim((string)($person['full_name'] ?? '')))[0] ?? '';
    return strtr($text, ['{first_name}' => $first !== '' ? $first : 'there', '{business}' => (string)($business['name'] ?: 'your business')]);
}

/**
 * Who the client sees the email is from, and its signature: [first name,
 * full name, title]. Someone who signs as another person (the founder signs
 * as Simi, Customer Support: app/helpers/ops_persona.php) shows that name
 * everywhere in the email.
 */
function ops_email_signer(array $staff): array
{
    require_once BASE_PATH . '/app/helpers/ops_persona.php';
    $full = trim((string)($staff['full_name'] ?? ''));
    if ($as = ops_public_persona($full)) {
        return [$as[0], $as[0], $as[1]];
    }
    $first = preg_split('/\s+/u', $full)[0] ?? '';
    $title = match ($staff['role'] ?? '') { 'support' => 'Customer Support', 'sales' => 'Sales', default => 'Nfelia' };
    return [$first, $full !== '' ? $full : 'The Nfelia team', $title];
}

/** The writer as the inbox shows them: "Simi from Nfelia". */
function ops_email_from_name(array $staff): string
{
    $first = ops_email_signer($staff)[0];
    return ($first !== '' ? $first . ' from ' : '') . 'Nfelia';
}

/**
 * The variables of views/emails/ops_client_message.php, ready for render_email()
 * and the outbox. $draft: subject, body, button (a key of ops_email_buttons()).
 */
function ops_email_vars(array $draft, array $person, array $business, array $staff): array
{
    $button = ops_email_buttons()[$draft['button'] ?? 'none'] ?? ops_email_buttons()['none'];
    [, $writer, $title] = ops_email_signer($staff);
    return [
        'subject'      => ops_email_fill(trim((string)$draft['subject']), $person, $business),
        'body'         => ops_email_fill(trim((string)$draft['body']), $person, $business),
        'button_text'  => $button['text'],
        'button_url'   => $button['url'],
        'writer'       => $writer,
        'writer_role'  => $title,
        'reply_to'     => ops_email_reply_to(),
    ];
}

/** What is wrong with a draft, or null. */
function ops_email_problem(array $draft): ?string
{
    if (mb_strlen(trim((string)$draft['subject'])) < 3) return 'Give the email a subject.';
    if (mb_strlen(trim((string)$draft['subject'])) > 150) return 'Keep the subject under 150 characters.';
    if (mb_strlen(trim((string)$draft['body'])) < 10) return 'Write the message first.';
    if (mb_strlen((string)$draft['body']) > 6000) return 'Keep the message under 6,000 characters.';
    if (!isset(ops_email_buttons()[$draft['button'] ?? 'none'])) return 'Pick a button from the list.';
    return null;
}

/**
 * Send it now. Returns [ok, what to tell the writer]. The outbox keeps it,
 * retries it if the mail server is busy, and says if the address bounced.
 */
function ops_email_send(PDO $db, array $draft, array $person, array $business, array $staff): array
{
    $vars = ops_email_vars($draft, $person, $business, $staff);
    // The same email to the same person within 10 minutes is a second press of Send
    $same = 'ops-email:' . substr(sha1(implode('|', [$business['id'], strtolower($person['email']), $vars['subject'], $vars['body'], $draft['button'] ?? ''])), 0, 32) . ':';
    $twice = $db->prepare("SELECT 1 FROM notify_outbox WHERE dedupe_key LIKE ? AND created_at > NOW() - INTERVAL 10 MINUTE LIMIT 1");
    $twice->execute([$same . '%']);
    if ($twice->fetchColumn()) {
        return [false, 'That email went to ' . $person['email'] . ' a moment ago. Nothing was sent twice.'];
    }
    $key = $same . bin2hex(random_bytes(6));
    $id = notify_queue($db, [
        'business_id' => (int)$business['id'], 'event' => 'ops.client_email', 'channel' => 'email',
        'recipient' => $person['email'], 'recipient_name' => $person['full_name'], 'dedupe_key' => $key, 'status' => 'pending',
        'payload' => [
            'subject' => $vars['subject'], 'template' => 'ops_client_message', 'brand' => null, 'vars' => $vars,
            'from_name' => ops_email_from_name($staff), 'reply_to' => ops_email_reply_to(), 'bcc' => ops_email_copies(),
        ],
    ]);
    if (!$id) {
        return [false, 'The email could not be queued. Try again in a moment.'];
    }
    notify_flush([$id]);
    $stmt = $db->prepare("SELECT status, last_error FROM notify_outbox WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch() ?: ['status' => 'pending', 'last_error' => null];

    if (str_starts_with((string)$row['last_error'], 'bounced')) {
        ops_audit('client.email_bounced', 'Email to ' . $person['email'] . ' at ' . $business['name'] . ' bounced: the address doesn\'t exist',
                  ['business' => (int)$business['id'], 'type' => 'business', 'id' => (int)$business['id'], 'data' => ['outbox' => $id]]);
        return [false, $person['email'] . ' does not take email: the address doesn\'t exist. Try WhatsApp, or another person at ' . $business['name'] . '.'];
    }

    $copies = ops_email_copies();
    $note = "Emailed {$person['full_name']} <{$person['email']}>: {$vars['subject']}\n\n{$vars['body']}"
          . ($vars['button_text'] ? "\n\n[Button: {$vars['button_text']}]" : '')
          . "\n\nReplies go to " . ops_email_reply_to() . ($copies ? '; a copy went to ' . implode(', ', $copies) : '') . '.'
          . ($vars['writer'] !== trim((string)($staff['full_name'] ?? '')) ? ' Signed as ' . $vars['writer'] . ', ' . $vars['writer_role'] . '.' : '');
    $db->prepare("INSERT INTO ops_notes (subject_type, subject_id, staff_id, body) VALUES ('business', ?, ?, ?)")
       ->execute([(int)$business['id'], (int)$staff['id'], mb_substr($note, 0, 4000)]);
    ops_audit('client.email', 'Emailed ' . $business['name'] . ' (' . $person['email'] . '): ' . $vars['subject'],
              ['business' => (int)$business['id'], 'type' => 'business', 'id' => (int)$business['id'], 'data' => ['outbox' => $id, 'status' => $row['status']]]);

    if ($row['status'] === 'sent') {
        return [true, 'Sent to ' . $person['email'] . '. Replies come to ' . ops_email_reply_to() . '.'];
    }
    return [true, 'The mail server is busy: it goes out within a few minutes, by itself. It is noted on the client\'s page.'];
}

/* ── Polish (Gemini) ─────────────────────────────────────────────── */

function ops_email_polish_ready(): bool
{
    return function_exists('product_ai_key') && product_ai_key() !== '';
}

/**
 * A cleaner version of the message: [text, problem]. The writer decides; it
 * is never sent by itself. {first_name} and {business} stay as they are.
 */
function ops_email_polish(string $subject, string $body): array
{
    if (!ops_email_polish_ready()) {
        return [null, 'Polish needs GEMINI_API_KEY on the server.'];
    }
    $system = "You edit short emails that the Nfelia team (a business app for small shops, salons and caterers in Nigeria and Cameroon) "
            . "sends to one client. Make the email clear, warm and natural, in plain English (or plain French if it is written in French). "
            . "Keep the writer's meaning, facts, offers and tone; add no promises, prices, dates or links; keep it about as long as it is. "
            . "Keep {first_name} and {business} exactly as written. Short sentences. No emoji, no markdown, no signature (one is added). "
            . "Answer with JSON: {\"subject\": \"...\", \"body\": \"...\"}, the body's paragraphs separated by one blank line.";
    $request = json_encode([
        'systemInstruction' => ['parts' => [['text' => $system]]],
        'contents'          => [['role' => 'user', 'parts' => [['text' => "Subject: {$subject}\n\n{$body}"]]]],
        'generationConfig'  => [
            'responseMimeType'   => 'application/json',
            'responseJsonSchema' => ['type' => 'object', 'properties' => ['subject' => ['type' => 'string'], 'body' => ['type' => 'string']], 'required' => ['subject', 'body']],
            'maxOutputTokens'    => 2048,
            'thinkingConfig'     => ['thinkingLevel' => 'minimal'],
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $url = product_ai_url() . '/v1beta/models/' . rawurlencode(product_ai_model()) . ':generateContent';
    [$status, $answer, $error] = product_ai_post($url, $request);
    if ($status !== 200) {
        $said = json_decode($answer, true)['error']['message'] ?? $error;
        error_log('[Nfelia] Ops email polish: Gemini answered ' . ($status ?: 'nothing') . ($said ? ': ' . mb_substr((string)$said, 0, 200) : ''));
        return [null, 'Gemini did not answer just now. Your words are as you left them; try again in a minute, or send them as they are.'];
    }
    $text = '';
    foreach (json_decode($answer, true)['candidates'][0]['content']['parts'] ?? [] as $part) {
        if (empty($part['thought']) && isset($part['text'])) $text .= $part['text'];
    }
    $out = json_decode($text, true);
    if (!is_array($out) || trim((string)($out['body'] ?? '')) === '') {
        return [null, 'Gemini wrote nothing usable this time. Your words are as you left them.'];
    }
    return [['subject' => mb_substr(trim((string)$out['subject']), 0, 150) ?: $subject, 'body' => mb_substr(trim((string)$out['body']), 0, 6000)], null];
}
