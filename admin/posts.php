<?php
require __DIR__ . '/../config.php';
require_login();
require __DIR__ . '/_layout.php';

$action = $_GET['action'] ?? 'list';

/** Convierte "arduino, longevity" en ids de tags (creándolos si no existen). */
function sync_tags(int $postId, string $csv): void {
    $names = array_filter(array_map('trim', explode(',', $csv)));
    $ids = [];
    foreach ($names as $name) {
        $slug = slugify($name);
        $st = db()->prepare('SELECT id FROM tags WHERE slug=?');
        $st->execute([$slug]);
        $tid = $st->fetchColumn();
        if (!$tid) {
            db()->prepare('INSERT INTO tags (name, slug) VALUES (?, ?)')->execute([$name, $slug]);
            $tid = (int) db()->lastInsertId();
        }
        $ids[(int) $tid] = true;
    }
    db()->prepare('DELETE FROM post_tags WHERE post_id=?')->execute([$postId]);
    $ins = db()->prepare('INSERT INTO post_tags (post_id, tag_id) VALUES (?, ?)');
    foreach (array_keys($ids) as $tid) {
        $ins->execute([$postId, $tid]);
    }
}

/** Crea las columnas de la demo (PWA) la primera vez. Devuelve false si la BD no lo permite. */
function ensure_demo_columns(): bool {
    try {
        $cols = db()->query("SHOW COLUMNS FROM posts LIKE 'demo\\_%'")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('demo_url', $cols, true)) {
            db()->exec("ALTER TABLE posts ADD COLUMN demo_url VARCHAR(500) NOT NULL DEFAULT ''");
        }
        if (!in_array('demo_note', $cols, true)) {
            db()->exec("ALTER TABLE posts ADD COLUMN demo_note VARCHAR(255) NOT NULL DEFAULT ''");
        }
        return true;
    } catch (PDOException $ex) {
        return false;
    }
}

/** Link de la demo: ruta del sitio ("/pwa/x/") o https://…  Devuelve '' si no es válido. */
function clean_demo_url(string $u): string {
    $u = trim($u);
    if ($u === '') return '';
    if (preg_match('#^[\w-]+(\.[\w-]+)+/#', $u)) $u = 'https://' . $u; // "javiermx.com/pwa/x/"
    $u = preg_replace('#^http://#i', 'https://', $u);
    if (preg_match('#^https://#i', $u)) return filter_var($u, FILTER_VALIDATE_URL) ? $u : '';
    $u = '/' . ltrim($u, '/');
    return preg_match('#^/[\w\-./~%]*$#', $u) ? $u : '';
}

