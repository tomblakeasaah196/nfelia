<?php
/**
 * Nfelia — SMTP Mail Engine
 * NFELIA AUDIT FIX — M13 — SSL verify_peer controlled via MAIL_VERIFY_PEER — 2026-06-03
 * NFELIA UPDATE — HTML email template support added — 2026-06-03
 */

require_once __DIR__ . '/../../lib/PHPMailer/src/Exception.php';
require_once __DIR__ . '/../../lib/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../../lib/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

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
    $biz_color   = $business['primary_color'] ?? '#8B004A';
    $biz_logo    = '';
    $biz_tagline = $business['tagline']       ?? 'Track everything. Carry nothing.';
    $biz_email   = $business['email']         ?? MAIL_FROM_ADDRESS;

    // Link the logo from the site rather than embedding it: Gmail and Outlook
    // strip base64 data: images, so an embedded logo never appeared.
    if (!empty($business['logo_path']) && file_exists(BASE_PATH . '/' . $business['logo_path'])) {
        $biz_logo = APP_URL . '/' . ltrim($business['logo_path'], '/');
    }

    // Nfelia's own emails (recovery, sign-in, billing) rather than a client
    // workspace's: they carry the Nfelia mark and credit JBS Praxis.
    $is_platform = $biz_logo === '' && in_array(strtolower($biz_name), ['nfelia', strtolower(APP_NAME)], true);

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
 * @return bool
 */
function send_mail(string $to_email, string $to_name, string $subject, string $body, bool $is_html = false, array $headers = []): bool {
    $mail = new PHPMailer(true);

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

        $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        $mail->addAddress($to_email, $to_name);

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