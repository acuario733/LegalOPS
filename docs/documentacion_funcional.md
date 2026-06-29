# Documentacion funcional de LegalOPS Cloud

Documento actualizado a partir del codigo fuente actual del sistema y de la auditoria vigente al 2026-06-26. Describe funciones visibles del software como unidades funcionales de negocio, y tambien capacidades operativas relevantes cuando afectan la salida a produccion. Cuando una funcion existe parcialmente o depende de informacion no visible en el codigo, se marca como pendiente o condicionada.

## Alcance

- Aplicacion SaaS multiempresa para operacion juridica.
- Usuarios principales: superadministrador, administrador de firma, usuario interno de firma y cliente externo de portal.
- Modulos cubiertos: autenticacion, firmas, planes, usuarios, roles, catalogos, clientes, prospectos, casos, partes, linea de tiempo, terminos, audiencias, tareas, documentos, finanzas, portal, reportes, importaciones, soporte, onboarding, auditoria y notificaciones.
- Fuente de permisos: rutas y configuracion RBAC del sistema.
- Estado actual del entorno verificado: desarrollo/prueba. `http://legal.com/api/health` responde correctamente, pero reporta `environment=development`.
- No se documenta este entorno como produccion. Los requisitos productivos se describen como condiciones de cierre antes de despliegue comercial.

## Estado funcional actual verificado

### Capacidades implementadas

- Health check publico por HTML y API.
- Login, logout, sesiones persistidas, revocacion de sesiones y rate limit.
- Recuperacion de contrasena con token aleatorio, token hasheado en base de datos y spool local de correo en desarrollo.
- Aislamiento por firma, estado comercial, planes, limites, usuarios, roles y permisos.
- Modulos juridicos de clientes, prospectos, casos, partes, timeline, terminos, audiencias y tareas.
- Documentos privados fuera de `public/`, versionamiento, checksum, descarga autenticada y autorizacion de portal.
- Finanzas juridicas: honorarios, pagos, gastos, saldos, anulaciones y revelacion controlada de datos sensibles.
- Portal cliente, aceptaciones legales versionadas, soporte, notificaciones, dashboard, reportes e importaciones CSV.
- Scripts operativos de cron, limpieza, sesiones, alertas y backups SQL.
- Checklist owner para control humano de salida comercial o gobierno interno.

### Restricciones actuales confirmadas

- El entorno auditado es desarrollo/prueba; HTTPS, `APP_ENV=production` y cookie `Secure` quedan como requisitos productivos, no como errores del ambiente actual.
- No hay transporte SMTP/API productivo implementado. `LocalMailService` solo soporta `MAIL_TRANSPORT=local` y genera archivos JSON de correo local.
- No existe apartado de superadmin para configurar SMTP/correo productivo.
- No hay MFA/2FA funcional para superadmin o administradores.
- No hay antivirus, sandbox, cuarentena ni inspeccion de macros/contenido activo en documentos o importaciones.
- No hay cifrado en reposo implementado en aplicacion para documentos ni backups; los backups observados son SQL plano.
- Existen backups generados y cron de backup OK, pero no hay evidencia de restauracion aprobada con RPO, RTO, frecuencia, retencion y responsables formalizados.
- No hay monitoreo externo/alertamiento productivo configurado; esto es aceptable en desarrollo y pendiente para produccion.
- Los assets minificados referencian sourcemaps `.map` no incluidos, lo que genera ruido 404 en logs.

### Dictamen funcional de estado

El software esta apto para desarrollo y pruebas internas con observaciones. Para produccion quedan bloqueantes reales: configuracion productiva verificable, correo transaccional real, restauracion de backup aprobada y proteccion documental completa.

## Nombre de la funcion: verificarEstadoDelSistema

### Modulo
Salud del sistema.

### Proposito
Permite comprobar que la aplicacion responde y que el servidor puede atender solicitudes basicas.

### Problema que resuelve
Da una forma rapida de saber si el sistema esta disponible antes de operar o diagnosticar fallas.

### Usuario que la utiliza
Sistema automatico, operador tecnico o usuario interno con acceso a la URL de salud.

### Datos de entrada
No requiere datos de negocio. Solo recibe la solicitud HTTP.

### Validaciones
Verifica que la aplicacion pueda iniciar y responder. En entorno de desarrollo existe una ruta para provocar un error controlado.

### Proceso
El usuario o monitor consulta la ruta de salud; el sistema carga la aplicacion, procesa la ruta y devuelve una respuesta de estado.

### Resultado esperado
Respuesta correcta indicando que la aplicacion esta disponible.

### Informacion afectada
Consulta estado de ejecucion. No crea, modifica ni elimina registros.

### Relacion con otros modulos
Se relaciona con despliegue, monitoreo, manejo de errores y disponibilidad general.

### Reglas de negocio
No debe depender de datos sensibles ni de sesion de usuario.

### Permisos requeridos
No requiere permiso funcional en la ruta publica de salud.

### Posibles errores
Aplicacion no disponible, autoload faltante, error de servidor o error controlado en desarrollo.

### Impacto
Sirve como primer punto de diagnostico del estado operativo.

### Observaciones
No reemplaza monitoreo profundo de base de datos, colas, correo o almacenamiento. En el entorno verificado `http://legal.com/api/health` responde OK y reporta `environment=development`, `storage_writable=true` y `database=true`. Para produccion debe verificarse el mismo endpoint con `APP_ENV=production`, HTTPS y cookies seguras.

## Nombre de la funcion: iniciarSesion

### Modulo
Autenticacion.

### Proposito
Permite que un usuario acceda al sistema con correo, contrasena y, cuando aplica, identificador de firma.

### Problema que resuelve
Controla el acceso al sistema y evita que usuarios no autorizados ingresen a informacion de una firma o al portal.

### Usuario que la utiliza
Superadministrador, usuario interno y cliente externo.

### Datos de entrada
Correo electronico, contrasena y slug de firma opcional.

### Validaciones
Valida formato de credenciales, intentos fallidos recientes, existencia unica del usuario, contrasena correcta, usuario activo y firma activa cuando el usuario pertenece a una firma.

### Proceso
El usuario envia credenciales; el sistema normaliza correo y firma; busca candidatos; verifica contrasena y estado; registra intento; crea sesion; carga permisos y roles; actualiza ultimo ingreso; registra auditoria.

### Resultado esperado
Sesion iniciada y redireccion a portal, superadmin o area interna segun tipo de usuario.

### Informacion afectada
Consulta usuarios, roles, permisos y firma. Crea registro de sesion, registra intento de login y actualiza ultimo acceso.

### Relacion con otros modulos
Usuarios, firmas, RBAC, sesiones, auditoria y portal.

### Reglas de negocio
No permite ingreso con usuario inactivo, firma suspendida o contrasena invalida. Bloquea temporalmente despues de demasiados intentos fallidos.

### Permisos requeridos
No requiere permiso previo; es una ruta de invitado con limitacion de intentos.

### Posibles errores
Credenciales invalidas, demasiados intentos, usuario ambiguo por multiples firmas, usuario inactivo o firma no activa.

### Impacto
Determina todo el contexto de seguridad posterior: usuario, firma, permisos y tipo de acceso.

### Observaciones
El sistema usa hash de contrasena y no expone si el correo existe.

## Nombre de la funcion: cerrarSesion

### Modulo
Autenticacion.

### Proposito
Cierra la sesion activa del usuario.

### Problema que resuelve
Evita que una sesion permanezca abierta cuando el usuario termina su trabajo.

### Usuario que la utiliza
Usuario autenticado de cualquier tipo.

### Datos de entrada
Sesion activa y solicitud de cierre.

### Validaciones
Debe existir una sesion iniciada para registrar la revocacion actual.

### Proceso
El usuario solicita salir; el sistema revoca la sesion actual, registra auditoria y limpia el estado de autenticacion.

### Resultado esperado
Sesion cerrada y redireccion a login.

### Informacion afectada
Actualiza estado de la sesion y registra evento de auditoria.

### Relacion con otros modulos
Sesiones, autenticacion y auditoria.

### Reglas de negocio
La sesion debe quedar inutilizable despues del cierre.

### Permisos requeridos
Usuario autenticado.

### Posibles errores
Sesion inexistente, sesion ya revocada o error al registrar auditoria.

### Impacto
Reduce riesgo de acceso no autorizado desde sesiones abiertas.

### Observaciones
No elimina el usuario ni sus permisos.

## Nombre de la funcion: solicitarRecuperacionContrasena

### Modulo
Autenticacion.

### Proposito
Permite pedir un enlace para restablecer la contrasena.

### Problema que resuelve
Da una via segura para recuperar acceso sin exponer la contrasena anterior.

### Usuario que la utiliza
Usuario que no puede iniciar sesion.

### Datos de entrada
Correo electronico y firma opcional.

### Validaciones
Valida formato del correo y que la busqueda coincida con un unico usuario.

