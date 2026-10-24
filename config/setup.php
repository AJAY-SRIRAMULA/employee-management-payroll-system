
<?php

$conn = new mysqli(
    "127.0.0.1",
    "root",
    "root123",
    "",
    3307
);

if ($conn->connect_error) {
    die("Invalid connection: " . $conn->connect_error);
}

if (!$conn->set_charset("utf8mb4")) {
    die("Cannot set character set: " . $conn->error);
}

// Create database
$sql = "CREATE DATABASE IF NOT EXISTS employee_payroll_system
        CHARACTER SET utf8mb4
        COLLATE utf8mb4_unicode_ci";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Cannot prepare database query: " . $conn->error);
}

if (!$stmt->execute()) {
    die("Cannot create database: " . $stmt->error);
}

$stmt->close();

echo "Database is ready. ";

// Select database
if (!$conn->select_db("employee_payroll_system")) {
    die("Cannot select database: " . $conn->error);
}

echo "Database selected successfully. ";

// Create departments table
$sql = "CREATE TABLE IF NOT EXISTS departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    department_name VARCHAR(250) NOT NULL UNIQUE,
    description TEXT,
    status ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
)";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Cannot prepare departments query: " . $conn->error);
}

if (!$stmt->execute()) {
    die("Cannot create departments table: " . $stmt->error);
}

$stmt->close();

echo "Departments table is ready. ";

// Create users table
$sql = "CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(254) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
)";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Cannot prepare users query: " . $conn->error);
}

if (!$stmt->execute()) {
    die("Cannot create users table: " . $stmt->error);
}

$stmt->close();

echo "Users table is ready. ";


$sql="CREATE TABLE IF NOT EXISTS employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_code VARCHAR(50) NOT NULL UNIQUE,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(254) NOT NULL UNIQUE,
    phone VARCHAR(20) NOT NULL UNIQUE,
    department_id INT NOT NULL,
    designation VARCHAR(255) NOT NULL,
    joining_date DATE NOT NULL,
    status ENUM('ACTIVE', 'INACTIVE') NOT NULL DEFAULT 'ACTIVE',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_employee_department
        FOREIGN KEY (department_id)
        REFERENCES departments(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Cannot prepare users query: " . $conn->error);
}

if (!$stmt->execute()) {
    die("Cannot create employees table: " . $stmt->error);
}

$stmt->close();

echo "employees table is ready. ";

$sql="CREATE TABLE IF NOT EXISTS salary_structures (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    basic_salary DECIMAL(10,2) NOT NULL,
    allowances DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    deductions DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    overtime_rate DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    effective_from DATE NOT NULL,
    effective_to DATE NULL DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_salary_employee
        FOREIGN KEY (employee_id)
        REFERENCES employees(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Cannot prepare salary query: " . $conn->error);
}

if (!$stmt->execute()) {
    die("Cannot create salary table: " . $stmt->error);
}

$stmt->close();

echo "salary table is ready. ";

$sql = "CREATE TABLE IF NOT EXISTS attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    attendance_date DATE NOT NULL,
    status ENUM('Present', 'Absent', 'Late', 'Half Day')
        NOT NULL DEFAULT 'Absent',
    check_in TIME NULL,
    check_out TIME NULL,
    overtime_hours DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    remarks TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT uq_employee_attendance_date
        UNIQUE (employee_id, attendance_date),

    CONSTRAINT fk_attendance_employee
        FOREIGN KEY (employee_id)
        REFERENCES employees(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Cannot prepare attendance query: " . $conn->error);
}

if (!$stmt->execute()) {
    die("Cannot create attendance table: " . $stmt->error);
}

$stmt->close();

echo "Attendance table is ready.";

$sql = "CREATE TABLE IF NOT EXISTS leave_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    leave_type ENUM('Casual', 'Sick', 'Unpaid') NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    reason TEXT NOT NULL,
    status ENUM('Pending', 'Approved', 'Rejected')
        NOT NULL DEFAULT 'Pending',
    approved_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_leave_employee
        FOREIGN KEY (employee_id)
        REFERENCES employees(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_leave_user
        FOREIGN KEY (approved_by)
        REFERENCES users(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Cannot prepare leave requests query: " . $conn->error);
}

if (!$stmt->execute()) {
    die("Cannot create leave requests table: " . $stmt->error);
}

$stmt->close();

echo "Leave requests table is ready.";


$sql = "CREATE TABLE IF NOT EXISTS payroll (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    salary_structure_id INT NULL,
    payroll_month DATE NOT NULL,

    basic_salary DECIMAL(10,2) NOT NULL,
    allowances DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    overtime_hours DECIMAL(7,2) NOT NULL DEFAULT 0.00,
    overtime_pay DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    unpaid_deduction DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    other_deductions DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    net_salary DECIMAL(10,2) NOT NULL,

    status ENUM('Draft', 'Finalized', 'Paid')
        NOT NULL DEFAULT 'Draft',
    finalized_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT uq_employee_payroll_month
        UNIQUE (employee_id, payroll_month),

    CONSTRAINT fk_payroll_employee
        FOREIGN KEY (employee_id)
        REFERENCES employees(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_payroll_salary_structure
        FOREIGN KEY (salary_structure_id)
        REFERENCES salary_structures(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Cannot prepare payroll query: " . $conn->error);
}

if (!$stmt->execute()) {
    die("Cannot create payroll table: " . $stmt->error);
}

$stmt->close();

echo "Payroll table is ready.";

$sql = "CREATE TABLE IF NOT EXISTS payroll_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    standard_working_days INT NOT NULL DEFAULT 26,
    default_overtime_rate DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    currency CHAR(3) NOT NULL DEFAULT 'INR',
    updated_at DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Cannot prepare payroll settings query: " . $conn->error);
}

if (!$stmt->execute()) {
    die("Cannot create payroll settings table: " . $stmt->error);
}

$stmt->close();

echo "Payroll settings table is ready.";

$sql=("CREATE TABLE remember_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    selector CHAR(24) NOT NULL UNIQUE,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_remember_tokens_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE,

    INDEX idx_remember_tokens_user_id (user_id),
    INDEX idx_remember_tokens_expires_at (expires_at)
)");
$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Cannot prepare remember_tokens query: " . $conn->error);
}
if (!$stmt->execute()) {
    die("Cannot create remember_tokens table: " . $stmt->error);
}
// Close only after all table-creation blocks.
$conn->close();



?>
