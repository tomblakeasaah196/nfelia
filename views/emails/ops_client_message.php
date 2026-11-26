<?php
/*
 * views/emails/ops_client_message.php — an email someone in Nfelia Ops wrote
 * to one client (Clients › a client › Email; app/ops/client_email.php).
 * Variables: $subject, $body (plain text: a blank line starts a paragraph),
 * $button_text, $button_url, $writer, $writer_role, $reply_to.
 * Nfelia's own frame (views/emails/base.php) goes round it.
 */
$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
$paragraphs = preg_split("/\r?\n\s*\r?\n/", trim((string)$body)) ?: [];
?>
<?php foreach ($paragraphs as $p): ?>
<p style="margin:0 0 18px;font-size:16px;line-height:1.65;color:#1F0A14;"><?= nl2br($h(trim($p))) ?></p>
<?php endforeach; ?>
<?php if (!empty($button_text) && !empty($button_url)): ?>
<table cellpadding="0" cellspacing="0" border="0" style="margin:10px 0 28px;">
    <tr><td style="background-color:#8B004A;border-radius:2px;">
        <a href="<?= $h($button_url) ?>" style="display:inline-block;padding:13px 26px;font-size:16px;line-height:1.25;font-weight:600;color:#FFFFFF;text-decoration:none;"><?= $h($button_text) ?></a>
    </td></tr>
</table>
<?php endif; ?>
<table cellpadding="0" cellspacing="0" border="0" style="margin:8px 0 0;">
    <tr>
        <td style="width:3px;background-color:#B69F5E;font-size:0;line-height:0;">&nbsp;</td>
        <td style="padding:2px 0 2px 14px;">
            <p style="margin:0;font-size:16px;line-height:1.4;font-weight:600;color:#1F0A14;"><?= $h($writer) ?></p>
            <p style="margin:2px 0 0;font-size:14px;line-height:1.4;color:#7D6C73;"><?= $h($writer_role) ?></p>
        </td>
    </tr>
</table>
<p style="margin:28px 0 0;font-size:13px;line-height:1.6;color:#8A7A80;">
    Just reply to this email to answer: it reaches the Nfelia team<?= !empty($reply_to) ? ' at ' . $h($reply_to) : '' ?>.
</p>
