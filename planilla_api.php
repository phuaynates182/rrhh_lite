<?php
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/boletas.php';
header('Content-Type: application/json; charset=utf-8');
$me = user();
if (!$me) { http_response_code(401); echo json_encode(['ok' => false, 'error' => 'Sesión expirada']); exit; }
if (!is_post()) { http_response_code(405); exit; }
csrf_verify();

try {
    $pid = (int)post('periodo'); $eid = (int)post('emp');
    $valores = (array)($_POST['c'] ?? []);
    $dias = post('dias') === '' ? null : (int)post('dias');
    $r = guardar_boleta($pid, $eid, $valores, $dias, (int)$me['id']);
    audit('BOLETA_CALCULADA', 'boletas', $r['boleta_id'], "periodo=$pid emp=$eid neto=" . $r['neto']);
    echo json_encode([
        'ok' => true, 'boleta' => $r['boleta_id'], 'dias' => $r['dias'], 'bruto' => $r['bruto'], 'descuentos' => $r['descuentos'],
        'neto' => $r['neto'], 'aportes' => $r['aportes'],
    ]);
} catch (RuntimeException $ex) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => $ex->getMessage()]);
}
