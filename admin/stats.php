<?php
require __DIR__ . '/../config.php';
require_login();
require __DIR__ . '/_layout.php';
require __DIR__ . '/../partials/visits.php';
require __DIR__ . '/_throttle.php';

const STATS_DAYS = 30;
$today = date('Y-m-d');
$from  = date('Y-m-d', strtotime('-' . (STATS_DAYS - 1) . ' days'));

// ── Visitas por día (visitantes únicos del día + páginas vistas) ──
$byDay = with_table(VISITS_TABLE_SQL, function () use ($from) {
    $st = db()->prepare('SELECT day, COUNT(DISTINCT visitor) AS v, COUNT(*) AS pv FROM visits WHERE day >= ? GROUP BY day');
    $st->execute([$from]);
    $out = [];
    foreach ($st->fetchAll() as $r) $out[$r['day']] = ['v' => (int) $r['v'], 'pv' => (int) $r['pv']];
    return $out;
});
$days = [];
for ($i = STATS_DAYS - 1; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $days[$d] = $byDay[$d] ?? ['v' => 0, 'pv' => 0];
}
$sum = function (int $n, string $k) use ($days) { return array_sum(array_column(array_slice($days, -$n), $k)); };

// ── Posts más leídos (30 días) ──
$topPosts = db()->prepare(
    'SELECT p.title, p.slug, COUNT(DISTINCT v.day, v.visitor) AS v, COUNT(*) AS pv
     FROM visits v JOIN posts p ON p.id = v.post_id
     WHERE v.day >= ? GROUP BY p.id ORDER BY v DESC, pv DESC LIMIT 10'
);
$topPosts->execute([$from]);
$topPosts = $topPosts->fetchAll();

// ── De dónde llegan (30 días) ──
$src = db()->prepare(
    "SELECT source, COUNT(DISTINCT day, visitor) AS v FROM visits
     WHERE day >= ? AND source <> '' GROUP BY source ORDER BY v DESC LIMIT 10"
);
$src->execute([$from]);
$sources = $src->fetchAll();
$direct = db()->prepare("SELECT COUNT(DISTINCT day, visitor) FROM visits WHERE day >= ? AND source = ''");
$direct->execute([$from]);
$directCount = (int) $direct->fetchColumn();

function source_name(string $host): string {
    $map = ['google.' => 'Google', 'bing.' => 'Bing', 'duckduckgo.' => 'DuckDuckGo', 'yahoo.' => 'Yahoo',
            't.co' => 'X / Twitter', 'x.com' => 'X / Twitter', 'twitter.' => 'X / Twitter',
            'facebook.' => 'Facebook', 'fb.' => 'Facebook', 'instagram.' => 'Instagram', 'linkedin.' => 'LinkedIn',
            'lnkd.in' => 'LinkedIn', 'reddit.' => 'Reddit', 'github.' => 'GitHub', 'youtube.' => 'YouTube'];
    foreach ($map as $needle => $name) {
        if (strpos($host, $needle) !== false) return $name;
    }
    return $host;
}

// ── Seguridad: intentos de login ──
$fails = with_table(LOGIN_ATTEMPTS_TABLE_SQL, function () {
    $n = db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE success = 0 AND created_at > ?');
    $n->execute([date('Y-m-d H:i:s', strtotime('-24 hours'))]);
    $n24 = (int) $n->fetchColumn();
    $n->execute([date('Y-m-d H:i:s', strtotime('-7 days'))]);
    $n7 = (int) $n->fetchColumn();
    $last = db()->query('SELECT ip, username, success, created_at FROM login_attempts ORDER BY id DESC LIMIT 15')->fetchAll();
    return ['24h' => $n24, '7d' => $n7, 'last' => $last];
});

// Escala del eje Y: número "redondo" (1, 2, 5 × 10ⁿ) por encima del máximo.
$max = max(array_column($days, 'v')) ?: 1;
$step = 10 ** floor(log10($max));
foreach ([1, 2, 5, 10] as $m) { if ($m * $step >= $max) { $yMax = max(4, $m * $step); break; } }
$ticks = [0, $yMax / 2, $yMax];

$fmtDay = function (string $d, bool $long = false) {
    $mes = ['', 'ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    $dias = ['dom', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb'];
    $t = strtotime($d);
    return ($long ? $dias[(int) date('w', $t)] . ' ' : '') . (int) date('j', $t) . ' ' . $mes[(int) date('n', $t)];
};

