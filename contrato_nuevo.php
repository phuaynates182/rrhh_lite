<?php
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/contratos.php';
$me = require_login();

$emps = rows("SELECT * FROM empleados WHERE estado = 'ACTIVO' ORDER BY apellido_paterno, apellido_materno, nombres");
$plantillas = rows('SELECT * FROM contrato_plantillas WHERE activo = 1 ORDER BY tipo_trabajador, id');
$plPorTipo = [];
foreach ($plantillas as $p) $plPorTipo[$p['tipo_trabajador']] ??= $p;
$empSel = (int)get('emp');

if (is_post()) {
    csrf_verify();
    try {
        $modo = post('modo');
        $ids = $modo === 'individual' ? [(int)post('emp_id')] : array_map('intval', (array)($_POST['sel'] ?? []));
        if (!$ids || $ids === [0]) throw new RuntimeException('Seleccione al menos un colaborador.');
        $lote = $modo === 'lote' ? strtoupper(bin2hex(random_bytes(4))) : null;
        $creados = [];
        $pdo = db(); $pdo->beginTransaction();
        try {
            foreach ($ids as $eid) {
                $e = row('SELECT * FROM empleados WHERE id = ?', [$eid]);
                if (!$e) throw new RuntimeException('Colaborador no encontrado.');
                if ($modo === 'individual' && post('plantilla_id') !== '') {
                    $pl = row('SELECT * FROM contrato_plantillas WHERE id = ? AND activo = 1', [(int)post('plantilla_id')]);
                } else {
                    $pl = $plPorTipo[$e['tipo_trabajador']] ?? null;
                }
                if (!$pl) throw new RuntimeException('No hay plantilla activa para ' . nombre_completo($e) . '.');
                if ($modo === 'individual') {
                    $ini = post('fecha_inicio'); $fin = post('fecha_fin'); $sueldo = fnum(post('sueldo')); $cargo = post('cargo');
                } else {
                    $ini = isset($_POST['usar_ingreso']) ? $e['fecha_ingreso'] : post('fecha_inicio');
                    $meses = (int)post('meses');
                    $fin = ($meses > 0 && valid_date($ini)) ? (new DateTime($ini))->modify("+$meses months")->modify('-1 day')->format('Y-m-d') : '';
                    $sueldo = (float)$e['sueldo_basico']; $cargo = $e['cargo'];
                }
                try {
                    $creados[] = crear_contrato($e, $pl, ['fecha_inicio' => $ini, 'fecha_fin' => $fin, 'sueldo' => $sueldo, 'cargo' => $cargo, 'causa' => post('causa'),
                        'con_firma' => isset($_POST['con_firma'])], $lote, (int)$me['id']);
                } catch (RuntimeException $ex) {
                    throw new RuntimeException(nombre_completo($e) . ': ' . $ex->getMessage());
                }
            }
            $pdo->commit();
        } catch (Throwable $t) { $pdo->rollBack(); throw $t; }
        flash('ok', count($creados) . ' contrato(s) generado(s).');
        redirect($lote ? 'contrato_ver.php?lote=' . $lote : 'contrato_ver.php?id=' . $creados[0]);
    } catch (RuntimeException $ex) { flash('err', $ex->getMessage()); }
}

$modo = get('modo', $empSel ? 'individual' : 'lote');
$eS = $empSel ? row('SELECT * FROM empleados WHERE id = ?', [$empSel]) : null;
page_head('Generar contratos', 'contratos');
?>
<div class="tabs"><a class="<?= $modo === 'individual' ? 'on' : '' ?>" href="?modo=individual<?= $empSel ? '&emp=' . $empSel : '' ?>">Individual</a><a class="<?= $modo === 'lote' ? 'on' : '' ?>" href="?modo=lote">Por lote</a>
  <a href="contratos.php">← Contratos emitidos</a></div>
<div class="alert warn">Las plantillas son modelos base. Antes de usarlas haga que un abogado laboralista las revise y ajuste en <a href="contrato_plantillas.php">Plantillas</a>.</div>

