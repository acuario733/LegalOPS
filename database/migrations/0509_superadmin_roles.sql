-- @up
-- Sprint 2: Roles Superadmin
-- Opción C aprobada: hacer firma_id nullable para permitir roles sin firma (superadmin)
-- 1. Soltar FK sobre firma_id (nombres reales confirmados)
ALTER TABLE roles         DROP FOREIGN KEY fk_roles_firma;
ALTER TABLE rol_permiso   DROP FOREIGN KEY fk_rol_permiso_firma;
ALTER TABLE usuario_roles DROP FOREIGN KEY fk_usuario_roles_firma;

-- 2. Hacer nullable
ALTER TABLE roles         MODIFY COLUMN firma_id BIGINT NULL;
ALTER TABLE rol_permiso   MODIFY COLUMN firma_id BIGINT NULL;
ALTER TABLE usuario_roles MODIFY COLUMN firma_id BIGINT NULL;

-- 3. Re-crear FK como nullable (firma borrada → NULL, no error)
ALTER TABLE roles
    ADD CONSTRAINT fk_roles_firma
    FOREIGN KEY (firma_id) REFERENCES firmas(id) ON DELETE SET NULL;

ALTER TABLE rol_permiso
    ADD CONSTRAINT fk_rol_permiso_firma
    FOREIGN KEY (firma_id) REFERENCES firmas(id) ON DELETE SET NULL;

ALTER TABLE usuario_roles
    ADD CONSTRAINT fk_usuario_roles_firma
    FOREIGN KEY (firma_id) REFERENCES firmas(id) ON DELETE SET NULL;

-- Permisos superadmin en la tabla permisos (para asignación granular entre sub-roles)
INSERT INTO permisos (codigo, modulo, accion, descripcion, created_at, updated_at) VALUES
  ('superadmin_usuarios.ver',              'superadmin_usuarios', 'ver',               'Ver usuarios superadmin',               CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
  ('superadmin_usuarios.crear',            'superadmin_usuarios', 'crear',             'Crear usuarios superadmin',             CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
  ('superadmin_usuarios.editar',           'superadmin_usuarios', 'editar',            'Editar usuarios superadmin',            CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
  ('superadmin_usuarios.desactivar',       'superadmin_usuarios', 'desactivar',        'Desactivar usuarios superadmin',        CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
  ('superadmin_usuarios.reactivar',        'superadmin_usuarios', 'reactivar',         'Reactivar usuarios superadmin',         CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
  ('superadmin_usuarios.resetear_password','superadmin_usuarios', 'resetear_password', 'Resetear contraseña superadmin',        CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
  ('superadmin_roles.ver',                 'superadmin_roles',    'ver',               'Ver roles superadmin',                  CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
  ('superadmin_roles.crear',               'superadmin_roles',    'crear',             'Crear roles superadmin',                CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
  ('superadmin_roles.editar',              'superadmin_roles',    'editar',            'Editar roles superadmin',               CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
  ('superadmin_roles.asignar',             'superadmin_roles',    'asignar',           'Asignar roles superadmin a usuarios',   CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
ON DUPLICATE KEY UPDATE updated_at = updated_at;

-- @down
DELETE FROM permisos WHERE modulo IN ('superadmin_usuarios', 'superadmin_roles');
-- Note: ALTER TABLE reversals left to manual DBA action to avoid data loss
