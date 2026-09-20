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
        if ($name === '') { flash('El nombre es obligatorio.', 'err'); redirect(url('admin/tags.php')); }
        $slug = unique_slug('tags', slugify($_POST['slug'] ?: $name), $id);
        if ($id) {
            db()->prepare('UPDATE tags SET name=?, slug=? WHERE id=?')->execute([$name, $slug, $id]);
            flash('Etiqueta actualizada.');
        } else {
            db()->prepare('INSERT INTO tags (name, slug) VALUES (?, ?)')->execute([$name, $slug]);
            flash('Etiqueta creada.');
        }
    } elseif ($do === 'delete') {
        db()->prepare('DELETE FROM tags WHERE id=?')->execute([(int) $_POST['id']]);
        flash('Etiqueta eliminada.');
    }
    redirect(url('admin/tags.php'));
}

if ($action === 'form') {
    $id = (int) ($_GET['id'] ?? 0);
    $item = ['id' => 0, 'name' => '', 'slug' => ''];
    if ($id) {
        $st = db()->prepare('SELECT * FROM tags WHERE id=?');
        $st->execute([$id]);
        $item = $st->fetch() ?: $item;
    }
    admin_head($id ? 'Editar etiqueta' : 'Nueva etiqueta');
    ?>
    <h1 class="page"><?= $id ? 'Editar' : 'Nueva' ?> etiqueta</h1>
    <form method="post" class="card" style="max-width:520px;">
    <?= csrf_field() ?>
    <input type="hidden" name="do" value="save">
    <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
    <label class="f" for="name">Nombre</label>
    <input class="in" id="name" name="name" value="<?= e($item['name']) ?>" required>
    <label class="f" for="slug">Slug (opcional)</label>
    <input class="in" id="slug" name="slug" value="<?= e($item['slug']) ?>" placeholder="se genera solo">
    <div class="actions"><button class="btn">Guardar</button><a class="btn ghost" href="<?= e(url('admin/tags.php')) ?>">Cancelar</a></div>
    </form>
    <?php
    admin_foot();
    exit;
}

$rows = db()->query('SELECT * FROM tags ORDER BY name')->fetchAll();
admin_head('Etiquetas');
?>
<h1 class="page">Etiquetas</h1>
<div class="actions"><a class="btn" href="<?= e(url('admin/tags.php?action=form')) ?>">+ Nueva etiqueta</a></div>
<div class="card">
<table>
<tr><th>Nombre</th><th>Slug</th><th></th></tr>
<?php foreach ($rows as $r): ?>
<tr>
<td><?= e($r['name']) ?></td>
<td class="mono muted"><?= e($r['slug']) ?></td>
<td style="text-align:right;">
<a href="<?= e(url('admin/tags.php?action=form&id=' . $r['id'])) ?>">Editar</a>
<form method="post" style="display:inline" onsubmit="return confirm('¿Eliminar esta etiqueta?')">
<?= csrf_field() ?><input type="hidden" name="do" value="delete"><input type="hidden" name="id" value="<?= $r['id'] ?>">
<button class="btn danger" style="padding:5px 12px;margin-left:8px;">Borrar</button>
</form>
</td>
</tr>
<?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="3" class="muted">Sin etiquetas todavía.</td></tr><?php endif; ?>
</table>
</div>
<?php admin_foot(); ?>
