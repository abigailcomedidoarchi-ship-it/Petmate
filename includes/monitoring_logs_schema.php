<?php

/**
 * Create monitoring_logs if missing (older DBs / partial migrations).
 */
function petmate_ensure_monitoring_logs_table(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    try {
        $chk = $pdo->query("SHOW TABLES LIKE 'monitoring_logs'");
        if ($chk && $chk->fetch()) {
            $done = true;

            return;
        }
        $pdo->exec("
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
                wound_condition VARCHAR(200) NULL,
                bleeding VARCHAR(120) NULL,
                pain_indicators VARCHAR(200) NULL,
                medication_response TEXT NULL,
                recovery_observations TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_plan_created (plan_id, created_at),
                FOREIGN KEY (plan_id) REFERENCES treatment_plans(id) ON DELETE CASCADE,
                FOREIGN KEY (vet_assistant_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $done = true;
    } catch (Throwable $e) {
        // Retry on next request if e.g. lock
    }
}
