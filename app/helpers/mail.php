<?php
/**
 * Nfelia — SMTP Mail Engine
 * NFELIA AUDIT FIX — M13 — SSL verify_peer controlled via MAIL_VERIFY_PEER — 2026-06-03
 * NFELIA UPDATE — HTML email template support added — 2026-06-03
 */

require_once __DIR__ . '/../../lib/PHPMailer/src/Exception.php';
require_once __DIR__ . '/../../lib/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../../lib/PHPMailer/src/SMTP.php';
require_once __DIR__ . '/theme.php';   // the business's colour in an email (render_email()); the cron doesn't load it

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

/**
 * PHPMailer's SMTP, keeping the mail server's answer when it refuses the
 * address. PHPMailer reports a refused address only as text, and its QUIT
 * clears the code, but "no such address" and "try later" need telling apart.
 */
class NfeliaSMTP extends SMTP
{
    public int $refused = 0;        // the code it answered, 0 when it took the address
    public string $status = '';     // its enhanced status (5.1.1), when it gave one
    public string $answer = '';     // and what it said

    public function recipient($address, $dsn = '')
    {
        $ok = parent::recipient($address, $dsn);
        if (!$ok) {
            $error = $this->getError();
            $this->refused = (int)($error['smtp_code'] ?? 0);
            $this->status  = (string)($error['smtp_code_ex'] ?? '');
            $this->answer  = trim(preg_replace('/\s+/', ' ', $this->getLastReply()) ?? '');
        }
        return $ok;
    }

    /**
     * It said the address doesn't exist: 5.1.x (no such mailbox or domain),
     * or a plain 55x that says so. Not "try later" (4xx), and not a refusal
     * of Nfelia itself (5.7.x: relaying, policy), which no other address fixes.
     */
    public function no_such_address(): bool
    {
        if ($this->refused < 500 || $this->refused > 599) {
            return false;
        }
        if ($this->status !== '') {
            return str_starts_with($this->status, '5.1.');
        }
        // cPanel's own mail server gives no 5.1.x and says "The mail server
        // could not deliver mail to … The account or domain may not exist".
        return (bool)preg_match('/no such (user|mailbox|address|recipient)|(user|recipient|mailbox) (unknown|not found)|unknown (user|recipient)|does ?n[o’\']?t exist|account or domain may not exist/i', $this->answer);
    }
}

/**
 * After send_mail() returned false: what the mail server said when it said
 * the address doesn't exist, or null when it failed for another reason.
 */
function mail_bounced(): ?string
{
    return $GLOBALS['nf_mail_bounced'] ?? null;
}

/**
 * Render a branded HTML email template.
 *
 * @param string $template  Template name without extension (e.g. 'welcome', 'verification')
 * @param array  $vars      Variables injected into the template
 * @param array  $business  Business context (name, logo, primary_color, tagline, email)
 * @return string           Rendered HTML string
 */
function render_email(string $template, array $vars = [], array $business = []): string {
    // Defaults from system config if no business context given
    $biz_name    = $business['name']          ?? APP_NAME;
    $biz_logo    = '';
    $biz_tagline = $business['tagline']       ?? 'Track everything. Carry nothing.';
    $biz_email   = $business['email']         ?? MAIL_FROM_ADDRESS;

    // The business's colour in the parts an email needs, worked out as the
    // app does (app/helpers/theme.php), so a colour nobody chose is Nfelia's
    // wine here too: $biz_color for the top bar and buttons, $biz_on for the
    // words on a button, $biz_text for words on white, $biz_soft for the pale
    // box behind a note.
    $theme     = theme_tokens($business['primary_color'] ?? null, $business['secondary_color'] ?? null);
    $biz_color = $theme['accent'];
    $biz_on    = $theme['on_accent'];
    $biz_text  = $theme['accent_text'];
    $biz_soft  = $theme['accent_soft'];

    // Link the logo from the site rather than embedding it: Gmail and Outlook
    // strip base64 data: images, so an embedded logo never appeared.
    if (!empty($business['logo_path']) && file_exists(BASE_PATH . '/' . $business['logo_path'])) {
        $biz_logo = APP_URL . '/' . ltrim($business['logo_path'], '/');
    }

    // Nfelia's own emails (recovery, sign-in, billing) rather than a client
    // workspace's: they carry the Nfelia mark and credit JBS Praxis.
    $is_platform = $biz_logo === '' && in_array(strtolower($biz_name), ['nfelia', strtolower(APP_NAME)], true);
    // Small links and labels: Nfelia's gold in Nfelia's own emails, the
    // business's colour in a business's.
    $biz_link = $is_platform ? '#B69F5E' : $biz_text;

    // Extract vars for use in template
    extract($vars);

    ob_start();
    $template_path = BASE_PATH . '/views/emails/' . $template . '.php';
    if (!file_exists($template_path)) {
        ob_end_clean();
        return "Error: email template '{$template}' not found.";
    }
    require $template_path;
    $inner_content = ob_get_clean();

    // Wrap in base layout
    ob_start();
    require BASE_PATH . '/views/emails/base.php';
    return ob_get_clean();
}

