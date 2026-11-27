<?php
declare(strict_types=1);

/**
 * Plantillas de contrato por tipo de trabajador (editables luego desde el sistema).
 * IMPORTANTE: son modelos orientativos; deben ser revisados por un abogado laboralista antes de su uso.
 * Marcadores: {{EMPRESA}} {{RUC}} {{DOMICILIO_EMPRESA}} {{REP_NOMBRE}} {{REP_DNI}} {{REP_CARGO}} {{TRABAJADOR}} {{TIPO_DOC}} {{NRO_DOC}}
 * {{DOMICILIO}} {{NACIONALIDAD}} {{ESTADO_CIVIL}} {{CARGO}} {{AREA}} {{SUELDO}} {{SUELDO_LETRAS}} {{FECHA_INICIO}} {{FECHA_FIN}}
 * {{JORNADA}} {{CAUSA}} {{CIUDAD}} {{FECHA_HOY}} {{PERIODO_PRUEBA}}
 */
function plantillas_default(): array
{
    $cab = '<h2 style="text-align:center">%TITULO%</h2>
<p>Conste por el presente documento el contrato de trabajo que celebran, de una parte, <b>{{EMPRESA}}</b>, con RUC N° {{RUC}}, con domicilio en {{DOMICILIO_EMPRESA}}, debidamente representada por {{REP_NOMBRE}}, identificado con DNI N° {{REP_DNI}}, en su calidad de {{REP_CARGO}}, a quien en adelante se denominará <b>EL EMPLEADOR</b>; y de la otra parte, <b>{{TRABAJADOR}}</b>, identificado con {{TIPO_DOC}} N° {{NRO_DOC}}, de nacionalidad {{NACIONALIDAD}}, estado civil {{ESTADO_CIVIL}}, con domicilio en {{DOMICILIO}}, a quien en adelante se denominará <b>EL TRABAJADOR</b>, en los términos y condiciones siguientes:</p>';
    $pie = '<p>Las partes declaran haber leído el presente contrato y, en señal de conformidad, lo suscriben en dos ejemplares de igual tenor en {{CIUDAD}}, el {{FECHA_HOY}}.</p>
<table style="width:100%;margin-top:70px;border:0"><tr>
<td style="text-align:center;border:0;width:50%">{{FIRMA_EMPLEADOR}}<hr style="width:70%"><b>EL EMPLEADOR</b><br>{{REP_NOMBRE}}<br>{{REP_CARGO}}</td>
<td style="text-align:center;border:0;width:50%"><div style="height:70px"></div><hr style="width:70%"><b>EL TRABAJADOR</b><br>{{TRABAJADOR}}<br>{{TIPO_DOC}} {{NRO_DOC}}</td></tr></table>';

    $remu = '<p><b>CLÁUSULA {N}.- REMUNERACIÓN.</b> EL EMPLEADOR abonará a EL TRABAJADOR una remuneración mensual de <b>S/ {{SUELDO}}</b> ({{SUELDO_LETRAS}}), de la cual se efectuarán las retenciones y aportes de ley (sistema de pensiones, impuesto a la renta de quinta categoría cuando corresponda). EL TRABAJADOR percibirá además los beneficios sociales que le correspondan según su régimen laboral (gratificaciones por Fiestas Patrias y Navidad, bonificación extraordinaria, compensación por tiempo de servicios, vacaciones y asignación familiar de corresponder).</p>';
    $jorn = '<p><b>CLÁUSULA {N}.- JORNADA Y HORARIO.</b> La jornada de trabajo será de {{JORNADA}} horas semanales, sin exceder el máximo legal de 8 horas diarias o 48 semanales, según el horario establecido por EL EMPLEADOR, con un refrigerio no computable de la jornada. Las horas trabajadas en sobretiempo requieren autorización previa y son voluntarias, abonándose con los recargos de ley.</p>';
    $gen = '<p><b>CLÁUSULA {N}.- OBLIGACIONES.</b> EL TRABAJADOR se obliga a cumplir con diligencia las labores inherentes a su cargo, el Reglamento Interno de Trabajo, las normas de seguridad y salud en el trabajo (Ley 29783), las normas de confidencialidad y protección de datos personales (Ley 29733) y las instrucciones razonables de EL EMPLEADOR.</p>
<p><b>CLÁUSULA {N}.- NORMATIVA APLICABLE.</b> En todo lo no previsto, las partes se someten al Texto Único Ordenado del Decreto Legislativo N° 728, Ley de Productividad y Competitividad Laboral (D.S. N° 003-97-TR), su Reglamento y demás normas laborales vigentes.</p>';

    $arm = function (string $titulo, string $cuerpo) use ($cab, $pie, $remu, $jorn, $gen): string {
        $html = str_replace('%TITULO%', $titulo, $cab) . $cuerpo . $remu . $jorn . $gen . $pie;
        $n = 0;
        return preg_replace_callback('/\{N\}/', function () use (&$n) { return (string)(++$n === 0 ? 0 : $n); }, $html);
    };
    // Numeración correlativa: se reemplaza {N} secuencialmente.

    $indef = '<p><b>CLÁUSULA {N}.- OBJETO.</b> EL EMPLEADOR contrata a EL TRABAJADOR para desempeñar el cargo de <b>{{CARGO}}</b> en el área de {{AREA}}, a plazo <b>indeterminado</b>, iniciando sus labores el {{FECHA_INICIO}}.</p>
<p><b>CLÁUSULA {N}.- PERIODO DE PRUEBA.</b> EL TRABAJADOR estará sujeto a un periodo de prueba de {{PERIODO_PRUEBA}}, conforme al artículo 10 del D.S. N° 003-97-TR; vencido el cual alcanzará protección contra el despido arbitrario.</p>';

    $fijo = '<p><b>CLÁUSULA {N}.- OBJETO Y MODALIDAD.</b> EL EMPLEADOR contrata a EL TRABAJADOR bajo la modalidad de contrato de trabajo <b>sujeto a modalidad a plazo fijo</b> (artículos 53 a 74 del D.S. N° 003-97-TR), para desempeñar el cargo de <b>{{CARGO}}</b> en el área de {{AREA}}.</p>
<p><b>CLÁUSULA {N}.- CAUSA OBJETIVA.</b> La contratación se justifica por la siguiente causa objetiva determinante: {{CAUSA}}</p>
<p><b>CLÁUSULA {N}.- PLAZO.</b> El contrato regirá desde el {{FECHA_INICIO}} hasta el {{FECHA_FIN}}, fecha en que concluirá automáticamente, salvo renovación por escrito dentro del límite legal de duración. EL EMPLEADOR presentará el contrato ante la Autoridad Administrativa de Trabajo dentro de los quince (15) días naturales de su celebración (art. 73).</p>
<p><b>CLÁUSULA {N}.- PERIODO DE PRUEBA.</b> Tres (3) meses, conforme al artículo 10 del D.S. N° 003-97-TR.</p>';

    $parc = '<p><b>CLÁUSULA {N}.- OBJETO.</b> EL EMPLEADOR contrata a EL TRABAJADOR bajo la modalidad de <b>tiempo parcial</b> (artículo 4 del D.S. N° 003-97-TR) para desempeñar el cargo de <b>{{CARGO}}</b> en el área de {{AREA}}, con una jornada que no supera, en promedio, cuatro (4) horas diarias, iniciando el {{FECHA_INICIO}}.</p>
<p><b>CLÁUSULA {N}.- DERECHOS.</b> EL TRABAJADOR gozará de los derechos laborales compatibles con su jornada, conforme a ley (entre ellos remuneración proporcional, gratificaciones, descanso semanal y seguridad social). No le corresponde compensación por tiempo de servicios cuando su jornada sea inferior a cuatro horas diarias.</p>';

    $prac = '<h2 style="text-align:center">CONVENIO DE PRÁCTICAS PRE PROFESIONALES</h2>
<p>Conste por el presente documento el convenio de prácticas que celebran <b>{{EMPRESA}}</b>, RUC {{RUC}}, con domicilio en {{DOMICILIO_EMPRESA}}, representada por {{REP_NOMBRE}} (DNI {{REP_DNI}}), en adelante <b>LA EMPRESA</b>, y <b>{{TRABAJADOR}}</b>, identificado con {{TIPO_DOC}} N° {{NRO_DOC}}, domiciliado en {{DOMICILIO}}, en adelante <b>EL PRACTICANTE</b>, con intervención de su centro de formación, conforme a la Ley N° 28518, Ley sobre Modalidades Formativas Laborales y sus modificatorias (Ley N° 31396).</p>
<p><b>CLÁUSULA 1.- OBJETO.</b> EL PRACTICANTE realizará prácticas en el área de {{AREA}}, desempeñando actividades de {{CARGO}} vinculadas a su formación, del {{FECHA_INICIO}} al {{FECHA_FIN}}. Este convenio no genera vínculo laboral.</p>
<p><b>CLÁUSULA 2.- SUBVENCIÓN.</b> LA EMPRESA otorgará una subvención mensual de <b>S/ {{SUELDO}}</b> ({{SUELDO_LETRAS}}), no inferior a la Remuneración Mínima Vital vigente, y lo afiliará a EsSalud.</p>
<p><b>CLÁUSULA 3.- JORNADA.</b> Máximo de 6 horas diarias o 30 semanales; {{JORNADA}} horas semanales pactadas, en horario compatible con la formación académica.</p>
<p><b>CLÁUSULA 4.- DESCANSO Y OTROS DERECHOS.</b> EL PRACTICANTE gozará de quince (15) días de descanso por año de prácticas, así como de los demás derechos reconocidos por la Ley N° 28518 y sus normas modificatorias.</p>
<p><b>CLÁUSULA 5.- OBLIGACIONES.</b> Cumplir el programa de aprendizaje, las normas internas, las de seguridad y salud en el trabajo y guardar confidencialidad.</p>'
        . $pie;

    return [
        ['Contrato a plazo indeterminado', 'INDEFINIDO', 'D.S. 003-97-TR, arts. 4 y 10', $arm('CONTRATO DE TRABAJO A PLAZO INDETERMINADO', $indef)],
        ['Contrato sujeto a modalidad (plazo fijo)', 'PLAZO_FIJO', 'D.S. 003-97-TR, arts. 53-74', $arm('CONTRATO DE TRABAJO SUJETO A MODALIDAD', $fijo)],
        ['Contrato a tiempo parcial', 'TIEMPO_PARCIAL', 'D.S. 003-97-TR, art. 4', $arm('CONTRATO DE TRABAJO A TIEMPO PARCIAL', $parc)],
        ['Convenio de prácticas pre profesionales', 'PRACTICANTE', 'Ley 28518 / Ley 31396', $prac],
    ];
}
