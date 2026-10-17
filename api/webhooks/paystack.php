<?php
// Bypass auth, but load the controller and execute the webhook logic
require_once __DIR__ . '/../../app/controllers/PaystackController.php';
(new PaystackController())->webhook();