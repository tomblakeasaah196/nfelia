<?php
/**
 * Nfelia — Deed of Acquisition Controller
 *
 * Routes:
 *   POST /deed/create            — Create deed from invoice or receipt (authenticated)
 *   GET  /deed/{token}           — Public branded deed view page
 *   GET  /deed/download/{token}  — Stream PDF via dompdf
 */

use Dompdf\Dompdf;
use Dompdf\Options;

class DeedController {

    private $db;

    public function __construct() {
        $this->db = get_db();
    }

    /* ══════════════════════════════════════════
     *  CREATE — POST /deed/create
     *  Called from invoice/receipt view pages.
     * ══════════════════════════════════════════ */
    public function create() {
        if (!is_logged_in()) { redirect('login'); return; }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('dashboard'); return; }
        csrf_verify();

        $biz_id    = auth_business_id();
        $source    = trim($_POST['source'] ?? '');  // 'invoice' or 'receipt'
        $source_id = (int)($_POST['source_id'] ?? 0);
        $provenance_text = trim($_POST['provenance_text'] ?? '');

        if (!$source_id || !in_array($source, ['invoice', 'receipt'])) {
            flash('error', 'Invalid deed source.');
            redirect('dashboard');
            return;
        }

        // Fetch the source document + its items
        if ($source === 'invoice') {
            $stmt = $this->db->prepare("SELECT * FROM invoices WHERE id = ? AND business_id = ?");
            $stmt->execute([$source_id, $biz_id]);
            $doc = $stmt->fetch();
            if (!$doc) { not_found(); return; }

            $stmt = $this->db->prepare(
                "SELECT ii.description, ii.quantity, ii.unit_price, ii.subtotal
                 FROM invoice_items ii WHERE ii.invoice_id = ?"
            );
            $stmt->execute([$source_id]);
            $items      = $stmt->fetchAll();
            $customer_id = $doc['customer_id'];
            $total       = (float)$doc['total'];
            $invoice_id  = $source_id;
            $receipt_id  = null;
        } else {
            $stmt = $this->db->prepare("SELECT * FROM receipts WHERE id = ? AND business_id = ?");
            $stmt->execute([$source_id, $biz_id]);
            $doc = $stmt->fetch();
            if (!$doc) { not_found(); return; }

            // Receipts: items from receipt_items if standalone, or invoice_items if linked
            if ($doc['invoice_id']) {
                $stmt = $this->db->prepare(
                    "SELECT ii.description, ii.quantity, ii.unit_price, ii.subtotal
                     FROM invoice_items ii WHERE ii.invoice_id = ?"
                );
                $stmt->execute([$doc['invoice_id']]);
                $items = $stmt->fetchAll();
            } else {
                $stmt = $this->db->prepare(
                    "SELECT ri.description, ri.quantity, ri.unit_price, ri.subtotal
                     FROM receipt_items ri WHERE ri.receipt_id = ?"
                );
                $stmt->execute([$source_id]);
                $items = $stmt->fetchAll();
            }
            $customer_id = $doc['customer_id'];
            $total       = (float)$doc['amount'];
            $invoice_id  = $doc['invoice_id'] ?? null;
            $receipt_id  = $source_id;
        }

        // Fetch business + customer context
        $business = $this->get_business($biz_id);
        // A walk-in sale has nobody to give a deed to.
        if (!$customer_id) { flash('error', 'A deed needs a customer. Add the customer to the receipt first.'); redirect('dashboard'); return; }
        $customer = $this->get_customer((int)$customer_id, $biz_id);
        if (!$customer) { flash('error', 'Client not found.'); redirect('dashboard'); return; }

        // If provenance_text is blank, pull from the first product's provenance_text (if set)
        if ($provenance_text === '' && !empty($items)) {
            $first_desc = $items[0]['description'] ?? '';
            $stmt = $this->db->prepare(
                "SELECT provenance_text FROM products
                 WHERE business_id = ? AND name LIKE ? AND provenance_text IS NOT NULL LIMIT 1"
            );
            $stmt->execute([$biz_id, '%' . $first_desc . '%']);
            $prod = $stmt->fetch();
            if ($prod) $provenance_text = $prod['provenance_text'];
        }

        $currency_symbol = $business['currency_symbol'] ?? '₦';
        $currency_code   = $_SESSION['selected_country']['currency_code'] ?? 'NGN';
        $line_items_json = json_encode($items);
        $token           = generate_token();
        $doc_number      = generate_doc_number($this->db, $biz_id, 'DEA');

