<?php
/** Sitemap para Google: se genera solo con los posts publicados. */
require __DIR__ . '/config.php';
require_once __DIR__ . '/partials/seo.php';

$posts = db()->query("SELECT * FROM posts WHERE status = 'published' ORDER BY created_at DESC")->fetchAll();
$latest = $posts ? date('Y-m-d', strtotime($posts[0]['updated_at'] ?? $posts[0]['created_at'])) : null;

header('Content-Type: application/xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<url><loc><?= e(abs_url()) ?></loc><?php if ($latest): ?><lastmod><?= $latest ?></lastmod><?php endif; ?></url>
<url><loc><?= e(abs_url('blog.php')) ?></loc><?php if ($latest): ?><lastmod><?= $latest ?></lastmod><?php endif; ?></url>
<?php foreach ($posts as $p): ?>
<url><loc><?= e(abs_url('post.php?slug=' . urlencode($p['slug']))) ?></loc><lastmod><?= date('Y-m-d', strtotime($p['updated_at'] ?? $p['created_at'])) ?></lastmod></url>
<?php endforeach; ?>
</urlset>
