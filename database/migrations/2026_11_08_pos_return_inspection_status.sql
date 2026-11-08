-- Keep the refund header's inspection state in sync with its item-level decisions.
ALTER TABLE pos_refunds
  MODIFY stock_status ENUM('pending_inspection','partially_inspected','inspected') NOT NULL DEFAULT 'pending_inspection';
