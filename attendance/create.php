<?php
require_once __DIR__ . '/../config/auth_check.php';
require_once __DIR__ . '/../config/db.php';

$error = '';

$employees_sql = "SELECT id, employee_code, full_name
                  FROM employees
                  WHERE status = 'ACTIVE'
                  ORDER BY full_name ASC";

$employees_result = $conn->query($employees_sql);

if (!$employees_result) {
    die("Unable to fetch employees: " . $conn->error);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employee_id = $_POST['employee_id'] ?? '';
    $attendance_date = $_POST['attendance_date'] ?? '';
    $status = $_POST['status'] ?? '';
    $check_in = $_POST['check_in'] ?? '';
    $check_out = $_POST['check_out'] ?? '';
    $overtime_hours = $_POST['overtime_hours'] ?? '0';
    $remarks = trim($_POST['remarks'] ?? '');

    if (empty($employee_id) || empty($attendance_date) || empty($status)) {
        $error = 'Please fill in all required fields.';
    } elseif (
        !ctype_digit((string)$employee_id) ||
        !in_array($status, ['Present', 'Absent', 'Late', 'Half Day'], true)
    ) {
        $error = 'Please select a valid employee and attendance status.';
    } elseif (
        !is_numeric($overtime_hours) ||
        (float)$overtime_hours < 0 ||
        (float)$overtime_hours > 999.99
    ) {
        $error = 'Please enter valid overtime hours.';
    } else {
        // Check that the selected employee is active.
        $check_sql = "SELECT id FROM employees
                      WHERE id = ? AND status = 'ACTIVE'";
        $check_stmt = $conn->prepare($check_sql);

        if (!$check_stmt) {
            $error = 'Something went wrong. Please try again.';
        } else {
            $check_stmt->bind_param("i", $employee_id);
            $check_stmt->execute();
            $employee_result = $check_stmt->get_result();

            if ($employee_result->num_rows === 0) {
                $error = 'Please select an active employee.';
            }

            $check_stmt->close();
        }

        // Check whether attendance already exists for this employee and date.
        if ($error === '') {
            $duplicate_sql = "SELECT id FROM attendance
                              WHERE employee_id = ? AND attendance_date = ?";
            $duplicate_stmt = $conn->prepare($duplicate_sql);

            if (!$duplicate_stmt) {
                $error = 'Something went wrong. Please try again.';
            } else {
                $duplicate_stmt->bind_param(
                    "is",
                    $employee_id,
                    $attendance_date
                );
                $duplicate_stmt->execute();
                $duplicate_result = $duplicate_stmt->get_result();

                if ($duplicate_result->num_rows > 0) {
                    $error = 'Attendance already exists for this employee on this date.';
                }

                $duplicate_stmt->close();
            }
        }

        // Insert attendance record.
        if ($error === '') {
            $check_in = $check_in !== '' ? $check_in : null;
            $check_out = $check_out !== '' ? $check_out : null;
            $remarks = $remarks !== '' ? $remarks : null;

            $insert_sql = "INSERT INTO attendance
                (employee_id, attendance_date, status, check_in,
                 check_out, overtime_hours, remarks)
                VALUES (?, ?, ?, ?, ?, ?, ?)";

            $stmt = $conn->prepare($insert_sql);

            if (!$stmt) {
                $error = 'Something went wrong. Please try again.';
            } else {
                $stmt->bind_param(
                    "issssds",
                    $employee_id,
                    $attendance_date,
                    $status,
                    $check_in,
                    $check_out,
                    $overtime_hours,
                    $remarks
                );

                if ($stmt->execute()) {
                    $stmt->close();
                    header('Location: index.php?success=created');
                    exit;
                } else {
                    error_log('Attendance insert failed: ' . $stmt->error);
                    $error = 'Unable to create attendance. Please try again.';
                }

                $stmt->close();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mark Attendance</title>
    <link rel="stylesheet" href="../assets/css/app.css">
    <link rel="stylesheet" href="../assets/css/attendance-create.css">
</head>
<body>

<div class="container">
    <h1>Mark Attendance</h1>

    <?php if ($error !== ''): ?>
        <p class="error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label for="employee_id">Employee</label>
            <select id="employee_id" name="employee_id" required>
                <option value="">Select Employee</option>

                <?php while ($employee = $employees_result->fetch_assoc()): ?>
                    <option
                        value="<?= (int)$employee['id'] ?>"
                        <?= ($_POST['employee_id'] ?? '') == $employee['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars(
                            $employee['employee_code'] . ' - ' .
                            $employee['full_name']
                        ) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="attendance_date">Attendance Date</label>
            <input type="date" id="attendance_date" name="attendance_date"
                   value="<?= htmlspecialchars($_POST['attendance_date'] ?? date('Y-m-d')) ?>"
                   required>
        </div>

        <div class="form-group">
            <label for="status">Status</label>
            <select id="status" name="status" required>
                <option value="">Select Status</option>
                <?php foreach (['Present', 'Absent', 'Late', 'Half Day'] as $option): ?>
                    <option value="<?= htmlspecialchars($option) ?>"
                        <?= ($_POST['status'] ?? '') === $option ? 'selected' : '' ?>>
                        <?= htmlspecialchars($option) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="check_in">Check-in Time</label>
            <input type="time" id="check_in" name="check_in"
                   value="<?= htmlspecialchars($_POST['check_in'] ?? '') ?>">
        </div>

        <div class="form-group">
            <label for="check_out">Check-out Time</label>
            <input type="time" id="check_out" name="check_out"
                   value="<?= htmlspecialchars($_POST['check_out'] ?? '') ?>">
        </div>

        <div class="form-group">
            <label for="overtime_hours">Overtime Hours</label>
            <input type="number" id="overtime_hours" name="overtime_hours"
                   min="0" max="999.99" step="0.01"
                   value="<?= htmlspecialchars($_POST['overtime_hours'] ?? '0.00') ?>"
                   required>
        </div>

        <div class="form-group">
            <label for="remarks">Remarks</label>
            <textarea id="remarks" name="remarks" rows="3"><?= htmlspecialchars($_POST['remarks'] ?? '') ?></textarea>
        </div>

        <button type="submit">Save Attendance</button>
        <a href="index.php" class="cancel">Cancel</a>
    </form>
</div>

</body>
</html>
