<?php
/**
 * Text size and background, chosen per device. The choice applies at once
 * and is kept in the nf_ui cookie (assets/js/shell.js writes it,
 * theme_appearance() in app/helpers/theme.php reads it).
 * Used by the More page's Appearance sheet and Settings → Profile.
 */
$ui_now  = theme_appearance();
$ui_name = 'nf-ui-' . bin2hex(random_bytes(3)); // radio groups stay separate if this appears twice
?>
<div class="nf-appearance">
    <fieldset class="nf-field">
        <legend>Text size</legend>
        <div class="nf-seg">
            <?php foreach (['small' => ['Small', 14], 'standard' => ['Standard', 17], 'large' => ['Large', 21]] as $value => [$label, $px]): ?>
                <label>
                    <input type="radio" name="<?= $ui_name ?>-text" value="<?= $value ?>" data-ui="text"<?= $ui_now['text'] === $value ? ' checked' : '' ?>>
                    <span class="opt"><b style="font-size:<?= $px ?>px" aria-hidden="true">Aa</b><?= $label ?></span>
                </label>
            <?php endforeach; ?>
        </div>
    </fieldset>
    <fieldset class="nf-field">
        <legend>Background</legend>
        <div class="nf-swatches">
            <?php foreach (['white' => 'White', 'cream' => 'Cream'] as $value => $label): ?>
                <label>
                    <input type="radio" name="<?= $ui_name ?>-bg" value="<?= $value ?>" data-ui="bg"<?= $ui_now['bg'] === $value ? ' checked' : '' ?>>
                    <span class="opt"><i class="sw is-<?= $value ?>"></i><?= $label ?></span>
                </label>
            <?php endforeach; ?>
        </div>
    </fieldset>
    <p class="nf-help">Saved on this device. Your team’s phones keep their own.</p>
</div>
