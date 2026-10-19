<?php
/**
 * Settings › Signatures (app/helpers/signing.php): which papers are signed
 * for the business, and how (a stamp, or the signer's own signature, drawn
 * once), and that drawing. Each paper can still be signed or not on its own
 * page. Owners and admins only: they sign for the business.
 *
 * Expects $business, $user.
 */
$sign_db  = get_db();
$sign_cfg = signing_settings_for($sign_db, (int)$business['id']);
$stmt_sig = $sign_db->prepare("SELECT signature_path FROM users WHERE id = ? AND business_id = ?");
$stmt_sig->execute([(int)$user['id'], (int)$business['id']]);
$sign_mine = signing_image_uri($stmt_sig->fetchColumn() ?: null);
$sign_help = [
    'quote'    => 'Your client signs too, to accept it',
    'invoice'  => 'Signed for your business only',
    'receipt'  => 'Signed for your business only',
    'delivery' => 'The receiver signs too, when it arrives',
    'deed'     => 'Signed for your business only',
];
?>
<section class="nf-card nf-sign" id="signatures" aria-labelledby="nf-sign-h">
    <div class="nf-card-h">
        <div>
            <h2 id="nf-sign-h">Signatures <?= nf_info('Signatures', 'Papers switched on here are signed when you or an admin make them. Papers your team makes wait for one of you to sign them. Any paper can still be signed, or not, on its own page. Every signature keeps a fingerprint of what was signed: when the paper changes it is signed again in the name of whoever changed it, and anyone can check it with the QR code on it.') ?></h2>
            <p>Which papers are signed for your business, and how. Every paper carries a QR code anyone can check.</p>
        </div>
    </div>

    <form method="POST" action="<?= APP_URL ?>/signing/settings" class="nf-sign-form">
        <?= csrf_field() ?>
        <?php foreach (SIGN_TYPES as $type => $t): ?>
        <div class="nf-sign-row">
            <label class="nf-switch"><input type="checkbox" role="switch" name="signing[<?= $type ?>][on]" value="1"<?= $sign_cfg[$type]['on'] ? ' checked' : '' ?>>
                <span><b><?= e($t['many']) ?></b><small><?= e($sign_help[$type]) ?></small></span></label>
            <select name="signing[<?= $type ?>][style]" aria-label="How <?= e(strtolower($t['many'])) ?> are signed">
                <option value="stamp"<?= $sign_cfg[$type]['style'] === 'stamp' ? ' selected' : '' ?>>Stamp</option>
                <option value="drawn"<?= $sign_cfg[$type]['style'] === 'drawn' ? ' selected' : '' ?>>Drawn signature</option>
            </select>
        </div>
        <?php endforeach; ?>
        <button class="nf-btn nf-btn-primary">Save</button>
    </form>

    <div class="nf-sign-mine" x-data="{ redraw: <?= $sign_mine ? 'false' : 'true' ?> }">
        <h3>Your signature</h3>
        <p><?= $sign_mine ? 'Papers set to a drawn signature carry this when you sign them.' : 'Draw it once. Until you do, papers you sign carry the stamp.' ?></p>
        <?php if ($sign_mine): ?>
        <div x-show="!redraw" class="nf-sign-have">
            <img src="<?= e($sign_mine) ?>" alt="Your signature">
            <div class="nf-sign-do">
                <button type="button" class="nf-btn nf-btn-ghost" @click="redraw = true">Draw it again</button>
                <form method="POST" action="<?= APP_URL ?>/signing/mine"><?= csrf_field() ?><input type="hidden" name="remove" value="1">
                    <button class="nf-btn nf-btn-ghost">Remove it</button></form>
            </div>
        </div>
        <?php endif; ?>
        <form x-show="redraw"<?= $sign_mine ? ' x-cloak' : '' ?> method="POST" action="<?= APP_URL ?>/signing/mine" class="nf-sign-pad" data-sign-pad>
            <?= csrf_field() ?>
            <input type="hidden" name="signature">
            <canvas width="600" height="200" aria-label="Draw your signature here"></canvas>
            <div class="nf-sign-do">
                <button type="button" class="nf-btn nf-btn-ghost" data-sign-clear>Clear</button>
                <button class="nf-btn nf-btn-primary">Save my signature</button>
            </div>
        </form>
    </div>
</section>
<script>
/* The signature pad: draw with a finger or a mouse; saved as a PNG */
document.querySelectorAll('[data-sign-pad]').forEach(function (form) {
    var canvas = form.querySelector('canvas'), ctx = canvas.getContext('2d'), down = false, drawn = false;
    ctx.lineWidth = 4; ctx.lineCap = 'round'; ctx.lineJoin = 'round'; ctx.strokeStyle = '#1B0A11';
    function at(e) {
        var r = canvas.getBoundingClientRect();
        return [(e.clientX - r.left) * canvas.width / r.width, (e.clientY - r.top) * canvas.height / r.height];
    }
    canvas.addEventListener('pointerdown', function (e) { down = drawn = true; canvas.setPointerCapture(e.pointerId); var p = at(e); ctx.beginPath(); ctx.moveTo(p[0], p[1]); });
    canvas.addEventListener('pointermove', function (e) { if (!down) return; var p = at(e); ctx.lineTo(p[0], p[1]); ctx.stroke(); });
    ['pointerup', 'pointercancel'].forEach(function (t) { canvas.addEventListener(t, function () { down = false; }); });
    form.querySelector('[data-sign-clear]').addEventListener('click', function () { ctx.clearRect(0, 0, canvas.width, canvas.height); drawn = false; });
    form.addEventListener('submit', function (e) {
        if (!drawn) { e.preventDefault(); alert('Draw your signature first.'); return; }
        form.querySelector('[name=signature]').value = canvas.toDataURL('image/png');
    });
});
</script>
