<?php
/**
 * Settings › Payment accounts (SettingsController::save_account): the bank
 * accounts and Mobile Money numbers customers pay into. The shop shows them
 * (Delivery and payment, the bag, bookings) and invoices and quotes can carry
 * one; the main one comes first and is picked on new papers. Everyone sees
 * them here, only the owner changes them.
 *
 * Expects $business, $is_owner.
 */
$pay_db = get_db();
$stmt_pay = $pay_db->prepare("
    SELECT id, kind, bank_name, account_number, account_name, is_default
    FROM bank_accounts
    WHERE business_id = ? AND is_active = 1 AND deleted_at IS NULL
    ORDER BY is_default DESC, sort_order ASC, id ASC
");
$stmt_pay->execute([(int)$business['id']]);
$pay_accounts = $stmt_pay->fetchAll();

// A new account starts as the kind the business's country mostly pays with
$stmt_pay = $pay_db->prepare("SELECT c.payment_method FROM countries c JOIN businesses b ON b.country_id = c.id WHERE b.id = ?");
$stmt_pay->execute([(int)$business['id']]);
$pay_new_kind = $stmt_pay->fetchColumn() === 'mobile_money' ? 'mobile_money' : 'bank';

// What was typed when it couldn't be saved, and why: its sheet opens again
$pay_draft = $_SESSION['pay_draft'] ?? null;
unset($_SESSION['pay_draft']);

$pay_kinds   = ['bank' => ['Bank account', 'card'], 'mobile_money' => ['Mobile Money', 'phone']];
$pay_main_id = $pay_accounts && (int)$pay_accounts[0]['is_default'] ? (int)$pay_accounts[0]['id'] : 0;

/** The add or change sheet for one account ($a is null for a new one). */
$pay_sheet = function (?array $a) use ($pay_kinds, $pay_new_kind, $pay_draft, $pay_accounts, $pay_main_id): void {
    $key   = $a ? (int)$a['id'] : 'new';
    $draft = $pay_draft && (string)$pay_draft['for'] === (string)$key ? $pay_draft : null;
    $v     = $draft ?? $a ?? ['kind' => $pay_new_kind, 'bank_name' => '', 'account_number' => '', 'account_name' => '', 'is_default' => false];
    $sheet = 'nf-pay-' . $key;
    ?>
    <dialog class="nf-sheet" id="<?= $sheet ?>" aria-labelledby="<?= $sheet ?>-h">
        <div class="nf-grab"></div>
        <div class="nf-sheet-h">
            <h2 id="<?= $sheet ?>-h"><?= $a ? e($a['bank_name']) : 'Add an account' ?></h2>
            <button type="button" class="nf-icon-btn" data-close aria-label="Close"><?= nav_icon('close') ?></button>
        </div>
        <form method="POST" action="<?= APP_URL ?>/settings/save-account<?= $a ? '/' . (int)$a['id'] : '' ?>" class="nf-form nf-pay-form"
              x-data="{ kind: '<?= $v['kind'] === 'mobile_money' ? 'mobile_money' : 'bank' ?>' }">
            <?= csrf_field() ?>
            <?php if ($draft): ?><p class="nf-err" role="alert"><?= e($draft['problem']) ?></p><?php endif; ?>
            <fieldset class="nf-field">
                <legend>Customers pay into</legend>
                <div class="nf-seg is-2">
                    <?php foreach ($pay_kinds as $kind => [$kind_word, $kind_icon]): ?>
                        <label><input type="radio" name="kind" value="<?= $kind ?>" x-model="kind"<?= $v['kind'] === $kind ? ' checked' : '' ?>><span class="opt"><?= nav_icon($kind_icon) ?><?= $kind_word ?></span></label>
                    <?php endforeach; ?>
                </div>
            </fieldset>
            <label><span x-text="kind === 'mobile_money' ? 'Which Mobile Money' : 'Bank'"><?= $v['kind'] === 'mobile_money' ? 'Which Mobile Money' : 'Bank' ?></span>
                <input type="text" name="bank_name" value="<?= e($v['bank_name']) ?>" required maxlength="100" autocomplete="off"
                       :placeholder="kind === 'mobile_money' ? 'e.g. MTN MoMo or Orange Money' : 'e.g. Ecobank'"></label>
            <label><span x-text="kind === 'mobile_money' ? 'Mobile Money number' : 'Account number'"><?= $v['kind'] === 'mobile_money' ? 'Mobile Money number' : 'Account number' ?></span>
                <input type="text" name="account_number" value="<?= e($v['account_number']) ?>" required maxlength="40" autocomplete="off" spellcheck="false"
                       :inputmode="kind === 'mobile_money' ? 'tel' : 'text'"></label>
            <label>Name on the account <small>As customers see it when they pay, so they know it is you</small>
                <input type="text" name="account_name" value="<?= e($v['account_name']) ?>" required maxlength="100" autocomplete="off"></label>
            <?php if ($pay_accounts && !($a && (int)$a['id'] === $pay_main_id)): ?>
                <label class="nf-switch"><input type="checkbox" role="switch" name="is_default" value="1"<?= !empty($draft['is_default']) ? ' checked' : '' ?>>
                    <span><b>Make it the main account</b><small>Shown first on your shop, and picked on new invoices and quotes</small></span></label>
            <?php endif; ?>
            <button class="nf-btn nf-btn-primary nf-sheet-go"><?= $a ? 'Save changes' : 'Add account' ?></button>
        </form>
        <?php if ($a): ?>
            <div class="nf-menu-sheet nf-pay-gone">
                <form method="POST" action="<?= APP_URL ?>/settings/remove-account/<?= (int)$a['id'] ?>"
                      onsubmit="return confirm(<?= e(json_encode('Remove ' . $a['bank_name'] . ' ' . $a['account_number'] . '? Customers stop seeing it. Invoices and quotes already sent still show it.')) ?>);">
                    <?= csrf_field() ?>
                    <button class="nf-row is-danger"><span class="nf-tile-ic"><?= nav_icon('trash') ?></span>Remove this account</button>
                </form>
            </div>
        <?php endif; ?>
    </dialog>
    <?php
};
?>
<section class="nf-card nf-pay" id="payments" aria-labelledby="nf-pay-h">
    <div class="nf-card-h">
        <div>
            <h2 id="nf-pay-h">Payment accounts <?= nf_info('Payment accounts', 'Your shop shows these under Delivery and payment, at checkout and when people book, and you can put one on each invoice and quote. The main account comes first and is picked on new invoices and quotes. Removing an account takes it off your shop; invoices and quotes already sent keep showing it.') ?></h2>
            <p>Where customers send their money: your bank accounts and Mobile Money numbers.</p>
        </div>
    </div>

    <?php if ($pay_accounts): ?>
        <ul class="nf-pay-list">
            <?php foreach ($pay_accounts as $a): ?>
                <li>
                    <?php if ($is_owner): ?><button type="button" class="nf-pay-row" data-open="nf-pay-<?= (int)$a['id'] ?>" aria-haspopup="dialog"><?php else: ?><div class="nf-pay-row"><?php endif; ?>
                        <span class="nf-tile-ic"><?= nav_icon($pay_kinds[$a['kind']][1] ?? 'card') ?></span>
                        <span class="nf-pay-txt">
                            <b><?= e($a['bank_name']) ?><?php if ((int)$a['id'] === $pay_main_id && count($pay_accounts) > 1): ?> <span class="nf-tag is-accent">Main</span><?php endif; ?></b>
                            <span class="nf-pay-num"><?= e($a['account_number']) ?></span>
                            <small><?= e($a['account_name']) ?> · <?= $pay_kinds[$a['kind']][0] ?? 'Bank account' ?></small>
                        </span>
                        <?php if ($is_owner): ?><span class="nf-pay-edit"><?= nav_icon('edit') ?><span class="sr-only">Change or remove</span></span><?php endif; ?>
                    <?= $is_owner ? '</button>' : '</div>' ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p class="nf-pay-empty">No accounts yet. Add the bank account or Mobile Money number customers pay into, and your shop shows it at checkout. Until then it tells people you will contact them about payment.</p>
    <?php endif; ?>

    <?php if ($is_owner): ?>
        <?php if (count($pay_accounts) < SettingsController::MAX_PAY_ACCOUNTS): ?>
            <button type="button" class="nf-btn nf-btn-ghost nf-pay-add" data-open="nf-pay-new" aria-haspopup="dialog"><?= nav_icon('plus') ?>Add an account</button>
        <?php endif; ?>
    <?php else: ?>
        <p class="nf-help">Only the business owner can change these.</p>
    <?php endif; ?>
</section>

<?php if ($is_owner):
    $pay_sheet(null);
    foreach ($pay_accounts as $a) { $pay_sheet($a); }
endif; ?>
