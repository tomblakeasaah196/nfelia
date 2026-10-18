<?php
/** Setting up Nfelia Ops for the first time (AdminController::dashboard). */
?>
<div class="max-w-xl mx-auto py-10">
    <div class="rounded-xl border border-brand-burgundy/20 bg-white p-6">
        <h1 class="text-xl font-semibold text-brand-plum">Nfelia Ops is ready</h1>
        <p class="text-sm text-brand-plum/70 mt-2">The Nfelia team's own place for clients, payments, support, errors and sales is now at
            <b><?= e(ops_host()) ?></b>, with its own sign-in. Set up your Ops account as its first admin: you'll get an email to choose a
            password, then invite the rest of the team from there.</p>
        <form method="post" action="<?= APP_URL ?>/admin/ops-setup" class="mt-5">
            <?= csrf_field() ?>
            <button type="submit" class="px-4 py-2 rounded-lg bg-brand-burgundy text-white font-semibold text-sm">Set up my Ops account</button>
        </form>
    </div>
</div>
