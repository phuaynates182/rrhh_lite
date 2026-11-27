<?php
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/tabular.php';
require_once __DIR__ . '/includes/empleados.php';
$me = require_login();

const IMP_ALIAS = [
    'nro_doc' => ['dni', 'nro_doc', 'n_doc', 'documento', 'numero_documento', 'nro_documento', 'doc', 'n_documento', 'codigo'],
    'tipo_doc' => ['tipo_doc', 'tipo_documento'],
    'apellido_paterno' => ['apellido_paterno', 'ape_paterno', 'paterno'],
    'apellido_materno' => ['apellido_materno', 'ape_materno', 'materno'],
    'nombres' => ['nombres', 'nombre'],
    'apellidos_nombres' => ['apellidos_y_nombres', 'apellidos_nombres', 'nombre_completo', 'trabajador', 'empleado', 'apellidos_y_nombre'],
    'cargo' => ['cargo', 'cargo_u_ocupacion', 'ocupacion', 'puesto'],
    'area' => ['area', 'departamento'],
    'fecha_ingreso' => ['fecha_ingreso', 'ingreso', 'fecha_de_ingreso'],
    'fecha_nacimiento' => ['fecha_nacimiento', 'nacimiento', 'fecha_de_nacimiento'],
    'sueldo_basico' => ['sueldo_basico', 'basico', 'sueldo', 'remuneracion', 'haber_basico', 'sueldo_fijo', 'sueldo_basico_s'],
    'asignacion_familiar' => ['asignacion_familiar', 'asig_familiar', 'asig_fam'],
    'sistema_pension' => ['sistema_pension', 'pension', 'afp_onp', 'sistema_de_pension', 'regimen_pensionario'],
    'onp_flag' => ['snp_onp', 'onp', 'snp'],
    'afp_name' => ['afp', 'apf'],
    'cuspp' => ['cuspp'],
    'tipo_trabajador' => ['tipo_trabajador', 'tipo_contrato', 'modalidad'],
    'email' => ['email', 'correo', 'e_mail'],
    'telefono' => ['telefono', 'celular'],
    'direccion' => ['direccion', 'domicilio'],
    'banco' => ['banco'], 'nro_cuenta' => ['nro_cuenta', 'cuenta', 'n_cuenta'], 'cci' => ['cci'],
];

function es_si(string $v): bool { return in_array(mb_strtolower(trim($v)), ['si', 'sí', 's', '1', 'x', 'true', 'yes'], true); }
function es_doc(string $v): bool
{
    $v = preg_replace('/\.0$/', '', trim($v));
    return (bool)preg_match('/^\d{8,12}$/', $v) || (bool)preg_match('/^(?=.*\d)[A-Za-z0-9]{6,15}$/', $v);
}

function resolver_pension(string $txt, array $pens): ?int
{
    $t = norm_header($txt);
    if ($t === '' || $t === 'no' || $t === 'ninguno') return null;
    if (strpos($t, 'onp') !== false || strpos($t, 'snp') !== false) return $pens['ONP'] ?? null;
    foreach (['PRIMA', 'HABITAT', 'INTEGRA', 'PROFUTURO'] as $c) if (strpos($t, strtolower($c)) !== false) return $pens[$c] ?? null;
    return null;
}

function partir_nombre(string $full, bool $apellidosPrimero): array
{
    $w = preg_split('/\s+/', trim($full));
    $n = count($w);
    if ($n === 1) return [$w[0], '', ''];
    $na = $n >= 3 ? 2 : 1; // palabras de apellidos
    if ($apellidosPrimero) return [$w[0], $na === 2 ? $w[1] : '', implode(' ', array_slice($w, $na))];
    return [$w[$n - $na], $na === 2 ? $w[$n - 1] : '', implode(' ', array_slice($w, 0, $n - $na))];
}

