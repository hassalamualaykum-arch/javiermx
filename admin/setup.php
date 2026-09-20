<?php
require __DIR__ . '/../config.php';

$count = (int) db()->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
if ($count > 0) {
    exit('Ya existe un usuario administrador. Borra este archivo (admin/setup.php) por seguridad.');
}

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $user = trim($_POST['username'] ?? '');
    $pass = $_POST['password'] ?? '';
    $pass2 = $_POST['password2'] ?? '';
    if (strlen($user) < 3) {
        $msg = 'El usuario debe tener al menos 3 caracteres.';
    } elseif (strlen($pass) < 8) {
        $msg = 'La contraseña debe tener al menos 8 caracteres.';
    } elseif ($pass !== $pass2) {
        $msg = 'Las contraseñas no coinciden.';
    } else {
        $st = db()->prepare('INSERT INTO admin_users (username, password_hash) VALUES (?, ?)');
        $st->execute([$user, password_hash($pass, PASSWORD_DEFAULT)]);
        flash('Usuario creado. Por seguridad, borra ahora el archivo admin/setup.php.', 'ok');
        redirect(url('admin/login.php'));
    }
}
?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Configurar admin · javiermx</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:wght@400;500;600&display=swap">
<style>
body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#0B0D0F;color:#ECEDEA;font-family:'IBM Plex Sans',system-ui,sans-serif}
.box{width:360px;max-width:90vw;background:#121518;border:1px solid #242A2F;border-radius:14px;padding:32px}
.brand{font-family:'IBM Plex Mono',monospace;font-size:18px;margin-bottom:6px}
.brand b{color:#5FE3A1;font-weight:500}
.sub{color:#98A0A6;font-size:14px;margin-bottom:22px}
label{display:block;font-size:13px;color:#98A0A6;margin:14px 0 7px}
input{width:100%;box-sizing:border-box;background:#171C20;border:1px solid #242A2F;border-radius:8px;padding:11px 13px;color:#ECEDEA;font-size:15px;outline:none;font-family:inherit}
input:focus{border-color:#5FE3A1}
button{width:100%;margin-top:22px;background:#5FE3A1;color:#0B0D0F;border:none;border-radius:8px;padding:12px;font-size:15px;font-weight:600;cursor:pointer;font-family:inherit}
.err{color:#F87171;font-size:13px;margin-top:14px}
</style>
</head>
<body>
<div class="box">
<div class="brand mono">javier<b>mx</b>_</div>
<div class="sub">Crea tu usuario de administrador</div>
<?php if ($msg): ?><div class="err"><?= e($msg) ?></div><?php endif; ?>
<form method="post">
<?= csrf_field() ?>
<label for="u">Usuario</label>
<input id="u" name="username" required autofocus>
<label for="p">Contraseña (mín. 8)</label>
<input id="p" name="password" type="password" required>
<label for="p2">Repite la contraseña</label>
<input id="p2" name="password2" type="password" required>
<button type="submit">Crear administrador</button>
</form>
</div>
</body>
</html>
