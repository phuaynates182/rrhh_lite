<?php
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/empleados.php';
require_login();

$est = get('estado', 'ACTIVO');
$filtro = in_array($est, ['ACTIVO', 'CESADO', 'SUSPENDIDO'], true);
$list = rows("SELECT e.*, sp.codigo AS pen,
   (SELECT id FROM empleado_documentos d WHERE d.empleado_id=e.id AND d.tipo='FOTO' ORDER BY id DESC LIMIT 1) AS foto,
   (SELECT COUNT(*) FROM empleado_documentos d WHERE d.empleado_id=e.id AND d.tipo='CONTRATO_FIRMADO') AS n_contr
   FROM empleados e LEFT JOIN sistemas_pension sp ON sp.id = e.sistema_pension_id " . ($filtro ? 'WHERE e.estado = ?' : '') . "
   ORDER BY e.apellido_paterno, e.apellido_materno, e.nombres", $filtro ? [$est] : []);

page_head('Colaboradores', 'empleados');
?>
<div class="tabs">
  <?php foreach (['ACTIVO' => 'Activos', 'SUSPENDIDO' => 'Suspendidos', 'CESADO' => 'Cesados', 'TODOS' => 'Todos'] as $k => $l): ?>
    <a class="<?= $est === $k ? 'on' : '' ?>" href="?estado=<?= $k ?>"><?= $l ?></a>
  <?php endforeach; ?>
</div>
<div class="card">
  <div class="toolbar">
    <input type="search" placeholder="Buscar por nombre, DNI, cargo…" data-filter="#tabEmp">
    <div class="actions">
      <a class="btn ghost" href="empleados_import.php">📥 Importar Excel/CSV</a>
      <a class="btn" href="empleado_form.php">➕ Nuevo colaborador</a>
    </div>
  </div>
  <div class="tablewrap"><table id="tabEmp">
    <thead><tr><th></th><th>Documento</th><th>Apellidos y nombres</th><th>Cargo</th><th>Tipo</th><th>Ingreso</th><th class="num">Sueldo básico</th><th>Pensión</th><th>Contrato firmado</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($list as $e): ?>
      <tr>
        <td><?php if ($e['foto']): ?><img class="avatar" src="file.php?d=<?= $e['foto'] ?>" alt=""><?php else: ?><span class="avatar"><?= e(mb_substr($e['nombres'], 0, 1) . mb_substr($e['apellido_paterno'], 0, 1)) ?></span><?php endif; ?></td>
        <td><?= e($e['tipo_doc']) ?> <?= e($e['nro_doc']) ?></td>
        <td><a href="empleado_ver.php?id=<?= $e['id'] ?>"><?= e(nombre_completo($e)) ?></a></td>
        <td><?= e($e['cargo']) ?></td>
        <td><span class="badge"><?= e(str_replace('_', ' ', $e['tipo_trabajador'])) ?></span></td>
        <td><?= fdate($e['fecha_ingreso']) ?></td>
        <td class="num">S/ <?= money($e['sueldo_basico']) ?></td>
        <td><?= e($e['pen'] ?? '—') ?></td>
        <td><?= $e['n_contr'] ? '<span class="badge ok">Sí</span>' : '<span class="badge warn">Falta</span>' ?></td>
        <td><a class="btn sm ghost" href="empleado_form.php?id=<?= $e['id'] ?>">Editar</a></td>
      </tr>
    <?php endforeach; if (!$list): ?><tr><td colspan="10" class="muted">No hay colaboradores en esta vista.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
  <p class="hint mt"><?= count($list) ?> registro(s).</p>
</div>
<?php page_foot();
