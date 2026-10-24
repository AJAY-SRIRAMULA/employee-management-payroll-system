<?php

session_start();

require_once __DIR__ . '/../config/auth_check.php';
require_once __DIR__ . '/../config/db.php';

$error = '';

$employees = $conn->query(
    "SELECT id, full_name
     FROM employees
     WHERE status = 'ACTIVE'
     ORDER BY full_name ASC"
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $employee_id = (int) ($_POST['employee_id'] ?? 0);
    $leave_type = $_POST['leave_type'] ?? '';
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $reason = trim($_POST['reason'] ?? '');

    if (
        $employee_id <= 0 ||
        $leave_type === '' ||
        $start_date === '' ||
        $end_date === '' ||
        $reason === ''
    ) {
        $error = 'Please fill in all fields.';
    } elseif (!in_array($leave_type, ['Casual', 'Sick', 'Unpaid'], true)) {
        $error = 'Please select a valid leave type.';
    } elseif ($end_date < $start_date) {
        $error = 'End date cannot be before start date.';
    } else {

        $check = $conn->prepare(
            "SELECT id FROM employees
             WHERE id = ? AND status = 'ACTIVE'"
        );

        $check->bind_param("i", $employee_id);
        $check->execute();
        $check->store_result();

        if ($check->num_rows === 0) {
            $error = 'Please select a valid active employee.';
        } else {
            $sql = "INSERT INTO leave_requests
                    (employee_id, leave_type, start_date, end_date, reason)
                    VALUES (?, ?, ?, ?, ?)";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param(
                "issss",
                $employee_id,
                $leave_type,
                $start_date,
                $end_date,
                $reason
            );

            if ($stmt->execute()) {
                $stmt->close();
                $check->close();
                $conn->close();

                header('Location: index.php');
                exit;
            } else {
                $error = 'Failed to submit leave request.';
            }

            $stmt->close();
        }

        $check->close();
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Apply for Leave</title>
    <link rel="stylesheet" href="../assets/css/app.css">
    <link rel="stylesheet" href="../assets/css/leaves-create.css">
</head>

<body>

    <h1>Apply for Leave</h1>

    <?php if ($error !== ''): ?>
        <p class="error">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </p>
    <?php endif; ?>

    <form action="create.php" method="POST">

        <label for="employee_id">Employee</label>
        <select name="employee_id" id="employee_id" required>
            <option value="">Select Employee</option>

            <?php if ($employees): ?>
                <?php while ($employee = $employees->fetch_assoc()): ?>
                    <option
                        value="<?= (int) $employee['id'] ?>"
                        <?= (isset($_POST['employee_id']) &&
                            (int) $_POST['employee_id'] === (int) $employee['id'])
                            ? 'selected' : '' ?>
                    >
                        <?= htmlspecialchars($employee['full_name'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endwhile; ?>
            <?php endif; ?>
        </select>

        <label for="leave_type">Leave Type</label>
        <select name="leave_type" id="leave_type" required>
            <option value="">Select Leave Type</option>
            <option value="Casual" <?= ($_POST['leave_type'] ?? '') === 'Casual' ? 'selected' : '' ?>>Casual</option>
            <option value="Sick" <?= ($_POST['leave_type'] ?? '') === 'Sick' ? 'selected' : '' ?>>Sick</option>
            <option value="Unpaid" <?= ($_POST['leave_type'] ?? '') === 'Unpaid' ? 'selected' : '' ?>>Unpaid</option>
        </select>

        <label for="start_date">Start Date</label>
        <input
            type="date"
            name="start_date"
            id="start_date"
            value="<?= htmlspecialchars($_POST['start_date'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
            required
        >

        <label for="end_date">End Date</label>
        <input
            type="date"
            name="end_date"
            id="end_date"
            value="<?= htmlspecialchars($_POST['end_date'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
            required
        >

        <label for="reason">Reason</label>
        <textarea
            name="reason"
            id="reason"
            rows="4"
            required
        ><?= htmlspecialchars($_POST['reason'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>

        <button type="submit">Submit Leave Request</button>

        <a href="index.php">Cancel</a>

    </form>

</body>
</html>