<?php if ($modo === 'individual'): ?>
<form method="post" class="card"><?= csrf_field() ?><input type="hidden" name="modo" value="individual">
  <h2>Contrato individual</h2>
  <div class="row">
    <div><label>Colaborador *</label><select name="emp_id" id="selEmp" required><option value="">— Seleccione —</option>
      <?php foreach ($emps as $e): ?><option value="<?= $e['id'] ?>" data-tipo="<?= e($e['tipo_trabajador']) ?>" data-sueldo="<?= e($e['sueldo_basico']) ?>" data-cargo="<?= e($e['cargo']) ?>" data-ingreso="<?= e($e['fecha_ingreso']) ?>" <?= $empSel === (int)$e['id'] ? 'selected' : '' ?>><?= e(nombre_completo($e)) ?></option><?php endforeach; ?></select></div>
    <div><label>Plantilla</label><select name="plantilla_id" id="selPl"><option value="">Automática según tipo de trabajador</option>
      <?php foreach ($plantillas as $p): ?><option value="<?= $p['id'] ?>" data-tipo="<?= e($p['tipo_trabajador']) ?>"><?= e($p['nombre']) ?></option><?php endforeach; ?></select></div>
    <div><label>Cargo</label><input name="cargo" id="inCargo" value="<?= e($eS['cargo'] ?? '') ?>"></div>
    <div><label>Remuneración mensual (S/) *</label><input type="number" step="0.01" name="sueldo" id="inSueldo" value="<?= e($eS['sueldo_basico'] ?? '') ?>" required></div>
  </div>
  <div class="row">
    <div><label>Inicio *</label><input type="date" name="fecha_inicio" id="inIni" value="<?= e($eS['fecha_ingreso'] ?? date('Y-m-d')) ?>" required></div>
    <div><label>Término (plazo fijo / practicante)</label><input type="date" name="fecha_fin"></div>
    <div style="grid-column:span 2"><label>Causa objetiva (contratos modales)</label><textarea name="causa" placeholder="Ej.: Incremento de la actividad comercial por campaña de fin de año que genera una necesidad transitoria de personal…"></textarea></div>
  </div>
  <label class="chk"><input type="checkbox" name="con_firma" checked> Incluir firma de gerencia (imagen configurada en Empresa)</label>
  <p><button class="btn">Generar contrato</button></p>
</form>
<script>
 (function(){var s=document.getElementById('selEmp');if(!s)return;s.addEventListener('change',function(){var o=s.options[s.selectedIndex];if(!o.dataset.sueldo)return;
  document.getElementById('inSueldo').value=o.dataset.sueldo;document.getElementById('inCargo').value=o.dataset.cargo;document.getElementById('inIni').value=o.dataset.ingreso;});})();
</script>
<?php else: ?>
<form method="post" class="card"><?= csrf_field() ?><input type="hidden" name="modo" value="lote">
  <h2>Contratos por lote</h2>
  <p class="hint">Se usa la plantilla de cada colaborador según su <b>tipo de trabajador</b>, con su cargo y sueldo básico registrados.</p>
  <div class="row">
    <div><label>Fecha de inicio común</label><input type="date" name="fecha_inicio" value="<?= date('Y-m-d') ?>"></div>
    <div><label>&nbsp;</label><label class="chk"><input type="checkbox" name="usar_ingreso" checked> Usar la fecha de ingreso de cada colaborador</label></div>
    <div><label>Duración (meses) — plazo fijo / practicantes</label><input type="number" name="meses" min="0" max="60" value="6"></div>
    <div style="grid-column:span 2"><label>Causa objetiva común (contratos modales)</label><textarea name="causa"></textarea></div>
  </div>
  <label class="chk"><input type="checkbox" name="con_firma" checked> Incluir firma de gerencia</label>
  <div class="toolbar mt"><input type="search" placeholder="Filtrar colaboradores…" data-filter="#tabLote"><span class="hint">Marque los colaboradores:</span></div>
  <div class="tablewrap" style="max-height:380px"><table id="tabLote"><thead><tr><th><input type="checkbox" data-checkall=".chk-lote"></th><th>Colaborador</th><th>Cargo</th><th>Tipo</th><th class="num">Sueldo</th><th>Ingreso</th></tr></thead><tbody>
  <?php foreach ($emps as $e): ?><tr><td><input type="checkbox" class="chk-lote" name="sel[]" value="<?= $e['id'] ?>"></td><td><?= e(nombre_completo($e)) ?></td><td><?= e($e['cargo']) ?></td>
  <td><span class="badge"><?= e(TIPOS_TRAB[$e['tipo_trabajador']]) ?></span></td><td class="num"><?= money($e['sueldo_basico']) ?></td><td><?= fdate($e['fecha_ingreso']) ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
  <p><button class="btn">Generar contratos del lote</button></p>
</form>
<?php endif;
page_foot();
