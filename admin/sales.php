<?php
/**
 * admin/sales.php — Ventas de la tienda, leídas en vivo desde Stripe.
 *
 * Ubicación:  public_html/admin/sales.php   (súbelo también a tu repositorio: no lleva secretos)
 * Usa:        ../../private_files/stripe-secrets.php  (llave restringida: Checkout Sessions = Read)
 *             ../../private_files/products.php        (nombres y tipo de cada producto)
 *
 * No guarda nada en tu base de datos: cada vez que abres la página le pregunta a Stripe.
 */
require __DIR__ . '/../config.php';
require_login();
require __DIR__ . '/_layout.php';

$privateDir = __DIR__ . '/../../private_files/';

// ---------- catálogo (para poner nombre y tipo a cada producto) ----------
$catalog  = [];
$products = @include $privateDir . 'products.php';
if (is_array($products)) {
    foreach ($products as $p) {
        if (is_array($p) && !empty($p['id'])) {
            $catalog[(string) $p['id']] = [
                'name' => (string) ($p['name'] ?? ''),
                'type' => (($p['type'] ?? '') === 'physical') ? 'physical' : 'digital',
            ];
        }
    }
}

// ---------- llave de Stripe ----------
$secrets = @include $privateDir . 'stripe-secrets.php';
$key     = is_array($secrets) ? (string) ($secrets['stripe_key'] ?? '') : '';
$isTest  = strpos($key, '_test_') !== false;

// ---------- periodo ----------
$allowedDays = [7, 30, 90, 365];
$days = (int) ($_GET['days'] ?? 30);
if (!in_array($days, $allowedDays, true)) {
    $days = 30;
}
$since = time() - $days * 86400;

// ---------- helpers ----------
function sales_fetch(string $key, int $since, &$err, &$truncated): array
{
    $all = [];
    $after = null;
    $truncated = false;
    for ($page = 0; $page < 5; $page++) {
        $qs = 'limit=100&created%5Bgte%5D=' . $since . '&expand%5B%5D=data.line_items';
        if ($after !== null) {
            $qs .= '&starting_after=' . rawurlencode($after);
        }
        $ch = curl_init('https://api.stripe.com/v1/checkout/sessions?' . $qs);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $key],
            CURLOPT_TIMEOUT        => 20,
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false) {
            $err = 'No pude conectarme con Stripe. Intenta de nuevo en un minuto.';
            return [];
        }
        $data = json_decode($body, true);
        if ($code !== 200 || !is_array($data)) {
            $e = (is_array($data) && isset($data['error']) && is_array($data['error'])) ? $data['error'] : [];
            $extra = isset($e['code']) ? $e['code'] : (isset($e['type']) ? $e['type'] : '');
            $err = 'Stripe respondió con error HTTP ' . $code . ($extra !== '' ? ' (' . $extra . ')' : '') . '.';
            error_log('sales.php: ' . $err);
            return [];
        }
        foreach (($data['data'] ?? []) as $s) {
            $all[] = $s;
        }
        if (empty($data['has_more']) || empty($data['data'])) {
            return $all;
        }
        $last  = end($data['data']);
        $after = $last['id'];
    }
    $truncated = true; // había más de 500 sesiones en el periodo
    return $all;
}

function sales_money(int $cents, string $cur): string
{
    return '$' . number_format($cents / 100, 2) . ' ' . $cur;
}

function sales_totals_text(array $byCur): string
{
    if (!$byCur) {
        return '$0.00';
    }
    $out = [];
    foreach ($byCur as $cur => $cents) {
        $out[] = sales_money((int) $cents, (string) $cur);
    }
    return implode(' · ', $out);
}

function sales_addr(array $ship): string
{
    $a = isset($ship['address']) && is_array($ship['address']) ? $ship['address'] : [];
    $cityLine = trim(($a['city'] ?? '') . ' ' . ($a['state'] ?? '') . ' ' . ($a['postal_code'] ?? ''));
    $parts = [$ship['name'] ?? '', $a['line1'] ?? '', $a['line2'] ?? '', $cityLine, $a['country'] ?? ''];
    $parts = array_filter($parts, function ($x) { return trim((string) $x) !== ''; });
    return implode(', ', $parts);
}

// ---------- traer y resumir las ventas ----------
$err = null;
$truncated = false;
$sessions = [];
if ($key === '') {
    $err = 'No encuentro la llave de Stripe (private_files/stripe-secrets.php).';
} else {
    $sessions = sales_fetch($key, $since, $err, $truncated);
}

