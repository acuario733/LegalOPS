-- @up
INSERT INTO rol_permiso (firma_id, rol_id, permiso_id, created_at)
SELECT r.firma_id, r.id, p.id, CURRENT_TIMESTAMP
FROM roles r
INNER JOIN permisos p ON p.codigo IN (
    'intake.ver', 'intake.crear', 'intake.editar', 'intake.eliminar',
    'booking.ver', 'booking.crear', 'booking.editar', 'booking.eliminar'
)
WHERE r.codigo = 'administrador'
  AND r.deleted_at IS NULL
ON DUPLICATE KEY UPDATE created_at = rol_permiso.created_at;

INSERT INTO rol_permiso (firma_id, rol_id, permiso_id, created_at)
SELECT r.firma_id, r.id, p.id, CURRENT_TIMESTAMP
FROM roles r
INNER JOIN permisos p ON p.codigo IN (
    'intake.ver', 'intake.crear', 'intake.editar',
    'booking.ver', 'booking.crear', 'booking.editar'
)
WHERE r.codigo = 'abogado'
  AND r.deleted_at IS NULL
ON DUPLICATE KEY UPDATE created_at = rol_permiso.created_at;

-- @down
DELETE rp FROM rol_permiso rp
INNER JOIN roles r ON r.id = rp.rol_id AND r.firma_id = rp.firma_id
INNER JOIN permisos p ON p.id = rp.permiso_id
WHERE r.codigo IN ('administrador', 'abogado')
  AND p.codigo IN (
      'intake.ver', 'intake.crear', 'intake.editar', 'intake.eliminar',
      'booking.ver', 'booking.crear', 'booking.editar', 'booking.eliminar'
  );
