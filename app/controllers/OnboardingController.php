<?php
/**
 * Nfelia — Onboarding Controller
 * Guides new workspaces through three setup steps here; the shop link and
 * look come fourth (ShopSetupController).
 *
 * NFELIA FIX — Country selection added to Step 1 — 2026-06-04
 * NFELIA FIX — Currency session sync in Step 2 — 2026-06-04
 */

class OnboardingController {

    private $db;
    private $biz_id;

    public function __construct() {
        if (!is_logged_in()) redirect('login');
        
        if (empty($_SESSION['csrf_token'])) {
            csrf_regenerate();
        }

        $this->db = get_db();
        $this->biz_id = auth_business_id();
        
        $biz = auth_business();
        if (($biz['onboarding_complete'] ?? 0) == 1) {
            redirect('dashboard');
        }
    }

    /**
     * Step 1: About your business (Contacts, Category & Country)
     */
    public function step1() {
        // Load active countries for the dropdown
        require_once __DIR__ . '/CountryController.php';
        $countries = CountryController::get_active_countries($this->db);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();

            $category      = in_array($_POST['category'] ?? '', array_keys(BUSINESS_CATEGORIES), true)
                             ? $_POST['category'] : 'other';
            $phone         = trim($_POST['phone'] ?? '');
            $whatsapp      = trim($_POST['whatsapp'] ?? '');
            $address       = trim($_POST['address'] ?? '');
            $tagline       = trim($_POST['tagline'] ?? '');
            $reg_num       = trim($_POST['registration_number'] ?? '');
            $country_id    = (int)($_POST['country_id'] ?? 0);
            $valid_types   = ['product', 'service', 'mixed'];
            $business_type = in_array($_POST['business_type'] ?? '', $valid_types)
                             ? $_POST['business_type'] : 'product';

            // A phone too long for its column is refused here, not lost in a
            // failed save (D4)
            if ($p = phone_problem($phone) ?? phone_problem($whatsapp)) {
                $this->back_to_step(1, $p);
            }

            // Resolve the selected country for currency sync
            $selected_country = null;
            if ($country_id) {
                foreach ($countries as $c) {
                    if ((int)$c['id'] === $country_id) { $selected_country = $c; break; }
                }
            }
            // The country sets the money and the price, so it is never guessed
            if (!$selected_country) {
                $this->back_to_step(1, 'Choose your country. It sets your money and your Nfelia price.');
            }

            try {
                $stmt = $this->db->prepare("
                    UPDATE businesses
                    SET category = ?, phone = ?, whatsapp = ?, address = ?,
                        tagline = ?, registration_number = ?,
                        business_type = ?, country_id = ?,
                        currency_symbol = ?,
                        onboarding_step = 2
                    WHERE id = ?
                ");
                $stmt->execute([
                    $category, $phone ?: null, $whatsapp ?: null,
                    $address ?: null, $tagline ?: null, $reg_num ?: null,
                    $business_type,
                    $country_id ?: null,
                    $selected_country ? $selected_country['currency_symbol'] : '₦',
                    $this->biz_id
                ]);

                // The free trial lasts as long as the chosen country's trial,
                // counted from sign-up
                if ($selected_country && (int)($selected_country['trial_days'] ?? 0) > 0) {
                    $this->db->prepare("
                        UPDATE subscriptions s JOIN businesses b ON b.id = s.business_id
                        SET s.trial_ends_at = DATE_ADD(DATE(b.created_at), INTERVAL ? DAY)
                        WHERE s.business_id = ? AND s.status = 'trial'
                    ")->execute([(int)$selected_country['trial_days'], $this->biz_id]);
                }

                // Push country + currency into the live session
                $session_updates = [
                    'onboarding_step' => 2,
                    'business_type'   => $business_type,
                    // What the documents print, without a fresh sign-in (D11)
                    'tagline'         => $tagline ?: null,
                    'address'         => $address ?: null,
                    'phone'           => $phone ?: null,
                ];
                if ($selected_country) {
                    $session_updates['country_id']      = $country_id;
                    $session_updates['currency_symbol']  = $selected_country['currency_symbol'];
                    $_SESSION['selected_country']        = $selected_country;
                }
                update_business_session($session_updates);

                redirect('onboarding/step2');

            } catch (Exception $e) {
                error_log("Onboarding Step 1 Error: " . $e->getMessage());
                $this->back_to_step(1, 'We couldn\'t save that. Please try again.');
            }
        }

        $step = 1;
        $title = "About your business";
        require_once BASE_PATH . '/views/onboarding/wizard.php';
    }

    /**
     * Step 2: Money and payments (Bank & Tax)
     */
    public function step2() {
        require_once __DIR__ . '/CountryController.php';
        $countries = CountryController::get_active_countries($this->db);

        // The currency is the country's, chosen in step 1: shown here, never
        // picked or posted. A country no longer offered keeps what is saved.
        $stmt = $this->db->prepare("SELECT country_id, currency_symbol FROM businesses WHERE id = ?");
        $stmt->execute([$this->biz_id]);
        $biz_fresh = $stmt->fetch();
        $country = null;
        foreach ($countries as $c) {
            if ((int)$c['id'] === (int)($biz_fresh['country_id'] ?? 0)) { $country = $c; break; }
        }
        $current_currency = $country['currency_symbol'] ?? ($biz_fresh['currency_symbol'] ?? '₦');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();

            // Tax between 0 and 100%
            $currency = $current_currency;
            $tax_rate = min(100, max(0, (float)($_POST['default_tax_rate'] ?? 0.00)));

            $bank_name = trim($_POST['bank_name'] ?? '');
            $acc_num   = trim($_POST['account_number'] ?? '');
            $acc_name  = trim($_POST['account_name'] ?? '');

            // The account is optional, but half of one is not saved quietly
            if (($bank_name || $acc_num || $acc_name) && !($bank_name && $acc_num && $acc_name)) {
                $this->back_to_step(2, 'Add the bank, the account number and the account name, or leave all three empty.');
            }

            try {
                $this->db->beginTransaction();

                $stmt1 = $this->db->prepare("UPDATE businesses SET currency_symbol = ?, default_tax_rate = ?, onboarding_step = 3 WHERE id = ?");
                $stmt1->execute([$currency, $tax_rate, $this->biz_id]);

                if ($bank_name && $acc_num && $acc_name) {
                    // Coming back to this step changes the account it made, not a
                    // second copy; more are added in Settings › Payment accounts
                    $kind = preg_match('/mobile money|momo|orange money|airtel money|m-?pesa|^mtn/i', $bank_name) ? 'mobile_money' : 'bank';
                    $row  = [$kind, mb_substr($bank_name, 0, 100), mb_substr($acc_num, 0, 40), mb_substr($acc_name, 0, 255)];
                    $stmt2 = $this->db->prepare("SELECT id FROM bank_accounts WHERE business_id = ? AND is_default = 1 AND deleted_at IS NULL LIMIT 1");
                    $stmt2->execute([$this->biz_id]);
                    if ($made = (int)$stmt2->fetchColumn()) {
                        $this->db->prepare("UPDATE bank_accounts SET kind = ?, bank_name = ?, account_number = ?, account_name = ? WHERE id = ?")
                                 ->execute([...$row, $made]);
                    } else {
                        $this->db->prepare("INSERT INTO bank_accounts (business_id, kind, bank_name, account_number, account_name, is_default) VALUES (?, ?, ?, ?, ?, 1)")
                                 ->execute([$this->biz_id, ...$row]);
                    }
                }

                $this->db->commit();
                
                // Push currency into session so it takes effect immediately
                update_business_session([
                    'onboarding_step'  => 3,
                    'currency_symbol'  => $currency,
                ]);
                redirect('onboarding/step3');

            } catch (Exception $e) {
                $this->db->rollBack();
                error_log("Onboarding Step 2 Error: " . $e->getMessage());
                $this->back_to_step(2, 'We couldn\'t save that. Please try again.');
            }
        }

        $step = 2;
        $title = "Money and payments";
        require_once BASE_PATH . '/views/onboarding/wizard.php';
    }

