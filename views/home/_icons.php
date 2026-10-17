<?php
/**
 * Nfelia — line icons for the public pages (24px grid, currentColor).
 * Drawn for this site, so there is no icon library to load or license.
 */

if (!function_exists('nf_icon')) {
    function nf_icon(string $name, string $class = ''): string {
        static $paths = [
            'naira'    => '<circle cx="12" cy="12" r="9"/><path d="M9 16V8l6 8V8M7 11h10M7 13.2h10"/>',
            'chart'    => '<path d="M4 20h16M7 16v-4M12 16V6M17 16v-7"/>',
            'box'      => '<path d="M21 7.5 12 3 3 7.5 12 12l9-4.5Z"/><path d="M3 7.5v9L12 21l9-4.5v-9M12 12v9"/>',
            'chat'     => '<path d="M20.5 12a8.5 8.5 0 0 1-12.4 7.5L3.5 20.5l1-4.4A8.5 8.5 0 1 1 20.5 12Z"/><path d="m8.5 12 2.5 2.5 4.5-5"/>',
            'invoice'  => '<path d="M14 3H6.5A1.5 1.5 0 0 0 5 4.5v15A1.5 1.5 0 0 0 6.5 21h11a1.5 1.5 0 0 0 1.5-1.5V8Z"/><path d="M14 3v5h5M8.5 13h7M8.5 17h4.5"/>',
            'truck'    => '<path d="M2.5 6.5h11v9.5h-11zM13.5 10h4l3 3.2V16h-7"/><circle cx="6.5" cy="17.5" r="2" fill="#fff"/><circle cx="16.5" cy="17.5" r="2" fill="#fff"/>',
            'check'    => '<path d="M20 6 9 17l-5-5"/>',
            'check-circle' => '<circle cx="12" cy="12" r="9"/><path d="m8 12.2 2.8 2.8L16 9.5"/>',
            'bell'     => '<path d="M6 16v-5a6 6 0 1 1 12 0v5l1.5 2h-15Z"/><path d="M10 21h4"/>',
            'calendar' => '<rect x="3.5" y="5" width="17" height="15.5" rx="2"/><path d="M3.5 10h17M8 3v4M16 3v4"/><path d="m9 14.8 2 2 4-4"/>',
            'quote'    => '<path d="M14 3H6.5A1.5 1.5 0 0 0 5 4.5v15A1.5 1.5 0 0 0 6.5 21h11a1.5 1.5 0 0 0 1.5-1.5V8Z"/><path d="M14 3v5h5M12 11.5v6M9 14.5h6"/>',
            'pen'      => '<path d="M15.5 4.5 19.5 8.5 9 19H5v-4Z"/><path d="M13 7l4 4M3 22h18"/>',
            'globe'    => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.6 3.8 5.6 3.8 9S14.5 18.4 12 21M12 3C9.5 5.6 8.2 8.6 8.2 12s1.3 6.4 3.8 9"/>',
            'users'    => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0M16 4.6a3.5 3.5 0 0 1 0 6.8M18 14.3a6.4 6.4 0 0 1 3.5 5.7"/>',
            'receipt'  => '<path d="M6 2.5h12v19l-3-2-3 2-3-2-3 2Z"/><path d="M9 7.5h6M9 11h6M9 14.5h3.5"/>',
            'play'     => '<path d="M8 5v14l11-7Z" fill="currentColor" stroke="none"/>',
            'close'    => '<path d="M18 6 6 18M6 6l12 12"/>',
            'video'    => '<rect x="2.5" y="6" width="13" height="12" rx="2"/><path d="m15.5 10 6-3.5v11l-6-3.5"/>',
            'whatsapp' => '<path d="M12 2.6a9.4 9.4 0 0 0-8.1 14.2L2.6 21.4l4.7-1.2A9.4 9.4 0 1 0 12 2.6Z" stroke-width="1.8"/><path d="M8.7 7.5c.25-.55.52-.56.76-.57h.65c.22 0 .48.08.62.5l.78 1.9c.08.2.07.4-.04.58l-.5.72c-.12.17-.2.33-.02.62.5.9 1.2 1.7 2 2.3.62.44 1.1.62 1.33.72.2.08.38.03.52-.13l.7-.86c.16-.2.38-.24.6-.14l1.8.86c.25.12.4.2.42.38.05.48-.17 1.2-.6 1.62-.5.5-1.38.92-2.2.85-1.4-.12-3.3-.9-4.9-2.5-1.6-1.6-2.48-3.4-2.6-4.6-.06-.9.27-1.62.68-2.25Z" fill="currentColor" stroke="none"/>',
        ];
        $body = $paths[$name] ?? '';
        $cls  = $class !== '' ? ' class="' . htmlspecialchars($class, ENT_QUOTES) . '"' : '';
        return '<svg' . $cls . ' viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $body . '</svg>';
    }
}
