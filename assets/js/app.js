/**
 * Nfelia — Global App JS
 * NFELIA AUDIT FIX — H01 — Use window.Nfelia.csrf (not meta tag), fix csrf key name — 2026-06-03
 * NFELIA AUDIT FIX — M11 — Removed duplicate const Nfelia and dead poll_notifications — 2026-06-03
 * jQuery must be loaded before this file.
 */

// Base AJAX call wrapper — delegates to window.Nfelia set by the layout
function nfAjax(action, data = {}, method = 'POST') {
    return $.ajax({
        url: window.Nfelia.url + '/api/' + action,
        method: method,
        data: { ...data, csrf_token: window.Nfelia.csrf },
        dataType: 'json',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
}

// Flash a toast message on screen. shell.js replaces this with the full
// version (icon, dismiss, pause while touched) as soon as it loads; this one
// only covers a call made before then.
function nfToast(message, type = 'success') {
    const box = document.getElementById('nf-toasts');
    if (!box) return;
    const toast = document.createElement('div');
    toast.className = 'nf-toast is-' + (['error', 'warning'].includes(type) ? type : 'success');
    toast.textContent = message;
    box.appendChild(toast);
    setTimeout(() => toast.remove(), 4000);
}

// ---- LIVE PRODUCT SEARCH (Invoice / Quotation builder) ----
$(document).on('input', '.product-search', function () {
    const q     = $(this).val();
    const $list = $(this).closest('.product-field').find('.product-results');
    if (q.length < 2) { $list.empty().hide(); return; }

    $.ajax({
        url: window.Nfelia.url + '/api/products',
        method: 'GET',
        data: { action: 'search', q: q },
        dataType: 'json',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    }).done(res => {
        $list.empty();
        if (!res.data || !res.data.length) { $list.hide(); return; }
        res.data.forEach(p => {
            const symbol = window.Nfelia.currency || '₦';
            $list.append(
                '<div class="product-result-item cursor-pointer px-4 py-3 hover:bg-gray-50 border-b text-sm"' +
                ' data-id="' + p.id + '" data-name="' + p.name.replace(/"/g, '&quot;') + '"' +
                ' data-price="' + p.sell_price + '" data-unit="' + (p.unit || '') + '">' +
                '<div class="font-medium">' + p.name + '</div>' +
                '<div class="text-gray-400 text-xs">' + symbol + Number(p.sell_price).toLocaleString() +
                ' &middot; ' + p.stock_qty + ' ' + (p.unit || 'pcs') + ' in stock</div>' +
                '</div>'
            );
        });
        $list.show();
    }).fail(() => {
        $list.empty().hide();
    });
});

// Select product from search dropdown
$(document).on('click', '.product-result-item', function () {
    const $field = $(this).closest('.product-field');
    $field.find('.product-search').val($(this).data('name'));
    $field.find('.product-id-input').val($(this).data('id'));
    $field.find('.unit-price-input').val($(this).data('price'));
    $field.find('.product-results').empty().hide();
    calculate_line_items();
});

// ---- LINE ITEM CALCULATION ----
function calculate_line_items() {
    let subtotal = 0;
    $('.line-item-row').each(function () {
        const qty      = parseFloat($(this).find('.qty-input').val())        || 0;
        const price    = parseFloat($(this).find('.unit-price-input').val()) || 0;
        const disc_pct = parseFloat($(this).find('.discount-input').val())   || 0;
        const line     = (qty * price) * (1 - disc_pct / 100);
        const symbol   = window.Nfelia ? window.Nfelia.currency : '₦';
        $(this).find('.line-subtotal').text(symbol + line.toLocaleString(undefined, { minimumFractionDigits: 2 }));
        subtotal += line;
    });
    const tax_rate = parseFloat($('#tax-rate').val()) || 0;
    const tax_amt  = subtotal * (tax_rate / 100);
    const total    = subtotal + tax_amt;
    const symbol   = window.Nfelia ? window.Nfelia.currency : '₦';
    $('#subtotal-display').text(symbol + subtotal.toLocaleString(undefined, { minimumFractionDigits: 2 }));
    $('#tax-display').text(symbol + tax_amt.toLocaleString(undefined, { minimumFractionDigits: 2 }));
    $('#total-display').text(symbol + total.toLocaleString(undefined, { minimumFractionDigits: 2 }));
}

$(document).on('input', '.qty-input, .unit-price-input, .discount-input, #tax-rate', calculate_line_items);
