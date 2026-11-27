<?php
require __DIR__ . '/includes/bootstrap.php';
$me = require_admin();

if (is_post()) {
    csrf_verify();
    $a = post('accion');
    try {
        if ($a === 'crear') {
            $email = mb_strtolower(post('email'));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Correo inválido.');
            if (strlen((string)$_POST['password']) < 8) throw new RuntimeException('La contraseña debe tener al menos 8 caracteres.');
            if (val('SELECT COUNT(*) FROM usuarios WHERE email = ?', [$email])) throw new RuntimeException('Ese correo ya existe.');
            $rol = post('rol') === 'ADMIN' ? 'ADMIN' : 'USUARIO';
            q('INSERT INTO usuarios (nombre, email, password_hash, rol, es_gerente) VALUES (?,?,?,?,?)',
                [post('nombre'), $email, password_hash($_POST['password'], PASSWORD_DEFAULT), $rol, isset($_POST['es_gerente']) ? 1 : 0]);
            audit('USUARIO_CREADO', 'usuarios', (int)db()->lastInsertId(), $email);
            flash('ok', 'Usuario creado.');
        } elseif ($a === 'editar') {
            $id = (int)post('id');
            $rol = post('rol') === 'ADMIN' ? 'ADMIN' : 'USUARIO';
            $activo = isset($_POST['activo']) ? 1 : 0;
            if ($id === (int)$me['id'] && (!$activo || $rol !== 'ADMIN')) throw new RuntimeException('No puede desactivarse ni quitarse el rol Administrador a sí mismo.');
            q('UPDATE usuarios SET nombre=?, rol=?, es_gerente=?, activo=?, intentos=0, bloqueado_hasta=NULL WHERE id=?',
                [post('nombre'), $rol, isset($_POST['es_gerente']) ? 1 : 0, $activo, $id]);
            if ((string)($_POST['password'] ?? '') !== '') {
                if (strlen($_POST['password']) < 8) throw new RuntimeException('Contraseña mínima de 8 caracteres.');
                q('UPDATE usuarios SET password_hash=? WHERE id=?', [password_hash($_POST['password'], PASSWORD_DEFAULT), $id]);
            }
            audit('USUARIO_EDITADO', 'usuarios', $id);
            flash('ok', 'Usuario actualizado.');
        }
    } catch (RuntimeException $ex) { flash('err', $ex->getMessage()); }
    redirect('usuarios.php');
}
$us = rows('SELECT * FROM usuarios ORDER BY nombre');
page_head('Usuarios', 'usuarios');
?>
<div class="alert info"><b>Administrador:</b> acceso total (empresa, parámetros, usuarios). <b>Usuario:</b> colaboradores, planillas, contratos y vacaciones.
  <b>Gerente</b> (marca adicional): único perfil que puede <i>aprobar</i> planillas y estampar la firma de gerencia.</div>
<div class="card"><h2>Usuarios del sistema</h2>
<div class="tablewrap"><table><thead><tr><th>Nombre</th><th>Correo</th><th>Rol</th><th>Gerente</th><th>Estado</th><th>Último acceso</th><th></th></tr></thead><tbody>
<?php foreach ($us as $u): ?>
<tr>
<td><input form="f<?= $u['id'] ?>" style="text-align:left" name="nombre" value="<?= e($u['nombre']) ?>" required></td>
<td><?= e($u['email']) ?></td>
<td><select form="f<?= $u['id'] ?>" name="rol"><?= select_opts(['USUARIO' => 'Usuario', 'ADMIN' => 'Administrador'], $u['rol']) ?></select></td>
<td><input form="f<?= $u['id'] ?>" type="checkbox" name="es_gerente" <?= $u['es_gerente'] ? 'checked' : '' ?>></td>
<td><label class="chk"><input form="f<?= $u['id'] ?>" type="checkbox" name="activo" <?= $u['activo'] ? 'checked' : '' ?>> Activo</label></td>
<td class="muted"><?= $u['ultimo_login'] ? date('d/m/Y H:i', strtotime($u['ultimo_login'])) : '—' ?></td>
<td><input form="f<?= $u['id'] ?>" type="password" name="password" placeholder="Nueva contraseña (opcional)" style="text-align:left;min-width:170px"> <button form="f<?= $u['id'] ?>" class="btn sm">Guardar</button>
<form id="f<?= $u['id'] ?>" method="post" style="display:none"><?= csrf_field() ?><input type="hidden" name="accion" value="editar"><input type="hidden" name="id" value="<?= $u['id'] ?>"></form></td></tr>
<?php endforeach; ?></tbody></table></div></div>

<form method="post" class="card"><?= csrf_field() ?><input type="hidden" name="accion" value="crear">
<h2>Nuevo usuario</h2>
<div class="row">
  <div><label>Nombre</label><input name="nombre" required></div>
  <div><label>Correo</label><input type="email" name="email" required></div>
  <div><label>Contraseña (mín. 8)</label><input type="password" name="password" minlength="8" required></div>
  <div><label>Rol</label><select name="rol"><?= select_opts(['USUARIO' => 'Usuario', 'ADMIN' => 'Administrador']) ?></select></div>
</div>
<label class="chk"><input type="checkbox" name="es_gerente"> Es Gerente (puede aprobar planillas y firmar)</label>
<p><button class="btn">Crear usuario</button></p>
</form>
<?php page_foot();
