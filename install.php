<?php
declare(strict_types=1);
/**
 * Instalador: crea config.php, tablas, datos iniciales y el primer administrador.
 * ELIMINE este archivo del servidor al terminar.
 */
$root = __DIR__;
require_once $root . '/includes/plantillas_default.php';
function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$msg = ''; $err = '';
$installed = is_file($root . '/config.php');

if (!$installed && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $host = trim($_POST['host'] ?? 'localhost'); $name = trim($_POST['name'] ?? '');
    $usr = trim($_POST['usr'] ?? ''); $pass = (string)($_POST['pass'] ?? '');
    $an = trim($_POST['an'] ?? ''); $ae = trim($_POST['ae'] ?? ''); $ap = (string)($_POST['ap'] ?? '');
    try {
        if (!filter_var($ae, FILTER_VALIDATE_EMAIL)) throw new Exception('Correo del administrador inválido.');
        if (strlen($ap) < 8) throw new Exception('La contraseña debe tener al menos 8 caracteres.');
        if ($an === '') throw new Exception('Ingrese el nombre del administrador.');
        $pdo = new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4", $usr, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $sql = file_get_contents($root . '/database/schema.sql');
        $sql = str_replace("\r\n", "\n", $sql);
        $sql = preg_replace('/^\s*--.*$/m', '', $sql);
        foreach (array_filter(array_map('trim', explode(";\n", $sql))) as $st) {
            if ($st !== '') $pdo->exec($st);
        }
        $pdo->prepare('INSERT INTO usuarios (nombre, email, password_hash, rol, es_gerente) VALUES (?,?,?,?,1)')
            ->execute([$an, $ae, password_hash($ap, PASSWORD_DEFAULT), 'ADMIN']);
        $ins = $pdo->prepare('INSERT INTO contrato_plantillas (nombre, tipo_trabajador, base_legal, cuerpo) VALUES (?,?,?,?)');
        foreach (plantillas_default() as $p) $ins->execute($p);
        foreach (['storage/uploads', 'storage/empresa', 'storage/tmp'] as $d) @mkdir($root . '/' . $d, 0755, true);
        $cfg = "<?php\ndefine('DB_HOST', " . var_export($host, true) . ");\ndefine('DB_NAME', " . var_export($name, true)
            . ");\ndefine('DB_USER', " . var_export($usr, true) . ");\ndefine('DB_PASS', " . var_export($pass, true)
            . ");\ndefine('APP_DEBUG', false);\n";
        if (file_put_contents($root . '/config.php', $cfg) === false) throw new Exception('No se pudo escribir config.php (permisos).');
        $msg = 'Instalación completada. ELIMINE install.php y luego ingrese al sistema.';
        $installed = true;
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}
?><!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Instalación RRHH</title><link rel="stylesheet" href="assets/css/blur.css"></head><body class="auth">
<div class="blob b1"></div><div class="blob b2"></div><div class="blob b3"></div>
<div class="authbox" style="max-width:480px">
<h1>Instalación · RRHH</h1>
<?php if ($msg): ?><div class="alert ok"><?= h($msg) ?></div><p><a class="btn" href="login.php">Ir al inicio de sesión</a></p>
<?php elseif ($installed): ?><div class="alert warn">El sistema ya está instalado (existe config.php). Elimine install.php.</div>
<?php else: ?>
<?php if ($err): ?><div class="alert err"><?= h($err) ?></div><?php endif; ?>
<p class="hint">Cree antes la base de datos y el usuario MySQL en cPanel › «Bases de datos MySQL».</p>
<form method="post">
<div class="sec">Base de datos</div>
<div class="field"><label>Servidor</label><input name="host" value="<?= h($_POST['host'] ?? 'localhost') ?>" required></div>
<div class="field"><label>Nombre de la base</label><input name="name" value="<?= h($_POST['name'] ?? '') ?>" required></div>
<div class="field"><label>Usuario</label><input name="usr" value="<?= h($_POST['usr'] ?? '') ?>" required></div>
<div class="field"><label>Contraseña</label><input type="password" name="pass"></div>
<div class="sec">Primer administrador (también será Gerente)</div>
<div class="field"><label>Nombre completo</label><input name="an" value="<?= h($_POST['an'] ?? '') ?>" required></div>
<div class="field"><label>Correo</label><input type="email" name="ae" value="<?= h($_POST['ae'] ?? '') ?>" required></div>
<div class="field"><label>Contraseña (mín. 8)</label><input type="password" name="ap" required minlength="8"></div>
<button class="btn" style="width:100%">Instalar</button>
</form>
<?php endif; ?>
</div></body></html>
