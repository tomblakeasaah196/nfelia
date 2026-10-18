<?php
/**
 * Email campaigns (Admins and Sales): one email to a group of leads or
 * clients, sent a few at a time (app/ops/sales.php).
 *
 *   GET  campaigns                 every campaign, newest first
 *   GET  campaigns/new             write one
 *   POST campaigns                 save it as a draft
 *   GET  campaigns/{id}            a draft: who it reaches and how it looks; a sent one: how it went
 *   POST campaigns/{id}            change a draft
 *   POST campaigns/{id}/test       send it to yourself
 *   POST campaigns/{id}/send       send it to everyone it reaches (once)
 */
class OpsCampaignsController
{
    private PDO $db;
    private array $me;

    public function __construct()
    {
        $this->db = get_db();
        $this->me = ops_staff();
    }

    public function index(): void
    {
        $rows = $this->db->query("
            SELECT c.*, s.full_name AS author,
                   (SELECT COUNT(*) FROM campaign_sends cs WHERE cs.campaign_id = c.id) AS people,
                   (SELECT COUNT(*) FROM campaign_sends cs LEFT JOIN notify_outbox o ON o.id = cs.outbox_id WHERE cs.campaign_id = c.id AND COALESCE(o.status, cs.status) = 'sent') AS delivered
            FROM campaigns c LEFT JOIN ops_staff s ON s.id = c.created_by ORDER BY c.id DESC LIMIT 100")->fetchAll();
        ops_render('campaigns/index', ['rows' => $rows], 'Campaigns');
    }

    public function create_form(): void
    {
        ops_render('campaigns/form', ['c' => ['id' => null, 'name' => '', 'subject' => '', 'body' => "Hello {first_name},\n\n", 'button_text' => '', 'button_url' => '', 'audience' => '{}'],
                                      'narrow' => true], 'New campaign');
    }

    public function create(): void
    {
        $f = $this->fields();
        if ($problem = $this->problem($f)) {
            ops_flash('error', $problem);
            ops_redirect('campaigns/new');
        }
        $this->db->prepare("INSERT INTO campaigns (name, subject, body, button_text, button_url, audience, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)")
           ->execute([$f['name'], $f['subject'], $f['body'], $f['button_text'], $f['button_url'], $f['audience'], $this->me['id']]);
        $id = (int)$this->db->lastInsertId();
        ops_audit('campaign.created', 'Wrote the campaign "' . $f['name'] . '"', ['type' => 'campaign', 'id' => $id]);
        ops_redirect('campaigns/' . $id);
    }

    public function show(string $id): void
    {
        $c = $this->campaign((int)$id);
        if ($c['status'] === 'draft' && ($_GET['edit'] ?? '') === '1') {
            ops_render('campaigns/form', ['c' => $c, 'narrow' => true], 'Edit ' . $c['name']);
        }
        $people = $c['status'] === 'draft' ? ops_campaign_people($this->db, json_decode($c['audience'], true) ?: []) : [];
        $sample = $people ? reset($people) : ['email' => $this->me['email'], 'name' => $this->me['full_name'], 'business' => 'Mama Put Kitchen'];
        $preview = render_email('ops_campaign', ops_campaign_vars($c, $sample, APP_URL . '/unsubscribe/preview'));
        $stats = [];
        $sends = [];
        if ($c['status'] !== 'draft') {
            $stats = $this->db->query("
                SELECT COUNT(*) AS people, SUM(cs.status = 'waiting') AS waiting, SUM(COALESCE(o.status, cs.status) = 'sent') AS delivered,
                       SUM(COALESCE(o.status, cs.status) = 'failed') AS failed, SUM(o.status IN ('pending', 'sending')) AS sending, SUM(cs.status = 'skipped') AS skipped
                FROM campaign_sends cs LEFT JOIN notify_outbox o ON o.id = cs.outbox_id WHERE cs.campaign_id = " . (int)$c['id'])->fetch();
            $sends = $this->db->query("SELECT cs.*, COALESCE(o.status, cs.status) AS mail_status, o.last_error FROM campaign_sends cs LEFT JOIN notify_outbox o ON o.id = cs.outbox_id
                                       WHERE cs.campaign_id = " . (int)$c['id'] . " ORDER BY cs.id LIMIT 300")->fetchAll();
            $stats['unsubscribed'] = (int)$this->db->query("SELECT COUNT(*) FROM email_opt_outs WHERE campaign_id = " . (int)$c['id'])->fetchColumn();
        }
        ops_render('campaigns/show', ['c' => $c, 'people' => $people, 'preview' => $preview, 'stats' => $stats, 'sends' => $sends], $c['name']);
    }

    public function update(string $id): void
    {
        $c = $this->campaign((int)$id);
        if ($c['status'] !== 'draft') {
            ops_flash('error', 'This campaign has been sent; it can no longer be changed.');
            ops_redirect('campaigns/' . $c['id']);
        }
        $f = $this->fields();
        if ($problem = $this->problem($f)) {
            ops_flash('error', $problem);
            ops_redirect('campaigns/' . $c['id'] . '?edit=1');
        }
        $this->db->prepare("UPDATE campaigns SET name = ?, subject = ?, body = ?, button_text = ?, button_url = ?, audience = ? WHERE id = ? AND status = 'draft'")
           ->execute([$f['name'], $f['subject'], $f['body'], $f['button_text'], $f['button_url'], $f['audience'], $c['id']]);
        ops_flash('success', 'Saved.');
        ops_redirect('campaigns/' . $c['id']);
    }

    public function test(string $id): void
    {
        $c = $this->campaign((int)$id);
        $vars = ops_campaign_vars($c, ['name' => $this->me['full_name'], 'business' => 'Mama Put Kitchen'], APP_URL . '/unsubscribe/preview');
        $out = notify_email(null, $this->me['email'], $this->me['full_name'], '[Test] ' . $vars['subject'], 'ops_campaign', $vars, null, 'campaign.test');
        $done = $out ? notify_flush([$out]) : ['sent' => 0];
        ops_flash($done['sent'] ? 'success' : 'error', $done['sent'] ? 'A test is on its way to ' . $this->me['email'] . '.' : 'The test could not be sent. Health → Failed sends says why.');
        ops_redirect('campaigns/' . $c['id']);
    }

    public function send(string $id): void
    {
        $c = $this->campaign((int)$id);
        $people = ops_campaign_people($this->db, json_decode($c['audience'], true) ?: []);
        if (!$people) {
            ops_flash('error', 'Nobody to send it to: choose who it goes to.');
            ops_redirect('campaigns/' . $c['id']);
        }
        // Once: whoever presses Send first sends it.
        $claim = $this->db->prepare("UPDATE campaigns SET status = 'sending', sent_at = NOW(), sent_by = ? WHERE id = ? AND status = 'draft'");
        $claim->execute([$this->me['id'], $c['id']]);
        if ($claim->rowCount() !== 1) {
            ops_flash('error', 'This campaign was already sent.');
            ops_redirect('campaigns/' . $c['id']);
        }
        $add = $this->db->prepare("INSERT IGNORE INTO campaign_sends (campaign_id, email, name, business, lead_id, business_id, token) VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($people as $p) {
            $add->execute([$c['id'], $p['email'], $p['name'], $p['business'], $p['lead_id'], $p['business_id'], bin2hex(random_bytes(16))]);
        }
        $n = count($people);
        $hours = (int)ceil($n / campaign_per_hour());
        ops_audit('campaign.sent', 'Sent the campaign "' . $c['name'] . '" to ' . $n . ' ' . ($n === 1 ? 'person' : 'people'), ['type' => 'campaign', 'id' => (int)$c['id']]);
        ops_flash('success', 'Sending to ' . $n . ' ' . ($n === 1 ? 'person' : 'people') . ($hours > 1 ? ', over about ' . $hours . ' hours' : ' within the hour') . ', a few every 5 minutes.');
        ops_redirect('campaigns/' . $c['id']);
    }

    /* ── Shared ───────────────────────────────────────────── */

    private function campaign(int $id): array
    {
        $stmt = $this->db->prepare("SELECT c.*, s.full_name AS author, t.full_name AS sender FROM campaigns c LEFT JOIN ops_staff s ON s.id = c.created_by LEFT JOIN ops_staff t ON t.id = c.sent_by WHERE c.id = ?");
        $stmt->execute([$id]);
        $c = $stmt->fetch();
        if (!$c) ops_not_found();
        return $c;
    }

    private function fields(): array
    {
        $url = trim((string)($_POST['button_url'] ?? ''));
        return [
            'name'        => mb_substr(trim((string)($_POST['name'] ?? '')), 0, 120),
            'subject'     => mb_substr(trim((string)($_POST['subject'] ?? '')), 0, 160),
            'body'        => mb_substr(trim((string)($_POST['body'] ?? '')), 0, 20000),
            'button_text' => mb_substr(trim((string)($_POST['button_text'] ?? '')), 0, 60) ?: null,
            'button_url'  => $url !== '' && preg_match('#^https?://#', $url) ? mb_substr($url, 0, 255) : null,
            'audience'    => json_encode([
                'leads'   => array_values(array_intersect((array)($_POST['leads'] ?? []), array_keys(LEAD_STAGES))),
                'clients' => array_values(array_intersect((array)($_POST['clients'] ?? []), array_keys(CAMPAIGN_CLIENT_GROUPS))),
            ]),
        ];
    }

    private function problem(array $f): ?string
    {
        if ($f['name'] === '' || $f['subject'] === '' || mb_strlen($f['body']) < 10) {
            return 'Give it a name, a subject, and a message of a few words at least.';
        }
        if (($f['button_text'] === null) !== ($f['button_url'] === null)) {
            return 'A button needs both its words and a web address starting with https://.';
        }
        return null;
    }
}
