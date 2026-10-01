<?php
/**
 * admin/shop.php — administra los productos de la tienda.
 *
 * Ubicación:  public_html/admin/shop.php   (súbelo también a tu repositorio: no lleva secretos)
 * Guarda en:  ../../private_files/products.json   (vía partials/catalog.php)
 * Imágenes:   uploads/…  (igual que tus posts y proyectos, con handle_image())
 * Archivos:   ../../private_files/<archivo>   (PDF, ZIP, EPUB, TXT, MD)
 */
require __DIR__ . '/../config.php';
require_login();
require __DIR__ . '/_layout.php';
require_once __DIR__ . '/../partials/catalog.php';

const SHOP_FILE_EXT = ['pdf', 'zip', 'epub', 'txt', 'md'];
const SHOP_FILE_MAX = 100 * 1024 * 1024; // 100 MB (el servidor puede tener un límite menor)

$privateDir = catalog_dir();

// ───────────────────────── helpers ─────────────────────────

function shop_clip(string $s, int $max): string
{
    return function_exists('mb_substr') ? mb_substr($s, 0, $max, 'UTF-8') : substr($s, 0, $max);
}

/** Nombres que el panel nunca debe tocar dentro de private_files. */
function shop_reserved(string $name): bool
{
    $n = strtolower($name);
    return $n === '' || $n[0] === '.' || in_array($n, ['products.php', 'products.json', 'products.json.bak', 'products.json.tmp', 'stripe-secrets.php'], true);
}

/** Archivos vendibles que ya están en private_files. */
function shop_files(string $dir): array
{
    $out = [];
    foreach ((array) @scandir($dir) as $f) {
        if (!is_string($f) || shop_reserved($f) || !is_file($dir . $f)) {
            continue;
        }
        if (in_array(strtolower(pathinfo($f, PATHINFO_EXTENSION)), SHOP_FILE_EXT, true)) {
            $out[] = $f;
        }
    }
    sort($out);
    return $out;
}

/**
 * Guarda un archivo digital subido en private_files.
 * Devuelve el nombre guardado, o null si no se subió nada. Si hay problema, deja el texto en $err.
 */
