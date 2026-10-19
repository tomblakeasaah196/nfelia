<?php
/**
 * nfelia.com/verify (VerifyController). One page, by $mode:
 *   start         type a code, or check a file ($problem: what went wrong)
 *   found         $found (records_verify_lookup()): genuine, its facts, what
 *                 changed; the form to ask for the full document
 *                 ($ask_problem, $form when it was sent back); $file_match
 *                 when a file was checked; $sent / $sent_message after asking
 *   unknown       a code that is no Nfelia code ($code)
 *   file_unknown  a file that isn't a Records Book as Nfelia made it ($file_name)
 *   notice        $title, $body (a confirmed request, a download that can't be had)
 *   missing       nothing here
 * Standalone (no app layout), quick on a phone, never indexed.
 */
$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$found ??= null;
$b = $found['business'] ?? null;
$sym = (string)($b['currency_symbol'] ?? '₦');
$money = fn($v) => records_money((float)$v, $sym);
$show = function (string $field, $value) use ($money, $h) {
    if ($value === null || $value === '') return '—';
    if (in_array($field, ['Amount', 'Paid', 'Still owed'], true)) return $h($money($value));
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$value)) return $h(date('j M Y', strtotime((string)$value)));
    return $h($value);
};
$book_facts = function (array $f) use ($money): array {
    $out = [];
    if (isset($f['period'])) $out['Covers'] = $f['period'];
    if (isset($f['sheets'])) $out['Sheets'] = (string)$f['sheets'];
    if (isset($f['customers'])) $out['Customers'] = number_format((int)$f['customers']);
    if (isset($f['invoices'])) $out['Invoices'] = number_format((int)$f['invoices']) . ' (' . $money($f['invoiced'] ?? 0) . ')';
    if (isset($f['received'])) $out['Money received'] = $money($f['received']);
    if (isset($f['spent'])) $out['Spent'] = $money($f['spent']);
    if (!empty($f['orders'])) $out['Orders'] = number_format((int)$f['orders']);
    return $out;
};
$titles = ['start' => 'Check a Nfelia record', 'found' => 'Genuine', 'unknown' => 'We can\'t find that code', 'file_unknown' => 'We can\'t match this file',
           'notice' => $title ?? '', 'missing' => 'Nothing here'];
