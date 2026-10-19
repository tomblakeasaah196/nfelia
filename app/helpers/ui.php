<?php
/**
 * Nfelia — small pieces of screen shared by every page.
 *
 * nf_info() puts an (i) after a word or figure. Tapping it opens one small
 * sheet (#nf-info in views/layouts/app.php, filled by assets/js/shell.js)
 * with the longer explanation, so the page itself stays short.
 */

/** An (i) button that explains $title in $text (plain text, one to three sentences). */
function nf_info(string $title, string $text): string
{
    return '<button type="button" class="nf-info" data-info-title="' . e($title) . '" data-info="' . e($text) . '"'
         . ' aria-label="' . e('About ' . lcfirst($title)) . '" aria-haspopup="dialog">' . nav_icon('info') . '</button>';
}
