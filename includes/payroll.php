<?php
declare(strict_types=1);

/**
 * Motor de cálculo de planilla (Perú). Funciones puras: no tocan la BD.
 *
 * Cada método devuelve:
 *   ['lineas' => [[tipo(ING|DESC|APORTE), codigo, descripcion, monto, manual], ...],
 *    'afecta' => float, 'bruto' => float, 'descuentos' => float, 'neto' => float, 'aportes' => float, 'dias' => int]
 *
 * Parámetros $P: rmv, asig_fam_pct, essalud_pct, sctr_pct, uit, tope_rma, quinta_uit_deduccion, bonif_extra_pct
 * $pen: fila de sistemas_pension (tipo ONP|AFP, aporte_pct, comision_pct, prima_pct) o null
 * $manual: lista de ['codigo','descripcion','tipo'(ING|DESC),'afecto'(0|1),'monto']
 */
final class Payroll
{
    public static function r(float $v): float { return round($v + 1e-9, 2); }

    /** Días cubiertos de un mes (base 30) según fecha de ingreso/cese. */
    public static function diasMes(array $emp, int $anio, int $mes): int
    {
        $ini = new DateTime(sprintf('%04d-%02d-01', $anio, $mes));
        $fin = (clone $ini)->modify('last day of this month');
        $dim = (int)$fin->format('j');
        $desde = new DateTime($emp['fecha_ingreso']);
        if ($desde < $ini) $desde = clone $ini;
        $hasta = !empty($emp['fecha_cese']) ? new DateTime($emp['fecha_cese']) : clone $fin;
        if ($hasta > $fin) $hasta = clone $fin;
        if ($hasta < $desde) return 0;
        $cub = (int)$desde->diff($hasta)->days + 1;
        return $cub >= $dim ? 30 : min($cub, 30);
    }

    /** Régimen efectivo del trabajador */
    private static function reg(array $emp): string { return $emp['regimen'] ?? 'GENERAL'; }

    private static function aplicaAF(array $emp): bool
    {
        return !empty($emp['asignacion_familiar']) && $emp['tipo_trabajador'] !== 'PRACTICANTE' && self::reg($emp) !== 'MICRO';
    }

    private static function sumManual(array $manual, string $tipo, ?int $afecto = null): float
    {
        $s = 0.0;
        foreach ($manual as $m) {
            if ($m['tipo'] === $tipo && ($afecto === null || (int)$m['afecto'] === $afecto)) $s += (float)$m['monto'];
        }
        return $s;
    }

    private static function pack(array $lineas, float $afecta, int $dias): array
    {
        $ing = $des = $apo = 0.0;
        foreach ($lineas as $l) {
            if ($l[0] === 'ING') $ing += $l[3];
            elseif ($l[0] === 'DESC') $des += $l[3];
            else $apo += $l[3];
        }
        return ['lineas' => $lineas, 'afecta' => self::r($afecta), 'bruto' => self::r($ing), 'descuentos' => self::r($des),
            'neto' => self::r($ing - $des), 'aportes' => self::r($apo), 'dias' => $dias];
    }

