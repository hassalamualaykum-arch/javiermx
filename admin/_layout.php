<?php
/** Requiere config.php ya incluido. */

/** Genera un slug único en una tabla (nombres de tabla son internos, no del usuario). */
function unique_slug(string $table, string $base, int $excludeId = 0): string {
    $allowed = ['categories', 'tags', 'posts', 'projects'];
    if (!in_array($table, $allowed, true)) { return $base; }
    $slug = $base; $i = 2;
    $sql = "SELECT COUNT(*) FROM `$table` WHERE slug = ? AND id <> ?";
    $st = db()->prepare($sql);
    while (true) {
        $st->execute([$slug, $excludeId]);
        if ((int) $st->fetchColumn() === 0) { return $slug; }
        $slug = $base . '-' . $i;
        $i++;
    }
}

function admin_head(string $title): void {
    $u = current_user();
    $link = function ($file, $label) {
        $active = basename($_SERVER['PHP_SELF']) === $file ? ' style="color:var(--accent)"' : '';
        echo '<a href="' . e(url('admin/' . $file)) . '" class="a-link"' . $active . '>' . e($label) . '</a>';
    };
    ?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> · Panel javiermx</title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" href="<?= e(url('favicon.ico')) ?>" sizes="48x48">
<link rel="icon" href="<?= e(url('favicon.svg')) ?>" type="image/svg+xml">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&family=Plus+Jakarta+Sans:wght@600;700&display=swap">
<style>
/* Panel: mismo tema de vidrio que el sitio, más sobrio (sin animaciones) para trabajar cómodo */
:root{--ground:#070A12;--surface:rgba(255,255,255,.05);--surface2:#141A2A;--text:#EEF1F7;--muted:#9AA4B8;--line:rgba(255,255,255,.10);--line-hi:rgba(255,255,255,.18);
  --accent:#5FE3A1;--indigo:#7C83FF;--cyan:#38D6F5;--err:#F87171;
  --blur:blur(16px) saturate(140%);--shadow:0 1px 0 rgba(255,255,255,.06) inset,0 14px 40px -14px rgba(0,0,0,.6);
  --f-display:'Plus Jakarta Sans',system-ui,sans-serif;--f-body:'Inter',system-ui,sans-serif;--f-mono:'JetBrains Mono',ui-monospace,monospace;box-sizing:border-box}
*,*::before,*::after{box-sizing:inherit}
body{margin:0;min-height:100vh;background:var(--ground);color:var(--text);font-family:var(--f-body);-webkit-font-smoothing:antialiased}
body::before{content:'';position:fixed;inset:-6%;z-index:-1;pointer-events:none;filter:blur(36px);
  background:radial-gradient(40% 44% at 14% 18%,color-mix(in srgb,var(--indigo) 34%,transparent),transparent 72%),
             radial-gradient(34% 38% at 88% 26%,color-mix(in srgb,var(--cyan) 20%,transparent),transparent 72%),
             radial-gradient(40% 40% at 70% 92%,color-mix(in srgb,var(--accent) 16%,transparent),transparent 72%)}
.mono{font-family:var(--f-mono)}
a{color:var(--accent);text-decoration:none}
:focus-visible{outline:2px solid var(--accent);outline-offset:2px;border-radius:6px}
.topbar{position:sticky;top:0;z-index:20;border-bottom:1px solid var(--line);background:rgba(10,14,24,.6);backdrop-filter:var(--blur);-webkit-backdrop-filter:var(--blur)}
.topbar-in{max-width:1080px;margin:0 auto;padding:12px 20px;display:flex;align-items:center;gap:4px 6px;flex-wrap:wrap}
.brand{font-family:var(--f-mono);font-weight:500;color:var(--text);margin-right:10px}
.a-link{color:var(--muted);font-size:13.5px;font-weight:500;padding:6px 10px;border-radius:8px;transition:color .15s,background .15s}
.a-link:hover{color:var(--text);background:rgba(255,255,255,.06)}
.spacer{margin-left:auto}
.adm{max-width:1080px;margin:0 auto;padding:32px 20px 80px}
h1.page{font-family:var(--f-display);font-size:28px;font-weight:700;margin:0 0 20px;letter-spacing:-.02em}
h1,h2,h3{font-family:var(--f-display);letter-spacing:-.015em}
.card{background:linear-gradient(160deg,rgba(255,255,255,.065),rgba(255,255,255,.025));border:1px solid var(--line);border-radius:16px;padding:22px;
  box-shadow:var(--shadow);backdrop-filter:var(--blur);-webkit-backdrop-filter:var(--blur)}
.flash{padding:12px 16px;border-radius:12px;margin-bottom:14px;font-size:14px;border:1px solid var(--line);background:var(--surface);backdrop-filter:var(--blur);-webkit-backdrop-filter:var(--blur)}
.flash.ok{border-color:color-mix(in srgb,var(--accent) 60%,transparent);color:var(--accent)}
.flash.err{border-color:color-mix(in srgb,var(--err) 60%,transparent);color:var(--err)}
label.f{display:block;font-size:13px;font-weight:500;color:var(--muted);margin:16px 0 7px}
.in{width:100%;background:rgba(4,7,14,.5);border:1px solid var(--line);border-radius:10px;padding:11px 13px;color:var(--text);font-size:15px;font-family:inherit;outline:none;transition:border-color .15s,box-shadow .15s}
.in:focus{border-color:color-mix(in srgb,var(--accent) 70%,transparent);box-shadow:0 0 0 4px color-mix(in srgb,var(--accent) 14%,transparent)}
.in:focus-visible{outline:none}
select.in option{background:var(--surface2);color:var(--text)}
textarea.in{resize:vertical;min-height:120px}
.btn{display:inline-block;color:#04140C;border:none;border-radius:10px;padding:11px 20px;font-size:14px;font-weight:600;cursor:pointer;font-family:inherit;
  background:linear-gradient(135deg,var(--accent),color-mix(in srgb,var(--accent) 55%,var(--cyan)));box-shadow:0 6px 20px -8px color-mix(in srgb,var(--accent) 70%,transparent);transition:transform .12s,box-shadow .12s}
.btn:hover{transform:translateY(-1px)}
.btn.ghost{background:rgba(255,255,255,.04);border:1px solid var(--line-hi);color:var(--text);box-shadow:none}
.btn.ghost:hover{border-color:var(--accent)}
.btn.danger{background:rgba(248,113,113,.06);border:1px solid color-mix(in srgb,var(--err) 60%,transparent);color:var(--err);box-shadow:none}
.btn.danger:hover{background:rgba(248,113,113,.12)}
.row{display:flex;gap:14px;flex-wrap:wrap}
.row>*{flex:1;min-width:200px}
table{width:100%;border-collapse:collapse;font-size:14px}
th,td{text-align:left;padding:11px 10px;border-bottom:1px solid var(--line)}
tr:last-child>td{border-bottom:none}
tbody tr,table tr{transition:background .12s}
table tr:hover>td{background:rgba(255,255,255,.025)}
th{color:var(--muted);font-weight:500;font-size:11.5px;text-transform:uppercase;letter-spacing:.1em}
.muted{color:var(--muted)}
.tag{display:inline-block;font-size:11px;color:var(--accent);border:1px solid color-mix(in srgb,var(--accent) 25%,transparent);background:color-mix(in srgb,var(--accent) 8%,transparent);border-radius:999px;padding:2px 9px;margin:2px 3px 0 0}
.actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin:22px 0}
.thumb{width:46px;height:46px;border-radius:9px;object-fit:cover;background:var(--surface2);border:1px solid var(--line)}
@supports not ((backdrop-filter:blur(1px)) or (-webkit-backdrop-filter:blur(1px))){.card,.topbar{background:rgba(16,21,34,.94)}}
</style>
</head>
<body>
<div class="topbar"><div class="topbar-in">
<span class="brand">javier<span style="color:var(--accent)">mx</span> · panel</span>
<?php $link('index.php','Inicio'); $link('stats.php','Estadísticas'); $link('sales.php','Ventas'); $link('shop.php','Tienda'); $link('messages.php','Mensajes'); $link('posts.php','Posts'); $link('projects.php','Proyectos'); $link('currently.php','Currently'); $link('categories.php','Categorías'); $link('tags.php','Etiquetas'); $link('settings.php','Ajustes'); ?>
<span class="spacer"></span>
<a class="a-link" href="<?= e(url('')) ?>" target="_blank">Ver sitio ↗</a>
<a class="a-link" href="<?= e(url('admin/logout.php')) ?>">Salir (<?= e($u['username'] ?? '') ?>)</a>
</div></div>
<main class="adm">
<?php foreach (take_flashes() as $f): ?>
<div class="flash <?= e($f['type']) ?>"><?= e($f['msg']) ?></div>
<?php endforeach; ?>
<?php
}

function admin_foot(): void {
    echo "</main></body></html>";
}
