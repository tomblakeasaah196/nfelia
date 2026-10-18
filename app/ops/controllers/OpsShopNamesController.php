<?php
/**
 * Shop names waiting for a look (Admin and Support): names that look like a
 * bank, telco, government body or an official account wait here instead of
 * going live (shop_name_needs_review() in app/helpers/shop_links.php).
 *
 *   GET  shop-names
 *   POST shop-names/{business id}   approve or turn down the name it asked for
 */
class OpsShopNamesController
{
    public function index(): void
    {
        $rows = get_db()->query("
            SELECT b.id, b.name, b.category, b.email, b.phone, b.created_at, b.subdomain, b.subdomain_requested, b.subdomain_requested_at
            FROM businesses b
            WHERE b.subdomain_status = 'pending' AND b.subdomain_requested IS NOT NULL
            ORDER BY b.subdomain_requested_at ASC
        ")->fetchAll();
        ops_render('shop_names', ['rows' => $rows], 'Shop names');
    }

    public function decide(string $id): void
    {
        $db = get_db();
        $biz_id   = (int)$id;
        $name     = (string)($_POST['name'] ?? '');
        $decision = (string)($_POST['decision'] ?? '');
        $stmt = $db->prepare("SELECT id, name, subdomain, subdomain_requested, subdomain_status FROM businesses WHERE id = ?");
        $stmt->execute([$biz_id]);
        $biz = $stmt->fetch();
        // The owner may have picked another name since this page loaded.
        if (!$biz || $biz['subdomain_status'] !== 'pending' || $biz['subdomain_requested'] !== $name || !in_array($decision, ['approve', 'reject'], true)) {
            ops_flash('warn', 'That request changed since the page loaded. This is the current list.');
            ops_redirect('shop-names');
        }
        $host      = $name . '.' . shop_base_host();
        $is_rename = $biz['subdomain'] !== null;

        if ($decision === 'reject') {
            // A shop with a live link keeps it; one without goes back to setup.
            $db->prepare("UPDATE businesses SET subdomain_requested = NULL, subdomain_status = ?, subdomain_requested_at = NULL,
                                 subdomain_reviewed_at = NOW(), subdomain_reviewed_by = NULL
                          WHERE id = ? AND subdomain_status = 'pending' AND subdomain_requested = ?")
               ->execute([$is_rename ? 'active' : 'rejected', $biz_id, $name]);
            notify('shop.link_refused', $biz_id, ['host' => $host, 'has_current' => $is_rename], ['dedupe' => 'refused:' . $name . ':' . time()]);
            ops_audit('shop_name.rejected', 'Turned down ' . $host . ' for ' . $biz['name'], ['business' => $biz_id, 'type' => 'business', 'id' => $biz_id]);
            ops_flash('success', $host . ' was turned down. ' . $biz['name'] . ' has been told.');
            ops_redirect('shop-names');
        }

        // The rules may have changed since the request, so check once more.
        $check = shop_name_check($name, $biz_id);
        if (!in_array($check['state'], ['ok', 'review'], true)) {
            ops_flash('error', $host . ' can\'t be used: ' . $check['message']);
            ops_redirect('shop-names');
        }
        try {
            $db->beginTransaction();
            $db->prepare("UPDATE businesses SET subdomain = ?, subdomain_renames = subdomain_renames + ?, subdomain_requested = NULL, subdomain_status = 'active',
                                 subdomain_requested_at = NULL, subdomain_reviewed_at = NOW(), subdomain_reviewed_by = NULL
                          WHERE id = ? AND subdomain_status = 'pending' AND subdomain_requested = ?")
               ->execute([$name, $is_rename ? 1 : 0, $biz_id, $name]);
            if ($is_rename) {
                shop_name_hold($biz_id, $biz['subdomain']);   // the old name keeps sending people to the shop for 90 days
            }
            shop_name_release($biz_id, $name);
            $db->commit();
        } catch (PDOException $e) {
            if ($db->inTransaction()) $db->rollBack();
            if ($e->getCode() !== '23000') throw $e;
            ops_flash('error', 'Another shop already has ' . $host . '.');
            ops_redirect('shop-names');
        }
        storefront_purge($biz_id, $is_rename ? [$biz['subdomain']] : []);
        notify('shop.link_live', $biz_id, ['host' => $host, 'changed' => $is_rename], ['dedupe' => 'live:' . $name]);
        ops_audit('shop_name.approved', 'Approved ' . $host . ' for ' . $biz['name'], ['business' => $biz_id, 'type' => 'business', 'id' => $biz_id]);
        ops_flash('success', $host . ' is live for ' . $biz['name'] . '.');
        ops_redirect('shop-names');
    }
}
