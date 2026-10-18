<?php
/**
 * Nfelia — News and offers (owner side)
 *
 * Short posts a shop publishes: a new arrival, an offer, opening hours for
 * the holidays. Each published post gets its own page on the shop
 * (name.nfelia.com/news/{id}-{title}), shows in the shop's news section, and
 * goes in the shop's sitemap for Google. Drafts stay here.
 *
 *   GET       /posts              the list
 *   GET|POST  /posts/create       write one
 *   GET|POST  /posts/edit/{id}    change one
 *   POST      /posts/delete/{id}  delete one
 */
class PostController
{
    private $db;
    private $biz_id;

    public function __construct()
    {
        if (!is_logged_in()) {
            redirect('login');
        }
        $this->db = get_db();
        $this->biz_id = (int)auth_business_id();
        require_boss('post news and offers');
    }

    public function index()
    {
        $user     = auth_user();
        $business = auth_business();
        $stmt = $this->db->prepare("
            SELECT id, title, status, image_path, published_at, updated_at
            FROM shop_posts WHERE business_id = ? AND deleted_at IS NULL
            ORDER BY COALESCE(published_at, updated_at) DESC, id DESC
        ");
        $stmt->execute([$this->biz_id]);
        $posts     = $stmt->fetchAll();
        $title     = 'News and offers';
        $view_file = BASE_PATH . '/views/posts/index.php';
        require_once BASE_PATH . '/views/layouts/app.php';
    }

    public function create()
    {
        $this->form(null);
    }

    public function edit($id = null)
    {
        $post = $this->find($id);
        if (!$post) {
            flash('error', 'That post isn\'t here.');
            redirect('posts');
        }
        $this->form($post);
    }

    public function delete($id = null)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('posts');
        }
        csrf_verify();
        $post = $this->find($id);
        if ($post) {
            $this->db->prepare("UPDATE shop_posts SET deleted_at = NOW() WHERE id = ? AND business_id = ?")->execute([(int)$post['id'], $this->biz_id]);
            storefront_purge($this->biz_id);
            flash('success', 'Post deleted.');
        }
        redirect('posts');
    }

    /* ── Helpers ───────────────────────────────────── */

    private function form(?array $post)
    {
        $user     = auth_user();
        $business = auth_business();
        $errors   = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            $title   = mb_substr(trim(preg_replace('/\s+/u', ' ', (string)($_POST['title'] ?? '')) ?? ''), 0, 120);
            $body    = mb_substr(trim(str_replace(["\r\n", "\r"], "\n", (string)($_POST['body'] ?? ''))), 0, 5000);
            $publish = ($_POST['action'] ?? '') === 'publish';
            if ($title === '') {
                $errors[] = 'Give your post a title.';
            }
            if ($body === '') {
                $errors[] = 'Write something in the post.';
            }

            $image = $post['image_path'] ?? null;
            if (!empty($_POST['remove_image'])) {
                $image = null;
            }
            if (!empty($_FILES['image']['tmp_name'])) {
                $dir = BASE_PATH . '/uploads/storefront/' . $this->biz_id;
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                $file = 'post_' . time() . '_' . bin2hex(random_bytes(4)) . '.png';
                if (save_image($_FILES['image'], $dir . '/' . $file, 1600)) {
                    $image = 'uploads/storefront/' . $this->biz_id . '/' . $file;
                } else {
                    $errors[] = 'That photo couldn\'t be read. Use a JPG, PNG or WebP picture.';
                }
            }

            if (!$errors) {
                $status = $publish ? 'published' : 'draft';
                if ($post) {
                    $this->db->prepare("
                        UPDATE shop_posts SET title = ?, body = ?, image_path = ?, status = ?,
                            published_at = CASE WHEN ? = 'published' THEN COALESCE(published_at, NOW()) ELSE published_at END
                        WHERE id = ? AND business_id = ?
                    ")->execute([$title, $body, $image, $status, $status, (int)$post['id'], $this->biz_id]);
                    $id = (int)$post['id'];
                } else {
                    $this->db->prepare("
                        INSERT INTO shop_posts (business_id, title, body, image_path, status, published_at, created_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ")->execute([$this->biz_id, $title, $body, $image, $status, $publish ? date('Y-m-d H:i:s') : null, (int)$user['id']]);
                    $id = (int)$this->db->lastInsertId();
                }
                storefront_purge($this->biz_id);
                $link = $business['subdomain'] ? shop_url($business['subdomain'], shop_post_path(['id' => $id, 'title' => $title])) : '';
                flash('success', $publish ? 'Published' . ($link !== '' ? ': ' . $link : '.') : 'Saved as a draft. Only you can see it.');
                redirect('posts');
            }
            $post = array_merge($post ?? [], ['title' => $title, 'body' => $body, 'image_path' => $image]);
        }

        $title     = $post && !empty($post['id']) ? 'Edit post' : 'New post';
        $view_file = BASE_PATH . '/views/posts/form.php';
        require_once BASE_PATH . '/views/layouts/app.php';
    }

    private function find($id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM shop_posts WHERE id = ? AND business_id = ? AND deleted_at IS NULL");
        $stmt->execute([(int)$id, $this->biz_id]);
        return $stmt->fetch() ?: null;
    }
}
