SELECT CONCAT(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='products'
     AND COLUMN_NAME IN ('seo_title','seo_description')) AS product_seo_columns, ':',
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='categories'
     AND COLUMN_NAME IN ('description','seo_title','seo_description','updated_at')) AS category_seo_columns);
