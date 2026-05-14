USE petmate;

CREATE TABLE IF NOT EXISTS rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_name VARCHAR(50) NOT NULL UNIQUE,
    status ENUM('available', 'occupied', 'cleaning', 'maintenance', 'reserved') NOT NULL DEFAULT 'available'
);

INSERT IGNORE INTO rooms (room_name) VALUES ('Room 1'), ('Room 2'), ('Room 3');

CREATE TABLE IF NOT EXISTS examination_rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pet_id INT NOT NULL,
    room_id INT NOT NULL,
    equipment_ready TINYINT(1) DEFAULT 0,
    supplies_ready TINYINT(1) DEFAULT 0,
    sanitation_done TINYINT(1) DEFAULT 0,
    notes TEXT,
    status ENUM('pending', 'ready', 'in_use', 'done') DEFAULT 'pending',
    prepared_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (pet_id) REFERENCES pets(id),
    FOREIGN KEY (room_id) REFERENCES rooms(id),
    FOREIGN KEY (prepared_by) REFERENCES users(id)
);

-- Migration for existing deployments that already have examination_rooms
ALTER TABLE examination_rooms
    ADD COLUMN IF NOT EXISTS room_id INT NULL AFTER pet_id,
    ADD COLUMN IF NOT EXISTS equipment_ready TINYINT(1) DEFAULT 0,
    ADD COLUMN IF NOT EXISTS supplies_ready TINYINT(1) DEFAULT 0,
    ADD COLUMN IF NOT EXISTS sanitation_done TINYINT(1) DEFAULT 0,
    ADD COLUMN IF NOT EXISTS notes TEXT,
    ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
