<?php
/**
 * partials/catalog.php — catálogo de la tienda.
 *
 * Lo usan: store.php, gracias.php, admin/sales.php y admin/shop.php.
 *
 * Fuente de datos:   ../../private_files/products.json   (lo edita el panel: admin/shop.php)
 * Respaldo antiguo:  ../../private_files/products.php    (solo se lee mientras NO exista el .json)
 *
 * No depende de config.php, así que gracias.php puede usarlo sin cargar el resto del CMS.
 * Ubicación: public_html/partials/catalog.php   (súbelo también a tu repositorio: no lleva secretos)
 */

/** Carpeta privada (al lado de public_html). */
function catalog_dir(): string
{
    return __DIR__ . '/../../private_files/';
}

/** "Guía de ejemplo!" → "guia-de-ejemplo" (sin depender de config.php). */
function catalog_slug(string $text): string
{
    $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
    if ($t === false || $t === '') {
        $t = $text;
    }
    $t = strtolower(trim($t));
    $t = (string) preg_replace('/[^a-z0-9]+/', '-', $t);
    $t = trim($t, '-');
    return $t !== '' ? $t : 'item';
}

/** Deja cada producto con todos sus campos, claves únicas y orden estable. */
function catalog_normalize(array $list): array
{
    $out  = [];
    $seen = [];
    $n    = 0;

    foreach ($list as $p) {
        if (!is_array($p)) {
            continue;
        }
        $n++;
        $name = trim((string) ($p['name'] ?? ''));
        $id   = trim((string) ($p['id'] ?? ''));

        $key = (string) preg_replace('/[^a-z0-9_-]/', '', strtolower((string) ($p['key'] ?? '')));
        if ($key === '') {
            $key = catalog_slug($id !== '' ? $id : $name);
        }
        $base = $key;
        $i    = 2;
        while (isset($seen[$key])) {
            $key = $base . '-' . $i++;
        }
        $seen[$key] = true;

        $tags = (isset($p['tags']) && is_array($p['tags'])) ? $p['tags'] : [];
        $tags = array_values(array_filter(
            array_map(function ($t) { return trim((string) $t); }, $tags),
            function ($t) { return $t !== ''; }
        ));

        $out[] = [
            'key'      => $key,
            'id'       => $id,
            'active'   => ($p['active'] ?? true) !== false,
            'type'     => (($p['type'] ?? '') === 'physical') ? 'physical' : 'digital',
            'name'     => $name,
            'summary'  => trim((string) ($p['summary'] ?? '')),
            'price'    => (string) ($p['price'] ?? '0.00'),
            'currency' => strtoupper((string) ($p['currency'] ?? 'CAD')),
            'link'     => trim((string) ($p['link'] ?? '')),
            'file'     => trim((string) ($p['file'] ?? '')),
            'image'    => trim((string) ($p['image'] ?? '')),
            'tags'     => $tags,
            'sort'     => (isset($p['sort']) && is_numeric($p['sort'])) ? (int) $p['sort'] : $n * 10,
            '_i'       => $n,
        ];
    }

    usort($out, function ($a, $b) {
        return [$a['sort'], $a['_i']] <=> [$b['sort'], $b['_i']];
    });
    foreach ($out as &$o) {
        unset($o['_i']);
    }
    unset($o);

    return $out;
}

/** Lee el catálogo: primero products.json; si no existe, el products.php de antes. */
function catalog_load(): array
{
    $json = catalog_dir() . 'products.json';
    if (is_file($json)) {
        $raw  = @file_get_contents($json);
        $data = is_string($raw) ? json_decode($raw, true) : null;
        if (is_array($data) && isset($data['products']) && is_array($data['products'])) {
            return catalog_normalize($data['products']);
        }
        error_log('catalog_load: products.json está dañado o vacío; se usa products.php');
    }

    $legacy = @include catalog_dir() . 'products.php';
    return is_array($legacy) ? catalog_normalize($legacy) : [];
}

/** Guarda el catálogo en products.json (escritura atómica + una copia .bak del anterior). */
function catalog_save(array $products): bool
{
    $dir = catalog_dir();
    if (!is_dir($dir) || !is_writable($dir)) {
        error_log('catalog_save: private_files no existe o no es escribible');
        return false;
    }

    $data = ['version' => 1, 'updated' => date('c'), 'products' => catalog_normalize($products)];
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($json === false) {
        error_log('catalog_save: no se pudo convertir a JSON');
        return false;
    }

    $file = $dir . 'products.json';
    $tmp  = $file . '.tmp';
    if (file_put_contents($tmp, $json . "\n", LOCK_EX) === false) {
        error_log('catalog_save: no se pudo escribir el archivo temporal');
        return false;
    }
    if (is_file($file)) {
        @copy($file, $file . '.bak');
    }
    if (!@rename($tmp, $file)) {
        @unlink($tmp);
        error_log('catalog_save: no se pudo reemplazar products.json');
        return false;
    }
    return true;
}