/**
 * Send an email. Supports both plain text and HTML.
 *
 * @param string $to_email
 * @param string $to_name
 * @param string $subject
 * @param string $body       Plain text or HTML body
 * @param bool   $is_html    Set true to send HTML
 * @param array  $headers    Extra headers, name => value
 * @param string $from_name  The name the inbox shows, still from MAIL_FROM_ADDRESS
 * @param string $reply_to   Where a reply goes, when not to MAIL_FROM_ADDRESS
 * @param array  $bcc        Addresses that get a copy the recipient doesn't see (Ops emails to a client)
 * @return bool
 */
function send_mail(string $to_email, string $to_name, string $subject, string $body, bool $is_html = false, array $headers = [], string $from_name = '', string $reply_to = '', array $bcc = []): bool {
    $GLOBALS['nf_mail_bounced'] = null;
    $mail = new PHPMailer(true);
    $smtp = new NfeliaSMTP();
    $mail->setSMTPInstance($smtp);

    try {
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = (MAIL_ENCRYPTION === 'ssl') ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = MAIL_PORT;
        // A slow or dead mail server gives up after 15 seconds, not PHPMailer's
        // 300, so it can't hold up everything else that is sending (P3)
        $mail->Timeout    = 15;
        $mail->getSMTPInstance()->Timelimit = 15;

        // M13 audit: SSL peer verification — auto-disabled for localhost,
        // controllable via MAIL_VERIFY_PEER env var for non-standard setups
        if (!MAIL_VERIFY_PEER) {
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true,
                ],
            ];
        }

        // PHPMailer defaults to ISO-8859-1, but every subject and template
        // here is UTF-8: without this, "—" arrives as "â€”" and "₦" as junk.
        $mail->CharSet = PHPMailer::CHARSET_UTF8;

        $from_name = trim(preg_replace('/[\r\n\t]+/', ' ', $from_name));
        $mail->setFrom(MAIL_FROM_ADDRESS, $from_name !== '' ? mb_substr($from_name, 0, 70) : MAIL_FROM_NAME);
        if ($reply_to !== '' && filter_var($reply_to, FILTER_VALIDATE_EMAIL)) {
            $mail->addReplyTo($reply_to, $from_name);
        }
        $mail->addAddress($to_email, $to_name);
        foreach ($bcc as $copy) {
            if (is_string($copy) && filter_var($copy, FILTER_VALIDATE_EMAIL) && strcasecmp($copy, $to_email) !== 0) {
                $mail->addBCC($copy);
            }
        }

        // e.g. List-Unsubscribe on campaign emails (app/ops/sales.php)
        foreach ($headers as $name => $value) {
            $mail->addCustomHeader($name, $value);
        }

        $mail->isHTML($is_html);
        $mail->Subject = $subject;
        $mail->Body    = $body;

        if ($is_html) {
            $mail->AltBody = strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $body));
        }

        $mail->send();
        return true;

    } catch (Exception $e) {
        // The address doesn't exist. That is the address's problem, not
        // Nfelia's: noted once, as a notice, and the outbox tells someone
        // who can fix it (app/helpers/bounces.php).
        if ($smtp->no_such_address()) {
            $GLOBALS['nf_mail_bounced'] = $smtp->answer;
            require_once __DIR__ . '/errors.php';
            errors_report('job', 'notice', 'Email bounced: ' . $smtp->answer);
            return false;
        }
        error_log('[Nfelia] SMTP failure: ' . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Shorthand: render template and send in one call.
 *
 * @param string $to_email
 * @param string $to_name
 * @param string $subject
 * @param string $template    Template name
 * @param array  $vars        Template variables
 * @param array  $business    Business context for branding
 * @return bool
 */
function send_branded_mail(string $to_email, string $to_name, string $subject, string $template, array $vars = [], array $business = []): bool {
    $html = render_email($template, $vars, $business);
    return send_mail($to_email, $to_name, $subject, $html, true);
}