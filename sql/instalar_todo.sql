-- INSTALACIÓN COMPLETA de la base aqua_sentinel en un solo paso:
--   estructura del equipo (aqua_sentinel.sql) + colonias y puntos seguros de ejemplo (datos_ejemplo.sql).
--
-- Cómo usarlo: en phpMyAdmin entra a la base aqua_sentinel y, desde ahí, importa este archivo.
-- OJO: BORRA todas las tablas de esa base y las vuelve a crear. Se pierde lo que tengan guardado.

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS alertas, evaluaciones_riesgo, mediciones, puntos_monitoreo, puntos_seguros,
                     reportes, simulaciones, rol_permiso, permisos, usuarios, roles, colonias;
SET FOREIGN_KEY_CHECKS = 1;

-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost:3306
-- Tiempo de generación: 11-11-2026 a las 21:45:40
-- Versión del servidor: 8.0.30
-- Versión de PHP: 8.1.10

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `aqua_sentinel`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `alertas`
--

CREATE TABLE `alertas` (
  `id_alerta` int NOT NULL,
  `id_colonia` int NOT NULL,
  `id_evaluacion` int DEFAULT NULL,
  `id_usuario_creador` int DEFAULT NULL,
  `titulo` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mensaje` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` enum('inundacion','lluvia','fuga','falta_agua','prevencion','otro') COLLATE utf8mb4_unicode_ci NOT NULL,
  `nivel` enum('informativa','preventiva','urgente','emergencia') COLLATE utf8mb4_unicode_ci NOT NULL,
  `estado` enum('activa','finalizada','cancelada') COLLATE utf8mb4_unicode_ci DEFAULT 'activa',
  `fecha_inicio` datetime NOT NULL,
  `fecha_fin` datetime DEFAULT NULL,
  `fecha_registro` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `colonias`
--

CREATE TABLE `colonias` (
  `id_colonia` int NOT NULL,
  `nombre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `municipio` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `estado` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'Veracruz',
  `codigo_postal` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `latitud` decimal(10,7) DEFAULT NULL,
  `longitud` decimal(10,7) DEFAULT NULL,
  `nivel_vulnerabilidad` enum('bajo','medio','alto','muy_alto') COLLATE utf8mb4_unicode_ci DEFAULT 'medio',
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `estado_registro` enum('activa','inactiva') COLLATE utf8mb4_unicode_ci DEFAULT 'activa',
  `fecha_registro` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `evaluaciones_riesgo`
--

CREATE TABLE `evaluaciones_riesgo` (
  `id_evaluacion` int NOT NULL,
  `id_colonia` int NOT NULL,
  `id_simulacion` int DEFAULT NULL,
  `modelo_ia` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `probabilidad` decimal(5,2) DEFAULT NULL,
  `nivel_riesgo` enum('bajo','medio','alto','critico') COLLATE utf8mb4_unicode_ci NOT NULL,
  `lluvia_acumulada` decimal(10,2) DEFAULT NULL,
  `nivel_agua` decimal(10,2) DEFAULT NULL,
  `cantidad_reportes` int DEFAULT '0',
  `explicacion` text COLLATE utf8mb4_unicode_ci,
  `fecha_evaluacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `mediciones`
--

CREATE TABLE `mediciones` (
  `id_medicion` bigint NOT NULL,
  `id_punto` int NOT NULL,
  `tipo` enum('precipitacion','nivel_agua','temperatura','humedad') COLLATE utf8mb4_unicode_ci NOT NULL,
  `valor` decimal(12,3) NOT NULL,
  `unidad` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fuente` enum('sensor','api','manual') COLLATE utf8mb4_unicode_ci DEFAULT 'sensor',
  `fecha_medicion` datetime NOT NULL,
  `fecha_registro` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `permisos`
--

CREATE TABLE `permisos` (
  `id_permiso` int NOT NULL,
  `nombre` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `permisos`
--

INSERT INTO `permisos` (`id_permiso`, `nombre`, `descripcion`) VALUES
(1, 'dashboard', 'Consultar panel principal'),
(2, 'usuarios', 'Administrar usuarios'),
(3, 'colonias', 'Gestionar colonias'),
(4, 'monitoreo', 'Consultar mediciones'),
(5, 'reportes', 'Gestionar reportes'),
(6, 'simulaciones', 'Ejecutar simulaciones'),
(7, 'riesgos', 'Consultar evaluaciones de riesgo'),
(8, 'alertas', 'Gestionar alertas'),
(9, 'puntos_seguros', 'Gestionar refugios y puntos seguros');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `puntos_monitoreo`
--

CREATE TABLE `puntos_monitoreo` (
  `id_punto` int NOT NULL,
  `id_colonia` int NOT NULL,
  `nombre` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` enum('pluviometro','nivel_agua','estacion_meteorologica','otro') COLLATE utf8mb4_unicode_ci NOT NULL,
  `latitud` decimal(10,7) NOT NULL,
  `longitud` decimal(10,7) NOT NULL,
  `ubicacion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado` enum('activo','inactivo','mantenimiento') COLLATE utf8mb4_unicode_ci DEFAULT 'activo',
  `fecha_instalacion` date DEFAULT NULL,
  `fecha_registro` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `puntos_seguros`
--

CREATE TABLE `puntos_seguros` (
  `id_punto_seguro` int NOT NULL,
  `id_colonia` int NOT NULL,
  `nombre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` enum('refugio','centro_salud','punto_reunion','otro') COLLATE utf8mb4_unicode_ci DEFAULT 'refugio',
  `direccion` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `latitud` decimal(10,7) NOT NULL,
  `longitud` decimal(10,7) NOT NULL,
  `capacidad` int DEFAULT NULL,
  `telefono` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado` enum('disponible','ocupado','cerrado') COLLATE utf8mb4_unicode_ci DEFAULT 'disponible',
  `fecha_registro` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `reportes`
--

CREATE TABLE `reportes` (
  `id_reporte` int NOT NULL,
  `id_usuario` int DEFAULT NULL,
  `id_colonia` int NOT NULL,
  `tipo` enum('inundacion','encharcamiento','fuga','falta_agua','alcantarilla','otro') COLLATE utf8mb4_unicode_ci NOT NULL,
  `titulo` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `latitud` decimal(10,7) DEFAULT NULL,
  `longitud` decimal(10,7) DEFAULT NULL,
  `fotografia` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nivel_urgencia` enum('bajo','medio','alto','critico') COLLATE utf8mb4_unicode_ci DEFAULT 'medio',
  `estado` enum('pendiente','en_revision','atendido','descartado') COLLATE utf8mb4_unicode_ci DEFAULT 'pendiente',
  `fecha_reporte` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_atencion` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `roles`
--

CREATE TABLE `roles` (
  `id_rol` int NOT NULL,
  `nombre` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_registro` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `roles`
--

INSERT INTO `roles` (`id_rol`, `nombre`, `descripcion`, `fecha_registro`) VALUES
(1, 'Administrador', 'Control total del sistema', '2026-11-11 20:44:44'),
(2, 'Proteccion Civil', 'Monitoreo de riesgos y gestión de alertas', '2026-11-11 20:44:44'),
(3, 'Ciudadano', 'Registro y consulta de reportes', '2026-11-11 20:44:44'),
(4, 'Operador de Agua', 'Gestión de fugas y problemas de suministro', '2026-11-11 20:44:44');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `rol_permiso`
--

CREATE TABLE `rol_permiso` (
  `id_rol` int NOT NULL,
  `id_permiso` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `rol_permiso`
--

INSERT INTO `rol_permiso` (`id_rol`, `id_permiso`) VALUES
(1, 1),
(2, 1),
(3, 1),
(4, 1),
(1, 2),
(1, 3),
(2, 3),
(4, 3),
(1, 4),
(2, 4),
(1, 5),
(2, 5),
(3, 5),
(4, 5),
(1, 6),
(2, 6),
(1, 7),
(2, 7),
(1, 8),
(2, 8),
(3, 8),
(4, 8),
(1, 9),
(2, 9),
(3, 9);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `simulaciones`
--

CREATE TABLE `simulaciones` (
  `id_simulacion` int NOT NULL,
  `id_usuario` int DEFAULT NULL,
  `id_colonia` int NOT NULL,
  `nombre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `lluvia_estimada` decimal(10,2) NOT NULL,
  `duracion_horas` decimal(6,2) NOT NULL,
  `nivel_agua_estimado` decimal(10,2) DEFAULT NULL,
  `resultado_riesgo` enum('bajo','medio','alto','critico') COLLATE utf8mb4_unicode_ci NOT NULL,
  `resultado_analisis` text COLLATE utf8mb4_unicode_ci,
  `fecha_simulacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id_usuario` int NOT NULL,
  `id_rol` int NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `apellido` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `correo` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `telefono` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contrasena` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `estado` enum('activo','inactivo','bloqueado') COLLATE utf8mb4_unicode_ci DEFAULT 'activo',
  `fecha_registro` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `ultimo_acceso` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `alertas`
--
ALTER TABLE `alertas`
  ADD PRIMARY KEY (`id_alerta`),
  ADD KEY `id_colonia` (`id_colonia`),
  ADD KEY `id_evaluacion` (`id_evaluacion`),
  ADD KEY `id_usuario_creador` (`id_usuario_creador`),
  ADD KEY `idx_alertas_estado` (`estado`),
  ADD KEY `idx_alertas_fecha` (`fecha_inicio`);

--
-- Indices de la tabla `colonias`
--
ALTER TABLE `colonias`
  ADD PRIMARY KEY (`id_colonia`),
  ADD KEY `idx_municipio` (`municipio`);

--
-- Indices de la tabla `evaluaciones_riesgo`
--
ALTER TABLE `evaluaciones_riesgo`
  ADD PRIMARY KEY (`id_evaluacion`),
  ADD KEY `id_colonia` (`id_colonia`),
  ADD KEY `id_simulacion` (`id_simulacion`),
  ADD KEY `idx_riesgo_fecha` (`fecha_evaluacion`),
  ADD KEY `idx_riesgo_nivel` (`nivel_riesgo`);

--
-- Indices de la tabla `mediciones`
--
ALTER TABLE `mediciones`
  ADD PRIMARY KEY (`id_medicion`),
  ADD KEY `id_punto` (`id_punto`),
  ADD KEY `idx_fecha_medicion` (`fecha_medicion`),
  ADD KEY `idx_tipo_medicion` (`tipo`);

--
-- Indices de la tabla `permisos`
--
ALTER TABLE `permisos`
  ADD PRIMARY KEY (`id_permiso`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indices de la tabla `puntos_monitoreo`
--
ALTER TABLE `puntos_monitoreo`
  ADD PRIMARY KEY (`id_punto`),
  ADD KEY `id_colonia` (`id_colonia`);

--
-- Indices de la tabla `puntos_seguros`
--
ALTER TABLE `puntos_seguros`
  ADD PRIMARY KEY (`id_punto_seguro`),
  ADD KEY `id_colonia` (`id_colonia`);

--
-- Indices de la tabla `reportes`
--
ALTER TABLE `reportes`
  ADD PRIMARY KEY (`id_reporte`),
  ADD KEY `id_usuario` (`id_usuario`),
  ADD KEY `id_colonia` (`id_colonia`),
  ADD KEY `idx_reporte_tipo` (`tipo`),
  ADD KEY `idx_reporte_estado` (`estado`);

--
-- Indices de la tabla `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id_rol`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indices de la tabla `rol_permiso`
--
ALTER TABLE `rol_permiso`
  ADD PRIMARY KEY (`id_rol`,`id_permiso`),
  ADD KEY `id_permiso` (`id_permiso`);

--
-- Indices de la tabla `simulaciones`
--
ALTER TABLE `simulaciones`
  ADD PRIMARY KEY (`id_simulacion`),
  ADD KEY `id_usuario` (`id_usuario`),
  ADD KEY `id_colonia` (`id_colonia`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `correo` (`correo`),
  ADD KEY `id_rol` (`id_rol`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `alertas`
--
ALTER TABLE `alertas`
  MODIFY `id_alerta` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `colonias`
--
ALTER TABLE `colonias`
  MODIFY `id_colonia` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `evaluaciones_riesgo`
--
ALTER TABLE `evaluaciones_riesgo`
  MODIFY `id_evaluacion` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `mediciones`
--
ALTER TABLE `mediciones`
  MODIFY `id_medicion` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `permisos`
--
ALTER TABLE `permisos`
  MODIFY `id_permiso` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `puntos_monitoreo`
--
ALTER TABLE `puntos_monitoreo`
  MODIFY `id_punto` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `puntos_seguros`
--
ALTER TABLE `puntos_seguros`
  MODIFY `id_punto_seguro` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `reportes`
--
ALTER TABLE `reportes`
  MODIFY `id_reporte` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `roles`
--
ALTER TABLE `roles`
  MODIFY `id_rol` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `simulaciones`
--
ALTER TABLE `simulaciones`
  MODIFY `id_simulacion` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id_usuario` int NOT NULL AUTO_INCREMENT;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `alertas`
--
ALTER TABLE `alertas`
  ADD CONSTRAINT `alertas_ibfk_1` FOREIGN KEY (`id_colonia`) REFERENCES `colonias` (`id_colonia`),
  ADD CONSTRAINT `alertas_ibfk_2` FOREIGN KEY (`id_evaluacion`) REFERENCES `evaluaciones_riesgo` (`id_evaluacion`) ON DELETE SET NULL,
  ADD CONSTRAINT `alertas_ibfk_3` FOREIGN KEY (`id_usuario_creador`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL;

--
-- Filtros para la tabla `evaluaciones_riesgo`
--
ALTER TABLE `evaluaciones_riesgo`
  ADD CONSTRAINT `evaluaciones_riesgo_ibfk_1` FOREIGN KEY (`id_colonia`) REFERENCES `colonias` (`id_colonia`),
  ADD CONSTRAINT `evaluaciones_riesgo_ibfk_2` FOREIGN KEY (`id_simulacion`) REFERENCES `simulaciones` (`id_simulacion`) ON DELETE SET NULL;

--
-- Filtros para la tabla `mediciones`
--
ALTER TABLE `mediciones`
  ADD CONSTRAINT `mediciones_ibfk_1` FOREIGN KEY (`id_punto`) REFERENCES `puntos_monitoreo` (`id_punto`);

--
-- Filtros para la tabla `puntos_monitoreo`
--
ALTER TABLE `puntos_monitoreo`
  ADD CONSTRAINT `puntos_monitoreo_ibfk_1` FOREIGN KEY (`id_colonia`) REFERENCES `colonias` (`id_colonia`);

--
-- Filtros para la tabla `puntos_seguros`
--
ALTER TABLE `puntos_seguros`
  ADD CONSTRAINT `puntos_seguros_ibfk_1` FOREIGN KEY (`id_colonia`) REFERENCES `colonias` (`id_colonia`);

--
-- Filtros para la tabla `reportes`
--
ALTER TABLE `reportes`
  ADD CONSTRAINT `reportes_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL,
  ADD CONSTRAINT `reportes_ibfk_2` FOREIGN KEY (`id_colonia`) REFERENCES `colonias` (`id_colonia`);

--
-- Filtros para la tabla `rol_permiso`
--
ALTER TABLE `rol_permiso`
  ADD CONSTRAINT `rol_permiso_ibfk_1` FOREIGN KEY (`id_rol`) REFERENCES `roles` (`id_rol`) ON DELETE CASCADE,
  ADD CONSTRAINT `rol_permiso_ibfk_2` FOREIGN KEY (`id_permiso`) REFERENCES `permisos` (`id_permiso`) ON DELETE CASCADE;

--
-- Filtros para la tabla `simulaciones`
--
ALTER TABLE `simulaciones`
  ADD CONSTRAINT `simulaciones_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL,
  ADD CONSTRAINT `simulaciones_ibfk_2` FOREIGN KEY (`id_colonia`) REFERENCES `colonias` (`id_colonia`);

--
-- Filtros para la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `usuarios_ibfk_1` FOREIGN KEY (`id_rol`) REFERENCES `roles` (`id_rol`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

-- Datos de ejemplo para la base aqua_sentinel (colonias y puntos seguros).
-- Importar UNA sola vez, DESPUÉS de aqua_sentinel.sql y estando DENTRO de la base aqua_sentinel.
--
-- DATOS SINTÉTICOS: las coordenadas son APROXIMADAS y la vulnerabilidad es INVENTADA.
-- Verifiquen cada coordenada en openstreetmap.org y declárenlo en el README.

INSERT INTO colonias (nombre, municipio, latitud, longitud, nivel_vulnerabilidad, descripcion) VALUES
('Centro',             'Veracruz',         19.2000, -96.1380, 'medio',    'Dato de ejemplo'),
('Flores Magón',       'Veracruz',         19.1900, -96.1300, 'bajo',     'Dato de ejemplo'),
('Ignacio Zaragoza',   'Veracruz',         19.1830, -96.1330, 'medio',    'Dato de ejemplo'),
('Formando Hogar',     'Veracruz',         19.1850, -96.1450, 'alto',     'Dato de ejemplo'),
('El Coyol',           'Veracruz',         19.1640, -96.1500, 'alto',     'Dato de ejemplo'),
('Floresta',           'Veracruz',         19.1550, -96.1350, 'muy_alto', 'Dato de ejemplo'),
('Las Bajadas',        'Veracruz',         19.1560, -96.1900, 'alto',     'Dato de ejemplo'),
('Lomas de Río Medio', 'Veracruz',         19.2100, -96.1900, 'medio',    'Dato de ejemplo'),
('Playa Linda',        'Veracruz',         19.2100, -96.1600, 'bajo',     'Dato de ejemplo'),
('Puente Moreno',      'Medellín de Bravo', 19.1100, -96.1600, 'muy_alto', 'Dato de ejemplo');

-- PUNTOS SEGUROS DE EJEMPLO: no son refugios reales. Antes de la demo cámbienlos por
-- lugares tomados de una fuente pública de Protección Civil, o preséntenlos como ejemplo.
INSERT INTO puntos_seguros (id_colonia, nombre, tipo, direccion, latitud, longitud, capacidad, estado) VALUES
((SELECT id_colonia FROM colonias WHERE nombre = 'Centro'),             'Punto seguro de ejemplo A', 'refugio',       'Dirección de ejemplo, zona Centro',        19.1960, -96.1420, 150, 'disponible'),
((SELECT id_colonia FROM colonias WHERE nombre = 'Ignacio Zaragoza'),   'Punto seguro de ejemplo B', 'punto_reunion', 'Dirección de ejemplo, zona Zaragoza',      19.1790, -96.1280, 200, 'disponible'),
((SELECT id_colonia FROM colonias WHERE nombre = 'El Coyol'),           'Punto seguro de ejemplo C', 'centro_salud',  'Dirección de ejemplo, zona Coyol',         19.1700, -96.1560, 120, 'disponible'),
((SELECT id_colonia FROM colonias WHERE nombre = 'Lomas de Río Medio'), 'Punto seguro de ejemplo D', 'refugio',       'Dirección de ejemplo, zona Río Medio',     19.2040, -96.1820, 180, 'disponible'),
((SELECT id_colonia FROM colonias WHERE nombre = 'Puente Moreno'),      'Punto seguro de ejemplo E', 'refugio',       'Dirección de ejemplo, zona Puente Moreno', 19.1180, -96.1520, 100, 'cerrado');

-- Alertas por correo: colonia de cada usuario (lo mismo que sql/correo.sql).

ALTER TABLE usuarios
  ADD COLUMN id_colonia INT NULL AFTER id_rol,
  ADD CONSTRAINT usuarios_ibfk_colonia FOREIGN KEY (id_colonia) REFERENCES colonias (id_colonia) ON DELETE SET NULL;
