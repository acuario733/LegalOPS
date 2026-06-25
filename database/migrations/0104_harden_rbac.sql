-- @up
ALTER TABLE roles ADD COLUMN is_protected TINYINT(1) NOT NULL DEFAULT 0 AFTER estado;

INSERT INTO permisos (codigo, modulo, accion, descripcion) VALUES
('firmas.ver', 'firmas', 'ver', 'Consultar firmas'),
('firmas.crear', 'firmas', 'crear', 'Crear firmas'),
('firmas.editar', 'firmas', 'editar', 'Editar firmas'),
('firmas.suspender', 'firmas', 'suspender', 'Suspender firmas'),
('firmas.reactivar', 'firmas', 'reactivar', 'Reactivar firmas'),
('planes.ver', 'planes', 'ver', 'Consultar planes'),
('planes.crear', 'planes', 'crear', 'Crear planes'),
('planes.editar', 'planes', 'editar', 'Editar planes'),
('planes.asignar', 'planes', 'asignar', 'Asignar planes a firmas'),
('limites.ver', 'limites', 'ver', 'Consultar límites'),
('limites.editar', 'limites', 'editar', 'Editar límites y excepciones'),
('usuarios.ver', 'usuarios', 'ver', 'Consultar usuarios'),
('usuarios.crear', 'usuarios', 'crear', 'Crear usuarios'),
('usuarios.editar', 'usuarios', 'editar', 'Editar usuarios'),
('usuarios.desactivar', 'usuarios', 'desactivar', 'Desactivar usuarios'),
('roles.ver', 'roles', 'ver', 'Consultar roles'),
('roles.crear', 'roles', 'crear', 'Crear roles'),
('roles.editar', 'roles', 'editar', 'Editar roles'),
('roles.asignar', 'roles', 'asignar', 'Asignar roles'),
('permisos.ver', 'permisos', 'ver', 'Consultar permisos'),
('permisos.asignar', 'permisos', 'asignar', 'Asignar permisos'),
('sesiones.ver', 'sesiones', 'ver', 'Consultar sesiones'),
('sesiones.revocar', 'sesiones', 'revocar', 'Revocar sesiones'),
('auditoria.ver', 'auditoria', 'ver', 'Consultar auditoría'),
('configuracion.ver', 'configuracion', 'ver', 'Consultar configuración'),
('configuracion.editar', 'configuracion', 'editar', 'Editar configuración'),
('legal.ver', 'legal', 'ver', 'Consultar documentos legales'),
('legal.administrar', 'legal', 'administrar', 'Administrar documentos legales'),
('legal.aceptar', 'legal', 'aceptar', 'Aceptar documentos legales')
ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion), modulo = VALUES(modulo), accion = VALUES(accion);

-- @down
DELETE FROM permisos WHERE codigo IN (
'firmas.ver','firmas.crear','firmas.editar','firmas.suspender','firmas.reactivar',
'planes.ver','planes.crear','planes.editar','planes.asignar','limites.ver','limites.editar',
'usuarios.ver','usuarios.crear','usuarios.editar','usuarios.desactivar',
'roles.ver','roles.crear','roles.editar','roles.asignar','permisos.ver','permisos.asignar',
'sesiones.ver','sesiones.revocar','auditoria.ver','configuracion.ver','configuracion.editar',
'legal.ver','legal.administrar','legal.aceptar');
ALTER TABLE roles DROP COLUMN is_protected;

