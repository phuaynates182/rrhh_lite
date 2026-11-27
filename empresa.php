<?php
require __DIR__ . '/includes/bootstrap.php';
require_admin();

if (is_post()) {
    csrf_verify();
    try {
        $ruc = preg_replace('/\D/', '', post('ruc'));
        if ($ruc !== '' && strlen($ruc) !== 11) throw new RuntimeException('El RUC debe tener 11 dígitos.');
        if (post('razon_social') === '') throw new RuntimeException('Ingrese la razón social.');
        $cur = empresa();
        $logo = $cur['logo_path']; $firma = $cur['firma_path'];
        if (!empty($_FILES['logo']['name'])) {
            [$p] = guardar_archivo($_FILES['logo'], 'empresa', ['png', 'jpg', 'jpeg', 'webp'], 2);
            borrar_archivo($logo); $logo = $p;
        }
        if (!empty($_FILES['firma']['name'])) {
            [$p] = guardar_archivo($_FILES['firma'], 'empresa', ['png', 'jpg', 'jpeg', 'webp'], 2);
            borrar_archivo($firma); $firma = $p;
        }
        if (isset($_POST['quitar_logo'])) { borrar_archivo($logo); $logo = null; }
        if (isset($_POST['quitar_firma'])) { borrar_archivo($firma); $firma = null; }
        q('UPDATE empresa SET ruc=?, razon_social=?, nombre_comercial=?, direccion=?, ciudad=?, telefono=?, email=?, rep_nombre=?, rep_dni=?, rep_cargo=?,
           regimen_laboral=?, actividad_riesgo=?, logo_path=?, firma_path=? WHERE id=1',
            [$ruc, post('razon_social'), post('nombre_comercial') ?: null, post('direccion') ?: null, post('ciudad') ?: 'Lima', post('telefono') ?: null,
             post('email') ?: null, post('rep_nombre') ?: null, post('rep_dni') ?: null, post('rep_cargo') ?: 'Gerente General',
             in_array(post('regimen_laboral'), ['GENERAL', 'PEQUENA', 'MICRO'], true) ? post('regimen_laboral') : 'GENERAL',
             isset($_POST['actividad_riesgo']) ? 1 : 0, $logo, $firma]);
        audit('EMPRESA_ACTUALIZADA', 'empresa', 1);
        flash('ok', 'Datos de la empresa guardados.');
    } catch (RuntimeException $ex) {
        flash('err', $ex->getMessage());
    }
    redirect('empresa.php');
}
$c = row('SELECT * FROM empresa WHERE id = 1');
page_head('Configuración de la empresa', 'empresa');
?>
<form method="post" enctype="multipart/form-data" class="card">
<?= csrf_field() ?>
<h2>Datos generales</h2>
<div class="row">
  <div><label>RUC</label><input name="ruc" maxlength="11" value="<?= e($c['ruc']) ?>"></div>
  <div><label>Razón social *</label><input name="razon_social" value="<?= e($c['razon_social']) ?>" required></div>
  <div><label>Nombre comercial</label><input name="nombre_comercial" value="<?= e($c['nombre_comercial']) ?>"></div>
</div>
<div class="row">
  <div><label>Dirección</label><input name="direccion" value="<?= e($c['direccion']) ?>"></div>
  <div><label>Ciudad</label><input name="ciudad" value="<?= e($c['ciudad']) ?>"></div>
  <div><label>Teléfono</label><input name="telefono" value="<?= e($c['telefono']) ?>"></div>
  <div><label>Correo</label><input type="email" name="email" value="<?= e($c['email']) ?>"></div>
</div>
<div class="sec">Representante legal (firma contratos y boletas)</div>
<div class="row">
  <div><label>Nombre</label><input name="rep_nombre" value="<?= e($c['rep_nombre']) ?>"></div>
  <div><label>DNI</label><input name="rep_dni" value="<?= e($c['rep_dni']) ?>"></div>
  <div><label>Cargo</label><input name="rep_cargo" value="<?= e($c['rep_cargo']) ?>"></div>
</div>
<div class="sec">Régimen laboral</div>
<div class="row">
  <div><label>Régimen de la empresa (por defecto)</label>
    <select name="regimen_laboral"><?= select_opts(['GENERAL' => 'Régimen general (D.Leg. 728)', 'PEQUENA' => 'Pequeña empresa (REMYPE)', 'MICRO' => 'Microempresa (REMYPE)'], $c['regimen_laboral']) ?></select></div>
  <div><label>&nbsp;</label><label class="chk"><input type="checkbox" name="actividad_riesgo" <?= $c['actividad_riesgo'] ? 'checked' : '' ?>> Actividad de riesgo (aplica SCTR)</label></div>
</div>
<div class="sec">Imágenes</div>
<div class="row">
  <div><label>Logo (PNG/JPG, máx. 2 MB)</label><input type="file" name="logo" accept="image/*" data-preview="#pl">
    <img id="pl" class="prev mt" style="<?= $c['logo_path'] ? '' : 'display:none' ?>" src="<?= $c['logo_path'] ? 'file.php?e=logo' : '' ?>" alt="">
    <?php if ($c['logo_path']): ?><label class="chk mt"><input type="checkbox" name="quitar_logo"> Quitar logo</label><?php endif; ?></div>
  <div><label>Firma de gerencia (PNG con fondo transparente recomendado)</label><input type="file" name="firma" accept="image/*" data-preview="#pf">
    <img id="pf" class="prev mt" style="<?= $c['firma_path'] ? '' : 'display:none' ?>" src="<?= $c['firma_path'] ? 'file.php?e=firma' : '' ?>" alt="">
    <?php if ($c['firma_path']): ?><label class="chk mt"><input type="checkbox" name="quitar_firma"> Quitar firma</label><?php endif; ?>
    <p class="hint">La firma solo se estampa en boletas cuando el usuario Gerente aprueba la planilla.</p></div>
</div>
<button class="btn">Guardar</button>
</form>
<?php page_foot();
