<?php
require __DIR__ . '/config.php';

header('Content-Type: application/xml; charset=utf-8');

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$base   = $scheme . '://' . $_SERVER['HTTP_HOST'];

$posts = db()->query(
    "SELECT slug, updated_at FROM posts WHERE status = 'published' ORDER BY updated_at DESC"
)->fetchAll();

$urls = [
    ['loc' => $base . url('index.php'), 'lastmod' => null],
    ['loc' => $base . url('blog.php'),  'lastmod' => null],
];
foreach ($posts as $p) {
    $urls[] = [
        'loc'     => $base . url('post.php?slug=' . urlencode($p['slug'])),
        'lastmod' => date('Y-m-d', strtotime($p['updated_at'])),
    ];
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $u) {
    echo '<url><loc>' . e($u['loc']) . '</loc>';
    if ($u['lastmod']) {
        echo '<lastmod>' . e($u['lastmod']) . '</lastmod>';
    }
    echo "</url>\n";
}
echo '</urlset>';
