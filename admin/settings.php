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
    '_term'            => ['Terminal (bash — javiermx)', 'section'],
    'term_title'       => ['Terminal — título de la barra', 'text'],
    'term_whoami'      => ['Terminal — respuesta de "whoami"', 'text'],
    'term_focus'       => ['Terminal — líneas de "cat focus.txt" (una por línea)', 'area'],
    'term_location'    => ['Terminal — respuesta de "location --now"', 'text'],
    '_focus'           => ['Sección "What I do" (módulos)', 'section'],
    'focus_eyebrow'    => ['What I do — línea pequeña', 'text'],
    'focus_heading'    => ['What I do — titular', 'text'],
    'focus_lead'       => ['What I do — párrafo', 'area'],
    'focus_1_title'    => ['Módulo 1 (escudo) — título', 'text'],
    'focus_1_desc'     => ['Módulo 1 (escudo) — descripción', 'area'],
    'focus_2_title'    => ['Módulo 2 (código) — título', 'text'],
    'focus_2_desc'     => ['Módulo 2 (código) — descripción', 'area'],
    'focus_3_title'    => ['Módulo 3 (chip) — título', 'text'],
    'focus_3_desc'     => ['Módulo 3 (chip) — descripción', 'area'],
    'focus_4_title'    => ['Módulo 4 (pulso) — título', 'text'],
    'focus_4_desc'     => ['Módulo 4 (pulso) — descripción', 'area'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $up = db()->prepare('INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)');
    foreach ($fields as $key => [, $type]) {
        if ($type === 'section') { continue; }
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
<?php foreach ($fields as $key => [$label, $type]): $val = $s[$key] ?? setting_defaults()[$key] ?? ''; ?>
<?php if ($type === 'section'): ?>
<h2 style="font-size:17px;margin:34px 0 0;padding-top:20px;border-top:1px solid var(--line);"><?= e($label) ?></h2>
<?php continue; endif; ?>
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
