<?php
/**
 * "Share PDF" for an invoice, receipt or quote: the server-made PDF, through
 * the phone's share sheet, straight into WhatsApp. Hidden until
 * assets/js/phone.js finds the browser can share files.
 *
 * Expects $share_pdf = ['url' => public document URL, 'name' => file name,
 *                       'title' => 'Invoice IVL-INV-131', 'text' => message,
 *                       'tile' => true for the action tiles of the rebuilt pages]
 */
$share_attrs = 'data-share-pdf'
    . ' data-url="' . e($share_pdf['url'] . (str_contains($share_pdf['url'], '?') ? '&' : '?') . 'format=pdf') . '"'
    . ' data-name="' . e(preg_replace('/[^A-Za-z0-9._-]+/', '-', $share_pdf['name'])) . '"'
    . ' data-title="' . e($share_pdf['title']) . '" data-text="' . e($share_pdf['text']) . '"';
?>
<?php if (!empty($share_pdf['tile'])): ?>
<button type="button" hidden <?= $share_attrs ?>><?= nav_icon('share') ?><span>Share PDF</span></button>
<?php else: ?>
<button type="button" class="flex items-center gap-1.5 btn-primary text-white px-3 py-2 rounded-lg transition-all" hidden <?= $share_attrs ?>>
    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12M7.5 7.5 12 3l4.5 4.5M6 11H5a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-8a1 1 0 0 0-1-1h-1"/></svg>
    <span class="text-[10px] font-bold uppercase tracking-widest">Share PDF</span>
</button>
<?php endif; ?>
