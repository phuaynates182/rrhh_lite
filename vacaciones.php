<?php
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/payroll.php';
require_once __DIR__ . '/includes/empleados.php';
$me = require_login();

if (is_post()) {
    csrf_verify();
    try {
        if (post('accion') === 'registrar') {
            $ini = post('fecha_inicio'); $fin = post('fecha_fin'); $eid = (int)post('emp');
            if (!valid_date($ini) || !valid_date($fin) || $fin < $ini) throw new RuntimeException('Fechas inválidas.');
            $dias = (int)((new DateTime($ini))->diff(new DateTime($fin))->days) + 1;
            $e = row('SELECT * FROM empleados WHERE id = ?', [$eid]);
            if (!$e) throw new RuntimeException('Colaborador no encontrado.');
            $saldo = Payroll::vacacionesDevengadas($e) - (int)val('SELECT COALESCE(SUM(dias),0) FROM vacaciones WHERE empleado_id=? AND anulada=0', [$eid]);
            if ($dias > $saldo + 0.01 && !isset($_POST['forzar'])) throw new RuntimeException("Solicita $dias días y el saldo devengado es " . round($saldo, 1) . '. Marque «Adelanto de vacaciones» para registrar igualmente.');
            q('INSERT INTO vacaciones (empleado_id, fecha_inicio, fecha_fin, dias, observacion, registrado_por) VALUES (?,?,?,?,?,?)', [$eid, $ini, $fin, $dias, post('obs') ?: null, $me['id']]);
            audit('VACACIONES_REGISTRADAS', 'empleados', $eid, "$dias días");
            flash('ok', 'Vacaciones registradas.');
        } elseif (post('accion') === 'anular') {
            q('UPDATE vacaciones SET anulada = 1 WHERE id = ?', [(int)post('id')]);
            audit('VACACIONES_ANULADAS', 'vacaciones', (int)post('id'));
        }
    } catch (RuntimeException $ex) { flash('err', $ex->getMessage()); }
    redirect('vacaciones.php');
}
$emps = rows("SELECT * FROM empleados WHERE estado = 'ACTIVO' ORDER BY apellido_paterno, apellido_materno, nombres");
$tom = array_column(rows('SELECT empleado_id, SUM(dias) AS d FROM vacaciones WHERE anulada = 0 GROUP BY empleado_id'), 'd', 'empleado_id');
$hist = rows("SELECT v.*, CONCAT(e.apellido_paterno,' ',e.apellido_materno,', ',e.nombres) AS nom FROM vacaciones v JOIN empleados e ON e.id = v.empleado_id ORDER BY v.fecha_inicio DESC LIMIT 100");
page_head('Vacaciones', 'vacaciones');
?>
<div class="alert info">Régimen general: 30 días por año de servicio (D.Leg. 713). Pequeña y microempresa: 15 días. El saldo se devenga diariamente desde la fecha de ingreso. Recuerde: el goce debe darse dentro del año siguiente; si no, se genera la triple remuneración vacacional (D.Leg. 713 art. 23).</div>
<div class="card"><h2>Saldos</h2><div class="toolbar"><input type="search" data-filter="#tabVac" placeholder="Buscar…"></div>
<div class="tablewrap"><table id="tabVac"><thead><tr><th>Colaborador</th><th>Ingreso</th><th>Régimen</th><th class="num">Devengados</th><th class="num">Tomados</th><th class="num">Saldo</th></tr></thead><tbody>
<?php foreach ($emps as $e): $dev = Payroll::vacacionesDevengadas($e); $t = (int)($tom[$e['id']] ?? 0); $s = round($dev - $t, 1); ?>
<tr><td><a href="empleado_ver.php?id=<?= $e['id'] ?>"><?= e(nombre_completo($e)) ?></a></td><td><?= fdate($e['fecha_ingreso']) ?></td><td><?= e($e['regimen']) ?></td>
<td class="num"><?= $dev ?></td><td class="num"><?= $t ?></td><td class="num"><span class="badge <?= $s > 30 ? 'bad' : ($s > 0 ? 'ok' : '') ?>"><?= $s ?></span></td></tr><?php endforeach; ?>
</tbody></table></div></div>

<form method="post" class="card"><?= csrf_field() ?><input type="hidden" name="accion" value="registrar"><h2>Registrar goce de vacaciones</h2>
<div class="row"><div><label>Colaborador</label><select name="emp" required><option value="">—</option><?php foreach ($emps as $e): ?><option value="<?= $e['id'] ?>"><?= e(nombre_completo($e)) ?></option><?php endforeach; ?></select></div>
<div><label>Desde</label><input type="date" name="fecha_inicio" required></div><div><label>Hasta</label><input type="date" name="fecha_fin" required></div>
<div><label>Observación</label><input name="obs"></div></div>
<label class="chk"><input type="checkbox" name="forzar"> Adelanto de vacaciones (permitir exceder el saldo)</label><p><button class="btn">Registrar</button></p></form>

<div class="card"><h2>Historial</h2><table><thead><tr><th>Colaborador</th><th>Periodo</th><th class="num">Días</th><th>Obs.</th><th></th></tr></thead><tbody>
<?php foreach ($hist as $v): ?><tr><td><?= e($v['nom']) ?></td><td><?= fdate($v['fecha_inicio']) ?> → <?= fdate($v['fecha_fin']) ?></td><td class="num"><?= $v['dias'] ?></td><td><?= e($v['observacion']) ?></td>
<td><?php if ($v['anulada']): ?><span class="badge bad">Anulada</span><?php else: ?><form method="post"><?= csrf_field() ?><input type="hidden" name="accion" value="anular"><input type="hidden" name="id" value="<?= $v['id'] ?>"><button class="btn sm ghost" data-confirm="¿Anular?">Anular</button></form><?php endif; ?></td></tr><?php endforeach;
if (!$hist): ?><tr><td colspan="5" class="muted">Sin registros.</td></tr><?php endif; ?></tbody></table></div>
<?php page_foot();
