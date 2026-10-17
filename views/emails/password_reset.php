<?php /* views/emails/password_reset.php — Variables: $reset_url, $expires_label (default '1 hour'), $requested_by (optional: owner who sent it) */ ?>
<h2 style="margin:0 0 16px;font-size:22px;color:#1B0A11;font-weight:700;">
    Reset your workspace access.
</h2>
<p style="margin:0 0 16px;color:#3a2f2b;">
    <?php if (!empty($requested_by)): ?>
        <?= htmlspecialchars($requested_by, ENT_QUOTES) ?>, your workspace owner, sent you a link to set a new password for Nfelia.
    <?php else: ?>
        A password reset was requested for your Nfelia workspace.
    <?php endif; ?>
    This link expires in <strong><?= htmlspecialchars($expires_label ?? '1 hour', ENT_QUOTES) ?></strong> and works once.
</p>
<table cellpadding="0" cellspacing="0" border="0" style="margin-bottom:24px;">
    <tr>
        <td style="background-color:<?= htmlspecialchars($biz_color ?? '#8B004A', ENT_QUOTES) ?>;">
            <a href="<?= htmlspecialchars($reset_url ?? '#', ENT_QUOTES) ?>"
               style="display:inline-block;padding:14px 32px;font-size:11px;font-weight:700;color:#ffffff;text-decoration:none;letter-spacing:0.15em;text-transform:uppercase;">
                Reset Password →
            </a>
        </td>
    </tr>
</table>
<p style="margin:0 0 8px;font-size:11px;color:#9a8f8a;">
    If you did not request this, your workspace is safe — simply ignore this message.
</p>
<p style="margin:0;font-size:11px;color:#9a8f8a;">
    Link: <a href="<?= htmlspecialchars($reset_url ?? '#', ENT_QUOTES) ?>" style="color:#B69F5E;word-break:break-all;">
        <?= htmlspecialchars($reset_url ?? '', ENT_QUOTES) ?>
    </a>
</p>
