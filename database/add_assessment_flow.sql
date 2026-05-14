USE petmate;

CREATE TABLE IF NOT EXISTS assessment_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pet_id INT NOT NULL,
    room_id INT NOT NULL,
    technician_id INT NOT NULL,
    temperature DECIMAL(4,1) COMMENT 'Celsius',
    heart_rate INT COMMENT 'bpm',
    respiratory_rate INT COMMENT 'breaths per minute',
    weight_on_arrival DECIMAL(5,2) COMMENT 'kg',
    overall_notes TEXT,
    status ENUM('open','submitted') DEFAULT 'open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pet_id) REFERENCES pets(id),
    FOREIGN KEY (technician_id) REFERENCES users(id)
);

ALTER TABLE assessments
    ADD COLUMN IF NOT EXISTS assessment_session_id INT NULL AFTER pet_id,
    ADD COLUMN IF NOT EXISTS equipment_used ENUM('cbc','chemistry','microscopy','test_kit') NULL AFTER assessment_session_id,
    ADD COLUMN IF NOT EXISTS result_data JSON NULL AFTER result,
    ADD COLUMN IF NOT EXISTS status ENUM('pending','completed') DEFAULT 'pending' AFTER result_data,
    ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER date;

SET @fk_exists := (
    SELECT COUNT(*)
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'assessments'
      AND CONSTRAINT_NAME = 'fk_assessments_session'
);
SET @fk_sql := IF(
    @fk_exists = 0,
    'ALTER TABLE assessments ADD CONSTRAINT fk_assessments_session FOREIGN KEY (assessment_session_id) REFERENCES assessment_sessions(id) ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE stmt FROM @fk_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE pet_records
    MODIFY COLUMN status ENUM('pending', 'validated', 'assessed', 'completed') DEFAULT 'pending';
