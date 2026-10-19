<?php
/**
 * How the money came: Cash, Transfer, POS, MoMo, as four big choices.
 * Expects $pm_id (unique on the page) and optionally $pm_checked and $pm_model
 * (an Alpine variable the choice is bound to).
 */
$pm_checked = $pm_checked ?? 'cash';
$pm_model   = $pm_model ?? null;
?>
<fieldset class="nf-field nf-methods">
    <legend>Paid by</legend>
    <div class="nf-seg is-4">
        <?php foreach (['cash' => ['Cash', 'money'], 'bank_transfer' => ['Transfer', 'send'], 'pos' => ['POS', 'card'], 'mobile_money' => ['MoMo', 'phone']] as $pm_value => [$pm_label, $pm_icon]): ?>
            <label><input type="radio" name="method" value="<?= $pm_value ?>"<?= $pm_value === $pm_checked ? ' checked' : '' ?><?= $pm_model ? ' x-model="' . e($pm_model) . '"' : '' ?>><span class="opt"><?= nav_icon($pm_icon) ?><?= $pm_label ?></span></label>
        <?php endforeach; ?>
    </div>
</fieldset>
<?php unset($pm_checked, $pm_model); ?>
