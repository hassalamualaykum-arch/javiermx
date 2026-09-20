<?php
require __DIR__ . '/../config.php';
require_login();
require __DIR__ . '/_layout.php';

$fields = [
    'site_title'       => ['SEO — Título del sitio', 'text'],
    'site_description' => ['SEO — Descripción del sitio', 'area'],
    'hero_eyebrow'     => ['Hero — línea pequeña', 'text'],
    'hero_heading'     => ['Hero — titular', 'area'],
    'hero_lead'        => ['Hero — párrafo', 'area'],
    'stack_line'       => ['Hero — línea de tecnologías', 'text'],
    'contact_email'    => ['Correo de contacto', 'text'],
    'location_line'    => ['Línea de ubicación', 'text'],
    'currently_now'    => ['Currently — etiqueta "now"', 'text'],
    'github_url'       => ['URL de GitHub', 'text'],
    'linkedin_url'     => ['URL de LinkedIn', 'text'],
    'x_url'            => ['URL de X', 'text'],
    'accent_color'     => ['Color de acento', 'color'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $up = db()->prepare('INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)');
    foreach (array_keys($fields) as $key) {
        $up->execute([$key, trim($_POST[$key] ?? '')]);
    }
    flash('Ajustes guardados.');
    redirect(url('admin/settings.php'));
}

$s = settings();
admin_head('Ajustes');
?>
<h1 class="page">Ajustes</h1>
<form method="post" class="card" style="max-width:720px;">
<?= csrf_field() ?>
<?php foreach ($fields as $key => [$label, $type]): $val = $s[$key] ?? ''; ?>
<label class="f" for="<?= e($key) ?>"><?= e($label) ?></label>
<?php if ($type === 'area'): ?>
<textarea class="in" id="<?= e($key) ?>" name="<?= e($key) ?>" rows="2"><?= e($val) ?></textarea>
<?php elseif ($type === 'color'): ?>
<input class="in" id="<?= e($key) ?>" name="<?= e($key) ?>" type="text" value="<?= e($val) ?>" placeholder="#5FE3A1" style="max-width:180px">
<?php else: ?>
<input class="in" id="<?= e($key) ?>" name="<?= e($key) ?>" value="<?= e($val) ?>">
<?php endif; ?>
<?php endforeach; ?>
<div class="actions"><button class="btn">Guardar ajustes</button></div>
</form>
<?php admin_foot(); ?>
