<?php
/** Recibe el aviso "abrí esta demo" que manda el app.js de cada demo de /pwa/ (sendBeacon). */
require __DIR__ . '/config.php';
require_once __DIR__ . '/partials/visits.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    track_demo_hit(
        substr((string) ($_POST['path'] ?? ''), 0, 255),
        (string) ($_POST['mode'] ?? ''),
        ($_POST['ret'] ?? '') === '1'
    );
}
http_response_code(204);
header('Cache-Control: no-store');
