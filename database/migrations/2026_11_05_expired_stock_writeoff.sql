-- Record expired-stock disposal as a first-class, auditable inventory movement.
ALTER TABLE stock_movements
  MODIFY movement_type ENUM('receipt','transfer_in','transfer_out','adjustment','reservation','release','sale','return','supplier_return','expiry_writeoff') NOT NULL;
