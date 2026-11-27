<?php
/**
 * Getting-started emails: on days 1, 3 and 7 of a free trial, a short
 * email from Customer Support (Simi) with the one next step that matters,
 * until the business uses Nfelia (something to sell and a first sale).
 *
 * Why (27 Nov 2026): of the five businesses that found Nfelia on their own
 * in November, four finished setting up and only one ever used it, and
 * between the welcome email and "your trial ends in 3 days" Nfelia said
 * nothing. Owners in Cameroon (or anywhere French is the language) get
 * them in French: the app itself is English for now.
 *
 * The cron (cron/planner.php) runs getting_started_run() between 10:00 and
 * 11:00 Lagos time; each email goes once (its outbox dedupe key). Nobody
 * who was emailed from Ops in the last 3 days gets one, and nobody who
 * opted out of Nfelia's emails.
 */

const GETTING_STARTED_DAYS = [1, 3, 7];
/** Countries whose owners get French emails (the app itself is English for now). */
const GETTING_STARTED_FRENCH = ['CM', 'CI', 'SN', 'BJ', 'TG', 'GA', 'CD', 'CG', 'BF', 'ML', 'NE', 'TD', 'GN', 'FR', 'BE'];

/**
 * 'fr' or 'en' for a two-letter country code: the onboarding country, or
 * where the owner signed up from (businesses.signup_country). Also used by
 * the welcome email at sign-up (AuthController::register).
 */
function owner_email_lang(?string $iso): string
{
    return in_array(strtoupper((string)$iso), GETTING_STARTED_FRENCH, true) ? 'fr' : 'en';
}

/**
 * Where a business is, for choosing what to say: ['setup' => finished setup,
 * 'sell' => has something to sell, 'sale' => has a first sale, 'shop' => shop online].
 */
function getting_started_state(PDO $db, int $business_id): array
{
    $one = function (string $sql) use ($db, $business_id): bool {
        $stmt = $db->prepare($sql);
        $stmt->execute([$business_id]);
        return (bool)$stmt->fetchColumn();
    };
    return [
        'setup' => $one("SELECT onboarding_complete FROM businesses WHERE id = ?"),
        'sell'  => $one("SELECT 1 FROM products WHERE business_id = ? LIMIT 1"),
        'sale'  => $one("SELECT 1 FROM receipts WHERE business_id = ? LIMIT 1") || $one("SELECT 1 FROM invoices WHERE business_id = ? LIMIT 1")
                || $one("SELECT 1 FROM orders WHERE business_id = ? LIMIT 1") || $one("SELECT 1 FROM bookings WHERE business_id = ? LIMIT 1"),
        'shop'  => $one("SELECT 1 FROM businesses WHERE id = ? AND storefront_published IS NOT NULL"),
    ];
}

/**
 * The email for this day and state: ['subject', 'body', 'button_text',
 * 'button_url'], or null when there is nothing to say (they use Nfelia).
 * {first_name} and {business} are filled in when it is sent.
 */
