
<?php
require_once __DIR__ . '/../config/auth_check.php';
require_once __DIR__ . '/../config/db.php';

$stmt = $conn->prepare("SELECT * FROM departments ORDER BY id ASC");

if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

if (!$stmt->execute()) {
    die("Execute failed: " . $stmt->error);
}

$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Departments</title>
    <link rel="stylesheet" href="../assets/css/app.css">
    <link rel="stylesheet" href="../assets/css/departments-index.css">
</head>

<body>
<div class="container">

    <div class="page-header">
        <div>
            <h1>Departments</h1>
            <p class="subtitle">
                Manage department details and status.
            </p>
        </div>

        <a href="create.php" class="btn btn-primary btn-add">
            + Add Department
        </a>
    </div>

    <div class="table-card">

        <div class="table-heading">
            <h2>Department List</h2>
            <p>View, edit, or delete department records.</p>
        </div>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Department Name</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if ($result->num_rows === 0): ?>
                        <tr>
                            <td colspan="6" class="empty-state">
                                No departments found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td class="id-cell">
                                    <?= htmlspecialchars((string)$row["id"], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                                </td>

                                <td class="department-name">
                                    <?= htmlspecialchars($row["department_name"], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($row["description"] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                                </td>

                                <td>
                                    <span class="status <?= $row["status"] === "Active" ? "status-active" : "status-inactive" ?>">
                                        <?= htmlspecialchars($row["status"], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                                    </span>
                                </td>

                                <td>
                                    <?= htmlspecialchars($row["created_at"], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                                </td>

                                <td class="action-cell">
                                    <a
                                        href="edit.php?id=<?= (int)$row["id"] ?>"
                                        class="btn btn-edit"
                                    >Edit</a>

                                    <form
                                        action="delete.php"
                                        method="POST"
                                        class="delete-form"
                                        onsubmit="return confirm('Are you sure you want to delete this department?');"
                                    >
                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= (int)$row["id"] ?>"
                                        >

                                        <button type="submit" class="btn btn-delete">
                                            Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="page-footer">
        Employee Management &amp; Payroll System
    </div>

</div>

<?php
$stmt->close();
$conn->close();
?>

</body>
</html>