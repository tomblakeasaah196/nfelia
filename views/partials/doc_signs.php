<?php
/**
 * A document's signatures and its verify code, at the foot of the document
 * on its page (in the app and the customer's). $sign is doc_sign_state()
 * (app/helpers/signing.php); $business the business. Nothing about
 * signatures shows unless the document was signed: no empty "Authorized
 * By" lines (Tom: signatures only when someone wants them). The QR code and
 * the code are always there.
 *
 * The business signs with a stamp (a box saying who signed, when, and the
 * seal) or with the signer's drawn signature above the same words.
 */
if (empty($sign)) return;
$sig_label = strtolower($sign['label']);
$sig_when  = fn(string $at) => date('j M Y, g:ia', strtotime($at));
?>
<div class="mt-8 pt-6 border-t border-gray-200" data-doc-signs>
    <?php if (!empty($sign['can_sign']) && $sign['on'] && !$sign['business']): // made by someone who can't sign for the business ?>
    <p class="mb-4 text-[10px] font-semibold text-amber-700" data-sign-waiting>Set to be signed, and not signed for <?= e($business['name']) ?> yet.</p>
    <?php endif; ?>
    <?php if ($sign['business'] || $sign['customer']): ?>
    <div class="grid grid-cols-2 gap-8 mb-6">
        <div>
            <?php if ($sig_b = $sign['business']): ?>
            <div class="<?= $sig_b['image'] ? '' : 'inline-block border-2 border-gray-700 rounded-lg px-3 py-2' ?>" data-sign-business>
                <?php if ($sig_b['image']): ?><img src="<?= e($sig_b['image']) ?>" alt="Signature of <?= e($sig_b['signer_name']) ?>" class="h-12 w-auto mb-1"><?php endif; ?>
                <div class="text-xs font-semibold text-gray-600"><?= $sig_b['image'] ? 'Signed' : 'Digitally signed' ?> for <?= e($business['name']) ?></div>
                <div class="text-xs font-semibold text-gray-800"><?= e($sig_b['signer_name']) ?><?= !empty($sig_b['signer_role']) ? ', ' . e($sig_b['signer_role']) : '' ?></div>
                <div class="text-[10px] text-gray-500"><?= e($sig_when($sig_b['signed_at'])) ?><?= $sig_b['seal'] !== '' ? ' · Seal ' . e(doc_seal_short($sig_b['seal'])) : '' ?></div>
                <?php if ((int)$sig_b['version'] > 1): ?><div class="text-[10px] text-gray-500">Signed again (version <?= (int)$sig_b['version'] ?>)</div><?php endif; ?>
            </div>
            <?php if ($sign['changed']): ?><div class="mt-1 text-[10px] font-semibold text-amber-700" data-sign-changed>Changed since it was signed</div><?php endif; ?>
            <?php endif; ?>
        </div>
        <div>
            <?php if ($sig_c = $sign['customer']): ?>
            <div data-sign-customer>
                <?php if ($sig_c['image']): ?><img src="<?= e($sig_c['image']) ?>" alt="Signature of <?= e($sig_c['signer_name']) ?>" class="h-12 w-auto mb-1"><?php endif; ?>
                <div class="text-xs font-semibold text-gray-600"><?= e($sign['customer_words'] ?? 'Signed by') ?></div>
                <div class="text-xs font-semibold text-gray-800"><?= e($sig_c['signer_name']) ?></div>
                <div class="text-[10px] text-gray-500"><?= e($sig_when($sig_c['signed_at'])) ?><?= $sig_c['seal'] !== '' ? ' · Seal ' . e(doc_seal_short($sig_c['seal'])) : '' ?></div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    <div class="flex items-center gap-3" data-verify-code="<?= e($sign['code']) ?>">
        <?php if ($sign['qr']): ?><img src="<?= e($sign['qr']) ?>" alt="QR code: check this <?= e($sig_label) ?> at nfelia.com/verify" class="w-16 h-16 shrink-0"><?php endif; ?>
        <p class="text-[10px] leading-snug text-gray-500">
            Check this <?= e($sig_label) ?> is genuine<?= $sign['business'] ? ', and who signed it' : '' ?>:
            scan the code or open <a href="<?= e($sign['url']) ?>" class="font-semibold text-gray-700 underline">nfelia.com/verify</a><br>
            Code <span class="font-mono font-bold text-gray-800"><?= e($sign['display']) ?></span>
        </p>
    </div>
</div>
