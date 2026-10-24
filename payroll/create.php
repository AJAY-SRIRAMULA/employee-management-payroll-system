
<?php
session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth_check.php';

$error = '';

$employee_id = '';
$payroll_month = '';
$overtime_hours = '0';
$unpaid_deduction = '0';
$other_deductions = '0';

// Fetch active employees
$employeeSql = "SELECT id, full_name
                FROM employees
                WHERE status = 'ACTIVE'
                ORDER BY full_name";

$employees = $conn->query($employeeSql);

if (!$employees) {
    die('Failed to fetch employees: ' . $conn->error);
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $employee_id = $_POST['employee_id'] ?? '';
    $payroll_month = $_POST['payroll_month'] ?? '';
    $overtime_hours = $_POST['overtime_hours'] ?? '0';
    $unpaid_deduction = $_POST['unpaid_deduction'] ?? '0';
    $other_deductions = $_POST['other_deductions'] ?? '0';

    // Validate required fields
    if (
        $employee_id === '' ||
        !ctype_digit($employee_id) ||
        (int)$employee_id < 1 ||
        !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $payroll_month) ||
        !is_numeric($overtime_hours) ||
        !is_numeric($unpaid_deduction) ||
        !is_numeric($other_deductions) ||
        (float)$overtime_hours < 0 ||
        (float)$unpaid_deduction < 0 ||
        (float)$other_deductions < 0
    ) {
        $error = 'Please enter valid payroll details.';
    } else {

        $employee_id = (int)$employee_id;
        $overtime_hours = (float)$overtime_hours;
        $unpaid_deduction = (float)$unpaid_deduction;
        $other_deductions = (float)$other_deductions;

        // Convert selected month to a date
        $payroll_date = $payroll_month . '-01';

        // Check whether payroll already exists for this employee and month
        $checkSql = "SELECT id
                     FROM payroll
                     WHERE employee_id = ?
                     AND payroll_month = ?";

        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param("is", $employee_id, $payroll_date);
        $checkStmt->execute();

        $existingPayroll = $checkStmt->get_result();

        if ($existingPayroll->num_rows > 0) {

            $error = 'Payroll already exists for this employee and month.';

        } else {

            // Find the applicable salary structure
            $salarySql = "SELECT id, basic_salary, allowances,
                                 deductions, overtime_rate
                          FROM salary_structures
                          WHERE employee_id = ?
                          AND effective_from <= ?
                          AND (effective_to IS NULL OR effective_to >= ?)
                          ORDER BY effective_from DESC
                          LIMIT 1";

            $salaryStmt = $conn->prepare($salarySql);
            $salaryStmt->bind_param(
                "iss",
                $employee_id,
                $payroll_date,
                $payroll_date
            );
            $salaryStmt->execute();

            $salaryResult = $salaryStmt->get_result();

            if ($salaryResult->num_rows === 0) {

                $error = 'No applicable salary structure found for this employee and month.';

            } else {

                $salary = $salaryResult->fetch_assoc();

                $salary_structure_id = (int)$salary['id'];
                $basic_salary = (float)$salary['basic_salary'];
                $allowances = (float)$salary['allowances'];
                $overtime_rate = (float)$salary['overtime_rate'];

                // Include the salary structure's deductions
                // in addition to manually entered deductions
                $other_deductions += (float)$salary['deductions'];

                // Calculate overtime pay
                $overtime_pay = $overtime_hours * $overtime_rate;

                // Calculate net salary
                $net_salary = $basic_salary
                            + $allowances
                            + $overtime_pay
                            - $unpaid_deduction
                            - $other_deductions;

                if ($net_salary < 0) {

                    $error = 'Total deductions cannot exceed the gross salary.';

                } else {

                    // Save payroll as Draft
                    $insertSql = "INSERT INTO payroll
                        (
                            employee_id,
                            salary_structure_id,
                            payroll_month,
                            basic_salary,
                            allowances,
                            overtime_hours,
                            overtime_pay,
                            unpaid_deduction,
                            other_deductions,
                            net_salary,
                            status
                        )
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Draft')";

                    $insertStmt = $conn->prepare($insertSql);

                    $insertStmt->bind_param(
                        "iisddddddd",
                        $employee_id,
                        $salary_structure_id,
                        $payroll_date,
                        $basic_salary,
                        $allowances,
                        $overtime_hours,
                        $overtime_pay,
                        $unpaid_deduction,
                        $other_deductions,
                        $net_salary
                    );

                    if ($insertStmt->execute()) {

                        header('Location: index.php?success=created');
                        exit;

                    } else {

                        $error = 'Failed to generate payroll.';
                    }

                    $insertStmt->close();
                }
            }

            $salaryStmt->close();
        }

        $checkStmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Generate Payroll</title>
    <link rel="stylesheet" href="../assets/css/app.css">
    <link rel="stylesheet" href="../assets/css/payroll-create.css">
</head>

<body>

<div class="container">

    <h1>Generate Payroll</h1>

    <?php if ($error !== ''): ?>
        <div class="error">
            <?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">

        <label for="employee_id">Employee</label>

        <select name="employee_id" id="employee_id" required>
            <option value="">Select Employee</option>

            <?php while ($employee = $employees->fetch_assoc()): ?>
                <option
                    value="<?= (int)$employee['id'] ?>"
                    <?= (string)$employee_id === (string)$employee['id'] ? 'selected' : '' ?>
                >
                    <?= htmlspecialchars(
                        $employee['full_name'],
                        ENT_QUOTES | ENT_SUBSTITUTE,
                        'UTF-8'
                    ) ?>
                </option>
            <?php endwhile; ?>
        </select>

        <label for="payroll_month">Payroll Month</label>

        <input
            type="month"
            name="payroll_month"
            id="payroll_month"
            value="<?= htmlspecialchars($payroll_month, ENT_QUOTES, 'UTF-8') ?>"
            required
        >

        <label for="overtime_hours">Overtime Hours</label>

        <input
            type="number"
            name="overtime_hours"
            id="overtime_hours"
            min="0"
            step="0.01"
            value="<?= htmlspecialchars((string)$overtime_hours, ENT_QUOTES, 'UTF-8') ?>"
            required
        >

        <p class="note">
            Overtime pay is calculated using the overtime rate in the employee's salary structure.
        </p>

        <label for="unpaid_deduction">Unpaid Leave Deduction</label>

        <input
            type="number"
            name="unpaid_deduction"
            id="unpaid_deduction"
            min="0"
            step="0.01"
            value="<?= htmlspecialchars((string)$unpaid_deduction, ENT_QUOTES, 'UTF-8') ?>"
            required
        >

        <label for="other_deductions">Additional Deductions</label>

        <input
            type="number"
            name="other_deductions"
            id="other_deductions"
            min="0"
            step="0.01"
            value="<?= htmlspecialchars((string)$other_deductions, ENT_QUOTES, 'UTF-8') ?>"
            required
        >

        <p class="note">
            The salary structure's configured deductions will be added to these additional deductions.
        </p>

        <div class="buttons">
            <button type="submit">Generate Payroll</button>
            <a href="index.php" class="cancel">Cancel</a>
        </div>

    </form>

</div>

</body>
</html>
