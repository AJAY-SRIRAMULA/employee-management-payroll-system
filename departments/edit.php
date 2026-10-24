<?php
require_once __DIR__ . '/../config/auth_check.php';
require_once __DIR__ . '/../config/db.php';

$error = '';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    die("Invalid department ID.");
}

// Fetch existing department
$stmt = $conn->prepare("SELECT * FROM departments WHERE id = ?");

if (!$stmt) {
    error_log("Department fetch preparation failed: " . $conn->error);
    die("Something went wrong. Please try again.");
}

$stmt->bind_param("i", $id);

if (!$stmt->execute()) {
    error_log("Department fetch failed: " . $stmt->error);
    die("Something went wrong. Please try again.");
}

$result = $stmt->get_result();
$department = $result->fetch_assoc();
$stmt->close();

if (!$department) {
    die("Department not found.");
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $department_name = trim($_POST['department_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = $_POST['status'] ?? '';

    // Validate department name and status
    if ($department_name === '' || strlen($department_name) > 100) {
        $error = "Please enter a valid department name.";
    } elseif (!in_array($status, ['Active', 'Inactive'], true)) {
        $error = "Please select a valid status.";
    } else {

        // Check for duplicate department name, excluding current department
        $sql = "SELECT id FROM departments
                WHERE department_name = ? AND id != ?";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            error_log("Department check preparation failed: " . $conn->error);
            $error = "Something went wrong. Please try again.";
        } else {
            $stmt->bind_param("si", $department_name, $id);

            if (!$stmt->execute()) {
                error_log("Department check failed: " . $stmt->error);
                $error = "Something went wrong. Please try again.";
            } else {
                $result = $stmt->get_result();

                if ($result->num_rows > 0) {
                    $error = "A department with that name already exists.";
                }
            }

            $stmt->close();
        }

        // Update department if validation passed
        if ($error === '') {
            $sql = "UPDATE departments
                    SET department_name = ?, description = ?, status = ?
                    WHERE id = ?";

            $stmt = $conn->prepare($sql);

            if (!$stmt) {
                error_log("Department update preparation failed: " . $conn->error);
                $error = "Something went wrong. Please try again.";
            } else {
                $stmt->bind_param(
                    "sssi",
                    $department_name,
                    $description,
                    $status,
                    $id
                );

                if ($stmt->execute()) {
                    $stmt->close();
                    $conn->close();

                    header("Location: index.php?success=updated");
                    exit;
                } else {
                    if ($stmt->errno === 1062) {
                        $error = "A department with that name already exists.";
                    } else {
                        error_log("Department update failed: " . $stmt->error);
                        $error = "Unable to update department. Please try again.";
                    }
                }

                $stmt->close();
            }
        }

        // Preserve submitted values when validation fails
        $department['department_name'] = $department_name;
        $department['description'] = $description;
        $department['status'] = $status;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Department</title>
    <link rel="stylesheet" href="../assets/css/app.css">
    <link rel="stylesheet" href="../assets/css/departments-edit.css">
</head>

<body>

<div class="container">

    <div class="page-header">
        <h1>Edit Department</h1>
        <p class="subtitle">
            Update the department's information and status.
        </p>
    </div>

    <div class="form-card">

        <h2 class="form-title">Department Information</h2>
        <p class="form-description">
            Make the necessary changes and save your updates.
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

        <form
            method="POST"
            action="edit.php?id=<?= (int) $department['id'] ?>"
        >

            <div class="form-group">
                <label for="department_name">Department Name</label>

                <input
                    type="text"
                    id="department_name"
                    name="department_name"
                    maxlength="100"
                    value="<?= htmlspecialchars(
                        $department['department_name'],
                        ENT_QUOTES | ENT_SUBSTITUTE,
                        'UTF-8'
                    ) ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="description">Description</label>

                <textarea
                    id="description"
                    name="description"
                ><?= htmlspecialchars(
                    $department['description'] ?? '',
                    ENT_QUOTES | ENT_SUBSTITUTE,
                    'UTF-8'
                ) ?></textarea>
            </div>

            <div class="form-group">
                <label for="status">Department Status</label>

                <select id="status" name="status" required>
                    <option
                        value="Active"
                        <?= $department['status'] === 'Active' ? 'selected' : '' ?>
                    >Active</option>

                    <option
                        value="Inactive"
                        <?= $department['status'] === 'Inactive' ? 'selected' : '' ?>
                    >Inactive</option>
                </select>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    Update Department
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
