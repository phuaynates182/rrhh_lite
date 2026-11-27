<?php
require __DIR__ . '/includes/bootstrap.php';
require_login();
$tipo = get('t', 'empleados');
header('Content-Type: text/csv; charset=utf-8');
if ($tipo === 'variables') {
    header('Content-Disposition: attachment; filename="plantilla_variables.csv"');
    echo "\xEF\xBB\xBF";
    $cols = ['DNI'];
    foreach (rows("SELECT codigo FROM conceptos WHERE activo = 1 ORDER BY orden, id") as $c) $cols[] = $c['codigo'];
    $cols[] = 'DIAS';
    echo implode(';', $cols) . "\r\n";
    echo '45457414;300;150;0;0;0;30' . "\r\n";
    exit;
}
header('Content-Disposition: attachment; filename="plantilla_colaboradores.csv"');
echo "\xEF\xBB\xBF";
echo "DNI;Apellido_Paterno;Apellido_Materno;Nombres;Cargo;Area;Fecha_Ingreso;Sueldo_Basico;Asignacion_Familiar;Sistema_Pension;CUSPP;Tipo_Trabajador;Fecha_Nacimiento;Email;Telefono;Direccion;Banco;Nro_Cuenta;CCI\r\n";
echo "45457414;DURAN;PEREZ;Jhinson;Gerente General;Gerencia;01/03/2024;5000;SI;PRIMA;;INDEFINIDO;15/05/1985;jhinson@correo.com;999888777;Av. Principal 123;BCP;19412345678;00219400000000000000\r\n";
echo "45457418;LOGROÑO;SATAN;Catalina;Aux. Contable;Contabilidad;01/06/2024;1500;SI;ONP;;PLAZO_FIJO;;;;;;;\r\n";
