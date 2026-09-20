<?php
require __DIR__ . '/config.php';

$cat = trim($_GET['cat'] ?? '');
$tag = trim($_GET['tag'] ?? '');

$sql = "SELECT DISTINCT p.*, c.name AS cat_name, c.slug AS cat_slug
        FROM posts p
        LEFT JOIN categories c ON c.id = p.category_id
        LEFT JOIN post_tags pt ON pt.post_id = p.id
        LEFT JOIN tags t ON t.id = pt.tag_id
        WHERE p.status = 'published'";
$params = [];
if ($cat !== '') { $sql .= ' AND c.slug = :cat'; $params[':cat'] = $cat; }
if ($tag !== '') { $sql .= ' AND t.slug = :tag'; $params[':tag'] = $tag; }
$sql .= ' ORDER BY p.created_at DESC';

$st = db()->prepare($sql);
$st->execute($params);
$posts = $st->fetchAll();

$categories = db()->query('SELECT * FROM categories ORDER BY name')->fetchAll();
$tags = db()->query('SELECT * FROM tags ORDER BY name')->fetchAll();

$meta_title = 'Writing — ' . setting('site_title');
$meta_desc  = 'Build logs, research notes and field notes.';
require __DIR__ . '/partials/header.php';
?>
<main id="top">
<section class="section" style="border-top:none;">
<div class="wrap section-inner">
<div class="eyebrow mono">// from the notebook</div>
<h2 class="display">Writing &amp; build logs.</h2>
<p class="lead" style="margin:8px 0 18px; font-size:16px;">Where I am, what I'm building, and what I'm reading right now.</p>

<div style="margin:18px 0 8px; display:flex; flex-wrap:wrap; gap:8px; align-items:center;">
<a class="pill" href="<?= e(url('blog.php')) ?>"<?= ($cat === '' && $tag === '') ? ' style="border-color:var(--accent)"' : '' ?>>All</a>
<?php foreach ($categories as $c): ?>
<a class="pill" href="<?= e(url('blog.php?cat=' . urlencode($c['slug']))) ?>"<?= $cat === $c['slug'] ? ' style="border-color:var(--accent)"' : '' ?>><?= e($c['name']) ?></a>
<?php endforeach; ?>
</div>
<?php if ($tags): ?>
<div style="margin-bottom:8px; display:flex; flex-wrap:wrap; gap:8px; align-items:center;">
<span class="mono" style="font-size:12px; color:var(--muted);">tags:</span>
<?php foreach ($tags as $t): ?>
<a class="pill" href="<?= e(url('blog.php?tag=' . urlencode($t['slug']))) ?>"<?= $tag === $t['slug'] ? ' style="border-color:var(--accent)"' : '' ?>>#<?= e($t['name']) ?></a>
<?php endforeach; ?>
</div>
<?php endif; ?>

<div class="grid-3" style="margin-top:28px;">
<?php if (!$posts): ?>
<p class="lead">Nothing here yet.</p>
<?php endif; ?>
<?php foreach ($posts as $po): ?>
<div class="card post">
<div class="post-top">
<span class="post-cat mono"><?= e(strtoupper($po['cat_name'] ?? 'NOTE')) ?></span>
<span class="post-date mono"><?= e(date('M j, Y', strtotime($po['created_at']))) ?></span>
</div>
<div class="post-title display"><?= e($po['title']) ?></div>
<div class="card-desc" style="margin-bottom:18px;"><?= e($po['excerpt']) ?></div>
<a href="<?= e(url('post.php?slug=' . urlencode($po['slug']))) ?>" class="read">Read <span class="arrow">→</span></a>
</div>
<?php endforeach; ?>
</div>
</div>
</section>
</main>
<?php require __DIR__ . '/partials/footer.php'; ?>
