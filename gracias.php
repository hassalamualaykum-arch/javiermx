<?php
/**
 * gracias.php (v3) — entrega archivos SOLO si Stripe confirma que la compra fue pagada.
 *
 * Ubicación:  public_html/gracias.php   (súbelo también a tu repositorio: no lleva secretos)
 * Catálogo:   ../private_files/products.php      (los productos 'digital' con 'id' y 'file')
 * Archivos:   ../private_files/<archivo>
 * Llave:      ../private_files/stripe-secrets.php
 *
 * Ya no hay catálogo aquí: para agregar un producto digital, edita products.php.
 */

$privateDir = __DIR__ . '/../private_files/';

// ---------- catálogo de descargas, tomado de products.php ----------
$products = @include $privateDir . 'products.php';
$catalog  = [];
if (is_array($products)) {
    foreach ($products as $p) {
        if (is_array($p) && ($p['type'] ?? '') === 'digital' && !empty($p['id']) && !empty($p['file'])) {
            // Se incluyen también los productos con active=false: quien ya compró puede volver a descargar.
            $catalog[(string) $p['id']] = [
                'name' => (string) ($p['name'] ?? 'Download'),
                'file' => (string) $p['file'],
            ];
        }
    }
} else {
    error_log('gracias.php: no se pudo cargar private_files/products.php');
}

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');

// ---------- helpers ----------

function page(int $status, string $title, string $message, string $buttonHtml = ''): void
{
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    $t = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $m = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    echo <<<HTML
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>{$t}</title>
<style>
  body{font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;margin:0;min-height:100vh;
       display:flex;align-items:center;justify-content:center;background:#0f172a;color:#e2e8f0;padding:1rem}
  .card{max-width:32rem;width:100%;background:#1e293b;border-radius:14px;padding:2rem;text-align:center}
  h1{margin-top:0;font-size:1.5rem}
  p{line-height:1.5;color:#cbd5e1}
  a.btn{display:block;margin:1rem auto 0;max-width:22rem;padding:.8rem 1.4rem;background:#6366f1;color:#fff;
        text-decoration:none;border-radius:10px;font-weight:600}
  a.home{display:block;margin-top:1.5rem;color:#94a3b8;font-size:.9rem}
</style>
</head>
<body>
<div class="card">
  <h1>{$t}</h1>
  <p>{$m}</p>
  {$buttonHtml}
  <a class="home" href="/">← Back to javiermx.com</a>
</div>
</body>
</html>
HTML;
    exit;
}

function stripe_get(string $path, string $key): ?array
{
    $ch = curl_init('https://api.stripe.com/v1/' . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $key],
        CURLOPT_TIMEOUT        => 15,
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false || $code !== 200) {
        error_log('gracias.php: Stripe respondió HTTP ' . $code);
        return null;
    }
    $data = json_decode($body, true);
    return is_array($data) ? $data : null;
}

// ---------- 1. validar el session_id ----------

$sessionId = $_GET['session_id'] ?? '';
if (!is_string($sessionId) || !preg_match('/^cs_(test|live)_[A-Za-z0-9]{10,200}$/', $sessionId)) {
    page(400, 'Link not valid', 'This page needs a valid purchase link. If you already paid, please use the link shown right after your payment.');
}

// ---------- 2. cargar la llave (fuera de public_html) ----------

$secrets   = @include $privateDir . 'stripe-secrets.php';
$stripeKey = is_array($secrets) ? ($secrets['stripe_key'] ?? '') : '';
if ($stripeKey === '') {
    error_log('gracias.php: no se pudo leer stripe-secrets.php o falta stripe_key');
    page(500, 'Something went wrong', 'The download is temporarily unavailable. Please contact me and I will send it to you.');
}

// ---------- 3. preguntarle a Stripe si esta compra está pagada ----------

$session = stripe_get('checkout/sessions/' . rawurlencode($sessionId) . '?expand%5B%5D=line_items', $stripeKey);
if ($session === null) {
    page(502, 'Could not verify the payment', 'I could not confirm your payment right now. Please try again in a minute.');
}

if (($session['payment_status'] ?? '') !== 'paid') {
    page(402, 'Payment not completed', 'This purchase has not been paid yet, so the download is not available.');
}

// ---------- 4. ¿qué productos del catálogo incluye ESTA compra? ----------

$purchased = [];
foreach (($session['line_items']['data'] ?? []) as $item) {
    $pid = $item['price']['product'] ?? '';
    if (is_string($pid) && isset($catalog[$pid])) {
        $purchased[$pid] = $catalog[$pid];
    }
}
if (!$purchased) {
    page(403, 'No download available', 'This purchase does not include a downloadable file.');
}

// ---------- 5. entregar el archivo pedido ----------

if (isset($_GET['download'])) {
    $want = $_GET['download'];
    if (!is_string($want) || !isset($purchased[$want])) {
        page(403, 'No download available', 'That file is not part of this purchase.');
    }

    $name = basename($purchased[$want]['file']);
    $file = $privateDir . $name;
    if (!is_file($file)) {
        error_log('gracias.php: no existe ' . $file);
        page(500, 'Something went wrong', 'The file is temporarily unavailable. Please contact me and I will send it to you.');
    }

    $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $mime = [
        'pdf' => 'application/pdf',
        'zip' => 'application/zip',
        'txt' => 'text/plain; charset=utf-8',
        'md'  => 'text/markdown; charset=utf-8',
    ][$ext] ?? 'application/octet-stream';

    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('Content-Length: ' . filesize($file));
    header('Cache-Control: private, no-store');
    readfile($file);
    exit;
}

// ---------- 6. página de agradecimiento con un botón por archivo ----------

$buttons = '';
foreach ($purchased as $pid => $info) {
    $href = '?session_id=' . rawurlencode($sessionId) . '&download=' . rawurlencode($pid);
    $buttons .= '<a class="btn" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">Download: '
              . htmlspecialchars($info['name'], ENT_QUOTES, 'UTF-8') . '</a>';
}

page(
    200,
    'Thank you for your purchase!',
    'Your payment was confirmed. Tap the button to download your file. Bookmark this page if you want to download it again later.',
    $buttons
);
