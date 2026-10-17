<?php /* views/emails/verification.php — Variables: $owner_name, $verify_url, $expires_hours */ ?>
<h2 style="margin:0 0 16px;font-size:22px;color:#1B0A11;font-weight:700;">
    Verify your email address.
</h2>
<p style="margin:0 0 16px;color:#3a2f2b;">
    Hello <?= htmlspecialchars($owner_name ?? 'Director', ENT_QUOTES) ?>,
</p>
<p style="margin:0 0 24px;color:#3a2f2b;">
    To unlock document sharing with your clients, please verify your email address.
    This link expires in <strong><?= (int)($expires_hours ?? 48) ?> hours</strong>.
</p>

<table cellpadding="0" cellspacing="0" border="0" style="margin-bottom:24px;">
    <tr>
        <td style="background-color:<?= htmlspecialchars($biz_color ?? '#8B004A', ENT_QUOTES) ?>;">
            <a href="<?= htmlspecialchars($verify_url ?? '#', ENT_QUOTES) ?>"
               style="display:inline-block;padding:14px 32px;font-size:11px;font-weight:700;color:#ffffff;text-decoration:none;letter-spacing:0.15em;text-transform:uppercase;">
                Verify Email Address →
            </a>
        </td>
    </tr>
</table>

<p style="margin:0 0 8px;font-size:11px;color:#9a8f8a;">
    If you did not create a Nfelia workspace, ignore this email — no action is needed.
</p>
<p style="margin:0;font-size:11px;color:#9a8f8a;">
    If the button does not work:<br>
    <a href="<?= htmlspecialchars($verify_url ?? '#', ENT_QUOTES) ?>" style="color:#B69F5E;word-break:break-all;">
        <?= htmlspecialchars($verify_url ?? '', ENT_QUOTES) ?>
    </a>
</p>
