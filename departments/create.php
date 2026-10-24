<?php
require_once __DIR__ . '/../config/auth_check.php';
require_once __DIR__ . '/../config/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Receive and clean form data
    $department_name = trim($_POST['department_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = $_POST['status'] ?? '';

    // Validate department name
    if ($department_name === '' || strlen($department_name) > 100) {
        $error = 'Please enter a valid department name.';
    } elseif (!in_array($status, ['Active', 'Inactive'], true)) {
        $error = 'Please select a valid status.';
    } else {

        // Check for an existing department name
        $sql = "SELECT id FROM departments WHERE department_name = ?";
        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            error_log('Department check preparation failed: ' . $conn->error);
            $error = 'Something went wrong. Please try again.';
        } else {
            $stmt->bind_param("s", $department_name);

            if (!$stmt->execute()) {
                error_log('Department check failed: ' . $stmt->error);
                $error = 'Something went wrong. Please try again.';
            } else {
                $result = $stmt->get_result();

                if ($result->num_rows > 0) {
                    $error = 'A department with that name already exists.';
                }
            }

            $stmt->close();
        }

        // Insert the department if validation passed
        if ($error === '') {
            $sql = "INSERT INTO departments
                    (department_name, description, status)
                    VALUES (?, ?, ?)";

            $stmt = $conn->prepare($sql);

            if (!$stmt) {
                error_log('Department insert preparation failed: ' . $conn->error);
                $error = 'Something went wrong. Please try again.';
            } else {
                $stmt->bind_param(
                    "sss",
                    $department_name,
                    $description,
                    $status
                );

                if ($stmt->execute()) {
                    $stmt->close();
                    $conn->close();

                    header('Location: index.php?success=created');
                    exit;
                } else {
                    if ($stmt->errno === 1062) {
                        $error = 'A department with that name already exists.';
                    } else {
                        error_log('Department insert failed: ' . $stmt->error);
                        $error = 'Unable to create department. Please try again.';
                    }
                }

                $stmt->close();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Create Department</title>
    <link rel="stylesheet" href="../assets/css/app.css">
    <link rel="stylesheet" href="../assets/css/departments-create.css">
</head>

<body>

<div class="container">

    <div class="page-header">
        <h1>Create Department</h1>
        <p class="subtitle">
            Add a new department to your organization.
        </p>
    </div>

    <div class="form-card">

        <h2 class="form-title">Department Information</h2>
        <p class="form-description">
            Enter the department details below.
        </p>

        <?php if ($error !== ''): ?>
            <div class="alert-error" role="alert">
                <?= htmlspecialchars(
                    $error,
                    ENT_QUOTES | ENT_SUBSTITUTE,
                    'UTF-8'
                ) ?>
            </div>
        <?php endif; ?>

        <form action="create.php" method="POST">

            <div class="form-group">
                <label for="department_name">Department Name</label>

                <input
                    type="text"
                    id="department_name"
                    name="department_name"
                    maxlength="100"
                    placeholder="e.g. Human Resources"
                    value="<?= htmlspecialchars(
                        $_POST['department_name'] ?? '',
                        ENT_QUOTES | ENT_SUBSTITUTE,
                        'UTF-8'
                    ) ?>"
                    required
                >

                <span class="help-text">
                    Enter a unique department name.
                </span>
            </div>

            <div class="form-group">
                <label for="description">Description</label>

                <textarea
                    id="description"
                    name="description"
                    placeholder="Describe the department's responsibilities..."
                ><?= htmlspecialchars(
                    $_POST['description'] ?? '',
                    ENT_QUOTES | ENT_SUBSTITUTE,
                    'UTF-8'
                ) ?></textarea>
            </div>

            <div class="form-group">
                <label for="status">Department Status</label>

                <select id="status" name="status" required>
                    <option value="Active"
                        <?= ($_POST['status'] ?? 'Active') === 'Active' ? 'selected' : '' ?>>
                        Active
                    </option>

                    <option value="Inactive"
                        <?= ($_POST['status'] ?? '') === 'Inactive' ? 'selected' : '' ?>>
                        Inactive
                    </option>
                </select>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    Create Department
                </button>

                <a href="index.php" class="btn btn-secondary">
                    Cancel
                </a>
            </div>

        </form>
    </div>

    <a href="index.php" class="back-link">
        &larr; Back to Departments
    </a>

</div>

</body>
</html>
