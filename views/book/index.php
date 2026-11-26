<?php
/** nfelia.com/book: the kinds of meeting. $types (active, in order). */
?>
<h1>Book a meeting with the Nfelia team</h1>
<p class="bk-lead">Pick what you'd like to talk about, then a time that suits you. We meet on Google Meet, so you can join from your phone or computer. All times are Lagos time, the same as Cameroon’s: an hour ahead of Accra and two hours behind Nairobi.</p>

<?php if ($types): ?>
    <ul class="bk-types">
        <?php foreach ($types as $t): ?>
            <li>
                <a class="bk-type" href="<?= e(APP_URL . '/book/' . $t['slug']) ?>">
                    <b><?= e($t['name']) ?></b>
                    <?php if ($t['description']): ?><span><?= e($t['description']) ?></span><?php endif; ?>
                    <small><?= e(meeting_length_text((int)$t['minutes'])) ?> · Pick a time →</small>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
<?php else: ?>
    <div class="bk-empty">
        <h2>No meetings to book right now</h2>
        <p>Write to <a href="mailto:sales@nfelia.com">sales@nfelia.com</a> and we'll find a time with you.</p>
    </div>
<?php endif; ?>
