<?php
/**
 * Nfelia — Write or change a post (PostController::form)
 * Variables: $business, $post (null for a new one), $errors
 */
$editing = $post && !empty($post['id']);
$input   = 'w-full px-3 rounded-lg border border-brand-sand bg-white text-sm';
?>
<div class="mb-6">
    <a href="<?= APP_URL ?>/posts" class="text-sm font-semibold text-brand-plum/60 hover:text-brand-plum">← News and offers</a>
    <h1 class="font-heading font-extrabold text-2xl sm:text-3xl text-brand-plum tracking-tight mt-2"><?= $editing ? 'Edit post' : 'Write a post' ?></h1>
</div>

<?php if ($errors): ?>
<div class="mb-4 p-4 rounded-xl border border-red-200 bg-red-50 text-sm text-red-700" role="alert">
    <?php foreach ($errors as $error): ?><p><?= e($error) ?></p><?php endforeach; ?>
</div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data" action="<?= APP_URL ?>/posts/<?= $editing ? 'edit/' . (int)$post['id'] : 'create' ?>" class="space-y-5 max-w-2xl">
    <?= csrf_field() ?>
    <label class="block">
        <span class="block text-sm font-semibold text-brand-plum mb-1">Title</span>
        <input type="text" name="title" maxlength="120" required value="<?= e($post['title'] ?? '') ?>" class="<?= $input ?> h-11" placeholder="New Ankara prints are in">
    </label>
    <label class="block">
        <span class="block text-sm font-semibold text-brand-plum mb-1">Your post</span>
        <textarea name="body" rows="9" maxlength="5000" required class="<?= $input ?> py-2 leading-relaxed" placeholder="What's new, what it costs, until when."><?= e($post['body'] ?? '') ?></textarea>
        <span class="block text-xs text-brand-plum/50 mt-1">Leave an empty line between paragraphs.</span>
    </label>
    <div>
        <span class="block text-sm font-semibold text-brand-plum mb-1">Photo <span class="font-normal text-brand-plum/50">(optional)</span></span>
        <?php if (!empty($post['image_path'])): ?>
        <div class="flex items-center gap-3 mb-2">
            <img src="<?= APP_URL . '/' . e($post['image_path']) ?>" alt="The post's photo" class="w-24 h-24 rounded-lg object-cover border border-brand-sand">
            <label class="flex items-center gap-2 text-sm text-brand-plum"><input type="checkbox" name="remove_image" value="1" class="w-5 h-5"> Remove it</label>
        </div>
        <?php endif; ?>
        <input type="file" name="image" accept="image/jpeg,image/png,image/webp" class="block text-sm">
        <span class="block text-xs text-brand-plum/50 mt-1">It shows on the post and when someone shares its link.</span>
    </div>
    <div class="flex flex-wrap gap-3">
        <button type="submit" name="action" value="publish" class="h-12 px-6 btn-primary text-white text-sm font-bold rounded-xl"><?= $editing && ($post['status'] ?? '') === 'published' ? 'Save' : 'Publish' ?></button>
        <?php if (!$editing || ($post['status'] ?? '') !== 'published'): ?>
        <button type="submit" name="action" value="draft" class="h-12 px-6 border border-brand-sand bg-brand-white text-brand-plum text-sm font-bold rounded-xl">Save as a draft</button>
        <?php else: ?>
        <button type="submit" name="action" value="draft" class="h-12 px-6 border border-brand-sand bg-brand-white text-brand-plum text-sm font-bold rounded-xl">Take it off the shop</button>
        <?php endif; ?>
    </div>
</form>
<?php if ($editing): ?>
<form method="POST" action="<?= APP_URL ?>/posts/delete/<?= (int)$post['id'] ?>" class="mt-8 max-w-2xl"
      onsubmit="return confirm(<?= e(json_encode('Delete "' . ($post['title'] ?? '') . '"? It leaves your shop.', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)) ?>)">
    <?= csrf_field() ?>
    <button type="submit" class="text-sm font-semibold text-red-700 underline">Delete this post</button>
</form>
<?php endif; ?>