        try {
            $this->db->prepare("
                INSERT INTO deeds
                    (business_id, invoice_id, receipt_id, customer_id, doc_number, token,
                     issued_at, provenance_text, line_items, total_amount, currency_symbol, currency_code)
                VALUES (?, ?, ?, ?, ?, ?, CURDATE(), ?, ?, ?, ?, ?)
            ")->execute([
                $biz_id, $invoice_id, $receipt_id, $customer_id,
                $doc_number, $token,
                $provenance_text ?: null,
                $line_items_json,
                $total, $currency_symbol, $currency_code,
            ]);

            // The customer's copy, through the outbox: sent after this page
            // answers, retried if the mail server is down. Only when there
            // is an address to send it to, and the message says so.
            $emailed = notify_email((int)$biz_id, (string)($customer['email'] ?? ''), (string)$customer['full_name'],
                'Your Deed of Acquisition — ' . $doc_number, 'deed_issued', [
                    'client_name'     => $customer['full_name'],
                    'biz_name'        => $business['name'],
                    'doc_number'      => $doc_number,
                    'deed_url'        => APP_URL . '/deed/' . $token,
                    'total'           => $total,
                    'currency_symbol' => $currency_symbol,
                    'item_names'      => array_map(fn($i) => $i['description'], $items),
                ], 'deed:' . $token, 'customer.deed');

            flash('success', 'Deed of Acquisition ' . $doc_number . ' issued.'
                . ($emailed ? ' A copy is on its way to ' . $customer['email'] . '.' : ' The customer has no email address: share the link with them.'));
            redirect('deed/' . $token);

        } catch (\PDOException $e) {
            error_log('[Nfelia] Deed create failed: ' . $e->getMessage());
            flash('error', 'Failed to create deed. Please try again.');
            redirect($source === 'invoice' ? 'invoices/view/' . $source_id : 'receipts/view/' . $source_id);
        }
    }

    /* ══════════════════════════════════════════
     *  PUBLIC VIEW — GET /deed/{token}
     *  Beautiful branded deed page for the client.
     * ══════════════════════════════════════════ */
    public function deed($token = null) {
        if (!$token) { not_found(); return; }

        $data = $this->fetch_deed_data($token);
        if (!$data) { not_found(); return; }

        ['deed' => $deed, 'business' => $business, 'customer' => $customer] = $data;
        $items = json_decode($deed['line_items'], true) ?: [];

        $title     = e($deed['doc_number']) . ' — Deed of Acquisition';
        $view_file = BASE_PATH . '/views/deeds/view.php';
        require_once BASE_PATH . '/views/deeds/view.php';
    }

    /* ══════════════════════════════════════════
     *  DOWNLOAD PDF — GET /deed/download/{token}
     * ══════════════════════════════════════════ */
    public function download($token = null) {
        if (!$token) { not_found(); return; }

        $data = $this->fetch_deed_data($token);
        if (!$data) { not_found(); return; }

        ['deed' => $deed, 'business' => $business, 'customer' => $customer] = $data;
        $items = json_decode($deed['line_items'], true) ?: [];

        ob_start();
        require BASE_PATH . '/views/deeds/pdf_template.php';
        $html = ob_get_clean();

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Georgia');
        $options->set('tempDir', BASE_PATH . '/storage/pdf');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = 'Deed_' . $deed['doc_number'] . '.pdf';
        $dompdf->stream($filename, ['Attachment' => true]);
        exit;
    }

    /* ══════════════════════════════════════════
     *  PRIVATE HELPERS
     * ══════════════════════════════════════════ */

    private function fetch_deed_data(string $token): ?array {
        $stmt = $this->db->prepare("SELECT * FROM deeds WHERE token = ? LIMIT 1");
        $stmt->execute([$token]);
        $deed = $stmt->fetch();
        if (!$deed) return null;

        $business = $this->get_business($deed['business_id']);
        $customer = $this->get_customer($deed['customer_id'], $deed['business_id']);

        return compact('deed', 'business', 'customer');
    }

    private function get_business(int $id): array {
        $stmt = $this->db->prepare("SELECT * FROM businesses WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: [];
    }

    private function get_customer(int $id, int $biz_id): ?array {
        $stmt = $this->db->prepare(
            "SELECT * FROM customers WHERE id = ? AND business_id = ?"
        );
        $stmt->execute([$id, $biz_id]);
        return $stmt->fetch() ?: null;
    }
}
