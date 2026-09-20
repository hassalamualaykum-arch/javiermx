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
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:wght@400;500;600&display=swap">
<style>
:root{--ground:#0B0D0F;--surface:#121518;--surface2:#171C20;--text:#ECEDEA;--muted:#98A0A6;--line:#242A2F;--accent:#5FE3A1;--err:#F87171;box-sizing:border-box}
*,*::before,*::after{box-sizing:inherit}
body{margin:0;background:var(--ground);color:var(--text);font-family:'IBM Plex Sans',system-ui,sans-serif;-webkit-font-smoothing:antialiased}
.mono{font-family:'IBM Plex Mono',monospace}
a{color:var(--accent);text-decoration:none}
.topbar{border-bottom:1px solid var(--line);background:var(--surface)}
.topbar-in{max-width:1080px;margin:0 auto;padding:14px 20px;display:flex;align-items:center;gap:20px;flex-wrap:wrap}
.brand{font-family:'IBM Plex Mono',monospace;font-weight:500;color:var(--text)}
.a-link{color:var(--muted);font-size:14px;font-weight:500}
.a-link:hover{color:var(--text)}
.spacer{margin-left:auto}
.adm{max-width:1080px;margin:0 auto;padding:28px 20px 80px}
h1.page{font-size:26px;margin:0 0 20px;letter-spacing:-0.5px}
.card{background:var(--surface);border:1px solid var(--line);border-radius:12px;padding:22px}
.flash{padding:12px 16px;border-radius:9px;margin-bottom:14px;font-size:14px;border:1px solid var(--line)}
.flash.ok{border-color:var(--accent);color:var(--accent)}
.flash.err{border-color:var(--err);color:var(--err)}
label.f{display:block;font-size:13px;color:var(--muted);margin:16px 0 7px}
.in{width:100%;background:var(--surface2);border:1px solid var(--line);border-radius:8px;padding:11px 13px;color:var(--text);font-size:15px;font-family:inherit;outline:none}
.in:focus{border-color:var(--accent)}
textarea.in{resize:vertical;min-height:120px}
.btn{display:inline-block;background:var(--accent);color:var(--ground);border:none;border-radius:8px;padding:11px 20px;font-size:14px;font-weight:600;cursor:pointer;font-family:inherit}
.btn.ghost{background:transparent;border:1px solid var(--line);color:var(--text)}
.btn.danger{background:transparent;border:1px solid var(--err);color:var(--err)}
.row{display:flex;gap:14px;flex-wrap:wrap}
.row>*{flex:1;min-width:200px}
table{width:100%;border-collapse:collapse;font-size:14px}
th,td{text-align:left;padding:11px 10px;border-bottom:1px solid var(--line)}
th{color:var(--muted);font-weight:500;font-size:12px;text-transform:uppercase;letter-spacing:1px}
.muted{color:var(--muted)}
.tag{display:inline-block;font-size:11px;color:var(--accent);border:1px solid var(--line);border-radius:999px;padding:2px 9px;margin:2px 3px 0 0}
.actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin:22px 0}
.thumb{width:46px;height:46px;border-radius:7px;object-fit:cover;background:var(--surface2);border:1px solid var(--line)}
</style>
</head>
<body>
<div class="topbar"><div class="topbar-in">
<span class="brand">javier<span style="color:var(--accent)">mx</span> · panel</span>
<?php $link('index.php','Inicio'); $link('posts.php','Posts'); $link('projects.php','Proyectos'); $link('currently.php','Currently'); $link('categories.php','Categorías'); $link('tags.php','Etiquetas'); $link('settings.php','Ajustes'); ?>
<span class="spacer"></span>
<a class="a-link" href="<?= e(url('index.php')) ?>" target="_blank">Ver sitio ↗</a>
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
