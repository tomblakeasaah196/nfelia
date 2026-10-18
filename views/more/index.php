<?php
/**
 * More. Rendered inside views/layouts/app.php, which provides $nav_groups,
 * $user, $user_role, $selected_country and $all_countries.
 */
// Already a tab: Home, Tasks, and Sales with its three lists.
$in_tabs = ['dashboard', 'tasks', 'invoices', 'receipts', 'quotations'];
$tiles = array_values(array_filter($nav_groups['Your business'] ?? [], fn($i) => !in_array($i['url'], $in_tabs, true)));
?>
<h1 class="nf-large-title">More</h1>

<h2 class="nf-section-h">Your business</h2>
<div class="nf-tiles">
    <?php foreach ($tiles as $item): ?>
        <a class="nf-tile" href="<?= APP_URL ?>/<?= $item['url'] ?>"><span class="nf-tile-ic"><?= nav_icon($item['icon']) ?></span><?= e($item['label']) ?></a>
    <?php endforeach; ?>
</div>

<?php if (!empty($nav_groups['Storefront'])): ?>
<h2 class="nf-section-h">Storefront</h2>
<div class="nf-tiles">
    <?php foreach ($nav_groups['Storefront'] as $item): ?>
        <a class="nf-tile" href="<?= APP_URL ?>/<?= $item['url'] ?>"><span class="nf-tile-ic"><?= nav_icon($item['icon']) ?></span><?= e($item['label']) ?></a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<h2 class="nf-section-h">Account</h2>
<div class="nf-list">
    <a class="nf-row" href="<?= APP_URL ?>/settings"><span class="nf-tile-ic"><?= nav_icon('settings') ?></span>Settings<span class="end"><?= nav_icon('chevron') ?></span></a>
    <?php if ($user_role === 'owner'): ?>
        <a class="nf-row" href="<?= APP_URL ?>/settings?tab=billing"><span class="nf-tile-ic"><?= nav_icon('card') ?></span>Billing<span class="end"><?= nav_icon('chevron') ?></span></a>
    <?php endif; ?>
    <button type="button" class="nf-row" data-open="nf-notify"><span class="nf-tile-ic"><?= nav_icon('bell') ?></span>Notifications<span class="end"><?= nav_icon('chevron') ?></span></button>
    <button type="button" class="nf-row" data-open="nf-appearance"><span class="nf-tile-ic"><?= nav_icon('textsize') ?></span>Text size and background<span class="end"><?= nav_icon('chevron') ?></span></button>
    <?php if ($user_role === 'owner'): ?>
    <button type="button" class="nf-row" data-open="nf-country"><span class="nf-tile-ic"><?= nav_icon('globe') ?></span>Country and currency<span class="end"><?= e($selected_country['flag_emoji'] ?? '') ?> <?= e($selected_country['currency_symbol'] ?? '') ?><?= nav_icon('chevron') ?></span></button>
    <?php endif; ?>
    <button type="button" class="nf-row" data-install-row hidden><span class="nf-tile-ic"><?= nav_icon('phone') ?></span>Install the app<span class="end"><?= nav_icon('chevron') ?></span></button>
    <a class="nf-row" href="<?= APP_URL ?>/user-guide"><span class="nf-tile-ic"><?= nav_icon('book') ?></span>User guide<span class="end"><?= nav_icon('chevron') ?></span></a>
    <a class="nf-row" href="<?= APP_URL ?>/contact-support"><span class="nf-tile-ic"><?= nav_icon('chat') ?></span>Contact support<span class="end"><?= nav_icon('chevron') ?></span></a>
    <form action="<?= APP_URL ?>/logout" method="POST">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
        <button type="submit" class="nf-row"><span class="nf-tile-ic"><?= nav_icon('signout') ?></span>Sign out<span class="end"><?= e($user['full_name']) ?></span></button>
    </form>
</div>

<?php foreach (['Data', 'Founder panel'] as $group): if (empty($nav_groups[$group])) continue; ?>
<h2 class="nf-section-h"><?= e($group) ?></h2>
<div class="nf-list">
    <?php foreach ($nav_groups[$group] as $item): $danger = $item['url'] === 'purge'; ?>
        <a class="nf-row<?= $danger ? ' is-danger' : '' ?>"<?= $danger ? ' data-no-prefetch' : '' ?> href="<?= APP_URL ?>/<?= $item['url'] ?>"><span class="nf-tile-ic"><?= nav_icon($item['icon']) ?></span><?= e($item['label']) ?><span class="end"><?= nav_icon('chevron') ?></span></a>
    <?php endforeach; ?>
</div>
<?php endforeach; ?>

<?php require BASE_PATH . '/views/partials/notify_sheet.php'; ?>
<script src="<?= APP_URL ?>/assets/js/push.js?v=<?= filemtime(BASE_PATH . '/assets/js/push.js') ?>" defer></script>
<script src="<?= APP_URL ?>/assets/js/notify.js?v=<?= filemtime(BASE_PATH . '/assets/js/notify.js') ?>" defer></script>

<dialog class="nf-sheet" id="nf-appearance" aria-labelledby="nf-appearance-h">
    <div class="nf-grab"></div>
    <div class="nf-sheet-h">
        <h2 id="nf-appearance-h">Text size and background</h2>
        <button type="button" class="nf-icon-btn" data-close aria-label="Close"><?= nav_icon('close') ?></button>
    </div>
    <div class="nf-sheet-body"><?php require BASE_PATH . '/views/partials/appearance.php'; ?></div>
</dialog>

<?php if ($user_role === 'owner'): ?>
<dialog class="nf-sheet" id="nf-country" aria-labelledby="nf-country-h">
    <div class="nf-grab"></div>
    <div class="nf-sheet-h">
        <h2 id="nf-country-h">Country and currency</h2>
        <button type="button" class="nf-icon-btn" data-close aria-label="Close"><?= nav_icon('close') ?></button>
    </div>
    <div class="nf-sheet-body">
        <div class="nf-list">
            <?php foreach ($all_countries as $co): $is_sel = (int)($selected_country['id'] ?? 0) === (int)$co['id']; ?>
                <button type="button" class="nf-row" onclick="setCountry(<?= (int)$co['id'] ?>)"<?= $is_sel ? ' aria-current="true"' : '' ?>>
                    <span style="font-size:1.25rem;width:34px;text-align:center"><?= e($co['flag_emoji'] ?? '🌐') ?></span><?= e($co['name']) ?>
                    <span class="end"><?= e($co['currency_symbol']) ?> <?= e($co['currency_code']) ?><?php if ($is_sel): ?><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-label="Selected" style="color:var(--accent-text)"><path d="M5 13l4 4L19 7"/></svg><?php endif; ?></span>
                </button>
            <?php endforeach; ?>
        </div>
    </div>
</dialog>
<?php endif; ?>
