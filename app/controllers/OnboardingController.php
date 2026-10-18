<?php
/**
 * Nfelia — Onboarding Controller
 * Guides new workspaces through the 3-step setup process.
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
     * Step 1: Operational Profile (Contacts, Category & Country)
     */
    public function step1() {
        // Load active countries for the dropdown
        require_once __DIR__ . '/CountryController.php';
        $countries = CountryController::get_active_countries($this->db);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();

            $category      = $_POST['category'] ?? 'other';
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
                flash('error', $p);
                redirect('onboarding/step1');
            }

            // Resolve the selected country for currency sync
            $selected_country = null;
            if ($country_id) {
                foreach ($countries as $c) {
                    if ((int)$c['id'] === $country_id) { $selected_country = $c; break; }
                }
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
                flash('error', 'Failed to save operational profile. Please try again.');
                redirect('onboarding/step1');
            }
        }

        $step = 1;
        $title = "Operational Profile";
        require_once BASE_PATH . '/views/onboarding/wizard.php';
    }

    /**
     * Step 2: Financial Infrastructure (Bank & Tax)
     */
    public function step2() {
        // Load countries for the currency dropdown + pre-select from step 1
        require_once __DIR__ . '/CountryController.php';
        $countries = CountryController::get_active_countries($this->db);

        // Read the country saved in step 1
        $stmt = $this->db->prepare("SELECT country_id, currency_symbol FROM businesses WHERE id = ?");
        $stmt->execute([$this->biz_id]);
        $biz_fresh = $stmt->fetch();
        $current_currency = $biz_fresh['currency_symbol'] ?? '₦';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();

            $currency = trim($_POST['currency_symbol'] ?? '₦');
            $tax_rate = (float)($_POST['default_tax_rate'] ?? 0.00);
            
            $bank_name = trim($_POST['bank_name'] ?? '');
            $acc_num   = trim($_POST['account_number'] ?? '');
            $acc_name  = trim($_POST['account_name'] ?? '');

            try {
                $this->db->beginTransaction();

                $stmt1 = $this->db->prepare("UPDATE businesses SET currency_symbol = ?, default_tax_rate = ?, onboarding_step = 3 WHERE id = ?");
                $stmt1->execute([$currency, $tax_rate, $this->biz_id]);

                if ($bank_name && $acc_num && $acc_name) {
                    $stmt2 = $this->db->prepare("INSERT INTO bank_accounts (business_id, bank_name, account_number, account_name, is_default) VALUES (?, ?, ?, ?, 1)");
                    $stmt2->execute([$this->biz_id, $bank_name, $acc_num, $acc_name]);
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
                flash('error', 'Failed to save financial details.');
                redirect('onboarding/step2');
            }
        }

        $step = 2;
        $title = "Financial Infrastructure";
        require_once BASE_PATH . '/views/onboarding/wizard.php';
    }

    /**
     * Step 3: Aesthetic & Deployment (Brand Colors, Font, Logo)
     */
    public function step3() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();

            $primary   = trim($_POST['primary_color'] ?? '#1A1A2E');
            $secondary = trim($_POST['secondary_color'] ?? '#C27A62');
            $font      = $_POST['font_pairing'] ?? 'modern_sans';
            
            $logo_path = null;

            try {
                if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                    $dir = UPLOAD_PATH . '/logos/' . $this->biz_id;
                    if (!is_dir($dir)) {
                        mkdir($dir, 0775, true);
                    }
                    
                    $filename = 'logo_' . time() . '.png';
                    $dest = $dir . '/' . $filename;
                    
                    if (save_image($_FILES['logo'], $dest, 600)) {
                        $logo_path = 'uploads/logos/' . $this->biz_id . '/' . $filename;
                    }
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

                flash('success', 'Deployment complete. Welcome to your workspace.');
                redirect('dashboard');

            } catch (Exception $e) {
                error_log("Onboarding Step 3 Error: " . $e->getMessage());
                flash('error', 'System error during aesthetic deployment. Please try again.');
                redirect('onboarding/step3');
            }
        }

        $step = 3;
        $title = "Aesthetic Deployment";
        require_once BASE_PATH . '/views/onboarding/wizard.php';
    }
}
