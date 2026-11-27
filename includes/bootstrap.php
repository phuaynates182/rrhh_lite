<?php
declare(strict_types=1);

define('ROOT', dirname(__DIR__));
if (!is_file(ROOT . '/config.php')) {
    header('Location: install.php');
    exit;
}
require ROOT . '/config.php';

date_default_timezone_set('America/Lima');
mb_internal_encoding('UTF-8');
if (!defined('APP_DEBUG')) define('APP_DEBUG', false);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
error_reporting(E_ALL);

$__https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $__https, 'httponly' => true, 'samesite' => 'Lax']);
session_name('rrhhsid');
session_start();
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

// Expiración por inactividad: 2 horas
if (isset($_SESSION['last']) && time() - $_SESSION['last'] > 7200) {
    $_SESSION = [];
    session_regenerate_id(true);
}
$_SESSION['last'] = time();

set_exception_handler(function (Throwable $e) {
    http_response_code(500);
    error_log('[RRHH] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => APP_DEBUG ? $e->getMessage() : 'Error interno']);
    } else {
        echo '<h2 style="font-family:sans-serif">Error interno</h2><p style="font-family:sans-serif">' . (APP_DEBUG ? htmlspecialchars($e->getMessage()) : 'Intente nuevamente o contacte al administrador.') . '</p>';
    }
    exit;
});

// ---------------------------------------------------------------- BD
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec("SET time_zone = '-05:00'");
    }
    return $pdo;
}
function q(string $sql, array $p = []): PDOStatement { $s = db()->prepare($sql); $s->execute($p); return $s; }
function rows(string $sql, array $p = []): array { return q($sql, $p)->fetchAll(); }
function row(string $sql, array $p = []): ?array { $r = q($sql, $p)->fetch(); return $r === false ? null : $r; }
function val(string $sql, array $p = []) { $r = q($sql, $p)->fetch(PDO::FETCH_NUM); return $r ? $r[0] : null; }

// ---------------------------------------------------------------- Helpers
function e($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function money($v): string { return number_format((float)$v, 2, '.', ','); }
function redirect(string $url): void { header('Location: ' . $url); exit; }
function flash(string $type, string $msg): void { $_SESSION['flash'][] = [$type, $msg]; }
function post(string $k, $d = ''): string { return trim((string)($_POST[$k] ?? $d)); }
function get(string $k, $d = ''): string { return trim((string)($_GET[$k] ?? $d)); }
function fnum($v): float { return (float)str_replace([',', ' '], ['', ''], (string)$v); }
function fdate(?string $d): string { return $d ? date('d/m/Y', strtotime($d)) : ''; }
function valid_date(string $d): bool { $t = DateTime::createFromFormat('Y-m-d', $d); return $t && $t->format('Y-m-d') === $d; }
function ip(): string { return substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45); }

const MESES = [1 => 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Setiembre', 'Octubre', 'Noviembre', 'Diciembre'];

function params(): array
{
    static $p = null;
    if ($p === null) {
        $p = [];
        foreach (rows('SELECT clave, valor FROM parametros') as $r) $p[$r['clave']] = (float)$r['valor'];
    }
    return $p;
}
function empresa(): array { static $e = null; if ($e === null) $e = row('SELECT * FROM empresa WHERE id = 1') ?? []; return $e; }

function audit(string $accion, ?string $entidad = null, ?int $id = null, ?string $detalle = null): void
{
    $u = $_SESSION['user'] ?? null;
    q('INSERT INTO auditoria (usuario_id, usuario_nombre, accion, entidad, entidad_id, detalle, ip) VALUES (?,?,?,?,?,?,?)',
        [$u['id'] ?? null, $u['nombre'] ?? null, $accion, $entidad, $id, $detalle !== null ? mb_substr($detalle, 0, 500) : null, ip()]);
}

// ---------------------------------------------------------------- CSRF
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">'; }
function csrf_verify(): void
{
    $t = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($t) || !hash_equals(csrf_token(), $t)) {
        http_response_code(419);
        exit('Sesión expirada o petición inválida. Recargue la página.');
    }
}
function is_post(): bool { return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'; }

// ---------------------------------------------------------------- Auth
function user(): ?array
{
    static $u = false;
    if ($u === false) {
        $u = null;
        if (!empty($_SESSION['uid'])) {
            $u = row('SELECT id, nombre, email, rol, es_gerente, activo FROM usuarios WHERE id = ?', [$_SESSION['uid']]);
            if (!$u || !$u['activo']) { $u = null; unset($_SESSION['uid']); }
        }
    }
    return $u;
}
function require_login(): array
{
    $u = user();
    if (!$u) redirect('login.php');
    $_SESSION['user'] = $u;
    return $u;
}
function is_admin(): bool { $u = user(); return $u && $u['rol'] === 'ADMIN'; }
function require_admin(): array
{
    $u = require_login();
    if ($u['rol'] !== 'ADMIN') { http_response_code(403); exit('Acceso restringido a Administradores.'); }
    return $u;
}
function is_gerente(): bool { $u = user(); return $u && (int)$u['es_gerente'] === 1; }

// ---------------------------------------------------------------- Archivos
const EXT_MIME = [
    'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'], 'webp' => ['image/webp'],
    'pdf' => ['application/pdf'], 'doc' => ['application/msword', 'application/octet-stream'],
    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
];
/** Guarda un archivo subido en storage/. Devuelve [ruta_relativa, mime, tamano, nombre_original] o lanza RuntimeException */
function guardar_archivo(array $f, string $subdir, array $exts, int $maxMb = 5): array
{
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('No se pudo subir el archivo (código ' . ($f['error'] ?? '?') . '). Verifique el límite upload_max_filesize del hosting.');
    }
    if ($f['size'] > $maxMb * 1048576) throw new RuntimeException("El archivo supera {$maxMb} MB.");
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $exts, true)) throw new RuntimeException('Tipo de archivo no permitido. Permitidos: ' . implode(', ', $exts));
    $fi = new finfo(FILEINFO_MIME_TYPE);
    $mime = $fi->file($f['tmp_name']);
    if (!in_array($mime, EXT_MIME[$ext], true)) throw new RuntimeException('El contenido del archivo no coincide con su extensión.');
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true) && @getimagesize($f['tmp_name']) === false) throw new RuntimeException('Imagen inválida.');
    $dir = ROOT . '/storage/' . $subdir;
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) throw new RuntimeException('No se pudo crear la carpeta de almacenamiento.');
    $name = bin2hex(random_bytes(14)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) throw new RuntimeException('No se pudo guardar el archivo.');
    return [$subdir . '/' . $name, $mime, (int)$f['size'], mb_substr($f['name'], 0, 255)];
}
function borrar_archivo(?string $rel): void
{
    if ($rel && strpos($rel, '..') === false && is_file(ROOT . '/storage/' . $rel)) @unlink(ROOT . '/storage/' . $rel);
}

require_once __DIR__ . '/layout.php';
