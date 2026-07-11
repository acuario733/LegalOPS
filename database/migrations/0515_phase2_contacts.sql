-- @up
CREATE TABLE contacts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id BIGINT UNSIGNED NOT NULL COMMENT 'Tenant propietario',
    type ENUM('PERSON','ORGANIZATION') NOT NULL COMMENT 'Tipo de contacto',
    first_name VARCHAR(120) NULL COMMENT 'Nombres para persona natural',
    last_name VARCHAR(120) NULL COMMENT 'Apellidos para persona natural',
    company_name VARCHAR(180) NULL COMMENT 'Razon social para organizacion',
    display_name VARCHAR(180) NOT NULL COMMENT 'Nombre normalizado para mostrar',
    display_name_normalizado VARCHAR(180) NOT NULL COMMENT 'Nombre para busqueda LIKE',
    email VARCHAR(254) NULL COMMENT 'Correo principal',
    phone VARCHAR(60) NULL COMMENT 'Telefono principal',
    address JSON NULL COMMENT 'Direccion estructurada',
    tags JSON NULL COMMENT 'Etiquetas libres',
    source_type ENUM('cliente','prospecto','parte','externo') NOT NULL COMMENT 'Origen del registro',
    source_id BIGINT UNSIGNED NULL COMMENT 'Id en tabla origen',
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT 'Fecha de creacion',
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6) COMMENT 'Fecha de actualizacion',
    deleted_at DATETIME(6) NULL COMMENT 'Soft delete',
    PRIMARY KEY (id),
    UNIQUE KEY uq_contacts_id_firma (id, firma_id),
    UNIQUE KEY uq_contacts_source (firma_id, source_type, source_id),
    KEY idx_contacts_search (firma_id, display_name_normalizado, deleted_at),
    KEY idx_contacts_email (firma_id, email, deleted_at),
    CONSTRAINT fk_contacts_firma FOREIGN KEY (firma_id) REFERENCES firmas (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO contacts (firma_id,type,first_name,last_name,company_name,display_name,display_name_normalizado,email,phone,address,tags,source_type,source_id,created_at,updated_at,deleted_at)
SELECT firma_id,
       IF(tipo_persona = 'juridica','ORGANIZATION','PERSON'),
       IF(tipo_persona = 'juridica',NULL,nombre_razon_social),
       NULL,
       IF(tipo_persona = 'juridica',nombre_razon_social,NULL),
       nombre_razon_social,
       nombre_normalizado,
       email,
       telefono,
       IF(direccion IS NULL, NULL, JSON_OBJECT('linea', direccion)),
       JSON_ARRAY(),
       'cliente',
       id,
       created_at,
       updated_at,
       deleted_at
FROM clientes
ON DUPLICATE KEY UPDATE updated_at = VALUES(updated_at);

INSERT INTO contacts (firma_id,type,first_name,last_name,company_name,display_name,display_name_normalizado,email,phone,address,tags,source_type,source_id,created_at,updated_at,deleted_at)
SELECT firma_id,
       IF(tipo_persona = 'juridica','ORGANIZATION','PERSON'),
       IF(tipo_persona = 'juridica',NULL,nombre),
       NULL,
       IF(tipo_persona = 'juridica',COALESCE(empresa,nombre),NULL),
       nombre,
       nombre_normalizado,
       email,
       telefono,
       NULL,
       JSON_ARRAY(),
       'prospecto',
       id,
       created_at,
       updated_at,
       deleted_at
FROM prospectos
ON DUPLICATE KEY UPDATE updated_at = VALUES(updated_at);

ALTER TABLE caso_partes
    ADD COLUMN contact_id BIGINT UNSIGNED NULL COMMENT 'Contacto unificado vinculado' AFTER caso_id,
    ADD KEY idx_caso_partes_contact (firma_id, contact_id),
    ADD CONSTRAINT fk_caso_partes_contact FOREIGN KEY (contact_id, firma_id) REFERENCES contacts (id, firma_id);

ALTER TABLE prospectos
    ADD COLUMN contact_id BIGINT UNSIGNED NULL COMMENT 'Contacto unificado vinculado' AFTER firma_id,
    ADD KEY idx_prospectos_contact (firma_id, contact_id),
    ADD CONSTRAINT fk_prospectos_contact FOREIGN KEY (contact_id, firma_id) REFERENCES contacts (id, firma_id);

UPDATE prospectos p
INNER JOIN contacts c ON c.firma_id = p.firma_id AND c.source_type = 'prospecto' AND c.source_id = p.id
SET p.contact_id = c.id;

ALTER TABLE caso_comunicaciones
    ADD COLUMN contact_id BIGINT UNSIGNED NULL COMMENT 'Contacto unificado vinculado' AFTER caso_id,
    ADD KEY idx_caso_comunicaciones_contact (firma_id, contact_id),
    ADD CONSTRAINT fk_caso_comunicaciones_contact FOREIGN KEY (contact_id, firma_id) REFERENCES contacts (id, firma_id);

-- @down
ALTER TABLE caso_comunicaciones DROP FOREIGN KEY fk_caso_comunicaciones_contact;
ALTER TABLE caso_comunicaciones DROP INDEX idx_caso_comunicaciones_contact;
ALTER TABLE caso_comunicaciones DROP COLUMN contact_id;
ALTER TABLE prospectos DROP FOREIGN KEY fk_prospectos_contact;
ALTER TABLE prospectos DROP INDEX idx_prospectos_contact;
ALTER TABLE prospectos DROP COLUMN contact_id;
ALTER TABLE caso_partes DROP FOREIGN KEY fk_caso_partes_contact;
ALTER TABLE caso_partes DROP INDEX idx_caso_partes_contact;
ALTER TABLE caso_partes DROP COLUMN contact_id;
DROP TABLE IF EXISTS contacts;
