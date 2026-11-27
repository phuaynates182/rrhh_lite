# Análisis legal, brechas y plan de proyecto

> [!IMPORTANT]
> Documento técnico de apoyo. Los valores legales cambian por norma: valide RMV, UIT, tasas AFP/tope RMA (SBS) y la tasa SCTR de su póliza antes de cada cierre. Las plantillas de contrato deben ser revisadas por un abogado laboralista.

## 1. Análisis de los archivos adjuntos frente a la legislación vigente (Perú)

### 1.1 `Planilla_de_remuneraciones_con_asiento_contable.xlsm`

| Aspecto | Excel | Norma / valor vigente | Observación | Cómo lo resuelve el sistema |
|---|---|---|---|---|
| RMV | S/ 1,130 | D.S. 015-2026-TR: **S/ 1,230 desde 01/10/2026** (verificar) | Desactualizada | Parámetro `rmv` editable |
| Asignación familiar | 10 % RMV = 113 | Ley 25129: 10 % RMV (S/ 123 con RMV 1,230) | Correcta en fórmula; monto depende de la RMV | Calculada desde parámetros; prorrateada por días |
| ONP | 13 % | D.L. 19990: 13 % | Correcta | Tabla `sistemas_pension` |
| AFP: aporte, comisión y prima | 10 % + comisión flujo + 1.37 % | SPP (SBS) | Correcto; **no aplica el tope RMA a la prima** | Prima limitada a `tope_rma` |
| EsSalud | 9 % de la remuneración | Ley 26790: 9 %, **base mínima = RMV** | Vendedores de S/ 900 + AF: aporta 91.17 cuando el mínimo legal es 101.70 | `max(afecta, RMV) × 9 %` |
| Sueldo menor a la RMV | Vendedores con básico S/ 900 | Jornada completa: no puede ser inferior a la RMV (salvo tiempo parcial) | **Contingencia de infracción** (SUNAFIL) | Contratos validan sueldo ≥ RMV; recomendación de revisar planilla |
| SCTR | 1.25 % sobre todos | Ley 26790: solo actividades de riesgo (Anexo 5 D.S. 009-97-SA), tasa según póliza | No todos los cargos (gerente, contador) están obligados | Marca por empresa y por trabajador |
| Ingresos variables | Columna «OTROS» sin detalle | Deben detallarse en boleta (D.S. 001-98-TR) | Sin distinción afecto/no afecto | Catálogo `conceptos` con marca afecto |
| Renta de 5ta categoría | No se calcula | LIR art. 75; retención mensual obligatoria | Omisión relevante en sueldos altos (p. ej. S/ 5,000) | Cálculo por proyección anual − 7 UIT |
| Días laborados / ingresos y ceses | Siempre mes completo | Remuneración proporcional | No hay prorrateo | Días base 30 automáticos y editables |
| Gratificación y CTS | No contempladas | Ley 27735, Ley 30334 (bonif. 9 %), D.S. 001-97-TR | Faltan beneficios sociales | Periodos tipo GRATIFICACION y CTS |
| Asiento contable | 62/40/41/46 | PCGE | Correcto; falta 4017 (renta 5ta) | Asiento automático con 4017 |

### 1.2 `sistema_de_planillas_y_boletas.html`

| Debilidad | Riesgo | Solución en el sistema |
|---|---|---|
| Usa una tasa AFP **promedio** de 12.5 % | Descuento incorrecto por AFP | Tasa por AFP (aporte + comisión + prima) |
| RMV 1,025 y SCTR 1.06 fijos | Valores desfasados | Parámetros en BD |
| Datos solo en memoria del navegador | Pérdida de información, sin respaldo | MySQL y legajo en servidor |
| Sin usuarios ni permisos | Cualquiera ve sueldos (Ley 29733) | Login con roles, auditoría |
| Sin conceptos variables, días ni 5ta | Boleta incompleta | Motor de cálculo completo |
| Firma siempre visible | Boleta «firmada» sin control | Firma solo tras la aprobación del Gerente |
| Dependencia de CDN (Tailwind, html2pdf) | Falla sin internet; Tailwind CDN no apto para producción | Sin dependencias; impresión a PDF nativa |