$tz = new DateTimeZone('America/Toronto');
$orders = [];
$byProduct = [];
$totals = [];
$nDigital = 0;
$nPhysical = 0;

foreach ($sessions as $s) {
    if (($s['payment_status'] ?? '') !== 'paid') {
        continue;
    }
    $cur = strtoupper((string) ($s['currency'] ?? 'cad'));
    $amt = (int) ($s['amount_total'] ?? 0);
    $totals[$cur] = ($totals[$cur] ?? 0) + $amt;

    $ship = null;
    if (isset($s['collected_information']['shipping_details'])) {
        $ship = $s['collected_information']['shipping_details'];
    } elseif (isset($s['shipping_details'])) {
        $ship = $s['shipping_details'];
    }
    $hasShip = is_array($ship) && !empty($ship['address']);

    $items = [];
    $orderPhysical = $hasShip;
    foreach (($s['line_items']['data'] ?? []) as $li) {
        $pid  = (string) ($li['price']['product'] ?? '');
        $qty  = (int) ($li['quantity'] ?? 1);
        $lamt = (int) ($li['amount_total'] ?? 0);
        $name = ($pid !== '' && isset($catalog[$pid]) && $catalog[$pid]['name'] !== '')
              ? $catalog[$pid]['name']
              : (string) ($li['description'] ?? 'Producto');
        $type = isset($catalog[$pid]) ? $catalog[$pid]['type'] : ($hasShip ? 'physical' : 'digital');
        if ($type === 'physical') {
            $orderPhysical = true;
        }
        $items[] = ['name' => $name, 'qty' => $qty];

        $k = ($pid !== '' ? $pid : $name) . '|' . $cur;
        if (!isset($byProduct[$k])) {
            $byProduct[$k] = ['name' => $name, 'type' => $type, 'units' => 0, 'rev' => 0, 'cur' => $cur];
        }
        $byProduct[$k]['units'] += $qty;
        $byProduct[$k]['rev']   += $lamt;
    }
    if ($orderPhysical) {
        $nPhysical++;
    } else {
        $nDigital++;
    }

    $cd = isset($s['customer_details']) && is_array($s['customer_details']) ? $s['customer_details'] : [];
    $dt = new DateTime('@' . (int) ($s['created'] ?? time()));
    $dt->setTimezone($tz);

    $orders[] = [
        'when'  => $dt->format('Y-m-d H:i'),
        'items' => $items,
        'amt'   => $amt,
        'cur'   => $cur,
        'name'  => (string) ($cd['name'] ?? ''),
        'email' => (string) ($cd['email'] ?? ''),
        'phone' => (string) ($cd['phone'] ?? ''),
        'ship'  => $hasShip ? sales_addr($ship) : '',
        'phys'  => $orderPhysical,
        'test'  => empty($s['livemode']),
    ];
}

uasort($byProduct, function ($a, $b) { return $b['rev'] <=> $a['rev']; });
$nOrders = count($orders);
$showMax = 50;

