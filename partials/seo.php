<?php
/** SEO: dominio canónico y URLs absolutas (Google y redes sociales las necesitan completas). */

// Dominio oficial, SIN "www" y sin barra final. El .htaccess redirige www → este dominio.
if (!defined('SITE_URL')) {
    define('SITE_URL', 'https://javiermx.com');
}
// Nombre corto que se añade al final del <title> de cada post.
if (!defined('SITE_NAME')) {
    define('SITE_NAME', 'javiermx');
}

/** URL absoluta: "post.php?slug=x" → "https://javiermx.com/post.php?slug=x". Deja intactos los links externos. */
function abs_url(string $path = ''): string {
    if ($path === '') return SITE_URL . '/';
    if (preg_match('#^https?://#i', $path)) return $path;
    return SITE_URL . url($path);
}
