<?php
/**
 * Off camera, halfway through tutorial 2: the rest of Adanna's catalogue,
 * "added the same way" as the bundle made on camera.
 */
require __DIR__ . '/../adanna.php';
t_guard();
adanna_products(get_db(), ['bone']);
