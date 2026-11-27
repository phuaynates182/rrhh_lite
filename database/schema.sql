-- =====================================================================
-- RRHH Blur - Esquema MySQL 5.7+/8.0 / MariaDB 10.3+  (utf8mb4)
-- Ejecutado automáticamente por install.php (o importar desde phpMyAdmin)
-- =====================================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS usuarios (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(120) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  rol ENUM('ADMIN','USUARIO') NOT NULL DEFAULT 'USUARIO',
  es_gerente TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Puede aprobar planillas y estampar firma de gerencia',
  activo TINYINT(1) NOT NULL DEFAULT 1,
  intentos TINYINT UNSIGNED NOT NULL DEFAULT 0,
  bloqueado_hasta DATETIME NULL,
  ultimo_login DATETIME NULL,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS empresa (
  id TINYINT UNSIGNED PRIMARY KEY DEFAULT 1,
  ruc VARCHAR(11) NOT NULL DEFAULT '',
  razon_social VARCHAR(200) NOT NULL DEFAULT 'MI EMPRESA S.A.C.',
  nombre_comercial VARCHAR(200) NULL,
  direccion VARCHAR(255) NULL,
  ciudad VARCHAR(80) NOT NULL DEFAULT 'Lima',
  telefono VARCHAR(40) NULL,
  email VARCHAR(150) NULL,
  rep_nombre VARCHAR(150) NULL COMMENT 'Representante legal / Gerente',
  rep_dni VARCHAR(15) NULL,
  rep_cargo VARCHAR(80) NOT NULL DEFAULT 'Gerente General',
  regimen_laboral ENUM('GENERAL','PEQUENA','MICRO') NOT NULL DEFAULT 'GENERAL',
  actividad_riesgo TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Aplica SCTR',
  logo_path VARCHAR(255) NULL,
  firma_path VARCHAR(255) NULL,
  actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Parámetros legales editables (RMV, UIT, % EsSalud, etc.)
CREATE TABLE IF NOT EXISTS parametros (
  clave VARCHAR(40) PRIMARY KEY,
  valor DECIMAL(14,4) NOT NULL,
  descripcion VARCHAR(200) NOT NULL,
  base_legal VARCHAR(200) NULL,
  actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ONP y cada AFP (aportes en %)
CREATE TABLE IF NOT EXISTS sistemas_pension (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(20) NOT NULL UNIQUE,
  nombre VARCHAR(80) NOT NULL,
  tipo ENUM('ONP','AFP') NOT NULL,
  aporte_pct DECIMAL(6,3) NOT NULL DEFAULT 0,
  comision_pct DECIMAL(6,3) NOT NULL DEFAULT 0,
  prima_pct DECIMAL(6,3) NOT NULL DEFAULT 0,
  activo TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Conceptos variables (bonos, comisiones, incentivos...). No obligatorios.
CREATE TABLE IF NOT EXISTS conceptos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(20) NOT NULL UNIQUE,
  nombre VARCHAR(80) NOT NULL,
  tipo ENUM('ING','DESC') NOT NULL DEFAULT 'ING',
  afecto TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=remunerativo (afecto a AFP/ONP/EsSalud/5ta). 0=no remunerativo (liberalidad)',
  activo TINYINT(1) NOT NULL DEFAULT 1,
  orden TINYINT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS empleados (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(20) NULL,
  tipo_doc ENUM('DNI','CE','PTP','PASAPORTE') NOT NULL DEFAULT 'DNI',
  nro_doc VARCHAR(15) NOT NULL UNIQUE,
  apellido_paterno VARCHAR(60) NOT NULL,
  apellido_materno VARCHAR(60) NULL,
  nombres VARCHAR(100) NOT NULL,
  fecha_nacimiento DATE NULL,
  sexo ENUM('M','F') NULL,
  estado_civil ENUM('SOLTERO','CASADO','CONVIVIENTE','DIVORCIADO','VIUDO') NULL,
  nacionalidad VARCHAR(40) NOT NULL DEFAULT 'Peruana',
  direccion VARCHAR(255) NULL,
  distrito VARCHAR(80) NULL,
  telefono VARCHAR(40) NULL,
  email VARCHAR(150) NULL,
  -- Datos laborales
  cargo VARCHAR(100) NOT NULL DEFAULT '',
  area VARCHAR(100) NULL,
  tipo_trabajador ENUM('INDEFINIDO','PLAZO_FIJO','TIEMPO_PARCIAL','PRACTICANTE') NOT NULL DEFAULT 'INDEFINIDO',
  regimen ENUM('GENERAL','PEQUENA','MICRO') NOT NULL DEFAULT 'GENERAL',
  jornada_horas TINYINT UNSIGNED NOT NULL DEFAULT 48,
  fecha_ingreso DATE NOT NULL,
  fecha_cese DATE NULL,
  estado ENUM('ACTIVO','CESADO','SUSPENDIDO') NOT NULL DEFAULT 'ACTIVO',
  -- Remuneración fija
  sueldo_basico DECIMAL(12,2) NOT NULL DEFAULT 0,
  asignacion_familiar TINYINT(1) NOT NULL DEFAULT 0,
  num_hijos TINYINT UNSIGNED NOT NULL DEFAULT 0,
  -- Seguridad social
  sistema_pension_id INT UNSIGNED NULL,
  cuspp VARCHAR(20) NULL,
  essalud TINYINT(1) NOT NULL DEFAULT 1,
  sctr TINYINT(1) NOT NULL DEFAULT 0,
  quinta_categoria TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Retener 5ta categoria',
  -- Pago
  banco VARCHAR(60) NULL,
  nro_cuenta VARCHAR(30) NULL,
  cci VARCHAR(25) NULL,
  banco_cts VARCHAR(60) NULL,
  cuenta_cts VARCHAR(30) NULL,
  creado_por INT UNSIGNED NULL,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY (estado), KEY (apellido_paterno),
  CONSTRAINT fk_emp_sp FOREIGN KEY (sistema_pension_id) REFERENCES sistemas_pension(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Legajo digital: CV, foto, recibo de domicilio, contrato firmado, DNI, otros
CREATE TABLE IF NOT EXISTS empleado_documentos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  empleado_id INT UNSIGNED NOT NULL,
  tipo ENUM('FOTO','CV','RECIBO_DOMICILIO','CONTRATO_FIRMADO','DNI','OTRO') NOT NULL,
  nombre_original VARCHAR(255) NOT NULL,
  ruta VARCHAR(255) NOT NULL,
  mime VARCHAR(100) NOT NULL,
  tamano INT UNSIGNED NOT NULL DEFAULT 0,
  contrato_id INT UNSIGNED NULL,
  subido_por INT UNSIGNED NULL,
  subido_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY (empleado_id, tipo),
  CONSTRAINT fk_doc_emp FOREIGN KEY (empleado_id) REFERENCES empleados(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Periodos de planilla: MENSUAL, GRATIFICACION (jul/dic), CTS (may/nov)
CREATE TABLE IF NOT EXISTS periodos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  anio SMALLINT UNSIGNED NOT NULL,
  mes TINYINT UNSIGNED NOT NULL,
  tipo ENUM('MENSUAL','GRATIFICACION','CTS') NOT NULL DEFAULT 'MENSUAL',
  descripcion VARCHAR(150) NULL,
  fecha_pago DATE NULL,
  estado ENUM('BORRADOR','PENDIENTE','APROBADO') NOT NULL DEFAULT 'BORRADOR',
  creado_por INT UNSIGNED NULL,
  enviado_por INT UNSIGNED NULL,
  aprobado_por INT UNSIGNED NULL,
  aprobado_en DATETIME NULL,
  firma_path VARCHAR(255) NULL COMMENT 'Copia de la firma vigente al aprobar',
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_periodo (anio, mes, tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS boletas (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  periodo_id INT UNSIGNED NOT NULL,
  empleado_id INT UNSIGNED NOT NULL,
  dias_laborados TINYINT UNSIGNED NOT NULL DEFAULT 30,
  remuneracion_afecta DECIMAL(12,2) NOT NULL DEFAULT 0,
  bruto DECIMAL(12,2) NOT NULL DEFAULT 0,
  total_descuentos DECIMAL(12,2) NOT NULL DEFAULT 0,
  neto DECIMAL(12,2) NOT NULL DEFAULT 0,
  total_aportes DECIMAL(12,2) NOT NULL DEFAULT 0,
  snap TEXT NULL COMMENT 'JSON con datos del trabajador al generar',
  codigo_verif VARCHAR(20) NULL,
  generado_por INT UNSIGNED NULL,
  actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_bol (periodo_id, empleado_id),
  CONSTRAINT fk_bol_per FOREIGN KEY (periodo_id) REFERENCES periodos(id) ON DELETE CASCADE,
  CONSTRAINT fk_bol_emp FOREIGN KEY (empleado_id) REFERENCES empleados(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Detalle de la boleta: ING (ingresos), DESC (descuentos), APORTE (empleador)
CREATE TABLE IF NOT EXISTS boleta_lineas (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  boleta_id INT UNSIGNED NOT NULL,
  tipo ENUM('ING','DESC','APORTE') NOT NULL,
  codigo VARCHAR(20) NOT NULL,
  descripcion VARCHAR(120) NOT NULL,
  monto DECIMAL(12,2) NOT NULL DEFAULT 0,
  manual TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = digitado por el usuario (variable)',
  orden SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  KEY (boleta_id),
  CONSTRAINT fk_lin_bol FOREIGN KEY (boleta_id) REFERENCES boletas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contrato_plantillas (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(120) NOT NULL,
  tipo_trabajador ENUM('INDEFINIDO','PLAZO_FIJO','TIEMPO_PARCIAL','PRACTICANTE') NOT NULL,
  base_legal VARCHAR(200) NULL,
  cuerpo MEDIUMTEXT NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contratos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  empleado_id INT UNSIGNED NOT NULL,
  plantilla_id INT UNSIGNED NULL,
  lote VARCHAR(20) NULL,
  tipo_trabajador VARCHAR(20) NOT NULL,
  fecha_inicio DATE NOT NULL,
  fecha_fin DATE NULL,
  cargo VARCHAR(100) NULL,
  sueldo DECIMAL(12,2) NOT NULL DEFAULT 0,
  causa_objetiva TEXT NULL,
  con_firma TINYINT(1) NOT NULL DEFAULT 0,
  cuerpo MEDIUMTEXT NOT NULL,
  estado ENUM('BORRADOR','EMITIDO','FIRMADO') NOT NULL DEFAULT 'BORRADOR',
  creado_por INT UNSIGNED NULL,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY (empleado_id), KEY (fecha_fin),
  CONSTRAINT fk_con_emp FOREIGN KEY (empleado_id) REFERENCES empleados(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vacaciones (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  empleado_id INT UNSIGNED NOT NULL,
  fecha_inicio DATE NOT NULL,
  fecha_fin DATE NOT NULL,
  dias SMALLINT UNSIGNED NOT NULL,
  observacion VARCHAR(200) NULL,
  anulada TINYINT(1) NOT NULL DEFAULT 0,
  registrado_por INT UNSIGNED NULL,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY (empleado_id),
  CONSTRAINT fk_vac_emp FOREIGN KEY (empleado_id) REFERENCES empleados(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auditoria (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT UNSIGNED NULL,
  usuario_nombre VARCHAR(120) NULL,
  accion VARCHAR(60) NOT NULL,
  entidad VARCHAR(40) NULL,
  entidad_id INT UNSIGNED NULL,
  detalle VARCHAR(500) NULL,
  ip VARCHAR(45) NULL,
  fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY (fecha), KEY (entidad, entidad_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ===================== DATOS INICIALES =====================
INSERT IGNORE INTO empresa (id) VALUES (1);

INSERT IGNORE INTO parametros (clave, valor, descripcion, base_legal) VALUES
('rmv', 1230, 'Remuneración Mínima Vital (S/) - VERIFICAR vigencia en el MTPE', 'D.S. 015-2026-TR (desde 01/10/2026)'),
('asig_fam_pct', 10, 'Asignación familiar (% de la RMV)', 'Ley 25129'),
('essalud_pct', 9, 'Aporte EsSalud (%) a cargo del empleador', 'Ley 26790'),
('sctr_pct', 1.25, 'Tasa SCTR (%) - según póliza contratada', 'Ley 26790 / D.S. 003-98-SA'),
('uit', 5500, 'Unidad Impositiva Tributaria (S/)', 'D.S. 301-2025-EF'),
('tope_rma', 12732.70, 'Remuneración Máxima Asegurable AFP para la prima (S/) - VERIFICAR SBS (trimestral)', 'SBS'),
('quinta_uit_deduccion', 7, 'Deducción anual de 5ta categoría (UIT)', 'Ley del Impuesto a la Renta Art. 46'),
('bonif_extra_pct', 9, 'Bonificación extraordinaria sobre gratificación (%)', 'Ley 30334');

INSERT IGNORE INTO sistemas_pension (codigo, nombre, tipo, aporte_pct, comision_pct, prima_pct) VALUES
('ONP', 'ONP / SNP (D.L. 19990)', 'ONP', 13, 0, 0),
('PRIMA', 'AFP Prima', 'AFP', 10, 1.600, 1.370),
('HABITAT', 'AFP Hábitat', 'AFP', 10, 1.470, 1.370),
('INTEGRA', 'AFP Integra', 'AFP', 10, 1.550, 1.370),
('PROFUTURO', 'AFP Profuturo', 'AFP', 10, 1.690, 1.370);

INSERT IGNORE INTO conceptos (codigo, nombre, tipo, afecto, orden) VALUES
('BONO', 'Bonos', 'ING', 1, 1),
('COMISION', 'Comisiones', 'ING', 1, 2),
('INCENTIVO', 'Incentivos', 'ING', 1, 3),
('OTRO_ING', 'Otros ingresos (libre disposición)', 'ING', 0, 4),
('DESC_ADIC', 'Otros descuentos (adelantos, préstamos)', 'DESC', 0, 5);
