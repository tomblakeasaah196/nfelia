<?php
/*
 * views/emails/ops_campaign.php — a campaign from the Nfelia team (app/ops/sales.php).
 * Variables: $subject, $body (plain text: blank lines make paragraphs), $button_text, $button_url, $unsubscribe.
 */
$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
$paragraphs = preg_split("/\r?\n\s*\r?\n/", trim((string)$body)) ?: [];
?>
<?php foreach ($paragraphs as $p): ?>
<p style="margin:0 0 16px;font-size:16px;line-height:1.6;color:#1F0A14;"><?= nl2br($h(trim($p))) ?></p>
<?php endforeach; ?>
<?php if (!empty($button_text) && !empty($button_url)): ?>
<table cellpadding="0" cellspacing="0" border="0" style="margin:8px 0 24px;">
    <tr><td style="background-color:#8B004A;">
        <a href="<?= $h($button_url) ?>" style="display:inline-block;padding:12px 24px;font-size:16px;line-height:1.25;font-weight:600;color:#FFFFFF;text-decoration:none;"><?= $h($button_text) ?></a>
    </td></tr>
</table>
<?php endif; ?>
<p style="margin:24px 0 0;font-size:12px;line-height:1.5;color:#8A7A80;">
    You get emails like this from the Nfelia team. <a href="<?= $h($unsubscribe) ?>" style="color:#8A7A80;">Unsubscribe</a> and we won't send you any more;
    emails about your own account still come.
</p>
