<?php /* A short message on its own: $heading, $text. */ ?>
<h1><?= e($heading) ?></h1>
<p style="margin-top:8px;color:var(--ink-2)"><?= e($text) ?></p>
<p style="margin-top:20px">
    <a class="ops-btn ops-btn-secondary" href="<?= e(ops_url(ops_staff() ? '' : 'signin')) ?>"><?= ops_staff() ? 'Go to Home' : 'Go to sign in' ?></a>
</p>
