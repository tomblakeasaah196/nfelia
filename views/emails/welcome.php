<?php /* views/emails/welcome.php — Variables: $owner_name, $workspace_name, $trial_days, $verify_url, $expires_hours
   ($workspace_name, not $biz_name: a $biz_name here would replace "Nfelia" as the sender in the header and footer) */ ?>
<h2 style="margin:0 0 16px;font-size:22px;color:#1B0A11;font-weight:700;">
    Welcome to Nfelia, <?= htmlspecialchars($owner_name ?? 'Director', ENT_QUOTES) ?>.
</h2>
<p style="margin:0 0 16px;color:#3a2f2b;">
    Your workspace — <strong><?= htmlspecialchars($workspace_name ?? 'Your Business', ENT_QUOTES) ?></strong> — is live.
    You have <strong><?= (int)($trial_days ?? 14) ?> days</strong> of full access, no card required.
</p>
<p style="margin:0 0 24px;color:#3a2f2b;">Here is what to do first:</p>

<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:24px;">
    <?php
    $steps = [
        ['num'=>'01', 'title'=>'Complete your setup', 'desc'=>'Add your logo, brand colors, and bank details so every document you send looks professional immediately.'],
        ['num'=>'02', 'title'=>'Add your first product or service', 'desc'=>'Everything in Nfelia flows from your catalog — invoices, quotes, receipts, and delivery notes all pull from it.'],
        ['num'=>'03', 'title'=>'Create your first invoice', 'desc'=>'It takes under 60 seconds. Send it as a link. Your client does not need an app.'],
    ];
    foreach ($steps as $s):
    ?>
    <tr>
        <td style="padding:12px 0;border-bottom:1px solid #E8E4DA;vertical-align:top;">
            <table width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                    <td width="40" style="vertical-align:top;padding-top:2px;">
                        <span style="font-size:11px;font-weight:700;color:#B69F5E;font-family:'Courier New',monospace;letter-spacing:0.1em;">
                            <?= $s['num'] ?>
                        </span>
                    </td>
                    <td>
                        <strong style="font-size:13px;color:#1B0A11;"><?= $s['title'] ?></strong><br>
                        <span style="font-size:12px;color:#6b5e58;"><?= $s['desc'] ?></span>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    <?php endforeach; ?>
</table>

<?php
// The verification link is also the way in: it opens the workspace, or the
// sign-in page on a device that is not signed in.
$cta_url = $verify_url ?? APP_URL . '/dashboard';
?>
<?php if (isset($verify_url)): ?>
<p style="margin:0 0 16px;color:#3a2f2b;">
    Confirm your email address with the button below, so we can reach you and you can reset your password if you ever forget it. It also opens your workspace.
    The link works for <strong><?= (int)($expires_hours ?? 48) ?> hours</strong>.
</p>
<?php endif; ?>
<table cellpadding="0" cellspacing="0" border="0">
    <tr>
        <td style="background-color:<?= htmlspecialchars($biz_color ?? '#8B004A', ENT_QUOTES) ?>;border-radius:0;">
            <a href="<?= htmlspecialchars($cta_url, ENT_QUOTES) ?>"
               style="display:inline-block;padding:14px 32px;font-size:11px;font-weight:700;color:#ffffff;text-decoration:none;letter-spacing:0.15em;text-transform:uppercase;">
                <?= isset($verify_url) ? 'Verify Email Address →' : 'Open Your Workspace →' ?>
            </a>
        </td>
    </tr>
</table>
<p style="margin:24px 0 0;font-size:11px;color:#9a8f8a;">
    If the button does not work, paste this into your browser:<br>
    <a href="<?= htmlspecialchars($cta_url, ENT_QUOTES) ?>" style="color:#B69F5E;word-break:break-all;"><?= htmlspecialchars($cta_url, ENT_QUOTES) ?></a>
</p>
