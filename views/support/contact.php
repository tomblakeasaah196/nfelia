<?php
/**
 * views/support/contact.php — Contact Support Page
 * Variables: $tickets, $user
 */
$user = auth_user();
?>

<div class="max-w-3xl mx-auto px-4 py-6 space-y-6">

    <div>
        <h1 class="text-2xl font-bold text-brand-plum">Contact Support</h1>
        <p class="text-sm text-brand-plum/60 mt-1">We're here to help. Choose the way that works best for you.</p>
    </div>

    <!-- Contact Methods -->
    <div class="grid sm:grid-cols-3 gap-4">
        <!-- WhatsApp -->
        <a href="https://wa.me/2348169789106?text=<?= urlencode('Hi Nfelia Support! I need help with my business account (' . (auth_business()['name'] ?? '') . ').') ?>"
           target="_blank"
           class="bg-white rounded-xl border p-5 text-center hover:border-green-300 hover:shadow-sm transition-all group">
            <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center mx-auto mb-3 group-hover:bg-green-200 transition-colors">
                <svg class="w-6 h-6 text-green-600" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 0C5.373 0 0 5.373 0 12c0 2.625.846 5.059 2.284 7.034L.789 23.492a.75.75 0 00.913.913l4.458-1.495A11.952 11.952 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 22c-2.387 0-4.592-.822-6.328-2.2l-.442-.36-3.085 1.034 1.034-3.085-.36-.442A9.96 9.96 0 012 12C2 6.486 6.486 2 12 2s10 4.486 10 10-4.486 10-10 10z"/></svg>
            </div>
            <p class="font-semibold text-sm text-gray-900">WhatsApp</p>
            <p class="text-xs text-gray-500 mt-1">Chat with us directly</p>
            <p class="text-xs text-green-600 font-medium mt-2">+234 816 978 9106</p>
        </a>

        <!-- Phone -->
        <a href="tel:+2348169789106"
           class="bg-white rounded-xl border p-5 text-center hover:border-blue-300 hover:shadow-sm transition-all group">
            <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center mx-auto mb-3 group-hover:bg-blue-200 transition-colors">
                <svg class="w-6 h-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z"/></svg>
            </div>
            <p class="font-semibold text-sm text-gray-900">Call Us</p>
            <p class="text-xs text-gray-500 mt-1">Speak to a human</p>
            <p class="text-xs text-blue-600 font-medium mt-2">+234 816 978 9106</p>
        </a>

        <!-- Email -->
        <a href="mailto:support@nfelia.com?subject=Support%20Request%20-%20<?= urlencode(auth_business()['name'] ?? 'Business') ?>"
           class="bg-white rounded-xl border p-5 text-center hover:border-purple-300 hover:shadow-sm transition-all group">
            <div class="w-12 h-12 bg-purple-100 rounded-xl flex items-center justify-center mx-auto mb-3 group-hover:bg-purple-200 transition-colors">
                <svg class="w-6 h-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/></svg>
            </div>
            <p class="font-semibold text-sm text-gray-900">Email</p>
            <p class="text-xs text-gray-500 mt-1">We reply within 24 hours</p>
            <p class="text-xs text-purple-600 font-medium mt-2">support@nfelia.com</p>
        </a>
    </div>

    <!-- Send a Message (In-App Ticket) -->
    <div class="bg-white rounded-xl border p-6">
        <h2 class="font-semibold text-brand-plum mb-4">Send Us a Message</h2>
        <form method="POST" action="<?= APP_URL ?>/support/submit-ticket">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Subject</label>
                    <input type="text" name="subject" required placeholder="What do you need help with?"
                           class="w-full border rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-brand-gold/30 focus:border-brand-gold">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Message</label>
                    <textarea name="message" rows="4" required placeholder="Describe your issue or question in detail..."
                              class="w-full border rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-brand-gold/30 focus:border-brand-gold resize-none"></textarea>
                </div>
                <button type="submit" class="px-6 py-2.5 bg-brand-plum text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-opacity">
                    Send Message
                </button>
            </div>
        </form>
    </div>

    <!-- Previous Tickets -->
    <?php if (!empty($tickets)): ?>
    <div class="bg-white rounded-xl border overflow-hidden">
        <div class="px-5 py-4 border-b">
            <h2 class="font-semibold text-brand-plum">Your Messages</h2>
        </div>
        <div class="divide-y divide-gray-50">
            <?php foreach ($tickets as $ticket): ?>
            <div class="px-5 py-4 scroll-mt-24" id="ticket-<?= (int)$ticket['id'] ?>">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="font-medium text-sm text-gray-900"><?= htmlspecialchars($ticket['subject']) ?></p>
                        <p class="text-sm text-gray-600 mt-1"><?= nl2br(htmlspecialchars($ticket['message'])) ?></p>
                    </div>
                    <div class="shrink-0 text-right">
                        <?php
                        $badge = match($ticket['status']) {
                            'open'    => 'bg-blue-100 text-blue-700',
                            'replied' => 'bg-green-100 text-green-700',
                            'closed'  => 'bg-gray-100 text-gray-600',
                            default   => 'bg-gray-100 text-gray-600',
                        };
                        ?>
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium <?= $badge ?>"><?= ucfirst($ticket['status']) ?></span>
                        <p class="text-xs text-gray-400 mt-1"><?= date('M j, Y', strtotime($ticket['created_at'])) ?></p>
                    </div>
                </div>
                <?php if ($ticket['admin_reply']): ?>
                <div class="mt-3 bg-brand-plum/5 rounded-lg p-3">
                    <p class="text-xs font-medium text-brand-plum mb-1">Nfelia Support:</p>
                    <p class="text-sm text-gray-700"><?= nl2br(htmlspecialchars($ticket['admin_reply'])) ?></p>
                    <p class="text-xs text-gray-400 mt-1"><?= $ticket['replied_at'] ? date('M j, Y g:ia', strtotime($ticket['replied_at'])) : '' ?></p>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- User Guide link -->
    <div class="text-center py-4">
        <a href="<?= APP_URL ?>/user-guide" class="text-sm text-brand-plum hover:underline">
            Check our User Guide & FAQs before reaching out — your answer might be there!
        </a>
    </div>
</div>