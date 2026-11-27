<?php
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/boletas.php';
require_once __DIR__ . '/includes/tabular.php';
require_once __DIR__ . '/includes/empleados.php';
$me = require_login();

$pid = (int)get('id');
$per = row('SELECT p.*, ua.nombre AS aprob FROM periodos p LEFT JOIN usuarios ua ON ua.id = p.aprobado_por WHERE p.id = ?', [$pid]);
if (!$per) { flash('err', 'Periodo no encontrado.'); redirect('planillas.php'); }
$estado = $per['estado'];

// ------------------------------------------------------------ Acciones del flujo
if (is_post()) {
    csrf_verify();
    $a = post('accion');
    try {
        $n = (int)val('SELECT COUNT(*) FROM boletas WHERE periodo_id = ?', [$pid]);
        if ($a === 'enviar') {
            if ($estado !== 'BORRADOR') throw new RuntimeException('El periodo no está en borrador.');
            if ($n === 0) throw new RuntimeException('Calcule al menos una boleta antes de enviar a aprobación.');
            q("UPDATE periodos SET estado='PENDIENTE', enviado_por=? WHERE id=?", [$me['id'], $pid]);
            audit('PLANILLA_ENVIADA', 'periodos', $pid);
            flash('ok', 'Planilla enviada a aprobación de Gerencia.');
        } elseif ($a === 'devolver') {
            if ($estado !== 'PENDIENTE') throw new RuntimeException('Solo se devuelve una planilla pendiente.');
            q("UPDATE periodos SET estado='BORRADOR' WHERE id=?", [$pid]);
            audit('PLANILLA_DEVUELTA', 'periodos', $pid);
            flash('ok', 'Planilla devuelta a borrador.');
        } elseif ($a === 'aprobar') {
            if (!is_gerente()) throw new RuntimeException('Solo un usuario Gerente puede aprobar y firmar.');
            if ($estado !== 'PENDIENTE') throw new RuntimeException('La planilla debe estar pendiente de aprobación.');
            $firma = empresa()['firma_path'] ?? null;
            if (!$firma) throw new RuntimeException('Configure la firma de gerencia en Empresa antes de aprobar.');
            q("UPDATE periodos SET estado='APROBADO', aprobado_por=?, aprobado_en=NOW(), firma_path=? WHERE id=?", [$me['id'], $firma, $pid]);
            audit('PLANILLA_APROBADA', 'periodos', $pid, 'Firma de gerencia estampada en ' . $n . ' boletas');
            flash('ok', "Planilla aprobada. Se estampó la firma de gerencia en $n boletas.");
        } elseif ($a === 'reabrir') {
            if (!is_gerente()) throw new RuntimeException('Solo un Gerente puede reabrir una planilla aprobada.');
            q("UPDATE periodos SET estado='BORRADOR', aprobado_por=NULL, aprobado_en=NULL, firma_path=NULL WHERE id=?", [$pid]);
            audit('PLANILLA_REABIERTA', 'periodos', $pid);
            flash('warn', 'Planilla reabierta. Las boletas pierden la firma hasta una nueva aprobación.');
        } elseif ($a === 'quitar') {
            if ($estado !== 'BORRADOR') throw new RuntimeException('Periodo bloqueado.');
            q('DELETE FROM boletas WHERE periodo_id = ? AND empleado_id = ?', [$pid, (int)post('emp')]);
        } elseif ($a === 'importar_var') {
            if ($estado !== 'BORRADOR') throw new RuntimeException('Periodo bloqueado.');
            if (empty($_FILES['archivo']['name'])) throw new RuntimeException('Seleccione un archivo.');
            $tabla = leer_tabla($_FILES['archivo']['tmp_name'], pathinfo($_FILES['archivo']['name'], PATHINFO_EXTENSION));
            if (count($tabla) < 2) throw new RuntimeException('El archivo no tiene datos.');
            $hdr = array_map('norm_header', $tabla[0]);
            $iDoc = array_search('dni', $hdr, true);
            if ($iDoc === false) $iDoc = array_search('documento', $hdr, true);
            if ($iDoc === false) throw new RuntimeException('Falta la columna DNI.');
            $iDias = array_search('dias', $hdr, true);
            $cods = [];
            foreach (conceptos_activos() as $c) { $ix = array_search(norm_header($c['codigo']), $hdr, true); if ($ix !== false) $cods[$c['codigo']] = $ix; }
            $ok = 0; $bad = [];
            foreach (array_slice($tabla, 1) as $r) {
                $doc = preg_replace('/\.0$/', '', trim((string)($r[$iDoc] ?? '')));
                if ($doc === '') continue;
                $emp = row('SELECT id FROM empleados WHERE nro_doc = ?', [$doc]);
                if (!$emp) { $bad[] = $doc; continue; }
                $vals = [];
                foreach ($cods as $cod => $ix) $vals[$cod] = $r[$ix] ?? 0;
                $dias = ($iDias !== false && trim((string)($r[$iDias] ?? '')) !== '') ? (int)$r[$iDias] : null;
                guardar_boleta($pid, (int)$emp['id'], $vals, $dias, (int)$me['id']);
                $ok++;
            }
            audit('VARIABLES_IMPORTADAS', 'periodos', $pid, "$ok boletas");
            flash('ok', "Se calcularon $ok boletas desde el archivo." . ($bad ? ' DNI no encontrados: ' . implode(', ', array_slice($bad, 0, 8)) : ''));
        }
    } catch (RuntimeException $ex) { flash('err', $ex->getMessage()); }
    redirect('planilla.php?id=' . $pid);
}