    /**
     * Step 3: How your business looks (Brand Colors, Font, Logo)
     */
    public function step3() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();

            // Anything but a #rrggbb colour or a known font keeps what the
            // business has (the burgundy and gold it got at sign-up)
            $biz       = auth_business();
            $hex       = fn(string $v, string $now) => preg_match('/^#[0-9A-Fa-f]{6}$/', $v) ? $v : $now;
            $primary   = $hex(trim($_POST['primary_color'] ?? ''), $biz['primary_color'] ?? '#8B004A');
            $secondary = $hex(trim($_POST['secondary_color'] ?? ''), $biz['secondary_color'] ?? '#B69F5E');
            $font      = in_array($_POST['font_pairing'] ?? '', array_keys(FONT_PAIRINGS), true)
                         ? $_POST['font_pairing'] : ($biz['font_pairing'] ?? 'modern_sans');

            $logo_path = null;
            $logo_failed = false;

            try {
                $logo_error = $_FILES['logo']['error'] ?? UPLOAD_ERR_NO_FILE;
                if ($logo_error === UPLOAD_ERR_OK) {
                    $dir = UPLOAD_PATH . '/logos/' . $this->biz_id;
                    if (!is_dir($dir)) {
                        mkdir($dir, 0775, true);
                    }
                    
                    $filename = 'logo_' . time() . '.png';
                    $dest = $dir . '/' . $filename;
                    
                    if (save_image($_FILES['logo'], $dest, 600)) {
                        $logo_path = 'uploads/logos/' . $this->biz_id . '/' . $filename;
                    } else {
                        $logo_failed = true;
                    }
                } elseif ($logo_error !== UPLOAD_ERR_NO_FILE) {
                    $logo_failed = true;   // too big for the server, or cut off
                }

                $sql = "UPDATE businesses SET primary_color = ?, secondary_color = ?, font_pairing = ?, onboarding_step = 4, onboarding_complete = 1";
                $params = [$primary, $secondary, $font];

                if ($logo_path) {
                    $sql .= ", logo_path = ?";
                    $params[] = $logo_path;
                }
                
                $sql .= " WHERE id = ?";
                $params[] = $this->biz_id;

                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);

