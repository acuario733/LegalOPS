-- @up
INSERT INTO catalogos (firma_id, alcance, codigo, scope_key, nombre, estado, created_at, updated_at) VALUES
(NULL, 'global', 'tipo_documento', 'global:tipo_documento', 'Tipos de documento', 'activo', CURRENT_TIMESTAMP(6), CURRENT_TIMESTAMP(6)),
(NULL, 'global', 'moneda', 'global:moneda', 'Monedas', 'activo', CURRENT_TIMESTAMP(6), CURRENT_TIMESTAMP(6)),
(NULL, 'global', 'metodo_pago', 'global:metodo_pago', 'Metodos de pago', 'activo', CURRENT_TIMESTAMP(6), CURRENT_TIMESTAMP(6)),
(NULL, 'global', 'medio', 'global:medio', 'Medios de autorizacion', 'activo', CURRENT_TIMESTAMP(6), CURRENT_TIMESTAMP(6)),
(NULL, 'global', 'origen_fuente', 'global:origen_fuente', 'Origenes y fuentes comerciales', 'activo', CURRENT_TIMESTAMP(6), CURRENT_TIMESTAMP(6)),
(NULL, 'global', 'concepto_honorario', 'global:concepto_honorario', 'Conceptos de honorarios', 'activo', CURRENT_TIMESTAMP(6), CURRENT_TIMESTAMP(6)),
(NULL, 'global', 'tipo_caso', 'global:tipo_caso', 'Tipos de caso', 'activo', CURRENT_TIMESTAMP(6), CURRENT_TIMESTAMP(6)),
(NULL, 'global', 'jurisdiccion', 'global:jurisdiccion', 'Jurisdicciones', 'activo', CURRENT_TIMESTAMP(6), CURRENT_TIMESTAMP(6)),
(NULL, 'global', 'despacho', 'global:despacho', 'Despachos judiciales', 'activo', CURRENT_TIMESTAMP(6), CURRENT_TIMESTAMP(6))
ON DUPLICATE KEY UPDATE nombre=VALUES(nombre), estado='activo', updated_at=CURRENT_TIMESTAMP(6);

INSERT INTO catalogo_items (firma_id, catalogo_id, codigo, etiqueta, orden, estado, created_at, updated_at)
SELECT NULL, c.id, v.codigo, v.etiqueta, v.orden, 'activo', CURRENT_TIMESTAMP(6), CURRENT_TIMESTAMP(6)
FROM catalogos c
INNER JOIN (
    SELECT 'tipo_documento' catalogo, 'CC' codigo, 'Cedula de ciudadania' etiqueta, 10 orden
    UNION ALL SELECT 'tipo_documento', 'TI', 'Tarjeta de identidad', 20
    UNION ALL SELECT 'tipo_documento', 'CE', 'Cedula de extranjeria', 30
    UNION ALL SELECT 'tipo_documento', 'NIT', 'Numero de identificacion tributaria', 40
    UNION ALL SELECT 'tipo_documento', 'PAS', 'Pasaporte', 50
    UNION ALL SELECT 'moneda', 'COP', 'Peso colombiano', 10
    UNION ALL SELECT 'moneda', 'USD', 'Dolar estadounidense', 20
    UNION ALL SELECT 'metodo_pago', 'efectivo', 'Efectivo', 10
    UNION ALL SELECT 'metodo_pago', 'transferencia', 'Transferencia bancaria', 20
    UNION ALL SELECT 'metodo_pago', 'tarjeta_debito', 'Tarjeta debito', 30
    UNION ALL SELECT 'metodo_pago', 'tarjeta_credito', 'Tarjeta credito', 40
    UNION ALL SELECT 'metodo_pago', 'pse', 'PSE', 50
    UNION ALL SELECT 'medio', 'registro_interno', 'Registro interno', 10
    UNION ALL SELECT 'medio', 'correo', 'Correo electronico', 20
    UNION ALL SELECT 'medio', 'formulario_web', 'Formulario web', 30
    UNION ALL SELECT 'medio', 'whatsapp', 'WhatsApp', 40
    UNION ALL SELECT 'medio', 'contrato', 'Contrato', 50
    UNION ALL SELECT 'origen_fuente', 'prospecto', 'Prospecto', 10
    UNION ALL SELECT 'origen_fuente', 'referido', 'Referido', 20
    UNION ALL SELECT 'origen_fuente', 'web', 'Sitio web', 30
    UNION ALL SELECT 'origen_fuente', 'redes_sociales', 'Redes sociales', 40
    UNION ALL SELECT 'origen_fuente', 'campana', 'Campana comercial', 50
    UNION ALL SELECT 'origen_fuente', 'cliente_existente', 'Cliente existente', 60
    UNION ALL SELECT 'concepto_honorario', 'consulta', 'Consulta juridica', 10
    UNION ALL SELECT 'concepto_honorario', 'anticipo', 'Anticipo de honorarios', 20
    UNION ALL SELECT 'concepto_honorario', 'cuota', 'Cuota pactada', 30
    UNION ALL SELECT 'concepto_honorario', 'exito', 'Honorarios de exito', 40
    UNION ALL SELECT 'concepto_honorario', 'representacion', 'Representacion judicial', 50
    UNION ALL SELECT 'tipo_caso', 'civil', 'Civil', 10
    UNION ALL SELECT 'tipo_caso', 'laboral', 'Laboral', 20
    UNION ALL SELECT 'tipo_caso', 'familia', 'Familia', 30
    UNION ALL SELECT 'tipo_caso', 'penal', 'Penal', 40
    UNION ALL SELECT 'tipo_caso', 'administrativo', 'Administrativo', 50
    UNION ALL SELECT 'tipo_caso', 'comercial', 'Comercial', 60
    UNION ALL SELECT 'jurisdiccion', 'ordinaria', 'Ordinaria', 10
    UNION ALL SELECT 'jurisdiccion', 'contencioso_administrativa', 'Contencioso administrativa', 20
    UNION ALL SELECT 'jurisdiccion', 'constitucional', 'Constitucional', 30
    UNION ALL SELECT 'jurisdiccion', 'arbitral', 'Arbitral', 40
    UNION ALL SELECT 'despacho', 'por_definir', 'Por definir', 10
) v ON v.catalogo=c.codigo
WHERE c.firma_id IS NULL
ON DUPLICATE KEY UPDATE etiqueta=VALUES(etiqueta), orden=VALUES(orden), estado='activo', updated_at=CURRENT_TIMESTAMP(6);

-- @down
DELETE ci FROM catalogo_items ci
INNER JOIN catalogos c ON c.id=ci.catalogo_id
WHERE c.firma_id IS NULL
  AND c.codigo IN ('tipo_documento','moneda','metodo_pago','medio','origen_fuente','concepto_honorario','tipo_caso','jurisdiccion','despacho');

DELETE FROM catalogos
WHERE firma_id IS NULL
  AND codigo IN ('tipo_documento','moneda','metodo_pago','medio','origen_fuente','concepto_honorario','tipo_caso','jurisdiccion','despacho');
