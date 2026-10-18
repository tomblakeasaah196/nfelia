<?php
/**
 * More — the fifth tab. Every place in the app that isn't a tab of its own,
 * and the account: settings, billing, help, sign out.
 */
class MoreController {

    public function index() {
        $title     = 'More';
        $view_file = BASE_PATH . '/views/more/index.php';
        require_once BASE_PATH . '/views/layouts/app.php';
    }
}