admin_head('Estadísticas');
?>
<style>
.tiles{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:14px}
.tile .k{font-size:12px;text-transform:uppercase;letter-spacing:1px;color:var(--muted)}
.tile .n{font-size:34px;font-weight:600;margin-top:6px;font-variant-numeric:tabular-nums}
.tile .s{font-size:13px;color:var(--muted);margin-top:2px}
h2.sec{font-size:17px;margin:30px 0 12px}
.chart{position:relative;height:220px;margin:8px 0 0 34px}
.grid-line{position:absolute;left:0;right:0;border-top:1px solid var(--line)}
.grid-line span{position:absolute;left:-34px;top:-8px;width:28px;text-align:right;font-size:11px;color:var(--muted);font-family:'IBM Plex Mono',monospace}
.bars{position:absolute;inset:0;display:flex;align-items:flex-end;gap:2px}
.col{flex:1;height:100%;display:flex;align-items:flex-end;cursor:default;position:relative}
.col .bar{width:100%;background:var(--accent);border-radius:4px 4px 0 0;min-height:0}
.col.zero .bar{height:2px!important;background:var(--line);border-radius:1px}
.col:hover .bar{filter:brightness(1.15)}
.col:hover::after{content:'';position:absolute;inset:0;background:rgba(255,255,255,.04);border-radius:4px;pointer-events:none}
.xlabels{display:flex;gap:2px;margin:6px 0 0 34px}
.xlabels span{flex:1;font-size:11px;color:var(--muted);text-align:center;white-space:nowrap;overflow:visible;font-family:'IBM Plex Mono',monospace}
.tip{position:absolute;pointer-events:none;background:var(--surface2);border:1px solid var(--line);border-radius:8px;padding:8px 11px;font-size:13px;white-space:nowrap;transform:translate(-50%,-100%);margin-top:-8px;z-index:2;box-shadow:0 6px 20px rgba(0,0,0,.35)}
.tip b{font-variant-numeric:tabular-nums}
.two{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:14px}
td.num,th.num{text-align:right;font-variant-numeric:tabular-nums}
.pill-ok{color:var(--accent)} .pill-err{color:var(--err)}
details summary{cursor:pointer;color:var(--muted);font-size:13px;margin-top:14px}
</style>

<h1 class="page">Estadísticas</h1>

<div class="tiles">
  <div class="card tile"><div class="k">Hoy</div><div class="n"><?= $days[$today]['v'] ?></div><div class="s"><?= $days[$today]['pv'] ?> páginas vistas</div></div>
  <div class="card tile"><div class="k">Últimos 7 días</div><div class="n"><?= $sum(7, 'v') ?></div><div class="s"><?= $sum(7, 'pv') ?> páginas vistas</div></div>
  <div class="card tile"><div class="k">Últimos 30 días</div><div class="n"><?= $sum(30, 'v') ?></div><div class="s"><?= $sum(30, 'pv') ?> páginas vistas</div></div>
  <div class="card tile"><div class="k">Logins fallidos · 24 h</div><div class="n" style="<?= $fails['24h'] ? 'color:var(--err)' : '' ?>"><?= $fails['24h'] ?></div><div class="s"><?= $fails['7d'] ?> en 7 días</div></div>
</div>

<div class="card" style="margin-top:14px">
  <div style="font-weight:600">Visitantes por día</div>
  <div class="muted" style="font-size:13px;margin-top:2px">Últimos <?= STATS_DAYS ?> días · personas distintas cada día (sin bots ni tus propias visitas)</div>
  <div class="chart" id="chart">
    <?php foreach (array_reverse($ticks) as $t): ?>
    <div class="grid-line" style="top:<?= 100 - $t / $yMax * 100 ?>%"><span><?= (int) $t ?></span></div>
    <?php endforeach; ?>
    <div class="bars">
      <?php foreach ($days as $d => $r): ?>
      <div class="col<?= $r['v'] ? '' : ' zero' ?>" data-day="<?= e($fmtDay($d, true)) ?>" data-v="<?= $r['v'] ?>" data-pv="<?= $r['pv'] ?>">
        <div class="bar" style="height:<?= round($r['v'] / $yMax * 100, 2) ?>%"></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="xlabels">
    <?php $i = 0; foreach ($days as $d => $r): ?>
    <span><?= ($i % 5 === 4 || $d === $today) && ($i >= 2) ? e($fmtDay($d)) : '' ?></span>
    <?php $i++; endforeach; ?>
  </div>
  <details>
    <summary>Ver como tabla</summary>
    <table style="margin-top:10px">
      <tr><th>Día</th><th class="num">Visitantes</th><th class="num">Páginas vistas</th></tr>
      <?php foreach (array_reverse($days, true) as $d => $r): ?>
      <tr><td><?= e($fmtDay($d, true)) ?></td><td class="num"><?= $r['v'] ?></td><td class="num"><?= $r['pv'] ?></td></tr>
      <?php endforeach; ?>
    </table>
  </details>
