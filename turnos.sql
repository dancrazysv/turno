-- --------------------------------------------------------
-- Host:                         127.0.0.1
-- Versión del servidor:         10.4.24-MariaDB - mariadb.org binary distribution
-- SO del servidor:              Win64
-- HeidiSQL Versión:             12.5.0.6677
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


-- Volcando estructura de base de datos para turnero_db
CREATE DATABASE IF NOT EXISTS `turnero_db` /*!40100 DEFAULT CHARACTER SET utf8mb4 */;
USE `turnero_db`;

-- Volcando estructura para tabla turnero_db.areas
CREATE TABLE IF NOT EXISTS `areas` (
  `id_area` int(11) NOT NULL AUTO_INCREMENT,
  `nombre_area` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `termino_ubicacion` varchar(50) NOT NULL DEFAULT 'Escritorio' COMMENT 'Ej: Escritorio, Módulo, Puerta, Ventanilla',
  PRIMARY KEY (`id_area`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4;

-- Volcando datos para la tabla turnero_db.areas: ~3 rows (aproximadamente)
DELETE FROM `areas`;
INSERT INTO `areas` (`id_area`, `nombre_area`, `descripcion`, `activo`, `termino_ubicacion`) VALUES
	(1, 'Área Jurídica', NULL, 1, 'Puerta'),
	(2, 'Trámites Registrales', NULL, 1, 'Ventanilla'),
	(3, 'Exterior', NULL, 1, 'Modulo');

-- Volcando estructura para tabla turnero_db.tipos_tramite
CREATE TABLE IF NOT EXISTS `tipos_tramite` (
  `id_tramite` int(11) NOT NULL AUTO_INCREMENT,
  `id_area` int(11) NOT NULL COMMENT 'FK al área principal',
  `nombre_tramite` varchar(100) NOT NULL COMMENT 'Ej: Renovación de Licencia, Pago de Multas',
  `prefijo_letra` char(1) NOT NULL COMMENT 'Ej: R, M, S',
  `ultimo_turno_diario` int(11) NOT NULL DEFAULT 0,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `prioridad` int(11) NOT NULL DEFAULT 0 COMMENT 'Nivel de prioridad (0=Normal, 1=Alta)',
  PRIMARY KEY (`id_tramite`),
  UNIQUE KEY `prefijo_letra` (`prefijo_letra`),
  KEY `id_area` (`id_area`),
  CONSTRAINT `tipos_tramite_ibfk_1` FOREIGN KEY (`id_area`) REFERENCES `areas` (`id_area`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COMMENT='Define sub-colas con prefijos individuales.';

-- Volcando datos para la tabla turnero_db.tipos_tramite: ~5 rows (aproximadamente)
DELETE FROM `tipos_tramite`;
INSERT INTO `tipos_tramite` (`id_tramite`, `id_area`, `nombre_tramite`, `prefijo_letra`, `ultimo_turno_diario`, `activo`, `prioridad`) VALUES
	(1, 2, 'Inscripciones', 'H', 1, 1, 0),
	(2, 1, 'Rectificaciones', 'R', 2, 1, 0),
	(3, 2, 'Carnet de Minoridad', 'C', 1, 1, 1),
	(4, 3, 'Público Exterior', 'P', 1, 1, 0),
	(5, 2, 'Prioritario', 'B', 1, 1, 1);

-- Volcando estructura para tabla turnero_db.turnos
CREATE TABLE IF NOT EXISTS `turnos` (
  `id_turno` int(11) NOT NULL AUTO_INCREMENT,
  `id_area` int(11) NOT NULL,
  `id_tramite` int(11) DEFAULT NULL,
  `numero_correlativo` int(11) NOT NULL,
  `numero_completo` varchar(10) NOT NULL,
  `escritorio_llamado` int(11) DEFAULT NULL,
  `id_usuario_atendio` int(11) DEFAULT NULL,
  `estado` enum('ESPERA','LLAMADO','ATENDIDO','PERDIDO') NOT NULL DEFAULT 'ESPERA',
  `hora_generacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `hora_llamada` timestamp NULL DEFAULT NULL,
  `hora_atencion_inicio` timestamp NULL DEFAULT NULL,
  `hora_atencion_fin` timestamp NULL DEFAULT NULL,
  `anunciado_display` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_turno`),
  KEY `id_area` (`id_area`),
  KEY `id_tramite` (`id_tramite`),
  KEY `id_usuario_atendio` (`id_usuario_atendio`),
  CONSTRAINT `turnos_ibfk_1` FOREIGN KEY (`id_area`) REFERENCES `areas` (`id_area`) ON UPDATE CASCADE,
  CONSTRAINT `turnos_ibfk_2` FOREIGN KEY (`id_tramite`) REFERENCES `tipos_tramite` (`id_tramite`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `turnos_ibfk_3` FOREIGN KEY (`id_usuario_atendio`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `turnos_ibfk_4` FOREIGN KEY (`id_usuario_atendio`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=108 DEFAULT CHARSET=utf8mb4;

-- Volcando datos para la tabla turnero_db.turnos: ~74 rows (aproximadamente)
DELETE FROM `turnos`;
INSERT INTO `turnos` (`id_turno`, `id_area`, `id_tramite`, `numero_correlativo`, `numero_completo`, `escritorio_llamado`, `id_usuario_atendio`, `estado`, `hora_generacion`, `hora_llamada`, `hora_atencion_inicio`, `hora_atencion_fin`, `anunciado_display`) VALUES
	(1, 2, 3, 1, 'C-001', 2, 3, 'ATENDIDO', '2025-10-10 21:19:42', '2025-10-11 06:11:35', NULL, '2025-10-11 06:11:49', 0),
	(2, 2, 1, 1, 'H-001', 2, 3, 'ATENDIDO', '2025-10-10 21:19:47', '2025-10-11 05:41:06', NULL, '2025-10-11 05:41:20', 0),
	(3, 2, 3, 2, 'C-002', 2, 3, 'ATENDIDO', '2025-10-10 21:19:51', '2025-10-11 06:11:52', NULL, '2025-10-11 06:11:55', 0),
	(4, 2, 1, 2, 'H-002', 2, 3, 'ATENDIDO', '2025-10-10 21:19:54', '2025-10-11 05:41:23', NULL, '2025-10-11 05:41:25', 0),
	(5, 1, 2, 1, 'O-001', 1, 2, 'ATENDIDO', '2025-10-10 21:19:59', '2025-10-11 06:12:05', NULL, '2025-10-11 06:12:13', 0),
	(6, 1, 2, 2, 'O-002', 1, 2, 'ATENDIDO', '2025-10-10 21:20:02', '2025-10-11 06:12:14', NULL, '2025-10-11 06:12:20', 0),
	(16, 1, 2, 1, 'R-001', 1, 2, 'PERDIDO', '2025-10-11 17:26:40', '2025-10-12 01:30:15', NULL, '2025-10-12 01:30:17', 0),
	(17, 2, 1, 1, 'H-001', 2, 3, 'ATENDIDO', '2025-10-11 17:26:46', '2025-10-12 01:29:35', NULL, '2025-10-12 01:29:39', 0),
	(18, 2, 3, 1, 'C-001', 2, 3, 'ATENDIDO', '2025-10-11 17:26:49', '2025-10-12 01:29:42', NULL, '2025-10-12 01:29:45', 0),
	(19, 3, 4, 1, 'P-001', 4, 5, 'ATENDIDO', '2025-10-11 17:26:53', '2025-10-12 01:30:04', NULL, '2025-10-12 01:30:07', 0),
	(20, 1, 2, 1, 'R-001', 1, 2, 'ATENDIDO', '2025-10-12 17:31:37', '2025-10-13 01:38:46', NULL, '2025-10-13 01:38:47', 0),
	(21, 2, 3, 1, 'C-001', 2, 3, 'ATENDIDO', '2025-10-12 17:31:41', '2025-10-13 01:38:32', NULL, '2025-10-13 01:38:33', 0),
	(22, 2, 1, 1, 'H-001', 2, 3, 'ATENDIDO', '2025-10-12 17:31:45', '2025-10-13 01:38:35', NULL, '2025-10-13 01:38:36', 0),
	(23, 3, 4, 1, 'P-001', 4, 5, 'ATENDIDO', '2025-10-12 17:31:48', '2025-10-13 01:38:20', NULL, '2025-10-13 01:38:22', 0),
	(24, 1, 2, 1, 'R-001', 1, 2, 'ATENDIDO', '2025-10-13 17:49:56', '2025-10-14 01:53:05', NULL, '2025-10-14 01:57:07', 0),
	(25, 2, 1, 1, 'H-001', 2, 3, 'ATENDIDO', '2025-10-13 17:50:00', '2025-10-14 01:53:17', NULL, '2025-10-14 01:53:20', 0),
	(26, 2, 3, 1, 'C-001', 2, 3, 'ATENDIDO', '2025-10-13 17:50:02', '2025-10-14 01:57:05', NULL, '2025-10-14 02:00:10', 0),
	(27, 1, 2, 2, 'R-002', 1, 2, 'ATENDIDO', '2025-10-13 17:56:52', '2025-10-14 01:57:23', NULL, '2025-10-14 01:57:31', 0),
	(28, 1, 2, 3, 'R-003', 1, 2, 'ATENDIDO', '2025-10-13 18:00:19', '2025-10-14 02:01:05', NULL, '2025-10-14 02:01:11', 0),
	(29, 2, 3, 2, 'C-002', 2, 3, 'ATENDIDO', '2025-10-13 18:00:24', '2025-10-14 02:00:57', NULL, '2025-10-14 02:01:17', 0),
	(30, 2, 1, 2, 'H-002', 2, 3, 'ATENDIDO', '2025-10-13 18:00:28', '2025-10-14 02:03:43', NULL, '2025-10-14 02:04:42', 0),
	(31, 1, 2, 4, 'R-004', 1, 2, 'ATENDIDO', '2025-10-13 18:03:37', '2025-10-14 02:03:51', NULL, '2025-10-14 02:04:01', 0),
	(32, 1, 2, 5, 'R-005', 1, 2, 'ATENDIDO', '2025-10-13 18:04:49', '2025-10-14 02:05:08', NULL, '2025-10-14 02:05:22', 0),
	(33, 2, 3, 3, 'C-003', 2, 3, 'ATENDIDO', '2025-10-13 18:04:53', '2025-10-14 02:05:05', NULL, '2025-10-14 02:05:16', 0),
	(34, 2, 1, 3, 'H-003', 2, 3, 'ATENDIDO', '2025-10-13 18:04:56', '2025-10-14 02:05:44', NULL, '2025-10-14 02:06:11', 0),
	(35, 1, 2, 6, 'R-006', 1, 2, 'ATENDIDO', '2025-10-13 18:05:28', '2025-10-14 02:05:48', NULL, '2025-10-14 02:05:58', 0),
	(36, 1, 2, 7, 'R-007', 1, 2, 'ATENDIDO', '2025-10-13 18:06:04', '2025-10-14 02:06:07', NULL, '2025-10-14 02:12:07', 0),
	(37, 2, 1, 4, 'H-004', 2, 3, 'ATENDIDO', '2025-10-13 18:06:24', '2025-10-14 02:06:32', NULL, '2025-10-14 02:06:39', 0),
	(38, 1, 2, 8, 'R-008', 1, 2, 'ATENDIDO', '2025-10-13 18:11:52', '2025-10-14 02:12:11', NULL, '2025-10-14 02:12:32', 0),
	(39, 2, 3, 4, 'C-004', 2, 3, 'ATENDIDO', '2025-10-13 18:11:55', '2025-10-14 02:12:18', NULL, '2025-10-14 02:12:27', 0),
	(40, 2, 1, 5, 'H-005', 2, 3, 'ATENDIDO', '2025-10-13 18:16:50', '2025-10-14 02:17:03', NULL, '2025-10-14 02:17:11', 0),
	(41, 1, 2, 9, 'R-009', 1, 2, 'ATENDIDO', '2025-10-13 18:16:53', '2025-10-14 02:16:56', NULL, '2025-10-14 02:19:44', 0),
	(42, 2, 1, 6, 'H-006', 2, 3, 'ATENDIDO', '2025-10-13 18:19:52', '2025-10-14 02:21:29', NULL, '2025-10-14 02:21:33', 0),
	(43, 1, 2, 10, 'R-010', 1, 2, 'ATENDIDO', '2025-10-13 18:19:56', '2025-10-14 02:21:22', NULL, '2025-10-14 02:23:07', 0),
	(44, 3, 4, 1, 'P-001', 4, 5, 'ATENDIDO', '2025-10-13 18:22:55', '2025-10-14 02:23:48', NULL, '2025-10-14 02:24:35', 0),
	(45, 2, 1, 7, 'H-007', 2, 3, 'ATENDIDO', '2025-10-13 18:22:59', '2025-10-14 02:23:52', NULL, '2025-10-14 02:24:30', 0),
	(46, 1, 2, 11, 'R-011', 1, 2, 'ATENDIDO', '2025-10-13 18:23:02', '2025-10-14 02:24:06', NULL, '2025-10-14 02:24:13', 0),
	(47, 1, 2, 12, 'R-012', 1, 2, 'ATENDIDO', '2025-10-13 18:27:00', '2025-10-14 02:27:39', NULL, '2025-10-14 02:27:46', 0),
	(48, 2, 3, 5, 'C-005', 2, 3, 'ATENDIDO', '2025-10-13 18:27:04', '2025-10-14 02:27:20', NULL, '2025-10-14 02:27:53', 0),
	(49, 3, 4, 2, 'P-002', 4, 5, 'ATENDIDO', '2025-10-13 18:27:07', '2025-10-14 02:27:23', NULL, '2025-10-14 02:28:00', 0),
	(50, 1, 2, 13, 'R-013', 1, 2, 'ATENDIDO', '2025-10-13 18:31:29', '2025-10-14 02:32:45', NULL, '2025-10-14 02:37:42', 0),
	(51, 2, 3, 6, 'C-006', 2, 3, 'ATENDIDO', '2025-10-13 18:31:34', '2025-10-14 02:32:48', NULL, '2025-10-14 02:37:37', 0),
	(52, 3, 4, 3, 'P-003', 4, 5, 'ATENDIDO', '2025-10-13 18:31:37', '2025-10-14 02:32:50', NULL, '2025-10-14 02:37:34', 0),
	(53, 2, 1, 8, 'H-008', 2, 3, 'ATENDIDO', '2025-10-13 18:37:47', '2025-10-14 02:38:01', NULL, '2025-10-14 02:42:17', 1),
	(54, 3, 4, 4, 'P-004', 4, 5, 'ATENDIDO', '2025-10-13 18:37:52', '2025-10-14 02:38:04', NULL, '2025-10-14 02:42:09', 1),
	(55, 1, 2, 14, 'R-014', 1, 2, 'ATENDIDO', '2025-10-13 18:37:55', '2025-10-14 02:38:03', NULL, '2025-10-14 02:42:16', 1),
	(56, 1, 2, 15, 'R-015', 1, 2, 'ATENDIDO', '2025-10-13 18:41:46', '2025-10-14 02:43:25', NULL, '2025-10-14 02:43:34', 1),
	(57, 2, 1, 9, 'H-009', 2, 3, 'ATENDIDO', '2025-10-13 18:41:49', '2025-10-14 02:43:29', NULL, '2025-10-14 02:43:36', 1),
	(58, 3, 4, 5, 'P-005', 4, 5, 'ATENDIDO', '2025-10-13 18:41:53', '2025-10-14 02:43:27', NULL, '2025-10-14 02:43:33', 1),
	(59, 1, 2, 16, 'R-016', 1, 2, 'ATENDIDO', '2025-10-13 18:48:08', '2025-10-14 02:49:31', NULL, '2025-10-14 02:49:54', 1),
	(60, 2, 1, 10, 'H-010', 2, 3, 'ATENDIDO', '2025-10-13 18:48:11', '2025-10-14 02:49:39', NULL, '2025-10-14 02:49:56', 1),
	(61, 3, 4, 6, 'P-006', 4, 5, 'ATENDIDO', '2025-10-13 18:48:14', '2025-10-14 02:49:44', NULL, '2025-10-14 02:49:50', 1),
	(62, 1, 2, 17, 'R-017', 1, 2, 'ATENDIDO', '2025-10-13 18:52:32', '2025-10-14 02:52:45', NULL, '2025-10-14 02:57:07', 1),
	(63, 2, 1, 11, 'H-011', 2, 3, 'ATENDIDO', '2025-10-13 18:52:35', '2025-10-14 02:52:47', NULL, '2025-10-14 02:57:09', 1),
	(64, 3, 4, 7, 'P-007', 4, 5, 'ATENDIDO', '2025-10-13 18:52:38', '2025-10-14 02:52:43', NULL, '2025-10-14 02:57:04', 1),
	(65, 1, 2, 18, 'R-018', 1, 2, 'ATENDIDO', '2025-10-13 18:57:15', '2025-10-14 02:57:54', NULL, '2025-10-14 02:58:57', 1),
	(66, 2, 1, 12, 'H-012', 2, 3, 'ATENDIDO', '2025-10-13 18:57:18', '2025-10-14 02:57:58', NULL, '2025-10-14 02:59:08', 1),
	(67, 3, 4, 8, 'P-008', 4, 5, 'ATENDIDO', '2025-10-13 18:57:21', '2025-10-14 02:57:55', NULL, '2025-10-14 02:58:56', 1),
	(68, 1, 2, 19, 'R-019', 1, 2, 'ATENDIDO', '2025-10-13 18:59:13', '2025-10-14 02:59:33', NULL, '2025-10-14 03:00:43', 1),
	(69, 2, 1, 13, 'H-013', 2, 3, 'ATENDIDO', '2025-10-13 18:59:16', '2025-10-14 02:59:32', NULL, '2025-10-14 03:00:39', 1),
	(70, 3, 4, 9, 'P-009', 4, 5, 'ATENDIDO', '2025-10-13 18:59:19', '2025-10-14 02:59:35', NULL, '2025-10-14 03:00:44', 1),
	(71, 2, 3, 7, 'C-007', 2, 3, 'ATENDIDO', '2025-10-13 19:00:49', '2025-10-14 03:00:59', NULL, '2025-10-14 03:02:04', 1),
	(72, 3, 4, 10, 'P-010', 4, 5, 'ATENDIDO', '2025-10-13 19:00:52', '2025-10-14 03:01:00', NULL, '2025-10-14 03:02:00', 1),
	(73, 1, 2, 20, 'R-020', 1, 2, 'ATENDIDO', '2025-10-13 19:00:55', '2025-10-14 03:01:02', NULL, '2025-10-14 03:02:02', 1),
	(74, 1, 2, 1, 'R-001', 1, 2, 'PERDIDO', '2025-10-14 13:09:21', '2025-10-14 21:14:14', NULL, NULL, 1),
	(75, 2, 3, 1, 'C-001', NULL, NULL, 'PERDIDO', '2025-10-14 13:09:36', NULL, NULL, NULL, 0),
	(76, 2, 1, 1, 'H-001', NULL, NULL, 'PERDIDO', '2025-10-14 13:09:40', NULL, NULL, NULL, 0),
	(77, 3, 4, 1, 'P-001', 4, 5, 'PERDIDO', '2025-10-14 13:09:43', '2025-10-14 21:14:14', NULL, NULL, 1),
	(78, 2, 3, 2, 'C-002', NULL, NULL, 'PERDIDO', '2025-10-17 12:45:58', NULL, NULL, NULL, 0),
	(79, 2, 1, 1, 'H-001', 2, 3, 'ATENDIDO', '2025-10-20 14:36:09', '2025-10-20 22:41:07', NULL, '2025-10-20 22:41:35', 0),
	(80, 3, 4, 1, 'P-001', NULL, NULL, 'PERDIDO', '2025-10-20 06:00:01', NULL, NULL, NULL, 0),
	(81, 2, 3, 2, 'C-002', 2, 3, 'ATENDIDO', '2025-10-20 21:27:42', '2025-10-21 05:29:16', NULL, '2025-10-21 05:29:30', 1),
	(82, 2, 1, 2, 'H-002', 2, 3, 'ATENDIDO', '2025-10-20 21:29:10', '2025-10-21 05:55:59', NULL, '2025-10-21 05:56:08', 0),
	(83, 1, 2, 2, 'R-002', NULL, NULL, 'PERDIDO', '2025-10-20 21:32:01', NULL, NULL, NULL, 0),
	(103, 2, 1, 1, 'H-001', NULL, NULL, 'ESPERA', '2025-11-11 15:51:14', NULL, NULL, NULL, 0),
	(104, 3, 4, 1, 'P-001', NULL, NULL, 'ESPERA', '2025-11-11 15:51:18', NULL, NULL, NULL, 0),
	(105, 1, 2, 2, 'R-002', NULL, NULL, 'ESPERA', '2025-11-11 15:51:23', NULL, NULL, NULL, 0),
	(106, 2, 3, 1, 'C-001', NULL, NULL, 'ESPERA', '2025-11-11 15:51:26', NULL, NULL, NULL, 0),
	(107, 2, 5, 1, 'B-001', NULL, NULL, 'ESPERA', '2025-11-11 15:51:30', NULL, NULL, NULL, 0);

-- Volcando estructura para tabla turnero_db.usuarios
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id_usuario` int(11) NOT NULL AUTO_INCREMENT,
  `nombre_completo` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `rol` enum('admin','empleado') NOT NULL DEFAULT 'empleado',
  `id_area_asignada` int(11) DEFAULT NULL,
  `escritorio_asignado` int(11) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_usuario`),
  UNIQUE KEY `username` (`username`),
  KEY `id_area_asignada` (`id_area_asignada`),
  CONSTRAINT `usuarios_ibfk_1` FOREIGN KEY (`id_area_asignada`) REFERENCES `areas` (`id_area`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4;

-- Volcando datos para la tabla turnero_db.usuarios: ~5 rows (aproximadamente)
DELETE FROM `usuarios`;
INSERT INTO `usuarios` (`id_usuario`, `nombre_completo`, `username`, `password`, `rol`, `id_area_asignada`, `escritorio_asignado`, `activo`) VALUES
	(1, 'Administrador Principal', 'admin', '$2y$10$tJ09KzE/S0LzR2O8d0T.E.F.F.uE8lY4d0Xw6l0V7N3x5oI4wK1h.', 'admin', NULL, NULL, 1),
	(2, 'Empleado Ventas 1', 'juridico1', '$2y$10$tJ09KzE/S0LzR2O8d0T.E.F.F.uE8lY4d0Xw6l0V7N3x5oI4wK1h.', 'empleado', 1, 1, 1),
	(3, 'Empleado Caja 1', 'ventanilla1', '$2y$10$tJ09KzE/S0LzR2O8d0T.E.F.F.uE8lY4d0Xw6l0V7N3x5oI4wK1h.', 'empleado', 2, 2, 1),
	(4, 'Empleado Caja 2', 'ventanilla2', '$2y$10$tJ09KzE/S0LzR2O8d0T.E.F.F.uE8lY4d0Xw6l0V7N3x5oI4wK1h.', 'empleado', 2, 3, 1),
	(5, 'Empleado Atencion 1', 'exterior1', '$2y$10$tJ09KzE/S0LzR2O8d0T.E.F.F.uE8lY4d0Xw6l0V7N3x5oI4wK1h.', 'empleado', 3, 4, 0);

-- Volcando estructura para tabla turnero_db.usuario_tramite_asignado
CREATE TABLE IF NOT EXISTS `usuario_tramite_asignado` (
  `id_asignacion` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) NOT NULL,
  `id_tramite` int(11) NOT NULL,
  PRIMARY KEY (`id_asignacion`),
  UNIQUE KEY `unique_user_tramite` (`id_usuario`,`id_tramite`),
  KEY `id_tramite` (`id_tramite`),
  CONSTRAINT `usuario_tramite_asignado_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE,
  CONSTRAINT `usuario_tramite_asignado_ibfk_2` FOREIGN KEY (`id_tramite`) REFERENCES `tipos_tramite` (`id_tramite`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4;

-- Volcando datos para la tabla turnero_db.usuario_tramite_asignado: ~4 rows (aproximadamente)
DELETE FROM `usuario_tramite_asignado`;
INSERT INTO `usuario_tramite_asignado` (`id_asignacion`, `id_usuario`, `id_tramite`) VALUES
	(3, 2, 2),
	(5, 3, 1),
	(4, 3, 3),
	(8, 4, 3);

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
