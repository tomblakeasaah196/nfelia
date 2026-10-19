<?php
/**
 * Someone who checked a Records Book code at nfelia.com/verify (a bank, a
 * landlord, a buyer) asking for the full document. Nothing is shared until
 * the business says yes (Tom, October 2026):
 *
 *   1. They give their name, email, who they are and why, and confirm the
 *      email from a link (status unconfirmed → new). That keeps junk out
 *      and proves the document goes to an inbox they hold.
 *   2. Nfelia Ops admins are told. One click asks the client (new → asked);
 *      junk can be turned down there without bothering anyone.
 *   3. The owner hears in the app, on their phone and by email, and answers
 *      on Your records → the request (signed in; a mail scanner opening the
 *      link decides nothing).
 *   4. Approved: the person gets a link to download it, good for
 *      RECORD_REQUEST_LINK_DAYS. A whole book is the file as it was made; one
 *      record is a fresh extract of it, which says what changed since.
 *      Declined, or no answer in RECORD_REQUEST_ANSWER_DAYS: they are told,
 *      politely.
 * Every step is kept on the request: who asked, who was asked, who decided,
 * when it was downloaded.
 */

const RECORD_REQUEST_LINK_DAYS   = 7;
const RECORD_REQUEST_ANSWER_DAYS = 14;
const RECORD_REQUEST_ROLES = [
    'bank'       => 'A bank or lender',
    'landlord'   => 'A landlord',
    'accountant' => 'An accountant or auditor',
    'buyer'      => 'A customer',
    'supplier'   => 'A supplier or partner',
    'government' => 'A government office',
    'other'      => 'Someone else',
];

