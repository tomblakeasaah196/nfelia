<?php
/**
 * The layout of the site's own pages (app/helpers/site.php → site_render).
 * $page is the page from the list, $content its view's HTML.
 *
 * The head says everything a search engine or an AI assistant needs: the
 * one canonical address, the same page in other countries (hreflang), the
 * picture and words to share, and the schema graph (site_schema).
 */
require_once BASE_PATH . '/views/home/_icons.php';

$page_url   = $page['path'] === '/' ? APP_URL . '/' : APP_URL . $page['path'];
$country    = site_country($page['country']);
$og_image   = site_image($page);
$is_article = in_array($page['type'], ['Article', 'TechArticle', 'BlogPosting'], true);
$trail      = site_trail($page);
$nav_iso    = $country['iso'];
?>
<!DOCTYPE html>
<html lang="<?= e($country['hreflang']) ?>">
<head><meta charset="utf-8">
    <?= errors_script_tag() ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page['title']) ?></title>
    <meta name="description" content="<?= e($page['description']) ?>">
    <meta name="robots" content="<?= $page['noindex'] ? 'noindex, follow' : 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1' ?>">
    <link rel="canonical" href="<?= e($page_url) ?>">
<?php foreach (site_alternates($page) as $lang => $href): ?>
    <link rel="alternate" hreflang="<?= e($lang) ?>" href="<?= e($href) ?>">
<?php endforeach; ?>
    <meta name="theme-color" content="#8B004A">

    <meta property="og:type" content="<?= $is_article ? 'article' : 'website' ?>">
    <meta property="og:url" content="<?= e($page_url) ?>">
    <meta property="og:title" content="<?= e($page['og']['title'] ?? $page['title']) ?>">
    <meta property="og:description" content="<?= e($page['description']) ?>">
    <meta property="og:image" content="<?= e($og_image) ?>">
<?php if (!$page['image']): ?>
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="<?= e(($page['og']['title'] ?? $page['crumb']) . ' — Nfelia') ?>">
<?php endif; ?>
    <meta property="og:site_name" content="Nfelia">
    <meta property="og:locale" content="<?= e($country['locale']) ?>">
<?php if ($is_article): ?>
    <meta property="article:published_time" content="<?= e($page['published']) ?>">
    <meta property="article:modified_time" content="<?= e($page['updated']) ?>">
<?php endif; ?>
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= e($page['og']['title'] ?? $page['title']) ?>">
    <meta name="twitter:description" content="<?= e($page['description']) ?>">
    <meta name="twitter:image" content="<?= e($og_image) ?>">

<?php require BASE_PATH . '/views/partials/pwa_head.php'; ?>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,700&amp;family=Caveat:wght@600&amp;family=Plus+Jakarta+Sans:wght@400;600&amp;display=swap">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/home.css?v=<?= (int)@filemtime(BASE_PATH . '/assets/css/home.css') ?>">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/site.css?v=<?= (int)@filemtime(BASE_PATH . '/assets/css/site.css') ?>">
    <link rel="alternate" type="text/plain" title="Nfelia for AI assistants" href="<?= APP_URL ?>/llms.txt">

    <script type="application/ld+json"><?= json_encode(site_schema($page), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
</head>
<body class="site-page site-<?= e($page['group']) ?>">
<a class="skip-link" href="#main">Skip to content</a>

<?php require BASE_PATH . '/views/site/_header.php'; ?>

<main id="main">
<?php if (site_shows_crumbs($page)): ?>
<nav class="crumbs" aria-label="Breadcrumb">
    <ol class="wrap">
        <?php foreach ($trail as $i => [$name, $href]): ?>
        <li><?php if ($i < count($trail) - 1): ?><a href="<?= e($href) ?>"><?= e($name) ?></a><?php else: ?><span aria-current="page"><?= e($name) ?></span><?php endif; ?></li>
        <?php endforeach; ?>
    </ol>
</nav>
<?php endif; ?>
<?= $content ?>
</main>

<?php require BASE_PATH . '/views/site/_footer.php'; ?>

<div class="wa">
    <a class="wa-toggle" href="<?= e(site_wa('Hello Nfelia, I was reading about ' . $page['crumb'] . ' and I have a question.')) ?>" target="_blank" rel="noopener" aria-label="Chat with us on WhatsApp" data-tip="Chat on WhatsApp"><?= nf_icon('whatsapp') ?></a>
</div>
<script src="<?= APP_URL ?>/assets/js/site.js?v=<?= (int)@filemtime(BASE_PATH . '/assets/js/site.js') ?>" defer></script>
</body>
</html>