### Proceso
El usuario solicita recuperacion; el sistema busca candidato unico; si existe genera token, guarda hash del token, envia correo local y registra auditoria.

### Resultado esperado
Respuesta generica indicando que, si los datos coinciden, se enviaron instrucciones.

### Informacion afectada
Crea registro de recuperacion y registra auditoria.

### Relacion con otros modulos
Usuarios, correo local, auditoria y sesiones. En el estado actual no hay integracion SMTP/API productiva.

### Reglas de negocio
No debe revelar si el correo existe. El token se guarda como hash.

### Permisos requeridos
No requiere permiso previo; es funcion de invitado.

### Posibles errores
Correo invalido, usuario no unico, error de correo o error de base de datos.

### Impacto
Facilita continuidad de acceso manteniendo privacidad.

### Observaciones
El canal actual de envio es local: `LocalMailService` escribe un JSON fuera de `public/` en `storage/temp/mail`. `MAIL_TRANSPORT` solo esta soportado como `local`; si se configura otro transporte el servicio lanza error. Para produccion falta implementar SMTP/API transaccional y probar entrega real. No existe pantalla de superadmin para configurar SMTP/correo productivo.

## Nombre de la funcion: restablecerContrasena

### Modulo
Autenticacion.

### Proposito
Permite definir una nueva contrasena mediante un token valido.

### Problema que resuelve
Recupera el acceso de un usuario sin intervencion manual de soporte.

### Usuario que la utiliza
Usuario con enlace de recuperacion.

### Datos de entrada
Token de recuperacion y nueva contrasena.

### Validaciones
Contrasena minima de 12 caracteres y token valido no expirado ni usado.

### Proceso
El usuario envia nueva contrasena; el sistema valida token; actualiza hash de contrasena; marca token como usado; revoca sesiones anteriores; registra auditoria.

### Resultado esperado
Contrasena actualizada y redireccion a login.

### Informacion afectada
Actualiza usuario, recuperacion de contrasena y sesiones.

### Relacion con otros modulos
Usuarios, sesiones, auditoria y autenticacion.

### Reglas de negocio
La contrasena nunca se guarda en texto plano. Las sesiones previas se revocan.

### Permisos requeridos
Token valido de recuperacion.

### Posibles errores
Token invalido, token expirado, contrasena corta o error de base de datos.

### Impacto
Restablece acceso con control de seguridad.

### Observaciones
No se documenta complejidad adicional de contrasena mas alla del minimo de longitud observado en codigo. El reset revoca sesiones previas del usuario. En la revision de logs no se encontro token de reset expuesto; los tokens registrados por eventos controlados aparecen redactados.

## Nombre de la funcion: gestionarSesiones

### Modulo
Sesiones.

### Proposito
Permite consultar sesiones activas y revocar sesiones.

### Problema que resuelve
Ayuda a controlar accesos abiertos desde otros dispositivos o ubicaciones.

### Usuario que la utiliza
Usuario interno con permisos de sesiones.

### Datos de entrada
Identificador de sesion a revocar cuando aplica.

### Validaciones
La sesion debe pertenecer al alcance permitido y existir.

### Proceso
El usuario consulta la lista; el sistema muestra sesiones. Para revocar, recibe ID, marca la sesion como revocada y registra auditoria.

### Resultado esperado
Listado actualizado o sesion revocada.

### Informacion afectada
Consulta y modifica registros de sesiones de usuario.

### Relacion con otros modulos
Autenticacion, usuarios y auditoria.

### Reglas de negocio
Una sesion revocada no debe poder seguir operando.

### Permisos requeridos
`sesiones.ver` para consultar y `sesiones.revocar` para revocar.

### Posibles errores
Sesion inexistente, falta de permiso o error de persistencia.

### Impacto
Mejora control de seguridad operativo.

### Observaciones
No cambia contrasenas ni roles.

## Nombre de la funcion: aceptarDocumentoLegal

### Modulo
Legal y aceptaciones.

### Proposito
Permite que un usuario acepte documentos legales pendientes.

### Problema que resuelve
Garantiza trazabilidad de aceptacion de terminos, politicas u otros documentos obligatorios.

### Usuario que la utiliza
Usuario autenticado interno o cliente externo.

### Datos de entrada
ID del documento legal pendiente.

### Validaciones
Documento existente, publicado y pendiente para el usuario.

### Proceso
El usuario consulta pendientes; selecciona aceptar; el sistema guarda aceptacion con usuario, fecha y contexto; registra auditoria.

### Resultado esperado
Documento marcado como aceptado para el usuario.

### Informacion afectada
Consulta documentos legales y crea aceptacion.

### Relacion con otros modulos
Autenticacion, portal, superadmin legal y auditoria.

### Reglas de negocio
Cada aceptacion debe quedar asociada al usuario que la realiza. Si existen documentos legales pendientes, el middleware `legal_pending` bloquea la navegacion funcional interna y de portal hasta que el usuario acepte, permitiendo solo rutas de aceptacion, portal inicial y logout.

### Permisos requeridos
`legal.ver` para consultar y `legal.aceptar` para aceptar.

### Posibles errores
Documento inexistente, ya aceptado, no publicado, sin permiso o intento de continuar sin aceptar documentos pendientes.

### Impacto
Permite cumplir requisitos legales antes o durante el uso del sistema.

### Observaciones
La aceptacion pendiente si bloquea flujos protegidos. En respuestas JSON devuelve HTTP 428 con codigo `LEGAL_ACCEPTANCE_REQUIRED`; en navegacion HTML redirige a `/legal/pendientes` o al portal segun corresponda.

## Nombre de la funcion: administrarDocumentosLegales

### Modulo
Legal y aceptaciones.

### Proposito
Permite a superadministracion crear, consultar y publicar documentos legales.

### Problema que resuelve
Centraliza el control de politicas y textos legales que deben aceptar usuarios.

### Usuario que la utiliza
Superadministrador.

### Datos de entrada
Titulo, contenido, version, estado y datos del documento legal.

### Validaciones
Campos requeridos, version valida y permisos de administracion legal.

### Proceso
El superadministrador consulta documentos, crea uno nuevo o publica una version. El sistema persiste el documento y registra auditoria.

### Resultado esperado
Documento legal disponible para aceptacion cuando se publica.

### Informacion afectada
Crea y actualiza documentos legales y consulta aceptaciones.

### Relacion con otros modulos
Aceptaciones legales, usuarios, portal y auditoria.

### Reglas de negocio
Solo documentos publicados deben exigirse al usuario.

### Permisos requeridos
`legal.ver` y `legal.administrar`.

### Posibles errores
Datos incompletos, documento inexistente, version invalida o falta de permiso.

### Impacto
Define obligaciones legales transversales del sistema.

### Observaciones
Funcion reservada a superadministracion; no aparece como permiso asignable a roles de firma.

## Nombre de la funcion: gestionarFirmas

### Modulo
Superadministracion de firmas.

### Proposito
Permite crear, editar, suspender, reactivar y consultar firmas tenant.

### Problema que resuelve
Administra las organizaciones que usan el SaaS y su estado comercial/operativo.

### Usuario que la utiliza
Superadministrador.

### Datos de entrada
Nombre de firma, slug, zona horaria, motivo de suspension e ID de firma segun accion.

### Validaciones
Slug unico, datos de firma validos, firma existente y motivo minimo para suspension.

### Proceso
El superadministrador consulta firmas; crea o edita datos; al crear se genera UUID y rol administrador protegido; al suspender se cambia estado y se revocan sesiones; al reactivar se limpia motivo.

### Resultado esperado
Firma creada, actualizada, suspendida, reactivada o con uso consultado.

### Informacion afectada
Crea y modifica firmas, roles base, sesiones y auditoria.

### Relacion con otros modulos
Usuarios, roles, planes, limites, sesiones y auditoria.

### Reglas de negocio
El slug debe ser unico. Una firma suspendida no debe operar normalmente. Toda firma nueva recibe rol administrador protegido.

### Permisos requeridos
`firmas.ver`, `firmas.crear`, `firmas.editar`, `firmas.suspender`, `firmas.reactivar`.

### Posibles errores
Slug duplicado, firma inexistente, motivo invalido, falta de permiso o error de base de datos.

### Impacto
Controla el ciclo de vida de cada tenant.

### Observaciones
El uso de firma se consulta como resumen numerico; detalles exactos dependen del repositorio.

## Nombre de la funcion: crearAdministradorDeFirma

### Modulo
Superadministracion de firmas.

### Proposito
Permite crear un usuario administrador dentro de una firma especifica.

### Problema que resuelve
Facilita entregar acceso inicial o recuperar administracion de un tenant.

### Usuario que la utiliza
Superadministrador.

### Datos de entrada
ID de firma, nombre, correo, contrasena y datos de usuario.

### Validaciones
Firma existente, correo unico en el alcance, contrasena valida y rol administrador disponible.

