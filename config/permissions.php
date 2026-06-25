<?php

declare(strict_types=1);

return [
    'superadmin' => [
        'firmas.ver', 'firmas.crear', 'firmas.editar', 'firmas.suspender', 'firmas.reactivar',
        'planes.ver', 'planes.crear', 'planes.editar', 'planes.asignar',
        'limites.ver', 'limites.editar', 'auditoria.ver',
        'configuracion.ver', 'configuracion.editar', 'legal.ver', 'legal.administrar',
        'soporte.ver_global', 'checklist.ver', 'checklist.evaluar', 'checklist.aprobar',
    ],
    'firma' => [
        'usuarios.ver', 'usuarios.crear', 'usuarios.editar', 'usuarios.desactivar',
        'roles.ver', 'roles.crear', 'roles.editar', 'roles.asignar',
        'permisos.ver', 'permisos.asignar', 'sesiones.ver', 'sesiones.revocar',
        'auditoria.ver', 'configuracion.ver', 'configuracion.editar',
        'legal.ver', 'legal.aceptar',
        'clientes.ver', 'clientes.crear', 'clientes.editar', 'clientes.eliminar', 'clientes.revelar',
        'prospectos.ver', 'prospectos.crear', 'prospectos.editar', 'prospectos.convertir',
        'casos.ver', 'casos.crear', 'casos.editar', 'casos.cerrar', 'casos.archivar',
        'partes.ver', 'partes.crear', 'partes.editar', 'partes.eliminar', 'partes.revelar',
        'timeline.ver', 'timeline.crear', 'timeline.editar', 'timeline.eliminar', 'timeline.publicar',
        'terminos.ver', 'terminos.crear', 'terminos.editar', 'terminos.cumplir', 'terminos.eliminar',
        'audiencias.ver', 'audiencias.crear', 'audiencias.editar', 'audiencias.registrar_resultado',
        'tareas.ver', 'tareas.crear', 'tareas.editar', 'tareas.reasignar', 'tareas.cambiar_estado',
        'documentos.ver', 'documentos.cargar', 'documentos.editar', 'documentos.eliminar', 'documentos.versionar', 'documentos.descargar',
        'finanzas.ver', 'finanzas.crear', 'finanzas.editar', 'finanzas.revelar',
        'notificaciones.ver', 'notificaciones.marcar_leida',
        'portal.autorizar',
        'reportes.ver', 'reportes.exportar',
        'importaciones.ver', 'importaciones.crear', 'importaciones.ejecutar',
        'soporte.ver_propio', 'soporte.ver_firma', 'soporte.crear', 'soporte.responder', 'soporte.cambiar_estado',
        'onboarding.ver', 'onboarding.administrar',
    ],
    'portal' => ['sesiones.ver', 'sesiones.revocar', 'legal.ver', 'legal.aceptar'],
];
