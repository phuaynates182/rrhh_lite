<?php
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/empleados.php';
require_once __DIR__ . '/includes/boletas.php';
$me = require_login();

$id = (int)get('id');
$e = row('SELECT e.*, sp.nombre AS pension FROM empleados e LEFT JOIN sistemas_pension sp ON sp.id = e.sistema_pension_id WHERE e.id = ?', [$id]);
if (!$e) { flash('err', 'Colaborador no encontrado.'); redirect('empleados.php'); }

if (is_post()) {
    csrf_verify();
    $a = post('accion');
    try {
        if ($a === 'subir') {
            if (empty($_FILES['archivo']['name'])) throw new RuntimeException('Seleccione un archivo.');
            subir_doc_empleado($id, post('tipo'), $_FILES['archivo']);
            flash('ok', 'Documento subido.');
        } elseif ($a === 'borrar_doc') {
            $d = row('SELECT * FROM empleado_documentos WHERE id = ? AND empleado_id = ?', [(int)post('doc'), $id]);
            if ($d) { borrar_archivo($d['ruta']); q('DELETE FROM empleado_documentos WHERE id = ?', [$d['id']]); audit('DOC_ELIMINADO', 'empleados', $id, $d['nombre_original']); flash('ok', 'Documento eliminado.'); }
        } elseif ($a === 'eliminar' && is_admin()) {
            if (val('SELECT COUNT(*) FROM boletas WHERE empleado_id = ?', [$id])) throw new RuntimeException('Tiene boletas emitidas; márquelo como CESADO en lugar de eliminarlo.');
            foreach (rows('SELECT ruta FROM empleado_documentos WHERE empleado_id = ?', [$id]) as $d) borrar_archivo($d['ruta']);
            q('DELETE FROM empleados WHERE id = ?', [$id]);
            audit('EMPLEADO_ELIMINADO', 'empleados', $id, nombre_completo($e));
            flash('ok', 'Colaborador eliminado.');
            redirect('empleados.php');
        }
    } catch (RuntimeException $ex) { flash('err', $ex->getMessage()); }
    redirect('empleado_ver.php?id=' . $id);
}

$docs = rows('SELECT * FROM empleado_documentos WHERE empleado_id = ? ORDER BY tipo, id DESC', [$id]);
$foto = foto_empleado($id);
$contratos = rows('SELECT c.*, p.nombre AS plantilla FROM contratos c LEFT JOIN contrato_plantillas p ON p.id = c.plantilla_id WHERE c.empleado_id = ? ORDER BY c.id DESC', [$id]);
$boletas = rows("SELECT b.*, p.anio, p.mes, p.tipo, p.estado FROM boletas b JOIN periodos p ON p.id = b.periodo_id WHERE b.empleado_id = ? ORDER BY p.anio DESC, p.mes DESC, p.id DESC LIMIT 24", [$id]);
$vacTom = (int)val('SELECT COALESCE(SUM(dias),0) FROM vacaciones WHERE empleado_id = ? AND anulada = 0', [$id]);
$vacDev = Payroll::vacacionesDevengadas($e);
$tabs = ['dl' => 'Datos', 'doc' => 'Legajo'];

page_head(nombre_completo($e), 'empleados');
?>
<div class="card" style="display:flex;gap:20px;align-items:center;flex-wrap:wrap">
  <?php if ($foto): ?><img class="avatar lg" src="file.php?d=<?= $foto ?>" alt=""><?php else: ?><span class="avatar lg"><?= e(mb_substr($e['nombres'], 0, 1) . mb_substr($e['apellido_paterno'], 0, 1)) ?></span><?php endif; ?>
  <div style="flex:1;min-width:220px">
    <h2 style="margin:0"><?= e(nombre_completo($e)) ?></h2>
    <div class="muted"><?= e($e['cargo']) ?><?= $e['area'] ? ' · ' . e($e['area']) : '' ?> · <?= e($e['tipo_doc']) ?> <?= e($e['nro_doc']) ?></div>
    <div class="mt"><span class="badge <?= $e['estado'] === 'ACTIVO' ? 'ok' : 'bad' ?>"><?= e($e['estado']) ?></span>
      <span class="badge info"><?= e(str_replace('_', ' ', $e['tipo_trabajador'])) ?></span> <span class="badge"><?= e($e['regimen']) ?></span></div>
  </div>
  <div class="actions">
    <a class="btn" href="empleado_form.php?id=<?= $id ?>">✏️ Editar</a>
    <a class="btn ghost" href="contrato_nuevo.php?emp=<?= $id ?>">📄 Generar contrato</a>
    <?php if (is_admin()): ?><form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="accion" value="eliminar"><button class="btn bad" data-confirm="¿Eliminar definitivamente al colaborador y su legajo?">Eliminar</button></form><?php endif; ?>
  </div>
</div>

