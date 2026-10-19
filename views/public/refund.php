<?php
/**
 * The customer's refund page (PublicController::refund), in the shop's
 * colours, standalone (no app layout). Before the shop sends the money: a
 * short form for where it should go. After: the refund slip, with the
 * transfer reference or the proof.
 *
 * Expects $refund, $business, $refund_order (order_number, customer_name),
 * $error (what to fix, or null) and $sent (the details were just saved).
 */
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: private, no-store');
$theme    = theme_tokens($business['primary_color'] ?? null, $business['secondary_color'] ?? null);
$currency = $business['currency_symbol'] ?? '₦';
$amount   = planner_money((float)$refund['amount'], $currency);
$first    = planner_first_name($refund_order['customer_name']) ?: '';
$shop     = (string)$business['name'];
$paid     = $refund['status'] === 'paid';
$is_ghana = business_dial_code($business) === '233';
$kind     = (string)($_POST['payee_kind'] ?? ($refund['payee_kind'] ?: ($is_ghana ? 'momo' : 'bank')));
$val      = fn(string $k) => e((string)($_POST[$k] ?? ($refund[$k] ?? '')));
$banks    = ['Access Bank', 'Ecobank', 'Fidelity Bank', 'First Bank', 'FCMB', 'GTBank', 'Kuda', 'Moniepoint', 'OPay', 'PalmPay',
             'Polaris Bank', 'Stanbic IBTC', 'Sterling Bank', 'UBA', 'Union Bank', 'Wema Bank', 'Zenith Bank'];
