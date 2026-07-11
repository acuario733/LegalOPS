-- @up
ALTER TABLE honorarios
    ADD COLUMN pdf_s3_key VARCHAR(500) NULL COMMENT 'S3 key del PDF generado de la factura' AFTER estado;

-- @down
ALTER TABLE honorarios DROP COLUMN pdf_s3_key;
