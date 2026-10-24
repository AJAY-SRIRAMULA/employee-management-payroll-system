<?php
require_once __DIR__ . '/../config/auth_check.php';
require_once __DIR__ . '/../config/db.php';

$sql = "SELECT attendance.*, employees.employee_code, employees.full_name
        FROM attendance
        JOIN employees ON employees.id = attendance.employee_id
        ORDER BY attendance.attendance_date DESC, attendance.id DESC";

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
    <title>Attendance</title>
    <link rel="stylesheet" href="../assets/css/app.css">
    <link rel="stylesheet" href="../assets/css/attendance-index.css">
</head>
<body>

    <h1>Attendance</h1>
    <a href="create.php">Mark Attendance</a>

    <table>
        <thead>
            <tr>
                <th>Id</th>
                <th>Employee Code</th>
                <th>Full Name</th>
                <th>Attendance Date</th>
                <th>Status</th>
                <th>Check In</th>
                <th>Check Out</th>
                <th>Overtime Hours</th>
                <th>Remarks</th>
                <th>Created At</th>
                <th>Actions</th>
            </tr>
        </thead>

        <tbody>
            <?php if ($result->num_rows > 0): ?>
                <?php while ($attendance = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($attendance['id']) ?></td>
                        <td><?= htmlspecialchars($attendance['employee_code']) ?></td>
                        <td><?= htmlspecialchars($attendance['full_name']) ?></td>
                        <td><?= htmlspecialchars($attendance['attendance_date']) ?></td>
                        <td>
                            <?php if ($attendance['status'] === 'Present'): ?>
                                <span class="status-present">Present</span>
                            <?php elseif ($attendance['status'] === 'Absent'): ?>
                                <span class="status-absent">Absent</span>
                            <?php elseif ($attendance['status'] === 'Late'): ?>
                                <span class="status-late">Late</span>
                            <?php elseif ($attendance['status'] === 'Half Day'): ?>
                                <span class="status-half-day">Half Day</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($attendance['check_in'] ?? '') ?></td>
                        <td><?= htmlspecialchars($attendance['check_out'] ?? '') ?></td>
                        <td><?= htmlspecialchars($attendance['overtime_hours']) ?></td>
                        <td><?= htmlspecialchars($attendance['remarks'] ?? '') ?></td>
                        <td><?= htmlspecialchars($attendance['created_at']) ?></td>
                        <td>
                            <a href="edit.php?id=<?= (int)$attendance['id'] ?>">Edit</a>

                            <a href="delete.php?id=<?= (int)$attendance['id'] ?>"
                               onclick="return confirm('Are you sure you want to delete this attendance?')">Delete</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="11">No attendance records found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

</body>
</html>