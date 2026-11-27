<?php
require __DIR__ . '/includes/bootstrap.php';

if (user()) redirect('index.php');
$err = '';
if (is_post()) {
    csrf_verify();
    $email = mb_strtolower(post('email'));
    $u = row('SELECT * FROM usuarios WHERE email = ?', [$email]);
    if ($u && $u['bloqueado_hasta'] && strtotime($u['bloqueado_hasta']) > time()) {
        $err = 'Cuenta bloqueada temporalmente por intentos fallidos. Intente más tarde.';
    } elseif ($u && $u['activo'] && password_verify((string)($_POST['password'] ?? ''), $u['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['uid'] = (int)$u['id'];
        q('UPDATE usuarios SET intentos = 0, bloqueado_hasta = NULL, ultimo_login = NOW() WHERE id = ?', [$u['id']]);
        $_SESSION['user'] = $u;
        audit('LOGIN', 'usuarios', (int)$u['id']);
        redirect('index.php');
    } else {
        if ($u) {
            $n = (int)$u['intentos'] + 1;
            q('UPDATE usuarios SET intentos = ?, bloqueado_hasta = ? WHERE id = ?', [$n >= 5 ? 0 : $n, $n >= 5 ? date('Y-m-d H:i:s', time() + 900) : null, $u['id']]);
        }
        usleep(400000);
        $err = 'Correo o contraseña incorrectos.';
    }
}
$emp = empresa();
bare_head('Iniciar sesión · RRHH');
?>
<div class="authbox">
  <?php if (!empty($emp['logo_path'])): ?><img class="lg" src="file.php?e=logo" alt="logo"><?php endif; ?>
  <h1><?= e($emp['razon_social'] ?? 'RRHH') ?></h1>
  <p class="muted">Ingrese a su cuenta</p>
  <?php if ($err): ?><div class="alert err"><?= e($err) ?></div><?php endif; ?>
  <form method="post" autocomplete="on">
    <?= csrf_field() ?>
    <div class="field"><label>Correo</label><input type="email" name="email" required autofocus></div>
    <div class="field"><label>Contraseña</label><input type="password" name="password" required></div>
    <button class="btn" style="width:100%">Ingresar</button>
  </form>
</div>
<?php bare_foot();
