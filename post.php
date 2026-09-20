<?php
require __DIR__ . '/config.php';

$slug = trim($_GET['slug'] ?? '');
$st = db()->prepare(
    "SELECT p.*, c.name AS cat_name, c.slug AS cat_slug
     FROM posts p LEFT JOIN categories c ON c.id = p.category_id
     WHERE p.slug = ? AND p.status = 'published' LIMIT 1"
);
$st->execute([$slug]);
$post = $st->fetch();

if (!$post) {
    http_response_code(404);
    $meta_title = 'Not found — ' . setting('site_title');
    $meta_desc = '';
    require __DIR__ . '/partials/header.php';
    echo '<main class="wrap"><div class="article"><h1>404</h1><p class="lead">That post does not exist. <a href="' . e(url('blog.php')) . '">Back to writing →</a></p></div></main>';
    require __DIR__ . '/partials/footer.php';
    exit;
}

$tt = db()->prepare(
    'SELECT t.name, t.slug FROM tags t
     JOIN post_tags pt ON pt.tag_id = t.id WHERE pt.post_id = ? ORDER BY t.name'
);
$tt->execute([(int) $post['id']]);
$ptags = $tt->fetchAll();

$cover = img_src($post['cover_image']);
$meta_title = ($post['seo_title'] ?: $post['title']) . ' — ' . setting('site_title');
$meta_desc  = $post['seo_description'] ?: $post['excerpt'];
$og_image   = $cover;
require __DIR__ . '/partials/header.php';
?>
<main class="wrap">
<article class="article">
<a class="back" href="<?= e(url('blog.php')) ?>">← Writing</a>
<h1 class="display" style="margin-top:14px;"><?= e($post['title']) ?></h1>
<div class="meta mono">
<?php if ($post['cat_name']): ?><span style="color:var(--accent)"><?= e($post['cat_name']) ?></span> · <?php endif; ?>
<?= e(date('F j, Y', strtotime($post['created_at']))) ?>
</div>
<?php if ($cover): ?><img class="cover" src="<?= e($cover) ?>" alt="<?= e($post['title']) ?>"><?php endif; ?>
<div class="body">
<?= $post['body'] /* HTML del autor */ ?>
</div>
<?php if ($ptags): ?>
<div style="margin-top:34px;">
<?php foreach ($ptags as $t): ?>
<a class="pill" href="<?= e(url('blog.php?tag=' . urlencode($t['slug']))) ?>">#<?= e($t['name']) ?></a>
<?php endforeach; ?>
</div>
<?php endif; ?>
</article>
</main>
<?php require __DIR__ . '/partials/footer.php'; ?>