    // ------------------------------------------------------------------ MENSUAL
    /**
     * @param array $prev ['quinta' => retenido previo en el año, 'variables' => variables afectas pagadas antes en el año]
     */
    public static function mensual(array $emp, ?array $pen, array $P, array $manual, int $dias, int $anio, int $mes, array $prev = []): array
    {
        $L = [];
        $dias = max(0, min(30, $dias));
        $basico = self::r((float)$emp['sueldo_basico'] * $dias / 30);
        $af = self::aplicaAF($emp) ? self::r($P['rmv'] * $P['asig_fam_pct'] / 100 * $dias / 30) : 0.0;
        $L[] = ['ING', 'SUELDO', 'Haber básico (' . $dias . ' días)', $basico, 0];
        if ($af > 0) $L[] = ['ING', 'ASIGFAM', 'Asignación familiar', $af, 0];
        foreach ($manual as $m) {
            if ($m['tipo'] === 'ING' && (float)$m['monto'] > 0) $L[] = ['ING', $m['codigo'], $m['descripcion'], self::r((float)$m['monto']), 1];
        }
        $varAfecta = self::sumManual($manual, 'ING', 1);
        $afecta = $basico + $af + $varAfecta;

        // Pensiones (practicantes no aportan)
        if ($pen && $emp['tipo_trabajador'] !== 'PRACTICANTE') {
            if ($pen['tipo'] === 'ONP') {
                $L[] = ['DESC', 'ONP', 'ONP ' . (float)$pen['aporte_pct'] . '%', self::r($afecta * $pen['aporte_pct'] / 100), 0];
            } else {
                $L[] = ['DESC', 'AFP_APORTE', 'AFP ' . $pen['nombre'] . ' - Aporte obligatorio ' . (float)$pen['aporte_pct'] . '%', self::r($afecta * $pen['aporte_pct'] / 100), 0];
                $L[] = ['DESC', 'AFP_COMISION', 'AFP - Comisión sobre flujo ' . (float)$pen['comision_pct'] . '%', self::r($afecta * $pen['comision_pct'] / 100), 0];
                $baseP = min($afecta, $P['tope_rma']);
                $L[] = ['DESC', 'AFP_PRIMA', 'AFP - Prima de seguro ' . (float)$pen['prima_pct'] . '%', self::r($baseP * $pen['prima_pct'] / 100), 0];
            }
        }

        // 5ta categoría (estimación por proyección anual)
        if (!empty($emp['quinta_categoria']) && $emp['tipo_trabajador'] !== 'PRACTICANTE') {
            $q = self::quinta($emp, $P, $basico, $af, $varAfecta, $mes, (float)($prev['quinta'] ?? 0), (float)($prev['variables'] ?? 0), $dias);
            if ($q > 0) $L[] = ['DESC', 'RENTA5', 'Impuesto a la renta 5ta categoría', $q, 0];
        }

        foreach ($manual as $m) {
            if ($m['tipo'] === 'DESC' && (float)$m['monto'] > 0) $L[] = ['DESC', $m['codigo'], $m['descripcion'], self::r((float)$m['monto']), 1];
        }

        // Aportes del empleador
        if (!empty($emp['essalud'])) {
            $baseE = max($afecta, $P['rmv'] * $dias / 30);
            $L[] = ['APORTE', 'ESSALUD', 'EsSalud ' . $P['essalud_pct'] . '%', self::r($baseE * $P['essalud_pct'] / 100), 0];
        }
        if (!empty($emp['sctr'])) {
            $L[] = ['APORTE', 'SCTR', 'SCTR ' . $P['sctr_pct'] . '%', self::r($afecta * $P['sctr_pct'] / 100), 0];
        }
        return self::pack($L, $afecta, $dias);
    }

    /** Retención mensual de 5ta categoría (método SUNAT simplificado). */
    private static function quinta(array $emp, array $P, float $basico, float $af, float $varMes, int $mes, float $retPrevia, float $varPrev, int $dias): float
    {
        $fijoMes = $emp['sueldo_basico'] + ($af > 0 ? $P['rmv'] * $P['asig_fam_pct'] / 100 : 0);
        $grati = 0.0;
        if (self::reg($emp) === 'GENERAL') $grati = 2 * $fijoMes * (1 + $P['bonif_extra_pct'] / 100);
        elseif (self::reg($emp) === 'PEQUENA') $grati = $fijoMes * (1 + $P['bonif_extra_pct'] / 100);
        // Remuneración del mes + meses restantes proyectados + meses previos (supuesto: igual fijo) + variables
        $proy = $basico + $af + $fijoMes * (12 - $mes) + $fijoMes * ($mes - 1) + $grati + $varMes + $varPrev;
        $neta = $proy - $P['quinta_uit_deduccion'] * $P['uit'];
        if ($neta <= 0) return 0.0;
        $uit = $P['uit'];
        $tramos = [[5, .08], [20, .14], [35, .17], [45, .20], [INF, .30]];
        $imp = 0.0; $ant = 0.0;
        foreach ($tramos as [$lim, $tasa]) {
            $tope = $lim === INF ? INF : $lim * $uit;
            if ($neta > $ant) $imp += (min($neta, $tope) - $ant) * $tasa;
            $ant = $tope;
            if ($neta <= $tope) break;
        }
        $divisor = [1 => 12, 2 => 12, 3 => 12, 4 => 9, 5 => 8, 6 => 8, 7 => 8, 8 => 5, 9 => 4, 10 => 4, 11 => 4, 12 => 1][$mes];
        $pend = $imp - $retPrevia;
        return $pend > 0 ? self::r($pend / $divisor) : 0.0;
    }

