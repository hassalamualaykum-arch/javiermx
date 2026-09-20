<?php
require __DIR__ . '/../config.php';

if (current_user()) redirect(url('admin/index.php'));

// ¿Ya existe algún admin? Si no, manda a setup.
$count = (int) db()->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
if ($count === 0) redirect(url('admin/setup.php'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $user = trim($_POST['username'] ?? '');
    $pass = $_POST['password'] ?? '';
    if (login_user($user, $pass)) {
        redirect(url('admin/index.php'));
    }
    flash('Usuario o contraseña incorrectos.', 'err');
    redirect(url('admin/login.php'));
}
?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Entrar · Panel javiermx</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:wght@400;500;600&display=swap">
<style>
body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#0B0D0F;color:#ECEDEA;font-family:'IBM Plex Sans',system-ui,sans-serif}
.box{width:340px;max-width:90vw;background:#121518;border:1px solid #242A2F;border-radius:14px;padding:32px}
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
<div class="sub">Panel de administración</div>
<?php foreach (take_flashes() as $f): ?><div class="err"><?= e($f['msg']) ?></div><?php endforeach; ?>
<form method="post">
<?= csrf_field() ?>
<label for="u">Usuario</label>
<input id="u" name="username" autocomplete="username" required autofocus>
<label for="p">Contraseña</label>
<input id="p" name="password" type="password" autocomplete="current-password" required>
<button type="submit">Entrar</button>
</form>
</div>
</body>
</html>
