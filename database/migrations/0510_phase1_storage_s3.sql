-- @up
ALTER TABLE documento_versiones
    ADD COLUMN s3_key VARCHAR(500) NULL COMMENT 'Ruta en S3: {firmId}/docs/{docId}/{version}/{filename}' AFTER storage_path,
    ADD COLUMN s3_bucket VARCHAR(100) NULL COMMENT 'Bucket S3 donde reside el archivo' AFTER s3_key;

-- @down
ALTER TABLE documento_versiones
    DROP COLUMN s3_bucket,
    DROP COLUMN s3_key;
