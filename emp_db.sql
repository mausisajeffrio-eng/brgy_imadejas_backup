CREATE TABLE emp_db (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id VARCHAR(20) NOT NULL UNIQUE,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    position VARCHAR(100),
    age INT,
    phone VARCHAR(20),
    status ENUM('Active','Inactive') DEFAULT 'Active'
);