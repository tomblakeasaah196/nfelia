<?php
/**
 * Delivery and payment: the owner's delivery note, how people can pay (from
 * the bank accounts and WhatsApp number the shop really has), and the bank
 * details if the owner shows them.
 */
$banks = $catalogue['banks'];
if ($banks && $wa !== '') {
    $pay_line = 'Pay by ' . sf_pay_way($banks) . ' when you order, or order on WhatsApp.';
} elseif ($banks) {
    $pay_line = 'Pay by ' . sf_pay_way($banks) . ' when you order.';
} elseif ($wa !== '') {
    $pay_line = 'Order here or on WhatsApp, and we will tell you how to pay.';
} else {
    $pay_line = 'Order here and we will contact you about payment.';
}
$show_banks = $section['show_bank'] && $banks;
?>
<section class="sf-section sf-delivery" id="sf-delivery" aria-labelledby="sf-delivery-title">
    <div class="sf-wrap">
        <div class="sf-delivery__card">
            <h2 class="sf-h2" id="sf-delivery-title"><?= e($section['title'] !== '' ? $section['title'] : $labels['delivery']) ?></h2>
            <?php if ($section['text'] !== ''): ?>
                <div class="sf-iconrow"><?= sf_icon('truck', 24) ?><div><?= sf_paragraphs($section['text']) ?></div></div>
            <?php else: ?>
                <?= sf_gap($ctx, 'Say where you deliver, what it costs and when people can pick up.') ?>
            <?php endif; ?>
            <div class="sf-iconrow"><?= sf_icon('card', 24) ?><div><p><?= e($pay_line) ?></p></div></div>
            <?php if ($show_banks): ?>
                <div class="sf-banks">
                    <?php foreach (array_slice($banks, 0, 3) as $bank): ?>
                        <div class="sf-bank">
                            <p class="sf-bank__name"><?= e($bank['bank_name']) ?></p>
                            <p class="sf-bank__number">
                                <span><?= e($bank['account_number']) ?></span>
                                <button type="button" class="sf-link sf-link--button" data-copy="<?= e($bank['account_number']) ?>">Copy</button>
                            </p>
                            <p class="sf-bank__holder"><?= e($bank['account_name']) ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
