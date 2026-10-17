<?php /* views/emails/password_changed.php — Variables: $recover_url */ ?>
<h2 style="margin:0 0 16px;font-size:22px;color:#1B0A11;font-weight:700;">
    Your password was changed.
</h2>
<p style="margin:0 0 16px;color:#3a2f2b;">
    The password for your Nfelia workspace account was just reset. Any device
    that used "Keep me signed in" has been signed out.
</p>
<p style="margin:0 0 24px;color:#3a2f2b;">
    If this was you, there is nothing else to do.
    <strong>If it was not you</strong>, reset your password now and tell your workspace owner.
</p>
<table cellpadding="0" cellspacing="0" border="0" style="margin-bottom:24px;">
    <tr>
        <td style="background-color:<?= htmlspecialchars($biz_color ?? '#8B004A', ENT_QUOTES) ?>;">
            <a href="<?= htmlspecialchars($recover_url ?? '#', ENT_QUOTES) ?>"
               style="display:inline-block;padding:14px 32px;font-size:11px;font-weight:700;color:#ffffff;text-decoration:none;letter-spacing:0.15em;text-transform:uppercase;">
                Reset Password →
            </a>
        </td>
    </tr>
</table>
