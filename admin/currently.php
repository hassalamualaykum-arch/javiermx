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
        $city  = trim($_POST['city'] ?? '');
        $note  = trim($_POST['note'] ?? '');
        $isnow = isset($_POST['is_now']) ? 1 : 0;
        $sort  = (int) ($_POST['sort_order'] ?? 0);
        if ($city === '') { flash('La ciudad es obligatoria.', 'err'); redirect(url('admin/currently.php')); }
        $existing = '';
        if ($id) {
            $st = db()->prepare('SELECT image FROM currently WHERE id=?'); $st->execute([$id]);
            $existing = (string) $st->fetchColumn();
        }
        $image = handle_image('image_file', $_POST['image_url'] ?? '', $existing);
        if ($id) {
            db()->prepare('UPDATE currently SET city=?, note=?, image=?, is_now=?, sort_order=? WHERE id=?')
                ->execute([$city, $note, $image, $isnow, $sort, $id]);
            flash('Actualizado.');
        } else {
            db()->prepare('INSERT INTO currently (city, note, image, is_now, sort_order) VALUES (?,?,?,?,?)')
                ->execute([$city, $note, $image, $isnow, $sort]);
            flash('Agregado.');
        }
    } elseif ($do === 'delete') {
        db()->prepare('DELETE FROM currently WHERE id=?')->execute([(int) $_POST['id']]);
        flash('Eliminado.');
    }
    redirect(url('admin/currently.php'));
}

if ($action === 'form') {
    $id = (int) ($_GET['id'] ?? 0);
    $item = ['id' => 0, 'city' => '', 'note' => '', 'image' => '', 'is_now' => 0, 'sort_order' => 0];
    if ($id) {
        $st = db()->prepare('SELECT * FROM currently WHERE id=?'); $st->execute([$id]);
        $item = $st->fetch() ?: $item;
    }
    admin_head($id ? 'Editar foto' : 'Nueva foto');
    ?>
    <h1 class="page"><?= $id ? 'Editar' : 'Nueva' ?> foto (Currently)</h1>
    <form method="post" enctype="multipart/form-data" class="card" style="max-width:620px;">
    <?= csrf_field() ?>
    <input type="hidden" name="do" value="save">
    <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
    <div class="row">
      <div><label class="f" for="city">Ciudad</label><input class="in" id="city" name="city" value="<?= e($item['city']) ?>" required></div>
      <div><label class="f" for="sort">Orden</label><input class="in" id="sort" name="sort_order" type="number" value="<?= (int) $item['sort_order'] ?>"></div>
    </div>
    <label class="f" for="note">Nota corta</label>
    <input class="in" id="note" name="note" value="<?= e($item['note']) ?>" placeholder="Home base.">
    <label class="f" for="url">Imagen — pega un link</label>
    <input class="in" id="url" name="image_url" value="<?= e($item['image']) ?>" placeholder="https://…">
    <label class="f" for="file">…o sube un archivo (reemplaza el link)</label>
    <input class="in" id="file" name="image_file" type="file" accept="image/*">
    <?php $img = img_src($item['image']); if ($img): ?><div style="margin-top:12px"><img class="thumb" style="width:120px;height:90px" src="<?= e($img) ?>"></div><?php endif; ?>
    <label class="f" style="margin-top:16px"><input type="checkbox" name="is_now" <?= $item['is_now'] ? 'checked' : '' ?>> Marcar como "NOW" (dónde estás ahora)</label>
    <div class="actions"><button class="btn">Guardar</button><a class="btn ghost" href="<?= e(url('admin/currently.php')) ?>">Cancelar</a></div>
    </form>
    <?php
    admin_foot();
    exit;
}

$rows = db()->query('SELECT * FROM currently ORDER BY sort_order, id')->fetchAll();
admin_head('Currently');
?>
<h1 class="page">Currently — fotos</h1>
<div class="actions"><a class="btn" href="<?= e(url('admin/currently.php?action=form')) ?>">+ Nueva foto</a></div>
<div class="card">
<table>
<tr><th></th><th>Ciudad</th><th>Nota</th><th>Now</th><th>Orden</th><th></th></tr>
<?php foreach ($rows as $r): $img = img_src($r['image']); ?>
<tr>
<td><?php if ($img): ?><img class="thumb" src="<?= e($img) ?>"><?php else: ?><span class="muted mono">—</span><?php endif; ?></td>
<td><?= e($r['city']) ?></td>
<td class="muted"><?= e($r['note']) ?></td>
<td><?= $r['is_now'] ? '<span class="tag">NOW</span>' : '' ?></td>
<td class="muted"><?= (int) $r['sort_order'] ?></td>
<td style="text-align:right;">
<a href="<?= e(url('admin/currently.php?action=form&id=' . $r['id'])) ?>">Editar</a>
<form method="post" style="display:inline" onsubmit="return confirm('¿Eliminar?')">
<?= csrf_field() ?><input type="hidden" name="do" value="delete"><input type="hidden" name="id" value="<?= $r['id'] ?>">
<button class="btn danger" style="padding:5px 12px;margin-left:8px;">Borrar</button>
</form>
</td>
</tr>
<?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="6" class="muted">Sin fotos todavía.</td></tr><?php endif; ?>
</table>
</div>
<?php admin_foot(); ?>