$form ??= [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <?= errors_script_tag() ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= $h($titles[$mode] ?? 'Verify') ?> · Nfelia verify</title>
    <link rel="icon" href="/favicon.ico">
    <link rel="preload" href="/assets/fonts/plus-jakarta-sans-latin.woff2" as="font" type="font/woff2" crossorigin>
    <style>
        @font-face { font-family: "Plus Jakarta Sans"; src: url("/assets/fonts/plus-jakarta-sans-latin.woff2") format("woff2"); font-weight: 200 800; font-display: swap; }
        @font-face { font-family: "Bricolage Grotesque"; src: url("/assets/fonts/bricolage-grotesque-latin.woff2") format("woff2"); font-weight: 200 800; font-display: swap; }
        :root { --ink: #1F0A14; --ink-2: #5B4650; --brand: #8B004A; --deep: #5E0032; --tint: #F8EEF3; --gold: #B69F5E; --ground: #FBF8F9; --line: #E9DCE2;
                --in: #00764A; --in-soft: #E5F4EC; --owed: #B42318; --owed-soft: #FEECEA; --warn: #8A4B00; --warn-soft: #FFF2D6; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; background: var(--ground); color: var(--ink); font: 400 1rem/1.55 "Plus Jakarta Sans", system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; -webkit-font-smoothing: antialiased; }
        a { color: var(--brand); }
        .top { display: flex; align-items: center; justify-content: space-between; gap: 12px; max-width: 640px; margin: 0 auto; padding: 20px 16px 8px; }
        .top img { height: 28px; width: auto; display: block; }
        .top span { font: 600 .8125rem/1 "Plus Jakarta Sans", sans-serif; color: var(--ink-2); }
        .thread { max-width: 608px; height: 3px; margin: 8px auto 0; border-radius: 2px; background: linear-gradient(90deg, var(--brand), var(--brand));
                  position: relative; }
        .thread::after { content: ""; position: absolute; inset: 1px 4px; background: repeating-linear-gradient(90deg, var(--gold) 0 4px, transparent 4px 11px); }
        main { max-width: 640px; margin: 0 auto; padding: 20px 16px 40px; }
        .card { background: #FFF; border-radius: 20px; box-shadow: inset 0 0 0 1px var(--line); padding: 24px 20px; margin: 0 0 16px; }
        h1 { margin: 0 0 8px; font: 700 1.75rem/1.2 "Bricolage Grotesque", "Plus Jakarta Sans", sans-serif; letter-spacing: -.01em; }
        h2 { margin: 0 0 8px; font: 700 1.125rem/1.3 "Bricolage Grotesque", "Plus Jakarta Sans", sans-serif; }
        p { margin: 0 0 12px; color: var(--ink-2); }
        .lead { font-size: 1.0625rem; }
        .seal { display: inline-flex; align-items: center; gap: 8px; min-height: 32px; padding: 0 14px; border-radius: 999px; font: 700 .875rem/1 "Plus Jakarta Sans", sans-serif; margin: 0 0 14px; }
        .seal svg { width: 18px; height: 18px; }
        .seal.ok { background: var(--in-soft); color: var(--in); }
        .seal.bad { background: var(--owed-soft); color: var(--owed); }
        .seal.warn { background: var(--warn-soft); color: var(--warn); }
        .biz { display: flex; align-items: center; gap: 12px; margin: 0 0 12px; }
        .biz img { width: 48px; height: 48px; border-radius: 12px; object-fit: cover; box-shadow: inset 0 0 0 1px var(--line); }
        .biz b { display: block; font: 700 1.125rem/1.3 "Plus Jakarta Sans", sans-serif; }
        .biz small { color: var(--ink-2); font-size: .875rem; }
        dl { margin: 12px 0 0; }
        dl > div { display: flex; justify-content: space-between; gap: 16px; padding: 10px 0; border-top: 1px solid var(--line); }
        dt { color: var(--ink-2); }
        dd { margin: 0; font-weight: 600; text-align: right; font-variant-numeric: tabular-nums; overflow-wrap: anywhere; }
        .changes { margin: 16px 0 0; padding: 14px 16px; border-radius: 14px; background: var(--warn-soft); color: var(--warn); }
        .changes b { display: block; margin: 0 0 4px; }
        .changes ul { margin: 0; padding-left: 18px; }
        .code { font: 600 .9375rem/1.3 ui-monospace, "SF Mono", Consolas, monospace; letter-spacing: .02em; color: var(--ink); overflow-wrap: anywhere; }
        form { margin: 0; }
        label { display: grid; gap: 6px; margin: 0 0 12px; font: 600 .875rem/1.3 "Plus Jakarta Sans", sans-serif; }
        label small { font-weight: 400; color: var(--ink-2); }
        input[type=text], input[type=email], input[type=tel], select, textarea {
            width: 100%; min-height: 48px; padding: 0 14px; border-radius: 12px; border: 0; background: #FFF; box-shadow: inset 0 0 0 1px #D9C9D1;
            font: 400 1rem/1.3 "Plus Jakarta Sans", sans-serif; color: var(--ink); }
        textarea { min-height: 88px; padding: 12px 14px; resize: vertical; }
        input:focus, select:focus, textarea:focus { outline: none; box-shadow: inset 0 0 0 2px var(--brand); }
        input[name=code] { font: 600 1.125rem/1 ui-monospace, "SF Mono", Consolas, monospace; letter-spacing: .06em; text-transform: uppercase; }
        .row { display: flex; gap: 8px; }
        .row input { flex: 1; min-width: 0; }
        .two { display: grid; grid-template-columns: 1fr; gap: 0 12px; }
        @media (min-width: 560px) { .two { grid-template-columns: 1fr 1fr; } }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 48px; padding: 0 20px; border-radius: 12px; border: 0; cursor: pointer;
               background: var(--brand); color: #FFF; font: 700 1rem/1 "Plus Jakarta Sans", sans-serif; text-decoration: none; }
        .btn:hover { background: var(--deep); }
        .btn.quiet { background: rgb(31 10 20 / .06); color: var(--ink); }
        .btn:focus-visible, a:focus-visible, summary:focus-visible { outline: 2px solid var(--brand); outline-offset: 3px; }
        .check { display: flex; gap: 10px; align-items: flex-start; font-weight: 400; color: var(--ink-2); }
        .check input { width: 20px; height: 20px; margin: 2px 0 0; flex: none; accent-color: var(--brand); }
        .problem { margin: 0 0 14px; padding: 10px 12px; border-radius: 12px; background: var(--owed-soft); color: var(--owed); font-weight: 500; }
        .sent { margin: 0 0 14px; padding: 12px 14px; border-radius: 12px; background: var(--in-soft); color: var(--in); font-weight: 500; }
        details > summary { cursor: pointer; list-style: none; display: flex; align-items: center; justify-content: space-between; min-height: 48px; font: 700 1.0625rem/1.3 "Bricolage Grotesque", sans-serif; }
        details > summary::-webkit-details-marker { display: none; }
        details > summary::after { content: "+"; font: 600 1.5rem/1 "Plus Jakarta Sans", sans-serif; color: var(--brand); }
        details[open] > summary::after { content: "–"; }
        .hp { position: absolute; left: -9999px; width: 1px; height: 1px; overflow: hidden; }
        .file { display: grid; gap: 10px; }
        .file input[type=file] { font: inherit; color: var(--ink-2); }
        footer { max-width: 640px; margin: 0 auto; padding: 0 16px 32px; color: var(--ink-2); font-size: .875rem; }
        footer p { margin: 0 0 6px; }
        .sr-only { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }
    </style>
</head>
<body>
<header class="top">
    <a href="<?= $h(APP_URL) ?>" aria-label="Nfelia"><img src="/assets/img/records/nfelia-lockup.png" alt="Nfelia" width="93" height="28"></a>
    <span>Verify</span>
</header>
<div class="thread" aria-hidden="true"></div>

<main id="main">
<?php if ($mode === 'start'): ?>
    <section class="card">
        <h1>Check a Nfelia record</h1>
        <p class="lead">Every Records Book from Nfelia carries a code, and so does each invoice, receipt and payment in it. Scan the QR code, or type the code, to see that it is genuine and what it says.</p>
        <?php if (!empty($problem)): ?><p class="problem" role="alert"><?= $h($problem) ?></p><?php endif; ?>
        <form method="post" action="<?= $h(APP_URL . '/verify') ?>">
            <?= csrf_field() ?>
            <label for="code">The code <small>On the cover of the book, or in a record's Verify column</small></label>
            <div class="row">
                <input type="text" id="code" name="code" required autocomplete="off" autocapitalize="characters" spellcheck="false" placeholder="K7Q2M-XA9WT" maxlength="40">
                <button class="btn" type="submit">Check it</button>
            </div>
        </form>
    </section>
    <section class="card">
        <details>
            <summary>Check the file itself</summary>
            <p>Someone sent you a Records Book? Choose the file and we will tell you whether it is exactly as Nfelia made it. We only read its fingerprint; the file is not kept.</p>
            <form method="post" action="<?= $h(APP_URL . '/verify/check') ?>" enctype="multipart/form-data" class="file">
                <?= csrf_field() ?>
                <label for="book" class="sr-only">The Excel file</label>
                <input type="file" id="book" name="book" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
                <button class="btn quiet" type="submit">Check the file</button>
            </form>
        </details>
    </section>

<?php elseif ($mode === 'found'): $record = $found['sheet_key'] !== null; ?>
    <section class="card">
        <?php if (!empty($file_match)): ?>
            <span class="seal ok"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="m5 12 5 5L20 7"/></svg>Genuine and unchanged</span>
            <h1>This file is exactly as Nfelia made it</h1>
        <?php elseif (!empty($found['removed'])): ?>
            <span class="seal warn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M12 8v5m0 4h.01"/><circle cx="12" cy="12" r="9"/></svg>Genuine, since removed</span>
            <h1><?= $h($found['label']) ?></h1>
        <?php else: ?>
            <span class="seal ok"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="m5 12 5 5L20 7"/></svg>Genuine</span>
            <h1><?= $h($found['label']) ?></h1>
        <?php endif; ?>

        <div class="biz">
            <?php if (!empty($b['logo_path']) && is_file(BASE_PATH . '/' . ltrim($b['logo_path'], '/'))): ?>
                <img src="<?= $h(APP_URL . '/' . ltrim($b['logo_path'], '/')) ?>" alt="">
            <?php endif; ?>
            <span><b><?= $h($b['name']) ?></b><small><?= $record ? 'In its Records Book of ' : 'Records Book made by Nfelia on ' ?><?= $h(records_when($found['book']['created_at'], !$record)) ?></small></span>
        </div>

        <?php if (!$found['kept']): ?>
            <p>This book was made more than a year ago. Nfelia keeps a book's details for a year, so we can confirm the code is ours, but no longer what it said.</p>
        <?php elseif ($record): ?>
            <dl>
                <?php foreach ($found['facts'] as $field => $value): ?>
                    <div><dt><?= $h($field) ?></dt><dd><?= $show($field, $value) ?></dd></div>
                <?php endforeach; ?>
            </dl>
            <?php if (!empty($found['removed'])): ?>
                <div class="changes" role="status"><b>Removed since</b>The business has removed this record from Nfelia since the book was made.</div>
            <?php elseif ($found['changes']): ?>
                <div class="changes" role="status">
                    <b>Changed since the book was made</b>
                    <ul><?php foreach ($found['changes'] as $field => [$then, $now]): ?><li><?= $h($field) ?>: <?= $show($field, $then) ?> → <?= $show($field, $now) ?></li><?php endforeach; ?></ul>
                </div>
            <?php else: ?>
                <p style="margin:14px 0 0">Nothing about it has changed since the book was made.</p>
            <?php endif; ?>
        <?php else: ?>
            <dl><?php foreach ($book_facts($found['facts']) as $field => $value): ?><div><dt><?= $h($field) ?></dt><dd><?= $h($value) ?></dd></div><?php endforeach; ?></dl>
            <?php if (empty($file_match)): ?>
                <p style="margin:14px 0 0">To be sure a file you were sent is this book, unchanged, <a href="<?= $h(APP_URL . '/verify') ?>">check the file itself</a>.</p>
            <?php endif; ?>
        <?php endif; ?>
        <p style="margin:16px 0 0">Code <span class="code"><?= $h($record ? $found['code'] : records_code_display($found['code'])) ?></span></p>
    </section>

    <?php if ($found['kept']): ?>
    <section class="card" id="ask">
        <?php if (!empty($sent)): ?>
            <h2>Check your email</h2>
            <p class="sent" role="status"><?= $h($sent_message ?: 'We sent you a link. Open it to confirm, and we will ask the business.') ?></p>
        <?php else: ?>
        <details<?= !empty($ask_problem) ? ' open' : '' ?>>
            <summary>Need the full <?= $record ? 'document' : 'book' ?>?</summary>
            <p>Ask <?= $h($b['name']) ?> for it through Nfelia. They see your name, email and reason, and decide. If they say yes, we email you a link to download it.</p>
            <?php if (!empty($ask_problem)): ?><p class="problem" role="alert"><?= $h($ask_problem) ?></p><?php endif; ?>
            <form method="post" action="<?= $h(records_verify_url($found['code']) . '/ask') ?>">
                <?= csrf_field() ?>
                <div class="hp" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
                <div class="two">
                    <label>Your name<input type="text" name="name" required maxlength="120" autocomplete="name" value="<?= $h($form['name'] ?? '') ?>"></label>
                    <label>Your email<input type="email" name="email" required maxlength="190" autocomplete="email" value="<?= $h($form['email'] ?? '') ?>"></label>
                </div>
                <div class="two">
                    <label>Organisation <small>(optional)</small><input type="text" name="organisation" maxlength="160" autocomplete="organization" value="<?= $h($form['organisation'] ?? '') ?>"></label>
                    <label>Phone <small>(optional)</small><input type="tel" name="phone" maxlength="40" autocomplete="tel" value="<?= $h($form['phone'] ?? '') ?>"></label>
                </div>
                <label>You are
                    <select name="role"><?php foreach ($roles as $k => $label): ?><option value="<?= $h($k) ?>"<?= ($form['role'] ?? '') === $k ? ' selected' : '' ?>><?= $h($label) ?></option><?php endforeach; ?></select>
                </label>
                <label>Why you need it<textarea name="reason" required minlength="10" maxlength="1000" placeholder="e.g. Loan application for this business, reference LA-2291"><?= $h($form['reason'] ?? '') ?></textarea></label>
                <label class="check"><input type="checkbox" name="agree" value="1" required<?= !empty($form['agree']) ? ' checked' : '' ?>><span><?= $h($b['name']) ?> may see my name, email, organisation and reason.</span></label>
                <button class="btn" type="submit">Ask <?= $h($b['name']) ?></button>
            </form>
        </details>
        <?php endif; ?>
    </section>
    <?php endif; ?>

<?php elseif ($mode === 'unknown' || $mode === 'file_unknown'): ?>
    <section class="card">
        <span class="seal bad"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>Not found</span>
        <?php if ($mode === 'unknown'): ?>
            <h1>We can't find that code</h1>
            <p>Nfelia has no record with the code <span class="code"><?= $h($code ?? '') ?></span>. Check it was typed exactly (codes have no I, L, O or U), or scan the QR code instead.</p>
            <p>If it still isn't found, the document may not have come from Nfelia.</p>
        <?php else: ?>
            <h1>We can't match this file</h1>
            <p><b><?= $h($file_name ?? 'This file') ?></b> isn't a Records Book exactly as Nfelia made it. It may have been changed, or opened and saved again, which changes the file even when nothing else is different.</p>
            <p>Ask whoever sent it for the copy Nfelia gave them, or type the code from its cover to see what the book said.</p>
        <?php endif; ?>
        <p style="margin:16px 0 0"><a class="btn quiet" href="<?= $h(APP_URL . '/verify') ?>">Check another</a></p>
    </section>

<?php elseif ($mode === 'notice'): ?>
    <section class="card">
        <h1><?= $h($title ?? '') ?></h1>
        <p class="lead"><?= $h($body ?? '') ?></p>
        <p style="margin:16px 0 0"><a class="btn quiet" href="<?= $h(APP_URL . '/verify') ?>">Check a code</a></p>
    </section>

<?php else: ?>
    <section class="card">
        <h1>Nothing here</h1>
        <p>That page doesn't exist. <a href="<?= $h(APP_URL . '/verify') ?>">Check a code</a> instead.</p>
    </section>
<?php endif; ?>
</main>

<footer>
    <p>Nfelia keeps records for small businesses. We only share a business's records when the business says yes.</p>
    <p><a href="<?= $h(APP_URL) ?>">nfelia.com</a> · <a href="mailto:support@nfelia.com">support@nfelia.com</a></p>
</footer>
</body>
</html>
