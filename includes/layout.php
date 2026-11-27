<?php
declare(strict_types=1);

function page_head(string $title, string $active = ''): void
{
    $u = $_SESSION['user'] ?? user();
    $emp = empresa();
    $menu = [
        ['inicio', 'Inicio', 'index.php', '🏠', false],
        ['empleados', 'Colaboradores', 'empleados.php', '👥', false],
        ['planillas', 'Planillas y boletas', 'planillas.php', '🧾', false],
        ['contratos', 'Contratos', 'contratos.php', '📄', false],
        ['vacaciones', 'Vacaciones', 'vacaciones.php', '🏖️', false],
        ['importar', 'Importar Excel/CSV', 'empleados_import.php', '📥', false],
        ['empresa', 'Empresa', 'empresa.php', '🏢', true],
        ['parametros', 'Parámetros legales', 'parametros.php', '⚖️', true],
        ['usuarios', 'Usuarios', 'usuarios.php', '🔑', true],
        ['auditoria', 'Auditoría', 'auditoria.php', '🕵️', true],
    ];
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf" content="<?= e(csrf_token()) ?>">
<title><?= e($title) ?> · RRHH</title>
<link rel="stylesheet" href="assets/css/blur.css">
</head>
<body class="app">
<div class="blob b1"></div><div class="blob b2"></div><div class="blob b3"></div>
<button class="burger" id="burger" aria-label="Menú">☰</button>
<aside class="sidebar glass" id="sidebar">
  <div class="brand">
    <?php if (!empty($emp['logo_path'])): ?><img src="file.php?e=logo" alt="logo"><?php else: ?><span class="logo-ph">RH</span><?php endif; ?>
    <div><strong><?= e($emp['nombre_comercial'] ?: ($emp['razon_social'] ?? 'RRHH')) ?></strong><small>Sistema de RRHH</small></div>
  </div>
  <nav>
    <?php foreach ($menu as [$k, $label, $href, $ico, $adm]): if ($adm && ($u['rol'] ?? '') !== 'ADMIN') continue; ?>
      <a href="<?= $href ?>" class="<?= $active === $k ? 'on' : '' ?>"><span><?= $ico ?></span><?= $label ?></a>
    <?php endforeach; ?>
  </nav>
  <div class="who">
    <div><strong><?= e($u['nombre'] ?? '') ?></strong>
      <small><?= ($u['rol'] ?? '') === 'ADMIN' ? 'Administrador' : 'Usuario' ?><?= !empty($u['es_gerente']) ? ' · Gerente' : '' ?></small></div>
    <a class="btn sm ghost" href="logout.php">Salir</a>
  </div>
</aside>
<main class="main">
<header class="top"><h1><?= e($title) ?></h1></header>
<?php foreach ($_SESSION['flash'] ?? [] as [$t, $m]): ?>
  <div class="alert <?= e($t) ?>"><?= e($m) ?></div>
<?php endforeach; unset($_SESSION['flash']); ?>
<?php
}

function page_foot(): void
{
    echo '</main><script src="assets/js/app.js"></script></body></html>';
}

/** Cabecera mínima sin menú (login / instalación) */
function bare_head(string $title): void
{
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>' . e($title) . '</title><link rel="stylesheet" href="assets/css/blur.css"></head><body class="auth">'
        . '<div class="blob b1"></div><div class="blob b2"></div><div class="blob b3"></div>';
}
function bare_foot(): void { echo '<script src="assets/js/app.js"></script></body></html>'; }

function select_opts(array $opts, $sel = null): string
{
    $h = '';
    foreach ($opts as $v => $l) $h .= '<option value="' . e($v) . '"' . ((string)$v === (string)$sel ? ' selected' : '') . '>' . e($l) . '</option>';
    return $h;
}
