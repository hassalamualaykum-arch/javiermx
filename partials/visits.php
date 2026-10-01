<?php
/**
 * Contador de visitas propio.
 * - Sin cookies ni servicios externos.
 * - No guarda la IP: guarda un identificador anónimo (hash) que cambia cada día,
 *   suficiente para contar visitantes únicos por día y nada más.
 * - Ignora bots conocidos, peticiones que no son GET, errores 404 y tus propias visitas (sesión de admin).
 * - Ignora el spam de referencia (sitios de "backlinks") y el optimizador de caché de Hostinger (LSCWP_CTRL).
 */
require_once __DIR__ . '/common.php';

const VISITS_TABLE_SQL = "CREATE TABLE IF NOT EXISTS visits (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    day DATE NOT NULL,
    visitor CHAR(16) NOT NULL,
    path VARCHAR(255) NOT NULL,
    post_id INT UNSIGNED NULL,
    source VARCHAR(100) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL,
    KEY idx_day (day),
    KEY idx_post_day (post_id, day)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

function is_bot(string $ua): bool {
    if ($ua === '' || stripos($ua, 'mozilla') === false) return true;
    return (bool) preg_match(
        '/bot|crawl|spider|slurp|scan|monitor|uptime|pingdom|lighthouse|headless|preview|fetch|curl|wget|python|java\/|go-http|okhttp|axios|httpclient|'
        . 'facebookexternalhit|whatsapp|telegram|discord|slack|skype|embedly|semrush|ahrefs|mj12|petal|bytespider|dataprovider|gptbot|claude|perplexity/i',
        $ua
    );
}

/** Dominio de donde llegó el visitante ("google.com", "t.co"…), o '' si entró directo o navega dentro del sitio. */
function visit_source(): string {
    $host = strtolower((string) parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_HOST));
    $host = preg_replace('/^www\./', '', $host);
    $own  = preg_replace('/^www\./', '', strtolower($_SERVER['HTTP_HOST'] ?? ''));
    return ($host === '' || $host === $own) ? '' : substr($host, 0, 100);
}

/** Dominios de spam de referencia: bots que fingen venir de su sitio para que lo visites. */
function is_spam_source(string $host): bool {
    return $host !== '' && (bool) preg_match('/backlink|linkbuilding|qualitylink|dofollow|checker|dataindex/i', $host);
}

/** Registra la visita a la página actual. Nunca rompe la página si algo falla. */
function track_visit(?int $postId = null): void {
    try {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') return;
        if (http_response_code() >= 400) return;
        if (current_user()) return;
        $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
        if (is_bot($ua)) return;
        if (isset($_GET['LSCWP_CTRL'])) return; // optimizador de LiteSpeed Cache (Hostinger), no es una persona
        if (is_spam_source(visit_source())) return;

        $day     = date('Y-m-d');
        $visitor = substr(hash_hmac('sha256', client_ip() . '|' . $ua . '|' . $day, DB_PASS), 0, 16);
        $path    = substr(strtok($_SERVER['REQUEST_URI'] ?? '/', '#'), 0, 255);

        with_table(VISITS_TABLE_SQL, function () use ($day, $visitor, $path, $postId) {
            db()->prepare('INSERT INTO visits (day, visitor, path, post_id, source, created_at) VALUES (?,?,?,?,?,?)')
                ->execute([$day, $visitor, $path, $postId, visit_source(), date('Y-m-d H:i:s')]);
        });

        // De vez en cuando, borra registros de más de 13 meses para que la tabla no crezca sin fin.
        if (random_int(1, 200) === 1) {
            db()->prepare('DELETE FROM visits WHERE day < ?')->execute([date('Y-m-d', strtotime('-400 days'))]);
        }
    } catch (Throwable $ex) {
        // El contador es secundario: si falla, la página se muestra igual.
    }
}
