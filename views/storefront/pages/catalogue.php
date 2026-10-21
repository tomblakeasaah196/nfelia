<?php
/**
 * Everything the shop sells, name.nfelia.com/shop: the list with its search
 * and categories (sections/catalogue.php), then how delivery and payment
 * work, which people want to know before they order.
 *
 * From shell.php: $sections.
 */
$section = $sections['catalogue'];
include __DIR__ . '/../sections/catalogue.php';
if ($sections['delivery']['on']) {
    $section = $sections['delivery'];
    include __DIR__ . '/../sections/delivery.php';
}