function analizar_archivo(array $tabla, array $opt): array
{
    // 1) localizar fila de encabezados
    $hr = null;
    foreach (array_slice($tabla, 0, 40, true) as $i => $r) {
        $hits = 0;
        foreach ($r as $c) { $nh = norm_header($c); foreach (IMP_ALIAS as $al) if (in_array($nh, $al, true)) { $hits++; break; } }
        if ($hits >= 3) { $hr = $i; break; }
    }
    if ($hr === null) throw new RuntimeException('No se encontró la fila de encabezados (se requieren al menos 3 columnas reconocibles: DNI, Nombres, Sueldo_Basico…).');
    // 2) mapear columnas usando hasta 3 filas de encabezado (celdas combinadas)
    $ncol = max(array_map('count', array_slice($tabla, $hr, 3)));
    $cands = array_fill(0, $ncol, []);
    for ($k = 0; $k < 3 && isset($tabla[$hr + $k]); $k++) {
        foreach ($tabla[$hr + $k] as $ci => $c) if ($c !== '' && !is_numeric($c)) $cands[$ci][] = norm_header($c);
    }
    $col = [];
    foreach (IMP_ALIAS as $field => $aliases) {
        foreach ($aliases as $al) {
            foreach ($cands as $ci => $list) if (in_array($al, $list, true) && !in_array($ci, $col, true)) { $col[$field] = $ci; break 2; }
        }
    }
    if (!isset($col['nro_doc'])) throw new RuntimeException('Falta la columna DNI / documento.');
    if (!isset($col['apellidos_nombres']) && !(isset($col['nombres']) && isset($col['apellido_paterno']))) throw new RuntimeException('Falta la columna de nombres (Apellidos_y_Nombres, o Apellido_Paterno + Nombres).');
    if (!isset($col['sueldo_basico'])) throw new RuntimeException('Falta la columna Sueldo_Basico.');

    $pens = [];
    foreach (rows('SELECT id, codigo FROM sistemas_pension') as $p) $pens[$p['codigo']] = (int)$p['id'];
    $emp = empresa();
    $out = []; $vistos = [];
    $get = fn($r, $f) => isset($col[$f]) ? trim((string)($r[$col[$f]] ?? '')) : '';

    foreach ($tabla as $i => $r) {
        if ($i <= $hr) continue;
        $doc = preg_replace('/\.0$/', '', $get($r, 'nro_doc'));
        if (!es_doc($doc)) continue; // subencabezados, totales, filas vacías
        $rec = ['nro_doc' => strtoupper($doc)];
        $tipoDoc = strtoupper($get($r, 'tipo_doc'));
        $rec['tipo_doc'] = in_array($tipoDoc, ['DNI', 'CE', 'PTP', 'PASAPORTE'], true) ? $tipoDoc : (preg_match('/^\d{8}$/', $doc) ? 'DNI' : 'CE');
        if (isset($col['apellidos_nombres']) && $get($r, 'apellidos_nombres') !== '' && $get($r, 'nombres') === '') {
            [$ap, $am, $no] = partir_nombre($get($r, 'apellidos_nombres'), $opt['apellidos_primero']);
        } else {
            $ap = $get($r, 'apellido_paterno'); $am = $get($r, 'apellido_materno'); $no = $get($r, 'nombres');
        }
        $rec['apellido_paterno'] = mb_strtoupper($ap); $rec['apellido_materno'] = mb_strtoupper($am); $rec['nombres'] = mb_convert_case($no, MB_CASE_TITLE);
        $rec['cargo'] = $get($r, 'cargo');
        $rec['sueldo_basico'] = round(fnum($get($r, 'sueldo_basico')), 2);
        $fi = parse_fecha($get($r, 'fecha_ingreso'));
        $rec['fecha_ingreso'] = $fi ?: $opt['fecha_ingreso'];
        $fn = parse_fecha($get($r, 'fecha_nacimiento'));
        $rec['fecha_nacimiento'] = $fn ?: null;
        $rec['fecha_cese'] = null;
        $rec['asignacion_familiar'] = es_si($get($r, 'asignacion_familiar')) ? 1 : 0;
        // Pensión: columna directa, o bandera ONP=SI, o nombre de AFP
        $pid = resolver_pension($get($r, 'sistema_pension'), $pens);
        if ($pid === null && es_si($get($r, 'onp_flag'))) $pid = $pens['ONP'] ?? null;
        if ($pid === null) $pid = resolver_pension($get($r, 'afp_name'), $pens);
        $rec['sistema_pension_id'] = $pid;
        $tt = norm_header($get($r, 'tipo_trabajador'));
        $rec['tipo_trabajador'] = strpos($tt, 'plazo') !== false || strpos($tt, 'fijo') !== false || strpos($tt, 'modal') !== false ? 'PLAZO_FIJO'
            : (strpos($tt, 'parcial') !== false ? 'TIEMPO_PARCIAL' : (strpos($tt, 'practic') !== false ? 'PRACTICANTE' : 'INDEFINIDO'));
        foreach (['cuspp', 'email', 'telefono', 'direccion', 'banco', 'nro_cuenta', 'cci', 'area'] as $f) { $v = $get($r, $f); $rec[$f] = $v === '' ? null : $v; }

        $exist = row('SELECT id FROM empleados WHERE nro_doc = ?', [$rec['nro_doc']]);
        $rec['_fila'] = $i + 1;
        $rec['_exist'] = $exist ? (int)$exist['id'] : 0;
        $err = validar_empleado($rec + ['fecha_nacimiento' => $rec['fecha_nacimiento']], $rec['_exist'] ?: null);
        if ($err && $exist) $err = null; // el duplicado contra la BD se resuelve con la opción actualizar/omitir
        if (isset($vistos[$rec['nro_doc']])) $err = 'Documento repetido en el archivo (fila ' . $vistos[$rec['nro_doc']] . ').';
        $vistos[$rec['nro_doc']] = $i + 1;
        if (!$err && $rec['sistema_pension_id'] === null) $rec['_aviso'] = 'Sin sistema de pensiones reconocido';
        $rec['_error'] = $err;
        $out[] = $rec;
    }
    if (!$out) throw new RuntimeException('No se encontraron filas de colaboradores con documento válido.');
    return $out;
}

