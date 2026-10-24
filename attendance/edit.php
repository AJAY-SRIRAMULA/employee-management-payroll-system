<?php
require_once __DIR__ . '/../config/auth_check.php';
require_once __DIR__ . '/../config/db.php';

$error = '';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$id = (int) $_GET['id'];

// Fetch existing attendance record
$sql = "SELECT * FROM attendance WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: index.php');
    exit;
}

$attendance = $result->fetch_assoc();
$stmt->close();

// Fetch active employees
$employees_sql = "SELECT id, employee_code, full_name
                  FROM employees
                  WHERE status = 'ACTIVE'
                  ORDER BY full_name ASC";
$employees_result = $conn->query($employees_sql);

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
    } elseif (!in_array($status, ['Present', 'Absent', 'Late', 'Half Day'], true)) {
        $error = 'Please select a valid attendance status.';
    } else {
        // Check for duplicate attendance on the same date
        $check_sql = "SELECT id FROM attendance
                      WHERE employee_id = ?
                      AND attendance_date = ?
                      AND id != ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("isi", $employee_id, $attendance_date, $id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            $error = 'Attendance already exists for this employee on this date.';
        }

        $check_stmt->close();

        if ($error === '') {
            $check_in = $check_in !== '' ? $check_in : null;
            $check_out = $check_out !== '' ? $check_out : null;
            $remarks = $remarks !== '' ? $remarks : null;

            $update_sql = "UPDATE attendance
                           SET employee_id = ?,
                               attendance_date = ?,
                               status = ?,
                               check_in = ?,
                               check_out = ?,
                               overtime_hours = ?,
                               remarks = ?
                           WHERE id = ?";

            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param(
                "issssdsi",
                $employee_id,
                $attendance_date,
                $status,
                $check_in,
                $check_out,
                $overtime_hours,
                $remarks,
                $id
            );

            if ($update_stmt->execute()) {
                $update_stmt->close();
                header('Location: index.php?success=updated');
                exit;
            } else {
                $error = 'Unable to update attendance. Please try again.';
            }

            $update_stmt->close();
        }
    }
}

// Use submitted values when validation fails
$form_employee_id = $_POST['employee_id'] ?? $attendance['employee_id'];
$form_date = $_POST['attendance_date'] ?? $attendance['attendance_date'];
$form_status = $_POST['status'] ?? $attendance['status'];
$form_check_in = $_POST['check_in'] ?? $attendance['check_in'];
$form_check_out = $_POST['check_out'] ?? $attendance['check_out'];
$form_overtime = $_POST['overtime_hours'] ?? $attendance['overtime_hours'];
$form_remarks = $_POST['remarks'] ?? $attendance['remarks'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Attendance</title>
    <link rel="stylesheet" href="../assets/css/app.css">
    <link rel="stylesheet" href="../assets/css/attendance-edit.css">
</head>
<body>

<div class="container">
    <h1>Edit Attendance</h1>

    <?php if ($error !== ''): ?>
        <p class="error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label for="employee_id">Employee</label>
            <select id="employee_id" name="employee_id" required>
                <option value="">Select Employee</option>

                <?php while ($employee = $employees_result->fetch_assoc()): ?>
                    <option value="<?= (int)$employee['id'] ?>"
                        <?= $form_employee_id == $employee['id'] ? 'selected' : '' ?>>
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
                   value="<?= htmlspecialchars($form_date) ?>" required>
        </div>

        <div class="form-group">
            <label for="status">Status</label>
            <select id="status" name="status" required>
                <?php foreach (['Present', 'Absent', 'Late', 'Half Day'] as $option): ?>
                    <option value="<?= htmlspecialchars($option) ?>"
                        <?= $form_status === $option ? 'selected' : '' ?>>
                        <?= htmlspecialchars($option) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="check_in">Check-in Time</label>
            <input type="time" id="check_in" name="check_in"
                   value="<?= htmlspecialchars($form_check_in ?? '') ?>">
        </div>

        <div class="form-group">
            <label for="check_out">Check-out Time</label>
            <input type="time" id="check_out" name="check_out"
                   value="<?= htmlspecialchars($form_check_out ?? '') ?>">
        </div>

        <div class="form-group">
            <label for="overtime_hours">Overtime Hours</label>
            <input type="number" id="overtime_hours" name="overtime_hours"
                   min="0" max="999.99" step="0.01"
                   value="<?= htmlspecialchars($form_overtime) ?>" required>
        </div>

        <div class="form-group">
            <label for="remarks">Remarks</label>
            <textarea id="remarks" name="remarks" rows="3"><?= htmlspecialchars($form_remarks ?? '') ?></textarea>
        </div>

        <button type="submit">Update Attendance</button>
        <a href="index.php" class="cancel">Cancel</a>
    </form>
</div>

</body>
</html>