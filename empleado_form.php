<?php
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/empleados.php';
$me = require_login();

$id = (int)get('id');
$e = $id ? row('SELECT * FROM empleados WHERE id = ?', [$id]) : null;
if ($id && !$e) { flash('err', 'Colaborador no encontrado.'); redirect('empleados.php'); }
$emp = empresa();
$d = $e ?? ['tipo_doc' => 'DNI', 'nro_doc' => '', 'apellido_paterno' => '', 'apellido_materno' => '', 'nombres' => '', 'fecha_nacimiento' => '', 'sexo' => '',
    'estado_civil' => '', 'nacionalidad' => 'Peruana', 'direccion' => '', 'distrito' => '', 'telefono' => '', 'email' => '', 'cargo' => '', 'area' => '',
    'tipo_trabajador' => 'INDEFINIDO', 'regimen' => $emp['regimen_laboral'] ?? 'GENERAL', 'jornada_horas' => 48, 'fecha_ingreso' => date('Y-m-d'), 'fecha_cese' => '',
    'estado' => 'ACTIVO', 'sueldo_basico' => '', 'asignacion_familiar' => 0, 'num_hijos' => 0, 'sistema_pension_id' => '', 'cuspp' => '', 'essalud' => 1,
    'sctr' => $emp['actividad_riesgo'] ?? 0, 'quinta_categoria' => 0, 'banco' => '', 'nro_cuenta' => '', 'cci' => '', 'banco_cts' => '', 'cuenta_cts' => ''];

if (is_post()) {
    csrf_verify();
    $n = fn($k) => post($k) === '' ? null : post($k);
    $nd = [
        'tipo_doc' => in_array(post('tipo_doc'), ['DNI', 'CE', 'PTP', 'PASAPORTE'], true) ? post('tipo_doc') : 'DNI',
        'nro_doc' => strtoupper(post('nro_doc')), 'apellido_paterno' => mb_strtoupper(post('apellido_paterno')),
        'apellido_materno' => mb_strtoupper(post('apellido_materno')), 'nombres' => mb_convert_case(post('nombres'), MB_CASE_TITLE),
        'fecha_nacimiento' => $n('fecha_nacimiento'), 'sexo' => in_array(post('sexo'), ['M', 'F'], true) ? post('sexo') : null,
        'estado_civil' => in_array(post('estado_civil'), ['SOLTERO', 'CASADO', 'CONVIVIENTE', 'DIVORCIADO', 'VIUDO'], true) ? post('estado_civil') : null,
        'nacionalidad' => post('nacionalidad') ?: 'Peruana', 'direccion' => $n('direccion'), 'distrito' => $n('distrito'), 'telefono' => $n('telefono'),
        'email' => $n('email'), 'cargo' => post('cargo'), 'area' => $n('area'),
        'tipo_trabajador' => in_array(post('tipo_trabajador'), ['INDEFINIDO', 'PLAZO_FIJO', 'TIEMPO_PARCIAL', 'PRACTICANTE'], true) ? post('tipo_trabajador') : 'INDEFINIDO',
        'regimen' => in_array(post('regimen'), ['GENERAL', 'PEQUENA', 'MICRO'], true) ? post('regimen') : 'GENERAL',
        'jornada_horas' => max(1, min(48, (int)post('jornada_horas', 48))), 'fecha_ingreso' => post('fecha_ingreso'), 'fecha_cese' => $n('fecha_cese'),
        'estado' => in_array(post('estado'), ['ACTIVO', 'CESADO', 'SUSPENDIDO'], true) ? post('estado') : 'ACTIVO',
        'sueldo_basico' => round(fnum(post('sueldo_basico')), 2), 'asignacion_familiar' => isset($_POST['asignacion_familiar']) ? 1 : 0,
        'num_hijos' => (int)post('num_hijos'), 'sistema_pension_id' => $n('sistema_pension_id') ? (int)post('sistema_pension_id') : null,
        'cuspp' => $n('cuspp'), 'essalud' => isset($_POST['essalud']) ? 1 : 0, 'sctr' => isset($_POST['sctr']) ? 1 : 0,
        'quinta_categoria' => isset($_POST['quinta_categoria']) ? 1 : 0, 'banco' => $n('banco'), 'nro_cuenta' => $n('nro_cuenta'), 'cci' => $n('cci'),
        'banco_cts' => $n('banco_cts'), 'cuenta_cts' => $n('cuenta_cts'),
    ];
    $err = validar_empleado($nd, $id ?: null);
    if ($err) {
        flash('err', $err);
        $d = array_merge($d, $nd);
    } else {
        $cols = array_keys($nd);
        if ($id) {
            q('UPDATE empleados SET ' . implode(', ', array_map(fn($c) => "$c = ?", $cols)) . ' WHERE id = ?', array_merge(array_values($nd), [$id]));
            audit('EMPLEADO_EDITADO', 'empleados', $id, nombre_completo($nd));
        } else {
            q('INSERT INTO empleados (' . implode(', ', $cols) . ', creado_por) VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ', ?)',
                array_merge(array_values($nd), [$me['id']]));
            $id = (int)db()->lastInsertId();
            q('UPDATE empleados SET codigo = ? WHERE id = ?', [sprintf('E%04d', $id), $id]);
            audit('EMPLEADO_CREADO', 'empleados', $id, nombre_completo($nd));
        }
        foreach (['foto' => 'FOTO', 'cv' => 'CV', 'recibo' => 'RECIBO_DOMICILIO', 'contrato' => 'CONTRATO_FIRMADO', 'dni' => 'DNI'] as $f => $tipo) {
            if (!empty($_FILES[$f]['name'])) {
                try { subir_doc_empleado($id, $tipo, $_FILES[$f]); }
                catch (RuntimeException $ex) { flash('warn', DOC_TIPOS[$tipo] . ': ' . $ex->getMessage()); }
            }
        }
        flash('ok', 'Colaborador guardado.');
        redirect('empleado_ver.php?id=' . $id);
    }
}
$pens = rows('SELECT id, nombre FROM sistemas_pension WHERE activo = 1 ORDER BY tipo, nombre');
$pensOpts = ['' => '— Sin régimen —'] + array_column($pens, 'nombre', 'id');
page_head($id ? 'Editar colaborador' : 'Nuevo colaborador', 'empleados');
?>
<form method="post" enctype="multipart/form-data" class="card">
<?= csrf_field() ?>
<div class="sec" style="border:0;margin-top:0">Datos personales</div>
<div class="row">
  <div><label>Tipo de documento</label><select name="tipo_doc"><?= select_opts(['DNI' => 'DNI', 'CE' => 'Carné de extranjería', 'PTP' => 'PTP', 'PASAPORTE' => 'Pasaporte'], $d['tipo_doc']) ?></select></div>
  <div><label>N° de documento *</label><input name="nro_doc" value="<?= e($d['nro_doc']) ?>" required></div>
  <div><label>Apellido paterno *</label><input name="apellido_paterno" value="<?= e($d['apellido_paterno']) ?>" required></div>
  <div><label>Apellido materno</label><input name="apellido_materno" value="<?= e($d['apellido_materno']) ?>"></div>
  <div><label>Nombres *</label><input name="nombres" value="<?= e($d['nombres']) ?>" required></div>