$demoOk = ensure_demo_columns();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $do = $_POST['do'] ?? '';
    if ($do === 'save') {
        $id      = (int) ($_POST['id'] ?? 0);
        $title   = trim($_POST['title'] ?? '');
        $excerpt = trim($_POST['excerpt'] ?? '');
        $body    = $_POST['body'] ?? '';
        $catId   = (int) ($_POST['category_id'] ?? 0) ?: null;
        $status  = ($_POST['status'] ?? 'draft') === 'published' ? 'published' : 'draft';
        $seoT    = trim($_POST['seo_title'] ?? '');
        $seoD    = trim($_POST['seo_description'] ?? '');
        $tagsCsv = $_POST['tags'] ?? '';
        if ($title === '') { flash('El título es obligatorio.', 'err'); redirect(url('admin/posts.php')); }
        $slug = unique_slug('posts', slugify($_POST['slug'] ?: $title), $id);
        $existing = '';
        if ($id) {
            $st = db()->prepare('SELECT cover_image FROM posts WHERE id=?'); $st->execute([$id]);
            $existing = (string) $st->fetchColumn();
        }
        $cover = handle_image('cover_file', $_POST['cover_url'] ?? '', $existing);
        if ($id) {
            db()->prepare('UPDATE posts SET title=?, slug=?, excerpt=?, body=?, cover_image=?, category_id=?, status=?, seo_title=?, seo_description=? WHERE id=?')
                ->execute([$title, $slug, $excerpt, $body, $cover, $catId, $status, $seoT, $seoD, $id]);
        } else {
            db()->prepare('INSERT INTO posts (title, slug, excerpt, body, cover_image, category_id, status, seo_title, seo_description) VALUES (?,?,?,?,?,?,?,?,?)')
                ->execute([$title, $slug, $excerpt, $body, $cover, $catId, $status, $seoT, $seoD]);
            $id = (int) db()->lastInsertId();
        }
        sync_tags($id, $tagsCsv);
        $demoRaw = trim($_POST['demo_url'] ?? '');
        $demoUrl = clean_demo_url($demoRaw);
        if ($demoOk) {
            db()->prepare('UPDATE posts SET demo_url=?, demo_note=? WHERE id=?')
                ->execute([$demoUrl, mb_substr(trim($_POST['demo_note'] ?? ''), 0, 255), $id]);
        }
        if ($demoRaw !== '' && $demoUrl === '') {
            flash('Post guardado, pero el link de la demo no es válido (usa /pwa/nombre/ o https://…).', 'err');
            redirect(url('admin/posts.php?action=form&id=' . $id));
        }
        flash('Post guardado.');
    } elseif ($do === 'delete') {
        db()->prepare('DELETE FROM posts WHERE id=?')->execute([(int) $_POST['id']]);
        flash('Post eliminado.');
    }
    redirect(url('admin/posts.php'));
}