    // ------------------------------------------------------------------ GRATIFICACIÓN
    /** $mes = 7 (Fiestas Patrias: ene-jun) o 12 (Navidad: jul-dic) */
    public static function gratificacion(array $emp, array $P, array $manual, int $anio, int $mes): array
    {
        $reg = self::reg($emp);
        $factor = $reg === 'GENERAL' ? 1.0 : ($reg === 'PEQUENA' ? 0.5 : 0.0);
        if ($emp['tipo_trabajador'] === 'PRACTICANTE') $factor = 0.0;
        [$m1, $m2] = $mes === 7 ? [1, 6] : [7, 12];
        $meses = 0;
        for ($m = $m1; $m <= $m2; $m++) if (self::diasMes($emp, $anio, $m) === 30) $meses++;
        $af = self::aplicaAF($emp) ? $P['rmv'] * $P['asig_fam_pct'] / 100 : 0;
        $base = (float)$emp['sueldo_basico'] + $af + self::sumManual($manual, 'ING', 1); // variables = promedio semestral ingresado
        $grati = self::r($base / 6 * $meses * $factor);
        $L = [];
        $L[] = ['ING', 'GRATI', "Gratificación ($meses/6 meses)", $grati, 0];
        $bon = (!empty($emp['essalud']) && $grati > 0) ? self::r($grati * $P['bonif_extra_pct'] / 100) : 0.0;
        if ($bon > 0) $L[] = ['ING', 'BONIF_EXTRA', 'Bonificación extraordinaria ' . $P['bonif_extra_pct'] . '% (Ley 30334)', $bon, 0];
        foreach ($manual as $m) {
            if ($m['tipo'] === 'ING' && (float)$m['monto'] > 0 && (int)$m['afecto'] === 0) $L[] = ['ING', $m['codigo'], $m['descripcion'], self::r((float)$m['monto']), 1];
            if ($m['tipo'] === 'DESC' && (float)$m['monto'] > 0) $L[] = ['DESC', $m['codigo'], $m['descripcion'], self::r((float)$m['monto']), 1];
        }
        // La gratificación no está afecta a AFP/ONP. Ver nota de 5ta categoría en la documentación.
        return self::pack($L, 0, $meses * 30);
    }

    // ------------------------------------------------------------------ CTS
    /** $mes = 5 (nov-abr) o 11 (may-oct). Se acumula en días base 30. */
    public static function cts(array $emp, array $P, array $manual, int $anio, int $mes): array
    {
        $reg = self::reg($emp);
        $factor = $reg === 'GENERAL' ? 1.0 : ($reg === 'PEQUENA' ? 0.5 : 0.0);
        if (in_array($emp['tipo_trabajador'], ['PRACTICANTE', 'TIEMPO_PARCIAL'], true)) $factor = 0.0;
        $per = $mes === 5 ? [[$anio - 1, 11], [$anio - 1, 12], [$anio, 1], [$anio, 2], [$anio, 3], [$anio, 4]]
                          : [[$anio, 5], [$anio, 6], [$anio, 7], [$anio, 8], [$anio, 9], [$anio, 10]];
        $tot = 0;
        foreach ($per as [$y, $m]) $tot += self::diasMes($emp, $y, $m);
        $meses = intdiv($tot, 30); $dias = $tot % 30;
        $af = self::aplicaAF($emp) ? $P['rmv'] * $P['asig_fam_pct'] / 100 : 0;
        $fijo = (float)$emp['sueldo_basico'] + $af;
        // Remuneración computable = fijo + 1/6 de la última gratificación (aprox. = fijo/6) + promedio de variables
        $base = $fijo + $fijo / 6 + self::sumManual($manual, 'ING', 1);
        $cts = self::r(($base / 12 * $meses + $base / 360 * $dias) * $factor);
        $L = [['ING', 'CTS', "CTS ($meses meses y $dias días)", $cts, 0]];
        foreach ($manual as $m) {
            if ($m['tipo'] === 'DESC' && (float)$m['monto'] > 0) $L[] = ['DESC', $m['codigo'], $m['descripcion'], self::r((float)$m['monto']), 1];
        }
        return self::pack($L, 0, $tot);
    }

    // ------------------------------------------------------------------ VACACIONES
    /** Días devengados a la fecha (30/año régimen general; 15/año pequeña y micro) */
    public static function vacacionesDevengadas(array $emp, ?DateTime $hasta = null): float
    {
        $hasta = $hasta ?? new DateTime('today');
        $ing = new DateTime($emp['fecha_ingreso']);
        if (!empty($emp['fecha_cese'])) { $c = new DateTime($emp['fecha_cese']); if ($c < $hasta) $hasta = $c; }
        if ($hasta < $ing) return 0.0;
        $dias = ((int)$ing->diff($hasta)->days + 1) / 365.0;
        $porAnio = self::reg($emp) === 'GENERAL' ? 30 : 15;
        return round($dias * $porAnio, 1);
    }
}
