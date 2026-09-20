<?php
require __DIR__ . '/../config.php';
require_login();
require __DIR__ . '/_layout.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $do = $_POST['do'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    if ($do === 'toggle_read') {
        db()->prepare('UPDATE contact_messages SET is_read = 1 - is_read WHERE id = ?')->execute([$id]);
    } elseif ($do === 'delete') {
        db()->prepare('DELETE FROM contact_messages WHERE id = ?')->execute([$id]);
        flash('Mensaje eliminado.');
    } elseif ($do === 'block') {
        $type  = ($_POST['type'] ?? '') === 'ip' ? 'ip' : 'email';
        $value = trim($_POST['value'] ?? '');
        if ($value !== '') {
            db()->prepare('INSERT IGNORE INTO blocked_senders (type, value, reason) VALUES (?, ?, ?)')
                ->execute([$type, $value, trim($_POST['reason'] ?? '')]);
            flash('Bloqueado: ' . $value);
        }
        if ($id) {
            db()->prepare('DELETE FROM contact_messages WHERE id = ?')->execute([$id]);
        }
    } elseif ($do === 'unblock') {
        db()->prepare('DELETE FROM blocked_senders WHERE id = ?')->execute([$id]);
        flash('Bloqueo eliminado.');
    }
    redirect(url('admin/messages.php'));
}

$messages = db()->query('SELECT * FROM contact_messages ORDER BY created_at DESC')->fetchAll();
$blocked  = db()->query('SELECT * FROM blocked_senders ORDER BY created_at DESC')->fetchAll();

admin_head('Mensajes');
?>
<h1 class="page">Mensajes de contacto</h1>
<div class="card">
<table>
<tr><th>Fecha</th><th>Nombre</th><th>Email</th><th>Mensaje</th><th>IP</th><th></th></tr>
<?php foreach ($messages as $m): ?>
<tr style="<?= $m['is_read'] ? '' : 'font-weight:600;' ?>">
<td class="mono muted" style="white-space:nowrap;"><?= e(date('d M, H:i', strtotime($m['created_at']))) ?></td>
<td><?= e($m['name']) ?></td>
<td class="mono"><?= e($m['email']) ?></td>
<td style="max-width:320px;"><?= e(mb_strimwidth($m['message'], 0, 140, '…')) ?></td>
<td class="mono muted"><?= e($m['ip_address']) ?></td>
<td style="text-align:right; white-space:nowrap;">
<form method="post" style="display:inline">
<?= csrf_field() ?><input type="hidden" name="do" value="toggle_read"><input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
<button class="btn ghost" style="padding:5px 10px;"><?= $m['is_read'] ? 'Marcar no leído' : 'Marcar leído' ?></button>
</form>
<form method="post" style="display:inline" onsubmit="return confirm('¿Bloquear este email y borrar el mensaje?')">
<?= csrf_field() ?><input type="hidden" name="do" value="block"><input type="hidden" name="type" value="email"><input type="hidden" name="value" value="<?= e($m['email']) ?>"><input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
<button class="btn danger" style="padding:5px 10px;margin-left:6px;">Bloquear email</button>
</form>
<form method="post" style="display:inline" onsubmit="return confirm('¿Bloquear esta IP y borrar el mensaje?')">
<?= csrf_field() ?><input type="hidden" name="do" value="block"><input type="hidden" name="type" value="ip"><input type="hidden" name="value" value="<?= e($m['ip_address']) ?>"><input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
<button class="btn danger" style="padding:5px 10px;margin-left:6px;">Bloquear IP</button>
</form>
<form method="post" style="display:inline" onsubmit="return confirm('¿Borrar este mensaje?')">
<?= csrf_field() ?><input type="hidden" name="do" value="delete"><input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
<button class="btn danger" style="padding:5px 10px;margin-left:6px;">Borrar</button>
</form>
</td>
</tr>
<?php endforeach; ?>
<?php if (!$messages): ?><tr><td colspan="6" class="muted">Sin mensajes todavía.</td></tr><?php endif; ?>
</table>
</div>

<h1 class="page" style="margin-top:40px;">Remitentes bloqueados</h1>
<div class="card">
<form method="post" class="row" style="margin-bottom:18px; align-items:flex-end;">
<?= csrf_field() ?><input type="hidden" name="do" value="block">
<div>
<label class="f">Tipo</label>
<select class="in" name="type"><option value="email">Email</option><option value="ip">IP</option></select>
</div>
<div>
<label class="f">Valor</label>
<input class="in" name="value" placeholder="spam@ejemplo.com o 1.2.3.4" required>
</div>
<div>
<label class="f">Motivo (opcional)</label>
<input class="in" name="reason" placeholder="spam">
</div>
<div style="flex:0 0 auto;">
<button class="btn">+ Bloquear</button>
</div>
</form>
<table>
<tr><th>Tipo</th><th>Valor</th><th>Motivo</th><th>Fecha</th><th></th></tr>
<?php foreach ($blocked as $b): ?>
<tr>
<td class="mono"><?= e($b['type']) ?></td>
<td class="mono"><?= e($b['value']) ?></td>
<td class="muted"><?= e($b['reason']) ?></td>
<td class="mono muted"><?= e(date('d M, Y', strtotime($b['created_at']))) ?></td>
<td style="text-align:right;">
<form method="post" style="display:inline" onsubmit="return confirm('¿Quitar este bloqueo?')">
<?= csrf_field() ?><input type="hidden" name="do" value="unblock"><input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
<button class="btn ghost" style="padding:5px 12px;">Desbloquear</button>
</form>
</td>
</tr>
<?php endforeach; ?>
<?php if (!$blocked): ?><tr><td colspan="5" class="muted">Sin bloqueos todavía.</td></tr><?php endif; ?>
</table>
</div>
<?php admin_foot(); ?>
