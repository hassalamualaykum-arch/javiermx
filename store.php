<?php
/**
 * store.php — tienda pública de javiermx.com
 *
 * Ubicación:  public_html/store.php   (súbelo también a tu repositorio: no lleva secretos)
 * Catálogo:   ../private_files/products.json   (lo edita el panel: admin/shop.php)
 *
 * Muestra los productos activos en dos secciones (digitales y físicos).
 * Cada botón "Buy" lleva al Payment Link de Stripe del producto.
 */
require __DIR__ . '/config.php';

// ---------- cargar el catálogo (products.json, que edita el panel admin/shop.php) ----------
require_once __DIR__ . '/partials/catalog.php';
$products = catalog_load();

// ---------- helpers ----------
function store_is_buy_link($u): bool
{
    return is_string($u) && preg_match('#^https://buy\.stripe\.com/[A-Za-z0-9_\-]+$#', $u) === 1 && stripos($u, 'PEGA_AQUI') === false;
}

function store_price(array $p): string
{
    $amount = (float) ($p['price'] ?? 0);
    $cur    = strtoupper((string) ($p['currency'] ?? 'CAD'));
    return '$' . number_format($amount, 2) . ' ' . $cur;
}

function store_img(array $p): string
{
    $img = trim((string) ($p['image'] ?? ''));
    if ($img === '') {
        return '';
    }
    return img_src($img); // link https:// o archivo local (uploads/…), igual que en el resto del sitio
}

// ---------- agrupar por tipo ----------
$groups   = ['digital' => [], 'physical' => []];
$testMode = false;

foreach ($products as $p) {
    if (!is_array($p) || ($p['active'] ?? true) === false) {
        continue;
    }
    if (trim((string) ($p['name'] ?? '')) === '') {
        continue;
    }
    $type = (($p['type'] ?? '') === 'physical') ? 'physical' : 'digital';
    $groups[$type][] = $p;

    if (store_is_buy_link($p['link'] ?? '') && strpos($p['link'], '/test_') !== false) {
        $testMode = true;
    }
}

$sections = [
    'digital'  => ['// digital', 'Digital products.',  'Instant download right after payment.'],
    'physical' => ['// hardware', 'Physical products.', 'Circuits and kits for your Arduino projects.'],
];

$meta_title = 'Store — ' . setting('site_title');
$meta_desc  = 'Digital guides, code and hardware kits.';
$canonical  = 'store.php';
$noindex    = $testMode; // mientras sea modo prueba, que Google no lo indexe
require __DIR__ . '/partials/header.php';
?>
<style>
.store-grid{display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:20px; margin-top:22px;}
.store-card{padding:0 !important; overflow:hidden; display:flex; flex-direction:column;}
.store-media{height:170px; background:var(--surface2); display:flex; align-items:center; justify-content:center;
             color:#5B6268; font-size:13px; letter-spacing:1px; overflow:hidden;}
.store-media img{width:100%; height:100%; object-fit:cover; display:block;}
.store-body{padding:22px 24px 24px; display:flex; flex-direction:column; flex:1;}
.store-tags{margin-bottom:10px;}
.store-price{font-size:22px; font-weight:600; margin:0;}
.store-foot{margin-top:auto; padding-top:18px; display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;}
.store-btn{padding:10px 20px; font-size:15px;}
.store-soon{color:var(--muted); font-size:13.5px; border:1px dashed var(--line); border-radius:10px; padding:8px 14px;}
.store-test{border:1px dashed var(--accent); border-radius:12px; padding:14px 18px; margin:20px 0 4px; font-size:14px; color:var(--muted); line-height:1.6;}
.store-group + .store-group{margin-top:64px;}
</style>
<main id="top">
<section class="section" style="border-top:none;">
<div class="wrap section-inner">
<div class="eyebrow mono">// store</div>
<h2 class="display">Store.</h2>
<p class="lead" style="margin:8px 0 0; font-size:16px;">Guides, code and hardware from my workshop.</p>

<?php if ($testMode): ?>
<div class="store-test">
<strong style="color:var(--text);">Test mode.</strong> This store is connected to Stripe's sandbox, so no real charges are made.
Test card: <span class="mono">4242 4242 4242 4242</span> · any future date · any CVC.
</div>
<?php endif; ?>

<?php if (!$groups['digital'] && !$groups['physical']): ?>
<p class="lead" style="margin-top:32px;">The store is opening soon.</p>
<?php endif; ?>

<?php foreach ($sections as $type => [$eyebrow, $title, $lead]): ?>
<?php if (!$groups[$type]) { continue; } ?>
<div class="store-group" id="<?= e($type) ?>" style="margin-top:44px;">
<div class="eyebrow mono"><?= e($eyebrow) ?></div>
<h3 class="display" style="font-size:28px; margin:0 0 6px;"><?= e($title) ?></h3>
<p class="lead" style="margin:0; font-size:15px;"><?= e($lead) ?></p>

<div class="store-grid">
<?php foreach ($groups[$type] as $p): ?>
<?php
    $img  = store_img($p);
    $link = $p['link'] ?? '';
    $tags = is_array($p['tags'] ?? null) ? $p['tags'] : [];
?>
<div class="card store-card">
<div class="store-media">
<?php if ($img !== ''): ?>
<img src="<?= e($img) ?>" alt="<?= e($p['name']) ?>" loading="lazy">
<?php else: ?>
<span class="mono"><?= $type === 'physical' ? 'HARDWARE' : 'DIGITAL' ?></span>
<?php endif; ?>
</div>
<div class="store-body">
<?php if ($tags): ?>
<div class="store-tags">
<?php foreach ($tags as $tg): ?><span class="pill"><?= e((string) $tg) ?></span><?php endforeach; ?>
</div>
<?php endif; ?>
<div class="post-title display"><?= e($p['name']) ?></div>
<?php if (trim((string) ($p['summary'] ?? '')) !== ''): ?>
<div class="card-desc"><?= e($p['summary']) ?></div>
<?php endif; ?>
<div class="store-foot">
<div class="store-price display"><?= e(store_price($p)) ?></div>
<?php if (store_is_buy_link($link)): ?>
<a class="btn btn-primary store-btn" href="<?= e($link) ?>" rel="noopener">Buy <span class="arrow">→</span></a>
<?php else: ?>
<span class="store-soon mono">Coming soon</span>
<?php endif; ?>
</div>
</div>
</div>
<?php endforeach; ?>
</div>
</div>
<?php endforeach; ?>

<p class="lead" style="margin-top:48px; font-size:14.5px;">
Questions before you buy? <a href="<?= e(url('') . '#contact') ?>" style="color:var(--accent);">Get in touch</a>.
</p>
</div>
</section>
</main>
<?php require __DIR__ . '/partials/footer.php'; ?>
