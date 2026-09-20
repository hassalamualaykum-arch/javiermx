<?php
require __DIR__ . '/../config.php';
require_login();
require __DIR__ . '/_layout.php';

$action = $_GET['action'] ?? 'list';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $do = $_POST['do'] ?? '';
    if ($do === 'save') {
        $id   = (int) ($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        if ($name === '') { flash('El nombre es obligatorio.', 'err'); redirect(url('admin/categories.php')); }
        $slug = unique_slug('categories', slugify($_POST['slug'] ?: $name), $id);
        if ($id) {
            db()->prepare('UPDATE categories SET name=?, slug=? WHERE id=?')->execute([$name, $slug, $id]);
            flash('Categoría actualizada.');
        } else {
            db()->prepare('INSERT INTO categories (name, slug) VALUES (?, ?)')->execute([$name, $slug]);
            flash('Categoría creada.');
        }
    } elseif ($do === 'delete') {
        db()->prepare('DELETE FROM categories WHERE id=?')->execute([(int) $_POST['id']]);
        flash('Categoría eliminada.');
    }
    redirect(url('admin/categories.php'));
}

if ($action === 'form') {
    $id = (int) ($_GET['id'] ?? 0);
    $item = ['id' => 0, 'name' => '', 'slug' => ''];
    if ($id) {
        $st = db()->prepare('SELECT * FROM categories WHERE id=?');
        $st->execute([$id]);
        $item = $st->fetch() ?: $item;
    }
    admin_head($id ? 'Editar categoría' : 'Nueva categoría');
    ?>
    <h1 class="page"><?= $id ? 'Editar' : 'Nueva' ?> categoría</h1>
    <form method="post" class="card" style="max-width:520px;">
    <?= csrf_field() ?>
    <input type="hidden" name="do" value="save">
    <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
    <label class="f" for="name">Nombre</label>
    <input class="in" id="name" name="name" value="<?= e($item['name']) ?>" required>
    <label class="f" for="slug">Slug (opcional)</label>
    <input class="in" id="slug" name="slug" value="<?= e($item['slug']) ?>" placeholder="se genera solo">
    <div class="actions"><button class="btn">Guardar</button><a class="btn ghost" href="<?= e(url('admin/categories.php')) ?>">Cancelar</a></div>
    </form>
    <?php
    admin_foot();
    exit;
}

$rows = db()->query('SELECT * FROM categories ORDER BY name')->fetchAll();
admin_head('Categorías');
?>
<h1 class="page">Categorías</h1>
<div class="actions"><a class="btn" href="<?= e(url('admin/categories.php?action=form')) ?>">+ Nueva categoría</a></div>
<div class="card">
<table>
<tr><th>Nombre</th><th>Slug</th><th></th></tr>
<?php foreach ($rows as $r): ?>
<tr>
<td><?= e($r['name']) ?></td>
<td class="mono muted"><?= e($r['slug']) ?></td>
<td style="text-align:right;">
<a href="<?= e(url('admin/categories.php?action=form&id=' . $r['id'])) ?>">Editar</a>
<form method="post" style="display:inline" onsubmit="return confirm('¿Eliminar esta categoría?')">
<?= csrf_field() ?><input type="hidden" name="do" value="delete"><input type="hidden" name="id" value="<?= $r['id'] ?>">
<button class="btn danger" style="padding:5px 12px;margin-left:8px;">Borrar</button>
</form>
</td>
</tr>
<?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="3" class="muted">Sin categorías todavía.</td></tr><?php endif; ?>
</table>
</div>
<?php admin_foot(); ?>
