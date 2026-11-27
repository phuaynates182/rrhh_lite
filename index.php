<?php
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/boletas.php';
$u = require_login();

$act = (int)val("SELECT COUNT(*) FROM empleados WHERE estado='ACTIVO'");
$masa = (float)val("SELECT COALESCE(SUM(sueldo_basico),0) FROM empleados WHERE estado='ACTIVO'");
$pend = (int)val("SELECT COUNT(*) FROM periodos WHERE estado='PENDIENTE'");
$ultimo = row("SELECT p.*, (SELECT COALESCE(SUM(neto),0) FROM boletas b WHERE b.periodo_id=p.id) AS tot_neto,
               (SELECT COUNT(*) FROM boletas b WHERE b.periodo_id=p.id) AS n FROM periodos p ORDER BY anio DESC, mes DESC, id DESC LIMIT 1");
$vencen = rows("SELECT c.id, c.fecha_fin, e.id AS eid, CONCAT(e.apellido_paterno,' ',e.apellido_materno,', ',e.nombres) AS nom
                FROM contratos c JOIN empleados e ON e.id=c.empleado_id
                WHERE c.fecha_fin IS NOT NULL AND c.fecha_fin BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 45 DAY) AND e.estado='ACTIVO'
                ORDER BY c.fecha_fin LIMIT 10");
$cumple = rows("SELECT id, CONCAT(apellido_paterno,' ',apellido_materno,', ',nombres) AS nom, fecha_nacimiento FROM empleados
                WHERE estado='ACTIVO' AND fecha_nacimiento IS NOT NULL AND MONTH(fecha_nacimiento)=MONTH(CURDATE()) ORDER BY DAY(fecha_nacimiento) LIMIT 10");
$sinDocs = rows("SELECT e.id, CONCAT(e.apellido_paterno,' ',e.apellido_materno,', ',e.nombres) AS nom FROM empleados e
                 WHERE e.estado='ACTIVO' AND NOT EXISTS (SELECT 1 FROM empleado_documentos d WHERE d.empleado_id=e.id AND d.tipo='CONTRATO_FIRMADO') LIMIT 8");
$cfg = empresa();

page_head('Inicio', 'inicio');
if (empty($cfg['ruc']) && is_admin()): ?>
  <div class="alert warn">Complete los datos de la empresa (RUC, logo, representante) en <a href="empresa.php">Empresa</a>.</div>
<?php endif; ?>
<div class="grid g4">
  <div class="stat"><b><?= $act ?></b><span>Colaboradores activos</span></div>
  <div class="stat"><b>S/ <?= money($masa) ?></b><span>Sueldos básicos mensuales</span></div>
  <div class="stat"><b><?= $pend ?></b><span>Planillas pendientes de aprobación</span></div>
  <div class="stat"><b><?= $ultimo ? 'S/ ' . money($ultimo['tot_neto']) : '—' ?></b><span><?= $ultimo ? e(nombre_periodo($ultimo)) . ' · neto' : 'Sin planillas aún' ?></span></div>
</div>
<div class="grid g2 mt">
  <div class="card"><h2>⏰ Contratos por vencer (45 días)</h2>
    <?php if (!$vencen): ?><p class="muted">Sin vencimientos próximos.</p><?php else: ?>
    <table><tbody><?php foreach ($vencen as $v): ?><tr><td><a href="empleado_ver.php?id=<?= $v['eid'] ?>"><?= e($v['nom']) ?></a></td><td class="num"><span class="badge warn"><?= fdate($v['fecha_fin']) ?></span></td></tr><?php endforeach; ?></tbody></table><?php endif; ?>
    <p class="hint mt">Recuerde: renovar con adenda y registrar los contratos modales ante el MTPE dentro de 15 días naturales.</p></div>
  <div class="card"><h2>📎 Sin contrato firmado adjunto</h2>
    <?php if (!$sinDocs): ?><p class="muted">Todos los legajos tienen contrato firmado.</p><?php else: ?>
    <table><tbody><?php foreach ($sinDocs as $v): ?><tr><td><a href="empleado_ver.php?id=<?= $v['id'] ?>"><?= e($v['nom']) ?></a></td></tr><?php endforeach; ?></tbody></table><?php endif; ?></div>
  <div class="card"><h2>🎂 Cumpleaños del mes</h2>
    <?php if (!$cumple): ?><p class="muted">Sin cumpleaños registrados.</p><?php else: ?>
    <table><tbody><?php foreach ($cumple as $v): ?><tr><td><?= e($v['nom']) ?></td><td class="num"><?= date('d/m', strtotime($v['fecha_nacimiento'])) ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?></div>
  <div class="card"><h2>🗓️ Calendario laboral</h2>
    <ul class="muted" style="margin:0;padding-left:18px">
      <li>Gratificaciones: hasta el 15 de julio y el 15 de diciembre</li>
      <li>CTS: depósito hasta el 15 de mayo y el 15 de noviembre</li>
      <li>Pago de PDT PLAME / AFP net: según cronograma SUNAT</li>
      <li>Utilidades (empresas con más de 20 trabajadores): dentro de 30 días de la declaración anual</li>
    </ul></div>
</div>
<?php page_foot();
