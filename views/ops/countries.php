<?php
/** Countries (settings-like list with inline editing): $rows, $methods. */
?>
<div class="ops-head">
    <div>
        <h1>Countries</h1>
        <p>Where Nfelia sells: the monthly price, the free trial, and how businesses there pay. New sign-ups and renewals use what is here.</p>
    </div>
    <div class="ops-head-actions"><button type="button" class="ops-btn ops-btn-primary" data-ops-dialog="add-country"><?= ops_icon('plus') ?> Add a country</button></div>
</div>
<div class="ops-stack">
<?php foreach ($rows as $c): ?>
    <details class="ops-card ops-country">
        <summary>
            <span class="grow"><b><?= e(trim(($c['flag_emoji'] ?? '') . ' ' . $c['name'])) ?></b>
                <span class="muted"> · <?= e($c['currency_symbol'] . number_format((float)$c['monthly_price'])) ?> a month · <?= (int)$c['trial_days'] ?>-day trial · <?= e($methods[$c['payment_method']] ?? $c['payment_method']) ?> · <?= (int)$c['businesses'] ?> business<?= (int)$c['businesses'] === 1 ? '' : 'es' ?></span></span>
            <span class="ops-chip <?= (int)$c['is_active'] ? 'ops-chip-ok' : '' ?>"><?= (int)$c['is_active'] ? 'Offered' : 'Not offered' ?></span>
        </summary>
        <div class="ops-card-body">
            <form method="post" action="<?= e(ops_url('countries/' . (int)$c['id'])) ?>">
                <?= ops_csrf_field() ?>
                <div class="ops-row">
                    <div class="ops-field"><label for="mp-<?= (int)$c['id'] ?>">Monthly price (<?= e($c['currency_code']) ?>)</label><input class="ops-input" id="mp-<?= (int)$c['id'] ?>" name="monthly_price" inputmode="decimal" value="<?= e((string)(float)$c['monthly_price']) ?>"></div>
                    <div class="ops-field"><label for="es-<?= (int)$c['id'] ?>">Extra seat</label><input class="ops-input" id="es-<?= (int)$c['id'] ?>" name="extra_seat_price" inputmode="decimal" value="<?= e((string)(float)$c['extra_seat_price']) ?>"></div>
                    <div class="ops-field"><label for="td-<?= (int)$c['id'] ?>">Trial days</label><input class="ops-input" id="td-<?= (int)$c['id'] ?>" name="trial_days" inputmode="numeric" value="<?= (int)$c['trial_days'] ?>"></div>
                    <div class="ops-field"><label for="cs-<?= (int)$c['id'] ?>">Symbol</label><input class="ops-input" id="cs-<?= (int)$c['id'] ?>" name="currency_symbol" value="<?= e($c['currency_symbol']) ?>"></div>
                </div>
                <div class="ops-row">
                    <div class="ops-field"><label for="pm-<?= (int)$c['id'] ?>">How they pay</label>
                        <select class="ops-select" id="pm-<?= (int)$c['id'] ?>" name="payment_method"><?php foreach ($methods as $k => $label): ?><option value="<?= e($k) ?>"<?= $c['payment_method'] === $k ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
                    <div class="ops-field"><label for="mo-<?= (int)$c['id'] ?>">Mobile money number <span class="muted">(optional)</span></label><input class="ops-input" id="mo-<?= (int)$c['id'] ?>" name="momo_number" value="<?= e($c['momo_number'] ?? '') ?>"></div>
                    <div class="ops-field"><label for="fe-<?= (int)$c['id'] ?>">Flag</label><input class="ops-input" id="fe-<?= (int)$c['id'] ?>" name="flag_emoji" value="<?= e($c['flag_emoji'] ?? '') ?>"></div>
                </div>
                <div class="ops-field"><label for="pi-<?= (int)$c['id'] ?>">Payment instructions <span class="muted">(shown to businesses paying by mobile money)</span></label><textarea class="ops-textarea" id="pi-<?= (int)$c['id'] ?>" name="payment_instructions"><?= e($c['payment_instructions'] ?? '') ?></textarea></div>
                <div class="ops-actions">
                    <button class="ops-btn ops-btn-primary" type="submit">Save <?= e($c['name']) ?></button>
                </div>
            </form>
            <?php if ($c['iso_code'] !== 'NG'): ?>
            <form method="post" action="<?= e(ops_url('countries/' . (int)$c['id'] . '/toggle')) ?>" style="margin-top:12px" data-ops-confirm="<?= (int)$c['is_active'] ? 'Stop offering ' . e($c['name']) . ' at sign-up? Businesses already there carry on.' : 'Offer ' . e($c['name']) . ' at sign-up?' ?>">
                <?= ops_csrf_field() ?><button class="ops-btn <?= (int)$c['is_active'] ? 'ops-btn-danger' : 'ops-btn-secondary' ?> ops-btn-sm" type="submit"><?= (int)$c['is_active'] ? 'Stop offering it' : 'Offer it at sign-up' ?></button>
            </form>
            <?php endif; ?>
        </div>
    </details>
<?php endforeach; ?>
</div>

<dialog class="ops-dialog" id="add-country" aria-labelledby="add-country-title">
    <form method="post" action="<?= e(ops_url('countries')) ?>">
        <?= ops_csrf_field() ?>
        <div class="ops-dialog-head"><h2 id="add-country-title">Add a country</h2><button type="button" data-ops-close aria-label="Close"><?= ops_icon('close') ?></button></div>
        <div class="ops-dialog-body">
            <div class="ops-row">
                <div class="ops-field"><label for="nc-name">Name</label><input class="ops-input" id="nc-name" name="name" required></div>
                <div class="ops-field"><label for="nc-iso">Country code</label><input class="ops-input" id="nc-iso" name="iso_code" maxlength="2" required placeholder="GH"></div>
            </div>
            <div class="ops-row">
                <div class="ops-field"><label for="nc-cn">Currency</label><input class="ops-input" id="nc-cn" name="currency_name" placeholder="Ghana Cedi"></div>
                <div class="ops-field"><label for="nc-cc">Currency code</label><input class="ops-input" id="nc-cc" name="currency_code" maxlength="3" required placeholder="GHS"></div>
                <div class="ops-field"><label for="nc-cs">Symbol</label><input class="ops-input" id="nc-cs" name="currency_symbol" required placeholder="GH₵"></div>
            </div>
            <div class="ops-row">
                <div class="ops-field"><label for="nc-mp">Monthly price</label><input class="ops-input" id="nc-mp" name="monthly_price" inputmode="decimal" required></div>
                <div class="ops-field"><label for="nc-td">Trial days</label><input class="ops-input" id="nc-td" name="trial_days" inputmode="numeric" value="14"></div>
            </div>
            <div class="ops-field"><label for="nc-pm">How they pay</label><select class="ops-select" id="nc-pm" name="payment_method"><?php foreach ($methods as $k => $label): ?><option value="<?= e($k) ?>"><?= e($label) ?></option><?php endforeach; ?></select></div>
            <label class="ops-check"><input type="checkbox" name="is_active" value="1" checked><span>Offer it at sign-up now</span></label>
        </div>
        <div class="ops-dialog-foot"><button type="button" class="ops-btn ops-btn-secondary" data-ops-close>Cancel</button><button type="submit" class="ops-btn ops-btn-primary">Add it</button></div>
    </form>
</dialog>
