SELECT 'platform_sms_settings' AS check_name, COUNT(*) AS table_count
FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='platform_sms_settings';
SELECT 'platform_sms_settings_innodb' AS check_name, COUNT(*) AS table_count
FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='platform_sms_settings' AND engine='InnoDB';
