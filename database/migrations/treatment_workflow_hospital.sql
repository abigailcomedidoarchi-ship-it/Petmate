-- Hospital-style treatment workflow (run once). If a column already exists, skip that ALTER line.
USE petmate;

ALTER TABLE treatment_plans
  MODIFY COLUMN workflow_status VARCHAR(40) NOT NULL DEFAULT 'draft';

UPDATE treatment_plans SET workflow_status = 'draft'
  WHERE consent_status = 'not_submitted';

UPDATE treatment_plans SET workflow_status = 'draft'
  WHERE workflow_status = 'in_prep' AND consent_status = 'not_submitted';

UPDATE treatment_plans SET workflow_status = 'pending_consent'
  WHERE consent_status = 'pending';

UPDATE treatment_plans SET workflow_status = 'approved'
  WHERE consent_status = 'approved' AND workflow_status NOT IN (
    'forwarded','ongoing_treatment','monitoring','discharge_ready','pending_billing','awaiting_payment','completed'
  );

UPDATE treatment_plans SET workflow_status = 'draft' WHERE consent_status = 'declined';

UPDATE treatment_plans SET workflow_status = 'monitoring' WHERE workflow_status = 'administered';

UPDATE treatment_plans SET workflow_status = 'completed' WHERE workflow_status IN ('paid', 'discharged');

ALTER TABLE administration_logs
  ADD COLUMN dosage_given VARCHAR(100) NULL AFTER medicine_name,
  ADD COLUMN patient_response TEXT NULL AFTER notes,
  ADD COLUMN reaction VARCHAR(255) NULL AFTER patient_response,
  ADD COLUMN monitoring_required TINYINT(1) NOT NULL DEFAULT 0 AFTER reaction;

CREATE TABLE IF NOT EXISTS monitoring_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  plan_id INT NOT NULL,
  vet_assistant_id INT NOT NULL,
  observation TEXT,
  patient_status ENUM('stable', 'recovering', 'critical', 'under_observation') NOT NULL DEFAULT 'under_observation',
  temperature DECIMAL(4,1) NULL COMMENT 'Celsius',
  appetite VARCHAR(50) NULL,
  energy_level VARCHAR(50) NULL,
  complications TEXT NULL,
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (plan_id) REFERENCES treatment_plans(id) ON DELETE CASCADE,
  FOREIGN KEY (vet_assistant_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_plan_created (plan_id, created_at)
);
