<?php require_once 'views/partials/feedback_modal.php'; ?>
-->
<div x-data="feedbackModal()" x-init="checkTrigger()" x-show="open" x-cloak
     class="fixed inset-0 z-[999] flex items-center justify-center p-4"
     @keydown.escape.window="dismiss()">

    <!-- Backdrop -->
    <div x-show="open" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         class="absolute inset-0 bg-black/40" @click="dismiss()"></div>

    <!-- Modal -->
    <div x-show="open" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
         class="relative bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden">

        <!-- Success state -->
        <template x-if="submitted">
            <div class="p-8 text-center">
                <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-1">Thank you!</h3>
                <p class="text-sm text-gray-500">Your feedback helps us build a better Nfelia.</p>
                <button @click="open = false" class="mt-4 px-4 py-2 text-sm font-medium text-brand-plum hover:underline">Close</button>
            </div>
        </template>

        <!-- Form state -->
        <template x-if="!submitted">
            <form @submit.prevent="send()" class="divide-y divide-gray-100">
                <div class="p-6 pb-4">
                    <!-- Close -->
                    <button type="button" @click="dismiss()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>

                    <h3 class="text-lg font-semibold text-gray-900 mb-1" x-text="triggerTitle"></h3>
                    <p class="text-sm text-gray-500 mb-5">How was your experience? Your feedback shapes Nfelia.</p>

                    <!-- Star rating -->
                    <div class="flex justify-center gap-2 mb-5">
                        <template x-for="star in [1,2,3,4,5]" :key="star">
                            <button type="button" @click="rating = star" @mouseenter="hover = star" @mouseleave="hover = 0"
                                    class="transition-transform hover:scale-110 focus:outline-none">
                                <svg class="w-10 h-10 transition-colors" :class="star <= (hover || rating) ? 'text-yellow-400' : 'text-gray-200'"
                                     fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                                </svg>
                            </button>
                        </template>
                    </div>
                    <p class="text-center text-xs text-gray-400 mb-4" x-text="ratingLabel"></p>

                    <!-- Message -->
                    <textarea x-model="message" rows="3" placeholder="Tell us more (optional)..."
                              class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand-gold/30 focus:border-brand-gold resize-none"></textarea>

                    <!-- Image upload -->
                    <div class="mt-3">
                        <label class="flex items-center gap-2 text-xs text-gray-500 cursor-pointer hover:text-gray-700">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0022.5 18.75V5.25A2.25 2.25 0 0020.25 3H3.75A2.25 2.25 0 001.5 5.25v13.5A2.25 2.25 0 003.75 21z"/></svg>
                            <span x-text="imageFile ? imageFile.name : 'Attach a screenshot (optional)'"></span>
                            <input type="file" accept="image/jpeg,image/png,image/webp" class="hidden" @change="imageFile = $event.target.files[0]">
                        </label>
                    </div>
                </div>

                <div class="px-6 py-4 bg-gray-50 flex justify-end gap-3">
                    <button type="button" @click="dismiss()" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">Not now</button>
                    <button type="submit" :disabled="rating === 0 || sending"
                            class="px-5 py-2 bg-brand-plum text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-opacity disabled:opacity-50">
                        <span x-show="!sending">Send Feedback</span>
                        <span x-show="sending">Sending...</span>
                    </button>
                </div>
            </form>
        </template>
    </div>
</div>

<script>
function feedbackModal() {
    return {
        open: false,
        rating: 0,
        hover: 0,
        message: '',
        imageFile: null,
        trigger: null,
        submitted: false,
        sending: false,

        ratingLabels: { 1: 'Terrible', 2: 'Poor', 3: 'Okay', 4: 'Good', 5: 'Excellent!' },
        triggerTitles: {
            first_invoice:           'You created your first invoice!',
            first_delivery_signoff:  'Your first delivery was signed off!',
            first_receipt:           'You generated your first receipt!',
            first_quotation:         'You sent your first quotation!',
            manual:                  'How are you enjoying Nfelia?'
        },

        get ratingLabel() { return this.ratingLabels[this.hover || this.rating] || 'Tap a star'; },
        get triggerTitle() { return this.triggerTitles[this.trigger] || 'How are you enjoying Nfelia?'; },

        checkTrigger() {
            // Delay check so it doesn't fire on page load instantly
            setTimeout(() => {
                fetch('<?= APP_URL ?>/feedback/check', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(r => r.json())
                    .then(data => {
                        if (data.trigger) {
                            this.trigger = data.trigger;
                            // Small delay so user sees the page first
                            setTimeout(() => { this.open = true; }, 1500);
                        }
                    }).catch(() => {});
            }, 2000);
        },

        send() {
            if (this.rating === 0 || this.sending) return;
            this.sending = true;

            const fd = new FormData();
            fd.append('rating', this.rating);
            fd.append('message', this.message);
            fd.append('trigger_action', this.trigger || 'manual');
            fd.append('csrf_token', '<?= csrf_token() ?>');
            if (this.imageFile) fd.append('feedback_image', this.imageFile);

            fetch('<?= APP_URL ?>/feedback/submit', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    this.sending = false;
                    if (data.success) {
                        this.submitted = true;
                        setTimeout(() => { this.open = false; }, 2500);
                    }
                }).catch(() => { this.sending = false; });
        },

        dismiss() {
            if (this.trigger) {
                const fd = new FormData();
                fd.append('trigger_action', this.trigger);
                fd.append('csrf_token', '<?= csrf_token() ?>');
                fetch('<?= APP_URL ?>/feedback/dismiss', { method: 'POST', body: fd }).catch(() => {});
            }
            this.open = false;
        }
    };
}
</script>
