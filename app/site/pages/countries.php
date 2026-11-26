<?php
/**
 * Pricing, and Nfelia in Ghana, Kenya and Cameroon.
 *
 * One pricing view (views/site/pages/pricing.php) serves all four countries
 * and one country view (views/site/pages/country.php) the three country
 * homes; both read the country from the entry. What a month costs is never
 * written here: the views ask site_prices(), which is the countries table
 * (Ops → Countries and Prices). A country with no active row is "opening":
 * its pages say so and show no amount.
 *
 * Nigeria is the site itself, so its pricing page is /pricing (group main);
 * the others live under their prefix (group countries). Pages that share
 * 'alternates' are the same page for each country (hreflang).
 */
return [
    'pricing' => [
        'path'        => '/pricing',
        'view'        => 'pricing',
        'title'       => 'Pricing in Nigeria: one monthly price, free trial | Nfelia',
        'description' => 'What Nfelia costs in Nigeria: one monthly price for your website, invoices, receipts, debts and stock. Try it free first, no card. No commission on sales.',
        'summary'     => 'What Nfelia costs in Nigeria: the monthly price, the free trial, extra staff, the Records Book, how to pay and what happens if you stop.',
        'crumb'       => 'Pricing',
        'parent'      => null,
        'type'        => 'WebPage',
        'group'       => 'main',
        'country'     => 'NG',
        'alternates'  => 'pricing',
        'og'          => ['kicker' => 'Pricing', 'title' => 'One monthly price, everything included'],
        'published'   => '2026-10-28',
        'updated'     => '2026-10-28',
        'order'       => 20,
    ],

    /* ── Ghana ── */
    'gh' => [
        'path'        => '/gh',
        'view'        => 'country',
        'title'       => 'Nfelia Ghana: shop website, invoices and debts',
        'description' => 'Nfelia in Ghana: your own shop website, plus invoices, receipts, debts and stock on your phone. For sellers in Accra, Kumasi and Tema. Try it free now.',
        'summary'     => 'Nfelia for small businesses in Ghana: who it is for, how customers pay you by MoMo or bank, the free trial and what it costs in cedis.',
        'crumb'       => 'Nfelia in Ghana',
        'parent'      => null,
        'type'        => 'WebPage',
        'group'       => 'countries',
        'country'     => 'GH',
        'alternates'  => 'home',
        'og'          => ['kicker' => 'Ghana', 'title' => 'Your shop online, your books in order'],
        'published'   => '2026-10-28',
        'updated'     => '2026-10-28',
        'order'       => 10,
    ],
    'gh/pricing' => [
        'path'        => '/gh/pricing',
        'view'        => 'pricing',
        'title'       => 'Nfelia Ghana pricing: monthly price in cedis',
        'description' => 'What Nfelia costs in Ghana, in cedis: a free trial first, then one monthly price. Extra staff, the Records Book, paying by Mobile Money, and no commission.',
        'summary'     => 'What Nfelia costs in Ghana: the free trial, the monthly price in cedis, extra staff, the Records Book and how paying works.',
        'crumb'       => 'Pricing in Ghana',
        'parent'      => 'gh',
        'type'        => 'WebPage',
        'group'       => 'countries',
        'country'     => 'GH',
        'alternates'  => 'pricing',
        'og'          => ['kicker' => 'Pricing in Ghana', 'title' => 'One monthly price, in cedis'],
        'published'   => '2026-10-28',
        'updated'     => '2026-10-28',
        'order'       => 11,
    ],

    /* ── Kenya ── */
    'ke' => [
        'path'        => '/ke',
        'view'        => 'country',
        'title'       => 'Nfelia Kenya: online shop, invoices and debt tracker',
        'description' => 'Nfelia in Kenya: an online shop at yourname.nfelia.com, invoices, receipts, debts and stock on your phone. Customers pay you by M-Pesa or bank. Try it free.',
        'summary'     => 'Nfelia for small businesses in Kenya: who it is for, how customers pay you by M-Pesa or bank, the free trial and what it costs in shillings.',
        'crumb'       => 'Nfelia in Kenya',
        'parent'      => null,
        'type'        => 'WebPage',
        'group'       => 'countries',
        'country'     => 'KE',
        'alternates'  => 'home',
        'og'          => ['kicker' => 'Kenya', 'title' => 'An online shop and proper records'],
        'published'   => '2026-10-28',
        'updated'     => '2026-10-28',
        'order'       => 20,
    ],
    'ke/pricing' => [
        'path'        => '/ke/pricing',
        'view'        => 'pricing',
        'title'       => 'Nfelia Kenya pricing: monthly price in shillings',
        'description' => 'What Nfelia costs in Kenya, in shillings: a free trial first, then one monthly price. Extra staff, the Records Book, paying by Mobile Money, and no commission.',
        'summary'     => 'What Nfelia costs in Kenya: the free trial, the monthly price in shillings, extra staff, the Records Book and how paying works.',
        'crumb'       => 'Pricing in Kenya',
        'parent'      => 'ke',
        'type'        => 'WebPage',
        'group'       => 'countries',
        'country'     => 'KE',
        'alternates'  => 'pricing',
        'og'          => ['kicker' => 'Pricing in Kenya', 'title' => 'One monthly price, in shillings'],
        'published'   => '2026-10-28',
        'updated'     => '2026-10-28',
        'order'       => 21,
    ],

    /* ── Cameroon ── */
    'cm' => [
        'path'        => '/cm',
        'view'        => 'country',
        'title'       => 'Nfelia Cameroon: online shop, invoices and stock',
        'description' => 'Nfelia in Cameroon: your own shop website, invoices, receipts, debts and stock on your phone. For sellers in Douala, Yaoundé and Bamenda. Try it free first.',
        'summary'     => 'Nfelia for small businesses in Cameroon: who it is for, how customers pay you by MTN MoMo, Orange Money or bank, and what it costs in FCFA.',
        'crumb'       => 'Nfelia in Cameroon',
        'parent'      => null,
        'type'        => 'WebPage',
        'group'       => 'countries',
        'country'     => 'CM',
        'alternates'  => 'home',
        'og'          => ['kicker' => 'Cameroon', 'title' => 'Your shop online, your records in FCFA'],
        'published'   => '2026-10-28',
        'updated'     => '2026-10-28',
        'order'       => 30,
    ],
    'cm/pricing' => [
        'path'        => '/cm/pricing',
        'view'        => 'pricing',
        'title'       => 'Nfelia Cameroon pricing: monthly price in FCFA',
        'description' => 'What Nfelia costs in Cameroon, in FCFA: a free trial first, then one monthly price. Extra staff, the Records Book, how to pay, and no commission on sales.',
        'summary'     => 'What Nfelia costs in Cameroon: the monthly price in FCFA, the free trial, extra staff, the Records Book and how to pay by Mobile Money.',
        'crumb'       => 'Pricing in Cameroon',
        'parent'      => 'cm',
        'type'        => 'WebPage',
        'group'       => 'countries',
        'country'     => 'CM',
        'alternates'  => 'pricing',
        'og'          => ['kicker' => 'Pricing in Cameroon', 'title' => 'One monthly price, in FCFA'],
        'published'   => '2026-10-28',
        'updated'     => '2026-10-28',
        'order'       => 31,
    ],
];
