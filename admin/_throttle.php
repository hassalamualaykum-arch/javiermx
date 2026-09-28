<?php
/**
 * Bloqueo de intentos de login: tras LOGIN_MAX_FAILS contraseñas incorrectas desde la misma IP
 * en LOGIN_WINDOW_MIN minutos, esa IP no puede intentarlo más hasta que pase el tiempo.
 * Un login correcto reinicia el contador de esa IP.
 */
require_once __DIR__ . '/../partials/common.php';

const LOGIN_MAX_FAILS  = 5;
const LOGIN_WINDOW_MIN = 15;

const LOGIN_ATTEMPTS_TABLE_SQL = "CREATE TABLE IF NOT EXISTS login_attempts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip VARCHAR(45) NOT NULL,
    username VARCHAR(100) NOT NULL DEFAULT '',
    success TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    KEY idx_ip (ip, id),
    KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

/** Segundos que le faltan a esta IP para poder volver a intentarlo (0 = puede intentar). */
function login_blocked_for(string $ip): int {
    return with_table(LOGIN_ATTEMPTS_TABLE_SQL, function () use ($ip) {
        $since = date('Y-m-d H:i:s', time() - LOGIN_WINDOW_MIN * 60);
        $st = db()->prepare(
            'SELECT COUNT(*) AS n, MAX(created_at) AS last FROM login_attempts
             WHERE ip = ? AND success = 0 AND created_at > ?
               AND id > (SELECT COALESCE(MAX(id), 0) FROM login_attempts WHERE ip = ? AND success = 1)'
        );
        $st->execute([$ip, $since, $ip]);
        $r = $st->fetch();
        if ((int) $r['n'] < LOGIN_MAX_FAILS) return 0;
        return max(0, strtotime($r['last']) + LOGIN_WINDOW_MIN * 60 - time());
    });
}

function login_record(string $ip, string $username, bool $success): void {
    with_table(LOGIN_ATTEMPTS_TABLE_SQL, function () use ($ip, $username, $success) {
        db()->prepare('INSERT INTO login_attempts (ip, username, success, created_at) VALUES (?,?,?,?)')
            ->execute([$ip, substr($username, 0, 100), $success ? 1 : 0, date('Y-m-d H:i:s')]);
    });
    // Limpieza ocasional: guarda solo los últimos 90 días.
    if (random_int(1, 100) === 1) {
        db()->prepare('DELETE FROM login_attempts WHERE created_at < ?')->execute([date('Y-m-d H:i:s', strtotime('-90 days'))]);
    }
}
