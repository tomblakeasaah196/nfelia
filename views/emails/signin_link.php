<?php /* views/emails/signin_link.php — Variables: $signin_url */ ?>
<h2 style="margin:0 0 16px;font-size:22px;color:#1B0A11;font-weight:700;">
    Sign in to your workspace.
</h2>
<p style="margin:0 0 16px;color:#3a2f2b;">
    Use the button below to sign in to Nfelia without a password.
    This link expires in <strong>15 minutes</strong> and works once.
</p>
<table cellpadding="0" cellspacing="0" border="0" style="margin-bottom:24px;">
    <tr>
        <td style="background-color:<?= htmlspecialchars($biz_color ?? '#8B004A', ENT_QUOTES) ?>;">
            <a href="<?= htmlspecialchars($signin_url ?? '#', ENT_QUOTES) ?>"
               style="display:inline-block;padding:14px 32px;font-size:11px;font-weight:700;color:#ffffff;text-decoration:none;letter-spacing:0.15em;text-transform:uppercase;">
                Sign In →
            </a>
        </td>
    </tr>
</table>
<p style="margin:0 0 8px;font-size:11px;color:#9a8f8a;">
    If you did not ask for this, ignore this message. Nobody can use the link without access to this inbox. Do not forward it.
</p>
<p style="margin:0;font-size:11px;color:#9a8f8a;">
    Link: <a href="<?= htmlspecialchars($signin_url ?? '#', ENT_QUOTES) ?>" style="color:#B69F5E;word-break:break-all;">
        <?= htmlspecialchars($signin_url ?? '', ENT_QUOTES) ?>
    </a>
</p>