### Proceso
El superadministrador envia datos; el sistema normaliza usuario, asigna rol administrador de la firma y registra auditoria.

### Resultado esperado
Administrador interno creado y habilitado para acceder a la firma.

### Informacion afectada
Crea usuario, asigna roles y registra auditoria.

### Relacion con otros modulos
Firmas, usuarios, roles, autenticacion y auditoria.

### Reglas de negocio
El administrador debe pertenecer a la firma indicada y no a otra.

### Permisos requeridos
`firmas.editar`.

### Posibles errores
Firma inexistente, correo duplicado, rol administrador faltante o limite de usuarios.

### Impacto
Permite operar una firma recien creada o recuperar control administrativo.

### Observaciones
No se documenta envio automatico de invitacion por correo en esta funcion.

## Nombre de la funcion: gestionarPlanes

### Modulo
Planes y limites.

### Proposito
Permite crear, editar y consultar planes comerciales con sus limites.

### Problema que resuelve
Define capacidades disponibles para cada firma segun plan contratado.

### Usuario que la utiliza
Superadministrador.

### Datos de entrada
Codigo, nombre, descripcion, estado y definiciones de limites por recurso.

### Validaciones
Datos obligatorios del plan, estado valido y politicas de limite permitidas.

### Proceso
El superadministrador registra o edita un plan; el sistema normaliza codigo, guarda plan, reemplaza limites si se enviaron y registra auditoria.

### Resultado esperado
Plan creado o actualizado con limites coherentes.

### Informacion afectada
Crea o modifica planes y limites de plan.

### Relacion con otros modulos
Firmas, limites de creacion, auditoria y middleware de plan.

### Reglas de negocio
Politicas permitidas: `warn`, `upgrade` o `block`. Limites vacios representan sin limite explicito.

### Permisos requeridos
`planes.ver`, `planes.crear`, `planes.editar`.

### Posibles errores
Datos invalidos, plan inexistente o falta de permiso.

### Impacto
Condiciona si una firma puede crear usuarios, casos, documentos, pagos y otros recursos.

### Observaciones
El usuario pidio no tocar la edicion de limites; este documento solo describe su existencia.

## Nombre de la funcion: asignarPlanAFirma

### Modulo
Planes y limites.

### Proposito
Asocia un plan existente a una firma.

### Problema que resuelve
Permite activar o cambiar la capacidad comercial de un tenant.

### Usuario que la utiliza
Superadministrador.

### Datos de entrada
ID de firma e ID de plan.

### Validaciones
Firma existente y plan existente.

### Proceso
El superadministrador selecciona firma y plan; el sistema actualiza la asignacion y registra auditoria.

### Resultado esperado
La firma queda asociada al plan indicado.

### Informacion afectada
Actualiza relacion firma-plan y auditoria.

### Relacion con otros modulos
Firmas, planes, limites y middleware de capacidad.

### Reglas de negocio
Solo planes existentes pueden asignarse.

### Permisos requeridos
`planes.asignar`.

### Posibles errores
Firma inexistente, plan inexistente o falta de permiso.

### Impacto
Cambia las restricciones operativas de la firma.

### Observaciones
Pendiente de aclaracion: reglas de prorrateo, facturacion o historial comercial no estan visibles en el codigo.

## Nombre de la funcion: crearExcepcionDeLimite

### Modulo
Planes y limites.

### Proposito
Permite configurar una excepcion de limite para una firma y recurso.

### Problema que resuelve
Da flexibilidad para ampliar o ajustar capacidad sin cambiar todo el plan.

### Usuario que la utiliza
Superadministrador.

### Datos de entrada
Firma, plan, recurso, limite, politica y motivo.

### Validaciones
Recurso, politica, limite y motivo segun servicio de limites.

### Proceso
El superadministrador envia excepcion; el sistema valida, guarda el override y registra auditoria.

### Resultado esperado
Limite especifico actualizado para el recurso indicado.

### Informacion afectada
Crea o modifica excepciones de limites.

### Relacion con otros modulos
Planes, firmas y validaciones de capacidad al crear recursos.

### Reglas de negocio
Las politicas de limite deben ser respetadas por el middleware y servicios de plan.

### Permisos requeridos
`limites.editar`.

### Posibles errores
Datos invalidos, firma/plan inexistente o falta de permiso.

### Impacto
Puede habilitar o restringir creacion de recursos para una firma.

### Observaciones
Detalle exacto del motivo minimo y precedencia de limites queda en el servicio de limites.

## Nombre de la funcion: gestionarUsuarios

### Modulo
Usuarios.

### Proposito
Permite listar, crear, editar, desactivar y reactivar usuarios de una firma.

### Problema que resuelve
Administra quienes pueden operar dentro de la firma o acceder como clientes externos.

### Usuario que la utiliza
Administrador o usuario interno con permisos de usuarios.

### Datos de entrada
Nombre, correo, contrasena inicial en creacion, tipo de usuario, estado y roles.

### Validaciones
Correo valido y unico por firma, contrasena minima al crear, tipo valido, estado valido, roles validos y regla de ultimo administrador activo.

### Proceso
El usuario autorizado consulta equipo; crea o edita datos; el sistema normaliza correo, valida duplicados, guarda usuario, sincroniza roles, cambia estado y revoca sesiones si se inactiva.

### Resultado esperado
Usuario creado o actualizado con roles y estado correctos.

### Informacion afectada
Crea o modifica usuarios, roles de usuario, sesiones y auditoria.

### Relacion con otros modulos
Autenticacion, roles, permisos, sesiones, portal y auditoria.

### Reglas de negocio
Usuarios externos no reciben roles internos. No se puede dejar la firma sin administrador activo. Inactivar usuario revoca sesiones.

### Permisos requeridos
`usuarios.ver`, `usuarios.crear`, `usuarios.editar`, `usuarios.desactivar`; creacion consume limite `usuarios`.

### Posibles errores
Correo duplicado, datos invalidos, rol no valido, usuario inexistente, ultimo administrador o limite de plan.

### Impacto
Controla acceso humano al sistema y al portal.

### Observaciones
La contrasena se almacena con hash y el usuario creado queda con cambio obligatorio de contrasena.

## Nombre de la funcion: gestionarRoles

### Modulo
Roles y permisos.

### Proposito
Permite crear, editar y asignar roles con permisos a usuarios internos.

### Problema que resuelve
Define que puede hacer cada usuario dentro de una firma.

### Usuario que la utiliza
Administrador o usuario interno con permisos de roles.

### Datos de entrada
Codigo, nombre, descripcion, estado, permisos y usuario destino cuando se asignan roles.

### Validaciones
Datos de rol validos, permisos asignables, rol existente, usuario existente y usuario interno.

### Proceso
Se consulta lista de roles y permisos; se crea o edita rol; el sistema filtra permisos reservados; sincroniza permisos; para asignacion, sincroniza roles del usuario.

### Resultado esperado
Roles actualizados y permisos efectivos disponibles en autenticacion.

### Informacion afectada
Crea/modifica roles, relacion rol-permiso y usuario-rol.

### Relacion con otros modulos
Usuarios, autenticacion, middleware de permisos y auditoria.

### Reglas de negocio
Permisos de superadmin, planes, limites y checklist no son asignables a roles de firma. Roles protegidos conservan codigo, estado y permisos base.

### Permisos requeridos
`roles.ver`, `roles.crear`, `roles.editar`, `roles.asignar`; creacion consume limite `roles`.

### Posibles errores
Permiso no asignable, usuario externo, rol protegido, datos invalidos o falta de permiso.

### Impacto
Es la base del control RBAC de la firma.

### Observaciones
Los permisos con comodin son soportados por el motor de permisos, pero la UI de roles filtra permisos reservados.

## Nombre de la funcion: consultarAuditoria

### Modulo
Auditoria.

### Proposito
Permite consultar eventos registrados por acciones relevantes.

### Problema que resuelve
Da trazabilidad sobre cambios, accesos y operaciones sensibles.

### Usuario que la utiliza
Superadministrador o usuario interno autorizado.

### Datos de entrada
Filtros de consulta como modulo, accion, fecha o entidad segun pantalla.

### Validaciones
Permiso de auditoria y alcance de firma o superadmin.

### Proceso
El usuario abre auditoria; el sistema consulta eventos segun alcance y filtros; devuelve listado.

### Resultado esperado
Eventos visibles para revision o control.

### Informacion afectada
Consulta registros de auditoria. No modifica datos.

### Relacion con otros modulos
Todos los modulos que registran acciones.

### Reglas de negocio
Usuarios de firma no deben ver auditoria de otras firmas.

### Permisos requeridos
`auditoria.ver`.

### Posibles errores
Falta de permiso, filtros invalidos o error de consulta.

### Impacto
Soporta control interno, cumplimiento y diagnostico.

### Observaciones
La auditoria registra hashes para algunos datos sensibles en vez de valores completos.

