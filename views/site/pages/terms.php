<?php
/**
 * /terms: the Terms of Service. The words live in views/site/legal/
 * terms_body.php, which the Terms dialog on the sign-up page
 * (views/home/_dialogs.php) shows too, so the two always match. Change the
 * words there, with its version and date, and move 'updated' in
 * app/site/pages/legal.php.
 */
require_once BASE_PATH . '/views/home/_icons.php';   // the building blocks draw icons before the layout loads them
$support    = site_org()['support'];
$legal_opts = ['h' => 'h2', 'sub' => 'h3', 'id' => ''];
ob_start();
$legal = require BASE_PATH . '/views/site/legal/terms_body.php';
$legal_body = ob_get_clean();
?>
    <article class="wrap article-wrap has-aside">
        <header class="article-head">
            <p class="kicker">Legal</p>
            <h1>Nfelia Terms of Service</h1>
            <p class="page-lead">Questions? Write to <a href="mailto:<?= e($support) ?>"><?= e($support) ?></a>.</p>
            <p class="meta">Effective date: <time datetime="<?= e($legal['effective']) ?>"><?= e(date('j F Y', strtotime($legal['effective']))) ?></time> · Version <?= e($legal['version']) ?></p>
        </header>
        <aside class="article-aside">
            <nav class="toc" aria-labelledby="toc-title">
                <h2 id="toc-title">On this page</h2>
                <ol>
                    <?php foreach ($legal['toc'] as [$id, $name]): ?>
                    <li><a href="#<?= e($id) ?>"><?= e($name) ?></a></li>
                    <?php endforeach; ?>
                </ol>
            </nav>
        </aside>
        <div class="prose">
<?= $legal_body ?>
        </div>
    </article>

<?= site_related(['privacy', 'pricing', 'help/billing', 'contact']) ?>