</div>

<div class="two" style="margin-top:14px">
  <div class="card">
    <div style="font-weight:600;margin-bottom:6px">Posts más leídos · 30 días</div>
    <table>
      <tr><th>Post</th><th class="num">Visitantes</th><th class="num">Vistas</th></tr>
      <?php foreach ($topPosts as $p): ?>
      <tr><td><a href="<?= e(url('post.php?slug=' . urlencode($p['slug']))) ?>" target="_blank"><?= e($p['title']) ?></a></td><td class="num"><?= (int) $p['v'] ?></td><td class="num"><?= (int) $p['pv'] ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$topPosts): ?><tr><td colspan="3" class="muted">Todavía sin datos.</td></tr><?php endif; ?>
    </table>
  </div>
  <div class="card">
    <div style="font-weight:600;margin-bottom:6px">De dónde llegan · 30 días</div>
    <table>
      <tr><th>Origen</th><th class="num">Visitantes</th></tr>
      <?php foreach ($sources as $s): ?>
      <tr><td title="<?= e($s['source']) ?>"><?= e(source_name($s['source'])) ?></td><td class="num"><?= (int) $s['v'] ?></td></tr>
      <?php endforeach; ?>
      <tr><td class="muted">Directo / navegando en el sitio</td><td class="num"><?= $directCount ?></td></tr>
    </table>
  </div>
</div>

<h2 class="sec">Seguridad · últimos intentos de login</h2>
<div class="card">
  <div class="muted" style="font-size:13px;margin-bottom:6px">Tras <?= LOGIN_MAX_FAILS ?> contraseñas incorrectas desde la misma IP, esa IP queda bloqueada <?= LOGIN_WINDOW_MIN ?> minutos. Tu IP ahora: <span class="mono"><?= e(client_ip()) ?></span></div>
  <table>
    <tr><th>Fecha</th><th>IP</th><th>Usuario probado</th><th>Resultado</th></tr>
    <?php foreach ($fails['last'] as $a): ?>
    <tr>
      <td class="mono" style="font-size:12px"><?= e(date('Y-m-d H:i', strtotime($a['created_at']))) ?></td>
      <td class="mono" style="font-size:12px"><?= e($a['ip']) ?></td>
      <td><?= e($a['username']) ?></td>
      <td><?= $a['success'] ? '<span class="pill-ok">✓ Correcto</span>' : '<span class="pill-err">✗ Fallido</span>' ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$fails['last']): ?><tr><td colspan="4" class="muted">Sin intentos registrados todavía.</td></tr><?php endif; ?>
  </table>
</div>

<script>
(function () {
  var chart = document.getElementById('chart'), tip = null;
  chart.querySelectorAll('.col').forEach(function (col) {
    col.addEventListener('mouseenter', function () {
      tip = document.createElement('div');
      tip.className = 'tip';
      var v = +col.dataset.v, pv = +col.dataset.pv;
      tip.innerHTML = '<div class="muted" style="font-size:12px;margin-bottom:3px"></div>'
        + '<div><b>' + v + '</b> visitante' + (v === 1 ? '' : 's') + '</div>'
        + '<div class="muted"><b>' + pv + '</b> página' + (pv === 1 ? '' : 's') + ' vista' + (pv === 1 ? '' : 's') + '</div>';
      tip.firstChild.textContent = col.dataset.day;
      var bar = col.querySelector('.bar');
      var cr = chart.getBoundingClientRect(), br = bar.getBoundingClientRect(), colr = col.getBoundingClientRect();
      var x = colr.left - cr.left + colr.width / 2;
      tip.style.left = x + 'px';
      tip.style.top = (br.top - cr.top) + 'px';
      chart.appendChild(tip);
      // que no se salga por los lados
      var tr = tip.getBoundingClientRect();
      if (tr.left < cr.left - 34) tip.style.left = (x + (cr.left - 34 - tr.left)) + 'px';
      if (tr.right > cr.right) tip.style.left = (x - (tr.right - cr.right)) + 'px';
    });
    col.addEventListener('mouseleave', function () { if (tip) { tip.remove(); tip = null; } });
  });
})();
</script>
<?php admin_foot(); ?>