                $session_updates = [
                    'primary_color'       => $primary,
                    'secondary_color'     => $secondary,
                    'font_pairing'        => $font,
                    'onboarding_complete' => 1
                ];
                
                if ($logo_path) {
                    $session_updates['logo_path'] = $logo_path;
                }

                update_business_session($session_updates);
                $_SESSION['just_signed_in'] = true; // offer the home-screen app on the first page

                // The shop link and look are the last step (ShopSetupController)
                $next = is_owner() && shop_setup_needed() ? 'shop-setup' : 'dashboard';
                if ($logo_failed) {
                    flash('warning', 'Your logo didn\'t upload. Use a JPG, PNG or WebP picture up to 20MB. You can add it any time in Settings.');
                } else {
                    flash('success', $next === 'shop-setup' ? 'Saved. One last step: your shop link and look.' : 'All set. Welcome to Nfelia.');
                }
                redirect($next);

            } catch (Exception $e) {
                error_log("Onboarding Step 3 Error: " . $e->getMessage());
                flash('error', 'We couldn\'t save that. Please try again.');
                redirect('onboarding/step3');
            }
        }

        $step = 3;
        $title = "How your business looks";
        require_once BASE_PATH . '/views/onboarding/wizard.php';
    }

    /**
     * Back to a step with a message and what the person typed, so a mistake
     * does not mean filling the form again (the wizard puts it back).
     */
    private function back_to_step(int $step, string $message): never {
        $_SESSION['onboarding_old'] = array_map('strval', array_filter(
            array_diff_key($_POST, ['csrf_token' => 1]), 'is_scalar'
        ));
        flash('error', $message);
        redirect('onboarding/step' . $step);
    }
}
