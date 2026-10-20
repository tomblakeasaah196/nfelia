<?php
/**
 * The password checklist: one line per rule (app/helpers/auth.php
 * password_rules()), ticked as the person types. assets/js/password.js
 * reads data-nf-pw (the password field) and data-nf-pw-confirm (the "type
 * it again" field, when there is one) and also stops the form until every
 * rule is met. The server checks the same rules again on submit.
 *
 *   $pw_input    a CSS selector for the password field, e.g. '#password'
 *   $pw_confirm  a selector for the confirm field, or '' (default)
 *   $pw_compact  true for the small version inside settings cards
 */
$pw_confirm = $pw_confirm ?? '';
$pw_compact = !empty($pw_compact);
static $nf_pw_styled = false;
if (!$nf_pw_styled):
    $nf_pw_styled = true; ?>
<style>
.nf-pw{list-style:none;margin:8px 0 0;padding:0;display:grid;gap:4px;font-size:.8125rem;line-height:1.35;color:#5B4650}
.nf-pw.is-compact{font-size:.75rem;gap:2px}
.nf-pw li{display:flex;align-items:center;gap:8px;transition:color .15s}
.nf-pw li::before{content:"";flex:0 0 auto;width:14px;height:14px;border-radius:50%;border:1.5px solid #C9BFB2;box-sizing:border-box;background:transparent center/9px no-repeat;transition:background-color .15s,border-color .15s}
.nf-pw li.is-met{color:#1F6B3A}
.nf-pw li.is-met::before{border-color:#1F6B3A;background-color:#1F6B3A;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12'%3E%3Cpath d='M2.5 6.5l2.3 2.3L9.5 4' fill='none' stroke='%23fff' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E")}
@media (prefers-reduced-motion:reduce){.nf-pw li,.nf-pw li::before{transition:none}}
</style>
<script src="<?= APP_URL ?>/assets/js/password.js?v=<?= @filemtime(BASE_PATH . '/assets/js/password.js') ?: 1 ?>" defer></script>
<?php endif; ?>
<ul class="nf-pw<?= $pw_compact ? ' is-compact' : '' ?>" data-nf-pw="<?= e($pw_input) ?>"<?= $pw_confirm !== '' ? ' data-nf-pw-confirm="' . e($pw_confirm) . '"' : '' ?> aria-label="What the password needs">
<?php foreach (password_rules() as $key => $rule): ?>
    <li data-rule="<?= e($key) ?>" role="checkbox" aria-checked="false"><?= e($rule['label']) ?></li>
<?php endforeach; ?>
<?php if ($pw_confirm !== ''): ?>
    <li data-rule="match" role="checkbox" aria-checked="false">Typed the same way twice</li>
<?php endif; ?>
</ul>
