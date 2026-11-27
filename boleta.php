<?php
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/boletas.php';
require_login();

$ids = [];
if (get('b') !== '') {
    $b = row('SELECT periodo_id FROM boletas WHERE id = ?', [(int)get('b')]);
    if ($b) { $ids = [(int)get('b')]; $pid = (int)$b['periodo_id']; }
} elseif (get('p') !== '') {
    $pid = (int)get('p');
    $ids = array_map('intval', array_column(rows('SELECT b.id FROM boletas b JOIN empleados e ON e.id = b.empleado_id WHERE b.periodo_id = ? ORDER BY e.apellido_paterno, e.apellido_materno, e.nombres', [$pid]), 'id'));
}
if (!$ids) { http_response_code(404); exit('No hay boletas para mostrar.'); }
$per = row('SELECT * FROM periodos WHERE id = ?', [$pid]);
$emp = empresa();
$firmada = $per['estado'] === 'APROBADO' && !empty($per['firma_path']);
$fin = date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $per['anio'], $per['mes'])));
if ($per['fecha_pago']) $fpago = fdate($per['fecha_pago']); else $fpago = fdate($fin);

function money_cell($m) { return 'S/ ' . money($m); }
?><!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8"><title>Boletas · <?= e(nombre_periodo($per)) ?></title>
<style>
 @page{size:A4;margin:12mm}
 body{font-family:Arial,Helvetica,sans-serif;color:#000;background:#e8ebf3;margin:0}
 .bar{position:sticky;top:0;background:#1d1450;color:#fff;padding:10px 18px;display:flex;gap:12px;align-items:center;z-index:5}
 .bar button{background:#7c9cff;border:0;border-radius:8px;color:#fff;padding:8px 16px;font-weight:700;cursor:pointer}
 .bol{width:190mm;min-height:130mm;margin:14px auto;padding:10mm;background:#fff;box-shadow:0 4px 18px rgba(0,0,0,.2);position:relative;page-break-after:always;font-size:11px}
 .bol:last-child{page-break-after:auto}
 .h{display:flex;justify-content:space-between;align-items:flex-start}
 .h img{max-height:52px;max-width:180px}
 h2{text-align:center;font-size:15px;margin:10px 0 2px} .sub{text-align:center;font-size:10px;margin:0 0 10px}
 table{width:100%;border-collapse:collapse;margin-top:6px}
 td,th{border:1px solid #000;padding:4px 6px;vertical-align:top}
 th{background:#eee;text-align:left;font-size:10.5px}
 .l{display:flex;justify-content:space-between;gap:6px}
 .tot td{font-weight:700;background:#f6f6f6}
 .neto td{font-size:13px;font-weight:700}
 .sig{display:flex;justify-content:space-around;margin-top:34px;text-align:center}
 .sig .b{width:230px}.sig .line{border-top:1px solid #000;margin-top:4px;padding-top:3px;font-size:10px}
 .sig img{max-height:70px;display:block;margin:0 auto}
 .wm{position:absolute;top:44%;left:0;right:0;text-align:center;font-size:54px;font-weight:700;color:rgba(200,0,0,.13);transform:rotate(-24deg);pointer-events:none}
 .foot{font-size:9px;color:#444;margin-top:10px;display:flex;justify-content:space-between}
 @media print{body{background:#fff}.bar{display:none}.bol{margin:0;box-shadow:none;width:auto;min-height:0}}
</style></head><body>
<div class="bar"><b><?= e(nombre_periodo($per)) ?></b><span><?= count($ids) ?> boleta(s) · <?= $firmada ? 'Aprobada con firma de gerencia' : 'BORRADOR / sin aprobar (sin validez)' ?></span>
<button onclick="window.print()">🖨️ Imprimir / Guardar como PDF</button></div>
<?php foreach ($ids as $bid):
    $b = row('SELECT * FROM boletas WHERE id = ?', [$bid]);
    $s = json_decode($b['snap'] ?? '{}', true) ?: [];
    $lin = ['ING' => [], 'DESC' => [], 'APORTE' => []];
    foreach (rows('SELECT * FROM boleta_lineas WHERE boleta_id = ? ORDER BY orden', [$bid]) as $l) $lin[$l['tipo']][] = $l;
    ?>
<div class="bol">
  <?php if (!$firmada): ?><div class="wm">SIN APROBAR</div><?php endif; ?>
  <div class="h">
    <div><?php if (!empty($emp['logo_path'])): ?><img src="file.php?e=logo" alt=""><?php endif; ?></div>
    <div style="text-align:right"><b><?= e(mb_strtoupper($emp['razon_social'])) ?></b><br>RUC: <?= e($emp['ruc']) ?><br><?= e($emp['direccion']) ?></div>
  </div>
  <h2>BOLETA DE PAGO — <?= e(mb_strtoupper(['MENSUAL' => 'Remuneraciones', 'GRATIFICACION' => 'Gratificación', 'CTS' => 'Compensación por tiempo de servicios'][$per['tipo']])) ?></h2>
  <p class="sub">(D.S. N° 001-98-TR) · Periodo: <?= e(MESES[(int)$per['mes']]) ?> <?= $per['anio'] ?></p>
  <table>
    <tr><th width="16%">Documento</th><td width="34%"><?= e(($s['tipo_doc'] ?? '') . ' ' . ($s['nro_doc'] ?? '')) ?></td><th width="16%">Trabajador</th><td width="34%"><b><?= e(mb_strtoupper($s['nombre'] ?? '')) ?></b></td></tr>
    <tr><th>Cargo</th><td><?= e($s['cargo'] ?? '') ?></td><th>Fecha de ingreso</th><td><?= fdate($s['fecha_ingreso'] ?? null) ?></td></tr>
    <tr><th>Régimen pensionario</th><td><?= e($s['pension'] ?? '') ?> <?= e($s['cuspp'] ?? '') ?></td><th>Días laborados</th><td><?= (int)$b['dias_laborados'] ?></td></tr>
    <tr><th>Fecha de pago</th><td><?= $fpago ?></td><th>Cuenta de abono</th><td><?= e(($s['banco'] ?? '') . ' ' . ($s['nro_cuenta'] ?? '')) ?></td></tr>
  </table>
  <table>
    <tr><th width="34%">INGRESOS</th><th width="33%">DESCUENTOS AL TRABAJADOR</th><th width="33%">APORTES DEL EMPLEADOR</th></tr>
    <tr>
      <?php foreach (['ING', 'DESC', 'APORTE'] as $t): ?>
      <td style="height:95px"><?php foreach ($lin[$t] as $l): ?><div class="l"><span><?= e($l['descripcion']) ?></span><span><?= money($l['monto']) ?></span></div><?php endforeach; ?></td>
      <?php endforeach; ?>
    </tr>
    <tr class="tot"><td>Total ingresos: <?= money_cell($b['bruto']) ?></td><td>Total descuentos: <?= money_cell($b['total_descuentos']) ?></td><td>Total aportes: <?= money_cell($b['total_aportes']) ?></td></tr>
  </table>
  <table><tr class="neto"><td style="text-align:right;width:70%">NETO A PAGAR</td><td style="text-align:center"><?= money_cell($b['neto']) ?></td></tr></table>
  <div class="sig">
    <div class="b"><?php if ($firmada): ?><img src="file.php?e=firma" alt="Firma"><?php else: ?><div style="height:70px"></div><?php endif; ?>
      <div class="line"><?= e($emp['rep_nombre'] ?: 'Empleador') ?><br><?= e($emp['rep_cargo']) ?></div></div>
    <div class="b"><div style="height:70px"></div><div class="line"><?= e($s['nombre'] ?? 'Trabajador') ?><br>Recibí conforme · DNI <?= e($s['nro_doc'] ?? '') ?></div></div>
  </div>
  <div class="foot"><span>Cód. verificación: <?= e($b['codigo_verif']) ?></span>
    <span><?= $firmada ? 'Aprobado el ' . date('d/m/Y H:i', strtotime($per['aprobado_en'])) : 'Documento en revisión' ?></span></div>
</div>
<?php endforeach; ?>
</body></html>
