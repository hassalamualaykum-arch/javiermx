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
             'category_id' => null, 'status' => 'draft', 'seo_title' => '', 'seo_description' => ''];
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
    <textarea class="in" id="body" name="body" rows="12"><?= e($item['body']) ?></textarea>

    <label class="f" for="url">Imagen de portada — pega un link</label>
    <input class="in" id="url" name="cover_url" value="<?= e($item['cover_image']) ?>" placeholder="https://…">
    <label class="f" for="file">…o sube un archivo (reemplaza el link)</label>
    <input class="in" id="file" name="cover_file" type="file" accept="image/*">
    <?php $img = img_src($item['cover_image']); if ($img): ?><div style="margin-top:12px"><img class="thumb" style="width:180px;height:100px" src="<?= e($img) ?>"></div><?php endif; ?>

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
