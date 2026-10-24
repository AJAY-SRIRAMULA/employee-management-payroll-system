<?php


require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth_check.php';


$sql = "SELECT employees.*, departments.department_name AS department_name
        FROM employees
        JOIN departments
        ON employees.department_id = departments.id";

$result = $conn->query($sql);

if (!$result) {
    die("Query failed: " . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Management</title>
    <link rel="stylesheet" href="../assets/css/app.css">
    <link rel="stylesheet" href="../assets/css/employees-index.css">
</head>

<body>

<div class="container">

    <h1>Employee Management</h1>

    <a href="create.php" class="add-button">+ Add Employee</a>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Employee Code</th>
                <th>Full Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Department</th>
                <th>Designation</th>
                <th>Joining Date</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>

        <tbody>
            <?php if ($result->num_rows > 0): ?>

                <?php while ($employee = $result->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <?= htmlspecialchars($employee['id']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($employee['employee_code']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($employee['full_name']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($employee['email']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($employee['phone']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($employee['department_name']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($employee['designation']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($employee['joining_date']) ?>
                        </td>

                        <td>
                            <?php if ($employee['status'] === 'ACTIVE'): ?>
                                <span class="status-active">Active</span>
                            <?php else: ?>
                                <span class="status-inactive">Inactive</span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <a
                                href="edit.php?id=<?= (int) $employee['id'] ?>"
                                class="edit-button"
                            >Edit</a>

                            <a
                                href="deactivate.php?id=<?= (int) $employee['id'] ?>"
                                class="deactivate-button"
                                onclick="return confirm('Are you sure you want to deactivate this employee?');"
                            >Deactivate</a>
                        </td>
                    </tr>
                <?php endwhile; ?>

            <?php else: ?>
                <tr>
                    <td colspan="10" class="empty-message">
                        No employees found.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

</div>

</body>
</html>
