<?php
require __DIR__ . '/../config.php';

if (current_user()) redirect(url('admin/index.php'));

// ¿Ya existe algún admin? Si no, manda a setup.
$count = (int) db()->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
if ($count === 0) redirect(url('admin/setup.php'));

require __DIR__ . '/_throttle.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $ip   = client_ip();
    $wait = login_blocked_for($ip);
    if ($wait > 0) {
        flash('Demasiados intentos fallidos. Espera ' . ceil($wait / 60) . ' min y vuelve a intentarlo.', 'err');
        redirect(url('admin/login.php'));
    }
    $user = trim($_POST['username'] ?? '');
    $pass = $_POST['password'] ?? '';
    if (login_user($user, $pass)) {
        login_record($ip, $user, true);
        redirect(url('admin/index.php'));
    }
    login_record($ip, $user, false);
    sleep(1); // frena a los bots que prueban contraseñas en serie
    $wait = login_blocked_for($ip);
    flash($wait > 0
        ? 'Demasiados intentos fallidos. Espera ' . ceil($wait / 60) . ' min y vuelve a intentarlo.'
        : 'Usuario o contraseña incorrectos.', 'err');
    redirect(url('admin/login.php'));
}
?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Entrar · Panel javiermx</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap">
<style>
body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#070A12;color:#EEF1F7;font-family:'Inter',system-ui,sans-serif;-webkit-font-smoothing:antialiased}
body::before{content:'';position:fixed;inset:-6%;z-index:-1;pointer-events:none;filter:blur(36px);
  background:radial-gradient(40% 44% at 22% 26%,rgba(124,131,255,.38),transparent 72%),radial-gradient(36% 40% at 80% 34%,rgba(56,214,245,.22),transparent 72%),radial-gradient(40% 40% at 60% 88%,rgba(95,227,161,.2),transparent 72%)}
.box{width:360px;max-width:90vw;background:linear-gradient(160deg,rgba(255,255,255,.08),rgba(255,255,255,.03));border:1px solid rgba(255,255,255,.12);border-radius:20px;padding:34px;
  box-shadow:0 1px 0 rgba(255,255,255,.07) inset,0 30px 80px -20px rgba(0,0,0,.7);backdrop-filter:blur(18px) saturate(150%);-webkit-backdrop-filter:blur(18px) saturate(150%)}
.brand{font-family:'JetBrains Mono',monospace;font-size:19px;font-weight:500;margin-bottom:6px}
.brand b{color:#5FE3A1;font-weight:500}
.sub{color:#9AA4B8;font-size:14px;margin-bottom:22px}
label{display:block;font-size:13px;font-weight:500;color:#9AA4B8;margin:14px 0 7px}
input{width:100%;box-sizing:border-box;background:rgba(4,7,14,.5);border:1px solid rgba(255,255,255,.1);border-radius:10px;padding:11px 13px;color:#EEF1F7;font-size:15px;outline:none;font-family:inherit;transition:border-color .15s,box-shadow .15s}
input:focus{border-color:rgba(95,227,161,.7);box-shadow:0 0 0 4px rgba(95,227,161,.14)}
button{width:100%;margin-top:24px;background:linear-gradient(135deg,#5FE3A1,#4BDCCB);color:#04140C;border:none;border-radius:10px;padding:12px;font-size:15px;font-weight:600;cursor:pointer;font-family:inherit;box-shadow:0 8px 24px -8px rgba(95,227,161,.7)}
button:focus-visible{outline:2px solid #5FE3A1;outline-offset:3px}
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
