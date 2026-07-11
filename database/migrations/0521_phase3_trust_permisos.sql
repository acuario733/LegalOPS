-- @up
INSERT INTO permisos (codigo, modulo, accion, descripcion) VALUES
('trust.ver', 'trust', 'ver', 'Consultar cuentas y libro trust'),
('trust.depositar', 'trust', 'depositar', 'Registrar depositos trust'),
('trust.retirar', 'trust', 'retirar', 'Registrar retiros trust')
ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion), modulo = VALUES(modulo), accion = VALUES(accion);

INSERT INTO rol_permiso (firma_id, rol_id, permiso_id, created_at)
SELECT r.firma_id, r.id, p.id, CURRENT_TIMESTAMP(6)
FROM roles r
INNER JOIN permisos p ON p.codigo IN ('trust.ver','trust.depositar','trust.retirar')
WHERE r.codigo IN ('administrador', 'partner') AND r.deleted_at IS NULL
ON DUPLICATE KEY UPDATE created_at = rol_permiso.created_at;

INSERT INTO rol_permiso (firma_id, rol_id, permiso_id, created_at)
SELECT r.firma_id, r.id, p.id, CURRENT_TIMESTAMP(6)
FROM roles r
INNER JOIN permisos p ON p.codigo IN ('trust.ver','trust.depositar')
WHERE r.codigo IN ('billing') AND r.deleted_at IS NULL
ON DUPLICATE KEY UPDATE created_at = rol_permiso.created_at;

-- @down
DELETE rp FROM rol_permiso rp
INNER JOIN permisos p ON p.id = rp.permiso_id
WHERE p.codigo IN ('trust.ver','trust.depositar','trust.retirar');

DELETE FROM permisos WHERE codigo IN ('trust.ver','trust.depositar','trust.retirar');