function getting_started_email(int $day, array $state, string $lang): ?array
{
    if ($state['sell'] && $state['sale']) {
        return null;
    }
    $book = APP_URL . '/book';
    $fr = $lang === 'fr';

    if (!$state['setup']) {
        return $fr ? [
            'subject' => 'Terminez la configuration de {business} sur Nfelia',
            'body' => "Bonjour {first_name},\n\nBienvenue sur Nfelia ! Le compte de {business} est prêt, mais sa configuration n'est pas encore terminée. Cela prend environ deux minutes : votre pays, ce que vous vendez et votre numéro WhatsApp.\n\nEnsuite, vous pourrez ajouter ce que vous vendez et enregistrer votre première vente le jour même."
                    . ($day >= 3 ? "\n\nSi quelque chose vous bloque, répondez simplement à cet e-mail, ou réservez une courte séance gratuite avec nous." : ''),
            'button_text' => 'Terminer la configuration', 'button_url' => APP_URL . '/onboarding/step1',
        ] : [
            'subject' => 'Finish setting up {business} on Nfelia',
            'body' => "Hi {first_name},\n\nWelcome to Nfelia! The account for {business} is ready, but setup isn't finished yet. It takes about two minutes: your country, what you sell and your WhatsApp number.\n\nOnce that's done, you can add what you sell and record your first sale the same day."
                    . ($day >= 3 ? "\n\nIf something is in the way, just reply to this email, or book a short free session with us." : ''),
            'button_text' => 'Finish setting up', 'button_url' => APP_URL . '/onboarding/step1',
        ];
    }

    if ($day === 3) {
        return $fr ? [
            'subject' => 'On vous aide à bien démarrer ?',
            'body' => "Bonjour {first_name},\n\nComment se passent vos premiers jours avec Nfelia pour {business} ?\n\nSi vous le souhaitez, nous pouvons tout voir ensemble lors d'un court appel vidéo : ajouter ce que vous vendez, prendre une commande et envoyer un reçu. Cela prend environ 20 minutes et c'est gratuit.\n\nChoisissez le moment qui vous convient ci-dessous, ou répondez à cet e-mail avec vos questions.",
            'button_text' => 'Réserver une séance gratuite', 'button_url' => $book,
        ] : [
            'subject' => 'Can we help you get started?',
            'body' => "Hi {first_name},\n\nHow are the first days going with Nfelia for {business}?\n\nIf you like, we can go through it together on a short video call: adding what you sell, taking an order and sending a receipt. It takes about 20 minutes and it's free.\n\nPick a time that suits you below, or just reply to this email with your questions.",
            'button_text' => 'Book a free session', 'button_url' => $book,
        ];
    }

    if ($day === 7 && !$state['shop']) {
        return $fr ? [
            'subject' => 'Votre boutique peut être en ligne aujourd\'hui',
            'body' => "Bonjour {first_name},\n\n{business} a sa propre page sur Nfelia, prête à être partagée. Vos clients voient ce que vous vendez et commandent directement sur votre WhatsApp.\n\nIl suffit de quelques minutes : choisissez votre nom, votre style et ce que vous montrez. Ensuite, partagez le lien sur WhatsApp et Instagram.",
            'button_text' => 'Mettre ma boutique en ligne', 'button_url' => APP_URL . '/landing-page',
        ] : [
            'subject' => 'Your shop can be online today',
            'body' => "Hi {first_name},\n\n{business} has its own page on Nfelia, ready to share. Customers see what you sell and order straight to your WhatsApp.\n\nIt takes a few minutes: choose your name, your look and what to show. Then share the link on WhatsApp and Instagram.",
            'button_text' => 'Put my shop online', 'button_url' => APP_URL . '/landing-page',
        ];
    }

    if (!$state['sell']) {
        return $fr ? [
            'subject' => 'Commencez par ce que vous vendez',
            'body' => "Bonjour {first_name},\n\nBienvenue sur Nfelia, et merci d'avoir choisi Nfelia pour {business}.\n\nLa meilleure première étape : ajoutez ce que vous vendez, avec vos prix. Ensuite, chaque commande, reçu et facture se remplit tout seul, et votre boutique peut le montrer à vos clients.\n\nSi quelque chose n'est pas clair, répondez simplement à cet e-mail.",
            'button_text' => 'Ajouter ce que je vends', 'button_url' => APP_URL . '/products/create',
        ] : [
            'subject' => 'Start with what you sell',
            'body' => "Hi {first_name},\n\nWelcome to Nfelia, and thank you for choosing us for {business}.\n\nThe best first step is to add what you sell, with your prices. After that, every order, receipt and invoice fills itself in, and your shop can show it to customers.\n\nIf anything is unclear, just reply to this email.",
            'button_text' => 'Add what you sell', 'button_url' => APP_URL . '/products/create',
        ];
    }

    return $fr ? [
        'subject' => 'Enregistrez votre première vente',
        'body' => "Bonjour {first_name},\n\nCe que vous vendez est déjà dans Nfelia : bravo ! La prochaine étape est votre première vente. Notez une commande ou envoyez un reçu à un client sur WhatsApp : Nfelia garde le compte de ce qui est entré et de ce qui reste dû.\n\nSi vous avez une question, répondez simplement à cet e-mail.",
        'button_text' => 'Enregistrer une vente', 'button_url' => APP_URL . '/orders/create',
    ] : [
        'subject' => 'Record your first sale',
        'body' => "Hi {first_name},\n\nWhat you sell is already in Nfelia: well done! The next step is your first sale. Take an order or send a customer a receipt on WhatsApp, and Nfelia keeps count of what came in and what is still owed.\n\nIf you have a question, just reply to this email.",
        'button_text' => 'Record a sale', 'button_url' => APP_URL . '/orders/create',
    ];
}

