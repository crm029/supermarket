-- Platform payment settings migration checks.
-- Run after the platform payment settings migration.

DELIMITER //
CREATE PROCEDURE assert_platform_payment_settings()
BEGIN
  DECLARE setting_count INT DEFAULT 0;
  DECLARE permission_count INT DEFAULT 0;
  DECLARE snapshot_columns INT DEFAULT 0;

  SELECT COUNT(*) INTO setting_count
  FROM platform_payment_settings
  WHERE id = 1 AND mode = 'sandbox' AND merchant_id_encrypted IS NULL;
  IF setting_count <> 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Gateway settings must initialize in safe sandbox mode';
  END IF;

  SELECT COUNT(*) INTO permission_count
  FROM permissions
  WHERE permission_key = 'platform.payments.manage' AND scope = 'platform';
  IF permission_count <> 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Owner-only platform payment permission is missing';
  END IF;

  SELECT COUNT(*) INTO snapshot_columns
  FROM information_schema.columns
  WHERE table_schema = DATABASE() AND table_name = 'payment_attempts'
    AND column_name IN ('gateway_mode','merchant_id_encrypted');
  IF snapshot_columns <> 2 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Payment attempts must snapshot gateway mode and encrypted merchant ID';
  END IF;
END//
DELIMITER ;

CALL assert_platform_payment_settings();
DROP PROCEDURE assert_platform_payment_settings;

SELECT 'platform payment settings checks passed' AS result;
