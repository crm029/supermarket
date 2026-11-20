-- Pending registrations must not retain unverified profile or referral data.
-- The customer supplies these values again after proving ownership of the mobile number.
UPDATE users
SET full_name = 'کاربر جدید',
    referred_by_user_id = NULL
WHERE status = 'pending';