// ------------------------------------------------------------ Datos
$finMes = date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $per['anio'], $per['mes'])));
$emps = rows("SELECT e.*, b.id AS bid, b.dias_laborados, b.bruto, b.total_descuentos, b.neto, b.total_aportes
              FROM empleados e LEFT JOIN boletas b ON b.empleado_id = e.id AND b.periodo_id = ?
              WHERE (e.estado = 'ACTIVO' AND e.fecha_ingreso <= ?) OR b.id IS NOT NULL
              ORDER BY e.apellido_paterno, e.apellido_materno, e.nombres", [$pid, $finMes]);
$conceptos = conceptos_activos();
$tot = ['bruto' => 0, 'desc' => 0, 'neto' => 0, 'apor' => 0, 'n' => 0];
foreach ($emps as $e) if ($e['bid']) { $tot['bruto'] += $e['bruto']; $tot['desc'] += $e['total_descuentos']; $tot['neto'] += $e['neto']; $tot['apor'] += $e['total_aportes']; $tot['n']++; }

if (get('export') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="planilla_' . $per['anio'] . '_' . $per['mes'] . '_' . strtolower($per['tipo']) . '.csv"');
    echo "\xEF\xBB\xBF";
    $o = fopen('php://output', 'w');
    fputcsv($o, ['Documento', 'Apellidos y nombres', 'Cargo', 'Dias', 'Bruto', 'Descuentos', 'Neto a pagar', 'Aportes empleador', 'Banco', 'Cuenta'], ';');
    foreach ($emps as $e) if ($e['bid']) fputcsv($o, [$e['nro_doc'], nombre_completo($e), $e['cargo'], $e['dias_laborados'], $e['bruto'], $e['total_descuentos'], $e['neto'], $e['total_aportes'], $e['banco'], $e['nro_cuenta']], ';');
    fclose($o);
    exit;
}

// Asiento contable (PCGE) según el Excel de referencia
$asiento = null;
if ($tot['n'] > 0) {
    $s = [];
    foreach (rows('SELECT l.codigo, l.tipo, SUM(l.monto) AS m FROM boleta_lineas l JOIN boletas b ON b.id = l.boleta_id WHERE b.periodo_id = ? GROUP BY l.codigo, l.tipo', [$pid]) as $r) $s[$r['codigo']] = (float)$r['m'];
    $g = fn($k) => $s[$k] ?? 0.0;
    $afp = $g('AFP_APORTE') + $g('AFP_COMISION') + $g('AFP_PRIMA');
    $otros = $tot['desc'] - $g('ONP') - $afp - $g('RENTA5');
    $asiento = [
        'debe' => [['621', 'Remuneraciones', $tot['bruto']], ['6271', 'Régimen de prestaciones de salud (EsSalud)', $g('ESSALUD')], ['6273', 'SCTR', $g('SCTR')]],
        'haber' => [['4031', 'EsSalud por pagar', $g('ESSALUD')], ['4032', 'ONP por pagar', $g('ONP')], ['40391', 'SCTR por pagar', $g('SCTR')],
            ['4017', 'Renta de 5ta categoría por pagar', $g('RENTA5')], ['4699', 'AFP por pagar (aporte, comisión y prima)', $afp],
            ['469', 'Otros descuentos / terceros', $otros], ['411', 'Remuneraciones por pagar (neto)', $tot['neto']]],
    ];
}

$cls = ['BORRADOR' => 'warn', 'PENDIENTE' => 'info', 'APROBADO' => 'ok'];
$edit = $estado === 'BORRADOR';
page_head(nombre_periodo($per), 'planillas');
?>
<div class="card">
  <div class="toolbar">
    <div><span class="badge <?= $cls[$estado] ?>" style="font-size:13px"><?= $estado ?></span>
      <?php if ($estado === 'APROBADO'): ?><span class="muted"> Aprobado por <b><?= e($per['aprob']) ?></b> el <?= date('d/m/Y H:i', strtotime($per['aprobado_en'])) ?></span><?php endif; ?>
      <?php if ($per['fecha_pago']): ?><span class="muted"> · Pago: <?= fdate($per['fecha_pago']) ?></span><?php endif; ?></div>
    <div class="actions">
      <a class="btn ghost sm" href="planillas.php">← Periodos</a>
      <?php if ($tot['n']): ?><a class="btn ghost sm" href="?id=<?= $pid ?>&export=csv">⬇ Exportar CSV</a>
        <a class="btn ghost sm" target="_blank" href="boleta.php?p=<?= $pid ?>">🖨️ Ver/imprimir boletas en lote</a><?php endif; ?>
      <?php if ($estado === 'BORRADOR'): ?>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="accion" value="enviar"><button class="btn sm warn" data-confirm="¿Enviar la planilla a aprobación? Quedará bloqueada para edición.">📨 Enviar a aprobación</button></form>
      <?php elseif ($estado === 'PENDIENTE'): ?>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="accion" value="devolver"><button class="btn sm ghost">↩ Devolver a borrador</button></form>
        <?php if (is_gerente()): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="accion" value="aprobar"><button class="btn sm ok" data-confirm="Al aprobar se estampará la firma de gerencia en todas las boletas. ¿Continuar?">✅ Aprobar y firmar</button></form>
        <?php else: ?><span class="badge">Espera al usuario Gerente</span><?php endif; ?>
      <?php else: ?>
        <?php if (is_gerente()): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="accion" value="reabrir"><button class="btn sm bad" data-confirm="¿Reabrir? Se retirará la firma de las boletas.">Reabrir</button></form><?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
  <div class="grid g4">
    <div class="stat"><b><?= $tot['n'] ?> / <?= count($emps) ?></b><span>Boletas calculadas</span></div>
    <div class="stat"><b>S/ <?= money($tot['bruto']) ?></b><span>Total bruto</span></div>
    <div class="stat"><b>S/ <?= money($tot['desc']) ?></b><span>Total descuentos</span></div>
    <div class="stat"><b>S/ <?= money($tot['neto']) ?></b><span>Total neto a percibir</span></div>
  </div>
</div>

<?php if ($edit): ?>
<div class="alert info">
  <?php if ($per['tipo'] === 'MENSUAL'): ?>Ingrese el <b>sueldo variable</b> (bonos, comisiones, incentivos) solo cuando corresponda; es opcional. El sueldo fijo, asignación familiar, pensiones, 5ta categoría y aportes se calculan automáticamente.
  <?php elseif ($per['tipo'] === 'GRATIFICACION'): ?>Gratificación: 1/6 de la remuneración computable por mes completo del semestre + bonificación extraordinaria 9%. En «afectos» ingrese el <b>promedio semestral</b> de variables, si lo hubiera.
  <?php else: ?>CTS semestral: (remuneración computable + 1/6 de gratificación) ÷ 12 por mes y ÷ 360 por día. En «afectos» ingrese el <b>promedio semestral</b> de variables.<?php endif; ?>
</div>
<?php endif; ?>

<div class="card">
  <div class="toolbar">
    <input type="search" placeholder="Buscar colaborador…" data-filter="#tabPla">
    <?php if ($edit): ?>
    <div class="actions">
      <button class="btn sm" id="btnSel">⚡ Calcular seleccionados</button>
      <button class="btn sm ghost" id="btnAll">⚡ Calcular todos</button>
      <form method="post" enctype="multipart/form-data" class="actions"><?= csrf_field() ?><input type="hidden" name="accion" value="importar_var">
        <input type="file" name="archivo" accept=".csv,.xlsx,.xlsm" required style="max-width:200px"><button class="btn sm ghost">📥 Importar variables</button>
        <a class="hint" href="plantilla_csv.php?t=variables">plantilla</a></form>
    </div>
    <?php endif; ?>
  </div>
  <div class="progress" id="prog" style="display:none"><i></i></div>
  <div class="tablewrap"><table id="tabPla" data-periodo="<?= $pid ?>" data-edit="<?= $edit ? 1 : 0 ?>" data-mensual="<?= $per['tipo'] === 'MENSUAL' ? 1 : 0 ?>">
    <thead><tr>
      <?php if ($edit): ?><th><input type="checkbox" data-checkall=".chk-row"></th><?php endif; ?>
      <th>Documento</th><th>Colaborador</th><th class="num">Sueldo fijo</th>
      <?php if ($per['tipo'] === 'MENSUAL'): ?><th class="num">Días</th><?php endif; ?>
      <?php foreach ($conceptos as $c): ?><th class="num" title="<?= $c['tipo'] === 'ING' ? 'Ingreso' : 'Descuento' ?>"><?= e($c['nombre']) ?><?= $c['tipo'] === 'DESC' ? ' (−)' : '' ?></th><?php endforeach; ?>
      <th class="num">Bruto</th><th class="num">Descuentos</th><th class="num">Neto a percibir</th><th class="num">Aporte empleador</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($emps as $e):
      $vals = $e['bid'] ? valores_manuales((int)$e['bid']) : []; ?>
      <tr data-emp="<?= $e['id'] ?>" class="<?= $e['bid'] ? '' : 'sin' ?>">
        <?php if ($edit): ?><td><input type="checkbox" class="chk-row"></td><?php endif; ?>
        <td><?= e($e['nro_doc']) ?></td>
        <td><a href="empleado_ver.php?id=<?= $e['id'] ?>"><?= e(nombre_completo($e)) ?></a><div class="hint"><?= e($e['cargo']) ?></div></td>
        <td class="num"><?= money($e['sueldo_basico']) ?></td>
        <?php if ($per['tipo'] === 'MENSUAL'): ?><td class="num"><?php if ($edit): ?><input class="w50" name="dias" type="number" min="0" max="30" value="<?= $e['bid'] ? (int)$e['dias_laborados'] : Payroll::diasMes($e, (int)$per['anio'], (int)$per['mes']) ?>"><?php else: ?><?= (int)$e['dias_laborados'] ?><?php endif; ?></td><?php endif; ?>
        <?php foreach ($conceptos as $c): $v = $vals[$c['codigo']] ?? 0; ?>
          <td class="num"><?php if ($edit): ?><input name="c[<?= e($c['codigo']) ?>]" type="number" step="0.01" min="0" value="<?= $v > 0 ? e($v) : '' ?>" placeholder="0.00"><?php else: ?><?= $v > 0 ? money($v) : '—' ?><?php endif; ?></td>
        <?php endforeach; ?>
        <td class="num c-bruto"><?= $e['bid'] ? money($e['bruto']) : '—' ?></td>
        <td class="num c-desc"><?= $e['bid'] ? money($e['total_descuentos']) : '—' ?></td>
        <td class="num c-neto"><b><?= $e['bid'] ? 'S/ ' . money($e['neto']) : '—' ?></b></td>
        <td class="num c-apor muted"><?= $e['bid'] ? money($e['total_aportes']) : '—' ?></td>
        <td class="actions"><?php if ($e['bid']): ?><a class="btn sm ghost" target="_blank" href="boleta.php?b=<?= $e['bid'] ?>">Boleta</a><?php endif; ?>
          <?php if ($edit): ?><button class="btn sm row-save"><?= $e['bid'] ? '✏️ Recalcular' : 'Calcular' ?></button><?php endif; ?></td>
      </tr>
    <?php endforeach; if (!$emps): ?><tr><td colspan="20" class="muted">No hay colaboradores activos. <a href="empleados.php">Agregue colaboradores</a> o importe un Excel.</td></tr><?php endif; ?>
    </tbody>
    <tfoot><tr><td colspan="<?= ($edit ? 4 : 3) + ($per['tipo'] === 'MENSUAL' ? 1 : 0) + count($conceptos) ?>" class="right">TOTALES</td>
      <td class="num" id="t-bruto"><?= money($tot['bruto']) ?></td><td class="num" id="t-desc"><?= money($tot['desc']) ?></td>
      <td class="num" id="t-neto">S/ <?= money($tot['neto']) ?></td><td class="num" id="t-apor"><?= money($tot['apor']) ?></td><td></td></tr></tfoot>
  </table></div>
</div>

<?php if ($asiento): ?>
<div class="card"><h2>📒 Asiento contable de provisión (PCGE)</h2>
  <div class="tablewrap"><table><thead><tr><th>Cuenta</th><th>Descripción</th><th class="num">Debe</th><th class="num">Haber</th></tr></thead><tbody>
  <?php $td = $th = 0; foreach ($asiento['debe'] as [$c, $d, $m]): if ($m == 0) continue; $td += $m; ?><tr><td><?= $c ?></td><td><?= e($d) ?></td><td class="num"><?= money($m) ?></td><td></td></tr><?php endforeach;
  foreach ($asiento['haber'] as [$c, $d, $m]): if ($m == 0) continue; $th += $m; ?><tr><td><?= $c ?></td><td style="padding-left:28px"><?= e($d) ?></td><td></td><td class="num"><?= money($m) ?></td></tr><?php endforeach; ?>
  </tbody><tfoot><tr><td colspan="2">Sumas iguales</td><td class="num"><?= money($td) ?></td><td class="num"><?= money($th) ?></td></tr></tfoot></table></div>
  <p class="hint">Glosa: por la provisión de la planilla de <?= e(MESES[(int)$per['mes']]) ?> <?= $per['anio'] ?>. Las subcuentas pueden ajustarse al plan contable de la empresa.</p>
</div>
<?php endif; ?>
<script src="assets/js/planilla.js"></script>
<?php page_foot();