<div class="grid g2">
  <div class="card"><h2>Datos personales</h2><table><tbody>
    <tr><td class="muted">Nacimiento</td><td><?= fdate($e['fecha_nacimiento']) ?: '—' ?></td></tr>
    <tr><td class="muted">Estado civil</td><td><?= e($e['estado_civil'] ?: '—') ?></td></tr>
    <tr><td class="muted">Nacionalidad</td><td><?= e($e['nacionalidad']) ?></td></tr>
    <tr><td class="muted">Domicilio</td><td style="white-space:normal"><?= e($e['direccion'] ?: '—') ?> <?= e($e['distrito']) ?></td></tr>
    <tr><td class="muted">Teléfono / correo</td><td><?= e($e['telefono']) ?> <?= e($e['email']) ?></td></tr>
  </tbody></table></div>
  <div class="card"><h2>Datos laborales y remuneración</h2><table><tbody>
    <tr><td class="muted">Ingreso</td><td><?= fdate($e['fecha_ingreso']) ?><?= $e['fecha_cese'] ? ' · cese ' . fdate($e['fecha_cese']) : '' ?></td></tr>
    <tr><td class="muted">Sueldo básico</td><td><b>S/ <?= money($e['sueldo_basico']) ?></b></td></tr>
    <tr><td class="muted">Asignación familiar</td><td><?= $e['asignacion_familiar'] ? 'Sí (' . (int)$e['num_hijos'] . ' hijo/s)' : 'No' ?></td></tr>
    <tr><td class="muted">Pensiones</td><td><?= e($e['pension'] ?: '—') ?> <?= e($e['cuspp']) ?></td></tr>
    <tr><td class="muted">EsSalud / SCTR / 5ta</td><td><?= $e['essalud'] ? 'Sí' : 'No' ?> / <?= $e['sctr'] ? 'Sí' : 'No' ?> / <?= $e['quinta_categoria'] ? 'Sí' : 'No' ?></td></tr>
    <tr><td class="muted">Cuenta</td><td><?= e($e['banco']) ?> <?= e($e['nro_cuenta']) ?></td></tr>
    <tr><td class="muted">Vacaciones</td><td><?= $vacDev ?> días devengados · <?= $vacTom ?> tomados · <b>saldo <?= round($vacDev - $vacTom, 1) ?></b></td></tr>
  </tbody></table></div>
</div>

<div class="card"><h2>🗂️ Legajo digital</h2>
  <div class="tablewrap"><table><thead><tr><th>Tipo</th><th>Archivo</th><th>Subido</th><th></th></tr></thead><tbody>
  <?php foreach ($docs as $d): ?>
    <tr><td><span class="badge info"><?= e(DOC_TIPOS[$d['tipo']] ?? $d['tipo']) ?></span></td>
      <td><a href="file.php?d=<?= $d['id'] ?>" target="_blank"><?= e($d['nombre_original']) ?></a> <span class="muted">(<?= round($d['tamano'] / 1024) ?> KB)</span></td>
      <td><?= date('d/m/Y', strtotime($d['subido_en'])) ?></td>
      <td><a class="btn sm ghost" href="file.php?d=<?= $d['id'] ?>&dl=1">Descargar</a>
        <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="accion" value="borrar_doc"><input type="hidden" name="doc" value="<?= $d['id'] ?>"><button class="btn sm bad" data-confirm="¿Eliminar el documento?">✕</button></form></td></tr>
  <?php endforeach; if (!$docs): ?><tr><td colspan="4" class="muted">Sin documentos.</td></tr><?php endif; ?>
  </tbody></table></div>
  <form method="post" enctype="multipart/form-data" class="row mt"><?= csrf_field() ?><input type="hidden" name="accion" value="subir">
    <div><label>Tipo</label><select name="tipo"><?= select_opts(DOC_TIPOS, 'CONTRATO_FIRMADO') ?></select></div>
    <div><label>Archivo</label><input type="file" name="archivo" required></div>
    <div><label>&nbsp;</label><button class="btn">Subir</button></div>
  </form>
</div>

<div class="grid g2">
  <div class="card"><h2>📄 Contratos</h2>
    <table><tbody>
    <?php foreach ($contratos as $c): ?><tr><td><a href="contrato_ver.php?id=<?= $c['id'] ?>"><?= e($c['plantilla'] ?: $c['tipo_trabajador']) ?></a></td>
      <td><?= fdate($c['fecha_inicio']) ?><?= $c['fecha_fin'] ? ' → ' . fdate($c['fecha_fin']) : '' ?></td><td><span class="badge"><?= e($c['estado']) ?></span></td></tr><?php endforeach;
    if (!$contratos): ?><tr><td class="muted">Aún no se generaron contratos.</td></tr><?php endif; ?>
    </tbody></table></div>
  <div class="card"><h2>🧾 Boletas</h2>
    <table><tbody>
    <?php foreach ($boletas as $b): ?><tr><td><a href="boleta.php?b=<?= $b['id'] ?>" target="_blank"><?= e(nombre_periodo($b)) ?></a></td>
      <td class="num">S/ <?= money($b['neto']) ?></td><td><span class="badge <?= $b['estado'] === 'APROBADO' ? 'ok' : 'warn' ?>"><?= e($b['estado']) ?></span></td></tr><?php endforeach;
    if (!$boletas): ?><tr><td class="muted">Sin boletas.</td></tr><?php endif; ?>
    </tbody></table></div>
</div>
<?php page_foot();
