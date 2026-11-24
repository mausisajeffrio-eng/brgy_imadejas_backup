CREATE TABLE attendance_db (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id VARCHAR(50) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    position VARCHAR(100),
    clock_in DATETIME,
    clock_out DATETIME,
    type ENUM('daily','event','disaster_response') NOT NULL,
    details TEXT NOT NULL DEFAULT 'Regular Check-in',
    status VARCHAR(20) GENERATED ALWAYS AS (
        CASE 
            WHEN clock_in IS NOT NULL AND clock_out IS NOT NULL THEN 'Complete'
            ELSE 'Incomplete'
        END
    ) STORED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
