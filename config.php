<?php
/**
 * javiermx.com — configuración central
 * Edita SOLO el bloque de credenciales de abajo con los datos de tu base de datos de Hostinger.
 */

// ─────────────────────────────────────────────
//  CREDENCIALES DE LA BASE DE DATOS  (Hostinger → hPanel → MySQL Databases)
// ─────────────────────────────────────────────
define('DB_HOST', 'localhost');      // casi siempre "localhost" en Hostinger
define('DB_NAME', 'PON_TU_BD');      // nombre de la base de datos
define('DB_USER', 'PON_TU_USUARIO'); // usuario de la base de datos
define('DB_PASS', 'PON_TU_PASS');    // contraseña de la base de datos
define('DB_CHARSET', 'utf8mb4');

// Si tu sitio vive en la RAÍZ del dominio (javiermx.com), déjalo vacío ''.
// Si vive en una subcarpeta (javiermx.com/sitio), pon '/sitio'.
define('BASE_URL', '');

// En producción déjalo en false (no muestra errores internos al público).
define('APP_DEBUG', false);

// ─────────────────────────────────────────────
//  A PARTIR DE AQUÍ NO NECESITAS TOCAR NADA
// ─────────────────────────────────────────────
error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
date_default_timezone_set('America/Toronto');

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

/** Conexión PDO única (singleton). */
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $ex) {
            http_response_code(500);
            exit(APP_DEBUG ? 'DB error: ' . $ex->getMessage() : 'Error de conexión a la base de datos.');
        }
    }
    return $pdo;
}

/** Escapa texto para imprimir en HTML. */
function e(?string $s): string {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

/** Redirige y termina. */
function redirect(string $path): void {
    header('Location: ' . $path);
    exit;
}

/** URL absoluta dentro del sitio. */
function url(string $path = ''): string {
    return BASE_URL . '/' . ltrim($path, '/');
}

/** Convierte texto en slug: "Mi Proyecto!" → "mi-proyecto". */
function slugify(string $text): string {
    $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim($text, '-') ?: 'item';
}

// ── CSRF ──────────────────────────────────────
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}
function csrf_field(): string {
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}
function csrf_verify(): void {
    $ok = isset($_POST['csrf']) && hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf']);
    if (!$ok) {
        http_response_code(403);
        exit('Token de seguridad inválido. Vuelve atrás y reintenta.');
    }
}

// ── Mensajes flash ────────────────────────────
function flash(string $msg, string $type = 'ok'): void {
    $_SESSION['flash'][] = ['msg' => $msg, 'type' => $type];
}
function take_flashes(): array {
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

// ── Autenticación ─────────────────────────────
function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}
function require_login(): void {
    if (!current_user()) {
        redirect(url('admin/login.php'));
    }
}
function login_user(string $username, string $password): bool {
    $st = db()->prepare('SELECT * FROM admin_users WHERE username = ? LIMIT 1');
    $st->execute([$username]);
    $u = $st->fetch();
    if ($u && password_verify($password, $u['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user'] = ['id' => (int) $u['id'], 'username' => $u['username']];
        return true;
    }
    return false;
}
function logout_user(): void {
    $_SESSION = [];
    session_destroy();
}

// ── Ajustes del sitio (tabla settings) ────────
function settings(): array {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (db()->query('SELECT skey, svalue FROM settings') as $row) {
            $cache[$row['skey']] = $row['svalue'];
        }
    }
    return $cache;
}
/** Valores por defecto (se usan mientras no se guarden en Ajustes). */
function setting_defaults(): array {
    return [
        'term_title'    => 'bash — javiermx',
        'term_whoami'   => 'javier — security & software',
        'term_focus'    => "penetration testing\nfull-stack · php java python js\narduino & hardware\nbiohacking enthusiast",
        'term_location' => 'Toronto, CA · remote worldwide',
        'focus_eyebrow' => '// what I do',
        'focus_heading' => 'Four things I go deep on.',
        'focus_lead'    => 'Different disciplines, one common thread: taking things apart to understand how they really work.',
        'focus_1_title' => 'Penetration Testing',
        'focus_1_desc'  => 'Finding the gaps in a system before someone with worse intentions does.',
        'focus_2_title' => 'Development',
        'focus_2_desc'  => 'PHP, Java, Python and JavaScript — from backend logic to the browser.',
        'focus_3_title' => 'Arduino & Hardware',
        'focus_3_desc'  => 'Sensors, microcontrollers and small machines that do something useful.',
        'focus_4_title' => 'Biohacking & enthusiast',
        'focus_4_desc'  => 'Notes on biohacking research, emerging compounds and human performance.',
    ];
}
function setting(string $key, string $default = ''): string {
    $s = settings();
    return $s[$key] ?? setting_defaults()[$key] ?? $default;
}

// ── Imágenes: subir archivo O usar link (el usuario eligió "las dos") ──
/**
 * Devuelve la ruta/URL final de una imagen.
 * @param string $fileKey  nombre del input file (ej. "image_file")
 * @param string $urlValue valor del input de link (ej. $_POST['image_url'])
 * @param string $existing valor previo (para no borrarlo si no cambia)
 */
function handle_image(string $fileKey, string $urlValue, string $existing = ''): string {
    // 1) ¿Subió un archivo?
    if (!empty($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
        $tmp  = $_FILES[$fileKey]['tmp_name'];
        $size = (int) $_FILES[$fileKey]['size'];
        if ($size > 5 * 1024 * 1024) {
            flash('La imagen supera 5 MB.', 'err');
            return $existing;
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $tmp);
        finfo_close($finfo);
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        if (!isset($allowed[$mime])) {
            flash('Formato de imagen no permitido (usa JPG, PNG, WEBP o GIF).', 'err');
            return $existing;
        }
        $dir = __DIR__ . '/uploads';
        if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
        $name = bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
        if (move_uploaded_file($tmp, $dir . '/' . $name)) {
            return 'uploads/' . $name;
        }
        flash('No se pudo guardar la imagen subida.', 'err');
        return $existing;
    }
    // 2) ¿Pegó un link?
    $urlValue = trim($urlValue);
    if ($urlValue !== '') {
        return $urlValue;
    }
    // 3) Sin cambios
    return $existing;
}

/** Convierte el valor guardado en una URL usable en <img src>. */
function img_src(string $val): string {
    if ($val === '') return '';
    if (preg_match('#^https?://#i', $val)) return $val; // link externo
    return url($val); // archivo local (uploads/…)
}
