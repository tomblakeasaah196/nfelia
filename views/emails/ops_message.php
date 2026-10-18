<?php
/*
 * views/emails/ops_message.php — the frame of every Nfelia Ops email (an
 * invite, a password reset, a security notice). Variables: $headline,
 * $message, $button, $url, $note (small print under the button).
 */
$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
?>
<h2 style="margin:0 0 8px;font-size:22px;line-height:1.25;font-weight:600;color:#1F0A14;">
    <?= $h($headline) ?>
</h2>
<p style="margin:0 0 24px;font-size:16px;line-height:1.5;color:#5B4650;">
    <?= nl2br($h($message)) ?>
</p>
<table cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px;">
    <tr>
        <td style="background-color:#1B0A11;">
            <a href="<?= $h($url) ?>"
               style="display:inline-block;padding:12px 24px;font-size:16px;line-height:1.25;font-weight:600;color:#FFFFFF;text-decoration:none;">
                <?= $h($button) ?>
            </a>
        </td>
    </tr>
</table>
<?php if (!empty($note)): ?>
<p style="margin:0;font-size:13px;line-height:1.5;color:#8A7A80;">
    <?= nl2br($h($note)) ?>
</p>
<?php endif; ?>
