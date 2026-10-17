<?php
/**
 * Nfelia — News and offers list (PostController::index)
 * Variables: $business, $posts
 */
$live = !empty($business['subdomain']);
?>
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <h1 class="font-heading font-extrabold text-2xl sm:text-3xl text-brand-plum tracking-tight">News and offers</h1>
        <p class="text-sm text-brand-plum/60 mt-1">Short posts on your shop: new arrivals, offers, holiday hours. Each one gets its own page that Google can find.</p>
    </div>
    <a href="<?= APP_URL ?>/posts/create" class="h-11 px-5 inline-flex items-center justify-center rounded-xl btn-primary text-white text-sm font-bold shrink-0">Write a post</a>
</div>

<div class="space-y-3">
    <?php foreach ($posts as $post):
        $url = $live && $post['status'] === 'published' ? shop_url($business['subdomain'], shop_post_path($post)) : '';
    ?>
    <div class="bg-brand-white border border-brand-sand rounded-xl p-4 sm:p-5 shadow-sm flex items-start gap-4">
        <?php if ($post['image_path']): ?>
        <img src="<?= APP_URL . '/' . e($post['image_path']) ?>" alt="" class="w-16 h-16 rounded-lg object-cover border border-brand-sand shrink-0">
        <?php endif; ?>
        <div class="min-w-0 flex-1">
            <p class="text-base font-bold text-brand-plum break-words"><?= e($post['title']) ?></p>
            <p class="text-xs text-brand-plum/50 mt-1">
                <?php if ($post['status'] === 'published'): ?>
                    Published <?= e(date('j M Y', strtotime((string)$post['published_at']))) ?>
                <?php else: ?>
                    <span class="px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 font-semibold">Draft</span> only you can see it
                <?php endif; ?>
            </p>
            <div class="flex flex-wrap gap-3 mt-2 text-sm">
                <a class="font-semibold text-brand-plum underline" href="<?= APP_URL ?>/posts/edit/<?= (int)$post['id'] ?>">Edit</a>
                <?php if ($url): ?><a class="font-semibold text-brand-plum/70 underline" href="<?= e($url) ?>" target="_blank" rel="noopener">See it on your shop</a><?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if (!$posts): ?>
    <div class="bg-brand-white border border-brand-sand rounded-xl p-6 text-center">
        <p class="text-sm font-bold text-brand-plum">No posts yet</p>
        <p class="text-sm text-brand-plum/60 mt-1">Tell people what's new: a fresh delivery, a weekend offer, your Christmas hours.</p>
        <a href="<?= APP_URL ?>/posts/create" class="inline-flex mt-4 h-11 px-5 items-center rounded-xl btn-primary text-white text-sm font-bold">Write your first post</a>
    </div>
    <?php endif; ?>
</div>
