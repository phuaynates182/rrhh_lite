<?php
require __DIR__ . '/includes/bootstrap.php';
require_login();

$rel = null; $mime = null; $name = 'archivo';
if (get('e') === 'logo' || get('e') === 'firma') {
    $emp = empresa();
    $rel = $emp[get('e') . '_path'] ?? null;
} elseif (get('d') !== '') {
    $d = row('SELECT * FROM empleado_documentos WHERE id = ?', [(int)get('d')]);
    if ($d) { $rel = $d['ruta']; $name = $d['nombre_original']; }
}
if (!$rel || strpos($rel, '..') !== false || !is_file(ROOT . '/storage/' . $rel)) {
    http_response_code(404);
    exit('Archivo no encontrado');
}
$path = ROOT . '/storage/' . $rel;
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
$inline = in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'], true);
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: ' . ($inline && !isset($_GET['dl']) ? 'inline' : 'attachment') . '; filename="' . rawurlencode($name) . '"');
header('Cache-Control: private, max-age=600');
readfile($path);
