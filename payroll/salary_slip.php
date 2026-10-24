
<?php
session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth_check.php';

// Get payroll ID
$payroll_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$payroll_id || $payroll_id < 1) {
    header('Location: index.php?error=invalid_id');
    exit;
}

// Fetch payroll and employee details
$sql = "SELECT payroll.*, employees.full_name
        FROM payroll
        JOIN employees ON payroll.employee_id = employees.id
        WHERE payroll.id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $payroll_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    header('Location: index.php?error=not_found');
    exit;
}

$payroll = $result->fetch_assoc();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Salary Slip</title>
    <link rel="stylesheet" href="../assets/css/app.css">
    <link rel="stylesheet" href="../assets/css/payroll-salary-slip.css">
</head>

<body>

<div class="salary-slip">

    <div class="header">
        <h1>Employee Salary Slip</h1>
        <p>Employee Management & Payroll System</p>
        <h3>
            Payroll Month:
            <?= htmlspecialchars($payroll['payroll_month'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
        </h3>
    </div>

    <div class="employee-details">
        <strong>Employee Name:</strong>
        <?= htmlspecialchars($payroll['full_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>

        <br>

        <strong>Employee ID:</strong>
        <?= (int)$payroll['employee_id'] ?>

        <br>

        <strong>Payroll Status:</strong>
        <span class="status">
            <?= htmlspecialchars($payroll['status'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
        </span>
    </div>

    <table>
        <thead>
            <tr>
                <th>Salary Component</th>
                <th class="amount">Amount</th>
            </tr>
        </thead>

        <tbody>
            <tr>
                <td>Basic Salary</td>
                <td class="amount">
                    <?= number_format((float)$payroll['basic_salary'], 2) ?>
                </td>
            </tr>

            <tr>
                <td>Allowances</td>
                <td class="amount">
                    <?= number_format((float)$payroll['allowances'], 2) ?>
                </td>
            </tr>

            <tr>
                <td>Overtime Pay</td>
                <td class="amount">
                    <?= number_format((float)$payroll['overtime_pay'], 2) ?>
                </td>
            </tr>

            <tr>
                <td>Unpaid Leave Deduction</td>
                <td class="amount">
                    - <?= number_format((float)$payroll['unpaid_deduction'], 2) ?>
                </td>
            </tr>

            <tr>
                <td>Other Deductions</td>
                <td class="amount">
                    - <?= number_format((float)$payroll['other_deductions'], 2) ?>
                </td>
            </tr>
        </tbody>
    </table>

    <div class="net-salary">
        <span>Net Salary</span>
        <span>
            ₹<?= number_format((float)$payroll['net_salary'], 2) ?>
        </span>
    </div>

    <div class="actions">
        <button class="btn print-btn" onclick="window.print()">
            Print Salary Slip
        </button>

        <a href="index.php" class="btn back-btn">
            Back to Payroll
        </a>
    </div>

</div>

</body>
</html>