</div>
<div class="row">
  <div><label>Fecha de nacimiento</label><input type="date" name="fecha_nacimiento" value="<?= e($d['fecha_nacimiento']) ?>"></div>
  <div><label>Sexo</label><select name="sexo"><?= select_opts(['' => '—', 'M' => 'Masculino', 'F' => 'Femenino'], $d['sexo']) ?></select></div>
  <div><label>Estado civil</label><select name="estado_civil"><?= select_opts(['' => '—', 'SOLTERO' => 'Soltero(a)', 'CASADO' => 'Casado(a)', 'CONVIVIENTE' => 'Conviviente', 'DIVORCIADO' => 'Divorciado(a)', 'VIUDO' => 'Viudo(a)'], $d['estado_civil']) ?></select></div>
  <div><label>Nacionalidad</label><input name="nacionalidad" value="<?= e($d['nacionalidad']) ?>"></div>
</div>
<div class="row">
  <div style="grid-column:span 2"><label>Domicilio</label><input name="direccion" value="<?= e($d['direccion']) ?>"></div>
  <div><label>Distrito</label><input name="distrito" value="<?= e($d['distrito']) ?>"></div>
  <div><label>Teléfono</label><input name="telefono" value="<?= e($d['telefono']) ?>"></div>
  <div><label>Correo</label><input type="email" name="email" value="<?= e($d['email']) ?>"></div>
</div>

