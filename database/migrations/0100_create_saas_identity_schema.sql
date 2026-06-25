-- @up
CREATE TABLE planes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo VARCHAR(60) NOT NULL,
    nombre VARCHAR(120) NOT NULL,
    descripcion TEXT NULL,
    estado VARCHAR(30) NOT NULL DEFAULT 'activo',
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    deleted_at DATETIME(6) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_planes_codigo (codigo),
    KEY idx_planes_estado (estado, deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE permisos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo VARCHAR(120) NOT NULL,
    modulo VARCHAR(80) NOT NULL,
    accion VARCHAR(80) NOT NULL,
    descripcion VARCHAR(255) NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_permisos_codigo (codigo),
    KEY idx_permisos_modulo (modulo, accion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE documentos_legales (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tipo VARCHAR(80) NOT NULL,
    version VARCHAR(40) NOT NULL,
    titulo VARCHAR(180) NOT NULL,
    contenido LONGTEXT NOT NULL,
    checksum CHAR(64) NOT NULL,
    estado VARCHAR(30) NOT NULL DEFAULT 'borrador',
    published_at DATETIME(6) NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_documentos_legales_tipo_version (tipo, version),
    KEY idx_documentos_legales_vigencia (tipo, estado, published_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE firmas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL,
    nombre VARCHAR(180) NOT NULL,
    slug VARCHAR(120) NOT NULL,
    estado VARCHAR(30) NOT NULL DEFAULT 'activa',
    timezone VARCHAR(64) NOT NULL DEFAULT 'UTC',
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    deleted_at DATETIME(6) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_firmas_uuid (uuid),
    UNIQUE KEY uq_firmas_slug (slug),
    KEY idx_firmas_estado (estado, deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE firma_planes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id BIGINT UNSIGNED NOT NULL,
    plan_id BIGINT UNSIGNED NOT NULL,
    estado VARCHAR(30) NOT NULL DEFAULT 'activo',
    starts_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    ends_at DATETIME(6) NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    CONSTRAINT fk_firma_planes_firma FOREIGN KEY (firma_id) REFERENCES firmas (id),
    CONSTRAINT fk_firma_planes_plan FOREIGN KEY (plan_id) REFERENCES planes (id),
    KEY idx_firma_planes_actual (firma_id, estado, ends_at),
    KEY idx_firma_planes_plan (plan_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE firma_limites (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id BIGINT UNSIGNED NOT NULL,
    plan_id BIGINT UNSIGNED NULL,
    recurso VARCHAR(100) NOT NULL,
    limite INT UNSIGNED NULL,
    politica VARCHAR(20) NOT NULL DEFAULT 'block',
    motivo VARCHAR(255) NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_firma_limites_recurso (firma_id, recurso),
    CONSTRAINT fk_firma_limites_firma FOREIGN KEY (firma_id) REFERENCES firmas (id),
    CONSTRAINT fk_firma_limites_plan FOREIGN KEY (plan_id) REFERENCES planes (id),
    KEY idx_firma_limites_plan (plan_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE usuarios (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id BIGINT UNSIGNED NULL,
    nombre VARCHAR(160) NOT NULL,
    email VARCHAR(254) NOT NULL,
    email_normalizado VARCHAR(254) NOT NULL,
    password_hash VARCHAR(255) NULL,
    tipo VARCHAR(40) NOT NULL DEFAULT 'interno',
    estado VARCHAR(30) NOT NULL DEFAULT 'activo',
    must_change_password TINYINT(1) NOT NULL DEFAULT 0,
    last_login_at DATETIME(6) NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    deleted_at DATETIME(6) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_usuarios_id_firma (id, firma_id),
    CONSTRAINT fk_usuarios_firma FOREIGN KEY (firma_id) REFERENCES firmas (id),
    KEY idx_usuarios_firma_estado (firma_id, estado, deleted_at),
    KEY idx_usuarios_email_normalizado (email_normalizado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE roles (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id BIGINT UNSIGNED NOT NULL,
    codigo VARCHAR(80) NOT NULL,
    nombre VARCHAR(120) NOT NULL,
    descripcion VARCHAR(255) NULL,
    estado VARCHAR(30) NOT NULL DEFAULT 'activo',
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    deleted_at DATETIME(6) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_roles_id_firma (id, firma_id),
    UNIQUE KEY uq_roles_firma_codigo (firma_id, codigo),
    CONSTRAINT fk_roles_firma FOREIGN KEY (firma_id) REFERENCES firmas (id),
    KEY idx_roles_firma_estado (firma_id, estado, deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE usuario_roles (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id BIGINT UNSIGNED NOT NULL,
    usuario_id BIGINT UNSIGNED NOT NULL,
    rol_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_usuario_roles (firma_id, usuario_id, rol_id),
    CONSTRAINT fk_usuario_roles_firma FOREIGN KEY (firma_id) REFERENCES firmas (id),
    CONSTRAINT fk_usuario_roles_usuario_firma FOREIGN KEY (usuario_id, firma_id) REFERENCES usuarios (id, firma_id),
    CONSTRAINT fk_usuario_roles_rol_firma FOREIGN KEY (rol_id, firma_id) REFERENCES roles (id, firma_id),
    KEY idx_usuario_roles_rol (firma_id, rol_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rol_permiso (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id BIGINT UNSIGNED NOT NULL,
    rol_id BIGINT UNSIGNED NOT NULL,
    permiso_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_rol_permiso (firma_id, rol_id, permiso_id),
    CONSTRAINT fk_rol_permiso_firma FOREIGN KEY (firma_id) REFERENCES firmas (id),
    CONSTRAINT fk_rol_permiso_rol_firma FOREIGN KEY (rol_id, firma_id) REFERENCES roles (id, firma_id),
    CONSTRAINT fk_rol_permiso_permiso FOREIGN KEY (permiso_id) REFERENCES permisos (id),
    KEY idx_rol_permiso_permiso (permiso_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_sessions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id BIGINT UNSIGNED NULL,
    usuario_id BIGINT UNSIGNED NOT NULL,
    session_hash CHAR(64) NOT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    last_activity_at DATETIME(6) NOT NULL,
    expires_at DATETIME(6) NOT NULL,
    revoked_at DATETIME(6) NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_user_sessions_hash (session_hash),
    CONSTRAINT fk_user_sessions_firma FOREIGN KEY (firma_id) REFERENCES firmas (id),
    CONSTRAINT fk_user_sessions_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id),
    KEY idx_user_sessions_usuario (usuario_id, revoked_at, expires_at),
    KEY idx_user_sessions_firma (firma_id, revoked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE password_resets (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id BIGINT UNSIGNED NULL,
    usuario_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME(6) NOT NULL,
    used_at DATETIME(6) NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_password_resets_token (token_hash),
    CONSTRAINT fk_password_resets_firma FOREIGN KEY (firma_id) REFERENCES firmas (id),
    CONSTRAINT fk_password_resets_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id),
    KEY idx_password_resets_usuario (usuario_id, used_at, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE login_attempts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id BIGINT UNSIGNED NULL,
    email_hash CHAR(64) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    successful TINYINT(1) NOT NULL DEFAULT 0,
    attempted_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    CONSTRAINT fk_login_attempts_firma FOREIGN KEY (firma_id) REFERENCES firmas (id),
    KEY idx_login_attempts_rate (email_hash, ip_address, attempted_at),
    KEY idx_login_attempts_firma (firma_id, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE auditoria (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id BIGINT UNSIGNED NULL,
    usuario_id BIGINT UNSIGNED NULL,
    accion VARCHAR(120) NOT NULL,
    modulo VARCHAR(80) NOT NULL,
    entidad_tipo VARCHAR(100) NULL,
    entidad_id VARCHAR(80) NULL,
    severidad VARCHAR(20) NOT NULL DEFAULT 'info',
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    metadata JSON NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    CONSTRAINT fk_auditoria_firma FOREIGN KEY (firma_id) REFERENCES firmas (id),
    CONSTRAINT fk_auditoria_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id),
    KEY idx_auditoria_firma_fecha (firma_id, created_at),
    KEY idx_auditoria_usuario_fecha (usuario_id, created_at),
    KEY idx_auditoria_modulo_accion (modulo, accion, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE catalogos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id BIGINT UNSIGNED NULL,
    alcance VARCHAR(20) NOT NULL DEFAULT 'firma',
    codigo VARCHAR(100) NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    estado VARCHAR(30) NOT NULL DEFAULT 'activo',
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    deleted_at DATETIME(6) NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_catalogos_firma FOREIGN KEY (firma_id) REFERENCES firmas (id),
    KEY idx_catalogos_firma_estado (firma_id, estado, deleted_at),
    KEY idx_catalogos_codigo (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE catalogo_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id BIGINT UNSIGNED NULL,
    catalogo_id BIGINT UNSIGNED NOT NULL,
    codigo VARCHAR(100) NOT NULL,
    etiqueta VARCHAR(180) NOT NULL,
    orden INT NOT NULL DEFAULT 0,
    estado VARCHAR(30) NOT NULL DEFAULT 'activo',
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    deleted_at DATETIME(6) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_catalogo_items_codigo (catalogo_id, codigo),
    CONSTRAINT fk_catalogo_items_firma FOREIGN KEY (firma_id) REFERENCES firmas (id),
    CONSTRAINT fk_catalogo_items_catalogo FOREIGN KEY (catalogo_id) REFERENCES catalogos (id),
    KEY idx_catalogo_items_lista (catalogo_id, estado, orden, deleted_at),
    KEY idx_catalogo_items_firma (firma_id, deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE aceptaciones_legales (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id BIGINT UNSIGNED NULL,
    usuario_id BIGINT UNSIGNED NOT NULL,
    documento_legal_id BIGINT UNSIGNED NOT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    accepted_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_aceptaciones_usuario_documento (usuario_id, documento_legal_id),
    CONSTRAINT fk_aceptaciones_firma FOREIGN KEY (firma_id) REFERENCES firmas (id),
    CONSTRAINT fk_aceptaciones_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id),
    CONSTRAINT fk_aceptaciones_documento FOREIGN KEY (documento_legal_id) REFERENCES documentos_legales (id),
    KEY idx_aceptaciones_firma_fecha (firma_id, accepted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- @down
DROP TABLE IF EXISTS aceptaciones_legales;
DROP TABLE IF EXISTS catalogo_items;
DROP TABLE IF EXISTS catalogos;
DROP TABLE IF EXISTS auditoria;
DROP TABLE IF EXISTS login_attempts;
DROP TABLE IF EXISTS password_resets;
DROP TABLE IF EXISTS user_sessions;
DROP TABLE IF EXISTS rol_permiso;
DROP TABLE IF EXISTS usuario_roles;
DROP TABLE IF EXISTS roles;
DROP TABLE IF EXISTS usuarios;
DROP TABLE IF EXISTS firma_limites;
DROP TABLE IF EXISTS firma_planes;
DROP TABLE IF EXISTS firmas;
DROP TABLE IF EXISTS documentos_legales;
DROP TABLE IF EXISTS permisos;
DROP TABLE IF EXISTS planes;
