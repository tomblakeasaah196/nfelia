<?php
/**
 * Something broke on our side. Shown by index.php's handlers (with the
 * error's code, $GLOBALS['nf_error_ref'], which Ops → Errors can look up)
 * and by the web server itself (.htaccess ErrorDocument, with no code).
 * Self-contained: no stylesheet or script from anywhere else, since the
 * thing that broke may be the rest of the site.
 */
http_response_code(500);
$ref  = isset($GLOBALS['nf_error_ref']) && is_string($GLOBALS['nf_error_ref']) && preg_match('/^E[A-Z0-9]{7}$/', $GLOBALS['nf_error_ref']) ? $GLOBALS['nf_error_ref'] : null;
$home = defined('APP_URL') ? APP_URL : '/';
$mail = 'mailto:support@nfelia.com?subject=' . rawurlencode('Something went wrong' . ($ref ? ' (' . $ref . ')' : ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Something went wrong · Nfelia</title>
    <style>
        @font-face { font-family: "Plus Jakarta Sans"; font-weight: 200 800; font-display: swap; src: url(/assets/fonts/plus-jakarta-sans-latin.woff2) format("woff2"); }
        @font-face { font-family: "Bricolage Grotesque"; font-weight: 200 800; font-display: swap; src: url(/assets/fonts/bricolage-grotesque-latin.woff2) format("woff2"); }
        *, *::before, *::after { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px 16px; background: #F2EFE7; color: #1F0A14; font: 400 16px/1.55 "Plus Jakarta Sans", system-ui, sans-serif; }
        main { width: 100%; max-width: 480px; background: #fff; border: 1px solid #E8E4DA; border-radius: 16px; padding: 36px 28px; }
        .thread { width: 56px; height: 0; border-top: 3px dashed #B69F5E; margin-bottom: 24px; }
        h1 { font: 650 1.625rem/1.2 "Bricolage Grotesque", "Plus Jakarta Sans", sans-serif; margin: 0 0 10px; }
        p { margin: 0 0 20px; color: #5B4650; }
        .code { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 12px 14px; border: 1px solid #E8E4DA; border-radius: 10px; background: #FAF9F6; margin-bottom: 8px; }
        .code b { font-size: 1.125rem; letter-spacing: .06em; font-variant-numeric: tabular-nums; }
        .code button { border: 1px solid #D9D3C5; background: #fff; border-radius: 8px; padding: 6px 12px; font: 600 .875rem "Plus Jakarta Sans", sans-serif; color: #1F0A14; cursor: pointer; }
        .small { font-size: .875rem; color: #7D6C73; margin-bottom: 24px; }
        .actions { display: flex; gap: 10px; flex-wrap: wrap; }
        .btn { display: inline-flex; align-items: center; justify-content: center; min-height: 44px; padding: 10px 18px; border-radius: 10px; font-weight: 600; text-decoration: none; border: 1px solid transparent; cursor: pointer; font-size: 1rem; font-family: inherit; }
        .btn-primary { background: #8B004A; color: #fff; }
        .btn-secondary { background: #fff; color: #1F0A14; border-color: #D9D3C5; }
        a { color: #8B004A; }
    </style>
</head>
<body>
<main>
    <div class="thread" aria-hidden="true"></div>
    <h1>Something went wrong on our side</h1>
    <p>It wasn't anything you did. The Nfelia team has been told and is looking into it. Please try again in a minute.</p>
    <?php if ($ref): ?>
        <div class="code">
            <span>Error code <b id="ref"><?= htmlspecialchars($ref) ?></b></span>
            <button type="button" onclick="navigator.clipboard && navigator.clipboard.writeText('<?= htmlspecialchars($ref) ?>').then(function(){ this.textContent = 'Copied'; }.bind(this))">Copy</button>
        </div>
        <p class="small">If it keeps happening, <a href="<?= htmlspecialchars($mail) ?>">tell Nfelia support</a> this code and we'll find exactly what happened.</p>
    <?php else: ?>
        <p class="small">If it keeps happening, <a href="<?= htmlspecialchars($mail) ?>">tell Nfelia support</a>.</p>
    <?php endif; ?>
    <div class="actions">
        <button type="button" class="btn btn-primary" onclick="location.reload()">Try again</button>
        <a class="btn btn-secondary" href="<?= htmlspecialchars($home) ?>">Go to Nfelia</a>
    </div>
</main>
</body>
</html>