## Nombre de la funcion: gestionarCatalogos

### Modulo
Catalogos y configuracion.

### Proposito
Permite crear catalogos, consultar detalle, agregar items, editar items y cambiar su estado.

### Problema que resuelve
Estandariza valores usados por formularios como tipo de documento, moneda, medio, fuente, tipo de caso, jurisdiccion y otros.

### Usuario que la utiliza
Superadministrador o administrador interno con configuracion.

### Datos de entrada
Codigo de catalogo, nombre, descripcion, items, etiquetas, codigos, orden, estado y alcance.

### Validaciones
Codigos validos, duplicados, pertenencia al alcance y permisos.

### Proceso
El usuario consulta catalogos; crea catalogo o item; el sistema valida, guarda y permite activar/inactivar items.

### Resultado esperado
Catalogos disponibles para formularios y validaciones de negocio.

### Informacion afectada
Crea y modifica catalogos e items.

### Relacion con otros modulos
Clientes, prospectos, casos, audiencias, finanzas, importaciones y reportes.

### Reglas de negocio
Los items activos son los disponibles para normalizacion. Puede haber catalogos globales y de firma.

### Permisos requeridos
`configuracion.ver`, `configuracion.editar`; creacion puede consumir limite `catalogos`.

### Posibles errores
Codigo duplicado, item invalido, catalogo inexistente o falta de permiso.

### Impacto
Reduce valores libres y mejora consistencia operativa.

### Observaciones
Se agregaron catalogos normalizados por migracion para valores transversales.

## Nombre de la funcion: buscarDatosParaSelectores

### Modulo
Busqueda auxiliar y API interna.

### Proposito
Provee resultados compactos para selectores AJAX de clientes, casos, usuarios, tareas, terminos, honorarios, documentos, pagos y gastos.

### Problema que resuelve
Evita cargar listas completas en formularios y reduce errores al relacionar registros.

### Usuario que la utiliza
Usuarios internos desde formularios del sistema; el cliente externo no usa estas rutas internas.

### Datos de entrada
Texto de busqueda, limite y filtros opcionales como cliente_id, caso_id, saldo o tipo de usuario.

### Validaciones
Firma activa, permisos por tipo de entidad y limite maximo de resultados.

### Proceso
El formulario llama al endpoint; el sistema aplica alcance de firma, filtros y texto; devuelve value, label y metadatos utiles.

### Resultado esperado
Opciones seleccionables consistentes y acotadas.

### Informacion afectada
Consulta registros. No crea ni modifica datos.

### Relacion con otros modulos
CRM, casos, agenda, documentos, finanzas, portal y reportes.

### Reglas de negocio
Cada busqueda respeta permisos del modulo correspondiente y solo devuelve datos de la firma activa.

### Permisos requeridos
Depende del recurso: por ejemplo `clientes.ver`, `casos.ver`, `usuarios.ver`, `terminos.ver`, `documentos.ver`, `finanzas.ver` o `portal.autorizar`.

### Posibles errores
Falta de permiso, firma no activa, consulta SQL fallida o recurso no disponible.

### Impacto
Mejora usabilidad y coherencia de relaciones entre modulos.

### Observaciones
Es una funcion de soporte para pantallas, no un modulo de negocio independiente.

## Nombre de la funcion: gestionarClientes

### Modulo
Clientes.

### Proposito
Permite listar, ver, crear, editar, eliminar logicamente y revelar datos protegidos de clientes.

### Problema que resuelve
Centraliza la informacion de personas naturales o juridicas atendidas por la firma.

### Usuario que la utiliza
Usuario interno autorizado.

### Datos de entrada
Nombre o razon social, tipo de persona, tipo y numero de documento, datos de contacto, origen, tratamiento de datos, medio de autorizacion y estado.

### Validaciones
Datos requeridos, email valido, catalogos normalizados, documento no duplicado, tratamiento de datos cuando aplica y pertenencia a firma.

### Proceso
El usuario consulta o crea cliente; el sistema normaliza datos, valida duplicados, guarda registro y registra auditoria. Para revelar datos sensibles exige permiso especifico.

### Resultado esperado
Cliente creado, actualizado, consultado, eliminado logicamente o dato revelado.

### Informacion afectada
Crea, consulta, actualiza o marca eliminado un cliente; registra auditoria.

### Relacion con otros modulos
Prospectos, casos, documentos, finanzas, portal, importaciones y reportes.

### Reglas de negocio
No deben existir documentos duplicados en la firma. Los datos sensibles se muestran enmascarados salvo permiso de revelacion.

### Permisos requeridos
`clientes.ver`, `clientes.crear`, `clientes.editar`, `clientes.eliminar`, `clientes.revelar`; creacion consume limite `clientes`.

### Posibles errores
Documento duplicado, catalogo invalido, datos incompletos, falta de permiso o limite de plan.

### Impacto
Es la base de la relacion comercial y juridica del sistema.

### Observaciones
Eliminacion es logica mediante `deleted_at`, no borrado fisico.

## Nombre de la funcion: gestionarProspectos

### Modulo
Prospectos.

### Proposito
Permite administrar oportunidades antes de convertirlas en clientes.

### Problema que resuelve
Separa contactos comerciales potenciales de clientes formalmente creados.

### Usuario que la utiliza
Usuario interno autorizado.

### Datos de entrada
Nombre, tipo de persona, documento, correo, telefono, empresa, fuente, estado, responsable, valor estimado, autorizacion de datos y notas.

### Validaciones
Catalogos de documento/fuente, datos requeridos, email, responsable existente y estados permitidos.

### Proceso
El usuario crea o edita prospecto; puede cambiar estado; si se convierte, el sistema crea cliente asociado y marca el prospecto como ganado/convertido.

### Resultado esperado
Prospecto registrado, actualizado, con estado modificado o convertido en cliente.

### Informacion afectada
Crea/modifica prospectos y, al convertir, crea clientes.

### Relacion con otros modulos
Clientes, usuarios responsables, auditoria y catalogos.

### Reglas de negocio
Un prospecto ya convertido no debe convertirse de nuevo.

### Permisos requeridos
`prospectos.ver`, `prospectos.crear`, `prospectos.editar`, `prospectos.convertir`; creacion consume limite `prospectos`.

### Posibles errores
Prospecto inexistente, datos invalidos, responsable invalido, documento duplicado al convertir o falta de permiso.

### Impacto
Conecta gestion comercial con gestion juridica de clientes.

### Observaciones
El medio de autorizacion usado al convertir desde prospecto se normaliza como registro interno.

## Nombre de la funcion: gestionarCasos

### Modulo
Casos.

### Proposito
Permite crear, consultar, editar, cerrar y archivar expedientes o casos juridicos.

### Problema que resuelve
Organiza el trabajo juridico asociado a clientes y centraliza su ficha 360.

### Usuario que la utiliza
Usuario interno autorizado.

### Datos de entrada
Cliente, titulo, radicado, tipo de proceso, jurisdiccion, despacho, responsable, prioridad, fecha de apertura, estado y descripcion.

### Validaciones
Cliente existente, responsable interno valido, catalogos normalizados, radicado disponible, estado/prioridad validos y pertenencia a firma.

### Proceso
El usuario crea o edita caso; el sistema valida relaciones, guarda datos normalizados, registra auditoria. Para cerrar o archivar exige motivo y cambia estado.

### Resultado esperado
Caso disponible en lista y ficha, o marcado como cerrado/archivado.

### Informacion afectada
Crea y modifica casos; consulta relaciones de cliente y usuario; registra auditoria.

### Relacion con otros modulos
Clientes, partes, timeline, terminos, audiencias, tareas, documentos, finanzas, portal y reportes.

### Reglas de negocio
El caso siempre pertenece a un cliente de la firma. Cierre y archivo requieren accion explicita.

### Permisos requeridos
`casos.ver`, `casos.crear`, `casos.editar`, `casos.cerrar`, `casos.archivar`; creacion consume limite `casos`.

### Posibles errores
Cliente no valido, radicado duplicado, catalogo invalido, responsable no valido, falta de motivo o limite de plan.

### Impacto
Es el nucleo operativo de la gestion juridica.

### Observaciones
La ficha 360 conecta rapidamente con partes, timeline, terminos, audiencias y tareas.

## Nombre de la funcion: gestionarPartesProcesales

### Modulo
Partes procesales.

### Proposito
Permite registrar personas o entidades vinculadas a un caso.

### Problema que resuelve
Mantiene control de demandantes, demandados, testigos, apoderados, entidades y otros actores del expediente.

### Usuario que la utiliza
Usuario interno autorizado.

### Datos de entrada
Caso, tipo de parte, nombre, tipo y numero de documento, correo, telefono, direccion, estado y observaciones.

### Validaciones
Caso existente, tipo de parte valido, nombre requerido, catalogo de documento y datos de contacto validos.

