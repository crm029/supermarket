-- POS schema and permission smoke checks.
SELECT 'pos_schema' AS test_name,
       (SELECT COUNT(*) FROM information_schema.tables
        WHERE table_schema=DATABASE() AND table_name IN ('pos_shifts','pos_sales','pos_sale_items')) AS table_count,
       (SELECT COUNT(*) FROM permissions
        WHERE permission_key IN ('pos.read','pos.shift.open','pos.shift.close','pos.sale.create')
          AND scope='tenant') AS permission_count;