admin_head('Ventas');
?>
<style>
.tiles{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:14px}
.tile .k{font-size:12px;text-transform:uppercase;letter-spacing:1px;color:var(--muted)}
.tile .n{font-size:30px;font-weight:600;margin-top:6px;font-variant-numeric:tabular-nums}
.tile .s{font-size:13px;color:var(--muted);margin-top:2px}
h2.sec{font-size:17px;margin:30px 0 12px}
td.num,th.num{text-align:right;font-variant-numeric:tabular-nums}
.range{display:flex;gap:8px;flex-wrap:wrap;margin:0 0 18px}
.range a{font-size:13px;border:1px solid var(--line);border-radius:999px;padding:5px 13px;color:var(--muted)}
.range a.on{border-color:var(--accent);color:var(--accent)}
.badge{display:inline-block;font-size:11px;border-radius:999px;padding:2px 9px;border:1px solid var(--line);white-space:nowrap}
.badge.phys{color:#FBBF77;border-color:#7a5320}
.badge.dig{color:var(--accent)}
.badge.test{color:var(--muted)}
.scroll{overflow-x:auto}
.small{font-size:12.5px;color:var(--muted)}
</style>

<h1 class="page">Ventas</h1>

<div class="range">
<?php foreach ($allowedDays as $d): ?>
<a href="?days=<?= $d ?>"<?= $d === $days ? ' class="on"' : '' ?>><?= $d === 365 ? '1 año' : $d . ' días' ?></a>
<?php endforeach; ?>
</div>

<?php if ($isTest): ?>
<div class="flash" style="border-style:dashed;border-color:var(--accent);color:var(--muted)">
<strong style="color:var(--text)">Modo prueba.</strong> Estás viendo compras del <em>sandbox</em> de Stripe (dinero de mentira).
Al poner tu llave real, aquí aparecerán las ventas reales.
</div>
<?php endif; ?>

<?php if ($err): ?>
<div class="flash err">
<?= e($err) ?>
<div class="small" style="margin-top:6px">Revisa que la llave restringida tenga el permiso <strong>Checkout Sessions: Read</strong> y que sea la del modo correcto (prueba o real).</div>
</div>
<?php else: ?>

<div class="tiles">
<div class="card tile"><div class="k">Pedidos</div><div class="n"><?= $nOrders ?></div><div class="s">últimos <?= $days === 365 ? '365' : $days ?> días</div></div>
<div class="card tile"><div class="k">Ingresos</div><div class="n" style="font-size:<?= count($totals) > 1 ? '20' : '30' ?>px"><?= e(sales_totals_text($totals)) ?></div><div class="s">total cobrado</div></div>
<div class="card tile"><div class="k">Digitales</div><div class="n"><?= $nDigital ?></div><div class="s">pedidos</div></div>
<div class="card tile"><div class="k">Físicos</div><div class="n"><?= $nPhysical ?></div><div class="s">por enviar / enviados</div></div>
</div>

<h2 class="sec">Qué se está vendiendo</h2>
<div class="card scroll">
<table>
<tr><th>Producto</th><th>Tipo</th><th class="num">Unidades</th><th class="num">Ingresos</th></tr>
<?php foreach ($byProduct as $bp): ?>
<tr>
<td><?= e($bp['name']) ?></td>
<td><?= $bp['type'] === 'physical' ? '<span class="badge phys">Físico</span>' : '<span class="badge dig">Digital</span>' ?></td>
<td class="num"><?= (int) $bp['units'] ?></td>
<td class="num"><?= e(sales_money((int) $bp['rev'], $bp['cur'])) ?></td>
</tr>
<?php endforeach; ?>
<?php if (!$byProduct): ?><tr><td colspan="4" class="muted">Todavía no hay ventas en este periodo.</td></tr><?php endif; ?>
</table>
</div>

<h2 class="sec">Últimos pedidos</h2>
<div class="card scroll">
<table>
<tr><th>Fecha</th><th>Producto</th><th class="num">Total</th><th>Cliente</th><th>Envío</th></tr>
<?php foreach (array_slice($orders, 0, $showMax) as $o): ?>
<tr>
<td class="mono" style="font-size:12px;white-space:nowrap"><?= e($o['when']) ?><?= $o['test'] ? '<br><span class="badge test">prueba</span>' : '' ?></td>
<td>
<?php foreach ($o['items'] as $it): ?>
<div><?= e($it['name']) ?><?= $it['qty'] > 1 ? ' <span class="muted">× ' . (int) $it['qty'] . '</span>' : '' ?></div>
<?php endforeach; ?>
</td>
<td class="num" style="white-space:nowrap"><?= e(sales_money($o['amt'], $o['cur'])) ?></td>
<td>
<?php if ($o['name'] !== ''): ?><div><?= e($o['name']) ?></div><?php endif; ?>
<div class="small"><?= e($o['email']) ?></div>
<?php if ($o['phone'] !== ''): ?><div class="small"><?= e($o['phone']) ?></div><?php endif; ?>
</td>
<td>
<?php if ($o['phys']): ?>
<span class="badge phys">Enviar</span>
<div class="small" style="margin-top:4px"><?= $o['ship'] !== '' ? e($o['ship']) : 'Sin dirección (revisa el enlace de pago)' ?></div>
<?php else: ?>
<span class="badge dig">Descarga ✓</span>
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
<?php if (!$orders): ?><tr><td colspan="5" class="muted">Todavía no hay pedidos en este periodo.</td></tr><?php endif; ?>
</table>
<?php if ($nOrders > $showMax): ?>
<div class="small" style="margin-top:10px">Mostrando los <?= $showMax ?> más recientes de <?= $nOrders ?>.</div>
<?php endif; ?>
</div>

<p class="small" style="margin-top:16px">
Los datos vienen de Stripe en el momento en que abres esta página. Los <strong>reembolsos</strong> hechos en Stripe no se descuentan aquí.
<?php if ($truncated): ?> Este periodo tiene más de 500 sesiones: solo se leyeron las más recientes.<?php endif; ?>
</p>
<?php endif; ?>

<?php admin_foot(); ?>