### Proceso
El usuario abre partes de un caso; crea, edita o elimina una parte. El sistema enmascara datos sensibles y solo los revela con permiso.

### Resultado esperado
Parte procesal asociada al caso, actualizada, eliminada logicamente o dato sensible revelado.

### Informacion afectada
Crea, modifica, consulta o elimina logicamente partes; registra auditoria.

### Relacion con otros modulos
Casos, auditoria y permisos de revelacion.

### Reglas de negocio
Datos sensibles como documento, correo, telefono y direccion se muestran enmascarados por defecto.

### Permisos requeridos
`partes.ver`, `partes.crear`, `partes.editar`, `partes.eliminar`, `partes.revelar`.

### Posibles errores
Caso inexistente, parte inexistente, tipo invalido, dato sensible no permitido o falta de permiso.

### Impacto
Da contexto procesal al expediente y facilita seguimiento juridico.

### Observaciones
La eliminacion es logica.

## Nombre de la funcion: gestionarLineaDeTiempo

### Modulo
Linea de tiempo de casos.

### Proposito
Permite registrar eventos cronologicos del caso y controlar su visibilidad.

### Problema que resuelve
Construye historial ordenado de actuaciones, hitos o comunicaciones del expediente.

### Usuario que la utiliza
Usuario interno autorizado.

### Datos de entrada
Caso, fecha, titulo, descripcion, tipo de evento, visibilidad y datos adjuntos o referencia segun formulario.

### Validaciones
Caso existente, datos requeridos, permisos y visibilidad valida.

### Proceso
El usuario consulta timeline del caso; crea o edita evento; puede cambiar visibilidad o eliminarlo. El sistema guarda cambios y audita.

### Resultado esperado
Evento disponible en la linea de tiempo o modificado segun accion.

### Informacion afectada
Crea, actualiza, consulta o elimina logicamente eventos de timeline.

### Relacion con otros modulos
Casos, portal y auditoria.

### Reglas de negocio
Solo eventos con visibilidad adecuada deben mostrarse fuera del ambito interno.

### Permisos requeridos
`timeline.ver`, `timeline.crear`, `timeline.editar`, `timeline.publicar`, `timeline.eliminar`.

### Posibles errores
Caso inexistente, evento inexistente, visibilidad invalida o falta de permiso.

### Impacto
Permite reconstruir la historia del caso.

### Observaciones
Pendiente de aclaracion: catalogo exacto de tipos de evento visible para usuario final.

## Nombre de la funcion: gestionarTerminos

### Modulo
Terminos.

### Proposito
Permite crear, editar, cumplir y eliminar terminos juridicos o plazos.

### Problema que resuelve
Ayuda a controlar vencimientos criticos y evitar incumplimientos.

### Usuario que la utiliza
Usuario interno autorizado.

### Datos de entrada
Caso opcional, tarea vinculada opcional, responsable, titulo, descripcion, fecha inicio, fecha vencimiento, prioridad, alerta dias y estado.

### Validaciones
Caso y tarea pertenecen a la firma, fechas validas, prioridad/estado validos y alerta dentro de rango.

### Proceso
El usuario crea o edita termino; el sistema calcula estado visual segun vencimiento; puede marcar cumplimiento con observacion o eliminarlo logicamente.

### Resultado esperado
Termino registrado, actualizado, cumplido o eliminado.

### Informacion afectada
Crea/modifica terminos, puede vincular tareas y registra cumplimiento/auditoria.

### Relacion con otros modulos
Casos, tareas, notificaciones, dashboard y reportes.

### Reglas de negocio
Terminos vencidos o criticos deben destacarse visualmente. Cumplir un termino registra fecha y usuario.

### Permisos requeridos
`terminos.ver`, `terminos.crear`, `terminos.editar`, `terminos.cumplir`, `terminos.eliminar`; creacion consume limite `terminos`.

### Posibles errores
Fecha invalida, caso/tarea no valido, termino inexistente, estado no permitido o limite de plan.

### Impacto
Es una funcion clave para control de riesgos juridicos.

### Observaciones
El calculo de estado visual depende de la fecha de vencimiento y dias de alerta.

## Nombre de la funcion: gestionarAudiencias

### Modulo
Audiencias.

### Proposito
Permite programar, editar y registrar resultado de audiencias.

### Problema que resuelve
Organiza agenda procesal vinculada a casos y responsables.

### Usuario que la utiliza
Usuario interno autorizado.

### Datos de entrada
Caso, titulo, fecha, hora, zona horaria, modalidad, despacho, juez responsable, contacto de despacho, lugar, enlace, responsable y estado.

### Validaciones
Caso existente, responsable valido, fecha/hora, modalidad/estado validos, despacho normalizado y longitudes maximas de juez/contacto.

### Proceso
El usuario crea o edita audiencia; el sistema guarda datos y calcula estado visual. Posteriormente puede registrar resultado mediante accion separada.

### Resultado esperado
Audiencia programada o actualizada; resultado guardado cuando se registra.

### Informacion afectada
Crea y modifica audiencias; registra resultado y auditoria.

### Relacion con otros modulos
Casos, usuarios, dashboard, notificaciones y reportes.

### Reglas de negocio
Cada audiencia pertenece a un caso de la firma. Resultado se registra como accion especifica.

### Permisos requeridos
`audiencias.ver`, `audiencias.crear`, `audiencias.editar`, `audiencias.registrar_resultado`; creacion consume limite `audiencias`.

### Posibles errores
Caso invalido, fecha/hora invalida, despacho no catalogado, audiencia inexistente o limite de plan.

### Impacto
Mejora control de agenda y seguimiento procesal.

### Observaciones
Los campos juez responsable y contacto de despacho fueron agregados por migracion.

## Nombre de la funcion: gestionarTareas

### Modulo
Tareas.

### Proposito
Permite crear, editar, reasignar y cambiar estado de tareas operativas.

### Problema que resuelve
Organiza trabajo diario relacionado o no con casos y terminos.

### Usuario que la utiliza
Usuario interno autorizado.

### Datos de entrada
Caso opcional, termino opcional, responsable, titulo, descripcion, prioridad, estado y fecha de vencimiento.

### Validaciones
Caso/termino pertenecen a firma, responsable interno valido, estado/prioridad validos y fecha de vencimiento no anterior al dia actual.

### Proceso
El usuario crea o edita tarea; puede reasignarla a otro usuario; puede cambiar estado. El sistema sincroniza relacion con terminos cuando corresponde.

### Resultado esperado
Tarea disponible, reasignada o con estado actualizado.

### Informacion afectada
Crea/modifica tareas, registra reasignaciones, completado y auditoria.

### Relacion con otros modulos
Casos, terminos, usuarios, dashboard y reportes.

### Reglas de negocio
No permite vencimientos pasados. Al completar se registra fecha y usuario.

### Permisos requeridos
`tareas.ver`, `tareas.crear`, `tareas.editar`, `tareas.reasignar`, `tareas.cambiar_estado`; creacion consume limite `tareas`.

### Posibles errores
Fecha pasada, usuario invalido, caso/termino no valido, estado invalido o limite de plan.

### Impacto
Permite distribuir y controlar trabajo operativo de la firma.

### Observaciones
Estado visual resalta tareas vencidas.

## Nombre de la funcion: gestionarDocumentos

### Modulo
Documentos.

### Proposito
Permite cargar, consultar, editar metadata, versionar, descargar y eliminar documentos.

### Problema que resuelve
Centraliza archivos juridicos y soportes vinculados a clientes, casos o gastos.

### Usuario que la utiliza
Usuario interno autorizado y cliente externo para descarga de documentos autorizados.

### Datos de entrada
Archivo, titulo, descripcion, tipo documental, cliente, caso, gasto, visibilidad en portal y estado.

### Validaciones
Documento asociado a cliente/caso/gasto, relaciones coherentes, archivo valido, extension/MIME permitido, tamano maximo 15 MB y checksum.

### Proceso
El usuario carga documento; el sistema valida archivo, crea registro, almacena version fisica, marca version actual y audita. Puede editar metadata, crear nueva version, descargar o eliminar logicamente.

### Resultado esperado
Documento disponible con version actual y metadata consistente.

### Informacion afectada
Crea/modifica documentos, versiones, archivos en storage y auditoria.

### Relacion con otros modulos
Clientes, casos, gastos, portal, importaciones, reportes y auditoria.

### Reglas de negocio
El archivo debe pasar validacion de extension, MIME, tamano e integridad. Descarga verifica checksum. En el estado actual no existe antivirus, cuarentena, inspeccion de macros ni cifrado en reposo a nivel de aplicacion.

### Permisos requeridos
`documentos.ver`, `documentos.cargar`, `documentos.editar`, `documentos.eliminar`, `documentos.versionar`, `documentos.descargar`; carga consume limite `documentos`.

### Posibles errores
Archivo invalido, tamano excedido, MIME no permitido, documento sin asociacion, integridad fallida o falta de permiso.

