<?php

/**
 * Old installs used workflow_status ENUM without ongoing_treatment / monitoring / etc.
 * MySQL then stores '' when the app sets an invalid value — administration breaks.
 * Convert to VARCHAR(40) once (matches database/migrations/treatment_workflow_hospital.sql).
 */
function petmate_ensure_treatment_plan_workflow_varchar(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $row = $pdo->query("SHOW COLUMNS FROM treatment_plans WHERE Field = 'workflow_status'")->fetch(PDO::FETCH_ASSOC);
        if (!$row || empty($row['Type'])) {
            return;
        }
        $type = strtolower((string) $row['Type']);
        if (strpos($type, 'enum') !== 0) {
            return;
        }
        $pdo->exec("ALTER TABLE treatment_plans MODIFY COLUMN workflow_status VARCHAR(40) NOT NULL DEFAULT 'draft'");
    } catch (Throwable $e) {
        // Insufficient DB privileges or lock — run migrations manually
    }
}
