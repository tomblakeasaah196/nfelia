<?php /* views/emails/subscription_activated.php — Variables: $owner_name, $biz_name, $amount, $currency_symbol, $period_end */ ?>
<h2 style="margin:0 0 16px;font-size:22px;color:#1B0A11;font-weight:700;">
    Payment confirmed. Subscription active.
</h2>
<p style="margin:0 0 24px;color:#3a2f2b;">
    Hello <?= htmlspecialchars($owner_name ?? 'Director', ENT_QUOTES) ?>,
</p>

<table width="100%" cellpadding="0" cellspacing="0" border="0"
       style="border:1px solid #E8E4DA;margin-bottom:24px;">
    <tr>
        <td style="padding:20px 24px;background-color:#F2EFE7;">
            <table width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                    <td style="font-size:11px;color:#9a8f8a;text-transform:uppercase;letter-spacing:0.1em;padding-bottom:4px;">
                        Amount Paid
                    </td>
                    <td align="right" style="font-size:11px;color:#9a8f8a;text-transform:uppercase;letter-spacing:0.1em;padding-bottom:4px;">
                        Valid Until
                    </td>
                </tr>
                <tr>
                    <td style="font-size:24px;font-weight:700;color:#1B0A11;">
                        <?= htmlspecialchars(($currency_symbol ?? '₦') . number_format((float)($amount ?? 0), 0), ENT_QUOTES) ?>
                    </td>
                    <td align="right" style="font-size:18px;font-weight:700;color:#1B0A11;">
                        <?= htmlspecialchars($period_end ?? '', ENT_QUOTES) ?>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<p style="margin:0 0 24px;color:#3a2f2b;">
    Your <strong><?= htmlspecialchars($biz_name ?? 'workspace', ENT_QUOTES) ?></strong> subscription is now
    <strong style="color:#16a34a;">active</strong>. Full access to all modules has been restored.
</p>

<table cellpadding="0" cellspacing="0" border="0">
    <tr>
        <td style="background-color:<?= htmlspecialchars($biz_color ?? '#8B004A', ENT_QUOTES) ?>;">
            <a href="<?= APP_URL ?>/settings?tab=billing"
               style="display:inline-block;padding:14px 32px;font-size:11px;font-weight:700;color:#ffffff;text-decoration:none;letter-spacing:0.15em;text-transform:uppercase;">
                View Billing Details →
            </a>
        </td>
    </tr>
</table>