$tmpDir = ROOT . '/storage/tmp';
$preview = null; $token = '';

if (is_post()) {
    csrf_verify();
    try {
        if (post('accion') === 'analizar') {
            if (empty($_FILES['archivo']['name'])) throw new RuntimeException('Seleccione un archivo .csv, .xlsx o .xlsm');
            $f = $_FILES['archivo'];
            if ($f['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Error al subir el archivo.');
            if ($f['size'] > 10 * 1048576) throw new RuntimeException('El archivo supera 10 MB.');
            $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
            $fd = post('fecha_ingreso');
            $opt = ['apellidos_primero' => post('orden') !== 'nombres', 'fecha_ingreso' => valid_date($fd) ? $fd : date('Y-m-d')];
            $preview = analizar_archivo(leer_tabla($f['tmp_name'], $ext), $opt);
            if (!is_dir($tmpDir)) mkdir($tmpDir, 0755, true);
            $token = bin2hex(random_bytes(12));
            file_put_contents("$tmpDir/imp_$token.json", json_encode(['uid' => $me['id'], 'rows' => $preview, 'actualizar' => isset($_POST['actualizar'])]));
        } elseif (post('accion') === 'confirmar') {
            $tk = preg_replace('/[^a-f0-9]/', '', post('token'));
            $path = "$tmpDir/imp_$tk.json";
            if (!is_file($path)) throw new RuntimeException('La vista previa expiró. Suba el archivo nuevamente.');
            $data = json_decode(file_get_contents($path), true);
            if ((int)$data['uid'] !== (int)$me['id']) throw new RuntimeException('Importación no válida.');
            $ins = $upd = $omit = 0;
            $pdo = db(); $pdo->beginTransaction();
            try {
                foreach ($data['rows'] as $r) {
                    if ($r['_error']) { $omit++; continue; }
                    $ex = (int)$r['_exist'];
                    $campos = $r;
                    foreach (['_fila', '_exist', '_error', '_aviso'] as $k) unset($campos[$k]);
                    if ($ex) {
                        if (!$data['actualizar']) { $omit++; continue; }
                        foreach ($campos as $k => $v) if ($v === null || $v === '') unset($campos[$k]);
                        unset($campos['fecha_ingreso']); // no se pisa la fecha de ingreso existente
                        $set = implode(', ', array_map(fn($c) => "$c = ?", array_keys($campos)));
                        q("UPDATE empleados SET $set WHERE id = ?", array_merge(array_values($campos), [$ex]));
                        $upd++;
                    } else {
                        $campos += ['regimen' => empresa()['regimen_laboral'] ?? 'GENERAL', 'sctr' => (int)(empresa()['actividad_riesgo'] ?? 0), 'creado_por' => $me['id'],
                            'cargo' => $campos['cargo'] ?? '', 'jornada_horas' => 48];
                        $cols = array_keys($campos);
                        q('INSERT INTO empleados (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')', array_values($campos));
                        $nid = (int)$pdo->lastInsertId();
                        q('UPDATE empleados SET codigo = ? WHERE id = ?', [sprintf('E%04d', $nid), $nid]);
                        $ins++;
                    }
                }
                $pdo->commit();
            } catch (Throwable $t) { $pdo->rollBack(); throw $t; }
            @unlink($path);
            audit('IMPORTACION', 'empleados', null, "nuevos=$ins actualizados=$upd omitidos=$omit");
            flash('ok', "Importación completada: $ins nuevos, $upd actualizados, $omit omitidos.");
            redirect('empleados.php');
        }
    } catch (RuntimeException $ex) { flash('err', $ex->getMessage()); }
}

page_head('Importar colaboradores desde Excel / CSV', 'importar');
if ($preview === null): ?>
<div class="grid g2">
<form method="post" enctype="multipart/form-data" class="card"><?= csrf_field() ?><input type="hidden" name="accion" value="analizar">
  <h2>1. Subir archivo</h2>
  <div class="row" style="grid-template-columns:1fr">
    <div><label>Archivo (.csv, .xlsx, .xlsm)</label><input type="file" name="archivo" accept=".csv,.xlsx,.xlsm,.txt" required></div>
    <div><label>Si la columna es «Apellidos y Nombres», el orden es:</label>
      <select name="orden"><option value="apellidos">Apellidos primero (PÉREZ GÓMEZ JUAN)</option><option value="nombres">Nombres primero (JUAN PÉREZ GÓMEZ)</option></select></div>
    <div><label>Fecha de ingreso por defecto (si el archivo no la trae)</label><input type="date" name="fecha_ingreso" value="<?= date('Y-m-d') ?>"></div>
    <label class="chk"><input type="checkbox" name="actualizar" checked> Actualizar datos si el DNI ya existe</label>
  </div>
  <p><button class="btn">Analizar archivo</button></p>
</form>
<div class="card"><h2>Formato aceptado</h2>
  <p>Se reconoce el archivo <b>«Planilla de remuneraciones»</b> (hoja <i>Planilla</i>: CÓDIGO, APELLIDOS Y NOMBRES, CARGO, ASIGNACIÓN FAMILIAR, SUELDO BÁSICO, SNP/ONP, AFP) y también la plantilla propia.</p>
  <p><a class="btn ghost" href="plantilla_csv.php">⬇ Descargar plantilla CSV</a></p>
  <p class="hint">Columnas: DNI, Apellido_Paterno, Apellido_Materno, Nombres, Cargo, Area, Fecha_Ingreso, Sueldo_Basico, Asignacion_Familiar (SI/NO), Sistema_Pension (ONP, PRIMA, HABITAT, INTEGRA, PROFUTURO), CUSPP, Tipo_Trabajador (INDEFINIDO, PLAZO_FIJO, TIEMPO_PARCIAL, PRACTICANTE), Fecha_Nacimiento, Email, Telefono, Direccion, Banco, Nro_Cuenta, CCI.</p>
  <p class="hint">Verá una vista previa con validaciones antes de guardar nada.</p></div>
</div>
<?php else:
    $ok = count(array_filter($preview, fn($r) => !$r['_error'])); ?>
<div class="card"><h2>2. Vista previa · <?= $ok ?> válidos de <?= count($preview) ?></h2>
  <div class="tablewrap" style="max-height:480px"><table><thead><tr><th>Fila</th><th>Documento</th><th>Apellidos</th><th>Nombres</th><th>Cargo</th><th class="num">Sueldo</th><th>AF</th><th>Pensión</th><th>Ingreso</th><th>Resultado</th></tr></thead><tbody>
  <?php $pn = array_column(rows('SELECT id, codigo FROM sistemas_pension'), 'codigo', 'id');
  foreach ($preview as $r): ?>
    <tr><td><?= $r['_fila'] ?></td><td><?= e($r['nro_doc']) ?></td><td><?= e($r['apellido_paterno'] . ' ' . $r['apellido_materno']) ?></td><td><?= e($r['nombres']) ?></td><td><?= e($r['cargo']) ?></td>
    <td class="num"><?= money($r['sueldo_basico']) ?></td><td><?= $r['asignacion_familiar'] ? 'SI' : 'NO' ?></td><td><?= e($pn[$r['sistema_pension_id']] ?? '—') ?></td><td><?= fdate($r['fecha_ingreso']) ?></td>
    <td><?php if ($r['_error']): ?><span class="badge bad"><?= e($r['_error']) ?></span><?php elseif ($r['_exist']): ?><span class="badge info">Existe: <?= !empty($_POST['actualizar']) ? 'se actualiza' : 'se omite' ?></span><?php else: ?><span class="badge ok">Nuevo</span><?php endif; ?>
    <?= !empty($r['_aviso']) ? '<span class="badge warn">' . e($r['_aviso']) . '</span>' : '' ?></td></tr>
  <?php endforeach; ?></tbody></table></div>
  <form method="post" class="actions mt"><?= csrf_field() ?><input type="hidden" name="accion" value="confirmar"><input type="hidden" name="token" value="<?= e($token) ?>">
    <button class="btn ok" <?= $ok ? '' : 'disabled' ?>>Confirmar e importar <?= $ok ?> registro(s)</button><a class="btn ghost" href="empleados_import.php">Cancelar</a></form>
</div>
<?php endif;
page_foot();