<div class="sec">Datos laborales</div>
<div class="row">
  <div><label>Cargo *</label><input name="cargo" value="<?= e($d['cargo']) ?>" required></div>
  <div><label>Área</label><input name="area" value="<?= e($d['area']) ?>"></div>
  <div><label>Tipo de trabajador (define el contrato)</label><select name="tipo_trabajador"><?= select_opts(['INDEFINIDO' => 'Plazo indeterminado', 'PLAZO_FIJO' => 'Plazo fijo (modal)', 'TIEMPO_PARCIAL' => 'Tiempo parcial', 'PRACTICANTE' => 'Practicante (Ley 28518)'], $d['tipo_trabajador']) ?></select></div>
  <div><label>Régimen laboral</label><select name="regimen"><?= select_opts(['GENERAL' => 'General', 'PEQUENA' => 'Pequeña empresa', 'MICRO' => 'Microempresa'], $d['regimen']) ?></select></div>
  <div><label>Jornada (horas/semana)</label><input type="number" name="jornada_horas" min="1" max="48" value="<?= e($d['jornada_horas']) ?>"></div>
</div>
<div class="row">
  <div><label>Fecha de ingreso *</label><input type="date" name="fecha_ingreso" value="<?= e($d['fecha_ingreso']) ?>" required></div>
  <div><label>Fecha de cese</label><input type="date" name="fecha_cese" value="<?= e($d['fecha_cese']) ?>"></div>
  <div><label>Estado</label><select name="estado"><?= select_opts(['ACTIVO' => 'Activo', 'SUSPENDIDO' => 'Suspendido', 'CESADO' => 'Cesado'], $d['estado']) ?></select></div>
</div>

<div class="sec">Remuneración fija y seguridad social</div>
<div class="row">
  <div><label>Sueldo básico mensual (S/) *</label><input type="number" step="0.01" min="0" name="sueldo_basico" value="<?= e($d['sueldo_basico']) ?>" required></div>
  <div><label>Sistema de pensiones</label><select name="sistema_pension_id"><?= select_opts($pensOpts, $d['sistema_pension_id']) ?></select></div>
  <div><label>CUSPP (si es AFP)</label><input name="cuspp" value="<?= e($d['cuspp']) ?>"></div>
  <div><label>N° de hijos</label><input type="number" name="num_hijos" min="0" value="<?= e($d['num_hijos']) ?>"></div>
</div>
<div class="actions" style="gap:22px;margin-bottom:12px">
  <label class="chk"><input type="checkbox" name="asignacion_familiar" <?= $d['asignacion_familiar'] ? 'checked' : '' ?>> Asignación familiar</label>
  <label class="chk"><input type="checkbox" name="essalud" <?= $d['essalud'] ? 'checked' : '' ?>> Afiliado a EsSalud</label>
  <label class="chk"><input type="checkbox" name="sctr" <?= $d['sctr'] ? 'checked' : '' ?>> SCTR</label>
  <label class="chk"><input type="checkbox" name="quinta_categoria" <?= $d['quinta_categoria'] ? 'checked' : '' ?>> Retener 5ta categoría</label>
</div>
<div class="row">
  <div><label>Banco (sueldo)</label><input name="banco" value="<?= e($d['banco']) ?>"></div>
  <div><label>N° de cuenta</label><input name="nro_cuenta" value="<?= e($d['nro_cuenta']) ?>"></div>
  <div><label>CCI</label><input name="cci" value="<?= e($d['cci']) ?>"></div>
  <div><label>Banco CTS</label><input name="banco_cts" value="<?= e($d['banco_cts']) ?>"></div>
  <div><label>Cuenta CTS</label><input name="cuenta_cts" value="<?= e($d['cuenta_cts']) ?>"></div>
</div>

<div class="sec">Legajo digital (PDF, JPG, PNG; máx. 8 MB c/u)</div>
<div class="row">
  <div><label>📷 Fotografía</label><input type="file" name="foto" accept="image/*"></div>
  <div><label>📄 Currículum Vitae</label><input type="file" name="cv" accept=".pdf,.doc,.docx"></div>
  <div><label>🏠 Recibo de domicilio</label><input type="file" name="recibo" accept=".pdf,image/*"></div>
  <div><label>✍️ Contrato firmado</label><input type="file" name="contrato" accept=".pdf,image/*"></div>
  <div><label>🪪 Copia de DNI/CE</label><input type="file" name="dni" accept=".pdf,image/*"></div>
</div>
<?php if ($id): ?><p class="hint">Al subir foto, CV o recibo se reemplaza el archivo anterior. Los contratos firmados se acumulan.</p><?php endif; ?>
<div class="actions"><button class="btn">Guardar colaborador</button><a class="btn ghost" href="<?= $id ? 'empleado_ver.php?id=' . $id : 'empleados.php' ?>">Cancelar</a></div>
</form>
<?php page_foot();
