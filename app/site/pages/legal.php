<?php
/**
 * The legal pages. Their words are in views/site/legal/terms_body.php and
 * privacy_body.php, shared with the Terms and Privacy dialogs on the sign-up
 * page (views/home/_dialogs.php). Move 'updated' whenever they change.
 */
return [
    'terms' => [
        'path'        => '/terms',
        'view'        => 'terms',
        'title'       => 'Terms of Service | Nfelia',
        'description' => "Nfelia's Terms of Service: the free trial, paying one month at a time, refunds, what pauses if you stop paying, your website, and how long we keep your records.",
        'summary'     => 'The terms for using Nfelia: the free trial, paying month by month, refunds, your website, your records and the 12 months we keep them if you stop paying.',
        'crumb'       => 'Terms of Service',
        'parent'      => null,
        'type'        => 'WebPage',
        'group'       => 'legal',
        'og'          => ['kicker' => 'Legal', 'title' => 'Terms of Service'],
        'published'   => '2026-10-28',
        'updated'     => '2026-10-28',
        'order'       => 10,
    ],
    'privacy' => [
        'path'        => '/privacy',
        'view'        => 'privacy',
        'title'       => 'Privacy Policy | Nfelia',
        'description' => "Nfelia's Privacy Policy: what we collect and why, who we share it with, cookies, how long we keep it, and your rights in Nigeria, Ghana, Kenya and Cameroon.",
        'summary'     => 'What Nfelia collects and why, who helps us run it, what is public, the cookies we use, how long we keep data, and how to use your rights.',
        'crumb'       => 'Privacy Policy',
        'parent'      => null,
        'type'        => 'WebPage',
        'group'       => 'legal',
        'og'          => ['kicker' => 'Legal', 'title' => 'Privacy Policy'],
        'published'   => '2026-10-28',
        'updated'     => '2026-10-28',
        'order'       => 20,
    ],
];