/** One request by id, with its book and business. */
function record_request(PDO $db, int $id): ?array
{
    $stmt = $db->prepare("SELECT rq.*, b.name AS business_name, rb.code AS book_code, rb.created_at AS book_made
                          FROM record_requests rq JOIN businesses b ON b.id = rq.business_id JOIN records_books rb ON rb.id = rq.book_id
                          WHERE rq.id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

/** The request a link's token belongs to. */
function record_request_by_token(PDO $db, string $token): ?array
{
    if (!preg_match('/^[A-Za-z0-9_-]{20,100}$/', $token)) return null;
    $stmt = $db->prepare("SELECT id FROM record_requests WHERE token_hash = ?");
    $stmt->execute([hash('sha256', $token)]);
    $id = $stmt->fetchColumn();
    return $id ? record_request($db, (int)$id) : null;
}

/**
 * Step 1: a request from the verify page. $found is records_verify_lookup()
 * for the code they checked. Returns [ok, message for them].
 */
function record_request_create(PDO $db, array $found, array $in, string $ip, string $agent): array
{
    $name  = trim((string)($in['name'] ?? ''));
    $email = strtolower(trim((string)($in['email'] ?? '')));
    $org   = trim((string)($in['organisation'] ?? ''));
    $phone = trim((string)($in['phone'] ?? ''));
    $role  = isset(RECORD_REQUEST_ROLES[$in['role'] ?? '']) ? $in['role'] : 'other';
    $why   = trim((string)($in['reason'] ?? ''));
    if (mb_strlen($name) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($why) < 10) {
        return [false, 'Give your name, an email we can reach you on, and why you need it (a sentence is enough).'];
    }
    if (empty($in['agree'])) {
        return [false, 'Tick the box to say the business may see your name, email and reason.'];
    }
    if (!throttle_allows('record_request_ip', $ip, 10, 60 * 24) || !throttle_allows('record_request_email', $email, 5, 60 * 24)) {
        return [false, 'Too many requests today. Try again tomorrow, or ask the business directly.'];
    }
    $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    $db->prepare("INSERT INTO record_requests (book_id, business_id, code, record_type, record_id, label, name, organisation, email, phone, role, reason,
                                               token_hash, ip, user_agent)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
       ->execute([(int)$found['book']['id'], (int)$found['book']['business_id'], $found['code'], $found['sheet_key'], $found['record_id'],
                  mb_substr($found['label'], 0, 160), mb_substr($name, 0, 120), $org !== '' ? mb_substr($org, 0, 160) : null, $email,
                  $phone !== '' ? mb_substr($phone, 0, 40) : null, $role, mb_substr($why, 0, 1000), hash('sha256', $token), mb_substr($ip, 0, 45), mb_substr($agent, 0, 255)]);
    $id = (int)$db->lastInsertId();
    $mail = notify_email(null, $email, $name, 'Confirm your request for ' . $found['label'], 'record_request', [
        'headline' => 'Confirm your request',
        'message'  => 'You asked for ' . $found['label'] . ' from ' . $found['business']['name'] . '. Confirm this is your email, and we will ask them.',
        'button'   => 'Confirm my request',
        'url'      => APP_URL . '/verify/confirm/' . $token,
        'note'     => 'If you didn\'t ask for this, ignore this email: nothing happens until the link is opened.',
    ], 'record-request-confirm:' . $id, 'records.request_confirm');
    if ($mail) notify_flush_later($mail);
    return [true, 'Check your email: we sent a link to ' . $email . '. Open it to confirm, and we will ask ' . $found['business']['name'] . '.'];
}

/** Step 1, done: their email is confirmed and the Ops admins are told. Returns the request, or null. */
function record_request_confirm(PDO $db, string $token): ?array
{
    $r = record_request_by_token($db, $token);
    if (!$r) return null;
    if ($r['status'] === 'unconfirmed') {
        $db->prepare("UPDATE record_requests SET status = 'new', confirmed_at = NOW() WHERE id = ? AND status = 'unconfirmed'")->execute([$r['id']]);
        ops_alert($db, 'records.request', 'Document request: ' . $r['business_name'],
                  ($r['organisation'] ?: $r['name']) . ' asked for ' . $r['label'] . ': ' . mb_strimwidth($r['reason'], 0, 160, '…'),
                  'requests/' . (int)$r['id'], 'record-request:' . $r['id'], ['permission' => 'requests']);
        $r = record_request($db, (int)$r['id']);
    }
    return $r;
}

/** Step 2: an Ops admin asks the client. False when it was already dealt with. */
function record_request_ask(PDO $db, array $r, array $staff): bool
{
    $stmt = $db->prepare("UPDATE record_requests SET status = 'asked', asked_at = NOW(), asked_by = ? WHERE id = ? AND status = 'new'");
    $stmt->execute([(int)$staff['id'], (int)$r['id']]);
    if ($stmt->rowCount() !== 1) return false;
    notify('records.request', (int)$r['business_id'], [
        'request_id' => (int)$r['id'], 'who' => $r['organisation'] ? $r['name'] . ' (' . $r['organisation'] . ')' : $r['name'],
        'role' => RECORD_REQUEST_ROLES[$r['role']] ?? '', 'what' => $r['label'], 'reason' => $r['reason'], 'email' => $r['email'],
    ], ['dedupe' => 'request:' . $r['id']]);
    ops_audit('records.request_asked', 'Asked ' . $r['business_name'] . ' about ' . ($r['organisation'] ?: $r['name']) . '\'s request for ' . $r['label'],
              ['business' => (int)$r['business_id'], 'type' => 'record_request', 'id' => (int)$r['id']]);
    return true;
}

/**
 * Step 3, or Ops turning down junk at step 2: approve or decline.
 * $by: ['user' => id] for the owner, ['staff' => id] for Ops. Returns false
 * when it was already decided (or isn't waiting on this person).
 */
function record_request_decide(PDO $db, array $r, bool $approve, ?string $note, array $by): bool
{
    $note = $note !== null && trim($note) !== '' ? mb_substr(trim($note), 0, 500) : null;
    $waiting = isset($by['staff']) ? "status = 'new'" : "status = 'asked'";
    $stmt = $db->prepare("UPDATE record_requests SET status = ?, decided_at = NOW(), decided_by_user = ?, decided_by_staff = ?, decision_note = ?,
                                 sent_at = IF(? = 'approved', NOW(), NULL), file_expires_at = IF(? = 'approved', NOW() + INTERVAL " . RECORD_REQUEST_LINK_DAYS . " DAY, NULL)
                          WHERE id = ? AND {$waiting}");
    $status = $approve ? 'approved' : 'declined';
    $stmt->execute([$status, $by['user'] ?? null, $by['staff'] ?? null, $note, $status, $status, (int)$r['id']]);
    if ($stmt->rowCount() !== 1) return false;

    // The person who asked hears either way; on yes, with a fresh link of their own
    $token = null;
    if ($approve) {
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $db->prepare("UPDATE record_requests SET token_hash = ? WHERE id = ?")->execute([hash('sha256', $token), (int)$r['id']]);
    }
    $mail = notify_email(null, $r['email'], $r['name'],
        $approve ? $r['business_name'] . ' shared ' . $r['label'] . ' with you' : 'About your request to ' . $r['business_name'],
        'record_request', $approve ? [
            'headline' => $r['business_name'] . ' said yes',
            'message'  => 'You can download ' . $r['label'] . ' for the next ' . RECORD_REQUEST_LINK_DAYS . ' days.' . ($note ? ' Their note: “' . $note . '”' : ''),
            'button'   => 'Download it',
            'url'      => APP_URL . '/verify/file/' . $token,
            'note'     => 'Made by Nfelia from ' . $r['business_name'] . '\'s records. You can check any code in it at nfelia.com/verify.',
        ] : [
            'headline' => 'Your request was not approved',
            'message'  => $r['business_name'] . ' decided not to share ' . $r['label'] . ' through Nfelia.' . ($note ? ' Their note: “' . $note . '”' : '')
                        . ' You can still ask them for it directly.',
            'note'     => 'Nfelia only shares a business\'s records when the business says yes.',
        ], 'record-request-' . $status . ':' . $r['id'], 'records.request_' . $status);
    if ($mail) notify_flush_later($mail);

    $who = isset($by['staff']) ? 'Nfelia Ops' : 'the owner';
    ops_alert($db, 'records.request_' . $status, ($approve ? 'Approved: ' : 'Declined: ') . $r['label'],
              $r['business_name'] . ' (' . $who . ') ' . ($approve ? 'shared it with ' : 'turned down ') . ($r['organisation'] ?: $r['name']) . '.',
              'requests/' . (int)$r['id'], 'record-request-' . $status . ':' . $r['id'], ['permission' => 'requests', 'push' => false]);
    return true;
}

/**
 * Step 4: what an approved request downloads, sent to the browser, or the
 * reason it can't be ([title, body] for a notice page).
 */
function record_request_download(PDO $db, array $r): array
{
    if ($r['status'] !== 'approved') {
        return ['This link doesn\'t open anything yet', 'The business hasn\'t approved this request. We email you as soon as they answer.'];
    }
    if (strtotime((string)$r['file_expires_at']) < time()) {
        return ['This link has expired', 'Download links last ' . RECORD_REQUEST_LINK_DAYS . ' days. Ask again at nfelia.com/verify, or ask the business directly.'];
    }
    $book = $db->query("SELECT * FROM records_books WHERE id = " . (int)$r['book_id'])->fetch();
    if (!$book) {
        return ['This document is no longer kept', 'Ask the business for a fresh copy.'];
    }
    if ($r['record_type'] === null) {
        $file = records_file($book);
        if (!$file) {
            return ['This book is no longer kept', 'Nfelia keeps a Records Book for ' . RECORDS_KEEP_DAYS . ' days. Ask the business for a fresh one.'];
        }
        $db->prepare("UPDATE record_requests SET downloads = downloads + 1, last_download_at = NOW() WHERE id = ?")->execute([$r['id']]);
        records_stream($file, (string)$book['file_name'], 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }
    $business = records_business($db, (int)$r['business_id']);
    $tmp = tempnam(sys_get_temp_dir(), 'nfext') . '.xlsx';
    $made = records_extract_write($db, $book, $business, (string)$r['record_type'], (int)$r['record_id'], $r, $tmp);
    if (!$made) {
        @unlink($tmp);
        return ['This record no longer exists', $r['business_name'] . ' has since removed it from Nfelia. Ask them directly.'];
    }
    $db->prepare("UPDATE record_requests SET downloads = downloads + 1, last_download_at = NOW() WHERE id = ?")->execute([$r['id']]);
    register_shutdown_function(fn() => @unlink($tmp));
    records_stream($tmp, slugify($r['business_name']) . '-' . slugify($r['label']) . '.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
}

/** Every five minutes: links never confirmed go after a day; questions the client never answered close after two weeks. */
function record_requests_cron(PDO $db): array
{
    $db->exec("DELETE FROM record_requests WHERE status = 'unconfirmed' AND created_at < NOW() - INTERVAL 1 DAY");
    $stale = $db->query("SELECT id FROM record_requests WHERE status IN ('new', 'asked') AND COALESCE(asked_at, confirmed_at) < NOW() - INTERVAL " . RECORD_REQUEST_ANSWER_DAYS . " DAY LIMIT 50")
                ->fetchAll(PDO::FETCH_COLUMN);
    foreach ($stale as $id) {
        $r = record_request($db, (int)$id);
        $stmt = $db->prepare("UPDATE record_requests SET status = 'expired', decided_at = NOW() WHERE id = ? AND status IN ('new', 'asked')");
        $stmt->execute([(int)$id]);
        if ($stmt->rowCount() === 1 && $r) {
            $mail = notify_email(null, $r['email'], $r['name'], 'About your request to ' . $r['business_name'], 'record_request', [
                'headline' => 'No answer yet',
                'message'  => $r['business_name'] . ' hasn\'t answered your request for ' . $r['label'] . ' in ' . RECORD_REQUEST_ANSWER_DAYS . ' days, so we have closed it. You can ask them directly, or ask again at nfelia.com/verify.',
                'note'     => 'Nfelia only shares a business\'s records when the business says yes.',
            ], 'record-request-expired:' . $r['id'], 'records.request_expired');
        }
    }
    return ['record_requests_closed' => count($stale)];
}
