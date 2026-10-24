
<?php
session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth_check.php';

// Get leave request ID
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    header('Location: index.php');
    exit();
}

// Fetch existing leave request
$sql = "SELECT * FROM leave_requests WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $id);
$stmt->execute();

$result = $stmt->get_result();
$leave = $result->fetch_assoc();
$stmt->close();

if (!$leave) {
    header('Location: index.php');
    exit();
}

// Fetch active employees
$employees = $conn->query(
    "SELECT id, full_name
     FROM employees
     WHERE status = 'ACTIVE'
     ORDER BY full_name ASC"
);

$error = '';

// Update leave request
if (isset($_POST['update_leave'])) {
    $employee_id = (int) $_POST['employee_id'];
    $leave_type = $_POST['leave_type'] ?? '';
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $reason = trim($_POST['reason'] ?? '');

    // Simple validation
    if (
        $employee_id <= 0 ||
        $leave_type === '' ||
        $start_date === '' ||
        $end_date === '' ||
        $reason === ''
    ) {
        $error = 'Please fill in all fields.';
    } elseif (!in_array($leave_type, ['Casual', 'Sick', 'Unpaid'], true)) {
        $error = 'Invalid leave type.';
    } elseif ($end_date < $start_date) {
        $error = 'End date cannot be before start date.';
    } else {
        // Check whether employee is active
        $check = $conn->prepare(
            "SELECT id FROM employees
             WHERE id = ? AND status = 'ACTIVE'"
        );
        $check->bind_param('i', $employee_id);
        $check->execute();

        $employee_result = $check->get_result();
        $check->close();

        if ($employee_result->num_rows === 0) {
            $error = 'Please select an active employee.';
        } else {
            // Update leave request
            $sql = "UPDATE leave_requests
                    SET employee_id = ?,
                        leave_type = ?,
                        start_date = ?,
                        end_date = ?,
                        reason = ?
                    WHERE id = ?";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param(
                'issssi',
                $employee_id,
                $leave_type,
                $start_date,
                $end_date,
                $reason,
                $id
            );

            if ($stmt->execute()) {
                $stmt->close();
                header('Location: index.php');
                exit();
            } else {
                $error = 'Unable to update leave request.';
            }

            $stmt->close();
        }
    }
}

// Show submitted values after validation errors
$selected_employee = $_POST['employee_id'] ?? $leave['employee_id'];
$selected_type = $_POST['leave_type'] ?? $leave['leave_type'];
$selected_start = $_POST['start_date'] ?? $leave['start_date'];
$selected_end = $_POST['end_date'] ?? $leave['end_date'];
$selected_reason = $_POST['reason'] ?? $leave['reason'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Leave Request</title>
    <link rel="stylesheet" href="../assets/css/app.css">
    <link rel="stylesheet" href="../assets/css/leaves-edit.css">
</head>
<body>

    <h2>Edit Leave Request</h2>

    <?php if ($error !== ''): ?>
        <p class="error">
            <?= htmlspecialchars($error) ?>
        </p>
    <?php endif; ?>

    <form method="POST">

        <label for="employee_id">Employee</label>
        <select name="employee_id" id="employee_id" required>
            <option value="">Select Employee</option>

            <?php if ($employees): ?>
                <?php while ($employee = $employees->fetch_assoc()): ?>
                    <option
                        value="<?= (int) $employee['id'] ?>"
                        <?= (int) $selected_employee === (int) $employee['id']
                            ? 'selected'
                            : '' ?>
                    >
                        <?= htmlspecialchars($employee['full_name']) ?>
                    </option>
                <?php endwhile; ?>
            <?php endif; ?>
        </select>

        <br><br>

        <label for="leave_type">Leave Type</label>
        <select name="leave_type" id="leave_type" required>
            <?php foreach (['Casual', 'Sick', 'Unpaid'] as $option): ?>
                <option
                    value="<?= $option ?>"
                    <?= $selected_type === $option ? 'selected' : '' ?>
                >
                    <?= $option ?>
                </option>
            <?php endforeach; ?>
        </select>

        <br><br>

        <label for="start_date">Start Date</label>
        <input
            type="date"
            name="start_date"
            id="start_date"
            value="<?= htmlspecialchars($selected_start) ?>"
            required
        >

        <br><br>

        <label for="end_date">End Date</label>
        <input
            type="date"
            name="end_date"
            id="end_date"
            value="<?= htmlspecialchars($selected_end) ?>"
            required
        >

        <br><br>

        <label for="reason">Reason</label>
        <textarea
            name="reason"
            id="reason"
            required
        ><?= htmlspecialchars($selected_reason) ?></textarea>

        <br><br>

        <button type="submit" name="update_leave">Update Leave</button>
        <a href="index.php">Cancel</a>

    </form>

</body>
</html>