/**
 * Today's getting-started emails. Returns how many were queued.
 */
function getting_started_run(PDO $db, DateTimeImmutable $now): int
{
    require_once BASE_PATH . '/app/ops/client_email.php';
    $today = $now->format('Y-m-d');
    $in = implode(',', array_map('intval', GETTING_STARTED_DAYS));
    // One owner per business: the first active one (a business has one today).
    $rows = $db->query("
        SELECT b.id, b.name, b.signup_country, c.iso_code, DATEDIFF('{$today}', DATE(b.created_at)) AS day,
               u.full_name, u.email
        FROM businesses b
        JOIN subscriptions s ON s.business_id = b.id AND s.status = 'trial'
        JOIN users u ON u.id = (SELECT MIN(o.id) FROM users o WHERE o.business_id = b.id AND o.role = 'owner' AND o.is_active = 1)
        LEFT JOIN countries c ON c.id = b.country_id
        WHERE b.is_active = 1 AND DATEDIFF('{$today}', DATE(b.created_at)) IN ({$in})")->fetchAll();
    $queued = 0;
    foreach ($rows as $r) {
        $day = (int)$r['day'];
        $key = 'getting-started:' . (int)$r['id'] . ':day' . $day;
        $sent = $db->prepare("SELECT 1 FROM notify_outbox WHERE dedupe_key = ? OR (business_id = ? AND event = 'ops.client_email' AND created_at > NOW() - INTERVAL 3 DAY) LIMIT 1");
        $sent->execute([$key, (int)$r['id']]);
        $out = $db->prepare("SELECT 1 FROM email_opt_outs WHERE email = ?");
        $out->execute([strtolower((string)$r['email'])]);
        if ($sent->fetchColumn() || $out->fetchColumn() || !filter_var($r['email'], FILTER_VALIDATE_EMAIL)) {
            continue;
        }
        $lang  = owner_email_lang($r['iso_code'] ?: $r['signup_country']);
        $email = getting_started_email($day, getting_started_state($db, (int)$r['id']), $lang);
        if (!$email) {
            continue;
        }
        $person   = ['full_name' => $r['full_name'], 'email' => $r['email']];
        $business = ['id' => (int)$r['id'], 'name' => $r['name']];
        $vars = [
            'subject'     => ops_email_fill($email['subject'], $person, $business),
            'body'        => ops_email_fill($email['body'], $person, $business),
            'button_text' => $email['button_text'], 'button_url' => $email['button_url'],
            'writer'      => 'Simi', 'writer_role' => $lang === 'fr' ? 'Service client' : 'Customer Support',
            'reply_to'    => ops_email_reply_to(), 'lang' => $lang,
        ];
        $id = notify_queue($db, [
            'business_id' => (int)$r['id'], 'event' => 'onboarding.day' . $day, 'channel' => 'email',
            'recipient' => $r['email'], 'recipient_name' => $r['full_name'], 'dedupe_key' => $key,
            'payload' => [
                'subject' => $vars['subject'], 'template' => 'ops_client_message', 'brand' => null, 'vars' => $vars,
                'from_name' => $lang === 'fr' ? 'Simi de Nfelia' : 'Simi from Nfelia', 'reply_to' => ops_email_reply_to(),
            ],
        ]);
        if ($id) {
            $queued++;
            $db->prepare("INSERT INTO ops_notes (subject_type, subject_id, staff_id, body) VALUES ('business', ?, NULL, ?)")
               ->execute([(int)$r['id'], mb_substr("Getting-started email, day {$day}" . ($lang === 'fr' ? ' (in French)' : '') . ": {$vars['subject']}", 0, 4000)]);
        }
    }
    return $queued;
}
