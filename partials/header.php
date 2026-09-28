<?php
/**
 * Espera: $meta_title, $meta_desc.
 * Opcionales: $ON_HOME (bool), $og_image (url), $canonical (ruta, ej. "blog.php"),
 *             $og_type ("website" | "article"), $json_ld (array → JSON-LD para Google), $noindex (bool).
 */
require_once __DIR__ . '/seo.php';
$meta_title = $meta_title ?? setting('site_title', 'javiermx');
$meta_desc  = $meta_desc  ?? setting('site_description', '');
$ON_HOME    = $ON_HOME ?? false;
$og_image   = isset($og_image) && $og_image !== '' ? abs_url($og_image) : '';
$canonical  = isset($canonical) ? abs_url($canonical) : '';
$og_type    = $og_type ?? 'website';
$json_ld    = $json_ld ?? null;
$noindex    = $noindex ?? false;
$accent     = setting('accent_color', '#5FE3A1');
$anchor = function (string $id) use ($ON_HOME) {
    return $ON_HOME ? '#' . $id : url('') . '#' . $id;
};
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($meta_title) ?></title>
<meta name="description" content="<?= e($meta_desc) ?>">
<?php if ($noindex): ?><meta name="robots" content="noindex, follow">
<?php endif; ?>
<?php if ($canonical): ?><link rel="canonical" href="<?= e($canonical) ?>">
<meta property="og:url" content="<?= e($canonical) ?>">
<?php endif; ?>
<meta property="og:site_name" content="<?= e(SITE_NAME) ?>">
<meta property="og:type" content="<?= e($og_type) ?>">
<meta property="og:title" content="<?= e($meta_title) ?>">
<meta property="og:description" content="<?= e($meta_desc) ?>">
<?php if ($og_image): ?><meta property="og:image" content="<?= e($og_image) ?>">
<meta name="twitter:image" content="<?= e($og_image) ?>">
<?php endif; ?>
<meta name="twitter:card" content="<?= $og_image ? 'summary_large_image' : 'summary' ?>">
<meta name="twitter:title" content="<?= e($meta_title) ?>">
<meta name="twitter:description" content="<?= e($meta_desc) ?>">
<meta name="google-site-verification" content="O6NqFELxwcIkl7ryo9Wbw0rE-6LiETVcy3yAKBpg-Ps">
<link rel="icon" href="<?= e(url('favicon.ico')) ?>" sizes="48x48">
<link rel="icon" href="<?= e(url('favicon.svg')) ?>" type="image/svg+xml">
<link rel="apple-touch-icon" href="<?= e(url('apple-touch-icon.png')) ?>">
<link rel="manifest" href="<?= e(url('site.webmanifest')) ?>">
<meta name="theme-color" content="#0B0D0F">
<?php if ($json_ld): ?><script type="application/ld+json"><?= json_encode($json_ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
<?php endif; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<style>
<?php $__css = __DIR__ . '/../assets/site.css'; if (is_file($__css)) { readfile($__css); } ?>
:root{--accent:<?= e($accent) ?>;}
</style>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:wght@400;500;600&family=Space+Grotesk:wght@500;700&display=swap" media="print" onload="this.media='all'">
<noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:wght@400;500;600&family=Space+Grotesk:wght@500;700&display=swap"></noscript>
</head>
<body>
<header class="nav">
<div class="wrap nav-inner">
<a href="<?= e(url('')) ?>" class="brand mono">javier<span style="color:var(--accent)">mx</span><span style="color:var(--accent)">_</span></a>
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
