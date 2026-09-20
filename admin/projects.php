<?php
require __DIR__ . '/../config.php';
require_login();
require __DIR__ . '/_layout.php';

$action = $_GET['action'] ?? 'list';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $do = $_POST['do'] ?? '';
    if ($do === 'save') {
        $id    = (int) ($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $kind  = trim($_POST['kind'] ?? 'Personal');
        $desc  = trim($_POST['description'] ?? '');
        $link  = trim($_POST['link'] ?? '');
        $sort  = (int) ($_POST['sort_order'] ?? 0);
        if ($title === '') { flash('El título es obligatorio.', 'err'); redirect(url('admin/projects.php')); }
        $slug = unique_slug('projects', slugify($_POST['slug'] ?: $title), $id);
        $existing = '';
        if ($id) {
            $st = db()->prepare('SELECT image FROM projects WHERE id=?'); $st->execute([$id]);
            $existing = (string) $st->fetchColumn();
        }
        $image = handle_image('image_file', $_POST['image_url'] ?? '', $existing);
        if ($id) {
            db()->prepare('UPDATE projects SET title=?, slug=?, kind=?, description=?, link=?, image=?, sort_order=? WHERE id=?')
                ->execute([$title, $slug, $kind, $desc, $link, $image, $sort, $id]);
            flash('Proyecto actualizado.');
        } else {
            db()->prepare('INSERT INTO projects (title, slug, kind, description, link, image, sort_order) VALUES (?,?,?,?,?,?,?)')
                ->execute([$title, $slug, $kind, $desc, $link, $image, $sort]);
            flash('Proyecto creado.');
        }
    } elseif ($do === 'delete') {
        db()->prepare('DELETE FROM projects WHERE id=?')->execute([(int) $_POST['id']]);
        flash('Proyecto eliminado.');
    }
    redirect(url('admin/projects.php'));
}

if ($action === 'form') {
    $id = (int) ($_GET['id'] ?? 0);
    $item = ['id' => 0, 'title' => '', 'slug' => '', 'kind' => 'Personal', 'description' => '', 'link' => '', 'image' => '', 'sort_order' => 0];
    if ($id) {
        $st = db()->prepare('SELECT * FROM projects WHERE id=?'); $st->execute([$id]);
        $item = $st->fetch() ?: $item;
    }
    admin_head($id ? 'Editar proyecto' : 'Nuevo proyecto');
    ?>
    <h1 class="page"><?= $id ? 'Editar' : 'Nuevo' ?> proyecto</h1>
    <form method="post" enctype="multipart/form-data" class="card" style="max-width:640px;">
    <?= csrf_field() ?>
    <input type="hidden" name="do" value="save">
    <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
    <label class="f" for="title">Título</label>
    <input class="in" id="title" name="title" value="<?= e($item['title']) ?>" required>
    <div class="row">
      <div>
        <label class="f" for="kind">Tipo</label>
        <input class="in" id="kind" name="kind" list="kinds" value="<?= e($item['kind']) ?>">
        <datalist id="kinds"><option value="Personal"><option value="Client work"><option value="Open source"></datalist>
      </div>
      <div><label class="f" for="sort">Orden</label><input class="in" id="sort" name="sort_order" type="number" value="<?= (int) $item['sort_order'] ?>"></div>
    </div>
    <label class="f" for="desc">Descripción</label>
    <textarea class="in" id="desc" name="description" rows="3"><?= e($item['description']) ?></textarea>
    <label class="f" for="link">Enlace (URL del proyecto o repo)</label>
    <input class="in" id="link" name="link" value="<?= e($item['link']) ?>" placeholder="https://github.com/…">
    <label class="f" for="url">Imagen — pega un link</label>
    <input class="in" id="url" name="image_url" value="<?= e($item['image']) ?>" placeholder="https://…">
    <label class="f" for="file">…o sube un archivo (reemplaza el link)</label>
    <input class="in" id="file" name="image_file" type="file" accept="image/*">
    <?php $img = img_src($item['image']); if ($img): ?><div style="margin-top:12px"><img class="thumb" style="width:160px;height:90px" src="<?= e($img) ?>"></div><?php endif; ?>
    <div class="actions"><button class="btn">Guardar</button><a class="btn ghost" href="<?= e(url('admin/projects.php')) ?>">Cancelar</a></div>
    </form>
    <?php
    admin_foot();
    exit;
}

$rows = db()->query('SELECT * FROM projects ORDER BY sort_order, id')->fetchAll();
admin_head('Proyectos');
?>
<h1 class="page">Proyectos</h1>
<div class="actions"><a class="btn" href="<?= e(url('admin/projects.php?action=form')) ?>">+ Nuevo proyecto</a></div>
<div class="card">
<table>
<tr><th></th><th>Título</th><th>Tipo</th><th>Orden</th><th></th></tr>
<?php foreach ($rows as $r): $img = img_src($r['image']); ?>
<tr>
<td><?php if ($img): ?><img class="thumb" src="<?= e($img) ?>"><?php else: ?><span class="muted mono">—</span><?php endif; ?></td>
<td><?= e($r['title']) ?></td>
<td class="muted"><?= e($r['kind']) ?></td>
<td class="muted"><?= (int) $r['sort_order'] ?></td>
<td style="text-align:right;">
<a href="<?= e(url('admin/projects.php?action=form&id=' . $r['id'])) ?>">Editar</a>
<form method="post" style="display:inline" onsubmit="return confirm('¿Eliminar?')">
<?= csrf_field() ?><input type="hidden" name="do" value="delete"><input type="hidden" name="id" value="<?= $r['id'] ?>">
<button class="btn danger" style="padding:5px 12px;margin-left:8px;">Borrar</button>
</form>
</td>
</tr>
<?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="5" class="muted">Sin proyectos todavía.</td></tr><?php endif; ?>
</table>
</div>
<?php admin_foot(); ?>
