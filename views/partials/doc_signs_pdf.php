<?php
/**
 * A document's signatures and verify code in its PDF (dompdf: tables and
 * inline styles, pictures as data: URIs). The same as views/partials/
 * doc_signs.php: signatures only when it was signed, the QR code always.
 * $sign is doc_sign_state(), $business the business.
 */
if (empty($sign)) return;
$sig_label = strtolower($sign['label']);
$sig_when  = fn(string $at) => date('j M Y, g:ia', strtotime($at));
$sig_small = 'font-size:8px;color:#666;';
$sig_head  = 'font-size:7px;font-weight:bold;letter-spacing:1px;text-transform:uppercase;color:#777;';
?>
<div style="clear:both;page-break-inside:avoid;">
<table style="width:100%;margin-top:24px;border-top:1px solid #ddd;border-collapse:collapse;">
    <?php if ($sign['business'] || $sign['customer']): ?>
    <tr>
        <td style="width:50%;padding:14px 10px 10px 0;vertical-align:bottom;">
            <?php if ($sig_b = $sign['business']): ?>
                <?php if ($sig_b['image']): ?>
                <img src="<?= e($sig_b['image']) ?>" style="height:40px;"><br>
                <div style="<?= $sig_head ?>">Signed for <?= e($business['name']) ?></div>
                <?php else: ?>
                <table style="border:2px solid #333;border-collapse:collapse;"><tr><td style="padding:6px 10px;">
                <div style="<?= $sig_head ?>color:#333;">Digitally signed for <?= e($business['name']) ?></div>
                <?php endif; ?>
                <div style="font-size:10px;font-weight:bold;color:#222;"><?= e($sig_b['signer_name']) ?><?= !empty($sig_b['signer_role']) ? ', ' . e($sig_b['signer_role']) : '' ?></div>
                <div style="<?= $sig_small ?>"><?= e($sig_when($sig_b['signed_at'])) ?><?= $sig_b['seal'] !== '' ? ' · Seal ' . e(doc_seal_short($sig_b['seal'])) : '' ?></div>
                <?php if ((int)$sig_b['version'] > 1): ?><div style="<?= $sig_small ?>">Signed again (version <?= (int)$sig_b['version'] ?>)</div><?php endif; ?>
                <?php if (!$sig_b['image']): ?></td></tr></table><?php endif; ?>
                <?php if ($sign['changed']): ?><div style="font-size:8px;font-weight:bold;color:#b45309;margin-top:3px;">Changed since it was signed</div><?php endif; ?>
            <?php endif; ?>
        </td>
        <td style="width:50%;padding:14px 0 10px 10px;vertical-align:bottom;">
            <?php if ($sig_c = $sign['customer']): ?>
                <?php if ($sig_c['image']): ?><img src="<?= e($sig_c['image']) ?>" style="height:40px;"><br><?php endif; ?>
                <div style="<?= $sig_head ?>"><?= e($sign['customer_words'] ?? 'Signed by') ?></div>
                <div style="font-size:10px;font-weight:bold;color:#222;"><?= e($sig_c['signer_name']) ?></div>
                <div style="<?= $sig_small ?>"><?= e($sig_when($sig_c['signed_at'])) ?><?= $sig_c['seal'] !== '' ? ' · Seal ' . e(doc_seal_short($sig_c['seal'])) : '' ?></div>
            <?php endif; ?>
        </td>
    </tr>
    <?php endif; ?>
    <tr>
        <td colspan="2" style="padding-top:10px;">
            <table style="border-collapse:collapse;"><tr>
                <?php if ($sign['qr']): ?><td style="width:62px;vertical-align:middle;"><img src="<?= e($sign['qr']) ?>" style="width:56px;height:56px;"></td><?php endif; ?>
                <td style="vertical-align:middle;font-size:8px;color:#666;line-height:1.5;">
                    Check this <?= e($sig_label) ?> is genuine<?= $sign['business'] ? ', and who signed it' : '' ?>: scan the code or open nfelia.com/verify<br>
                    Code <span style="font-family:'DejaVu Sans Mono',monospace;font-weight:bold;color:#222;"><?= e($sign['display']) ?></span>
                </td>
            </tr></table>
        </td>
    </tr>
</table>
</div>