function shop_save_file(string $fileKey, string $dir, string $currentFile, ?string &$err): ?string
{
    $err = null;
    if (empty($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $f = $_FILES[$fileKey];
    if ($f['error'] !== UPLOAD_ERR_OK) {
        $err = in_array($f['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
            ? 'El archivo es más grande de lo que permite el servidor (límite actual: ' . ini_get('upload_max_filesize') . ').'
            : 'No se pudo subir el archivo (código ' . (int) $f['error'] . ').';
        return null;
    }
    if ($f['size'] > SHOP_FILE_MAX) {
        $err = 'El archivo supera 100 MB.';
        return null;
    }

    $orig = (string) $f['name'];
    $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    if (!in_array($ext, SHOP_FILE_EXT, true)) {
        $err = 'Tipo de archivo no permitido. Usa: ' . implode(', ', SHOP_FILE_EXT) . '.';
        return null;
    }

    // Revisión rápida del contenido real (no solo la extensión).
    $head = (string) @file_get_contents($f['tmp_name'], false, null, 0, 4);
    if (($ext === 'pdf' && $head !== '%PDF') || (in_array($ext, ['zip', 'epub'], true) && substr($head, 0, 2) !== 'PK')) {
        $err = 'El contenido del archivo no coincide con su extensión (.' . $ext . ').';
        return null;
    }

    // Nombre seguro: minúsculas, sin espacios ni acentos.
    $base = pathinfo($orig, PATHINFO_FILENAME);
    $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $base);
    $base = strtolower($t !== false && $t !== '' ? $t : $base);
    $base = trim((string) preg_replace('/[^a-z0-9._-]+/', '_', $base), '._-');
    if ($base === '') {
        $base = 'archivo';
    }
    $name = $base . '.' . $ext;
    if (shop_reserved($name)) {
        $err = 'Ese nombre de archivo está reservado. Cámbiale el nombre e inténtalo de nuevo.';
        return null;
    }

    // Si ya existe otro archivo con ese nombre, no lo pisa (salvo que sea el actual del mismo producto).
    $n = 2;
    while (is_file($dir . $name) && $name !== $currentFile) {
        $name = $base . '-' . $n++ . '.' . $ext;
    }

    if (!is_dir($dir) || !is_writable($dir) || !move_uploaded_file($f['tmp_name'], $dir . $name)) {
        $err = 'No pude guardar el archivo en private_files (revisa los permisos de la carpeta).';
        return null;
    }
    return $name;
}

function shop_price(string $raw): ?string
{
    $raw = str_replace(',', '.', trim($raw));
    if (!preg_match('/^\d{1,4}(\.\d{1,2})?$/', $raw) || (float) $raw <= 0) {
        return null;
    }
    return number_format((float) $raw, 2, '.', '');
}

function shop_link_ok(string $u): bool
{
    return preg_match('#^https://buy\.stripe\.com/[A-Za-z0-9_\-]+$#', $u) === 1 && stripos($u, 'PEGA_AQUI') === false;
}

function shop_id_ok(string $id): bool
{
    return preg_match('/^prod_[A-Za-z0-9]{6,}$/', $id) === 1;
}

// ───────────────────────── acciones (POST) ─────────────────────────

$products = catalog_load();
$errors   = [];
$form     = null;   // valores a re-mostrar si el formulario trae errores
$formKey  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = (string) ($_POST['action'] ?? '');
    $key    = (string) preg_replace('/[^a-z0-9_-]/', '', strtolower((string) ($_POST['key'] ?? '')));

    $idx = null;
    foreach ($products as $i => $p) {
        if ($p['key'] === $key) {
            $idx = $i;
        }
    }

    if ($action === 'toggle' && $idx !== null) {
        $products[$idx]['active'] = !$products[$idx]['active'];
        if (catalog_save($products)) {
            flash($products[$idx]['active'] ? 'Producto visible en la tienda.' : 'Producto oculto de la tienda.');
        } else {
            flash('No pude guardar el catálogo (permisos de private_files).', 'err');
        }
        redirect(url('admin/shop.php'));
    }

    if ($action === 'delete' && $idx !== null) {
        $name = $products[$idx]['name'];
        array_splice($products, $idx, 1);
        if (catalog_save($products)) {
            flash('Producto «' . $name . '» eliminado. (El archivo y la imagen no se borran; el producto sigue existiendo en Stripe.)');
        } else {
            flash('No pude guardar el catálogo (permisos de private_files).', 'err');
        }
        redirect(url('admin/shop.php'));
    }

    if ($action === 'migrate') {
        if (catalog_save($products)) {
            flash('Catálogo migrado a products.json.');
        } else {
            flash('No pude crear products.json (permisos de private_files).', 'err');
        }
        redirect(url('admin/shop.php'));
    }

    if ($action === 'save') {
        if ($key !== '' && $idx === null) {
            flash('Ese producto ya no existe.', 'err');
            redirect(url('admin/shop.php'));
        }
        $isNew = ($idx === null);
        $cur   = $isNew ? [] : $products[$idx];

        $type     = (($_POST['type'] ?? '') === 'physical') ? 'physical' : 'digital';
        $name     = shop_clip(trim((string) ($_POST['name'] ?? '')), 120);
        $summary  = shop_clip(trim((string) ($_POST['summary'] ?? '')), 300);
        $currency = in_array(strtoupper((string) ($_POST['currency'] ?? '')), ['CAD', 'USD'], true) ? strtoupper((string) $_POST['currency']) : 'CAD';
        $pid      = trim((string) ($_POST['id'] ?? ''));
        $link     = trim((string) ($_POST['link'] ?? ''));
        $active   = !empty($_POST['active']);
        $sortRaw  = trim((string) ($_POST['sort'] ?? ''));
        $price    = shop_price((string) ($_POST['price'] ?? ''));

        $tags = [];
        foreach (explode(',', (string) ($_POST['tags'] ?? '')) as $t) {
            $t = shop_clip(trim($t), 20);
            if ($t !== '') {
                $tags[] = $t;
            }
        }
        $tags = array_slice($tags, 0, 5);

        // ── validaciones ──
        if ($name === '') {
            $errors[] = 'Falta el nombre del producto.';
        }
        if ($price === null) {
            $errors[] = 'El precio no es válido. Usa un número como 5 o 5.00.';
        }
        if ($type === 'digital' && !shop_id_ok($pid)) {
            $errors[] = 'Un producto digital necesita su ID de Stripe (empieza con prod_).';
        } elseif ($pid !== '' && !shop_id_ok($pid)) {
            $errors[] = 'El ID de Stripe debe empezar con prod_ (o déjalo vacío).';
        }
        if ($pid !== '') {
            foreach ($products as $other) {
                if ($other['key'] !== ($cur['key'] ?? '') && $other['id'] === $pid) {
                    $errors[] = 'Ya existe otro producto con ese ID de Stripe («' . $other['name'] . '»).';
                    break;
                }
            }
        }
        if ($link !== '' && !shop_link_ok($link)) {
            $errors[] = 'El enlace de pago debe ser un Payment Link de Stripe: https://buy.stripe.com/…';
        }

        // ── archivo digital e imagen (solo si lo anterior está bien, para no dejar archivos huérfanos) ──
        $file  = (string) ($cur['file'] ?? '');
        $image = (string) ($cur['image'] ?? '');

        if (!$errors) {
            if ($type === 'digital') {
                $upErr = null;
                $up    = shop_save_file('file_upload', $privateDir, $file, $upErr);
                $pick  = trim((string) ($_POST['file_existing'] ?? ''));
                if ($upErr !== null) {
                    $errors[] = $upErr;
                } elseif ($up !== null) {
                    $file = $up;
                } elseif ($pick === '') {
                    $file = '';
                } elseif (in_array($pick, shop_files($privateDir), true) || $pick === $file) {
                    $file = $pick;
                } else {
                    $errors[] = 'El archivo elegido no es válido.';
                }
                if ($active && $file === '' && !$errors) {
                    $errors[] = 'Un producto digital visible necesita su archivo (elige uno o sube uno nuevo).';
                }
            } else {
                $file = '';
            }
        }

        if (!$errors) {
            if (!empty($_POST['image_remove'])) {
                $image = '';
            } else {
                $image = handle_image('image_file', (string) ($_POST['image_url'] ?? ''), $image);
            }
            if ($image !== '' && strpos($image, 'uploads/') !== 0 && strpos($image, 'https://') !== 0) {
                $errors[] = 'La imagen debe ser un archivo subido o un link que empiece con https://';
                $image = (string) ($cur['image'] ?? '');
            }
        }

        if (!$errors) {
            $maxSort = 0;
            foreach ($products as $p) {
                $maxSort = max($maxSort, (int) $p['sort']);
            }
            $rec = [
                'key'      => $cur['key'] ?? '',
                'id'       => $pid,
                'active'   => $active,
                'type'     => $type,
                'name'     => $name,
                'summary'  => $summary,
                'price'    => $price,
                'currency' => $currency,
                'link'     => $link,
                'file'     => $file,
                'image'    => $image,
                'tags'     => $tags,
                'sort'     => $sortRaw !== '' && is_numeric($sortRaw) ? (int) $sortRaw : ($isNew ? $maxSort + 10 : (int) $cur['sort']),
            ];
            if ($isNew) {
                $base = catalog_slug($pid !== '' ? $pid : $name);
                $k = $base;
                $n = 2;
                $taken = array_column($products, 'key');
                while (in_array($k, $taken, true)) {
                    $k = $base . '-' . $n++;
                }
                $rec['key'] = $k;
                $products[] = $rec;
            } else {
                $products[$idx] = $rec;
            }

            if (catalog_save($products)) {
                flash($isNew ? 'Producto agregado a la tienda.' : 'Cambios guardados.');
                redirect(url('admin/shop.php'));
            }
            $errors[] = 'No pude guardar el catálogo (revisa los permisos de la carpeta private_files).';
        }

        // Hubo errores: volver al formulario con lo que escribió.
        foreach ($errors as $m) {
            flash($m, 'err');
        }
        $formKey = $key;
        $form = [
            'key' => $key, 'type' => $type, 'name' => $name, 'summary' => $summary, 'price' => (string) ($_POST['price'] ?? ''),
            'currency' => $currency, 'id' => $pid, 'link' => $link, 'tags' => implode(', ', $tags), 'sort' => $sortRaw,
            'active' => $active, 'file' => (string) ($cur['file'] ?? ''), 'image' => (string) ($cur['image'] ?? ''),
        ];
    }
}

// ───────────────────────── qué pantalla mostrar ─────────────────────────

$mode = 'list';
if ($form !== null) {
    $mode = 'form';
} elseif (isset($_GET['new'])) {
    $mode = 'form';
    $form = ['key' => '', 'type' => 'digital', 'name' => '', 'summary' => '', 'price' => '', 'currency' => 'CAD', 'id' => '', 'link' => '',
             'tags' => '', 'sort' => '', 'active' => true, 'file' => '', 'image' => ''];
} elseif (isset($_GET['edit'])) {
    $want = (string) preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $_GET['edit']));
    foreach ($products as $p) {
        if ($p['key'] === $want) {
            $mode = 'form';
            $formKey = $p['key'];
            $form = $p;
            $form['tags'] = implode(', ', $p['tags']);
            $form['sort'] = (string) $p['sort'];
        }
    }
    if ($mode === 'list') {
        flash('No encontré ese producto.', 'err');
    }
}

