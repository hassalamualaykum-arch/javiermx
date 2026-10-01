<?php
/**
 * gracias.php (v4) — entrega archivos SOLO si Stripe confirma que la compra fue pagada.
 *
 * Ubicación:  public_html/gracias.php   (súbelo también a tu repositorio: no lleva secretos)
 * Catálogo:   ../private_files/products.json     (lo edita el panel; usa los productos 'digital' con 'id' y 'file')
 * Archivos:   ../private_files/<archivo>
 * Llave:      ../private_files/stripe-secrets.php
 *
 * Ya no hay catálogo aquí: para agregar un producto digital usa el panel (admin/shop.php).
 */

$privateDir = __DIR__ . '/../private_files/';

// ---------- catálogo de descargas (products.json, que edita el panel admin/shop.php) ----------
require_once __DIR__ . '/partials/catalog.php';
$catalog = [];
foreach (catalog_load() as $p) {
    // Se incluyen también los productos ocultos: quien ya compró puede volver a descargar.
    if ($p['type'] === 'digital' && $p['id'] !== '' && $p['file'] !== '') {
        $catalog[$p['id']] = ['name' => $p['name'] !== '' ? $p['name'] : 'Download', 'file' => $p['file']];
    }
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
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Plus+Jakarta+Sans:wght@700&display=swap">
<style>
  body{font-family:'Inter',system-ui,-apple-system,'Segoe UI',sans-serif;margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;
       background:#070A12;color:#EEF1F7;padding:1rem;box-sizing:border-box;-webkit-font-smoothing:antialiased}
  body::before{content:'';position:fixed;inset:-6%;z-index:-1;pointer-events:none;filter:blur(36px);
       background:radial-gradient(40% 44% at 20% 24%,rgba(124,131,255,.38),transparent 72%),
                  radial-gradient(36% 40% at 82% 32%,rgba(56,214,245,.22),transparent 72%),
                  radial-gradient(42% 40% at 60% 88%,rgba(95,227,161,.22),transparent 72%)}
  .card{max-width:32rem;width:100%;box-sizing:border-box;text-align:center;padding:2.4rem 2rem;border-radius:22px;
        background:linear-gradient(160deg,rgba(255,255,255,.08),rgba(255,255,255,.03));border:1px solid rgba(255,255,255,.12);
        box-shadow:0 1px 0 rgba(255,255,255,.07) inset,0 30px 80px -20px rgba(0,0,0,.7);
        backdrop-filter:blur(18px) saturate(150%);-webkit-backdrop-filter:blur(18px) saturate(150%)}
  h1{margin-top:0;font-family:'Plus Jakarta Sans',system-ui,sans-serif;font-size:1.65rem;font-weight:700;letter-spacing:-.02em;line-height:1.2}
  p{line-height:1.65;color:#9AA4B8}
  a.btn{display:block;margin:1rem auto 0;max-width:22rem;padding:.85rem 1.4rem;border-radius:12px;font-weight:600;text-decoration:none;color:#04140C;
        background:linear-gradient(135deg,#5FE3A1,#4BDCCB);box-shadow:0 8px 26px -8px rgba(95,227,161,.7),0 1px 0 rgba(255,255,255,.35) inset;transition:transform .15s}
  a.btn:hover{transform:translateY(-2px)}
  a.home{display:block;margin-top:1.6rem;color:#9AA4B8;font-size:.9rem;text-decoration:none}
  a.home:hover{color:#EEF1F7}
  a:focus-visible{outline:2px solid #5FE3A1;outline-offset:3px}
  @supports not ((backdrop-filter:blur(1px)) or (-webkit-backdrop-filter:blur(1px))){.card{background:rgba(16,21,34,.94)}}
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
