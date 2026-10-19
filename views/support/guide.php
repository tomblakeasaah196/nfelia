<?php
/**
 * views/support/guide.php — User Guide & FAQs
 */
$sections = [
    [
        'id'    => 'getting-started',
        'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z"/>',
        'title' => 'Getting Started',
        'steps' => [
            ['title' => 'Set Up Your Business', 'text' => 'After signing up, head to <strong>Settings → Business</strong> to add your business name, logo, contact details, and brand colours. These details appear on all your documents (invoices, receipts, quotations).'],
            ['title' => 'Add Your Products', 'text' => 'Go to <strong>Products</strong> and add the items or services you sell. Set names, prices, and categories. Products auto-populate when you create invoices and receipts.'],
            ['title' => 'Create Your First Document', 'text' => 'Head to <strong>Invoices</strong>, <strong>Receipts</strong>, or <strong>Quotations</strong> to create your first professional document. Add line items, your customer details, and generate a branded PDF you can share instantly.'],
        ],
    ],
    [
        'id'    => 'products',
        'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z"/>',
        'title' => 'Products',
        'steps' => [
            ['title' => 'Adding Products', 'text' => 'Click <strong>New Product</strong>, fill in the name, selling price, cost price (optional), and category. Cost price helps Nfelia calculate your profit margins.'],
            ['title' => 'Categories', 'text' => 'Organise products into categories to keep things tidy. Filter by category on the products list to find items quickly.'],
            ['title' => 'Editing & Deleting', 'text' => 'Click any product to edit its details. Products used in existing documents won\'t lose their history — only future documents use the updated info.'],
        ],
    ],
    [
        'id'    => 'invoices',
        'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>',
        'title' => 'Invoices',
        'steps' => [
            ['title' => 'Creating an Invoice', 'text' => 'Click <strong>New Invoice</strong>, enter the customer name and details, then add line items from your product list. The total calculates automatically. You can add notes and set a due date.'],
            ['title' => 'Sharing', 'text' => 'Once created, click <strong>Share</strong> to get a public link. Send it via WhatsApp or copy the link. Your customer sees a branded invoice page and can download the PDF.'],
            ['title' => 'Status Tracking', 'text' => 'Invoices move through statuses: <strong>Draft → Sent → Paid → Overdue</strong>. Mark invoices as paid when you receive payment.'],
        ],
    ],
    [
        'id'    => 'receipts',
        'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 14.25l6-6m4.5-3.493V21.75l-3.75-1.5-3.75 1.5-3.75-1.5-3.75 1.5V4.757c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0111.186 0c1.1.128 1.907 1.077 1.907 2.185z"/>',
        'title' => 'Receipts',
        'steps' => [
            ['title' => 'Generating Receipts', 'text' => 'Create a receipt for any cash or completed payment. Add line items, customer details, and payment method. Receipts get a unique number automatically.'],
            ['title' => 'From Invoices', 'text' => 'You can generate a receipt directly from a paid invoice — click the invoice, then <strong>Generate Receipt</strong>. All details carry over.'],
            ['title' => 'PDF & Sharing', 'text' => 'Every receipt has a public link and PDF download, just like invoices.'],
        ],
    ],
    [
        'id'    => 'quotations',
        'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z"/>',
        'title' => 'Quotations',
        'steps' => [
            ['title' => 'Creating Quotes', 'text' => 'Send professional price quotes to potential customers. Add items, set validity period, and include terms. Share via a public link.'],
            ['title' => 'Convert to Invoice', 'text' => 'When a customer accepts your quote, convert it to an invoice with one click — all line items transfer automatically.'],
        ],
    ],
    [
        'id'    => 'expenses',
        'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z"/>',
        'title' => 'Expenses',
        'steps' => [
            ['title' => 'Tracking Expenses', 'text' => 'Record every business expense — rent, supplies, transport, anything. Add the amount, category, date, and an optional note.'],
            ['title' => 'Categories', 'text' => 'Expenses are grouped by category so you can see where your money goes. This feeds into your Profit & Loss report.'],
        ],
    ],
    [
        'id'    => 'debts',
        'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>',
        'title' => 'Debts',
        'steps' => [
            ['title' => 'Money Owed To You', 'text' => 'Track receivable debts — money customers owe you. Add the person, amount, and due date. Mark as paid when collected.'],
            ['title' => 'Money You Owe', 'text' => 'Track payable debts too — money you owe suppliers. This gives you a complete picture of your cash position.'],
        ],
    ],
    [
        'id'    => 'deliveries',
        'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125h-1.5M8.25 18.75h6M3.75 14.25V3.375c0-.621.504-1.125 1.125-1.125h7.5c.621 0 1.125.504 1.125 1.125V14.25m-7.5 0h7.5"/>',
        'title' => 'Deliveries',
        'steps' => [
            ['title' => 'Creating a Delivery', 'text' => 'Link a delivery to an invoice or create a standalone one. Add recipient details, items, delivery provider, and rider info.'],
            ['title' => 'Status Tracking', 'text' => 'Track deliveries through: <strong>Pending → Dispatched → In Transit → Delivered → Received</strong>. Update the status as items move.'],
            ['title' => 'E-Signature Sign-Off', 'text' => 'Send a sign-off link to your customer. They sign on their phone to confirm receipt — the signature is stored and shown on the delivery document.'],
        ],
    ],
    [
        'id'    => 'landing-page',
        'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418"/>',
        'title' => 'Shop Studio',
        'steps' => [
            ['title' => 'Pick a look', 'text' => 'Go to <strong>Shop Studio</strong> and choose one of ten looks. Try the colour sets, colours from your logo and the fonts: the preview changes as you tap.'],
            ['title' => 'Arrange your sections', 'text' => 'Switch sections on or off, drag them into order, and tap one to change its words, photo or questions.'],
            ['title' => 'Publish and share', 'text' => 'Changes stay in a draft until you tap <strong>Publish</strong>. Copy your shop link from the More tab and share it on WhatsApp, Instagram or anywhere.'],
        ],
    ],
    [
        'id'    => 'referrals',
        'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"/>',
        'title' => 'Referrals',
        'steps' => [
            ['title' => 'Your Referral Code', 'text' => 'Every business gets a unique referral code. Find it on the <strong>Referrals</strong> page.'],
            ['title' => 'Share & Earn', 'text' => 'Share your code with other business owners. When they sign up and make their first payment, you earn <strong>₦2,500 off</strong> your next bill.'],
        ],
    ],
    [
        'id'    => 'settings',
        'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>',
        'title' => 'Settings & Billing',
        'steps' => [
            ['title' => 'Business Profile', 'text' => 'Update your business name, logo, tagline, contact info, currency, and brand colours. These reflect on all your documents.'],
            ['title' => 'Your Profile', 'text' => 'Change your name, email, avatar, and password from the <strong>Profile</strong> tab.'],
            ['title' => 'Team Management', 'text' => 'Business owners can add staff members from the <strong>Team</strong> tab. Staff get operational access (products, invoices, receipts) but can\'t access billing or P&L.'],
            ['title' => 'Billing', 'text' => 'View your subscription status, subscribe or renew, add extra user seats, view payment history, or cancel from the <strong>Billing</strong> tab.'],
        ],
    ],
];

