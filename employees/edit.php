
<?php
session_start();
if (!isset($_SESSION['username'])) {
    header('Location: ../auth/login.php');
    exit();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth_check.php';

    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    header('Location: index.php');
    exit();
}

$error = '';

$sql = "SELECT * FROM employees WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();

$result = $stmt->get_result();
$employee = $result->fetch_assoc();
$stmt->close();

if (!$employee) {
    header('Location: index.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $employee_code = trim($_POST['employee_code'] ?? '');
    $fullname = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $department_id = intval($_POST['department_id'] ?? 0);
    $designation = trim($_POST['designation'] ?? '');
    $joining_date = $_POST['joining_date'] ?? '';
    $status = $_POST['status'] ?? '';

    // Basic validation
    if (
        $employee_code === '' ||
        $fullname === '' ||
        $email === '' ||
        $phone === '' ||
        $department_id <= 0 ||
        $designation === '' ||
        $joining_date === ''
    ) {
        $error = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($fullname) > 255) {
        $error = "Full name must not exceed 255 characters.";
    } elseif (!preg_match('/^[0-9]{10}$/', $phone)) {
        $error = "Phone number must contain exactly 10 digits.";
    } elseif (!in_array($status, ['ACTIVE', 'INACTIVE'], true)) {
        $error = "Please select a valid employee status.";
    } else {

        // Check whether another employee uses this code, email, or phone
        $sql = "SELECT id FROM employees
                WHERE (employee_code = ?
                OR email = ?
                OR phone = ?)
                AND id != ?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "sssi",
            $employee_code,
            $email,
            $phone,
            $id
        );
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $error = "Employee code, email, or phone already exists.";
        }

        $stmt->close();

        if ($error === '') {

            $sql = "UPDATE employees
                    SET employee_code = ?,
                        full_name = ?,
                        email = ?,
                        phone = ?,
                        department_id = ?,
                        designation = ?,
                        joining_date = ?,
                        status = ?
                    WHERE id = ?";

            $stmt = $conn->prepare($sql);

            $stmt->bind_param(
                "ssssisssi",
                $employee_code,
                $fullname,
                $email,
                $phone,
                $department_id,
                $designation,
                $joining_date,
                $status,
                $id
            );

            if ($stmt->execute()) {
                $stmt->close();
                $conn->close();

                header('Location: index.php?success=updated');
                exit();
            } else {
                $error = "Unable to update employee. Please try again.";
                $stmt->close();
            }
        }
    }

    // Preserve submitted values if validation or update fails
    $employee['employee_code'] = $employee_code;
    $employee['full_name'] = $fullname;
    $employee['email'] = $email;
    $employee['phone'] = $phone;
    $employee['department_id'] = $department_id;
    $employee['designation'] = $designation;
    $employee['joining_date'] = $joining_date;
    $employee['status'] = $status;
}

// Fetch departments for dropdown
$departments = $conn->query("SELECT * FROM departments");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Employee</title>
    <link rel="stylesheet" href="../assets/css/app.css">
    <link rel="stylesheet" href="../assets/css/employees-edit.css">
</head>

<body>

<div class="container">

    <h1>Edit Employee</h1>

    <p class="subtitle">
        Update the employee's information and status.
    </p>

    <?php if ($error !== ''): ?>
        <div class="alert-error" role="alert">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <form action="edit.php?id=<?= $id ?>" method="POST">

        <div class="form-group">
            <label for="employee_code">Employee Code</label>
            <input
                type="text"
                id="employee_code"
                name="employee_code"
                value="<?= htmlspecialchars($employee['employee_code'], ENT_QUOTES, 'UTF-8') ?>"
                required
            >
        </div>

        <div class="form-group">
            <label for="full_name">Full Name</label>
            <input
                type="text"
                id="full_name"
                name="full_name"
                value="<?= htmlspecialchars($employee['full_name'], ENT_QUOTES, 'UTF-8') ?>"
                required
            >
        </div>

        <div class="form-group">
            <label for="email">Email</label>
            <input
                type="email"
                id="email"
                name="email"
                value="<?= htmlspecialchars($employee['email'], ENT_QUOTES, 'UTF-8') ?>"
                required
            >
        </div>

        <div class="form-group">
            <label for="phone">Phone</label>
            <input
                type="tel"
                id="phone"
                name="phone"
                maxlength="10"
                value="<?= htmlspecialchars($employee['phone'], ENT_QUOTES, 'UTF-8') ?>"
                required
            >
        </div>

        <div class="form-group">
            <label for="department_id">Department</label>
            <select id="department_id" name="department_id" required>
                <option value="">Select Department</option>

                <?php while ($dept = $departments->fetch_assoc()): ?>
                    <option
                        value="<?= (int) $dept['id'] ?>"
                        <?= (int) $employee['department_id'] === (int) $dept['id'] ? 'selected' : '' ?>
                    >
                        <?= htmlspecialchars(
                            $dept['department_name'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="designation">Designation</label>
            <input
                type="text"
                id="designation"
                name="designation"
                value="<?= htmlspecialchars($employee['designation'], ENT_QUOTES, 'UTF-8') ?>"
                required
            >
        </div>

        <div class="form-group">
            <label for="joining_date">Joining Date</label>
            <input
                type="date"
                id="joining_date"
                name="joining_date"
                value="<?= htmlspecialchars($employee['joining_date'], ENT_QUOTES, 'UTF-8') ?>"
                required
            >
        </div>

        <div class="form-group">
            <label for="status">Employee Status</label>
            <select id="status" name="status" required>
                <option value="ACTIVE"
                    <?= $employee['status'] === 'ACTIVE' ? 'selected' : '' ?>>
                    Active
                </option>

                <option value="INACTIVE"
                    <?= $employee['status'] === 'INACTIVE' ? 'selected' : '' ?>>
                    Inactive
                </option>
            </select>
        </div>

        <div class="buttons">
            <button type="submit" class="btn btn-update">
                Update Employee
            </button>

            <a href="index.php" class="btn btn-cancel">
                Cancel
            </a>
        </div>

    </form>

</div>

</body>
</html>
