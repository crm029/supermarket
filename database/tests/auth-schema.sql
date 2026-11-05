-- Authentication schema integration checks.
-- Run after tenant-isolation.sql and the auth sessions/OTP migration.

SELECT id INTO @auth_user_one
FROM users WHERE tenant_id = 1 AND mobile = '09120000001' LIMIT 1;

INSERT INTO auth_challenges
  (tenant_id,mobile,purpose,otp_hash,expires_at)
VALUES
  (1,'09120000001','login',REPEAT('a',64),DATE_ADD(UTC_TIMESTAMP(), INTERVAL 5 MINUTE));

INSERT INTO auth_rate_limits
  (tenant_id,action,bucket_hash,window_started_at,attempts)
VALUES
  (1,'otp_request_test',REPEAT('b',64),UTC_TIMESTAMP(),1);

INSERT INTO auth_sessions
  (tenant_id,user_id,token_hash,csrf_hash,expires_at)
VALUES
  (1,@auth_user_one,REPEAT('c',64),REPEAT('d',64),DATE_ADD(UTC_TIMESTAMP(), INTERVAL 1 DAY));

DELIMITER //
CREATE PROCEDURE assert_cross_tenant_session_rejected()
BEGIN
  DECLARE rejected BOOLEAN DEFAULT FALSE;
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLSTATE '23000' SET rejected = TRUE;
    INSERT INTO auth_sessions
      (tenant_id,user_id,token_hash,csrf_hash,expires_at)
    SELECT 1, id, REPEAT('e',64), REPEAT('f',64), DATE_ADD(UTC_TIMESTAMP(), INTERVAL 1 DAY)
    FROM users WHERE tenant_id = 2 AND mobile = '09120000002' LIMIT 1;
  END;
  IF rejected = FALSE THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cross-tenant authentication session was accepted';
  END IF;
END//
DELIMITER ;

CALL assert_cross_tenant_session_rejected();
DROP PROCEDURE assert_cross_tenant_session_rejected;

SELECT 'authentication schema integration checks passed' AS result;
