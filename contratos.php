<?php
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/contratos.php';
require_login();

$ls = rows("SELECT c.*, e.nro_doc, CONCAT(e.apellido_paterno,' ',e.apellido_materno,', ',e.nombres) AS nom, p.nombre AS plantilla
            FROM contratos c JOIN empleados e ON e.id = c.empleado_id LEFT JOIN contrato_plantillas p ON p.id = c.plantilla_id ORDER BY c.id DESC LIMIT 500");
$cls = ['BORRADOR' => '', 'EMITIDO' => 'warn', 'FIRMADO' => 'ok'];
page_head('Contratos', 'contratos');
?>
<div class="card"><div class="toolbar">
  <input type="search" placeholder="Buscar…" data-filter="#tabCon">
  <div class="actions"><a class="btn ghost" href="contrato_plantillas.php">📝 Plantillas</a><a class="btn ghost" href="contrato_nuevo.php?modo=lote">📦 Generar por lote</a><a class="btn" href="contrato_nuevo.php?modo=individual">➕ Contrato individual</a></div></div>
<div class="tablewrap"><table id="tabCon"><thead><tr><th>#</th><th>Colaborador</th><th>Documento</th><th>Vigencia</th><th>Lote</th><th class="num">Remuneración</th><th>Estado</th><th></th></tr></thead><tbody>
<?php foreach ($ls as $c): ?><tr><td><?= $c['id'] ?></td><td><a href="empleado_ver.php?id=<?= $c['empleado_id'] ?>"><?= e($c['nom']) ?></a></td><td><?= e($c['plantilla'] ?: $c['tipo_trabajador']) ?></td>
<td><?= fdate($c['fecha_inicio']) ?><?= $c['fecha_fin'] ? ' → ' . fdate($c['fecha_fin']) : ' (indef.)' ?></td>
<td><?= $c['lote'] ? '<a href="contrato_ver.php?lote=' . e($c['lote']) . '">' . e($c['lote']) . '</a>' : '—' ?></td>
<td class="num"><?= money($c['sueldo']) ?></td><td><span class="badge <?= $cls[$c['estado']] ?>"><?= e($c['estado']) ?></span></td>
<td><a class="btn sm ghost" href="contrato_ver.php?id=<?= $c['id'] ?>">Ver / imprimir</a></td></tr><?php endforeach;
if (!$ls): ?><tr><td colspan="8" class="muted">Aún no se han generado contratos.</td></tr><?php endif; ?>
</tbody></table></div></div>
<?php page_foot();
