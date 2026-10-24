
<?php
session_start();

if (!isset($_SESSION['username'])) {
    header('Location: ../auth/login.php');
    exit();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth_check.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    header('Location: index.php');
    exit();
}

// Check whether the employee exists
$sql = "SELECT id, status FROM employees WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();

$result = $stmt->get_result();
$employee = $result->fetch_assoc();
$stmt->close();

if (!$employee) {
    $conn->close();
    header('Location: index.php?error=not_found');
    exit();
}

// Check whether the employee is already inactive
if ($employee['status'] === 'INACTIVE') {
    $conn->close();
    header('Location: index.php?error=already_inactive');
    exit();
}

// Deactivate the employee
$sql = "UPDATE employees SET status = 'INACTIVE' WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);

if (!$stmt->execute()) {
    error_log("Employee deactivation failed: " . $stmt->error);

    $stmt->close();
    $conn->close();

    header('Location: index.php?error=deactivation_failed');
    exit();
}

$stmt->close();
$conn->close();

header('Location: index.php?success=deactivated');
exit();
?>
