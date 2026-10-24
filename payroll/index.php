
<?php
session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth_check.php';

$sql = "SELECT payroll.*, employees.full_name
        FROM payroll
        JOIN employees ON payroll.employee_id = employees.id
        ORDER BY payroll.payroll_month DESC";

$result = $conn->query($sql);

if (!$result) {
    die('Query failed: ' . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Payroll</title>
    <link rel="stylesheet" href="../assets/css/app.css">
    <link rel="stylesheet" href="../assets/css/payroll-index.css">
</head>

<body>

<div class="container">

    <h1>Employee Payroll</h1>

    <a href="create.php" class="add-btn">+ Generate Payroll</a>

    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Employee Name</th>
                    <th>Payroll Month</th>
                    <th>Basic Salary</th>
                    <th>Allowances</th>
                    <th>Overtime Pay</th>
                    <th>Unpaid Deduction</th>
                    <th>Other Deductions</th>
                    <th>Net Salary</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>

            <tbody>
                <?php if ($result->num_rows > 0): ?>

                    <?php while ($payroll = $result->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <?= htmlspecialchars($payroll['full_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($payroll['payroll_month'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                            </td>

                            <td><?= number_format((float) $payroll['basic_salary'], 2) ?></td>
                            <td><?= number_format((float) $payroll['allowances'], 2) ?></td>
                            <td><?= number_format((float) $payroll['overtime_pay'], 2) ?></td>
                            <td><?= number_format((float) $payroll['unpaid_deduction'], 2) ?></td>
                            <td><?= number_format((float) $payroll['other_deductions'], 2) ?></td>
                            <td><strong><?= number_format((float) $payroll['net_salary'], 2) ?></strong></td>

                            <td>
                                <span class="status <?= strtolower($payroll['status']) ?>">
                                    <?= htmlspecialchars($payroll['status'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                                </span>
                            </td>

                            <td>
                                <a href="edit.php?id=<?= (int) $payroll['id'] ?>"
                                   class="edit-btn">Edit</a>

                                <a href="delete.php?id=<?= (int) $payroll['id'] ?>"
                                   class="delete-btn"
                                   onclick="return confirm('Are you sure you want to delete this payroll?')">
                                    Delete
                                </a>
                                <a href="salary_slip.php?id=<?= (int)$payroll['id'] ?>">
    View Salary Slip
</a>
                                
<?php if ($payroll['status'] === 'Draft'): ?>

    <form action="update_status.php" method="POST" class="inline-action-form">
        <input type="hidden" name="id" value="<?= (int)$payroll['id'] ?>">
        <input type="hidden" name="status" value="Finalized">

        <button type="submit"
                onclick="return confirm('Finalize this payroll?')">
            Finalize
        </button>
    </form>

<?php elseif ($payroll['status'] === 'Finalized'): ?>

    <form action="update_status.php" method="POST" class="inline-action-form">
        <input type="hidden" name="id" value="<?= (int)$payroll['id'] ?>">
        <input type="hidden" name="status" value="Paid">

        <button type="submit"
                onclick="return confirm('Mark this payroll as Paid?')">
            Mark Paid
        </button>
    </form>

<?php endif; ?>

                            </td>
                            
                        </tr>
                    <?php endwhile; ?>

                <?php else: ?>
                    <tr>
                        <td colspan="10" class="empty-message">
                            No payroll records found.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

</body>
</html>