### Impacto
Es repositorio documental y soporte de evidencias.

### Observaciones
El almacenamiento se guarda fuera de `public` en `storage`. Esta proteccion reduce exposicion web directa, pero para produccion falta agregar inspeccion antivirus o control equivalente, bloqueo de descarga para archivos no inspeccionados y cifrado en reposo o control de infraestructura aprobado.

## Nombre de la funcion: gestionarHonorarios

### Modulo
Finanzas.

### Proposito
Permite registrar y actualizar honorarios pactados con clientes.

### Problema que resuelve
Controla obligaciones de cobro por servicios juridicos.

### Usuario que la utiliza
Usuario interno autorizado de finanzas.

### Datos de entrada
Cliente, caso opcional, concepto, descripcion, monto, moneda, fecha de acuerdo y estado.

### Validaciones
Cliente existente, caso del cliente, concepto y moneda catalogados, monto mayor a cero y monto no menor a pagos ya registrados.

### Proceso
El usuario crea honorario; el sistema normaliza concepto/moneda, valida relacion y guarda. Al actualizar recalcula estado segun pagos salvo cancelacion.

### Resultado esperado
Honorario registrado con saldo, pagos acumulados y estado correcto.

### Informacion afectada
Crea/modifica honorarios y consulta pagos relacionados.

### Relacion con otros modulos
Clientes, casos, pagos, saldos, portal y reportes.

### Reglas de negocio
No se puede reducir monto por debajo de lo pagado. Estados de cobro se derivan de pagos: pendiente, parcial o pagado.

### Permisos requeridos
`finanzas.ver`, `finanzas.crear`, `finanzas.editar`; creacion consume limite `honorarios`.

### Posibles errores
Monto invalido, catalogo invalido, cliente/caso no valido, monto menor a pagado o limite de plan.

### Impacto
Base del control de cartera y saldos.

### Observaciones
El estado solicitado al crear se fuerza a pendiente salvo honorario cancelado.

## Nombre de la funcion: registrarPagos

### Modulo
Finanzas.

### Proposito
Permite registrar pagos recibidos y revelar referencias protegidas con permiso.

### Problema que resuelve
Controla recaudos y evita sobrepagos o duplicados.

### Usuario que la utiliza
Usuario interno autorizado de finanzas.

### Datos de entrada
Honorario opcional, cliente, caso, monto, moneda, fecha de pago, metodo de pago, referencia y observaciones.

### Validaciones
Cliente/caso validos, honorario de la firma, metodo y moneda catalogados, monto positivo, no duplicado y no superior al saldo del honorario.

### Proceso
El usuario registra pago; si selecciona honorario, el sistema hereda cliente, caso y moneda; valida saldo; guarda pago; actualiza estado del honorario; registra auditoria. La referencia se muestra enmascarada salvo permiso.

### Resultado esperado
Pago registrado y honorario actualizado a parcial o pagado.

### Informacion afectada
Crea pagos, actualiza honorarios y registra auditoria.

### Relacion con otros modulos
Honorarios, clientes, casos, saldos, portal y reportes.

### Reglas de negocio
No se puede pagar un honorario cancelado ni superar saldo pendiente. Referencias se protegen.

### Permisos requeridos
`finanzas.ver`, `finanzas.crear`, `finanzas.revelar`; creacion consume limite `pagos`.

### Posibles errores
Pago duplicado, saldo insuficiente, honorario cancelado, catalogo invalido o falta de permiso.

### Impacto
Actualiza cartera, saldos y trazabilidad financiera.

### Observaciones
No hay ruta visible de edicion de pagos, solo registro y revelacion de referencia.

## Nombre de la funcion: gestionarGastos

### Modulo
Finanzas.

### Proposito
Permite registrar y actualizar gastos operativos asociados a clientes, casos y documentos soporte.

### Problema que resuelve
Controla erogaciones y soportes relacionados con gestion juridica.

### Usuario que la utiliza
Usuario interno autorizado de finanzas.

### Datos de entrada
Cliente, caso opcional, soporte documental opcional, concepto, categoria, monto, moneda, fecha, estado y observaciones.

### Validaciones
Cliente/caso/documento pertenecen a la firma, soporte compatible con cliente/caso, moneda catalogada y monto mayor a cero.

### Proceso
El usuario registra gasto; el sistema valida relaciones, guarda gasto y vincula soporte si existe. En edicion actualiza datos y puede anexar soporte.

### Resultado esperado
Gasto registrado o actualizado con soporte asociado.

### Informacion afectada
Crea/modifica gastos, relacion gasto-soporte y auditoria.

### Relacion con otros modulos
Clientes, casos, documentos, saldos, portal y reportes.

### Reglas de negocio
El soporte documental no puede pertenecer a otro cliente/caso incompatible.

### Permisos requeridos
`finanzas.ver`, `finanzas.crear`, `finanzas.editar`; creacion consume limite `gastos`.

### Posibles errores
Monto invalido, soporte incompatible, cliente/caso inexistente, moneda invalida o limite de plan.

### Impacto
Afecta calculos de saldos y visibilidad financiera.

### Observaciones
La anulacion se maneja como estado, no como borrado visible en rutas actuales.

## Nombre de la funcion: consultarSaldos

### Modulo
Finanzas.

### Proposito
Calcula resumen de honorarios, pagos, gastos y saldo.

### Problema que resuelve
Permite conocer posicion financiera por firma, cliente o caso.

### Usuario que la utiliza
Usuario interno autorizado de finanzas.

### Datos de entrada
Filtros opcionales de cliente y caso.

### Validaciones
Firma activa y permisos de finanzas. Los IDs de cliente/caso se validan como enteros.

### Proceso
El usuario selecciona filtros; el sistema calcula totales y lista saldos derivados por cliente.

### Resultado esperado
Resumen numerico y tabla de saldos.

### Informacion afectada
Consulta honorarios, pagos y gastos. No modifica datos.

### Relacion con otros modulos
Honorarios, pagos, gastos, clientes y casos.

### Reglas de negocio
Los calculos deben respetar estado y pertenencia a firma.

### Permisos requeridos
`finanzas.ver`.

### Posibles errores
Filtros invalidos, falta de permiso o error de consulta.

### Impacto
Apoya decisiones de cobro y control financiero.

### Observaciones
Pendiente de aclaracion: formula contable exacta de saldo por escenarios de anulacion.

## Nombre de la funcion: autorizarPortalCliente

### Modulo
Portal de clientes.

### Proposito
Permite autorizar o revocar recursos que un cliente externo puede ver en el portal.

### Problema que resuelve
Controla que informacion de casos, documentos, finanzas o usuarios queda disponible al cliente.

### Usuario que la utiliza
Usuario interno con permiso de autorizacion de portal.

### Datos de entrada
Cliente, tipo de recurso, ID del recurso, estado, observacion publica y observacion interna.

### Validaciones
Cliente existente, recurso existente, recurso perteneciente al cliente cuando aplica, usuario externo valido si el recurso es usuario portal.

### Proceso
El usuario elige cliente y recurso; el sistema valida pertenencia; crea o actualiza permiso de portal; registra observaciones y auditoria.

### Resultado esperado
Recurso autorizado o revocado para el cliente.

### Informacion afectada
Actualiza permisos de portal para casos, documentos, finanzas o usuarios externos y crea observaciones.

### Relacion con otros modulos
Clientes, casos, documentos, finanzas, usuarios externos y portal cliente.

### Reglas de negocio
No se puede autorizar un recurso de otro cliente. Solo usuarios `cliente_externo` pueden vincularse al portal.

### Permisos requeridos
`portal.autorizar`.

### Posibles errores
Cliente invalido, recurso inexistente, recurso de otro cliente, usuario no externo o falta de permiso.

### Impacto
Define la informacion visible para clientes externos.

### Observaciones
Las finanzas autorizadas se abren desde listados filtrados porque no hay pantalla de detalle individual para todos los recursos financieros.

## Nombre de la funcion: usarPortalCliente

### Modulo
Portal de clientes.

### Proposito
Permite al cliente externo ver informacion autorizada y descargar documentos autorizados.

### Problema que resuelve
Ofrece acceso limitado y controlado al cliente sin exponer toda la operacion interna.

### Usuario que la utiliza
Cliente externo autenticado.

### Datos de entrada
Sesion de cliente externo, firma activa, recurso autorizado y, para descarga, ID de documento.

### Validaciones
Usuario externo, firma activa, relacion portal-cliente activa y permiso/autorizacion del recurso.

### Proceso
El cliente ingresa al portal; el sistema consulta recursos autorizados; muestra casos, documentos y finanzas permitidas; permite descargar documentos autorizados y aceptar legales.

### Resultado esperado
Portal con informacion autorizada y descargas disponibles.

### Informacion afectada
Consulta recursos autorizados; descarga documentos; crea aceptaciones legales cuando aplica.

