<?php
/**
 * Sign a document for the business, sign it again after a change, or take
 * the signature off: owners and admins only (doc_sign_state()['can_sign']),
 * posting to signing/sign. As rows of a document's More sheet when
 * $sign_row is set, else as small buttons for the older pages.
 *
 * $sign is doc_sign_state() with doc_sign_viewer(); $sign_back the page to
 * come back to, e.g. "invoices/view/12".
 */
if (empty($sign) || empty($sign['can_sign'])) return;
$sig_label = strtolower($sign['label']);
$sig_do = [];
if (!$sign['on'] || !$sign['business']) {
    $sig_do[] = [1, 'Sign this ' . $sig_label, false];
} else {
    if ($sign['changed'] || !empty($sign['business']['legacy'])) $sig_do[] = [1, 'Sign it again', false];
    // A customer's signature keeps the document signed (doc_switch())
    if (!$sign['customer'] || !in_array($sign['type'], ['quote', 'delivery'], true)) $sig_do[] = [0, 'Take the signature off', true];
}
?>
<?php foreach ($sig_do as [$sig_on, $sig_words, $sig_off]): ?>
<form method="POST" action="<?= APP_URL ?>/signing/sign"<?= empty($sign_row) ? ' class="inline"' : '' ?>>
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="type" value="<?= e($sign['type']) ?>">
    <input type="hidden" name="id" value="<?= (int)$sign['id'] ?>">
    <input type="hidden" name="on" value="<?= $sig_on ?>">
    <input type="hidden" name="back" value="<?= e($sign_back) ?>">
    <?php if (!empty($sign_row)): ?>
    <button type="submit" class="nf-row<?= $sig_off ? ' is-danger' : '' ?>" data-sign="<?= $sig_on ? 'on' : 'off' ?>"><span class="nf-tile-ic"><?= nav_icon('sign') ?></span><?= e($sig_words) ?></button>
    <?php else: ?>
    <button type="submit" data-sign="<?= $sig_on ? 'on' : 'off' ?>"
            class="flex items-center gap-1.5 bg-white border border-gray-200 hover:border-gray-400 text-gray-700 px-3 py-2 rounded-lg transition-all">
        <?= str_replace('width="24" height="24"', 'width="14" height="14"', nav_icon('sign')) ?>
        <span class="text-xs font-semibold"><?= e($sig_words) ?></span>
    </button>
    <?php endif; ?>
</form>
<?php endforeach; ?>