$networks = ['MTN MoMo', 'Telecel Cash', 'AirtelTigo Money'];
$methods  = ['cash' => 'Cash', 'bank_transfer' => 'Bank transfer', 'mobile_money' => 'Mobile money', 'pos' => 'POS', 'other' => 'Other'];
$masked   = $refund['payee_number'] ? str_repeat('•', max(0, strlen($refund['payee_number']) - 4)) . substr($refund['payee_number'], -4) : '';
$logo     = !empty($business['logo_path']) ? APP_URL . '/' . $business['logo_path'] : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <?= errors_script_tag() ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= $paid ? 'Refund ' . e($refund['doc_number']) : 'Your refund' ?> — <?= e($shop) ?></title>
    <style>
        :root { --ink: #1F0A14; --muted: #5B4650; --faint: #7A6570; --line: #E9DCE2; --ground: #FBF8F9;
                --brand: <?= e($theme['accent']) ?>; --on-brand: <?= e($theme['on_accent']) ?>; --brand-text: <?= e($theme['accent_text']) ?>; --brand-soft: <?= e($theme['accent_soft']) ?>;
                --in: #00764A; --in-soft: #E5F4EC; --owed: #B42318; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; padding: 24px 16px 40px; background: var(--ground); color: var(--ink);
               font-family: "Plus Jakarta Sans", system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; -webkit-font-smoothing: antialiased; }
        main { width: 100%; max-width: 460px; margin: 0 auto; }
        .shop { display: flex; align-items: center; gap: 12px; margin: 0 0 16px; }
        .shop img { width: 44px; height: 44px; border-radius: 12px; object-fit: contain; background: #FFF; border: 1px solid var(--line); }
        .shop span { width: 44px; height: 44px; border-radius: 12px; display: grid; place-items: center; background: var(--brand); color: var(--on-brand); font-weight: 700; }
        .shop b { font-size: 17px; }
        .card { padding: 24px 20px; background: #FFFFFF; border: 1px solid var(--line); border-radius: 20px; }
        h1 { margin: 0 0 8px; font-size: 24px; line-height: 1.25; font-weight: 700; }
        .lead { margin: 0 0 20px; font-size: 16px; line-height: 1.55; color: var(--muted); }
        .amt { margin: 4px 0 2px; font-size: 36px; line-height: 1.1; font-weight: 700; font-variant-numeric: tabular-nums; }
        .seg { display: grid; grid-template-columns: 1fr 1fr; gap: 4px; padding: 4px; margin: 0 0 18px; border-radius: 14px; background: rgb(31 10 20 / .05); }
        .seg label { position: relative; }
        .seg input { position: absolute; inset: 0; opacity: 0; width: 100%; height: 100%; margin: 0; cursor: pointer; }
        .seg span { display: flex; align-items: center; justify-content: center; min-height: 48px; border-radius: 10px; font-weight: 600; color: var(--muted); font-size: 15px; }
        .seg input:checked + span { background: #FFFFFF; color: var(--ink); box-shadow: 0 1px 3px rgb(31 10 20 / .12), 0 0 0 1px var(--line); }
        .seg input:focus-visible + span { outline: 2px solid var(--brand-text); outline-offset: 2px; }
        .field { display: grid; gap: 6px; margin: 0 0 14px; font-size: 14px; font-weight: 600; }
        .field small { font-weight: 400; color: var(--faint); font-size: 13px; }
        .field input { width: 100%; min-height: 50px; padding: 0 14px; border-radius: 12px; border: 1px solid #D9C9D1; font: inherit; font-size: 16px; font-weight: 400; color: var(--ink); background: #FFF; }
        .field input:focus { outline: none; border-color: var(--brand-text); box-shadow: 0 0 0 1px var(--brand-text); }
        .btn { display: flex; align-items: center; justify-content: center; width: 100%; min-height: 52px; margin-top: 6px; border: 0; border-radius: 14px;
               background: var(--brand); color: var(--on-brand); font: inherit; font-size: 16px; font-weight: 700; cursor: pointer; text-decoration: none; }
        .btn:focus-visible { outline: 2px solid var(--brand-text); outline-offset: 3px; }
        .btn-quiet { background: none; color: var(--brand-text); border: 1px solid var(--line); }
        .err { margin: 0 0 16px; padding: 12px 14px; border-radius: 12px; background: #FEECEA; color: var(--owed); font-size: 15px; line-height: 1.45; }
        .ok { margin: 0 0 16px; padding: 12px 14px; border-radius: 12px; background: var(--in-soft); color: var(--in); font-size: 15px; line-height: 1.45; font-weight: 600; }
        .note { margin: 16px 0 0; font-size: 13px; line-height: 1.5; color: var(--faint); }
        dl { margin: 18px 0 0; }
        dl div { display: flex; justify-content: space-between; gap: 16px; padding: 12px 0; border-top: 1px solid var(--line); font-size: 15px; }
        dt { color: var(--muted); }
        dd { margin: 0; font-weight: 600; text-align: right; overflow-wrap: anywhere; }
        .stamp { display: inline-block; margin: 0 0 12px; padding: 4px 12px; border-radius: 999px; background: var(--in-soft); color: var(--in); font-weight: 700; font-size: 13px; }
        .row { display: grid; gap: 8px; margin-top: 18px; }
        footer { margin: 20px 0 0; text-align: center; font-size: 13px; color: var(--faint); }
        footer a { color: inherit; }
        @media print { body { background: #FFF; padding: 0; } .row, footer { display: none; } .card { border: 0; } }
    </style>
</head>
<body>
<main>
    <div class="shop">
        <?php if ($logo): ?><img src="<?= e($logo) ?>" alt=""><?php else: ?><span aria-hidden="true"><?= e(mb_strtoupper(mb_substr($shop, 0, 1))) ?></span><?php endif; ?>
        <b><?= e($shop) ?></b>
    </div>

    <?php if ($paid): ?>
        <!-- The refund slip -->
        <section class="card" aria-labelledby="slip-h">
            <p class="stamp">Refunded</p>
            <h1 id="slip-h">Refund slip</h1>
            <p class="lead" style="margin:0"><?= e($shop) ?> gave <?= $first !== '' ? e($first) . ' ' : '' ?>this money back.</p>
            <p class="amt"><?= e($amount) ?></p>
            <dl>
                <div><dt>Refund</dt><dd><?= e($refund['doc_number']) ?></dd></div>
                <?php if ($refund_order['order_number'] !== ''): ?><div><dt>Order</dt><dd><?= e($refund_order['order_number']) ?></dd></div><?php endif; ?>
                <div><dt>Date</dt><dd><?= e(date('j M Y', strtotime((string)$refund['paid_at']))) ?></dd></div>
                <div><dt>How</dt><dd><?= e($methods[$refund['method']] ?? 'Other') ?></dd></div>
                <?php if ($refund['payee_number']): ?><div><dt>To</dt><dd><?= e($refund['payee_bank']) ?> <?= e($masked) ?></dd></div><?php endif; ?>
                <?php if ($refund['reference']): ?><div><dt>Reference</dt><dd><?= e($refund['reference']) ?></dd></div><?php endif; ?>
                <?php if ($refund['reason']): ?><div><dt>Why</dt><dd><?= e($refund['reason']) ?></dd></div><?php endif; ?>
            </dl>
            <div class="row">
                <?php if ($refund['proof_path']): ?>
                    <a class="btn" href="<?= APP_URL ?>/public/refund-proof/<?= e($refund['token']) ?>" target="_blank" rel="noopener">See the proof of payment</a>
                <?php endif; ?>
                <button type="button" class="btn btn-quiet" onclick="window.print()">Save or print</button>
            </div>
        </section>

    <?php else: ?>
        <!-- Where should the money go? -->
        <section class="card" aria-labelledby="ask-h">
            <h1 id="ask-h"><?= $refund['status'] === 'details_in' && !$error ? 'Thank you' . ($first !== '' ? ', ' . e($first) : '') : 'Where should your money go?' ?></h1>
            <?php if ($sent || ($refund['status'] === 'details_in' && !$error && $_SERVER['REQUEST_METHOD'] !== 'POST')): ?>
                <p class="ok"><?= e($shop) ?> has your details. They will send <?= e($amount) ?> to <?= e($refund['payee_bank']) ?> <?= e($masked) ?> and share the refund slip here.</p>
                <p class="lead">Made a mistake? Change the details below.</p>
            <?php else: ?>
                <p class="lead"><?= e($shop) ?> is giving you back <b><?= e($amount) ?></b><?= $refund_order['order_number'] !== '' ? ' for order ' . e($refund_order['order_number']) : '' ?>.<?= $refund['reason'] ? ' ' . e($refund['reason']) . '.' : '' ?></p>
            <?php endif; ?>
            <?php if ($error): ?><p class="err" role="alert"><?= e($error) ?></p><?php endif; ?>

            <form method="POST" action="<?= APP_URL ?>/public/refund/<?= e($refund['token']) ?>" novalidate>
                <div class="seg" role="radiogroup" aria-label="Where it goes">
                    <label><input type="radio" name="payee_kind" value="bank"<?= $kind === 'bank' ? ' checked' : '' ?> onchange="nfKind(this.value)"><span>Bank account</span></label>
                    <label><input type="radio" name="payee_kind" value="momo"<?= $kind === 'momo' ? ' checked' : '' ?> onchange="nfKind(this.value)"><span>Mobile money</span></label>
                </div>
                <label class="field">Name on the account
                    <input type="text" name="payee_name" value="<?= $val('payee_name') ?>" maxlength="150" autocomplete="name" required>
                </label>
                <label class="field"><span data-when="bank"<?= $kind === 'momo' ? ' hidden' : '' ?>>Bank</span><span data-when="momo"<?= $kind === 'bank' ? ' hidden' : '' ?>>Network</span>
                    <input type="text" name="payee_bank" value="<?= $val('payee_bank') ?>" maxlength="100" list="<?= $kind === 'momo' ? 'nf-networks' : 'nf-banks' ?>" autocomplete="off" required>
                </label>
                <label class="field"><span data-when="bank"<?= $kind === 'momo' ? ' hidden' : '' ?>>Account number</span><span data-when="momo"<?= $kind === 'bank' ? ' hidden' : '' ?>>Mobile money number</span>
                    <input type="text" name="payee_number" value="<?= $val('payee_number') ?>" maxlength="24" inputmode="numeric" autocomplete="off" required>
                </label>
                <datalist id="nf-banks"><?php foreach ($banks as $b): ?><option value="<?= e($b) ?>"><?php endforeach; ?></datalist>
                <datalist id="nf-networks"><?php foreach ($networks as $b): ?><option value="<?= e($b) ?>"><?php endforeach; ?></datalist>
                <button class="btn" type="submit"><?= $refund['status'] === 'details_in' ? 'Update my details' : 'Send my details' ?></button>
            </form>
            <p class="note">Only <?= e($shop) ?> sees these details, to send your money. Nfelia never asks for your PIN or password.</p>
        </section>
    <?php endif; ?>
    <footer>Sent with <a href="https://nfelia.com" rel="noopener">Nfelia</a></footer>
</main>
<script>
function nfKind(kind) {
    document.querySelectorAll('[data-when]').forEach(function (el) { el.hidden = el.getAttribute('data-when') !== kind; });
    var bank = document.querySelector('input[name="payee_bank"]');
    if (bank) bank.setAttribute('list', kind === 'momo' ? 'nf-networks' : 'nf-banks');
}
</script>
</body>
</html>