### Relacion con otros modulos
Autenticacion, autorizaciones portal, documentos, casos, finanzas y legal.

### Reglas de negocio
El cliente externo solo ve recursos explicitamente autorizados.

### Permisos requeridos
Middleware `portal` y usuario autenticado; legal usa `legal.aceptar` segun configuracion de portal.

### Posibles errores
Sin autorizacion, documento no disponible, integridad fallida, firma suspendida o sesion invalida.

### Impacto
Habilita colaboracion externa con control de seguridad.

### Observaciones
Pendiente de aclaracion: alcance visual exacto de cada recurso en portal.

## Nombre de la funcion: exportarReportes

### Modulo
Reportes y exportaciones.

### Proposito
Permite generar archivos CSV de distintos modulos con filtros.

### Problema que resuelve
Facilita analisis, auditoria y extraccion controlada de datos.

### Usuario que la utiliza
Usuario interno autorizado.

### Datos de entrada
Tipo de reporte, filtros de fecha, cliente, caso, estado, tipo de caso, moneda, tipo financiero, montos, modulo o accion segun reporte.

### Validaciones
Permiso de exportacion, tipo permitido, capacidad de plan, filtros validos y limite maximo de filas.

### Proceso
El usuario prepara filtros; el sistema consulta datos, evita exceso de filas, genera CSV con BOM UTF-8, guarda archivo temporal, registra exportacion y auditoria, y descarga el archivo.

### Resultado esperado
Archivo CSV descargado.

### Informacion afectada
Consulta datos de modulos y crea registro de exportacion.

### Relacion con otros modulos
Clientes, casos, terminos, audiencias, tareas, documentos, finanzas y auditoria.

### Reglas de negocio
Si supera el limite de filas, se debe ajustar filtros. Valores que podrian ser formulas se protegen en CSV.

### Permisos requeridos
`reportes.ver`, `reportes.exportar`; consume limite `exportaciones`.

### Posibles errores
Reporte inexistente, falta de permiso, demasiadas filas, filtros invalidos o error creando archivo.

### Impacto
Provee informacion fuera del sistema con trazabilidad.

### Observaciones
Los reportes disponibles dependen de permisos del usuario.

## Nombre de la funcion: importarDatosCSV

### Modulo
Importaciones.

### Proposito
Permite previsualizar y confirmar importaciones CSV de clientes y casos.

### Problema que resuelve
Agiliza carga masiva de informacion inicial o migrada.

### Usuario que la utiliza
Usuario interno autorizado.

### Datos de entrada
Tipo de importacion (`clientes` o `casos`) y archivo CSV.

### Validaciones
Tipo permitido, archivo CSV valido, tamano maximo 2 MB, MIME permitido, encabezados exactos, maximo 500 filas y campos obligatorios.

### Proceso
El usuario carga CSV para previsualizar; el sistema valida archivo, guarda temporalmente, parsea filas y registra errores. Si no hay errores, el usuario confirma; el sistema crea clientes o casos usando servicios existentes, marca importacion y elimina archivo temporal.

### Resultado esperado
Previsualizacion con errores o confirmacion con cantidad de registros creados.

### Informacion afectada
Crea importaciones y, al confirmar, crea clientes o casos.

### Relacion con otros modulos
Clientes, casos, limites de plan, auditoria y almacenamiento.

### Reglas de negocio
No se confirma una importacion con errores pendientes o expirada. La importacion valida extension, MIME, tamano, encabezados y reglas de negocio, pero no ejecuta antivirus ni sandbox de contenido.

### Permisos requeridos
`importaciones.ver`, `importaciones.crear`, `importaciones.ejecutar`; consume limite `importaciones`.

### Posibles errores
CSV invalido, encabezados incorrectos, filas con errores, importacion expirada, ya procesada o limite de plan.

### Impacto
Reduce carga manual y mantiene validaciones de negocio al crear datos.

### Observaciones
Pendiente de aclaracion: soporte futuro para mas tipos de importacion. Para produccion, las importaciones deben quedar cubiertas por la misma politica de inspeccion de archivos o por una justificacion formal de bajo riesgo para CSV.

## Nombre de la funcion: gestionarTicketsSoporte

### Modulo
Soporte.

### Proposito
Permite crear tickets, consultar tickets visibles, responder mensajes y cambiar estado.

### Problema que resuelve
Canaliza solicitudes de ayuda o incidencias desde una firma hacia soporte.

### Usuario que la utiliza
Usuario interno de firma y superadministrador para cola global.

### Datos de entrada
Asunto, categoria, prioridad, mensaje, URL de contexto, visibilidad del mensaje y estado.

### Validaciones
Datos de ticket, mensaje y estado validos; visibilidad permitida; limite de tickets; alcance del ticket.

### Proceso
El usuario crea ticket con mensaje inicial; el sistema guarda ticket y mensaje. Luego permite ver detalle, agregar mensajes o cambiar estado. Superadmin puede ver cola global y cambiar estado con alcance de firma.

### Resultado esperado
Ticket creado, respondido o actualizado.

### Informacion afectada
Crea/modifica tickets, mensajes y auditoria.

### Relacion con otros modulos
Usuarios, firmas, auditoria y superadmin.

### Reglas de negocio
Usuarios de firma solo ven tickets propios salvo alcance de firma; mensajes de visibilidad superadmin solo los crea superadmin.

### Permisos requeridos
Firma: `soporte.ver_propio`, `soporte.crear`, `soporte.responder`, `soporte.cambiar_estado`. Superadmin: `soporte.ver_global`.

### Posibles errores
Ticket inexistente, visibilidad no permitida, estado invalido, limite de plan o falta de permiso.

### Impacto
Da trazabilidad a soporte y seguimiento de incidentes.

### Observaciones
Pendiente de aclaracion: SLA, asignacion de agentes y notificaciones de soporte.

## Nombre de la funcion: gestionarOnboarding

### Modulo
Onboarding.

### Proposito
Calcula y muestra avance inicial de configuracion de una firma.

### Problema que resuelve
Ayuda a identificar pasos pendientes para que la firma use el sistema de forma completa.

### Usuario que la utiliza
Usuario interno autorizado.

### Datos de entrada
Firma activa y solicitud de actualizacion.

### Validaciones
Firma activa y permisos de onboarding.

### Proceso
El sistema revisa si existen primer cliente, primer caso, primer termino, primer documento, usuario invitado y usuario de portal; actualiza progreso y calcula porcentaje.

### Resultado esperado
Lista de pasos con estado y porcentaje de avance.

### Informacion afectada
Consulta varios modulos y actualiza progreso de onboarding.

### Relacion con otros modulos
Clientes, casos, terminos, documentos, usuarios y portal.

### Reglas de negocio
El progreso se calcula automaticamente desde datos reales de la firma.

### Permisos requeridos
`onboarding.ver` y `onboarding.administrar` para refrescar.

### Posibles errores
Falta de permiso o error consultando algun modulo.

### Impacto
Guia adopcion inicial del sistema.

### Observaciones
Los pasos estan definidos en codigo; cambiar el onboarding requiere ajustar esa lista.

## Nombre de la funcion: gestionarNotificaciones

### Modulo
Notificaciones.

### Proposito
Permite consultar notificaciones y marcarlas como leidas.

### Problema que resuelve
Informa al usuario sobre eventos relevantes y evita perder alertas.

### Usuario que la utiliza
Usuario interno autorizado.

### Datos de entrada
Filtros de consulta e ID de notificacion para marcar como leida.

### Validaciones
Notificacion existente, usuario/firma correctos y permisos.

### Proceso
El usuario abre notificaciones; el sistema lista las disponibles. Al marcar una, actualiza su estado de lectura.

### Resultado esperado
Notificacion visible o marcada como leida.

### Informacion afectada
Consulta y actualiza notificaciones.

### Relacion con otros modulos
Terminos, audiencias, tareas, dashboard y usuarios.

### Reglas de negocio
Cada usuario solo debe ver notificaciones de su alcance.

### Permisos requeridos
`notificaciones.ver`, `notificaciones.marcar_leida`.

### Posibles errores
Notificacion inexistente, falta de permiso o error de actualizacion.

### Impacto
Mejora seguimiento de alertas operativas.

### Observaciones
Pendiente de aclaracion: canales externos de notificacion y reglas de generacion.

## Nombre de la funcion: visualizarDashboard

### Modulo
Dashboard.

### Proposito
Presenta resumen operativo de la firma.

### Problema que resuelve
Concentra indicadores y accesos rapidos para priorizar trabajo.

### Usuario que la utiliza
Usuario interno autenticado de firma.

### Datos de entrada
Firma activa y permisos/sesion del usuario.

### Validaciones
Usuario autenticado, firma activa, estado comercial valido y usuario interno.

### Proceso
El usuario abre dashboard; el sistema consulta datos resumidos y devuelve vista principal.

### Resultado esperado
Panel inicial con informacion relevante.

