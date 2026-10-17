<?php
/**
 * Nfelia — Onboarding Controller
 * Guides new workspaces through the 3-step setup process.
 */

class OnboardingController {

    private $db;
    private $biz_id;

    public function __construct() {
        // Double-check auth and CSRF
        if (!is_logged_in()) redirect('login');
        
        if (empty($_SESSION['csrf_token'])) {
            csrf_regenerate(); // Using the global helper
        }

        $this->db = get_db();
        $this->biz_id = auth_business_id();
        
        // If they somehow hit this but are already complete, boot them to dashboard
        $biz = auth_business();
        if (($biz['onboarding_complete'] ?? 0) == 1) {
            redirect('dashboard');
        }
    }

    /**
     * Step 1: Operational Profile (Contacts & Category)
     */
    public function step1() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify(); // Using the global helper

            $category      = $_POST['category'] ?? 'other';
            $phone         = trim($_POST['phone'] ?? '');
            $whatsapp      = trim($_POST['whatsapp'] ?? '');
            $address       = trim($_POST['address'] ?? '');
            $tagline       = trim($_POST['tagline'] ?? '');
            $reg_num       = trim($_POST['registration_number'] ?? '');
            $valid_types   = ['product', 'service', 'mixed'];
            $business_type = in_array($_POST['business_type'] ?? '', $valid_types)
                             ? $_POST['business_type'] : 'product';

            try {
                $stmt = $this->db->prepare("
                    UPDATE businesses
                    SET category = ?, phone = ?, whatsapp = ?, address = ?,
                        tagline = ?, registration_number = ?,
                        business_type = ?, onboarding_step = 2
                    WHERE id = ?
                ");
                $stmt->execute([$category, $phone ?: null, $whatsapp ?: null,
                                $address ?: null, $tagline ?: null, $reg_num ?: null,
                                $business_type, $this->biz_id]);

                // Update Session using the global helper
                update_business_session(['onboarding_step' => 2, 'business_type' => $business_type]);
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
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();

            $currency = trim($_POST['currency_symbol'] ?? '₦');
            $tax_rate = (float)($_POST['default_tax_rate'] ?? 0.00);
            
            $bank_name = trim($_POST['bank_name'] ?? '');
            $acc_num   = trim($_POST['account_number'] ?? '');
            $acc_name  = trim($_POST['account_name'] ?? '');

            try {
                $this->db->beginTransaction();

                // Update business financial defaults
                $stmt1 = $this->db->prepare("UPDATE businesses SET currency_symbol = ?, default_tax_rate = ?, onboarding_step = 3 WHERE id = ?");
                $stmt1->execute([$currency, $tax_rate, $this->biz_id]);

                // Insert the primary bank account
                if ($bank_name && $acc_num && $acc_name) {
                    $stmt2 = $this->db->prepare("INSERT INTO bank_accounts (business_id, bank_name, account_number, account_name, is_default) VALUES (?, ?, ?, ?, 1)");
                    $stmt2->execute([$this->biz_id, $bank_name, $acc_num, $acc_name]);
                }

                $this->db->commit();
                
                // Update Session using the global helper
                update_business_session(['onboarding_step' => 3, 'currency_symbol' => $currency]);
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
                // Handle Logo Upload
                if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                    $dir = UPLOAD_PATH . '/logos/' . $this->biz_id;
                    if (!is_dir($dir)) {
                        mkdir($dir, 0775, true);
                    }
                    
                    // Use the save_image helper
                    $filename = 'logo_' . time() . '.png';
                    $dest = $dir . '/' . $filename;
                    
                    if (save_image($_FILES['logo'], $dest, 600)) {
                        $logo_path = 'uploads/logos/' . $this->biz_id . '/' . $filename;
                    }
                }

                // Final Update
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

                // Build session update array
                $session_updates = [
                    'primary_color'       => $primary,
                    'secondary_color'     => $secondary,
                    'font_pairing'        => $font,
                    'onboarding_complete' => 1
                ];
                
                if ($logo_path) {
                    $session_updates['logo_path'] = $logo_path;
                }

                // Update Session completely using the global helper
                update_business_session($session_updates);

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