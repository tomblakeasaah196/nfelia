<?php
/**
 * Nfelia — small standalone notice page (no layout, no CDN).
 * For links that don't lead anywhere: missing documents, retired shop links,
 * unknown shops and shops that are closed for now. Always kept out of search.
 *
 * Expects $notice: title, body, and optionally
 *   status        HTTP status, 404 by default
 *   shop_name     shown above the title on a shop's own page
 *   whatsapp      digits only; shows a "Chat on WhatsApp" button
 *   action_url    a link out, with action_label
 */
http_response_code((int)($notice['status'] ?? 404));
header('X-Robots-Tag: noindex, nofollow');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($notice['title']) ?><?= !empty($notice['shop_name']) ? ' — ' . e($notice['shop_name']) : ' — Nfelia' ?></title>
    <style>
        :root { --ink: #1F0A14; --muted: #5B4650; --brand: #8B004A; --ground: #FBF8F9; --line: #E9DCE2; }
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; display: flex; flex-direction: column;
            align-items: center; justify-content: center; gap: 24px; padding: 32px 20px;
            background: var(--ground); color: var(--ink);
            font-family: "Plus Jakarta Sans", system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            -webkit-font-smoothing: antialiased;
        }
        main {
            width: 100%; max-width: 440px; padding: 32px 24px; text-align: center;
            background: #FFFFFF; border: 1px solid var(--line); border-radius: 16px;
        }
        .shop { margin: 0 0 8px; font-size: 15px; font-weight: 600; color: var(--muted); }
        h1 { margin: 0 0 12px; font-size: 24px; line-height: 1.25; font-weight: 700; }
        p { margin: 0; font-size: 16px; line-height: 1.55; color: var(--muted); }
        .actions { display: flex; flex-direction: column; gap: 12px; margin-top: 24px; }
        .btn {
            display: flex; align-items: center; justify-content: center; gap: 8px; min-height: 48px;
            padding: 0 20px; border-radius: 12px; font-size: 16px; font-weight: 700; text-decoration: none;
        }
        .btn-primary { background: var(--brand); color: #FFFFFF; }
        .btn-quiet { color: var(--brand); }
        .btn:focus-visible { outline: 2px solid var(--brand); outline-offset: 3px; }
        footer { font-size: 13px; color: var(--muted); }
        footer a { color: inherit; }
    </style>
</head>
<body>
    <main>
        <?php if (!empty($notice['shop_name'])): ?>
            <p class="shop"><?= e($notice['shop_name']) ?></p>
        <?php endif; ?>
        <h1><?= e($notice['title']) ?></h1>
        <p><?= e($notice['body']) ?></p>

        <?php if (!empty($notice['whatsapp']) || !empty($notice['action_url'])): ?>
        <div class="actions">
            <?php if (!empty($notice['whatsapp'])): ?>
                <a class="btn btn-primary" href="https://wa.me/<?= e($notice['whatsapp']) ?>" rel="noopener">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.6A8 8 0 1 1 21 12z"/></svg>
                    Chat on WhatsApp
                </a>
            <?php endif; ?>
            <?php if (!empty($notice['action_url'])): ?>
                <a class="btn <?= empty($notice['whatsapp']) ? 'btn-primary' : 'btn-quiet' ?>" href="<?= e($notice['action_url']) ?>">
                    <?= e($notice['action_label'] ?? 'Go to Nfelia') ?>
                </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </main>
    <footer><a href="<?= e(APP_URL) ?>">Nfelia</a></footer>
</body>
</html>