### Informacion afectada
Consulta datos. No modifica registros.

### Relacion con otros modulos
Casos, terminos, audiencias, tareas, notificaciones y onboarding.

### Reglas de negocio
Debe mostrar solo informacion de la firma activa.

### Permisos requeridos
No se exige permiso especifico en ruta, pero requiere autenticacion, firma, estado comercial e usuario interno.

### Posibles errores
Sesion invalida, firma no activa o error consultando resumen.

### Impacto
Es punto de entrada operativo para usuarios internos.

### Observaciones
Pendiente de aclaracion: composicion exacta de indicadores del dashboard.

## Nombre de la funcion: evaluarChecklistOwner

### Modulo
Checklist owner.

### Proposito
Permite a superadministracion crear checklist, evaluar items y tomar decisiones.

### Problema que resuelve
Da un mecanismo de control interno o aprobacion sobre aspectos de configuracion/operacion.

### Usuario que la utiliza
Superadministrador autorizado.

### Datos de entrada
Datos de checklist, ID de item, estado de evaluacion, observacion, evidencia y decision.

### Validaciones
Checklist existente, item existente, estado/decision validos y permisos.

### Proceso
El superadministrador consulta checklist, crea nuevo registro, evalua items individualmente y finalmente registra decision.

### Resultado esperado
Checklist actualizado con items evaluados y decision registrada.

### Informacion afectada
Crea/modifica checklist, items, evaluaciones y auditoria.

### Relacion con otros modulos
Superadmin, auditoria y posiblemente firmas.

### Reglas de negocio
Solo superadmin con permisos de checklist puede evaluar o aprobar. Si la decision es aprobar salida comercial, el sistema exige que todos los items esten aprobados y valida especialmente `restore_drill` y `decisiones_produccion`.

### Permisos requeridos
`checklist.ver`, `checklist.evaluar`, `checklist.aprobar`.

### Posibles errores
Checklist inexistente, item invalido, decision no permitida o falta de permiso.

### Impacto
Apoya control de calidad o gobierno interno.

### Observaciones
El checklist owner funciona como control humano de salida comercial. Sus items por defecto incluyen verificacion de EXP-001 a EXP-008, OPS-001, OPS-002, restauracion de backup, decisiones de produccion, seguridad, datos y criterio comercial. No reemplaza evidencia tecnica externa: la restauracion, configuracion productiva, correo real y proteccion documental deben documentarse aparte.

## Nombre de la funcion: generarBackupBaseDatos

### Modulo
Operacion y backups.

### Proposito
Permite generar una copia SQL de la base de datos mediante proceso CLI o tarea programada.

### Problema que resuelve
Reduce el riesgo de perdida total de informacion al crear respaldos recuperables de la base de datos.

### Usuario que la utiliza
Operador tecnico, administrador de infraestructura o tarea programada del servidor.

### Datos de entrada
Variables de base de datos, ruta de `mysqldump`, ruta de almacenamiento y retencion configurada.

### Validaciones
Valida ejecucion CLI, prepara directorios de logs, locks y backups, evita ejecuciones concurrentes, exige base de datos configurada y verifica que el archivo resultante exista y no este vacio.

### Proceso
El operador o cron ejecuta `php scripts/cron_backups.php`; el sistema toma lock, carga configuracion, construye comando `mysqldump`, genera archivo `legalops_YYYYMMDD_HHMMSS.sql`, limpia backups antiguos segun retencion y registra resultado en `storage/logs/cron_backups.log`.

### Resultado esperado
Archivo SQL generado en `storage/backups/` y log de ejecucion con estado OK, nombre de archivo, bytes, eliminados por retencion y duracion.

### Informacion afectada
Crea archivos de backup y registros de log operativo. No modifica datos de negocio.

### Relacion con otros modulos
Base de datos, despliegue, restauracion, monitoreo y continuidad operativa.

### Reglas de negocio
Solo debe ejecutarse desde CLI. La retencion se controla con `BACKUP_RETENTION_DAYS`. El backup no sustituye el simulacro de restauracion.

### Permisos requeridos
Acceso operativo al servidor o tarea programada autorizada. No es funcion de UI ni ruta HTTP.

### Posibles errores
`mysqldump` no disponible, credenciales invalidas, base no configurada, permisos insuficientes en storage, backup vacio o ejecucion concurrente.

### Impacto
Aporta capacidad basica de continuidad y recuperacion ante incidentes.

### Observaciones
En el estado actual existen backups SQL generados y `cron_backups.log` registra ejecucion OK. Los backups observados son SQL plano; para produccion debe definirse cifrado, custodia, retencion aprobada y monitoreo de fallos.

## Nombre de la funcion: restaurarBackupBaseDatos

### Modulo
Operacion y restauracion.

### Proposito
Permite recuperar la base de datos desde un backup SQL generado por el sistema.

### Problema que resuelve
Permite validar que la informacion puede recuperarse despues de error humano, corrupcion de datos o incidente tecnico.

### Usuario que la utiliza
Operador tecnico o responsable de continuidad operativa.

### Datos de entrada
Archivo SQL de backup, base de datos destino, credenciales de restauracion y ambiente controlado.

### Validaciones
Debe usarse una base limpia o ambiente controlado, detener procesos concurrentes, restaurar el archivo correcto y ejecutar verificaciones posteriores.

### Proceso
El operador sigue `database/docs/restore-procedure.md`: genera o selecciona backup, crea base vacia, restaura el SQL con `mysql`, ejecuta `php scripts/migrate.php status`, valida comandos operativos, levanta aplicacion y confirma login y `GET /api/health`.

### Resultado esperado
Base restaurada, migraciones verificadas, health OK y usuarios/funciones criticas disponibles.

### Informacion afectada
Restaura datos completos de la base destino. En ambiente productivo real puede sobrescribir informacion, por lo que debe ejecutarse solo bajo procedimiento aprobado.

### Relacion con otros modulos
Backups, salud del sistema, autenticacion, documentos, finanzas, auditoria, portal y checklist owner.

### Reglas de negocio
La restauracion debe probarse en ambiente controlado antes de confiar en ella para produccion. Debe registrar backup usado, fecha, responsable, resultado y tiempos.

### Permisos requeridos
Acceso tecnico a base de datos y servidor. No es funcion de UI.

### Posibles errores
Backup corrupto, archivo incorrecto, credenciales invalidas, migraciones inconsistentes, perdida de datos de destino o health posterior fallido.

### Impacto
Es requisito para continuidad operativa y aprobacion productiva.

### Observaciones
El procedimiento existe, pero no se encontro evidencia de simulacro aprobado. Antes de produccion deben quedar aprobados RPO, RTO, frecuencia, retencion, responsable operativo y responsable de aprobacion.

## Nombre de la funcion: validarSalidaProductiva

### Modulo
Operacion, configuracion y gobierno productivo.

### Proposito
Consolidar las condiciones que deben cumplirse antes de considerar el software listo para produccion.

### Problema que resuelve
Evita confundir un ambiente de desarrollo funcional con un ambiente productivo seguro, recuperable y operable.

### Usuario que la utiliza
Propietario del producto, superadministrador, auditor, responsable tecnico y responsable operativo.

### Datos de entrada
Evidencias de configuracion, health, HTTPS, cookies, correo real, backup/restauracion, proteccion documental, monitoreo y checklist owner.

### Validaciones
Debe confirmar ambiente `production`, HTTPS valido, `SESSION_SECURE=true`, correo transaccional real, restauracion aprobada, proteccion documental definida, backups protegidos y responsables operativos.

### Proceso
El equipo revisa los bloqueantes productivos reales, adjunta evidencias, completa checklist owner y registra decision humana de aprobacion o rechazo.

### Resultado esperado
Dictamen de salida productiva con evidencia suficiente o lista clara de pendientes.

### Informacion afectada
Checklist owner, auditoria, documentacion operativa y decisiones de despliegue. No crea datos juridicos de negocio.

### Relacion con otros modulos
Health, autenticacion, correo, documentos, backups, monitoreo, auditoria y configuracion.

### Reglas de negocio
El sistema actual es apto para desarrollo/pruebas internas con observaciones. Para produccion no basta que los modulos funcionales respondan; deben cerrarse configuracion productiva, correo real, restauracion aprobada y proteccion documental.

### Permisos requeridos
Superadmin para checklist owner y responsables tecnicos para evidencias externas.

### Posibles errores
Evidencia incompleta, ambiente incorrecto, variables inseguras, ausencia de SMTP, ausencia de restore drill, archivos no inspeccionados o backups sin proteccion.

### Impacto
Define si el producto puede pasar de desarrollo/preproduccion a uso comercial productivo.

### Observaciones
Esta funcion es parcialmente operativa: existe checklist owner y documentacion de auditoria, pero no existe aun un validador automatico que bloquee arranque productivo inseguro. La validacion productiva requiere evidencia humana y tecnica hasta que se implemente un validador automatizado.
