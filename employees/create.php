<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employee_code = $_POST['employee_code'] ?? '';
    $full_name = $_POST['full_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $department_id = intval($_POST['department_id'] ?? 0);
    $designation = $_POST['designation'] ?? '';
    $joining_date = $_POST['joining_date'] ?? '';

    if (
        empty($employee_code) || empty($full_name) ||
        empty($email) || empty($phone) ||
        $department_id <= 0 || empty($designation) ||
        empty($joining_date)
    ) {
        die("All fields are required.");
    }

    $stmt = $conn->prepare(
        "INSERT INTO employees
        (employee_code, full_name, email, phone, department_id, designation, joining_date)
        VALUES (?, ?, ?, ?, ?, ?, ?)"
    );

    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param(
        "ssssiss",
        $employee_code,
        $full_name,
        $email,
        $phone,
        $department_id,
        $designation,
        $joining_date
    );

    if ($stmt->execute()) {
        header("Location: index.php?success=added");
        exit();
    } else {
        echo "Error adding employee: " . $stmt->error;
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Employee</title>
    <link rel="stylesheet" href="../assets/css/app.css">
    <link rel="stylesheet" href="../assets/css/employees-create.css">
</head>

<body>

<div class="container">

    <h1>Add New Employee</h1>

    <form method="POST">

        <div class="form-group">
            <label for="employee_code">Employee Code</label>
            <input type="text" id="employee_code"
                   name="employee_code" required>
        </div>

        <div class="form-group">
            <label for="full_name">Full Name</label>
            <input type="text" id="full_name"
                   name="full_name" required>
        </div>

        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email"
                   name="email" required>
        </div>

        <div class="form-group">
            <label for="phone">Phone</label>
            <input type="tel" id="phone"
                   name="phone" required>
        </div>

        <div class="form-group">
            <label for="department_id">Department</label>
            <select id="department_id" name="department_id" required>
                <option value="">Select Department</option>

                <?php
                $dept_sql = "SELECT * FROM departments";
                $dept_result = $conn->query($dept_sql);

                while ($dept_row = $dept_result->fetch_assoc()) {
                    echo "<option value='" . $dept_row['id'] . "'>"
                        . htmlspecialchars($dept_row['department_name'])
                        . "</option>";
                }
                ?>
            </select>
        </div>

        <div class="form-group">
            <label for="designation">Designation</label>
            <input type="text" id="designation"
                   name="designation" required>
        </div>

        <div class="form-group">
            <label for="joining_date">Joining Date</label>
            <input type="date" id="joining_date"
                   name="joining_date" required>
        </div>

        <button type="submit" class="submit-button">
            Add Employee
        </button>

    </form>

    <a href="index.php" class="back-link">
        Back to Employee List
    </a>

</div>

</body>
</html>
