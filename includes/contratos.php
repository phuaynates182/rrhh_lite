<?php
declare(strict_types=1);
require_once __DIR__ . '/tabular.php';
require_once __DIR__ . '/empleados.php';

const TIPOS_TRAB = ['INDEFINIDO' => 'Plazo indeterminado', 'PLAZO_FIJO' => 'Plazo fijo (modal)', 'TIEMPO_PARCIAL' => 'Tiempo parcial', 'PRACTICANTE' => 'Practicante (Ley 28518)'];

function contrato_render(string $cuerpo, array $e, array $c, bool $conFirma): string
{
    $emp = empresa();
    $firma = ($conFirma && !empty($emp['firma_path'])) ? '<img src="file.php?e=firma" alt="Firma" style="max-height:70px">' : '<div style="height:70px"></div>';
    $dom = trim(($e['direccion'] ?? '') . ' ' . ($e['distrito'] ?? '')) ?: '____________________';
    $map = [
        'EMPRESA' => $emp['razon_social'], 'RUC' => $emp['ruc'] ?: '___________', 'DOMICILIO_EMPRESA' => $emp['direccion'] ?: '____________________',
        'REP_NOMBRE' => $emp['rep_nombre'] ?: '____________________', 'REP_DNI' => $emp['rep_dni'] ?: '________', 'REP_CARGO' => $emp['rep_cargo'],
        'TRABAJADOR' => mb_strtoupper(trim($e['apellido_paterno'] . ' ' . $e['apellido_materno'] . ' ' . $e['nombres'])),
        'TIPO_DOC' => $e['tipo_doc'], 'NRO_DOC' => $e['nro_doc'], 'DOMICILIO' => $dom, 'NACIONALIDAD' => $e['nacionalidad'] ?: 'Peruana',
        'ESTADO_CIVIL' => $e['estado_civil'] ? mb_strtolower($e['estado_civil']) : '________', 'CARGO' => $c['cargo'] ?: $e['cargo'], 'AREA' => $e['area'] ?: '________',
        'SUELDO' => money($c['sueldo']), 'SUELDO_LETRAS' => num_letras((float)$c['sueldo']), 'FECHA_INICIO' => fecha_larga($c['fecha_inicio']),
        'FECHA_FIN' => $c['fecha_fin'] ? fecha_larga($c['fecha_fin']) : '________', 'JORNADA' => (string)$e['jornada_horas'],
        'CAUSA' => $c['causa'] ?: '____________________', 'CIUDAD' => $emp['ciudad'] ?: 'Lima', 'FECHA_HOY' => fecha_larga(date('Y-m-d')),
        'PERIODO_PRUEBA' => '3 (tres) meses',
    ];
    foreach ($map as $k => $v) $cuerpo = str_replace('{{' . $k . '}}', htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'), $cuerpo);
    return str_replace('{{FIRMA_EMPLEADOR}}', $firma, $cuerpo);
}

function fecha_larga(string $d): string
{
    $t = strtotime($d);
    return date('j', $t) . ' de ' . mb_strtolower(MESES[(int)date('n', $t)]) . ' de ' . date('Y', $t);
}

/** Valida y crea un contrato. Devuelve id. */
function crear_contrato(array $e, array $pl, array $o, ?string $lote, int $userId): int
{
    $ini = $o['fecha_inicio']; $fin = $o['fecha_fin'] ?: null;
    if (!valid_date($ini)) throw new RuntimeException('Fecha de inicio inválida.');
    $tipo = $pl['tipo_trabajador'];
    if (in_array($tipo, ['PLAZO_FIJO', 'PRACTICANTE'], true)) {
        if (!$fin || !valid_date($fin)) throw new RuntimeException('Este tipo de contrato requiere fecha de término.');
        if ($fin <= $ini) throw new RuntimeException('La fecha de término debe ser posterior al inicio.');
        if ($tipo === 'PLAZO_FIJO') {
            if (trim((string)$o['causa']) === '') throw new RuntimeException('El contrato modal requiere la causa objetiva (art. 72 D.S. 003-97-TR).');
            if ((new DateTime($ini))->modify('+5 years') < new DateTime($fin)) throw new RuntimeException('Un contrato modal no puede exceder 5 años de duración (art. 74).');
        }
    } else {
        $fin = null;
    }
    $sueldo = (float)$o['sueldo'];
    if ($sueldo <= 0) throw new RuntimeException('Ingrese una remuneración mayor a cero.');
    if ($tipo !== 'TIEMPO_PARCIAL' && $sueldo < (params()['rmv'] ?? 0) - 0.001) throw new RuntimeException('La remuneración es menor a la RMV vigente (S/ ' . money(params()['rmv']) . ').');
    $c = ['cargo' => $o['cargo'] ?: $e['cargo'], 'sueldo' => $sueldo, 'fecha_inicio' => $ini, 'fecha_fin' => $fin, 'causa' => $o['causa']];
    $cuerpo = contrato_render($pl['cuerpo'], $e, $c, !empty($o['con_firma']));
    q('INSERT INTO contratos (empleado_id, plantilla_id, lote, tipo_trabajador, fecha_inicio, fecha_fin, cargo, sueldo, causa_objetiva, con_firma, cuerpo, estado, creado_por)
       VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
        [$e['id'], $pl['id'], $lote, $tipo, $ini, $fin, $c['cargo'], $sueldo, $o['causa'] ?: null, !empty($o['con_firma']) ? 1 : 0, $cuerpo, 'EMITIDO', $userId]);
    $id = (int)db()->lastInsertId();
    audit('CONTRATO_GENERADO', 'contratos', $id, nombre_completo($e) . ' · ' . $pl['nombre']);
    return $id;
}