require_once BASE_PATH . '/app/helpers/prices.php';
$site_prices = prices_site();   // as Ops → Prices has them
$naira_text  = fn(float $n) => '₦' . number_format($n);
$faqs = [
    ['q' => 'How much does Nfelia cost?', 'a' => 'Nfelia is ' . $naira_text($site_prices['month']) . '/month with a 14-day free trial. No card required to start. Additional team members cost ' . $naira_text($site_prices['seat']) . '/month each (up to 5 total users).'],
    ['q' => 'Can I try Nfelia for free?', 'a' => 'Yes! Every new business gets a full 14-day free trial with access to all features. No payment information needed.'],
    ['q' => 'What happens when my trial ends?', 'a' => 'Your account will be paused until you subscribe. All your data is safe — nothing is deleted. Subscribe anytime to pick up where you left off.'],
    ['q' => 'Can I take my records with me?', 'a' => 'Yes, always. Open <strong>Your records</strong> in the menu, under Data. Your <strong>Records Book</strong> puts everything in one Excel workbook in your colours, with totals, a chart of your months and a code anyone can check at nfelia.com/verify. It is free once in every month you pay for, or ' . $naira_text($site_prices['book']) . ' at other times. Your <strong>plain copy</strong> (every record in CSV files) is free, whatever your plan.'],
    ['q' => 'How do I add team members?', 'a' => 'Go to <strong>Settings → Team</strong> (owner only). Add their name, email, and a temporary password. They can change their password after logging in. Each extra seat costs ' . $naira_text($site_prices['seat']) . '/month.'],
    ['q' => 'What can staff members see?', 'a' => 'Staff have access to products, invoices, receipts, quotations, expenses, debts, and deliveries. They cannot access billing, P&L reports, landing page settings, or team management.'],
    ['q' => 'How do I share documents with customers?', 'a' => 'Every invoice, receipt, quotation, and delivery has a <strong>Share</strong> button that generates a public link. Share via WhatsApp, email, or copy the link directly.'],
    ['q' => 'Can my customers download PDFs?', 'a' => 'Yes. Every shared document page has a <strong>Download PDF</strong> button that generates a branded PDF with your logo and colours.'],
    ['q' => 'How does the referral programme work?', 'a' => 'Share your unique referral code with other business owners. When they sign up and make their first payment, you get ₦2,500 off your next bill (25% discount).'],
    ['q' => 'Can I change my business logo?', 'a' => 'Yes. Go to <strong>Settings → Business</strong> and upload a new logo. If you don\'t have one, click <strong>Auto-Generate Logo</strong> — Nfelia will create one using your brand colours and business initials.'],
    ['q' => 'Is my data safe?', 'a' => 'Absolutely. All data is encrypted in transit (HTTPS), passwords are hashed with bcrypt, and each business\'s data is completely isolated from others.'],
    ['q' => 'How do I cancel my subscription?', 'a' => 'Go to <strong>Settings → Billing</strong> and click <strong>Cancel Subscription</strong> at the bottom. Your access continues until the end of your current billing period.'],
    ['q' => 'What payment methods do you accept?', 'a' => 'We accept all payment methods supported by Paystack — bank cards (Visa, Mastercard, Verve), bank transfers, and USSD.'],
    ['q' => 'Can I track deliveries?', 'a' => 'Yes. Create a delivery from any invoice and track it through five stages: Pending → Dispatched → In Transit → Delivered → Received. The receiver confirms it arrived from their link: with one tap, or by signing when the delivery note is signed.'],
    ['q' => 'How does the Profit & Loss report work?', 'a' => 'The P&L report (owner only) compares your receipts (revenue) against your expenses to show your profit, margin, and a 6-month trend chart. It also shows outstanding invoices and debts.'],
    ['q' => 'Can I use Nfelia on my phone?', 'a' => 'Yes! Nfelia is fully mobile-responsive. Access it from any phone browser — no app download needed.'],
];
?>

