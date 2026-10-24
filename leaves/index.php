<?php

session_start();

require_once __DIR__ . '/../config/auth_check.php';
require_once __DIR__ . '/../config/db.php';

$sql = "SELECT leave_requests.*, employees.full_name
        FROM leave_requests
        JOIN employees
        ON leave_requests.employee_id = employees.id
        ORDER BY leave_requests.created_at DESC";

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

    <title>Leave Management</title>
    <link rel="stylesheet" href="../assets/css/app.css">
    <link rel="stylesheet" href="../assets/css/leaves-index.css">
</head>

<body>

    <h1>Leave Management</h1>

    <a href="create.php" class="create-link">Apply for Leave</a>

    <table>

        <thead>
            <tr>
                <th>Employee Name</th>
                <th>Leave Type</th>
                <th>Start Date</th>
                <th>End Date</th>
                <th>Reason</th>
                <th>Status</th>
                <th>Requested On</th>
                <th>Actions</th>
            </tr>
        </thead>

        <tbody>

            <?php if ($result->num_rows > 0): ?>

                <?php while ($leave = $result->fetch_assoc()): ?>

                    <tr>

                        <td>
                            <?= htmlspecialchars($leave['full_name'], ENT_QUOTES, 'UTF-8') ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($leave['leave_type'], ENT_QUOTES, 'UTF-8') ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($leave['start_date'], ENT_QUOTES, 'UTF-8') ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($leave['end_date'], ENT_QUOTES, 'UTF-8') ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($leave['reason'], ENT_QUOTES, 'UTF-8') ?>
                        </td>

                        <td>
                            <?php if ($leave['status'] === 'Pending'): ?>

                                <span class="status pending">Pending</span>

                            <?php elseif ($leave['status'] === 'Approved'): ?>

                                <span class="status approved">Approved</span>

                            <?php elseif ($leave['status'] === 'Rejected'): ?>

                                <span class="status rejected">Rejected</span>

                            <?php endif; ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($leave['created_at'], ENT_QUOTES, 'UTF-8') ?>
                        </td>

                        <td>

                            <a
                                href="edit.php?id=<?= (int) $leave['id'] ?>"
                                class="edit-link"
                            >Edit</a>

                            <a
                                href="delete.php?id=<?= (int) $leave['id'] ?>"
                                class="delete-link"
                                onclick="return confirm('Are you sure you want to delete this leave request?')"
                            >Delete</a>

                            <?php if ($leave['status'] === 'Pending'): ?>

                                <form
                                    action="update_status.php"
                                    method="POST"
                                    class="action-form"
                                >

                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?= (int) $leave['id'] ?>"
                                    >

                                    <button
                                        type="submit"
                                        name="status"
                                        value="Approved"
                                        class="action-button approve-button"
                                        onclick="return confirm('Approve this leave request?')"
                                    >Approve</button>

                                    <button
                                        type="submit"
                                        name="status"
                                        value="Rejected"
                                        class="action-button reject-button"
                                        onclick="return confirm('Reject this leave request?')"
                                    >Reject</button>

                                </form>

                            <?php endif; ?>

                        </td>

                    </tr>

                <?php endwhile; ?>

            <?php else: ?>

                <tr>
                    <td colspan="8" class="empty-message">
                        No leave requests found.
                    </td>
                </tr>

            <?php endif; ?>

        </tbody>

    </table>

</body>

</html>
