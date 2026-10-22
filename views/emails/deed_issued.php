<?php /* views/emails/deed_issued.php — Variables: $client_name, $biz_name, $doc_number, $deed_url, $total, $currency_symbol, $item_names */ ?>
<h2 style="margin:0 0 8px;font-size:22px;color:#1B0A11;font-weight:700;letter-spacing:-0.02em;">
    Your Deed of Acquisition.
</h2>
<p style="margin:0 0 4px;font-size:11px;color:<?= htmlspecialchars($biz_link, ENT_QUOTES) ?>;letter-spacing:0.15em;text-transform:uppercase;font-weight:700;">
    <?= htmlspecialchars($doc_number ?? '', ENT_QUOTES) ?>
</p>
<div style="height:1px;background-color:#E8E4DA;margin:20px 0;"></div>
<p style="margin:0 0 16px;color:#3a2f2b;">
    Dear <?= htmlspecialchars($client_name ?? 'Valued Client', ENT_QUOTES) ?>,
</p>
<p style="margin:0 0 16px;color:#3a2f2b;">
    This is to confirm that the following acquisition has been formally recorded by
    <strong><?= htmlspecialchars($biz_name ?? '', ENT_QUOTES) ?></strong>:
</p>

<?php if (!empty($item_names)): ?>
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:20px;">
    <?php foreach ((array)$item_names as $item): ?>
    <tr>
        <td style="padding:8px 0;border-bottom:1px solid #F2EFE7;font-size:13px;color:#1B0A11;">
            — <?= htmlspecialchars($item, ENT_QUOTES) ?>
        </td>
    </tr>
    <?php endforeach; ?>
    <tr>
        <td style="padding:12px 0 0;font-size:14px;font-weight:700;color:#1B0A11;">
            Total: <?= htmlspecialchars(($currency_symbol ?? '₦') . number_format((float)($total ?? 0), 0), ENT_QUOTES) ?>
        </td>
    </tr>
</table>
<?php endif; ?>

<p style="margin:0 0 24px;color:#3a2f2b;font-style:italic;font-size:13px;border-left:3px solid <?= htmlspecialchars($biz_color, ENT_QUOTES) ?>;padding-left:16px;">
    Your deed is a permanent record of this acquisition. Keep this link for your records.
</p>

<table cellpadding="0" cellspacing="0" border="0" style="margin-bottom:20px;">
    <tr>
        <td style="background-color:<?= htmlspecialchars($biz_color, ENT_QUOTES) ?>;">
            <a href="<?= htmlspecialchars($deed_url ?? '#', ENT_QUOTES) ?>"
               style="display:inline-block;padding:14px 32px;font-size:11px;font-weight:700;color:<?= htmlspecialchars($biz_on, ENT_QUOTES) ?>;text-decoration:none;letter-spacing:0.15em;text-transform:uppercase;">
                View & Download Your Deed →
            </a>
        </td>
    </tr>
</table>
<p style="margin:0;font-size:11px;color:#9a8f8a;word-break:break-all;">
    <a href="<?= htmlspecialchars($deed_url ?? '#', ENT_QUOTES) ?>" style="color:<?= htmlspecialchars($biz_link, ENT_QUOTES) ?>;">
        <?= htmlspecialchars($deed_url ?? '', ENT_QUOTES) ?>
    </a>
</p>
