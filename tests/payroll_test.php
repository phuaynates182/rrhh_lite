<?php
// Pruebas del motor de cálculo contra los valores del Excel adjunto. Ejecutar:  php tests/payroll_test.php
require __DIR__ . '/../includes/payroll.php';

$P = ['rmv' => 1130, 'asig_fam_pct' => 10, 'essalud_pct' => 9, 'sctr_pct' => 1.25, 'uit' => 5500, 'tope_rma' => 12732.70, 'quinta_uit_deduccion' => 7, 'bonif_extra_pct' => 9];
$afp = ['PRIMA' => [1.6, 'AFP Prima'], 'HABITAT' => [1.47, 'AFP Habitat'], 'INTEGRA' => [1.55, 'AFP Integra'], 'PROFUTURO' => [1.69, 'AFP Profuturo']];
$onp = ['tipo' => 'ONP', 'nombre' => 'ONP', 'aporte_pct' => 13, 'comision_pct' => 0, 'prima_pct' => 0];
$mk = fn($c) => ['tipo' => 'AFP', 'nombre' => $afp[$c][1], 'aporte_pct' => 10, 'comision_pct' => $afp[$c][0], 'prima_pct' => 1.37];
$emp = fn($b, $af) => ['sueldo_basico' => $b, 'asignacion_familiar' => $af, 'tipo_trabajador' => 'INDEFINIDO', 'regimen' => 'GENERAL', 'essalud' => 1, 'sctr' => 1,
    'quinta_categoria' => 0, 'fecha_ingreso' => '2020-01-01', 'fecha_cese' => null];

// [nombre, basico, AF, pension, bruto, descuento, neto, essalud, sctr] — valores del Excel (hoja Planilla)
$casos = [
    ['Gerente (PRIMA)', 5000, 1, $mk('PRIMA'), 5113, 663.1561, 4449.8439, 460.17, 63.9125],
    ['Administrador (HABITAT)', 2500, 1, $mk('HABITAT'), 2613, 335.5092, 2277.4908, 235.17, 32.6625],
    ['Contador (INTEGRA)', 2300, 1, $mk('INTEGRA'), 2413, 311.7596, 2101.2404, 217.17, 30.1625],
    ['Cajero (PROFUTURO)', 2000, 0, $mk('PROFUTURO'), 2000, 261.2, 1738.8, 180, 25],
    ['Aux. contable (ONP)', 1500, 1, $onp, 1613, 209.69, 1403.31, 145.17, 20.1625],
    // EsSalud: el sistema aplica base mínima = RMV (1,130 x 9% = 101.70); el Excel usaba 1,013 x 9% = 91.17 (mejora legal)
    ['Vendedor (ONP)', 900, 1, $onp, 1013, 131.69, 881.31, 101.70, 12.6625],
];
$fail = 0;
foreach ($casos as [$n, $b, $af, $pen, $bruto, $desc, $neto, $ess, $sctr]) {
    $r = Payroll::mensual($emp($b, $af), $pen, $P, [], 30, 2025, 1);
    $ap = array_column(array_map(fn($l) => [$l[1], $l[3]], array_filter($r['lineas'], fn($l) => $l[0] === 'APORTE')), 1, 0);
    $ok = abs($r['bruto'] - $bruto) < 0.01 && abs($r['descuentos'] - $desc) < 0.03 && abs($r['neto'] - $neto) < 0.03
        && abs($ap['ESSALUD'] - $ess) < 0.01 && abs($ap['SCTR'] - $sctr) < 0.02;
    printf("%-26s bruto %9.2f desc %8.2f neto %9.2f  %s\n", $n, $r['bruto'], $r['descuentos'], $r['neto'], $ok ? 'OK' : 'FALLA');
    if (!$ok) $fail++;
}

// Variable afecta: bono 500 a un trabajador ONP de 2000 => afecta 2500 => ONP 325
$r = Payroll::mensual($emp(2000, 0), $onp, $P, [['codigo' => 'BONO', 'descripcion' => 'Bonos', 'tipo' => 'ING', 'afecto' => 1, 'monto' => 500]], 30, 2025, 6);
$t1 = abs($r['bruto'] - 2500) < 0.01 && abs($r['descuentos'] - 325) < 0.01;
echo 'Bono afecto (ONP 13%): ' . ($t1 ? 'OK' : 'FALLA') . "\n"; if (!$t1) $fail++;

// Prorrateo: 15 días de 3000 => 1500
$r = Payroll::mensual($emp(3000, 0), $onp, $P, [], 15, 2025, 6);
$t2 = abs($r['bruto'] - 1500) < 0.01; echo 'Prorrateo 15 dias: ' . ($t2 ? 'OK' : 'FALLA') . "\n"; if (!$t2) $fail++;

// Gratificación completa: (3000 + 113)... sin AF => 3000 + bonif 9% = 3270
$r = Payroll::gratificacion($emp(3000, 0), $P, [], 2025, 7);
$t3 = abs($r['bruto'] - 3270) < 0.01; echo 'Gratificacion julio: ' . ($t3 ? 'OK' : 'FALLA') . " ({$r['bruto']})\n"; if (!$t3) $fail++;

// CTS noviembre completa: base = 3000 + 500 = 3500 => 3500/12*6 = 1750
$r = Payroll::cts($emp(3000, 0), $P, [], 2025, 11);
$t4 = abs($r['bruto'] - 1750) < 0.01; echo 'CTS noviembre: ' . ($t4 ? 'OK' : 'FALLA') . " ({$r['bruto']})\n"; if (!$t4) $fail++;

// Quinta: 10,000 mensuales debe retener algo; 1,500 no
$e5 = $emp(10000, 0); $e5['quinta_categoria'] = 1;
$r = Payroll::mensual($e5, $onp, $P, [], 30, 2025, 1);
$q = 0; foreach ($r['lineas'] as $l) if ($l[1] === 'RENTA5') $q = $l[3];
$t5 = $q > 0; echo "5ta categoria 10,000: retiene $q " . ($t5 ? 'OK' : 'FALLA') . "\n"; if (!$t5) $fail++;

echo $fail ? "\n$fail PRUEBA(S) FALLARON\n" : "\nTODAS LAS PRUEBAS OK\n";
exit($fail ? 1 : 0);
