-- @up
INSERT INTO permisos (codigo, modulo, accion, descripcion) VALUES
('perfil.ver', 'perfil', 'ver', 'Consultar el perfil propio'),
('perfil.editar', 'perfil', 'editar', 'Editar los datos personales del perfil propio'),
('perfil.cambiar_password', 'perfil', 'cambiar_password', 'Cambiar la contrasena del perfil propio'),
('usuarios.editar_cargo', 'usuarios', 'editar_cargo', 'Editar el cargo de usuarios de la firma'),
('usuarios.verificar_profesional', 'usuarios', 'verificar_profesional', 'Verificar la tarjeta profesional de usuarios'),
('usuarios.editar_cuenta', 'usuarios', 'editar_cuenta', 'Editar tipo y estado de cuenta de usuarios'),
('usuarios.asignar_roles', 'usuarios', 'asignar_roles', 'Asignar roles a usuarios de la firma'),
('usuarios.reactivar', 'usuarios', 'reactivar', 'Reactivar usuarios de la firma'),
('usuarios.revocar_sesiones', 'usuarios', 'revocar_sesiones', 'Revocar sesiones de usuarios de la firma'),
('usuarios.ver_historial', 'usuarios', 'ver_historial', 'Consultar el historial sensible de usuarios'),
('usuarios.ver_datos_sensibles', 'usuarios', 'ver_datos_sensibles', 'Revelar datos sensibles de usuarios autorizados')
ON DUPLICATE KEY UPDATE
    modulo = VALUES(modulo),
    accion = VALUES(accion),
    descripcion = VALUES(descripcion);

INSERT INTO rol_permiso (firma_id, rol_id, permiso_id, created_at)
SELECT r.firma_id, r.id, p.id, CURRENT_TIMESTAMP(6)
FROM roles r
INNER JOIN permisos p ON p.codigo IN (
    'perfil.ver',
    'perfil.editar',
    'perfil.cambiar_password'
)
WHERE r.deleted_at IS NULL
ON DUPLICATE KEY UPDATE created_at = rol_permiso.created_at;

INSERT INTO rol_permiso (firma_id, rol_id, permiso_id, created_at)
SELECT r.firma_id, r.id, p.id, CURRENT_TIMESTAMP(6)
FROM roles r
INNER JOIN permisos p ON p.codigo IN (
    'usuarios.ver',
    'usuarios.crear',
    'usuarios.editar',
    'usuarios.editar_cargo',
    'usuarios.verificar_profesional',
    'usuarios.editar_cuenta',
    'usuarios.asignar_roles',
    'usuarios.desactivar',
    'usuarios.reactivar',
    'usuarios.revocar_sesiones',
    'usuarios.ver_historial',
    'usuarios.ver_datos_sensibles'
)
WHERE r.codigo = 'administrador'
  AND r.is_protected = 1
  AND r.deleted_at IS NULL
ON DUPLICATE KEY UPDATE created_at = rol_permiso.created_at;

-- @down
DELETE rp
FROM rol_permiso rp
INNER JOIN permisos p ON p.id = rp.permiso_id
WHERE p.codigo IN (
    'perfil.ver',
    'perfil.editar',
    'perfil.cambiar_password',
    'usuarios.editar_cargo',
    'usuarios.verificar_profesional',
    'usuarios.editar_cuenta',
    'usuarios.asignar_roles',
    'usuarios.reactivar',
    'usuarios.revocar_sesiones',
    'usuarios.ver_historial',
    'usuarios.ver_datos_sensibles'
);

DELETE FROM permisos
WHERE codigo IN (
    'perfil.ver',
    'perfil.editar',
    'perfil.cambiar_password',
    'usuarios.editar_cargo',
    'usuarios.verificar_profesional',
    'usuarios.editar_cuenta',
    'usuarios.asignar_roles',
    'usuarios.reactivar',
    'usuarios.revocar_sesiones',
    'usuarios.ver_historial',
    'usuarios.ver_datos_sensibles'
);
