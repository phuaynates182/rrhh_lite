<?php
declare(strict_types=1);
require_once __DIR__ . '/payroll.php';

function conceptos_activos(): array
{
    return rows('SELECT * FROM conceptos WHERE activo = 1 ORDER BY orden, id');
}

/**
 * Calcula y guarda (upsert) la boleta de un empleado en un periodo.
 * $valores: ['CODIGO' => monto] de conceptos variables (opcionales).
 * Devuelve el resumen de la boleta. Lanza RuntimeException si el periodo no es editable.
 */
function guardar_boleta(int $periodoId, int $empId, array $valores, ?int $dias = null, ?int $userId = null): array
{
    $per = row('SELECT * FROM periodos WHERE id = ?', [$periodoId]);
    if (!$per) throw new RuntimeException('Periodo no encontrado.');
    if ($per['estado'] !== 'BORRADOR') throw new RuntimeException('El periodo no está en borrador; no se puede modificar.');
    $emp = row('SELECT * FROM empleados WHERE id = ?', [$empId]);
    if (!$emp) throw new RuntimeException('Colaborador no encontrado.');
    $pen = $emp['sistema_pension_id'] ? row('SELECT * FROM sistemas_pension WHERE id = ?', [$emp['sistema_pension_id']]) : null;
    $P = params();
    foreach (['rmv', 'asig_fam_pct', 'essalud_pct', 'sctr_pct', 'uit', 'tope_rma', 'quinta_uit_deduccion', 'bonif_extra_pct'] as $k) {
        if (!isset($P[$k])) throw new RuntimeException("Falta el parámetro legal '$k'.");
    }

    // Conceptos variables con monto > 0
    $manual = [];
    foreach (conceptos_activos() as $c) {
        $m = isset($valores[$c['codigo']]) ? round(fnum($valores[$c['codigo']]), 2) : 0.0;
        if ($m < 0) throw new RuntimeException('Los montos no pueden ser negativos.');
        if ($m > 0) $manual[] = ['codigo' => $c['codigo'], 'descripcion' => $c['nombre'], 'tipo' => $c['tipo'], 'afecto' => (int)$c['afecto'], 'monto' => $m];
    }

    $anio = (int)$per['anio']; $mes = (int)$per['mes'];
    if ($per['tipo'] === 'MENSUAL') {
        $dias = $dias ?? Payroll::diasMes($emp, $anio, $mes);
        $prev = row("SELECT COALESCE(SUM(l.monto),0) AS q FROM boleta_lineas l
                     JOIN boletas b ON b.id = l.boleta_id JOIN periodos p ON p.id = b.periodo_id
                     WHERE b.empleado_id = ? AND p.anio = ? AND p.mes < ? AND p.tipo = 'MENSUAL' AND l.codigo = 'RENTA5'", [$empId, $anio, $mes]);
        $var = row("SELECT COALESCE(SUM(l.monto),0) AS v FROM boleta_lineas l
                    JOIN boletas b ON b.id = l.boleta_id JOIN periodos p ON p.id = b.periodo_id
                    JOIN conceptos c ON c.codigo = l.codigo AND c.tipo = 'ING' AND c.afecto = 1
                    WHERE b.empleado_id = ? AND p.anio = ? AND p.mes < ? AND p.tipo = 'MENSUAL' AND l.manual = 1", [$empId, $anio, $mes]);
        $res = Payroll::mensual($emp, $pen, $P, $manual, $dias, $anio, $mes, ['quinta' => (float)$prev['q'], 'variables' => (float)$var['v']]);
    } elseif ($per['tipo'] === 'GRATIFICACION') {
        $res = Payroll::gratificacion($emp, $P, $manual, $anio, $mes);
    } else {
        $res = Payroll::cts($emp, $P, $manual, $anio, $mes);
    }

    $snap = json_encode([
        'nombre' => trim($emp['apellido_paterno'] . ' ' . $emp['apellido_materno'] . ', ' . $emp['nombres']),
        'tipo_doc' => $emp['tipo_doc'], 'nro_doc' => $emp['nro_doc'], 'cargo' => $emp['cargo'], 'area' => $emp['area'],
        'fecha_ingreso' => $emp['fecha_ingreso'], 'cuspp' => $emp['cuspp'],
        'pension' => $pen ? $pen['nombre'] : 'Sin régimen', 'tipo_trabajador' => $emp['tipo_trabajador'],
        'banco' => $emp['banco'], 'nro_cuenta' => $emp['nro_cuenta'], 'sueldo_basico' => $emp['sueldo_basico'],
    ], JSON_UNESCAPED_UNICODE);

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $b = row('SELECT id FROM boletas WHERE periodo_id = ? AND empleado_id = ?', [$periodoId, $empId]);
        if ($b) {
            $bid = (int)$b['id'];
            q('UPDATE boletas SET dias_laborados=?, remuneracion_afecta=?, bruto=?, total_descuentos=?, neto=?, total_aportes=?, snap=?, generado_por=? WHERE id=?',
                [$res['dias'], $res['afecta'], $res['bruto'], $res['descuentos'], $res['neto'], $res['aportes'], $snap, $userId, $bid]);
            q('DELETE FROM boleta_lineas WHERE boleta_id = ?', [$bid]);
        } else {
            q('INSERT INTO boletas (periodo_id, empleado_id, dias_laborados, remuneracion_afecta, bruto, total_descuentos, neto, total_aportes, snap, codigo_verif, generado_por)
               VALUES (?,?,?,?,?,?,?,?,?,?,?)',
                [$periodoId, $empId, $res['dias'], $res['afecta'], $res['bruto'], $res['descuentos'], $res['neto'], $res['aportes'], $snap,
                 strtoupper(bin2hex(random_bytes(5))), $userId]);
            $bid = (int)$pdo->lastInsertId();
        }
        $ord = 0;
        $ins = $pdo->prepare('INSERT INTO boleta_lineas (boleta_id, tipo, codigo, descripcion, monto, manual, orden) VALUES (?,?,?,?,?,?,?)');
        foreach ($res['lineas'] as $l) $ins->execute([$bid, $l[0], $l[1], $l[2], $l[3], $l[4], $ord++]);
        $pdo->commit();
    } catch (Throwable $ex) {
        $pdo->rollBack();
        throw $ex;
    }
    return ['boleta_id' => $bid] + $res;
}

/** Valores variables guardados de una boleta: ['CODIGO' => monto] */
function valores_manuales(int $boletaId): array
{
    $o = [];
    foreach (rows('SELECT codigo, monto FROM boleta_lineas WHERE boleta_id = ? AND manual = 1', [$boletaId]) as $r) $o[$r['codigo']] = (float)$r['monto'];
    return $o;
}

function nombre_periodo(array $p): string
{
    $m = MESES[(int)$p['mes']] ?? $p['mes'];
    $t = ['MENSUAL' => 'Remuneraciones', 'GRATIFICACION' => 'Gratificación', 'CTS' => 'CTS'][$p['tipo']] ?? $p['tipo'];
    return "$t · $m {$p['anio']}";
}
