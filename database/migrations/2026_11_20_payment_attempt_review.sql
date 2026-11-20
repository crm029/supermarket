-- Preserve ambiguous gateway requests for manual reconciliation instead of retrying.
ALTER TABLE payment_attempts
  MODIFY COLUMN status ENUM('created','redirected','verified','failed','refunded','review')
  NOT NULL DEFAULT 'created';
