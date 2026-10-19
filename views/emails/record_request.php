<?php
/*
 * views/emails/record_request.php — to someone who asked for a document at
 * nfelia.com/verify (app/helpers/record_requests.php): confirm the email,
 * the business said yes (with the download link), or no.
 * Variables: $headline, $message, $button and $url (optional), $note.
 */
$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
?>
<h2 style="margin:0 0 8px;font-size:22px;line-height:1.25;font-weight:600;color:#1F0A14;">
    <?= $h($headline) ?>
</h2>
<p style="margin:0 0 24px;font-size:16px;line-height:1.5;color:#5B4650;">
    <?= nl2br($h($message)) ?>
</p>
<?php if (!empty($button) && !empty($url)): ?>
<table cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px;">
    <tr>
        <td style="background-color:#8B004A;">
            <a href="<?= $h($url) ?>"
               style="display:inline-block;padding:12px 24px;font-size:16px;line-height:1.25;font-weight:600;color:#FFFFFF;text-decoration:none;">
                <?= $h($button) ?>
            </a>
        </td>
    </tr>
</table>
<?php endif; ?>
<?php if (!empty($note)): ?>
<p style="margin:0;font-size:13px;line-height:1.5;color:#8A7A80;">
    <?= nl2br($h($note)) ?>
</p>
<?php endif; ?>
