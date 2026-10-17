<?php
/**
 * Nfelia — Email Base Layout
 * Variables available: $biz_name, $biz_color, $biz_logo, $biz_tagline, $biz_email, $inner_content,
 * $is_platform (true for Nfelia's own emails: Nfelia mark, "Powered by JBS Praxis")
 * All email clients have poor CSS support — use table-based layout, inline styles only.
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($subject ?? 'Nfelia', ENT_QUOTES) ?></title>
</head>
<body style="margin:0;padding:0;background-color:#F2EFE7;font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;">

    <!-- Outer wrapper -->
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#F2EFE7;">
        <tr>
            <td align="center" style="padding:32px 16px;">

                <!-- Email Card -->
                <table width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;background-color:#ffffff;border:1px solid #E8E4DA;">

                    <!-- Brand Color Top Bar -->
                    <tr>
                        <td height="4" style="background-color:<?= htmlspecialchars($biz_color, ENT_QUOTES) ?>;font-size:0;line-height:0;">&nbsp;</td>
                    </tr>

                    <!-- Header: Logo + Business Name -->
                    <tr>
                        <td style="padding:32px 40px 24px 40px;border-bottom:1px solid #E8E4DA;">
                            <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="vertical-align:middle;">
                                        <?php if ($is_platform): ?>
                                            <!-- Mark as an image, name as text: with images blocked the name still shows. -->
                                            <table cellpadding="0" cellspacing="0" border="0">
                                                <tr>
                                                    <td style="vertical-align:middle;padding-right:12px;">
                                                        <img src="<?= htmlspecialchars(APP_URL . '/assets/img/nfelia-mark-email.png', ENT_QUOTES) ?>"
                                                             width="55" height="40" alt=""
                                                             style="display:block;border:0;outline:none;width:55px;height:40px;">
                                                    </td>
                                                    <td style="vertical-align:middle;">
                                                        <span style="font-size:20px;font-weight:700;color:<?= htmlspecialchars($biz_color, ENT_QUOTES) ?>;letter-spacing:0.15em;text-transform:uppercase;">
                                                            <?= htmlspecialchars($biz_name, ENT_QUOTES) ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            </table>
                                        <?php elseif ($biz_logo): ?>
                                            <!-- Styled alt text stands in for the logo when images are blocked. -->
                                            <img src="<?= htmlspecialchars($biz_logo, ENT_QUOTES) ?>" alt="<?= htmlspecialchars($biz_name, ENT_QUOTES) ?>"
                                                 style="max-height:48px;max-width:160px;display:block;border:0;font-size:20px;font-weight:700;color:<?= htmlspecialchars($biz_color, ENT_QUOTES) ?>;letter-spacing:0.15em;">
                                        <?php else: ?>
                                            <span style="font-size:20px;font-weight:700;color:<?= htmlspecialchars($biz_color, ENT_QUOTES) ?>;letter-spacing:0.15em;text-transform:uppercase;">
                                                <?= htmlspecialchars($biz_name, ENT_QUOTES) ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td align="right" style="vertical-align:middle;">
                                        <span style="font-size:9px;color:#B69F5E;letter-spacing:0.25em;text-transform:uppercase;font-weight:700;">
                                            POWERED BY <?= $is_platform ? 'JBS PRAXIS' : 'NFELIA' ?>
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Body Content (injected per template) -->
                    <tr>
                        <td style="padding:40px 40px 32px 40px;font-size:14px;line-height:1.8;color:#1B0A11;">
                            <?= $inner_content ?>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="padding:24px 40px;background-color:#F2EFE7;border-top:1px solid #E8E4DA;">
                            <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="font-size:11px;color:#9a8f8a;line-height:1.6;">
                                        <strong style="color:#1B0A11;"><?= htmlspecialchars($biz_name, ENT_QUOTES) ?></strong><br>
                                        <?php if ($biz_tagline): ?>
                                            <em><?= htmlspecialchars($biz_tagline, ENT_QUOTES) ?></em><br>
                                        <?php endif; ?>
                                        <?php if ($biz_email && $biz_email !== MAIL_FROM_ADDRESS): ?>
                                            <a href="mailto:<?= htmlspecialchars($biz_email, ENT_QUOTES) ?>"
                                               style="color:#B69F5E;text-decoration:none;"><?= htmlspecialchars($biz_email, ENT_QUOTES) ?></a><br>
                                        <?php endif; ?>
                                    </td>
                                    <td align="right" style="font-size:10px;color:#c0b8b4;vertical-align:bottom;">
                                        <a href="<?= APP_URL ?>" style="color:#B69F5E;text-decoration:none;">nfelia.lpc.cm</a><br>
                                        <span style="color:#c0b8b4;">support@nfelia.lpc.cm</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="2" style="padding-top:16px;font-size:9px;color:#c0b8b4;border-top:1px solid #E8E4DA;margin-top:12px;">
                                        &copy; <?= date('Y') ?> Nfelia. This email was sent from <?= htmlspecialchars($biz_name, ENT_QUOTES) ?>'s workspace on Nfelia.
                                        If you did not expect this, you may safely ignore it.
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                </table>
                <!-- /Email Card -->

            </td>
        </tr>
    </table>

</body>
</html>
