-- Vet assistant treatment execution & monitoring.
-- Run in phpMyAdmin / mysql CLI. If a line errors with "Duplicate column", skip that line and continue.
USE petmate;

ALTER TABLE treatment_plans ADD COLUMN assigned_assistant_id INT NULL;
ALTER TABLE treatment_plans ADD COLUMN treatment_started_at DATETIME NULL;
ALTER TABLE treatment_plans ADD COLUMN treatment_completed_at DATETIME NULL;
ALTER TABLE treatment_plans ADD COLUMN monitoring_started_at DATETIME NULL;
ALTER TABLE treatment_plans ADD COLUMN discharge_approved_at DATETIME NULL;
ALTER TABLE treatment_plans ADD COLUMN started_by INT NULL;

ALTER TABLE administration_logs ADD COLUMN procedure_completed TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE administration_logs ADD COLUMN surgery_completed TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE monitoring_logs ADD COLUMN wound_condition VARCHAR(200) NULL;
ALTER TABLE monitoring_logs ADD COLUMN bleeding VARCHAR(120) NULL;
ALTER TABLE monitoring_logs ADD COLUMN pain_indicators VARCHAR(200) NULL;
ALTER TABLE monitoring_logs ADD COLUMN medication_response TEXT NULL;
ALTER TABLE monitoring_logs ADD COLUMN recovery_observations TEXT NULL;
