<?php
require __DIR__ . '/../config.php';
require_login();
require __DIR__ . '/_layout.php';

$counts = [
    'Posts'      => (int) db()->query('SELECT COUNT(*) FROM posts')->fetchColumn(),
    'Proyectos'  => (int) db()->query('SELECT COUNT(*) FROM projects')->fetchColumn(),
    'Currently'  => (int) db()->query('SELECT COUNT(*) FROM currently')->fetchColumn(),
    'Categorías' => (int) db()->query('SELECT COUNT(*) FROM categories')->fetchColumn(),
    'Etiquetas'  => (int) db()->query('SELECT COUNT(*) FROM tags')->fetchColumn(),
];
$links = [
    'Posts' => 'posts.php', 'Proyectos' => 'projects.php', 'Currently' => 'currently.php',
    'Categorías' => 'categories.php', 'Etiquetas' => 'tags.php',
];

admin_head('Inicio');
?>
<h1 class="page">Hola, <?= e(current_user()['username']) ?> 👋</h1>
<div class="row">
<?php foreach ($counts as $label => $n): ?>
<a class="card" href="<?= e(url('admin/' . $links[$label])) ?>" style="text-decoration:none;color:inherit;min-width:150px;">
<div class="muted mono" style="font-size:12px;text-transform:uppercase;letter-spacing:1px;"><?= e($label) ?></div>
<div style="font-size:34px;font-weight:600;margin-top:8px;"><?= $n ?></div>
</a>
<?php endforeach; ?>
</div>
<div class="card" style="margin-top:22px;">
<div class="muted" style="font-size:14px;">Consejo: crea primero tus <a href="<?= e(url('admin/categories.php')) ?>">categorías</a>, luego escribe tus <a href="<?= e(url('admin/posts.php')) ?>">posts</a>. Los textos del hero, el correo y las redes se editan en <a href="<?= e(url('admin/settings.php')) ?>">Ajustes</a>.</div>
</div>
<?php admin_foot(); ?>
