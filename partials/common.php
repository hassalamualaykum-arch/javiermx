<?php
/** Utilidades compartidas por el contador de visitas y el bloqueo de intentos de login. */

/** IP del visitante. Si Hostinger/CDN manda la IP real en X-Forwarded-For, usa la primera válida. */
function client_ip(): string {
    $xff = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
    if ($xff !== '') {
        $first = trim(explode(',', $xff)[0]);
        if (filter_var($first, FILTER_VALIDATE_IP)) return $first;
    }
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

/**
 * Ejecuta $fn; si la tabla todavía no existe (error 42S02), la crea con $createSql y reintenta.
 * Así las tablas nuevas se crean solas la primera vez, sin entrar a phpMyAdmin.
 */
function with_table(string $createSql, callable $fn) {
    try {
        return $fn();
    } catch (PDOException $ex) {
        if ($ex->getCode() !== '42S02') throw $ex;
        db()->exec($createSql);
        return $fn();
    }
}
