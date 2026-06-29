/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `aceptaciones_legales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `aceptaciones_legales` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `firma_id` bigint(20) unsigned DEFAULT NULL,
  `usuario_id` bigint(20) unsigned NOT NULL,
  `documento_legal_id` bigint(20) unsigned NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `evidence_hash` char(64) NOT NULL,
  `accepted_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_aceptaciones_usuario_documento` (`usuario_id`,`documento_legal_id`),
  KEY `idx_aceptaciones_firma_fecha` (`firma_id`,`accepted_at`),
  KEY `idx_aceptaciones_documento` (`documento_legal_id`,`accepted_at`),
  CONSTRAINT `fk_aceptaciones_documento` FOREIGN KEY (`documento_legal_id`) REFERENCES `documentos_legales` (`id`),
  CONSTRAINT `fk_aceptaciones_firma` FOREIGN KEY (`firma_id`) REFERENCES `firmas` (`id`),
  CONSTRAINT `fk_aceptaciones_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `audiencias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audiencias` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `firma_id` bigint(20) unsigned NOT NULL,
  `caso_id` bigint(20) unsigned NOT NULL,
  `responsable_usuario_id` bigint(20) unsigned DEFAULT NULL,
  `titulo` varchar(180) NOT NULL,
  `fecha` date NOT NULL,
  `hora` time NOT NULL,
  `timezone` varchar(64) NOT NULL,
  `modalidad` varchar(30) NOT NULL DEFAULT 'presencial',
  `despacho` varchar(180) DEFAULT NULL,
  `lugar` varchar(255) DEFAULT NULL,
  `enlace` varchar(500) DEFAULT NULL,
  `estado` varchar(30) NOT NULL DEFAULT 'programada',
  `resultado` text DEFAULT NULL,
  `resultado_registrado_at` datetime(6) DEFAULT NULL,
  `resultado_registrado_por_usuario_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  `deleted_at` datetime(6) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_audiencias_id_firma` (`id`,`firma_id`),
  KEY `idx_audiencias_caso_fecha` (`firma_id`,`caso_id`,`fecha`,`hora`,`deleted_at`),
  KEY `idx_audiencias_estado_fecha` (`firma_id`,`estado`,`fecha`,`hora`,`deleted_at`),
  KEY `idx_audiencias_responsable` (`firma_id`,`responsable_usuario_id`,`fecha`,`deleted_at`),
  KEY `fk_audiencias_caso` (`caso_id`,`firma_id`),
  KEY `fk_audiencias_responsable` (`responsable_usuario_id`,`firma_id`),
  KEY `fk_audiencias_resultado_por` (`resultado_registrado_por_usuario_id`),
  CONSTRAINT `fk_audiencias_caso` FOREIGN KEY (`caso_id`, `firma_id`) REFERENCES `casos` (`id`, `firma_id`),
  CONSTRAINT `fk_audiencias_firma` FOREIGN KEY (`firma_id`) REFERENCES `firmas` (`id`),
  CONSTRAINT `fk_audiencias_responsable` FOREIGN KEY (`responsable_usuario_id`, `firma_id`) REFERENCES `usuarios` (`id`, `firma_id`),
  CONSTRAINT `fk_audiencias_resultado_por` FOREIGN KEY (`resultado_registrado_por_usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `auditoria`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `auditoria` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `firma_id` bigint(20) unsigned DEFAULT NULL,
  `usuario_id` bigint(20) unsigned DEFAULT NULL,
  `accion` varchar(120) NOT NULL,
  `modulo` varchar(80) NOT NULL,
  `entidad_tipo` varchar(100) DEFAULT NULL,
  `entidad_id` varchar(80) DEFAULT NULL,
  `severidad` varchar(20) NOT NULL DEFAULT 'info',
  `correlation_id` varchar(64) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id`),
  KEY `idx_auditoria_firma_fecha` (`firma_id`,`created_at`),
  KEY `idx_auditoria_usuario_fecha` (`usuario_id`,`created_at`),
  KEY `idx_auditoria_modulo_accion` (`modulo`,`accion`,`created_at`),
  KEY `idx_auditoria_entidad` (`firma_id`,`entidad_tipo`,`entidad_id`,`created_at`),
  KEY `idx_auditoria_correlacion` (`correlation_id`),
  CONSTRAINT `fk_auditoria_firma` FOREIGN KEY (`firma_id`) REFERENCES `firmas` (`id`),
  CONSTRAINT `fk_auditoria_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `caso_partes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `caso_partes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `firma_id` bigint(20) unsigned NOT NULL,
  `caso_id` bigint(20) unsigned NOT NULL,
  `tipo_parte` varchar(40) NOT NULL,
  `nombre` varchar(180) NOT NULL,
  `nombre_normalizado` varchar(180) NOT NULL,
  `tipo_documento` varchar(40) DEFAULT NULL,
  `numero_documento` varchar(80) DEFAULT NULL,
  `documento_hash` char(64) DEFAULT NULL,
  `email` varchar(254) DEFAULT NULL,
  `telefono` varchar(60) DEFAULT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `estado` varchar(30) NOT NULL DEFAULT 'activo',
  `observaciones` text DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  `deleted_at` datetime(6) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_caso_partes_id_firma` (`id`,`firma_id`),
  KEY `idx_caso_partes_caso` (`firma_id`,`caso_id`,`tipo_parte`,`deleted_at`),
  KEY `idx_caso_partes_nombre` (`firma_id`,`nombre_normalizado`,`deleted_at`),
  KEY `idx_caso_partes_documento` (`firma_id`,`documento_hash`,`deleted_at`),
  KEY `fk_caso_partes_caso` (`caso_id`,`firma_id`),
  CONSTRAINT `fk_caso_partes_caso` FOREIGN KEY (`caso_id`, `firma_id`) REFERENCES `casos` (`id`, `firma_id`),
  CONSTRAINT `fk_caso_partes_firma` FOREIGN KEY (`firma_id`) REFERENCES `firmas` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `caso_permisos_portal`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `caso_permisos_portal` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `firma_id` bigint(20) unsigned NOT NULL,
  `cliente_id` bigint(20) unsigned NOT NULL,
  `caso_id` bigint(20) unsigned NOT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'autorizado',
  `observacion_publica` text DEFAULT NULL,
  `autorizado_por_usuario_id` bigint(20) unsigned DEFAULT NULL,
  `autorizado_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `revocado_at` datetime(6) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_caso_portal` (`firma_id`,`cliente_id`,`caso_id`),
  KEY `idx_caso_portal_estado` (`firma_id`,`cliente_id`,`estado`),
  KEY `idx_caso_portal_cliente_fk` (`cliente_id`,`firma_id`),
  KEY `idx_caso_portal_caso_fk` (`caso_id`,`firma_id`),
  KEY `fk_caso_portal_usuario` (`autorizado_por_usuario_id`),
  CONSTRAINT `fk_caso_portal_caso` FOREIGN KEY (`caso_id`, `firma_id`) REFERENCES `casos` (`id`, `firma_id`),
  CONSTRAINT `fk_caso_portal_cliente` FOREIGN KEY (`cliente_id`, `firma_id`) REFERENCES `clientes` (`id`, `firma_id`),
  CONSTRAINT `fk_caso_portal_firma` FOREIGN KEY (`firma_id`) REFERENCES `firmas` (`id`),
  CONSTRAINT `fk_caso_portal_usuario` FOREIGN KEY (`autorizado_por_usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `caso_timeline`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `caso_timeline` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `firma_id` bigint(20) unsigned NOT NULL,
  `caso_id` bigint(20) unsigned NOT NULL,
  `autor_usuario_id` bigint(20) unsigned DEFAULT NULL,
  `fecha_evento` date NOT NULL,
  `tipo_evento` varchar(80) NOT NULL,
  `titulo` varchar(180) NOT NULL,
  `contenido_publico` text DEFAULT NULL,
  `contenido_interno` text DEFAULT NULL,
  `visibilidad` varchar(20) NOT NULL DEFAULT 'interna',
  `critico` tinyint(1) NOT NULL DEFAULT 0,
  `documento_id` bigint(20) unsigned DEFAULT NULL,
  `estado` varchar(30) NOT NULL DEFAULT 'activo',
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  `deleted_at` datetime(6) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_caso_timeline_id_firma` (`id`,`firma_id`),
  KEY `idx_caso_timeline_caso_fecha` (`firma_id`,`caso_id`,`fecha_evento`,`deleted_at`),
  KEY `idx_caso_timeline_visibilidad` (`firma_id`,`visibilidad`,`deleted_at`),
  KEY `idx_caso_timeline_autor` (`autor_usuario_id`,`created_at`),
  KEY `fk_caso_timeline_caso` (`caso_id`,`firma_id`),
  CONSTRAINT `fk_caso_timeline_autor` FOREIGN KEY (`autor_usuario_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_caso_timeline_caso` FOREIGN KEY (`caso_id`, `firma_id`) REFERENCES `casos` (`id`, `firma_id`),
  CONSTRAINT `fk_caso_timeline_firma` FOREIGN KEY (`firma_id`) REFERENCES `firmas` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `casos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `casos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `firma_id` bigint(20) unsigned NOT NULL,
  `cliente_id` bigint(20) unsigned NOT NULL,
  `responsable_usuario_id` bigint(20) unsigned DEFAULT NULL,
  `titulo` varchar(180) NOT NULL,
  `titulo_normalizado` varchar(180) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `estado` varchar(30) NOT NULL DEFAULT 'activo',
  `prioridad` varchar(30) NOT NULL DEFAULT 'media',
  `tipo_proceso` varchar(120) DEFAULT NULL,
  `jurisdiccion` varchar(120) DEFAULT NULL,
  `despacho` varchar(180) DEFAULT NULL,
  `radicado` varchar(120) DEFAULT NULL,
  `fecha_apertura` date DEFAULT NULL,
  `closed_at` datetime(6) DEFAULT NULL,
  `closed_by_usuario_id` bigint(20) unsigned DEFAULT NULL,
  `close_reason` varchar(500) DEFAULT NULL,
  `archived_at` datetime(6) DEFAULT NULL,
  `archived_by_usuario_id` bigint(20) unsigned DEFAULT NULL,
  `archive_reason` varchar(500) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  `deleted_at` datetime(6) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_casos_id_firma` (`id`,`firma_id`),
  UNIQUE KEY `uq_casos_firma_radicado` (`firma_id`,`radicado`),
  KEY `idx_casos_cliente_estado` (`firma_id`,`cliente_id`,`estado`,`deleted_at`),
  KEY `idx_casos_responsable_estado` (`firma_id`,`responsable_usuario_id`,`estado`,`deleted_at`),
  KEY `idx_casos_titulo` (`firma_id`,`titulo_normalizado`,`deleted_at`),
  KEY `idx_casos_estado_prioridad` (`firma_id`,`estado`,`prioridad`,`deleted_at`),
  KEY `fk_casos_cliente` (`cliente_id`,`firma_id`),
  KEY `fk_casos_responsable` (`responsable_usuario_id`,`firma_id`),
  KEY `fk_casos_cerrado_por` (`closed_by_usuario_id`),
  KEY `fk_casos_archivado_por` (`archived_by_usuario_id`),
  CONSTRAINT `fk_casos_archivado_por` FOREIGN KEY (`archived_by_usuario_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_casos_cerrado_por` FOREIGN KEY (`closed_by_usuario_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_casos_cliente` FOREIGN KEY (`cliente_id`, `firma_id`) REFERENCES `clientes` (`id`, `firma_id`),
  CONSTRAINT `fk_casos_firma` FOREIGN KEY (`firma_id`) REFERENCES `firmas` (`id`),
  CONSTRAINT `fk_casos_responsable` FOREIGN KEY (`responsable_usuario_id`, `firma_id`) REFERENCES `usuarios` (`id`, `firma_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `catalogo_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `catalogo_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `firma_id` bigint(20) unsigned DEFAULT NULL,
  `catalogo_id` bigint(20) unsigned NOT NULL,
  `codigo` varchar(100) NOT NULL,
  `etiqueta` varchar(180) NOT NULL,
  `orden` int(11) NOT NULL DEFAULT 0,
  `estado` varchar(30) NOT NULL DEFAULT 'activo',
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  `deleted_at` datetime(6) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_catalogo_items_codigo` (`catalogo_id`,`codigo`),
  KEY `idx_catalogo_items_lista` (`catalogo_id`,`estado`,`orden`,`deleted_at`),
  KEY `idx_catalogo_items_firma` (`firma_id`,`deleted_at`),
  CONSTRAINT `fk_catalogo_items_catalogo` FOREIGN KEY (`catalogo_id`) REFERENCES `catalogos` (`id`),
  CONSTRAINT `fk_catalogo_items_firma` FOREIGN KEY (`firma_id`) REFERENCES `firmas` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `catalogos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `catalogos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `firma_id` bigint(20) unsigned DEFAULT NULL,
  `alcance` varchar(20) NOT NULL DEFAULT 'firma',
  `codigo` varchar(100) NOT NULL,
  `scope_key` varchar(240) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `estado` varchar(30) NOT NULL DEFAULT 'activo',
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  `deleted_at` datetime(6) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_catalogos_scope_key` (`scope_key`),
  KEY `idx_catalogos_firma_estado` (`firma_id`,`estado`,`deleted_at`),
  KEY `idx_catalogos_codigo` (`codigo`),
  CONSTRAINT `fk_catalogos_firma` FOREIGN KEY (`firma_id`) REFERENCES `firmas` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `checklist_owner`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `checklist_owner` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `firma_id` bigint(20) unsigned DEFAULT NULL,
  `titulo` varchar(180) NOT NULL,
  `estado` varchar(30) NOT NULL DEFAULT 'pendiente',
  `decision` varchar(30) DEFAULT NULL,
  `decision_observacion` text DEFAULT NULL,
  `decidido_por_usuario_id` bigint(20) unsigned DEFAULT NULL,
  `decidido_at` datetime(6) DEFAULT NULL,
  `created_by_usuario_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  PRIMARY KEY (`id`),
  KEY `idx_checklist_owner_estado` (`firma_id`,`estado`,`created_at`),
  KEY `fk_checklist_owner_decidido_por` (`decidido_por_usuario_id`),
  KEY `fk_checklist_owner_creado_por` (`created_by_usuario_id`),
  CONSTRAINT `fk_checklist_owner_creado_por` FOREIGN KEY (`created_by_usuario_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_checklist_owner_decidido_por` FOREIGN KEY (`decidido_por_usuario_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_checklist_owner_firma` FOREIGN KEY (`firma_id`) REFERENCES `firmas` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `checklist_owner_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `checklist_owner_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `checklist_id` bigint(20) unsigned NOT NULL,
  `codigo` varchar(80) NOT NULL,
  `titulo` varchar(180) NOT NULL,
  `estado` varchar(30) NOT NULL DEFAULT 'pendiente',
  `observacion` text DEFAULT NULL,
  `evidencia` varchar(500) DEFAULT NULL,
  `evaluado_por_usuario_id` bigint(20) unsigned DEFAULT NULL,
  `evaluado_at` datetime(6) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_checklist_item_codigo` (`checklist_id`,`codigo`),
  KEY `idx_checklist_items_estado` (`checklist_id`,`estado`),
  KEY `fk_checklist_items_usuario` (`evaluado_por_usuario_id`),
  CONSTRAINT `fk_checklist_items_checklist` FOREIGN KEY (`checklist_id`) REFERENCES `checklist_owner` (`id`),
  CONSTRAINT `fk_checklist_items_usuario` FOREIGN KEY (`evaluado_por_usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cliente_autorizaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cliente_autorizaciones` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `firma_id` bigint(20) unsigned NOT NULL,
  `cliente_id` bigint(20) unsigned NOT NULL,
  `tipo` varchar(80) NOT NULL,
  `estado` varchar(30) NOT NULL DEFAULT 'otorgada',
  `medio` varchar(120) DEFAULT NULL,
  `version_texto` varchar(80) DEFAULT NULL,
  `evidencia_hash` char(64) NOT NULL,
  `observacion` varchar(500) DEFAULT NULL,
  `registrado_por_usuario_id` bigint(20) unsigned DEFAULT NULL,
  `revoked_at` datetime(6) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id`),
  KEY `idx_cliente_autorizaciones_cliente` (`firma_id`,`cliente_id`,`tipo`,`created_at`),
  KEY `idx_cliente_autorizaciones_usuario` (`registrado_por_usuario_id`),
  KEY `fk_cliente_autorizaciones_cliente` (`cliente_id`,`firma_id`),
  CONSTRAINT `fk_cliente_autorizaciones_cliente` FOREIGN KEY (`cliente_id`, `firma_id`) REFERENCES `clientes` (`id`, `firma_id`),
  CONSTRAINT `fk_cliente_autorizaciones_firma` FOREIGN KEY (`firma_id`) REFERENCES `firmas` (`id`),
  CONSTRAINT `fk_cliente_autorizaciones_usuario` FOREIGN KEY (`registrado_por_usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `clientes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `clientes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `firma_id` bigint(20) unsigned NOT NULL,
  `tipo_persona` varchar(20) NOT NULL DEFAULT 'natural',
  `nombre_razon_social` varchar(180) NOT NULL,
  `nombre_normalizado` varchar(180) NOT NULL,
  `tipo_documento` varchar(40) DEFAULT NULL,
  `numero_documento` varchar(80) DEFAULT NULL,
  `documento_normalizado` varchar(80) DEFAULT NULL,
  `documento_hash` char(64) DEFAULT NULL,
  `email` varchar(254) DEFAULT NULL,
  `telefono` varchar(60) DEFAULT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `estado` varchar(30) NOT NULL DEFAULT 'activo',
  `origen` varchar(120) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `tratamiento_datos_autorizado` tinyint(1) NOT NULL DEFAULT 0,
  `autorizacion_tratamiento_at` datetime(6) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  `deleted_at` datetime(6) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_clientes_id_firma` (`id`,`firma_id`),
  KEY `idx_clientes_firma_estado` (`firma_id`,`estado`,`deleted_at`),
  KEY `idx_clientes_firma_nombre` (`firma_id`,`nombre_normalizado`,`deleted_at`),
  KEY `idx_clientes_firma_documento` (`firma_id`,`documento_hash`,`deleted_at`),
  KEY `idx_clientes_documento_normalizado` (`firma_id`,`documento_normalizado`,`deleted_at`),
  CONSTRAINT `fk_clientes_firma` FOREIGN KEY (`firma_id`) REFERENCES `firmas` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `documento_permisos_portal`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `documento_permisos_portal` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `firma_id` bigint(20) unsigned NOT NULL,
  `cliente_id` bigint(20) unsigned NOT NULL,
  `documento_id` bigint(20) unsigned NOT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'autorizado',
  `observacion_publica` text DEFAULT NULL,
  `autorizado_por_usuario_id` bigint(20) unsigned DEFAULT NULL,
  `autorizado_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `revocado_at` datetime(6) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_documento_portal` (`firma_id`,`cliente_id`,`documento_id`),
  KEY `idx_documento_portal_estado` (`firma_id`,`cliente_id`,`estado`),
  KEY `idx_documento_portal_cliente_fk` (`cliente_id`,`firma_id`),
  KEY `idx_documento_portal_documento_fk` (`documento_id`,`firma_id`),
  KEY `fk_documento_portal_usuario` (`autorizado_por_usuario_id`),
  CONSTRAINT `fk_documento_portal_cliente` FOREIGN KEY (`cliente_id`, `firma_id`) REFERENCES `clientes` (`id`, `firma_id`),
  CONSTRAINT `fk_documento_portal_documento` FOREIGN KEY (`documento_id`, `firma_id`) REFERENCES `documentos` (`id`, `firma_id`),
  CONSTRAINT `fk_documento_portal_firma` FOREIGN KEY (`firma_id`) REFERENCES `firmas` (`id`),
  CONSTRAINT `fk_documento_portal_usuario` FOREIGN KEY (`autorizado_por_usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `documento_versiones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `documento_versiones` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `firma_id` bigint(20) unsigned NOT NULL,
  `documento_id` bigint(20) unsigned NOT NULL,
  `version_numero` int(10) unsigned NOT NULL,
  `nombre_original` varchar(255) NOT NULL,
  `nombre_fisico` varchar(160) NOT NULL,
  `extension` varchar(20) NOT NULL,
  `mime_declarado` varchar(120) DEFAULT NULL,
  `mime_detectado` varchar(120) NOT NULL,
  `size_bytes` bigint(20) unsigned NOT NULL,
  `checksum_sha256` char(64) NOT NULL,
  `storage_path` varchar(500) NOT NULL,
  `uploaded_by_usuario_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_documento_version` (`firma_id`,`documento_id`,`version_numero`),
  KEY `idx_documento_versiones_documento` (`firma_id`,`documento_id`,`created_at`),
  KEY `idx_documento_versiones_checksum` (`checksum_sha256`),
  KEY `idx_documento_versiones_usuario` (`uploaded_by_usuario_id`),
  KEY `fk_documento_versiones_documento` (`documento_id`,`firma_id`),
  CONSTRAINT `fk_documento_versiones_documento` FOREIGN KEY (`documento_id`, `firma_id`) REFERENCES `documentos` (`id`, `firma_id`),
  CONSTRAINT `fk_documento_versiones_firma` FOREIGN KEY (`firma_id`) REFERENCES `firmas` (`id`),
  CONSTRAINT `fk_documento_versiones_usuario` FOREIGN KEY (`uploaded_by_usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `documentos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `documentos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `firma_id` bigint(20) unsigned NOT NULL,
  `cliente_id` bigint(20) unsigned DEFAULT NULL,
  `caso_id` bigint(20) unsigned DEFAULT NULL,
  `gasto_id` bigint(20) unsigned DEFAULT NULL,
  `current_version_id` bigint(20) unsigned DEFAULT NULL,
  `titulo` varchar(180) NOT NULL,
  `titulo_normalizado` varchar(180) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `tipo_documental` varchar(80) DEFAULT NULL,
  `estado` varchar(30) NOT NULL DEFAULT 'activo',
  `visible_portal` tinyint(1) NOT NULL DEFAULT 0,
  `created_by_usuario_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  `deleted_at` datetime(6) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_documentos_id_firma` (`id`,`firma_id`),
  KEY `idx_documentos_cliente` (`firma_id`,`cliente_id`,`deleted_at`),
  KEY `idx_documentos_caso` (`firma_id`,`caso_id`,`deleted_at`),
  KEY `idx_documentos_gasto` (`firma_id`,`gasto_id`,`deleted_at`),
  KEY `idx_documentos_titulo` (`firma_id`,`titulo_normalizado`,`deleted_at`),
  KEY `idx_documentos_current_version` (`current_version_id`),
  KEY `idx_documentos_creador` (`created_by_usuario_id`),
  KEY `fk_documentos_cliente` (`cliente_id`,`firma_id`),
  KEY `fk_documentos_caso` (`caso_id`,`firma_id`),
  KEY `fk_documentos_gasto` (`gasto_id`,`firma_id`),
  CONSTRAINT `fk_documentos_caso` FOREIGN KEY (`caso_id`, `firma_id`) REFERENCES `casos` (`id`, `firma_id`),
  CONSTRAINT `fk_documentos_cliente` FOREIGN KEY (`cliente_id`, `firma_id`) REFERENCES `clientes` (`id`, `firma_id`),
  CONSTRAINT `fk_documentos_creador` FOREIGN KEY (`created_by_usuario_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_documentos_current_version` FOREIGN KEY (`current_version_id`) REFERENCES `documento_versiones` (`id`),
  CONSTRAINT `fk_documentos_firma` FOREIGN KEY (`firma_id`) REFERENCES `firmas` (`id`),
  CONSTRAINT `fk_documentos_gasto` FOREIGN KEY (`gasto_id`, `firma_id`) REFERENCES `gastos` (`id`, `firma_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `documentos_legales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `documentos_legales` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tipo` varchar(80) NOT NULL,
  `version` varchar(40) NOT NULL,
  `titulo` varchar(180) NOT NULL,
  `contenido` longtext NOT NULL,
  `checksum` char(64) NOT NULL,
  `estado` varchar(30) NOT NULL DEFAULT 'borrador',
  `published_at` datetime(6) DEFAULT NULL,
  `published_by_usuario_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_documentos_legales_tipo_version` (`tipo`,`version`),
  KEY `idx_documentos_legales_vigencia` (`tipo`,`estado`,`published_at`),
  KEY `fk_documentos_legales_publicador` (`published_by_usuario_id`),
  CONSTRAINT `fk_documentos_legales_publicador` FOREIGN KEY (`published_by_usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `exportaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `exportaciones` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `firma_id` bigint(20) unsigned NOT NULL,
  `usuario_id` bigint(20) unsigned DEFAULT NULL,
  `tipo` varchar(60) NOT NULL,
  `filtros_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`filtros_json`)),
  `estado` varchar(30) NOT NULL DEFAULT 'generada',
  `archivo_path` varchar(500) DEFAULT NULL,
  `filas` int(10) unsigned NOT NULL DEFAULT 0,
  `expires_at` datetime(6) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id`),
  KEY `idx_exportaciones_firma_tipo` (`firma_id`,`tipo`,`created_at`),
  KEY `idx_exportaciones_expira` (`expires_at`),
  KEY `fk_exportaciones_usuario` (`usuario_id`),
  CONSTRAINT `fk_exportaciones_firma` FOREIGN KEY (`firma_id`) REFERENCES `firmas` (`id`),
  CONSTRAINT `fk_exportaciones_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `finanza_permisos_portal`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `finanza_permisos_portal` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `firma_id` bigint(20) unsigned NOT NULL,
  `cliente_id` bigint(20) unsigned NOT NULL,
  `tipo_finanza` varchar(20) NOT NULL,
  `honorario_id` bigint(20) unsigned DEFAULT NULL,
  `pago_id` bigint(20) unsigned DEFAULT NULL,
  `gasto_id` bigint(20) unsigned DEFAULT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'autorizado',
  `observacion_publica` text DEFAULT NULL,
  `autorizado_por_usuario_id` bigint(20) unsigned DEFAULT NULL,
  `autorizado_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `revocado_at` datetime(6) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_finanza_portal_honorario` (`firma_id`,`cliente_id`,`tipo_finanza`,`honorario_id`),
  UNIQUE KEY `uq_finanza_portal_pago` (`firma_id`,`cliente_id`,`tipo_finanza`,`pago_id`),
  UNIQUE KEY `uq_finanza_portal_gasto` (`firma_id`,`cliente_id`,`tipo_finanza`,`gasto_id`),
  KEY `idx_finanza_portal_estado` (`firma_id`,`cliente_id`,`estado`),
  KEY `idx_finanza_portal_cliente_fk` (`cliente_id`,`firma_id`),
  KEY `idx_finanza_portal_honorario_fk` (`honorario_id`,`firma_id`),
  KEY `idx_finanza_portal_pago_fk` (`pago_id`,`firma_id`),
  KEY `idx_finanza_portal_gasto_fk` (`gasto_id`,`firma_id`),
  KEY `fk_finanza_portal_usuario` (`autorizado_por_usuario_id`),
  CONSTRAINT `fk_finanza_portal_cliente` FOREIGN KEY (`cliente_id`, `firma_id`) REFERENCES `clientes` (`id`, `firma_id`),
  CONSTRAINT `fk_finanza_portal_firma` FOREIGN KEY (`firma_id`) REFERENCES `firmas` (`id`),
  CONSTRAINT `fk_finanza_portal_gasto` FOREIGN KEY (`gasto_id`, `firma_id`) REFERENCES `gastos` (`id`, `firma_id`),
  CONSTRAINT `fk_finanza_portal_honorario` FOREIGN KEY (`honorario_id`, `firma_id`) REFERENCES `honorarios` (`id`, `firma_id`),
  CONSTRAINT `fk_finanza_portal_pago` FOREIGN KEY (`pago_id`, `firma_id`) REFERENCES `pagos` (`id`, `firma_id`),
  CONSTRAINT `fk_finanza_portal_usuario` FOREIGN KEY (`autorizado_por_usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `firma_comercial_historial`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `firma_comercial_historial` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `firma_id` bigint(20) unsigned NOT NULL,
  `evento` varchar(60) NOT NULL,
  `estado_comercial` varchar(30) DEFAULT NULL,
  `estado_comercial_anterior` varchar(30) DEFAULT NULL,
  `plan_id` bigint(20) unsigned DEFAULT NULL,
  `plan_anterior_id` bigint(20) unsigned DEFAULT NULL,
  `firma_plan_id` bigint(20) unsigned DEFAULT NULL,
  `recurso` varchar(100) DEFAULT NULL,
  `limite_anterior` int(10) unsigned DEFAULT NULL,
  `limite_nuevo` int(10) unsigned DEFAULT NULL,
  `politica_anterior` varchar(20) DEFAULT NULL,
  `politica_nueva` varchar(20) DEFAULT NULL,
  `effective_at` datetime(6) DEFAULT NULL,
  `starts_at` datetime(6) DEFAULT NULL,
  `ends_at` datetime(6) DEFAULT NULL,
  `renews_at` datetime(6) DEFAULT NULL,
  `motivo` varchar(500) NOT NULL,
  `usuario_id` bigint(20) unsigned DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id`),
  KEY `idx_firma_comercial_firma_fecha` (`firma_id`,`created_at`),
  KEY `idx_firma_comercial_evento` (`evento`,`created_at`),
  KEY `idx_firma_comercial_plan` (`plan_id`),
  KEY `idx_firma_comercial_usuario` (`usuario_id`),
  KEY `fk_firma_comercial_plan_anterior` (`plan_anterior_id`),
  KEY `fk_firma_comercial_firma_plan` (`firma_plan_id`),
  CONSTRAINT `fk_firma_comercial_firma` FOREIGN KEY (`firma_id`) REFERENCES `firmas` (`id`),
  CONSTRAINT `fk_firma_comercial_firma_plan` FOREIGN KEY (`firma_plan_id`) REFERENCES `firma_planes` (`id`),
  CONSTRAINT `fk_firma_comercial_plan` FOREIGN KEY (`plan_id`) REFERENCES `planes` (`id`),
  CONSTRAINT `fk_firma_comercial_plan_anterior` FOREIGN KEY (`plan_anterior_id`) REFERENCES `planes` (`id`),
  CONSTRAINT `fk_firma_comercial_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `firma_limites`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `firma_limites` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `firma_id` bigint(20) unsigned NOT NULL,
  `plan_id` bigint(20) unsigned DEFAULT NULL,
  `recurso` varchar(100) NOT NULL,
  `limite` int(10) unsigned DEFAULT NULL,
  `politica` varchar(20) NOT NULL DEFAULT 'block',
  `motivo` varchar(255) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_firma_limites_recurso` (`firma_id`,`recurso`),
  KEY `idx_firma_limites_plan` (`plan_id`),
  CONSTRAINT `fk_firma_limites_firma` FOREIGN KEY (`firma_id`) REFERENCES `firmas` (`id`),
  CONSTRAINT `fk_firma_limites_plan` FOREIGN KEY (`plan_id`) REFERENCES `planes` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `firma_planes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `firma_planes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `firma_id` bigint(20) unsigned NOT NULL,
  `plan_id` bigint(20) unsigned NOT NULL,
  `estado` varchar(30) NOT NULL DEFAULT 'activo',
  `effective_at` datetime(6) NOT NULL,
  `starts_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `renews_at` datetime(6) DEFAULT NULL,
  `billing_period` varchar(20) NOT NULL DEFAULT 'monthly',
  `billing_anchor_day` tinyint(3) unsigned DEFAULT NULL,
  `trial_started_at` datetime(6) DEFAULT NULL,
  `trial_ends_at` datetime(6) DEFAULT NULL,
  `payment_due_at` datetime(6) DEFAULT NULL,
  `grace_ends_at` datetime(6) DEFAULT NULL,
  `auto_suspend_at` datetime(6) DEFAULT NULL,
  `proration_policy` varchar(30) NOT NULL DEFAULT 'manual_review',
  `proration_note` varchar(500) DEFAULT NULL,
  `assigned_by_usuario_id` bigint(20) unsigned DEFAULT NULL,
  `motivo` varchar(500) DEFAULT NULL,
  `replaced_by_firma_plan_id` bigint(20) unsigned DEFAULT NULL,
  `ends_at` datetime(6) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id`),
  KEY `idx_firma_planes_actual` (`firma_id`,`estado`,`ends_at`),
  KEY `idx_firma_planes_plan` (`plan_id`),
  KEY `idx_firma_planes_estado` (`estado`,`starts_at`,`ends_at`),
  KEY `idx_firma_planes_effective` (`firma_id`,`estado`,`effective_at`,`ends_at`),
  KEY `idx_firma_planes_assigned_by` (`assigned_by_usuario_id`),
  KEY `idx_firma_planes_billing_due` (`estado`,`payment_due_at`,`auto_suspend_at`),
  KEY `idx_firma_planes_trial` (`estado`,`trial_ends_at`),
  KEY `idx_firma_planes_proration` (`estado`,`proration_policy`),
  KEY `fk_firma_planes_replaced_by` (`replaced_by_firma_plan_id`),
  CONSTRAINT `fk_firma_planes_assigned_by` FOREIGN KEY (`assigned_by_usuario_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_firma_planes_firma` FOREIGN KEY (`firma_id`) REFERENCES `firmas` (`id`),
  CONSTRAINT `fk_firma_planes_plan` FOREIGN KEY (`plan_id`) REFERENCES `planes` (`id`),
  CONSTRAINT `fk_firma_planes_replaced_by` FOREIGN KEY (`replaced_by_firma_plan_id`) REFERENCES `firma_planes` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `firmas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `firmas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL,
  `nombre` varchar(180) NOT NULL,
  `slug` varchar(120) NOT NULL,
  `estado` varchar(30) NOT NULL DEFAULT 'activa',
  `timezone` varchar(64) NOT NULL DEFAULT 'UTC',
  `suspended_at` datetime(6) DEFAULT NULL,
  `suspension_reason` varchar(255) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  `deleted_at` datetime(6) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_firmas_uuid` (`uuid`),
  UNIQUE KEY `uq_firmas_slug` (`slug`),
  KEY `idx_firmas_estado` (`estado`,`deleted_at`),
  KEY `idx_firmas_nombre` (`nombre`,`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `gasto_soportes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `gasto_soportes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `firma_id` bigint(20) unsigned NOT NULL,
  `gasto_id` bigint(20) unsigned NOT NULL,
  `documento_id` bigint(20) unsigned NOT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_gasto_soporte` (`firma_id`,`gasto_id`,`documento_id`),
  KEY `idx_gasto_soportes_documento` (`firma_id`,`documento_id`),
  KEY `fk_gasto_soportes_gasto` (`gasto_id`,`firma_id`),
  KEY `fk_gasto_soportes_documento` (`documento_id`,`firma_id`),
  CONSTRAINT `fk_gasto_soportes_documento` FOREIGN KEY (`documento_id`, `firma_id`) REFERENCES `documentos` (`id`, `firma_id`),
  CONSTRAINT `fk_gasto_soportes_firma` FOREIGN KEY (`firma_id`) REFERENCES `firmas` (`id`),
  CONSTRAINT `fk_gasto_soportes_gasto` FOREIGN KEY (`gasto_id`, `firma_id`) REFERENCES `gastos` (`id`, `firma_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `gastos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `gastos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `firma_id` bigint(20) unsigned NOT NULL,
  `cliente_id` bigint(20) unsigned NOT NULL,
  `caso_id` bigint(20) unsigned DEFAULT NULL,
  `concepto` varchar(180) NOT NULL,
  `concepto_normalizado` varchar(180) NOT NULL,
  `categoria` varchar(100) DEFAULT NULL,
  `monto` decimal(14,2) NOT NULL,
  `moneda` char(3) NOT NULL DEFAULT 'COP',
  `fecha_gasto` date NOT NULL,
  `estado` varchar(30) NOT NULL DEFAULT 'registrado',
  `observaciones` text DEFAULT NULL,
  `registrado_por_usuario_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  `deleted_at` datetime(6) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_gastos_id_firma` (`id`,`firma_id`),
  KEY `idx_gastos_cliente_fecha` (`firma_id`,`cliente_id`,`fecha_gasto`,`deleted_at`),
  KEY `idx_gastos_caso_fecha` (`firma_id`,`caso_id`,`fecha_gasto`,`deleted_at`),
  KEY `idx_gastos_categoria` (`firma_id`,`categoria`,`deleted_at`),
  KEY `idx_gastos_usuario` (`registrado_por_usuario_id`),
  KEY `fk_gastos_cliente` (`cliente_id`,`firma_id`),
  KEY `fk_gastos_caso` (`caso_id`,`firma_id`),
  CONSTRAINT `fk_gastos_caso` FOREIGN KEY (`caso_id`, `firma_id`) REFERENCES `casos` (`id`, `firma_id`),
  CONSTRAINT `fk_gastos_cliente` FOREIGN KEY (`cliente_id`, `firma_id`) REFERENCES `clientes` (`id`, `firma_id`),
  CONSTRAINT `fk_gastos_firma` FOREIGN KEY (`firma_id`) REFERENCES `firmas` (`id`),
  CONSTRAINT `fk_gastos_usuario` FOREIGN KEY (`registrado_por_usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `honorarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `honorarios` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `firma_id` bigint(20) unsigned NOT NULL,
  `cliente_id` bigint(20) unsigned NOT NULL,
  `caso_id` bigint(20) unsigned DEFAULT NULL,
  `concepto` varchar(180) NOT NULL,
  `concepto_normalizado` varchar(180) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `monto` decimal(14,2) NOT NULL,
  `moneda` char(3) NOT NULL DEFAULT 'COP',
  `fecha_acuerdo` date NOT NULL,
  `estado` varchar(30) NOT NULL DEFAULT 'pendiente',
  `created_by_usuario_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  `deleted_at` datetime(6) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_honorarios_id_firma` (`id`,`firma_id`),
  KEY `idx_honorarios_cliente_estado` (`firma_id`,`cliente_id`,`estado`,`deleted_at`),
  KEY `idx_honorarios_caso_estado` (`firma_id`,`caso_id`,`estado`,`deleted_at`),
  KEY `idx_honorarios_concepto` (`firma_id`,`concepto_normalizado`,`deleted_at`),
  KEY `idx_honorarios_creador` (`created_by_usuario_id`),
  KEY `fk_honorarios_cliente` (`cliente_id`,`firma_id`),
  KEY `fk_honorarios_caso` (`caso_id`,`firma_id`),
  CONSTRAINT `fk_honorarios_caso` FOREIGN KEY (`caso_id`, `firma_id`) REFERENCES `casos` (`id`, `firma_id`),
  CONSTRAINT `fk_honorarios_cliente` FOREIGN KEY (`cliente_id`, `firma_id`) REFERENCES `clientes` (`id`, `firma_id`),
  CONSTRAINT `fk_honorarios_creador` FOREIGN KEY (`created_by_usuario_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_honorarios_firma` FOREIGN KEY (`firma_id`) REFERENCES `firmas` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `importaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `importaciones` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `firma_id` bigint(20) unsigned NOT NULL,
  `usuario_id` bigint(20) unsigned DEFAULT NULL,
  `tipo` varchar(60) NOT NULL,
  `estado` varchar(30) NOT NULL DEFAULT 'previsualizada',
  `archivo_path` varchar(500) DEFAULT NULL,
  `nombre_original` varchar(255) DEFAULT NULL,
  `filas_total` int(10) unsigned NOT NULL DEFAULT 0,
  `filas_validas` int(10) unsigned NOT NULL DEFAULT 0,
  `filas_error` int(10) unsigned NOT NULL DEFAULT 0,
  `errores_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`errores_json`)),
  `resultado_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`resultado_json`)),
  `expires_at` datetime(6) DEFAULT NULL,
  `confirmed_at` datetime(6) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  PRIMARY KEY (`id`),
  KEY `idx_importaciones_firma_tipo` (`firma_id`,`tipo`,`estado`,`created_at`),
  KEY `idx_importaciones_expira` (`expires_at`),
  KEY `fk_importaciones_usuario` (`usuario_id`),
  CONSTRAINT `fk_importaciones_firma` FOREIGN KEY (`firma_id`) REFERENCES `firmas` (`id`),
  CONSTRAINT `fk_importaciones_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `login_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `login_attempts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `firma_id` bigint(20) unsigned DEFAULT NULL,
  `email_hash` char(64) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `successful` tinyint(1) NOT NULL DEFAULT 0,
  `attempted_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id`),
  KEY `idx_login_attempts_rate` (`email_hash`,`ip_address`,`attempted_at`),
  KEY `idx_login_attempts_firma` (`firma_id`,`attempted_at`),
  KEY `idx_login_attempts_cleanup` (`attempted_at`),
  CONSTRAINT `fk_login_attempts_firma` FOREIGN KEY (`firma_id`) REFERENCES `firmas` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `migrations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(191) NOT NULL,
  `batch` int(10) unsigned NOT NULL,
  `duration_ms` int(10) unsigned NOT NULL DEFAULT 0,
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_migrations_migration` (`migration`),
  KEY `idx_migrations_batch` (`batch`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `notificaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notificaciones` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `firma_id` bigint(20) unsigned NOT NULL,
  `usuario_id` bigint(20) unsigned NOT NULL,
  `titulo` varchar(180) NOT NULL,
  `mensaje` varchar(500) NOT NULL,
  `severidad` varchar(20) NOT NULL DEFAULT 'info',
  `estado` varchar(20) NOT NULL DEFAULT 'pendiente',
  `origen_tipo` varchar(40) NOT NULL,
  `origen_id` bigint(20) unsigned NOT NULL,
  `origen_url` varchar(255) NOT NULL,
  `dedupe_key` char(64) NOT NULL,
  `generated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `read_at` datetime(6) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_notificaciones_dedupe` (`firma_id`,`usuario_id`,`dedupe_key`),
  KEY `idx_notificaciones_usuario_estado` (`firma_id`,`usuario_id`,`estado`,`created_at`),
  KEY `idx_notificaciones_usuario_fk` (`usuario_id`,`firma_id`),
  KEY `idx_notificaciones_origen` (`firma_id`,`origen_tipo`,`origen_id`),
  CONSTRAINT `fk_notificaciones_firma` FOREIGN KEY (`firma_id`) REFERENCES `firmas` (`id`),
  CONSTRAINT `fk_notificaciones_usuario` FOREIGN KEY (`usuario_id`, `firma_id`) REFERENCES `usuarios` (`id`, `firma_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `onboarding_progreso`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `onboarding_progreso` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `firma_id` bigint(20) unsigned NOT NULL,
  `paso` varchar(80) NOT NULL,
  `estado` varchar(30) NOT NULL DEFAULT 'pendiente',
  `completed_at` datetime(6) DEFAULT NULL,
  `completed_by_usuario_id` bigint(20) unsigned DEFAULT NULL,
  `metadata_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata_json`)),
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_onboarding_firma_paso` (`firma_id`,`paso`),
  KEY `idx_onboarding_estado` (`firma_id`,`estado`),
  KEY `fk_onboarding_usuario` (`completed_by_usuario_id`),
  CONSTRAINT `fk_onboarding_firma` FOREIGN KEY (`firma_id`) REFERENCES `firmas` (`id`),
  CONSTRAINT `fk_onboarding_usuario` FOREIGN KEY (`completed_by_usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pagos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pagos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `firma_id` bigint(20) unsigned NOT NULL,
  `cliente_id` bigint(20) unsigned NOT NULL,
  `caso_id` bigint(20) unsigned DEFAULT NULL,
  `honorario_id` bigint(20) unsigned DEFAULT NULL,
  `fecha_pago` date NOT NULL,
  `monto` decimal(14,2) NOT NULL,
  `moneda` char(3) NOT NULL DEFAULT 'COP',
  `metodo_pago` varchar(80) NOT NULL,
  `referencia` varchar(180) DEFAULT NULL,
  `referencia_hash` char(64) DEFAULT NULL,
  `estado` varchar(30) NOT NULL DEFAULT 'registrado',
  `observaciones` text DEFAULT NULL,
  `registrado_por_usuario_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  `deleted_at` datetime(6) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pagos_id_firma` (`id`,`firma_id`),
  KEY `idx_pagos_cliente_fecha` (`firma_id`,`cliente_id`,`fecha_pago`,`deleted_at`),
  KEY `idx_pagos_caso_fecha` (`firma_id`,`caso_id`,`fecha_pago`,`deleted_at`),
  KEY `idx_pagos_honorario` (`firma_id`,`honorario_id`,`deleted_at`),
  KEY `idx_pagos_referencia_hash` (`firma_id`,`referencia_hash`,`deleted_at`),
  KEY `idx_pagos_usuario` (`registrado_por_usuario_id`),
  KEY `fk_pagos_cliente` (`cliente_id`,`firma_id`),
  KEY `fk_pagos_caso` (`caso_id`,`firma_id`),
  KEY `fk_pagos_honorario` (`honorario_id`,`firma_id`),
  CONSTRAINT `fk_pagos_caso` FOREIGN KEY (`caso_id`, `firma_id`) REFERENCES `casos` (`id`, `firma_id`),
  CONSTRAINT `fk_pagos_cliente` FOREIGN KEY (`cliente_id`, `firma_id`) REFERENCES `clientes` (`id`, `firma_id`),
  CONSTRAINT `fk_pagos_firma` FOREIGN KEY (`firma_id`) REFERENCES `firmas` (`id`),
  CONSTRAINT `fk_pagos_honorario` FOREIGN KEY (`honorario_id`, `firma_id`) REFERENCES `honorarios` (`id`, `firma_id`),
  CONSTRAINT `fk_pagos_usuario` FOREIGN KEY (`registrado_por_usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_resets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `firma_id` bigint(20) unsigned DEFAULT NULL,
  `usuario_id` bigint(20) unsigned NOT NULL,
  `token_hash` char(64) NOT NULL,
  `requested_ip` varchar(45) DEFAULT NULL,
  `requested_user_agent` varchar(255) DEFAULT NULL,
  `expires_at` datetime(6) NOT NULL,
  `used_at` datetime(6) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_password_resets_token` (`token_hash`),
  KEY `fk_password_resets_firma` (`firma_id`),
  KEY `idx_password_resets_usuario` (`usuario_id`,`used_at`,`expires_at`),
  KEY `idx_password_resets_expiration` (`expires_at`,`used_at`),
  CONSTRAINT `fk_password_resets_firma` FOREIGN KEY (`firma_id`) REFERENCES `firmas` (`id`),
  CONSTRAINT `fk_password_resets_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `permisos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `permisos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(120) NOT NULL,
  `modulo` varchar(80) NOT NULL,
  `accion` varchar(80) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_permisos_codigo` (`codigo`),
  KEY `idx_permisos_modulo` (`modulo`,`accion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `plan_limites`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `plan_limites` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `plan_id` bigint(20) unsigned NOT NULL,
  `recurso` varchar(100) NOT NULL,
  `limite` int(10) unsigned DEFAULT NULL,
  `politica` varchar(20) NOT NULL DEFAULT 'block',
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_plan_limites_recurso` (`plan_id`,`recurso`),
  CONSTRAINT `fk_plan_limites_plan` FOREIGN KEY (`plan_id`) REFERENCES `planes` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `planes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `planes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(60) NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `estado` varchar(30) NOT NULL DEFAULT 'activo',
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  `deleted_at` datetime(6) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_planes_codigo` (`codigo`),
  KEY `idx_planes_estado` (`estado`,`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `portal_accesos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `portal_accesos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `firma_id` bigint(20) unsigned NOT NULL,
  `usuario_id` bigint(20) unsigned NOT NULL,
  `cliente_id` bigint(20) unsigned NOT NULL,
  `accion` varchar(80) NOT NULL,
  `entidad_tipo` varchar(40) DEFAULT NULL,
  `entidad_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id`),
  KEY `idx_portal_accesos_cliente_fecha` (`firma_id`,`cliente_id`,`created_at`),
  KEY `idx_portal_accesos_usuario_fecha` (`firma_id`,`usuario_id`,`created_at`),
  KEY `idx_portal_accesos_usuario_fk` (`usuario_id`,`firma_id`),
  KEY `idx_portal_accesos_cliente_fk` (`cliente_id`,`firma_id`),
  CONSTRAINT `fk_portal_accesos_cliente` FOREIGN KEY (`cliente_id`, `firma_id`) REFERENCES `clientes` (`id`, `firma_id`),
  CONSTRAINT `fk_portal_accesos_firma` FOREIGN KEY (`firma_id`) REFERENCES `firmas` (`id`),
  CONSTRAINT `fk_portal_accesos_usuario` FOREIGN KEY (`usuario_id`, `firma_id`) REFERENCES `usuarios` (`id`, `firma_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `portal_observaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `portal_observaciones` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `firma_id` bigint(20) unsigned NOT NULL,
  `cliente_id` bigint(20) unsigned NOT NULL,
  `caso_id` bigint(20) unsigned DEFAULT NULL,
  `documento_id` bigint(20) unsigned DEFAULT NULL,
  `honorario_id` bigint(20) unsigned DEFAULT NULL,
  `pago_id` bigint(20) unsigned DEFAULT NULL,
  `gasto_id` bigint(20) unsigned DEFAULT NULL,
  `observacion_publica` text DEFAULT NULL,
  `observacion_interna` text DEFAULT NULL,
  `created_by_usuario_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  PRIMARY KEY (`id`),
  KEY `idx_portal_observaciones_cliente` (`firma_id`,`cliente_id`,`created_at`),
  KEY `idx_portal_observaciones_cliente_fk` (`cliente_id`,`firma_id`),
  KEY `idx_portal_observaciones_caso_fk` (`caso_id`,`firma_id`),
  KEY `idx_portal_observaciones_documento_fk` (`documento_id`,`firma_id`),
  KEY `idx_portal_observaciones_honorario_fk` (`honorario_id`,`firma_id`),
  KEY `idx_portal_observaciones_pago_fk` (`pago_id`,`firma_id`),
  KEY `idx_portal_observaciones_gasto_fk` (`gasto_id`,`firma_id`),
  KEY `fk_portal_observaciones_usuario` (`created_by_usuario_id`),
  CONSTRAINT `fk_portal_observaciones_caso` FOREIGN KEY (`caso_id`, `firma_id`) REFERENCES `casos` (`id`, `firma_id`),
  CONSTRAINT `fk_portal_observaciones_cliente` FOREIGN KEY (`cliente_id`, `firma_id`) REFERENCES `clientes` (`id`, `firma_id`),
  CONSTRAINT `fk_portal_observaciones_documento` FOREIGN KEY (`documento_id`, `firma_id`) REFERENCES `documentos` (`id`, `firma_id`),
  CONSTRAINT `fk_portal_observaciones_firma` FOREIGN KEY (`firma_id`) REFERENCES `firmas` (`id`),
  CONSTRAINT `fk_portal_observaciones_gasto` FOREIGN KEY (`gasto_id`, `firma_id`) REFERENCES `gastos` (`id`, `firma_id`),
  CONSTRAINT `fk_portal_observaciones_honorario` FOREIGN KEY (`honorario_id`, `firma_id`) REFERENCES `honorarios` (`id`, `firma_id`),
  CONSTRAINT `fk_portal_observaciones_pago` FOREIGN KEY (`pago_id`, `firma_id`) REFERENCES `pagos` (`id`, `firma_id`),
  CONSTRAINT `fk_portal_observaciones_usuario` FOREIGN KEY (`created_by_usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `portal_usuario_clientes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `portal_usuario_clientes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `firma_id` bigint(20) unsigned NOT NULL,
  `usuario_id` bigint(20) unsigned NOT NULL,
  `cliente_id` bigint(20) unsigned NOT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'activo',
  `created_by_usuario_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_portal_usuario_cliente` (`firma_id`,`usuario_id`,`cliente_id`),
  KEY `idx_portal_usuario_cliente_estado` (`firma_id`,`usuario_id`,`estado`),
  KEY `idx_portal_usuario_clientes_usuario_fk` (`usuario_id`,`firma_id`),
  KEY `idx_portal_usuario_clientes_cliente_fk` (`cliente_id`,`firma_id`),
  KEY `fk_portal_usuario_clientes_creador` (`created_by_usuario_id`),
  CONSTRAINT `fk_portal_usuario_clientes_cliente` FOREIGN KEY (`cliente_id`, `firma_id`) REFERENCES `clientes` (`id`, `firma_id`),
  CONSTRAINT `fk_portal_usuario_clientes_creador` FOREIGN KEY (`created_by_usuario_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_portal_usuario_clientes_firma` FOREIGN KEY (`firma_id`) REFERENCES `firmas` (`id`),
  CONSTRAINT `fk_portal_usuario_clientes_usuario` FOREIGN KEY (`usuario_id`, `firma_id`) REFERENCES `usuarios` (`id`, `firma_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `prospectos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `prospectos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `firma_id` bigint(20) unsigned NOT NULL,
  `nombre` varchar(180) NOT NULL,
  `nombre_normalizado` varchar(180) NOT NULL,
  `tipo_persona` varchar(20) NOT NULL DEFAULT 'natural',
  `email` varchar(254) DEFAULT NULL,
  `telefono` varchar(60) DEFAULT NULL,
  `tipo_documento` varchar(40) DEFAULT NULL,
  `numero_documento` varchar(80) DEFAULT NULL,
  `documento_normalizado` varchar(80) DEFAULT NULL,
  `documento_hash` char(64) DEFAULT NULL,
  `empresa` varchar(180) DEFAULT NULL,
  `empresa_normalizada` varchar(180) DEFAULT NULL,
  `fuente` varchar(120) DEFAULT NULL,
  `estado` varchar(30) NOT NULL DEFAULT 'nuevo',
  `responsable_usuario_id` bigint(20) unsigned DEFAULT NULL,
  `valor_estimado` decimal(14,2) DEFAULT NULL,
  `notas` text DEFAULT NULL,
  `tratamiento_datos_autorizado` tinyint(1) NOT NULL DEFAULT 0,
  `converted_cliente_id` bigint(20) unsigned DEFAULT NULL,
  `converted_at` datetime(6) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  `deleted_at` datetime(6) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_prospectos_id_firma` (`id`,`firma_id`),
  UNIQUE KEY `uq_prospectos_cliente_convertido` (`firma_id`,`converted_cliente_id`),
  KEY `idx_prospectos_pipeline` (`firma_id`,`estado`,`deleted_at`,`updated_at`),
  KEY `idx_prospectos_nombre` (`firma_id`,`nombre_normalizado`,`deleted_at`),
  KEY `idx_prospectos_documento` (`firma_id`,`documento_hash`,`deleted_at`),
  KEY `idx_prospectos_responsable` (`firma_id`,`responsable_usuario_id`,`estado`),
  KEY `fk_prospectos_responsable` (`responsable_usuario_id`,`firma_id`),
  KEY `fk_prospectos_cliente` (`converted_cliente_id`,`firma_id`),
  CONSTRAINT `fk_prospectos_cliente` FOREIGN KEY (`converted_cliente_id`, `firma_id`) REFERENCES `clientes` (`id`, `firma_id`),
  CONSTRAINT `fk_prospectos_firma` FOREIGN KEY (`firma_id`) REFERENCES `firmas` (`id`),
  CONSTRAINT `fk_prospectos_responsable` FOREIGN KEY (`responsable_usuario_id`, `firma_id`) REFERENCES `usuarios` (`id`, `firma_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `rol_permiso`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rol_permiso` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `firma_id` bigint(20) unsigned NOT NULL,
  `rol_id` bigint(20) unsigned NOT NULL,
  `permiso_id` bigint(20) unsigned NOT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rol_permiso` (`firma_id`,`rol_id`,`permiso_id`),
  KEY `fk_rol_permiso_rol_firma` (`rol_id`,`firma_id`),
  KEY `idx_rol_permiso_permiso` (`permiso_id`),
  CONSTRAINT `fk_rol_permiso_firma` FOREIGN KEY (`firma_id`) REFERENCES `firmas` (`id`),
  CONSTRAINT `fk_rol_permiso_permiso` FOREIGN KEY (`permiso_id`) REFERENCES `permisos` (`id`),
  CONSTRAINT `fk_rol_permiso_rol_firma` FOREIGN KEY (`rol_id`, `firma_id`) REFERENCES `roles` (`id`, `firma_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `firma_id` bigint(20) unsigned NOT NULL,
  `codigo` varchar(80) NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `estado` varchar(30) NOT NULL DEFAULT 'activo',
  `is_protected` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  `deleted_at` datetime(6) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_roles_id_firma` (`id`,`firma_id`),
  UNIQUE KEY `uq_roles_firma_codigo` (`firma_id`,`codigo`),
  KEY `idx_roles_firma_estado` (`firma_id`,`estado`,`deleted_at`),
  CONSTRAINT `fk_roles_firma` FOREIGN KEY (`firma_id`) REFERENCES `firmas` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tareas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tareas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `firma_id` bigint(20) unsigned NOT NULL,
  `caso_id` bigint(20) unsigned DEFAULT NULL,
  `termino_id` bigint(20) unsigned DEFAULT NULL,
  `responsable_usuario_id` bigint(20) unsigned DEFAULT NULL,
  `titulo` varchar(180) NOT NULL,
  `titulo_normalizado` varchar(180) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `prioridad` varchar(30) NOT NULL DEFAULT 'media',
  `estado` varchar(30) NOT NULL DEFAULT 'pendiente',
  `fecha_vencimiento` date DEFAULT NULL,
  `completed_at` datetime(6) DEFAULT NULL,
  `completed_by_usuario_id` bigint(20) unsigned DEFAULT NULL,
  `reassigned_at` datetime(6) DEFAULT NULL,
  `reassigned_from_usuario_id` bigint(20) unsigned DEFAULT NULL,
  `reassigned_to_usuario_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  `deleted_at` datetime(6) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tareas_id_firma` (`id`,`firma_id`),
  KEY `idx_tareas_estado_vencimiento` (`firma_id`,`estado`,`fecha_vencimiento`,`deleted_at`),
  KEY `idx_tareas_caso_estado` (`firma_id`,`caso_id`,`estado`,`deleted_at`),
  KEY `idx_tareas_termino` (`firma_id`,`termino_id`,`deleted_at`),
  KEY `idx_tareas_responsable` (`firma_id`,`responsable_usuario_id`,`estado`,`deleted_at`),
  KEY `idx_tareas_titulo` (`firma_id`,`titulo_normalizado`,`deleted_at`),
  KEY `fk_tareas_caso` (`caso_id`,`firma_id`),
  KEY `fk_tareas_termino` (`termino_id`,`firma_id`),
  KEY `fk_tareas_responsable` (`responsable_usuario_id`,`firma_id`),
  KEY `fk_tareas_completed_by` (`completed_by_usuario_id`),
  KEY `fk_tareas_reassigned_from` (`reassigned_from_usuario_id`),
  KEY `fk_tareas_reassigned_to` (`reassigned_to_usuario_id`),
  CONSTRAINT `fk_tareas_caso` FOREIGN KEY (`caso_id`, `firma_id`) REFERENCES `casos` (`id`, `firma_id`),
  CONSTRAINT `fk_tareas_completed_by` FOREIGN KEY (`completed_by_usuario_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_tareas_firma` FOREIGN KEY (`firma_id`) REFERENCES `firmas` (`id`),
  CONSTRAINT `fk_tareas_reassigned_from` FOREIGN KEY (`reassigned_from_usuario_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_tareas_reassigned_to` FOREIGN KEY (`reassigned_to_usuario_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_tareas_responsable` FOREIGN KEY (`responsable_usuario_id`, `firma_id`) REFERENCES `usuarios` (`id`, `firma_id`),
  CONSTRAINT `fk_tareas_termino` FOREIGN KEY (`termino_id`, `firma_id`) REFERENCES `terminos` (`id`, `firma_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `terminos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `terminos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `firma_id` bigint(20) unsigned NOT NULL,
  `caso_id` bigint(20) unsigned DEFAULT NULL,
  `tarea_id` bigint(20) unsigned DEFAULT NULL,
  `responsable_usuario_id` bigint(20) unsigned DEFAULT NULL,
  `titulo` varchar(180) NOT NULL,
  `titulo_normalizado` varchar(180) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_vencimiento` date NOT NULL,
  `timezone` varchar(64) NOT NULL,
  `prioridad` varchar(30) NOT NULL DEFAULT 'media',
  `estado` varchar(30) NOT NULL DEFAULT 'vigente',
  `alerta_dias` smallint(5) unsigned NOT NULL DEFAULT 3,
  `cumplido_at` datetime(6) DEFAULT NULL,
  `cumplido_por_usuario_id` bigint(20) unsigned DEFAULT NULL,
  `observacion_cumplimiento` varchar(1000) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  `deleted_at` datetime(6) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_terminos_id_firma` (`id`,`firma_id`),
  KEY `idx_terminos_estado_vencimiento` (`firma_id`,`estado`,`fecha_vencimiento`,`deleted_at`),
  KEY `idx_terminos_caso_vencimiento` (`firma_id`,`caso_id`,`fecha_vencimiento`,`deleted_at`),
  KEY `idx_terminos_tarea` (`firma_id`,`tarea_id`,`deleted_at`),
  KEY `idx_terminos_responsable` (`firma_id`,`responsable_usuario_id`,`fecha_vencimiento`,`deleted_at`),
  KEY `idx_terminos_titulo` (`firma_id`,`titulo_normalizado`,`deleted_at`),
  KEY `fk_terminos_caso` (`caso_id`,`firma_id`),
  KEY `fk_terminos_responsable` (`responsable_usuario_id`,`firma_id`),
  KEY `fk_terminos_cumplido_por` (`cumplido_por_usuario_id`),
  KEY `fk_terminos_tarea` (`tarea_id`,`firma_id`),
  CONSTRAINT `fk_terminos_caso` FOREIGN KEY (`caso_id`, `firma_id`) REFERENCES `casos` (`id`, `firma_id`),
  CONSTRAINT `fk_terminos_cumplido_por` FOREIGN KEY (`cumplido_por_usuario_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_terminos_firma` FOREIGN KEY (`firma_id`) REFERENCES `firmas` (`id`),
  CONSTRAINT `fk_terminos_responsable` FOREIGN KEY (`responsable_usuario_id`, `firma_id`) REFERENCES `usuarios` (`id`, `firma_id`),
  CONSTRAINT `fk_terminos_tarea` FOREIGN KEY (`tarea_id`, `firma_id`) REFERENCES `tareas` (`id`, `firma_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ticket_mensajes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ticket_mensajes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `firma_id` bigint(20) unsigned NOT NULL,
  `ticket_id` bigint(20) unsigned NOT NULL,
  `autor_usuario_id` bigint(20) unsigned DEFAULT NULL,
  `visibilidad` varchar(20) NOT NULL DEFAULT 'firma',
  `mensaje` text NOT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id`),
  KEY `idx_ticket_mensajes_ticket` (`firma_id`,`ticket_id`,`created_at`),
  KEY `idx_ticket_mensajes_ticket_fk` (`ticket_id`,`firma_id`),
  KEY `fk_ticket_mensajes_autor` (`autor_usuario_id`),
  CONSTRAINT `fk_ticket_mensajes_autor` FOREIGN KEY (`autor_usuario_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_ticket_mensajes_firma` FOREIGN KEY (`firma_id`) REFERENCES `firmas` (`id`),
  CONSTRAINT `fk_ticket_mensajes_ticket` FOREIGN KEY (`ticket_id`, `firma_id`) REFERENCES `tickets_soporte` (`id`, `firma_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tickets_soporte`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tickets_soporte` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `firma_id` bigint(20) unsigned NOT NULL,
  `creado_por_usuario_id` bigint(20) unsigned DEFAULT NULL,
  `asignado_a_usuario_id` bigint(20) unsigned DEFAULT NULL,
  `asunto` varchar(180) NOT NULL,
  `categoria` varchar(80) DEFAULT NULL,
  `prioridad` varchar(30) NOT NULL DEFAULT 'media',
  `estado` varchar(30) NOT NULL DEFAULT 'abierto',
  `contexto_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`contexto_json`)),
  `closed_at` datetime(6) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  `deleted_at` datetime(6) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tickets_soporte_id_firma` (`id`,`firma_id`),
  KEY `idx_tickets_firma_estado` (`firma_id`,`estado`,`prioridad`,`deleted_at`),
  KEY `idx_tickets_creador` (`firma_id`,`creado_por_usuario_id`,`estado`,`deleted_at`),
  KEY `fk_tickets_soporte_creador` (`creado_por_usuario_id`),
  KEY `fk_tickets_soporte_asignado` (`asignado_a_usuario_id`),
  CONSTRAINT `fk_tickets_soporte_asignado` FOREIGN KEY (`asignado_a_usuario_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_tickets_soporte_creador` FOREIGN KEY (`creado_por_usuario_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_tickets_soporte_firma` FOREIGN KEY (`firma_id`) REFERENCES `firmas` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_sessions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `firma_id` bigint(20) unsigned DEFAULT NULL,
  `usuario_id` bigint(20) unsigned NOT NULL,
  `session_hash` char(64) NOT NULL,
  `fingerprint_hash` char(64) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `last_activity_at` datetime(6) NOT NULL,
  `expires_at` datetime(6) NOT NULL,
  `revoked_at` datetime(6) DEFAULT NULL,
  `revoked_reason` varchar(120) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_sessions_hash` (`session_hash`),
  KEY `idx_user_sessions_usuario` (`usuario_id`,`revoked_at`,`expires_at`),
  KEY `idx_user_sessions_firma` (`firma_id`,`revoked_at`),
  KEY `idx_user_sessions_cleanup` (`expires_at`,`revoked_at`),
  CONSTRAINT `fk_user_sessions_firma` FOREIGN KEY (`firma_id`) REFERENCES `firmas` (`id`),
  CONSTRAINT `fk_user_sessions_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `usuario_cambios_sensibles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `usuario_cambios_sensibles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `firma_id` bigint(20) unsigned NOT NULL,
  `usuario_afectado_id` bigint(20) unsigned NOT NULL,
  `usuario_actor_id` bigint(20) unsigned DEFAULT NULL,
  `campo` varchar(80) NOT NULL,
  `valor_anterior_enmascarado` varchar(255) DEFAULT NULL,
  `valor_nuevo_enmascarado` varchar(255) DEFAULT NULL,
  `valor_anterior_hash` char(64) DEFAULT NULL,
  `valor_nuevo_hash` char(64) DEFAULT NULL,
  `origen` varchar(20) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id`),
  KEY `fk_usuario_cambios_afectado_firma` (`usuario_afectado_id`,`firma_id`),
  KEY `fk_usuario_cambios_actor_firma` (`usuario_actor_id`,`firma_id`),
  KEY `idx_usuario_cambios_afectado_fecha` (`firma_id`,`usuario_afectado_id`,`created_at`),
  KEY `idx_usuario_cambios_actor_fecha` (`firma_id`,`usuario_actor_id`,`created_at`),
  KEY `idx_usuario_cambios_campo_fecha` (`firma_id`,`campo`,`created_at`),
  CONSTRAINT `fk_usuario_cambios_actor_firma` FOREIGN KEY (`usuario_actor_id`, `firma_id`) REFERENCES `usuarios` (`id`, `firma_id`),
  CONSTRAINT `fk_usuario_cambios_afectado_firma` FOREIGN KEY (`usuario_afectado_id`, `firma_id`) REFERENCES `usuarios` (`id`, `firma_id`),
  CONSTRAINT `fk_usuario_cambios_firma` FOREIGN KEY (`firma_id`) REFERENCES `firmas` (`id`),
  CONSTRAINT `chk_usuario_cambios_origen` CHECK (`origen` in ('mi_perfil','usuarios')),
  CONSTRAINT `chk_usuario_cambios_hash_anterior` CHECK (`valor_anterior_hash` is null or `valor_anterior_hash` regexp '^[0-9a-f]{64}$'),
  CONSTRAINT `chk_usuario_cambios_hash_nuevo` CHECK (`valor_nuevo_hash` is null or `valor_nuevo_hash` regexp '^[0-9a-f]{64}$'),
  CONSTRAINT `chk_usuario_cambios_dato_sensible` CHECK (`campo` not in ('numero_documento','numero_tarjeta_profesional') or (`valor_anterior_enmascarado` is null or locate('*',`valor_anterior_enmascarado`) > 0 and `valor_anterior_hash` is not null) and (`valor_nuevo_enmascarado` is null or locate('*',`valor_nuevo_enmascarado`) > 0 and `valor_nuevo_hash` is not null))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `usuario_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `usuario_roles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `firma_id` bigint(20) unsigned NOT NULL,
  `usuario_id` bigint(20) unsigned NOT NULL,
  `rol_id` bigint(20) unsigned NOT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_usuario_roles` (`firma_id`,`usuario_id`,`rol_id`),
  KEY `fk_usuario_roles_usuario_firma` (`usuario_id`,`firma_id`),
  KEY `fk_usuario_roles_rol_firma` (`rol_id`,`firma_id`),
  KEY `idx_usuario_roles_rol` (`firma_id`,`rol_id`),
  CONSTRAINT `fk_usuario_roles_firma` FOREIGN KEY (`firma_id`) REFERENCES `firmas` (`id`),
  CONSTRAINT `fk_usuario_roles_rol_firma` FOREIGN KEY (`rol_id`, `firma_id`) REFERENCES `roles` (`id`, `firma_id`),
  CONSTRAINT `fk_usuario_roles_usuario_firma` FOREIGN KEY (`usuario_id`, `firma_id`) REFERENCES `usuarios` (`id`, `firma_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `usuarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `usuarios` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `firma_id` bigint(20) unsigned DEFAULT NULL,
  `nombre` varchar(160) NOT NULL,
  `nombres` varchar(160) DEFAULT NULL,
  `apellidos` varchar(160) DEFAULT NULL,
  `tipo_documento_id` bigint(20) unsigned DEFAULT NULL,
  `numero_documento` varchar(80) DEFAULT NULL,
  `numero_documento_normalizado` varchar(80) DEFAULT NULL,
  `telefono` varchar(40) DEFAULT NULL,
  `cargo` varchar(160) DEFAULT NULL,
  `foto_perfil_path` varchar(500) DEFAULT NULL,
  `es_abogado` tinyint(1) NOT NULL DEFAULT 0,
  `tiene_tarjeta_profesional` tinyint(1) NOT NULL DEFAULT 0,
  `numero_tarjeta_profesional` varchar(80) DEFAULT NULL,
  `numero_tarjeta_profesional_normalizado` varchar(80) DEFAULT NULL,
  `tarjeta_profesional_verificacion_estado` varchar(20) DEFAULT NULL,
  `fecha_verificacion_tarjeta` datetime(6) DEFAULT NULL,
  `usuario_verificador_tarjeta_id` bigint(20) unsigned DEFAULT NULL,
  `observacion_verificacion_tarjeta` varchar(1000) DEFAULT NULL,
  `email` varchar(254) NOT NULL,
  `email_normalizado` varchar(254) NOT NULL,
  `email_scope` varchar(320) NOT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `tipo` varchar(40) NOT NULL DEFAULT 'interno',
  `estado` varchar(30) NOT NULL DEFAULT 'activo',
  `must_change_password` tinyint(1) NOT NULL DEFAULT 0,
  `invited_at` datetime(6) DEFAULT NULL,
  `deactivated_at` datetime(6) DEFAULT NULL,
  `last_login_at` datetime(6) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  `deleted_at` datetime(6) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_usuarios_email_scope` (`email_scope`),
  UNIQUE KEY `uq_usuarios_id_firma` (`id`,`firma_id`),
  UNIQUE KEY `uq_usuarios_documento_firma` (`firma_id`,`tipo_documento_id`,`numero_documento_normalizado`),
  KEY `idx_usuarios_firma_estado` (`firma_id`,`estado`,`deleted_at`),
  KEY `idx_usuarios_email_normalizado` (`email_normalizado`),
  KEY `idx_usuarios_tarjeta_pendiente` (`firma_id`,`tarjeta_profesional_verificacion_estado`,`deleted_at`),
  KEY `idx_usuarios_tipo_documento` (`tipo_documento_id`),
  KEY `idx_usuarios_verificador_firma` (`usuario_verificador_tarjeta_id`,`firma_id`),
  CONSTRAINT `fk_usuarios_firma` FOREIGN KEY (`firma_id`) REFERENCES `firmas` (`id`),
  CONSTRAINT `fk_usuarios_tipo_documento` FOREIGN KEY (`tipo_documento_id`) REFERENCES `catalogo_items` (`id`),
  CONSTRAINT `fk_usuarios_verificador_firma` FOREIGN KEY (`usuario_verificador_tarjeta_id`, `firma_id`) REFERENCES `usuarios` (`id`, `firma_id`),
  CONSTRAINT `chk_usuarios_es_abogado` CHECK (`es_abogado` in (0,1)),
  CONSTRAINT `chk_usuarios_tiene_tarjeta` CHECK (`tiene_tarjeta_profesional` in (0,1)),
  CONSTRAINT `chk_usuarios_tarjeta_consistencia` CHECK (`tiene_tarjeta_profesional` = 0 and `numero_tarjeta_profesional` is null and `numero_tarjeta_profesional_normalizado` is null and `tarjeta_profesional_verificacion_estado` is null and `fecha_verificacion_tarjeta` is null and `usuario_verificador_tarjeta_id` is null and `observacion_verificacion_tarjeta` is null or `tiene_tarjeta_profesional` = 1 and nullif(trim(`numero_tarjeta_profesional`),'') is not null and nullif(trim(`numero_tarjeta_profesional_normalizado`),'') is not null and `tarjeta_profesional_verificacion_estado` in ('pendiente','verificada','rechazada') and (`tarjeta_profesional_verificacion_estado` = 'pendiente' and `fecha_verificacion_tarjeta` is null and `usuario_verificador_tarjeta_id` is null and `observacion_verificacion_tarjeta` is null or `tarjeta_profesional_verificacion_estado` in ('verificada','rechazada') and `fecha_verificacion_tarjeta` is not null and `usuario_verificador_tarjeta_id` is not null))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
