<?php
require __DIR__ . '/includes/bootstrap.php';
require_admin();

if (is_post()) {
    csrf_verify();
    $a = post('accion');
    try {
        if ($a === 'params') {
            foreach ((array)($_POST['p'] ?? []) as $k => $v) {
                if (!is_numeric($v) || (float)$v < 0) throw new RuntimeException("Valor inválido para $k.");
                q('UPDATE parametros SET valor = ? WHERE clave = ?', [(float)$v, $k]);
            }
            audit('PARAMETROS_ACTUALIZADOS', 'parametros');
            flash('ok', 'Parámetros legales actualizados. Las boletas en borrador deben recalcularse.');
        } elseif ($a === 'pension') {
            foreach ((array)($_POST['s'] ?? []) as $id => $s) {
                q('UPDATE sistemas_pension SET aporte_pct=?, comision_pct=?, prima_pct=?, activo=? WHERE id=?',
                    [fnum($s['aporte']), fnum($s['comision']), fnum($s['prima']), isset($s['activo']) ? 1 : 0, (int)$id]);
            }
            audit('PENSIONES_ACTUALIZADAS', 'sistemas_pension');
            flash('ok', 'Tasas de pensiones actualizadas.');
        } elseif ($a === 'concepto') {
            $cod = strtoupper(preg_replace('/[^A-Za-z0-9_]/', '', post('codigo')));
            if ($cod === '' || post('nombre') === '') throw new RuntimeException('Código y nombre son obligatorios.');
            if (val('SELECT COUNT(*) FROM conceptos WHERE codigo = ?', [$cod])) throw new RuntimeException('El código ya existe.');
            q('INSERT INTO conceptos (codigo, nombre, tipo, afecto, orden) VALUES (?,?,?,?,?)',
                [$cod, post('nombre'), post('tipo') === 'DESC' ? 'DESC' : 'ING', post('tipo') === 'DESC' ? 0 : (isset($_POST['afecto']) ? 1 : 0), 50]);
            flash('ok', 'Concepto agregado.');
        } elseif ($a === 'concepto_toggle') {
            q('UPDATE conceptos SET activo = 1 - activo WHERE id = ?', [(int)post('id')]);
        }
    } catch (RuntimeException $ex) { flash('err', $ex->getMessage()); }
    redirect('parametros.php');
}
$ps = rows('SELECT * FROM parametros ORDER BY clave');
$sp = rows('SELECT * FROM sistemas_pension ORDER BY tipo, nombre');
$cs = rows('SELECT * FROM conceptos ORDER BY orden, id');
page_head('Parámetros legales', 'parametros');
?>
<div class="alert warn"><b>Responsabilidad del contador/abogado:</b> estos valores cambian por norma (RMV, UIT, comisiones y primas AFP, tope RMA, tasa SCTR según su póliza).
  Verifique siempre en MTPE, SUNAT y SBS antes de generar planillas. El Excel adjunto usa RMV S/ 1,130 y comisiones AFP a abril 2025.</div>
<form method="post" class="card"><?= csrf_field() ?><input type="hidden" name="accion" value="params">
<h2>Parámetros generales</h2>
<div class="tablewrap"><table><thead><tr><th>Parámetro</th><th>Descripción</th><th>Base legal</th><th class="num">Valor</th></tr></thead><tbody>
<?php foreach ($ps as $p): ?><tr><td><code><?= e($p['clave']) ?></code></td><td style="white-space:normal"><?= e($p['descripcion']) ?></td><td class="muted"><?= e($p['base_legal']) ?></td>
<td><input name="p[<?= e($p['clave']) ?>]" type="number" step="0.0001" value="<?= e($p['valor'] + 0) ?>"></td></tr><?php endforeach; ?>
</tbody></table></div><p><button class="btn">Guardar parámetros</button></p></form>

<form method="post" class="card"><?= csrf_field() ?><input type="hidden" name="accion" value="pension">
<h2>Sistemas de pensiones (porcentajes)</h2>
<div class="tablewrap"><table><thead><tr><th>Sistema</th><th class="num">Aporte %</th><th class="num">Comisión flujo %</th><th class="num">Prima seguro %</th><th>Activo</th></tr></thead><tbody>
<?php foreach ($sp as $s): $i = $s['id']; ?><tr><td><?= e($s['nombre']) ?></td>
<td><input name="s[<?= $i ?>][aporte]" type="number" step="0.001" value="<?= e($s['aporte_pct'] + 0) ?>"></td>
<td><input name="s[<?= $i ?>][comision]" type="number" step="0.001" value="<?= e($s['comision_pct'] + 0) ?>" <?= $s['tipo'] === 'ONP' ? 'readonly' : '' ?>></td>
<td><input name="s[<?= $i ?>][prima]" type="number" step="0.001" value="<?= e($s['prima_pct'] + 0) ?>" <?= $s['tipo'] === 'ONP' ? 'readonly' : '' ?>></td>
<td><input type="checkbox" name="s[<?= $i ?>][activo]" <?= $s['activo'] ? 'checked' : '' ?>></td></tr><?php endforeach; ?>
</tbody></table></div><p><button class="btn">Guardar tasas</button></p></form>

<div class="card"><h2>Conceptos variables de planilla (bonos, comisiones, incentivos…)</h2>
<p class="hint">Son opcionales por boleta. «Afecto» = remunerativo (computa para AFP/ONP, EsSalud, 5ta categoría, gratificación y CTS). «No afecto» = libre disposición / descuento.</p>
<div class="tablewrap"><table><thead><tr><th>Código</th><th>Nombre</th><th>Tipo</th><th>Afecto</th><th>Estado</th><th></th></tr></thead><tbody>
<?php foreach ($cs as $c): ?><tr><td><code><?= e($c['codigo']) ?></code></td><td><?= e($c['nombre']) ?></td><td><?= $c['tipo'] === 'ING' ? 'Ingreso' : 'Descuento' ?></td>
<td><?= $c['afecto'] ? 'Sí' : 'No' ?></td><td><span class="badge <?= $c['activo'] ? 'ok' : 'bad' ?>"><?= $c['activo'] ? 'Activo' : 'Inactivo' ?></span></td>
<td><form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="accion" value="concepto_toggle"><input type="hidden" name="id" value="<?= $c['id'] ?>"><button class="btn sm ghost"><?= $c['activo'] ? 'Desactivar' : 'Activar' ?></button></form></td></tr><?php endforeach; ?>
</tbody></table></div>
<form method="post" class="row mt"><?= csrf_field() ?><input type="hidden" name="accion" value="concepto">
<div><label>Código</label><input name="codigo" placeholder="MOVILIDAD" required></div>
<div><label>Nombre</label><input name="nombre" required></div>
<div><label>Tipo</label><select name="tipo"><option value="ING">Ingreso</option><option value="DESC">Descuento</option></select></div>
<div><label>&nbsp;</label><label class="chk"><input type="checkbox" name="afecto" checked> Ingreso afecto (remunerativo)</label></div>
<div><label>&nbsp;</label><button class="btn">Agregar</button></div></form></div>
<?php page_foot();
