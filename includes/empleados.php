<?php
declare(strict_types=1);

const DOC_TIPOS = [
    'FOTO' => 'Fotografía', 'CV' => 'Currículum Vitae', 'RECIBO_DOMICILIO' => 'Recibo de domicilio',
    'CONTRATO_FIRMADO' => 'Contrato firmado', 'DNI' => 'Copia de DNI/CE', 'OTRO' => 'Otro documento',
];
const DOC_UNICOS = ['FOTO', 'CV', 'RECIBO_DOMICILIO'];

function nombre_completo(array $e): string
{
    return trim(($e['apellido_paterno'] ?? '') . ' ' . ($e['apellido_materno'] ?? '')) . ', ' . ($e['nombres'] ?? '');
}

/** Sube un documento al legajo. Los tipos únicos reemplazan al anterior. */
function subir_doc_empleado(int $empId, string $tipo, array $file, ?int $contratoId = null): void
{
    if (!isset(DOC_TIPOS[$tipo])) throw new RuntimeException('Tipo de documento inválido.');
    $exts = $tipo === 'FOTO' ? ['jpg', 'jpeg', 'png', 'webp'] : ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx'];
    [$ruta, $mime, $size, $orig] = guardar_archivo($file, 'uploads/emp_' . $empId, $exts, 8);
    if (in_array($tipo, DOC_UNICOS, true)) {
        foreach (rows('SELECT id, ruta FROM empleado_documentos WHERE empleado_id = ? AND tipo = ?', [$empId, $tipo]) as $old) {
            borrar_archivo($old['ruta']);
            q('DELETE FROM empleado_documentos WHERE id = ?', [$old['id']]);
        }
    }
    q('INSERT INTO empleado_documentos (empleado_id, tipo, nombre_original, ruta, mime, tamano, contrato_id, subido_por) VALUES (?,?,?,?,?,?,?,?)',
        [$empId, $tipo, $orig, $ruta, $mime, $size, $contratoId, $_SESSION['uid'] ?? null]);
    audit('DOC_SUBIDO', 'empleados', $empId, DOC_TIPOS[$tipo] . ': ' . $orig);
}

function foto_empleado(int $empId): ?int
{
    $v = val("SELECT id FROM empleado_documentos WHERE empleado_id = ? AND tipo = 'FOTO' ORDER BY id DESC LIMIT 1", [$empId]);
    return $v ? (int)$v : null;
}

function validar_empleado(array $d, ?int $idActual): ?string
{
    if ($d['apellido_paterno'] === '' || $d['nombres'] === '') return 'Apellido paterno y nombres son obligatorios.';
    if ($d['tipo_doc'] === 'DNI' && !preg_match('/^\d{8}$/', $d['nro_doc'])) return 'El DNI debe tener 8 dígitos.';
    if ($d['tipo_doc'] !== 'DNI' && !preg_match('/^[A-Za-z0-9]{6,15}$/', $d['nro_doc'])) return 'Número de documento inválido.';
    if (!valid_date($d['fecha_ingreso'])) return 'Fecha de ingreso inválida.';
    if ($d['fecha_nacimiento'] !== null && !valid_date($d['fecha_nacimiento'])) return 'Fecha de nacimiento inválida.';
    if ($d['fecha_cese'] !== null && !valid_date($d['fecha_cese'])) return 'Fecha de cese inválida.';
    if ($d['sueldo_basico'] < 0) return 'El sueldo no puede ser negativo.';
    if ($d['email'] && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) return 'Correo inválido.';
    $dup = val('SELECT id FROM empleados WHERE nro_doc = ? AND id <> ?', [$d['nro_doc'], $idActual ?? 0]);
    if ($dup) return 'Ya existe un colaborador con ese documento.';
    return null;
}
