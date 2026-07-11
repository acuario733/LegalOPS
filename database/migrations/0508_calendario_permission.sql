-- @up
-- Insertar permiso calendario.ver en la tabla permisos
INSERT INTO permisos (codigo, modulo, accion, descripcion, created_at, updated_at)
VALUES (
    'calendario.ver',
    'calendario',
    'ver',
    'Ver el calendario de audiencias, términos y citas',
    CURRENT_TIMESTAMP,
    CURRENT_TIMESTAMP
)
ON DUPLICATE KEY UPDATE updated_at = updated_at;

-- Asignar calendario.ver a todos los roles administrador y abogado existentes
INSERT INTO rol_permiso (firma_id, rol_id, permiso_id, created_at)
SELECT r.firma_id, r.id, p.id, CURRENT_TIMESTAMP
FROM roles r
INNER JOIN permisos p ON p.codigo = 'calendario.ver'
WHERE r.codigo IN ('administrador', 'abogado')
  AND r.deleted_at IS NULL
ON DUPLICATE KEY UPDATE created_at = rol_permiso.created_at;

-- @down
DELETE rp FROM rol_permiso rp
INNER JOIN permisos p ON p.id = rp.permiso_id
WHERE p.codigo = 'calendario.ver';

DELETE FROM permisos WHERE codigo = 'calendario.ver';