if ($action === 'form') {
    $id = (int) ($_GET['id'] ?? 0);
    $item = ['id' => 0, 'title' => '', 'slug' => '', 'excerpt' => '', 'body' => '', 'cover_image' => '',
             'category_id' => null, 'status' => 'draft', 'seo_title' => '', 'seo_description' => '',
             'demo_url' => '', 'demo_note' => ''];
    $tagsCsv = '';
    if ($id) {
        $st = db()->prepare('SELECT * FROM posts WHERE id=?'); $st->execute([$id]);
        $item = $st->fetch() ?: $item;
        $tt = db()->prepare('SELECT t.name FROM tags t JOIN post_tags pt ON pt.tag_id=t.id WHERE pt.post_id=? ORDER BY t.name');
        $tt->execute([$id]);
        $tagsCsv = implode(', ', array_column($tt->fetchAll(), 'name'));
    }
    $cats = db()->query('SELECT * FROM categories ORDER BY name')->fetchAll();
    admin_head($id ? 'Editar post' : 'Nuevo post');
    ?>
    <h1 class="page"><?= $id ? 'Editar' : 'Nuevo' ?> post</h1>
    <form method="post" enctype="multipart/form-data" class="card" style="max-width:760px;">
    <?= csrf_field() ?>
    <input type="hidden" name="do" value="save">
    <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">

    <label class="f" for="title">Título</label>
    <input class="in" id="title" name="title" value="<?= e($item['title']) ?>" required>

    <div class="row">
      <div>
        <label class="f" for="cat">Categoría</label>
        <select class="in" id="cat" name="category_id">
          <option value="">— sin categoría —</option>
          <?php foreach ($cats as $c): ?>
          <option value="<?= $c['id'] ?>" <?= ((int) $item['category_id'] === (int) $c['id']) ? 'selected' : '' ?>><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="f" for="status">Estado</label>
        <select class="in" id="status" name="status">
          <option value="draft" <?= $item['status'] === 'draft' ? 'selected' : '' ?>>Borrador</option>
          <option value="published" <?= $item['status'] === 'published' ? 'selected' : '' ?>>Publicado</option>
        </select>
      </div>
    </div>

    <label class="f" for="tags">Etiquetas (separadas por coma)</label>
    <input class="in" id="tags" name="tags" value="<?= e($tagsCsv) ?>" placeholder="arduino, longevity">

    <label class="f" for="excerpt">Extracto (resumen corto para las tarjetas)</label>
    <textarea class="in" id="excerpt" name="excerpt" rows="2"><?= e($item['excerpt']) ?></textarea>

    <label class="f" for="body">Contenido (acepta HTML)</label>
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:8px">
      <select class="in" id="code-lang" style="width:auto;padding:7px 10px;font-size:13px">
        <?php foreach (['php', 'python', 'java', 'javascript', 'bash', 'arduino', 'cpp', 'c', 'html', 'css', 'sql', 'json', 'text'] as $l): ?>
        <option value="<?= $l ?>"><?= $l ?></option>
        <?php endforeach; ?>
      </select>
      <button type="button" class="btn ghost" id="code-insert" style="padding:7px 14px;font-size:13px">&lt;/&gt; Insertar código</button>
      <span class="muted" style="font-size:12px">Pega el código entre las líneas <span class="mono">```</span> — se mostrará en caja con botón de copiar.</span>
    </div>
    <textarea class="in mono" id="body" name="body" rows="16" style="font-size:14px"><?= e($item['body']) ?></textarea>
    <script>
    document.getElementById('code-insert').addEventListener('click', function () {
      var ta = document.getElementById('body');
      var lang = document.getElementById('code-lang').value;
      var s = ta.selectionStart, en = ta.selectionEnd, v = ta.value;
      var sel = v.slice(s, en) || 'pega tu código aquí';
      var before = (s > 0 && v[s - 1] !== '\n') ? '\n' : '';
      var block = before + '```' + lang + '\n' + sel + '\n```\n';
      ta.value = v.slice(0, s) + block + v.slice(en);
      var start = s + before.length + 3 + lang.length + 1;
      ta.focus();
      ta.setSelectionRange(start, start + sel.length);
    });
    </script>

    <label class="f" for="url">Imagen de portada — pega un link</label>
    <input class="in" id="url" name="cover_url" value="<?= e($item['cover_image']) ?>" placeholder="https://…">
    <label class="f" for="file">…o sube un archivo (reemplaza el link)</label>
    <input class="in" id="file" name="cover_file" type="file" accept="image/*">
    <?php $img = img_src($item['cover_image']); if ($img): ?><div style="margin-top:12px"><img class="thumb" style="width:180px;height:100px" src="<?= e($img) ?>"></div><?php endif; ?>

    <div style="border-top:1px solid var(--line);margin:22px 0 0;padding-top:8px">
    <?php if ($demoOk): ?>
    <label class="f" for="demo">Demo en vivo (PWA) — link (opcional)</label>
    <input class="in mono" id="demo" name="demo_url" value="<?= e($item['demo_url'] ?? '') ?>" placeholder="/pwa/pwa-vibrate/  o  https://…">
    <label class="f" for="demon">Nota de la demo (opcional)</label>
    <input class="in" id="demon" name="demo_note" maxlength="255" value="<?= e($item['demo_note'] ?? '') ?>" placeholder="Works on Android. iPhone: no vibration.">
    <div id="demo-prev" style="display:none;align-items:center;gap:14px;margin-top:12px">
      <div id="demo-qr" style="width:96px;height:96px;background:#fff;border-radius:10px;padding:6px"></div>
      <span class="muted" style="font-size:13px">Así se verá el QR en el post.<br><a id="demo-open" href="#" target="_blank" rel="noopener">Abrir la demo ↗</a></span>
    </div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcode-generator/1.4.4/qrcode.min.js"></script>
    <script>
    (function () {
      var inp = document.getElementById('demo'), box = document.getElementById('demo-prev');
      function abs(u) {
        u = u.trim();
        if (!u) return '';
        if (/^[\w-]+(\.[\w-]+)+\//.test(u)) u = 'https://' + u;
        if (/^https?:\/\//i.test(u)) return u.replace(/^http:/i, 'https:');
        return location.origin + '/' + u.replace(/^\/+/, '');
      }
      function draw() {
        var u = abs(inp.value);
        if (!u || !window.qrcode) { box.style.display = 'none'; return; }
        var qr = qrcode(0, 'M'); qr.addData(u); qr.make();
        document.getElementById('demo-qr').innerHTML = qr.createSvgTag({ cellSize: 2, margin: 0, scalable: true });
        document.getElementById('demo-open').href = u;
        box.style.display = 'flex';
      }
      inp.addEventListener('input', draw);
      window.addEventListener('load', draw);
    })();
    </script>
    <?php else: ?>
    <p class="muted" style="font-size:13px">No se pudieron crear las columnas de la demo en la base de datos. En phpMyAdmin ejecuta:<br>
    <span class="mono">ALTER TABLE posts ADD demo_url VARCHAR(500) NOT NULL DEFAULT '', ADD demo_note VARCHAR(255) NOT NULL DEFAULT '';</span></p>
    <?php endif; ?>
    </div>

    <label class="f" for="slug">Slug (opcional)</label>
    <input class="in" id="slug" name="slug" value="<?= e($item['slug']) ?>" placeholder="se genera del título">

    <div style="border-top:1px solid var(--line);margin:22px 0 0;padding-top:8px">
    <label class="f" for="seot">SEO — título (opcional)</label>
    <input class="in" id="seot" name="seo_title" value="<?= e($item['seo_title']) ?>" placeholder="Si lo dejas vacío usa el título">
    <label class="f" for="seod">SEO — descripción (opcional)</label>
    <textarea class="in" id="seod" name="seo_description" rows="2" placeholder="Si lo dejas vacío usa el extracto"><?= e($item['seo_description']) ?></textarea>
    </div>

    <div class="actions"><button class="btn">Guardar</button><a class="btn ghost" href="<?= e(url('admin/posts.php')) ?>">Cancelar</a></div>
    </form>
    <?php
    admin_foot();
    exit;
}

$rows = db()->query(
    'SELECT p.*, c.name AS cat_name FROM posts p LEFT JOIN categories c ON c.id=p.category_id ORDER BY p.created_at DESC'
)->fetchAll();
admin_head('Posts');
?>
<h1 class="page">Posts</h1>
<div class="actions"><a class="btn" href="<?= e(url('admin/posts.php?action=form')) ?>">+ Nuevo post</a></div>
<div class="card">
<table>
<tr><th>Título</th><th>Categoría</th><th>Estado</th><th>Fecha</th><th></th></tr>
<?php foreach ($rows as $r): ?>
<tr>
<td><?= e($r['title']) ?></td>
<td class="muted"><?= e($r['cat_name'] ?? '—') ?></td>
<td><?= $r['status'] === 'published' ? '<span class="tag">Publicado</span>' : '<span class="muted">Borrador</span>' ?></td>
<td class="muted mono" style="font-size:12px"><?= e(date('Y-m-d', strtotime($r['created_at']))) ?></td>
<td style="text-align:right;">
<a href="<?= e(url('post.php?slug=' . urlencode($r['slug']))) ?>" target="_blank">Ver</a>
<a href="<?= e(url('admin/posts.php?action=form&id=' . $r['id'])) ?>" style="margin-left:10px">Editar</a>
<form method="post" style="display:inline" onsubmit="return confirm('¿Eliminar este post?')">
<?= csrf_field() ?><input type="hidden" name="do" value="delete"><input type="hidden" name="id" value="<?= $r['id'] ?>">
<button class="btn danger" style="padding:5px 12px;margin-left:8px;">Borrar</button>
</form>
</td>
</tr>
<?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="5" class="muted">Sin posts todavía.</td></tr><?php endif; ?>
</table>
</div>
<?php admin_foot(); ?>
