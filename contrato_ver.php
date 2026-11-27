<?php
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/contratos.php';
$me = require_login();

$lote = get('lote');
$cons = $lote !== '' ? rows('SELECT * FROM contratos WHERE lote = ? ORDER BY id', [$lote]) : rows('SELECT * FROM contratos WHERE id = ?', [(int)get('id')]);
if (!$cons) { flash('err', 'Contrato no encontrado.'); redirect('contratos.php'); }

if (is_post()) {
    csrf_verify();
    $cid = (int)post('cid');
    $c = row('SELECT * FROM contratos WHERE id = ?', [$cid]);
    try {
        if (!$c) throw new RuntimeException('Contrato no encontrado.');
        if (post('accion') === 'firmado') {
            if (empty($_FILES['archivo']['name'])) throw new RuntimeException('Adjunte el contrato firmado (PDF o imagen).');
            subir_doc_empleado((int)$c['empleado_id'], 'CONTRATO_FIRMADO', $_FILES['archivo'], $cid);
            q("UPDATE contratos SET estado = 'FIRMADO' WHERE id = ?", [$cid]);
            flash('ok', 'Contrato firmado adjuntado al legajo del colaborador.');
        } elseif (post('accion') === 'eliminar' && is_admin()) {
            if ($c['estado'] === 'FIRMADO') throw new RuntimeException('No se elimina un contrato firmado.');
            q('DELETE FROM contratos WHERE id = ?', [$cid]);
            audit('CONTRATO_ELIMINADO', 'contratos', $cid);
            flash('ok', 'Contrato eliminado.');
            redirect('contratos.php');
        }
    } catch (RuntimeException $ex) { flash('err', $ex->getMessage()); }
    redirect($_SERVER['REQUEST_URI']);
}

page_head($lote !== '' ? "Lote de contratos $lote (" . count($cons) . ')' : 'Contrato #' . $cons[0]['id'], 'contratos');
?>
<div class="actions noprint" style="margin-bottom:14px">
  <a class="btn ghost" href="contratos.php">← Contratos</a>
  <button class="btn" onclick="window.print()">🖨️ Imprimir / Guardar como PDF<?= $lote !== '' ? ' (todo el lote)' : '' ?></button>
</div>
<?php foreach ($cons as $c): $e = row('SELECT * FROM empleados WHERE id = ?', [$c['empleado_id']]); ?>
<div class="noprint card" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;justify-content:space-between">
  <div><b><?= e(nombre_completo($e)) ?></b> · <span class="badge <?= $c['estado'] === 'FIRMADO' ? 'ok' : 'warn' ?>"><?= e($c['estado']) ?></span></div>
  <form method="post" enctype="multipart/form-data" class="actions"><?= csrf_field() ?><input type="hidden" name="cid" value="<?= $c['id'] ?>"><input type="hidden" name="accion" value="firmado">
    <input type="file" name="archivo" accept=".pdf,image/*" required style="max-width:230px"><button class="btn sm ok">Adjuntar contrato firmado</button></form>
  <?php if (is_admin() && $c['estado'] !== 'FIRMADO'): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="cid" value="<?= $c['id'] ?>"><input type="hidden" name="accion" value="eliminar"><button class="btn sm bad" data-confirm="¿Eliminar este contrato?">Eliminar</button></form><?php endif; ?>
</div>
<div class="paper"><?= $c['cuerpo'] ?></div>
<?php endforeach;
page_foot();
