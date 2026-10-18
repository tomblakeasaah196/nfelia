<?php
/**
 * Nfelia — Token & Document Number Helpers
 */

// NFELIA AUDIT FIX — C02 — next_doc_number now delegates to pure PHP generate_doc_number — 2026-06-03

/**
 * Get the next document number for a business.
 * Delegates to generate_doc_number() in functions.php (no stored procedure required).
 */
function next_doc_number(int $business_id, string $doc_type): string {
    return generate_doc_number(get_db(), $business_id, $doc_type);
}

/**
 * Build the public shareable URL for a document
 */
function doc_url(string $token): string {
    return APP_URL . '/doc/' . $token;
}

/**
 * Build the delivery sign-off URL (sent to recipient)
 */
function signoff_url(string $signoff_token): string {
    return APP_URL . '/sign/' . $signoff_token;
}