<div class="max-w-4xl mx-auto px-4 py-6 space-y-8" x-data="{ openFaq: null, search: '', openSection: null }">

    <!-- Header -->
    <div class="text-center">
        <h1 class="text-2xl font-bold text-brand-plum">User Guide</h1>
        <p class="text-sm text-brand-plum/60 mt-1">Everything you need to know to run your business on Nfelia.</p>
    </div>

    <!-- Quick Search -->
    <div class="relative">
        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg>
        <input type="text" x-model="search" placeholder="Search the guide or FAQs..." class="w-full pl-10 pr-4 py-3 border rounded-xl text-sm focus:ring-2 focus:ring-brand-gold/30 focus:border-brand-gold">
    </div>

    <!-- Quick Nav -->
    <div class="flex flex-wrap gap-2">
        <?php foreach ($sections as $i => $sec): ?>
        <a href="#<?= $sec['id'] ?>" class="px-3 py-1.5 bg-white border rounded-lg text-xs font-medium text-gray-600 hover:bg-brand-gold/10 hover:text-brand-gold hover:border-brand-gold/30 transition-colors">
            <?= $sec['title'] ?>
        </a>
        <?php endforeach; ?>
        <a href="#faqs" class="px-3 py-1.5 bg-brand-plum/5 border border-brand-plum/20 rounded-lg text-xs font-medium text-brand-plum hover:bg-brand-plum/10 transition-colors">
            FAQs
        </a>
    </div>

    <!-- Module Guides -->
    <?php foreach ($sections as $i => $sec): ?>
    <div id="<?= $sec['id'] ?>" class="bg-white rounded-xl border overflow-hidden"
         x-show="!search || '<?= strtolower($sec['title']) ?>'.includes(search.toLowerCase())"
         x-data="{ expanded: false }">
        <button @click="expanded = !expanded" class="w-full px-5 py-4 flex items-center gap-3 hover:bg-gray-50/50 transition-colors text-left">
            <div class="w-10 h-10 rounded-xl bg-brand-plum/5 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-brand-plum" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><?= $sec['icon'] ?></svg>
            </div>
            <div class="flex-1">
                <h2 class="font-semibold text-brand-plum"><?= $sec['title'] ?></h2>
                <p class="text-xs text-gray-400"><?= count($sec['steps']) ?> steps</p>
            </div>
            <svg class="w-5 h-5 text-gray-400 transition-transform" :class="expanded && 'rotate-180'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
        </button>
        <div x-show="expanded" x-collapse>
            <div class="px-5 pb-5 space-y-4 border-t pt-4">
                <?php foreach ($sec['steps'] as $j => $step): ?>
                <div class="flex gap-3">
                    <div class="w-7 h-7 rounded-full bg-brand-gold/10 flex items-center justify-center text-brand-gold font-bold text-xs shrink-0 mt-0.5"><?= $j + 1 ?></div>
                    <div>
                        <p class="font-medium text-sm text-gray-900"><?= $step['title'] ?></p>
                        <p class="text-sm text-gray-600 mt-0.5 leading-relaxed"><?= $step['text'] ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- FAQs -->
    <div id="faqs" class="space-y-3">
        <h2 class="text-xl font-bold text-brand-plum">Frequently Asked Questions</h2>

        <?php foreach ($faqs as $i => $faq): ?>
        <div class="bg-white rounded-xl border overflow-hidden"
             x-show="!search || '<?= strtolower(addslashes($faq['q'])) ?>'.includes(search.toLowerCase()) || '<?= strtolower(addslashes(strip_tags($faq['a']))) ?>'.includes(search.toLowerCase())">
            <button @click="openFaq === <?= $i ?> ? openFaq = null : openFaq = <?= $i ?>" class="w-full px-5 py-4 flex items-center justify-between text-left hover:bg-gray-50/50 transition-colors">
                <span class="font-medium text-sm text-gray-900 pr-4"><?= $faq['q'] ?></span>
                <svg class="w-4 h-4 text-gray-400 shrink-0 transition-transform" :class="openFaq === <?= $i ?> && 'rotate-180'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
            </button>
            <div x-show="openFaq === <?= $i ?>" x-collapse>
                <div class="px-5 pb-4 text-sm text-gray-600 leading-relaxed border-t pt-3"><?= $faq['a'] ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Still need help? -->
    <div class="bg-brand-plum/5 rounded-xl p-6 text-center">
        <p class="font-semibold text-brand-plum mb-1">Still have questions?</p>
        <p class="text-sm text-gray-600 mb-4">We're here to help. Reach out anytime.</p>
        <a href="<?= APP_URL ?>/contact-support" class="inline-flex items-center gap-2 px-5 py-2.5 bg-brand-plum text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-opacity">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z"/></svg>
            Contact Support
        </a>
    </div>
</div>
