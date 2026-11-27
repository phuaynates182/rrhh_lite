<?php
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/boletas.php';
$me = require_login();

if (is_post()) {
    csrf_verify();
    try {
        if (post('accion') === 'crear') {
            $anio = (int)post('anio'); $mes = (int)post('mes'); $tipo = post('tipo');
            if ($anio < 2020 || $anio > 2100 || $mes < 1 || $mes > 12) throw new RuntimeException('Periodo inválido.');
            if (!in_array($tipo, ['MENSUAL', 'GRATIFICACION', 'CTS'], true)) throw new RuntimeException('Tipo inválido.');
            if ($tipo === 'GRATIFICACION' && !in_array($mes, [7, 12], true)) throw new RuntimeException('La gratificación corresponde a julio o diciembre.');
            if ($tipo === 'CTS' && !in_array($mes, [5, 11], true)) throw new RuntimeException('La CTS corresponde a mayo o noviembre.');
            if (val('SELECT COUNT(*) FROM periodos WHERE anio=? AND mes=? AND tipo=?', [$anio, $mes, $tipo])) throw new RuntimeException('Ese periodo ya existe.');
            $fp = post('fecha_pago');
            q('INSERT INTO periodos (anio, mes, tipo, descripcion, fecha_pago, creado_por) VALUES (?,?,?,?,?,?)',
                [$anio, $mes, $tipo, post('descripcion') ?: null, valid_date($fp) ? $fp : null, $me['id']]);
            $pid = (int)db()->lastInsertId();
            audit('PERIODO_CREADO', 'periodos', $pid, "$tipo $mes/$anio");
            redirect('planilla.php?id=' . $pid);
        } elseif (post('accion') === 'eliminar') {
            $p = row('SELECT * FROM periodos WHERE id = ?', [(int)post('id')]);
            if (!$p || $p['estado'] !== 'BORRADOR') throw new RuntimeException('Solo se eliminan periodos en borrador.');
            q('DELETE FROM periodos WHERE id = ?', [$p['id']]);
            audit('PERIODO_ELIMINADO', 'periodos', (int)$p['id'], nombre_periodo($p));
            flash('ok', 'Periodo eliminado.');
        }
    } catch (RuntimeException $ex) { flash('err', $ex->getMessage()); }
    redirect('planillas.php');
}

$ps = rows("SELECT p.*, COUNT(b.id) AS n, COALESCE(SUM(b.bruto),0) AS bruto, COALESCE(SUM(b.neto),0) AS neto, MAX(u.nombre) AS aprob
            FROM periodos p LEFT JOIN boletas b ON b.periodo_id = p.id LEFT JOIN usuarios u ON u.id = p.aprobado_por
            GROUP BY p.id ORDER BY p.anio DESC, p.mes DESC, p.id DESC");
$cls = ['BORRADOR' => 'warn', 'PENDIENTE' => 'info', 'APROBADO' => 'ok'];
page_head('Planillas y boletas de pago', 'planillas');
?>
<div class="card"><h2>Periodos</h2>
<div class="tablewrap"><table><thead><tr><th>Periodo</th><th class="num">Boletas</th><th class="num">Total bruto</th><th class="num">Total neto</th><th>Estado</th><th>Aprobado por</th><th></th></tr></thead><tbody>
<?php foreach ($ps as $p): ?>
<tr><td><a href="planilla.php?id=<?= $p['id'] ?>"><b><?= e(nombre_periodo($p)) ?></b></a><?= $p['descripcion'] ? ' <span class="muted">· ' . e($p['descripcion']) . '</span>' : '' ?></td>
<td class="num"><?= $p['n'] ?></td><td class="num">S/ <?= money($p['bruto']) ?></td><td class="num"><b>S/ <?= money($p['neto']) ?></b></td>
<td><span class="badge <?= $cls[$p['estado']] ?>"><?= $p['estado'] ?></span></td>
<td><?= e($p['aprob'] ?? '—') ?> <?= $p['aprobado_en'] ? '<span class="muted">' . date('d/m/Y H:i', strtotime($p['aprobado_en'])) . '</span>' : '' ?></td>
<td class="actions"><a class="btn sm" href="planilla.php?id=<?= $p['id'] ?>">Abrir</a>
<?php if ($p['estado'] === 'BORRADOR'): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id" value="<?= $p['id'] ?>"><button class="btn sm bad" data-confirm="¿Eliminar este periodo y sus boletas?">✕</button></form><?php endif; ?></td></tr>
<?php endforeach; if (!$ps): ?><tr><td colspan="7" class="muted">Aún no hay periodos. Cree el primero abajo.</td></tr><?php endif; ?>
</tbody></table></div></div>

<form method="post" class="card"><?= csrf_field() ?><input type="hidden" name="accion" value="crear">
<h2>Nuevo periodo</h2>
<div class="row">
  <div><label>Tipo</label><select name="tipo" id="tipo"><?= select_opts(['MENSUAL' => 'Remuneraciones mensuales', 'GRATIFICACION' => 'Gratificación (jul / dic)', 'CTS' => 'CTS (may / nov)'], 'MENSUAL') ?></select></div>
  <div><label>Mes</label><select name="mes"><?= select_opts(MESES, (int)date('n')) ?></select></div>
  <div><label>Año</label><input type="number" name="anio" value="<?= date('Y') ?>" min="2020" max="2100"></div>
  <div><label>Fecha de pago</label><input type="date" name="fecha_pago"></div>
  <div><label>Descripción (opcional)</label><input name="descripcion"></div>
</div>
<button class="btn">Crear periodo</button>
</form>
<?php page_foot();
