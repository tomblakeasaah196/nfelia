<?php /* views/emails/welcome_fr.php — the welcome email in French, for owners who sign up from Cameroon
   (or another French-speaking country: owner_email_lang() in app/helpers/getting_started.php).
   The same variables as views/emails/welcome.php: $owner_name, $workspace_name, $trial_days, $verify_url, $expires_hours.
   The app itself is English for now, so its words (the button names) are not translated here. */ ?>
<h2 style="margin:0 0 16px;font-size:22px;color:#1B0A11;font-weight:700;">
    Bienvenue sur Nfelia, <?= htmlspecialchars($owner_name ?? '', ENT_QUOTES) ?>.
</h2>
<p style="margin:0 0 16px;color:#3a2f2b;">
    Votre espace — <strong><?= htmlspecialchars($workspace_name ?? 'votre entreprise', ENT_QUOTES) ?></strong> — est prêt.
    Vous avez <strong><?= (int)($trial_days ?? 14) ?> jours</strong> d'accès complet, sans carte bancaire.
</p>
<p style="margin:0 0 16px;color:#3a2f2b;">
    L'application est en anglais pour le moment. Si quelque chose n'est pas clair, écrivez-nous à
    <a href="mailto:support@nfelia.com" style="color:#8B004A;">support@nfelia.com</a>.
</p>
<p style="margin:0 0 24px;color:#3a2f2b;">Pour bien commencer :</p>

<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:24px;">
    <?php
    $steps = [
        ['num'=>'01', 'title'=>'Terminez la configuration', 'desc'=>'Ajoutez votre logo, vos couleurs et vos coordonnées bancaires : chaque document que vous envoyez sera professionnel dès le premier jour.'],
        ['num'=>'02', 'title'=>'Ajoutez votre premier produit ou service', 'desc'=>'Tout part de votre catalogue dans Nfelia : factures, devis, reçus et bons de livraison s\'en servent.'],
        ['num'=>'03', 'title'=>'Créez votre première facture', 'desc'=>'Moins d\'une minute. Envoyez-la sous forme de lien : votre client n\'a besoin d\'aucune application.'],
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
                        <strong style="font-size:13px;color:#1B0A11;"><?= htmlspecialchars($s['title'], ENT_QUOTES) ?></strong><br>
                        <span style="font-size:12px;color:#6b5e58;"><?= htmlspecialchars($s['desc'], ENT_QUOTES) ?></span>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    <?php endforeach; ?>
</table>

<?php
// The verification link is also the way in, as in the English email.
$cta_url = $verify_url ?? APP_URL . '/dashboard';
?>
<?php if (isset($verify_url)): ?>
<p style="margin:0 0 16px;color:#3a2f2b;">
    Confirmez votre adresse e-mail avec le bouton ci-dessous : nous pourrons vous joindre, et vous pourrez réinitialiser votre mot de passe si vous l'oubliez. Il ouvre aussi votre espace.
    Le lien est valable <strong><?= (int)($expires_hours ?? 48) ?> heures</strong>.
</p>
<?php endif; ?>
<table cellpadding="0" cellspacing="0" border="0">
    <tr>
        <td style="background-color:<?= htmlspecialchars($biz_color ?? '#8B004A', ENT_QUOTES) ?>;border-radius:0;">
            <a href="<?= htmlspecialchars($cta_url, ENT_QUOTES) ?>"
               style="display:inline-block;padding:14px 32px;font-size:11px;font-weight:700;color:#ffffff;text-decoration:none;letter-spacing:0.15em;text-transform:uppercase;">
                <?= isset($verify_url) ? 'Confirmer mon adresse →' : 'Ouvrir mon espace →' ?>
            </a>
        </td>
    </tr>
</table>
<p style="margin:24px 0 0;font-size:11px;color:#9a8f8a;">
    Si le bouton ne fonctionne pas, copiez ce lien dans votre navigateur :<br>
    <a href="<?= htmlspecialchars($cta_url, ENT_QUOTES) ?>" style="color:#B69F5E;word-break:break-all;"><?= htmlspecialchars($cta_url, ENT_QUOTES) ?></a>
</p>
