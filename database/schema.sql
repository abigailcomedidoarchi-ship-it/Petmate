CREATE DATABASE IF NOT EXISTS petmate;
USE petmate;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    contact VARCHAR(50),
    address TEXT,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'pet_owner', 'csr', 'vet_assistant', 'vet_technician', 'veterinarian') NOT NULL
);

CREATE TABLE IF NOT EXISTS pets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    owner_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    species VARCHAR(50) NOT NULL,
    breed VARCHAR(100),
    age INT,
    weight DECIMAL(5,2),
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS pet_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pet_id INT NOT NULL,
    visit_date DATE NOT NULL,
    notes TEXT,
    status ENUM('pending', 'validated', 'assessed', 'completed', 'rejected', 'pending_billing', 'awaiting_payment') DEFAULT 'pending',
    FOREIGN KEY (pet_id) REFERENCES pets(id) ON DELETE CASCADE
);

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
    FOREIGN KEY (pet_id) REFERENCES pets(id) ON DELETE CASCADE,
    FOREIGN KEY (technician_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS assessments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pet_id INT NOT NULL,
    assessment_session_id INT NULL,
    equipment_used ENUM('cbc','chemistry','microscopy','test_kit') NULL,
    test_type VARCHAR(100) NOT NULL,
    result TEXT,
    result_data JSON NULL,
    status ENUM('pending','completed') DEFAULT 'pending',
    technician_id INT,
    date DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (pet_id) REFERENCES pets(id) ON DELETE CASCADE,
    FOREIGN KEY (assessment_session_id) REFERENCES assessment_sessions(id) ON DELETE SET NULL,
    FOREIGN KEY (technician_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS treatment_plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pet_id INT NOT NULL,
    vet_id INT NOT NULL,
    assigned_assistant_id INT NULL,
    description TEXT NOT NULL,
    prescriptions TEXT,
    consent_status ENUM('not_submitted', 'pending', 'approved', 'declined') DEFAULT 'not_submitted',
    consent_note TEXT NULL,
    workflow_status VARCHAR(40) NOT NULL DEFAULT 'draft',
    treatment_started_at DATETIME NULL,
    treatment_completed_at DATETIME NULL,
    monitoring_started_at DATETIME NULL,
    discharge_approved_at DATETIME NULL,
    started_by INT NULL,
    date DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pet_id) REFERENCES pets(id) ON DELETE CASCADE,
    FOREIGN KEY (vet_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_assistant_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (started_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS treatment_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    plan_id INT NOT NULL,
    role VARCHAR(50) NOT NULL,
    type VARCHAR(50) NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (plan_id) REFERENCES treatment_plans(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS administration_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    plan_id INT NOT NULL,
    vet_assistant_id INT NOT NULL,
    medicine_name VARCHAR(255),
    dosage_given VARCHAR(100) NULL,
    administered_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    patient_response TEXT NULL,
    reaction VARCHAR(255) NULL,
    monitoring_required TINYINT(1) NOT NULL DEFAULT 0,
    procedure_completed TINYINT(1) NOT NULL DEFAULT 0,
    surgery_completed TINYINT(1) NOT NULL DEFAULT 0,
    FOREIGN KEY (plan_id) REFERENCES treatment_plans(id) ON DELETE CASCADE,
    FOREIGN KEY (vet_assistant_id) REFERENCES users(id) ON DELETE CASCADE
);

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
    FOREIGN KEY (plan_id) REFERENCES treatment_plans(id) ON DELETE CASCADE,
    FOREIGN KEY (vet_assistant_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_plan_created (plan_id, created_at)
);

CREATE TABLE IF NOT EXISTS discharge_summaries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    plan_id INT NOT NULL,
    vet_assistant_id INT NOT NULL,
    discharge_notes TEXT,
    home_care TEXT,
    follow_up_date DATE,
    warnings TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (plan_id) REFERENCES treatment_plans(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS bills (
    id INT AUTO_INCREMENT PRIMARY KEY,
    owner_id INT NOT NULL,
    visit_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status ENUM('unpaid', 'paid') DEFAULT 'unpaid',
    date DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (visit_id) REFERENCES pet_records(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_name VARCHAR(50) NOT NULL UNIQUE,
    status ENUM('available', 'occupied', 'cleaning', 'maintenance', 'reserved') NOT NULL DEFAULT 'available'
);

CREATE TABLE IF NOT EXISTS examination_rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pet_id INT NOT NULL,
    room_id INT NOT NULL,
    equipment_ready TINYINT(1) DEFAULT 0,
    supplies_ready TINYINT(1) DEFAULT 0,
    sanitation_done TINYINT(1) DEFAULT 0,
    notes TEXT,
    status ENUM('pending', 'ready', 'in_use', 'done') DEFAULT 'pending',
    prepared_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (pet_id) REFERENCES pets(id) ON DELETE CASCADE,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE RESTRICT,
    FOREIGN KEY (prepared_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS visit_summary (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pet_id INT NOT NULL,
    csr_id INT NOT NULL,
    summary TEXT NOT NULL,
    date DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pet_id) REFERENCES pets(id) ON DELETE CASCADE,
    FOREIGN KEY (csr_id) REFERENCES users(id) ON DELETE CASCADE
);

INSERT IGNORE INTO rooms (room_name) VALUES ('Room 1'), ('Room 2'), ('Room 3');
