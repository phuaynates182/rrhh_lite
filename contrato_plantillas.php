<?php
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/contratos.php';
require_admin();

if (is_post()) {
    csrf_verify();
    try {
        $a = post('accion');
        if ($a === 'guardar') {
            $id = (int)post('id');
            if (post('nombre') === '' || post('cuerpo') === '') throw new RuntimeException('Nombre y contenido son obligatorios.');
            $tt = array_key_exists(post('tipo_trabajador'), TIPOS_TRAB) ? post('tipo_trabajador') : 'INDEFINIDO';
            if ($id) {
                q('UPDATE contrato_plantillas SET nombre=?, tipo_trabajador=?, base_legal=?, cuerpo=?, activo=? WHERE id=?',
                    [post('nombre'), $tt, post('base_legal') ?: null, $_POST['cuerpo'], isset($_POST['activo']) ? 1 : 0, $id]);
            } else {
                q('INSERT INTO contrato_plantillas (nombre, tipo_trabajador, base_legal, cuerpo) VALUES (?,?,?,?)', [post('nombre'), $tt, post('base_legal') ?: null, $_POST['cuerpo']]);
            }
            audit('PLANTILLA_GUARDADA', 'contrato_plantillas', $id ?: null, post('nombre'));
            flash('ok', 'Plantilla guardada.');
        }
    } catch (RuntimeException $ex) { flash('err', $ex->getMessage()); }
    redirect('contrato_plantillas.php' . (post('id') ? '?id=' . (int)post('id') : ''));
}
$pls = rows('SELECT * FROM contrato_plantillas ORDER BY tipo_trabajador, id');
$sel = null;
foreach ($pls as $p) if ((int)get('id') === (int)$p['id']) $sel = $p;
$sel ??= ['id' => 0, 'nombre' => '', 'tipo_trabajador' => 'INDEFINIDO', 'base_legal' => '', 'cuerpo' => '', 'activo' => 1];
page_head('Plantillas de contrato', 'contratos');
?>
<div class="grid g2" style="grid-template-columns:minmax(220px,300px) 1fr">
<div class="card"><h2>Plantillas</h2><?php foreach ($pls as $p): ?>
  <p><a href="?id=<?= $p['id'] ?>"><?= e($p['nombre']) ?></a> <?= $p['activo'] ? '' : '<span class="badge bad">inactiva</span>' ?><br><span class="hint"><?= e(TIPOS_TRAB[$p['tipo_trabajador']]) ?></span></p><?php endforeach; ?>
  <a class="btn sm" href="contrato_plantillas.php">➕ Nueva</a>
  <p class="hint mt">Marcadores disponibles:<br>{{EMPRESA}} {{RUC}} {{DOMICILIO_EMPRESA}} {{REP_NOMBRE}} {{REP_DNI}} {{REP_CARGO}} {{TRABAJADOR}} {{TIPO_DOC}} {{NRO_DOC}} {{DOMICILIO}} {{NACIONALIDAD}} {{ESTADO_CIVIL}} {{CARGO}} {{AREA}} {{SUELDO}} {{SUELDO_LETRAS}} {{FECHA_INICIO}} {{FECHA_FIN}} {{JORNADA}} {{CAUSA}} {{CIUDAD}} {{FECHA_HOY}} {{PERIODO_PRUEBA}} {{FIRMA_EMPLEADOR}}</p></div>
<form method="post" class="card"><?= csrf_field() ?><input type="hidden" name="accion" value="guardar"><input type="hidden" name="id" value="<?= $sel['id'] ?>">
  <h2><?= $sel['id'] ? 'Editar plantilla' : 'Nueva plantilla' ?></h2>
  <div class="row"><div><label>Nombre</label><input name="nombre" value="<?= e($sel['nombre']) ?>" required></div>
    <div><label>Tipo de trabajador</label><select name="tipo_trabajador"><?= select_opts(TIPOS_TRAB, $sel['tipo_trabajador']) ?></select></div>
    <div><label>Base legal</label><input name="base_legal" value="<?= e($sel['base_legal']) ?>"></div>
    <div><label>&nbsp;</label><label class="chk"><input type="checkbox" name="activo" <?= $sel['activo'] ? 'checked' : '' ?>> Activa</label></div></div>
  <label>Contenido (HTML)</label><textarea name="cuerpo" style="min-height:420px;font-family:Consolas,monospace;font-size:12px" required><?= e($sel['cuerpo']) ?></textarea>
  <p class="hint">Si hay varias plantillas activas del mismo tipo, el lote usa la primera.</p>
  <button class="btn">Guardar plantilla</button></form></div>
<?php page_foot();
