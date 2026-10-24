
<?php
session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth_check.php';

$error = '';
$payroll_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$payroll_id || $payroll_id < 1) {
    header('Location: index.php?error=invalid_id');
    exit;
}

// Fetch payroll record
$sql = "SELECT payroll.*, employees.full_name,
               salary_structures.overtime_rate
        FROM payroll
        JOIN employees ON payroll.employee_id = employees.id
        LEFT JOIN salary_structures
            ON payroll.salary_structure_id = salary_structures.id
        WHERE payroll.id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $payroll_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: index.php?error=not_found');
    exit;
}

$payroll = $result->fetch_assoc();
$stmt->close();

// Only Draft payroll can be edited
if ($payroll['status'] !== 'Draft') {
    header('Location: index.php?error=not_editable');
    exit;
}

$overtime_hours = $payroll['overtime_hours'];
$unpaid_deduction = $payroll['unpaid_deduction'];
$other_deductions = $payroll['other_deductions'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $overtime_hours = $_POST['overtime_hours'] ?? '';
    $unpaid_deduction = $_POST['unpaid_deduction'] ?? '';
    $other_deductions = $_POST['other_deductions'] ?? '';

    if (
        !is_numeric($overtime_hours) ||
        !is_numeric($unpaid_deduction) ||
        !is_numeric($other_deductions) ||
        (float)$overtime_hours < 0 ||
        (float)$unpaid_deduction < 0 ||
        (float)$other_deductions < 0
    ) {
        $error = 'Please enter valid values.';
    } else {

        $overtime_hours = (float)$overtime_hours;
        $unpaid_deduction = (float)$unpaid_deduction;
        $other_deductions = (float)$other_deductions;

        // Calculate overtime pay
        $overtime_rate = (float)($payroll['overtime_rate'] ?? 0);

        $overtime_pay = $overtime_hours * $overtime_rate;

        // Calculate net salary
        $net_salary = (float)$payroll['basic_salary']
                    + (float)$payroll['allowances']
                    + $overtime_pay
                    - $unpaid_deduction
                    - $other_deductions;

        if ($net_salary < 0) {

            $error = 'Total deductions cannot exceed gross salary.';

        } else {

            // Update only if the record is still Draft
            $updateSql = "UPDATE payroll
                          SET overtime_hours = ?,
                              overtime_pay = ?,
                              unpaid_deduction = ?,
                              other_deductions = ?,
                              net_salary = ?
                          WHERE id = ?
                          AND status = 'Draft'";

            $updateStmt = $conn->prepare($updateSql);

            $updateStmt->bind_param(
                "dddddi",
                $overtime_hours,
                $overtime_pay,
                $unpaid_deduction,
                $other_deductions,
                $net_salary,
                $payroll_id
            );

            if ($updateStmt->execute() && $updateStmt->affected_rows >= 0) {

                // Check whether the record is still Draft
                if ($updateStmt->affected_rows > 0) {
                    header('Location: index.php?success=updated');
                    exit;
                }

                // If no rows changed, verify the current status
                $checkSql = "SELECT status FROM payroll WHERE id = ?";
                $checkStmt = $conn->prepare($checkSql);
                $checkStmt->bind_param("i", $payroll_id);
                $checkStmt->execute();

                $current = $checkStmt->get_result()->fetch_assoc();
                $checkStmt->close();

                if ($current && $current['status'] === 'Draft') {
                    header('Location: index.php?success=updated');
                    exit;
                }

                $error = 'This payroll record is no longer editable.';
            } else {
                $error = 'Failed to update payroll.';
            }

            $updateStmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Payroll</title>
    <link rel="stylesheet" href="../assets/css/app.css">
    <link rel="stylesheet" href="../assets/css/payroll-edit.css">
</head>

<body>

<div class="container">

    <h1>Edit Payroll</h1>

    <div class="employee">
        <strong>Employee:</strong>
        <?= htmlspecialchars($payroll['full_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>

        <br><br>

        <strong>Payroll Month:</strong>
        <?= htmlspecialchars($payroll['payroll_month'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>

        <br><br>

        <strong>Basic Salary:</strong>
        <?= number_format((float)$payroll['basic_salary'], 2) ?>

        <br><br>

        <strong>Allowances:</strong>
        <?= number_format((float)$payroll['allowances'], 2) ?>
    </div>

    <?php if ($error !== ''): ?>
        <div class="error">
            <?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <form method="POST">

        <label for="overtime_hours">Overtime Hours</label>
        <input
            type="number"
            id="overtime_hours"
            name="overtime_hours"
            min="0"
            step="0.01"
            value="<?= htmlspecialchars((string)$overtime_hours, ENT_QUOTES, 'UTF-8') ?>"
            required
        >

        <label for="unpaid_deduction">Unpaid Leave Deduction</label>
        <input
            type="number"
            id="unpaid_deduction"
            name="unpaid_deduction"
            min="0"
            step="0.01"
            value="<?= htmlspecialchars((string)$unpaid_deduction, ENT_QUOTES, 'UTF-8') ?>"
            required
        >

        <label for="other_deductions">Other Deductions</label>
        <input
            type="number"
            id="other_deductions"
            name="other_deductions"
            min="0"
            step="0.01"
            value="<?= htmlspecialchars((string)$other_deductions, ENT_QUOTES, 'UTF-8') ?>"
            required
        >

        <button type="submit">Update Payroll</button>

        <a href="index.php" class="cancel">Cancel</a>

    </form>

</div>

</body>
</html>