$host = (string) preg_replace('/[^A-Za-z0-9.:-]/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'javiermx.com'));
$redirectUrl = 'https://' . $host . url('gracias.php') . '?session_id={CHECKOUT_SESSION_ID}';
$hasJson = is_file($privateDir . 'products.json');

admin_head('Tienda');
?>
<style>
.shop-head{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:18px}
.shop-head h1{margin:0}
.scroll{overflow-x:auto}
.small{font-size:12.5px;color:var(--muted)}
.badge{display:inline-block;font-size:11px;border-radius:999px;padding:2px 9px;border:1px solid var(--line);white-space:nowrap}
.badge.dig{color:var(--accent)} .badge.phys{color:#FBBF77;border-color:#7a5320} .badge.off{color:var(--muted)} .badge.bad{color:var(--err);border-color:var(--err)}
.ok{color:var(--accent)} .warn{color:#FBBF77}
.mini{font-size:12.5px;padding:6px 12px;white-space:nowrap}
.inline{display:inline}
.help{border:1px dashed var(--accent);border-radius:10px;padding:14px 16px;color:var(--muted);font-size:13.5px;line-height:1.6;margin-bottom:6px}
.help b,.help strong{color:var(--text)}
.copyrow{display:flex;gap:8px}
.copyrow .in{font-family:var(--f-mono);font-size:13px}
.grid2{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:14px}
h2.sec{font-size:16px;margin:26px 0 10px}
.check{display:flex;align-items:center;gap:8px;margin-top:18px;font-size:14px}
.preview{width:160px;height:96px;object-fit:cover;border-radius:8px;border:1px solid var(--line);background:var(--surface2)}
</style>

<?php if ($mode === 'list'): ?>

<div class="shop-head">
<h1 class="page">Tienda</h1>
<div style="display:flex;gap:10px;flex-wrap:wrap">
<a class="btn ghost" href="<?= e(url('store.php')) ?>" target="_blank">Ver tienda ↗</a>
<a class="btn" href="<?= e(url('admin/shop.php?new=1')) ?>">+ Nuevo producto</a>
</div>
</div>

<?php if (!$hasJson): ?>
<div class="flash" style="border-style:dashed;border-color:var(--accent);color:var(--muted)">
Todavía estás usando el catálogo antiguo (<span class="mono">products.php</span>). En cuanto guardes un cambio aquí se crea
<span class="mono">products.json</span> y pasa a ser la fuente. También puedes hacerlo ahora:
<form method="post" class="inline" style="margin-left:8px"><?= csrf_field() ?><input type="hidden" name="action" value="migrate"><button class="btn ghost mini" type="submit">Migrar ahora</button></form>
</div>
<?php endif; ?>

<div class="card scroll">
<table>
<tr><th></th><th>Producto</th><th>Tipo</th><th>Precio</th><th>Enlace</th><th>Archivo</th><th>Estado</th><th></th></tr>
<?php foreach ($products as $p): ?>
<tr>
<td><?php if ($p['image'] !== ''): ?><img class="thumb" src="<?= e(img_src($p['image'])) ?>" alt=""><?php else: ?><div class="thumb"></div><?php endif; ?></td>
<td>
<div><?= e($p['name']) ?></div>
<?php foreach ($p['tags'] as $t): ?><span class="tag"><?= e($t) ?></span><?php endforeach; ?>
<?php if ($p['id'] !== ''): ?><div class="small mono" style="margin-top:3px"><?= e($p['id']) ?></div><?php endif; ?>
</td>
<td><?= $p['type'] === 'physical' ? '<span class="badge phys">Físico</span>' : '<span class="badge dig">Digital</span>' ?></td>
<td style="white-space:nowrap"><?= e('$' . number_format((float) $p['price'], 2) . ' ' . $p['currency']) ?></td>
<td><?= shop_link_ok($p['link']) ? '<span class="ok">✓</span>' : '<span class="warn">Coming soon</span>' ?></td>
<td>
<?php if ($p['type'] === 'physical'): ?><span class="muted">—</span>
<?php elseif ($p['file'] !== '' && is_file($privateDir . $p['file'])): ?><span class="ok">✓</span> <span class="small mono"><?= e($p['file']) ?></span>
<?php elseif ($p['file'] !== ''): ?><span class="badge bad">no encontrado</span> <span class="small mono"><?= e($p['file']) ?></span>
<?php else: ?><span class="badge bad">falta</span><?php endif; ?>
</td>
<td>
<form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="key" value="<?= e($p['key']) ?>">
<button class="btn ghost mini" type="submit" title="Clic para cambiar"><?= $p['active'] ? '● Visible' : '○ Oculto' ?></button></form>
</td>
<td style="white-space:nowrap">
<a class="btn ghost mini" href="<?= e(url('admin/shop.php?edit=' . urlencode($p['key']))) ?>">Editar</a>
<form method="post" class="inline" data-confirm="<?= e('¿Eliminar «' . $p['name'] . '»?' . ($p['type'] === 'digital' ? ' Si ya lo vendiste, quienes lo compraron dejarán de poder descargarlo. Mejor ocúltalo.' : '')) ?>">
<?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="key" value="<?= e($p['key']) ?>">
<button class="btn danger mini" type="submit">Eliminar</button></form>
</td>
</tr>
<?php endforeach; ?>
<?php if (!$products): ?><tr><td colspan="8" class="muted">Todavía no hay productos. Pulsa «+ Nuevo producto».</td></tr><?php endif; ?>
</table>
</div>
<p class="small" style="margin-top:14px">
«Oculto» quita el producto de la tienda pero conserva su descarga para quien ya lo compró. «Eliminar» lo quita del catálogo (no borra nada en Stripe).
</p>

<?php else: /* ───────── formulario ───────── */
    $f = $form;
    $isEdit = $formKey !== '';
    $fileOptions = shop_files($privateDir);
    if ($f['file'] !== '' && !in_array($f['file'], $fileOptions, true)) {
        array_unshift($fileOptions, $f['file']);
    }
?>

<div class="shop-head">
<h1 class="page"><?= $isEdit ? 'Editar producto' : 'Nuevo producto' ?></h1>
<a class="btn ghost" href="<?= e(url('admin/shop.php')) ?>">← Volver</a>
</div>

<form method="post" enctype="multipart/form-data" autocomplete="off">
<?= csrf_field() ?>
<input type="hidden" name="action" value="save">
<input type="hidden" name="key" value="<?= e($formKey) ?>">

<div class="card">
<div style="font-weight:600;margin-bottom:10px">1 · Datos de Stripe</div>
<div class="help">
<b>En Stripe</b> (modo prueba o real): crea el producto con precio <b>One-off</b> y copia su ID (<span class="mono">prod_…</span>).
Luego crea el <b>Payment Link</b>
<span data-only="digital">y, en <b>After payment → Don't show confirmation page</b>, pega la dirección de abajo.</span>
<span data-only="physical" style="display:none">(en <b>Options</b> marca <b>Collect customer addresses</b>; en After payment deja <b>Show confirmation page</b>).</span>
Después pega aquí el ID y el enlace.
</div>

<div data-only="digital">
<label class="f">Dirección de redirección para el Payment Link (cópiala en Stripe)</label>
<div class="copyrow">
<input class="in" id="redirect-url" type="text" readonly value="<?= e($redirectUrl) ?>">
<button class="btn ghost" type="button" id="copy-btn">Copiar</button>
</div>
<div class="small" style="margin-top:6px">Déjala tal cual, con las llaves: Stripe cambia <span class="mono">{CHECKOUT_SESSION_ID}</span> por el número de cada compra.</div>
</div>

<div class="row">
<div>
<label class="f" for="f-id">ID del producto en Stripe <span class="muted" data-only="physical" style="display:none">(opcional)</span></label>
<input class="in mono" id="f-id" name="id" type="text" placeholder="prod_XXXXXXXXXXXXXX" value="<?= e($f['id']) ?>">
<div class="small" style="margin-top:6px">Está en la dirección del producto en Stripe: <span class="mono">…/products/prod_XXXX</span></div>
</div>
<div>
<label class="f" for="f-link">Payment Link (https://buy.stripe.com/…)</label>
<input class="in mono" id="f-link" name="link" type="text" placeholder="https://buy.stripe.com/test_…" value="<?= e($f['link']) ?>">
<div class="small" style="margin-top:6px">Vacío = en la tienda dice «Coming soon». <a href="#" id="test-link" target="_blank" rel="noopener">Probar enlace ↗</a> y comprueba que NO diga «per month» ni «Subscribe».</div>
</div>
</div>
</div>

<h2 class="sec">2 · Lo que se ve en la tienda</h2>
<div class="card">
<div class="row">
<div>
<label class="f" for="f-type">Tipo</label>
<select class="in" id="f-type" name="type">
<option value="digital"<?= $f['type'] === 'digital' ? ' selected' : '' ?>>Digital (se descarga al pagar)</option>
<option value="physical"<?= $f['type'] === 'physical' ? ' selected' : '' ?>>Físico (tú lo envías)</option>
</select>
</div>
<div>
<label class="f" for="f-name">Nombre</label>
<input class="in" id="f-name" name="name" type="text" maxlength="120" value="<?= e($f['name']) ?>" required>
</div>
</div>

<label class="f" for="f-summary">Descripción corta (se ve en la tarjeta; tu sitio está en inglés)</label>
<textarea class="in" id="f-summary" name="summary" maxlength="300" style="min-height:80px"><?= e($f['summary']) ?></textarea>

<div class="row">
<div>
<label class="f" for="f-price">Precio (solo para mostrarlo; el cobro real lo define Stripe)</label>
<input class="in" id="f-price" name="price" type="text" inputmode="decimal" placeholder="5.00" value="<?= e($f['price']) ?>" required>
</div>
<div>
<label class="f" for="f-cur">Moneda</label>
<select class="in" id="f-cur" name="currency">
<option value="CAD"<?= $f['currency'] === 'CAD' ? ' selected' : '' ?>>CAD</option>
<option value="USD"<?= $f['currency'] === 'USD' ? ' selected' : '' ?>>USD</option>
</select>
</div>
<div>
<label class="f" for="f-tags">Etiquetas (separadas por coma)</label>
<input class="in" id="f-tags" name="tags" type="text" placeholder="PDF, Spanish" value="<?= e($f['tags']) ?>">
</div>
<div>
<label class="f" for="f-sort">Orden (menor = primero)</label>
<input class="in" id="f-sort" name="sort" type="text" inputmode="numeric" placeholder="auto" value="<?= e($f['sort']) ?>">
</div>
</div>

<label class="check"><input type="checkbox" name="active" value="1"<?= $f['active'] ? ' checked' : '' ?>> Visible en la tienda</label>
</div>

<h2 class="sec">3 · Imagen</h2>
<div class="card">
<div style="display:flex;gap:18px;align-items:flex-start;flex-wrap:wrap">
<?php if ($f['image'] !== ''): ?>
<div>
<img class="preview" src="<?= e(img_src($f['image'])) ?>" alt="Imagen actual">
<label class="check" style="margin-top:8px"><input type="checkbox" name="image_remove" value="1"> Quitar imagen</label>
</div>
<?php endif; ?>
<div style="flex:1;min-width:240px">
<label class="f" style="margin-top:0" for="f-imgfile">Subir imagen (JPG, PNG o WEBP · máx. 5 MB)</label>
<input class="in" id="f-imgfile" name="image_file" type="file" accept="image/jpeg,image/png,image/webp,image/gif">
<label class="f" for="f-imgurl">…o pegar un link https:// de una imagen</label>
<input class="in" id="f-imgurl" name="image_url" type="text" placeholder="https://…">
<div class="small" style="margin-top:6px">Tamaño ideal: 900 × 540 px, horizontal. La tarjeta recorta los bordes, así que deja lo importante al centro. Subirla aquí es más estable que usar un link de otro sitio.</div>
</div>
</div>
</div>

<div data-only="digital">
<h2 class="sec">4 · Archivo para descargar</h2>
<div class="card">
<div class="row">
<div>
<label class="f" style="margin-top:0" for="f-file">Archivo que ya está en private_files</label>
<select class="in" id="f-file" name="file_existing">
<option value="">— ninguno —</option>
<?php foreach ($fileOptions as $fn): ?>
<option value="<?= e($fn) ?>"<?= $f['file'] === $fn ? ' selected' : '' ?>><?= e($fn) ?><?= (!is_file($privateDir . $fn)) ? ' (no encontrado)' : '' ?></option>
<?php endforeach; ?>
</select>
</div>
<div>
<label class="f" style="margin-top:0" for="f-up">…o subir uno nuevo (PDF, ZIP, EPUB, TXT, MD)</label>
<input class="in" id="f-up" name="file_upload" type="file" accept=".pdf,.zip,.epub,.txt,.md">
</div>
</div>
<div class="small" style="margin-top:8px">Límite de subida del servidor ahora mismo: <span class="mono"><?= e((string) ini_get('upload_max_filesize')) ?></span>. El archivo se guarda fuera de public_html: nadie lo abre por URL sin pagar. Si subes uno nuevo, reemplaza al elegido.</div>
</div>
</div>

<div class="actions">
<button class="btn" type="submit">Guardar</button>
<a class="btn ghost" href="<?= e(url('admin/shop.php')) ?>">Cancelar</a>
</div>
</form>

<?php endif; ?>

<script>
(function () {
  document.querySelectorAll('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (ev) { if (!confirm(f.getAttribute('data-confirm'))) ev.preventDefault(); });
  });

  var typeSel = document.getElementById('f-type');
  if (!typeSel) return;
  function sync() {
    var dig = typeSel.value === 'digital';
    document.querySelectorAll('[data-only="digital"]').forEach(function (el) { el.style.display = dig ? '' : 'none'; });
    document.querySelectorAll('[data-only="physical"]').forEach(function (el) { el.style.display = dig ? 'none' : ''; });
  }
  typeSel.addEventListener('change', sync); sync();

  var copyBtn = document.getElementById('copy-btn'), red = document.getElementById('redirect-url');
  if (copyBtn && red) copyBtn.addEventListener('click', function () {
    red.select();
    (navigator.clipboard ? navigator.clipboard.writeText(red.value) : Promise.reject()).catch(function () { document.execCommand('copy'); });
    copyBtn.textContent = '¡Copiado!'; setTimeout(function () { copyBtn.textContent = 'Copiar'; }, 1500);
  });

  var test = document.getElementById('test-link'), link = document.getElementById('f-link');
  if (test && link) test.addEventListener('click', function (ev) {
    var v = link.value.trim();
    if (!/^https:\/\/buy\.stripe\.com\//.test(v)) { ev.preventDefault(); alert('Primero pega un Payment Link válido (https://buy.stripe.com/…).'); return; }
    test.href = v;
  });
})();
</script>
<?php admin_foot(); ?>