## 2. Módulos adicionales recomendados por el experto laboral

| Módulo | Base legal | Estado |
|---|---|---|
| Gratificaciones jul/dic + bonificación extraordinaria | Ley 27735, Ley 30334 | **Implementado** |
| CTS mayo/noviembre | D.S. 001-97-TR | **Implementado** |
| Vacaciones (devengo, goce, saldo, alerta de triple) | D.Leg. 713 | **Implementado** |
| Renta de 5ta categoría | TUO LIR | **Implementado (estimación)**; validar con PDT 601 |
| Asiento contable de planilla | PCGE | **Implementado** |
| Alertas de vencimiento de contratos modales | D.S. 003-97-TR art. 73 | **Implementado** (inicio) |
| Legajo digital | D.S. 001-98-TR, Ley 29733 | **Implementado** |
| Auditoría de acciones | Buenas prácticas / SUNAFIL | **Implementado** |
| Liquidación de beneficios sociales (cese) | D.S. 001-97-TR, Ley 27735, D.Leg. 713 | Backlog (sprint 5) |
| Control de asistencia, horas extra (25 %/35 %), tardanzas | D.S. 007-2002-TR | Backlog |
| Licencias y subsidios (maternidad, enfermedad, paternidad) | Ley 26790, Ley 30807 | Backlog |
| Exportación PLAME / T-Registro / AFPnet | SUNAT, SBS | Backlog |
| Utilidades (más de 20 trabajadores) | D.Leg. 892 | Backlog |
| Entrega de boleta al trabajador por correo / portal (firma digital) | D.S. 009-2011-TR | Backlog |
| Seguridad y salud en el trabajo (exámenes médicos, capacitaciones) | Ley 29783 | Backlog |
| Vida Ley (seguro desde el día 1) | D.Leg. 688 (mod. D.U. 044-2019) | Backlog (parámetro y aporte) |

## 3. Plan del proyecto (Scrum)

**Equipo:** Product Owner (gerencia/cliente) · Scrum Master/Jefe de proyecto · Desarrollador fullstack · Experto contable-laboral (define y valida reglas).
**Sprints de 2 semanas. Definición de Hecho (DoD):** código revisado, pruebas del motor en verde, validación del experto sobre un caso real, despliegue en cPanel de pruebas.

| Sprint | Objetivo | Historias principales | Estado |
|---|---|---|---|
| 0 | Base | Análisis legal, modelo de datos, arquitectura, tema BLUR, instalador | ✅ |
| 1 | Acceso y empresa | Login multiusuario, roles, empresa (logo/firma), parámetros legales | ✅ |
| 2 | Colaboradores | CRUD + legajo (foto, CV, recibo, contrato), importación Excel/CSV | ✅ |
| 3 | Planilla mensual | Motor de cálculo, cuadro editable, cálculo individual y por lote, boletas, aprobación con firma | ✅ |
| 4 | Contratos y beneficios | Plantillas por tipo, contratos individual/lote, gratificación, CTS, vacaciones, asiento | ✅ |
| 5 | Ceses y asistencia | Liquidación, asistencia, horas extra, licencias | Pendiente |
| 6 | Cumplimiento | PLAME/T-Registro/AFPnet, envío de boletas por correo, portal del trabajador | Pendiente |

### Criterios de aceptación clave (validados)
- Con los datos del Excel adjunto, el neto calculado coincide fila por fila (`tests/payroll_test.php`).
- Los conceptos variables son opcionales; si se dejan vacíos, la boleta solo incluye el sueldo fijo.
- Una boleta no muestra firma ni validez hasta que el usuario **Gerente** aprueba el periodo; el periodo aprobado queda bloqueado.
- La importación muestra una vista previa con errores por fila antes de guardar.

### Riesgos
| Riesgo | Mitigación |
|---|---|
| Cambios normativos (RMV, tasas AFP trimestrales) | Parámetros editables y checklist mensual del experto |
| Cálculo de 5ta categoría simplificado | Validación contra el PDT; ajuste manual con concepto de descuento |
| Límites del hosting (tamaño de subida, extensión zip) | `.user.ini`, CSV como alternativa |
| Datos personales sensibles | Roles, HTTPS obligatorio, respaldos cifrados |
