<?php
declare(strict_types=1);

/** Lee CSV o XLSX/XLSM y devuelve filas (array de arrays de string). Sin dependencias externas (apto cPanel). */
function leer_tabla(string $path, string $ext): array
{
    $ext = strtolower($ext);
    if (in_array($ext, ['xlsx', 'xlsm'], true)) return xlsx_rows($path);
    if (in_array($ext, ['csv', 'txt'], true)) return csv_rows($path);
    throw new RuntimeException('Formato no soportado. Use .csv, .xlsx o .xlsm');
}

function csv_rows(string $path): array
{
    $raw = file_get_contents($path);
    if ($raw === false) throw new RuntimeException('No se pudo leer el archivo.');
    if (substr($raw, 0, 3) === "\xEF\xBB\xBF") $raw = substr($raw, 3);
    if (!mb_check_encoding($raw, 'UTF-8')) $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
    $first = strtok($raw, "\n") ?: '';
    $best = ','; $max = -1;
    foreach ([',', ';', "\t", '|'] as $d) { $c = substr_count($first, $d); if ($c > $max) { $max = $c; $best = $d; } }
    $fh = fopen('php://temp', 'r+');
    fwrite($fh, $raw); rewind($fh);
    $out = [];
    while (($r = fgetcsv($fh, 0, $best, '"', '\\')) !== false) {
        if ($r === [null]) continue;
        $out[] = array_map(fn($v) => trim((string)$v), $r);
    }
    fclose($fh);
    return $out;
}

function xlsx_col_index(string $ref): int
{
    preg_match('/^([A-Z]+)/', $ref, $m);
    $n = 0;
    foreach (str_split($m[1] ?? 'A') as $ch) $n = $n * 26 + (ord($ch) - 64);
    return $n - 1;
}

function xlsx_rows(string $path): array
{
    if (!class_exists('ZipArchive')) throw new RuntimeException('La extensión PHP zip no está activa en el servidor. Use CSV.');
    $z = new ZipArchive();
    if ($z->open($path) !== true) throw new RuntimeException('Archivo Excel inválido.');
    $ss = [];
    $x = $z->getFromName('xl/sharedStrings.xml');
    if ($x !== false) {
        $sx = simplexml_load_string($x);
        foreach ($sx->si as $si) {
            if (isset($si->t)) { $ss[] = (string)$si->t; continue; }
            $t = '';
            foreach ($si->r as $r) $t .= (string)$r->t;
            $ss[] = $t;
        }
    }
    // Resolver hojas visibles en orden
    $wb = simplexml_load_string((string)$z->getFromName('xl/workbook.xml'));
    $rels = simplexml_load_string((string)$z->getFromName('xl/_rels/workbook.xml.rels'));
    $map = [];
    foreach ($rels->Relationship as $rel) {
        $t = (string)$rel['Target'];
        $map[(string)$rel['Id']] = ltrim(strpos($t, '/') === 0 ? $t : 'xl/' . $t, '/');
    }
    $sheets = [];
    foreach ($wb->sheets->sheet as $sh) {
        $state = (string)$sh['state'];
        $rid = (string)$sh->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')->id;
        if ($state !== 'hidden' && $state !== 'veryHidden' && isset($map[$rid])) $sheets[] = [(string)$sh['name'], $map[$rid]];
    }
    if (!$sheets) throw new RuntimeException('El libro no tiene hojas visibles.');
    $pick = $sheets[0];
    foreach ($sheets as $s) if (preg_match('/plantilla|planilla|colaborador|empleado|personal|datos/i', $s[0])) { $pick = $s; break; }

    $xml = $z->getFromName($pick[1]);
    if ($xml === false) throw new RuntimeException('No se pudo leer la hoja.');
    $ws = simplexml_load_string($xml);
    $out = [];
    foreach ($ws->sheetData->row as $row) {
        $line = [];
        $maxc = -1;
        foreach ($row->c as $c) {
            $i = xlsx_col_index((string)$c['r']);
            $t = (string)$c['t'];
            if ($t === 's') $v = $ss[(int)$c->v] ?? '';
            elseif ($t === 'inlineStr') $v = (string)$c->is->t;
            else $v = (string)$c->v;
            $line[$i] = trim($v);
            if ($i > $maxc) $maxc = $i;
        }
        $full = [];
        for ($i = 0; $i <= $maxc; $i++) $full[] = $line[$i] ?? '';
        if (array_filter($full, fn($v) => $v !== '')) $out[] = $full;
    }
    $z->close();
    return $out;
}

/** Normaliza un encabezado: sin tildes, minúsculas, guiones bajos */
function norm_header(string $h): string
{
    $h = mb_strtolower(trim($h));
    $h = strtr($h, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
    return trim(preg_replace('/[^a-z0-9]+/', '_', $h), '_');
}

/** Convierte serial Excel o texto (d/m/Y, Y-m-d) a Y-m-d; '' si no se puede */
function parse_fecha(string $v): string
{
    $v = trim($v);
    if ($v === '') return '';
    if (preg_match('/^\d{5}(\.\d+)?$/', $v)) return gmdate('Y-m-d', (int)(((float)$v - 25569) * 86400));
    if (preg_match('/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})$/', $v, $m)) return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
    if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})/', $v, $m)) return sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
    return '';
}

/** Número a letras (soles) para contratos */
function num_letras(float $n): string
{
    $ent = (int)floor($n);
    $cent = (int)round(($n - $ent) * 100);
    $txt = $ent === 0 ? 'cero' : trim(num_a_texto($ent));
    return mb_strtoupper($txt) . sprintf(' CON %02d/100 SOLES', $cent);
}
function num_a_texto(int $n): string
{
    $u = ['', 'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve', 'diez', 'once', 'doce', 'trece', 'catorce', 'quince',
        'dieciséis', 'diecisiete', 'dieciocho', 'diecinueve', 'veinte', 'veintiuno', 'veintidós', 'veintitrés', 'veinticuatro', 'veinticinco',
        'veintiséis', 'veintisiete', 'veintiocho', 'veintinueve'];
    $d = [3 => 'treinta', 4 => 'cuarenta', 5 => 'cincuenta', 6 => 'sesenta', 7 => 'setenta', 8 => 'ochenta', 9 => 'noventa'];
    $c = [1 => 'ciento', 2 => 'doscientos', 3 => 'trescientos', 4 => 'cuatrocientos', 5 => 'quinientos', 6 => 'seiscientos',
        7 => 'setecientos', 8 => 'ochocientos', 9 => 'novecientos'];
    if ($n < 30) return $u[$n];
    if ($n < 100) { $a = intdiv($n, 10); $b = $n % 10; return $d[$a] . ($b ? ' y ' . $u[$b] : ''); }
    if ($n === 100) return 'cien';
    if ($n < 1000) return $c[intdiv($n, 100)] . ' ' . num_a_texto($n % 100);
    if ($n < 2000) return trim('mil ' . num_a_texto($n % 1000));
    if ($n < 1000000) return trim(preg_replace('/uno$/u', 'ún', num_a_texto(intdiv($n, 1000))) . ' mil ' . num_a_texto($n % 1000));
    if ($n < 2000000) return trim('un millón ' . num_a_texto($n % 1000000));
    return trim(num_a_texto(intdiv($n, 1000000)) . ' millones ' . num_a_texto($n % 1000000));
}
