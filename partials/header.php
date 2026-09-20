<?php
/** Espera: $meta_title, $meta_desc. Opcionales: $ON_HOME (bool), $og_image (url). */
$meta_title = $meta_title ?? setting('site_title', 'javiermx');
$meta_desc  = $meta_desc  ?? setting('site_description', '');
$ON_HOME    = $ON_HOME ?? false;
$og_image   = $og_image ?? '';
$accent     = setting('accent_color', '#5FE3A1');
$anchor = function (string $id) use ($ON_HOME) {
    return $ON_HOME ? '#' . $id : url('index.php') . '#' . $id;
};
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($meta_title) ?></title>
<meta name="description" content="<?= e($meta_desc) ?>">
<meta property="og:type" content="website">
<meta property="og:title" content="<?= e($meta_title) ?>">
<meta property="og:description" content="<?= e($meta_desc) ?>">
<?php if ($og_image): ?><meta property="og:image" content="<?= e($og_image) ?>"><?php endif; ?>
<meta name="twitter:card" content="summary_large_image">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="<?= e(url('assets/site.css')) ?>">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:wght@400;500;600&family=Space+Grotesk:wght@500;700&display=swap" media="print" onload="this.media='all'">
<noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:wght@400;500;600&family=Space+Grotesk:wght@500;700&display=swap"></noscript>
<style>:root{--accent:<?= e($accent) ?>;}</style>
</head>
<body>
<header class="nav">
<div class="wrap nav-inner">
<a href="<?= e(url('index.php')) ?>" class="brand mono">javier<span style="color:var(--accent)">mx</span><span style="color:var(--accent)">_</span></a>
<nav class="nav-links">
<a href="<?= e($anchor('focus')) ?>" class="navlink">Focus</a>
<a href="<?= e($anchor('projects')) ?>" class="navlink">Projects</a>
<a href="<?= e($anchor('currently')) ?>" class="navlink">Currently</a>
<a href="<?= e(url('blog.php')) ?>" class="navlink">Writing</a>
<a href="<?= e($anchor('contact')) ?>" class="navlink">Contact</a>
<span class="status"><span class="dot"></span>Available</span>
</nav>
<a href="<?= e($anchor('contact')) ?>" class="nav-contact">Contact</a>
</div>
</header>
