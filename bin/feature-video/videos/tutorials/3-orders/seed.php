<?php
/**
 * Tutorial 3 (orders, payments and delivery): Adanna Luxe Hair with its full
 * catalogue from video 2 and no sales yet. Chiamaka orders from the shop and
 * Funke on WhatsApp during the video, so neither exists before it.
 */
require __DIR__ . '/../adanna.php';
$db  = adanna_reset(12);
$ids = adanna_products($db);
echo json_encode(['body' => $ids['body']]), "\n";
