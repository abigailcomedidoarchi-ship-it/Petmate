USE petmate;
ALTER TABLE pet_records MODIFY COLUMN status ENUM('pending', 'validated', 'completed', 'rejected') DEFAULT 'pending';
ALTER TABLE pet_records ADD COLUMN remarks TEXT AFTER status;
