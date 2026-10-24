
<?php
session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth_check.php';

// Check payroll ID
$payroll_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$payroll_id || $payroll_id < 1) {
    header('Location: index.php?error=invalid_id');
    exit;
}

// Check whether the payroll record exists and is Draft
$sql = "SELECT id, status
        FROM payroll
        WHERE id = ?";

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

// Allow deletion only for Draft payroll
if ($payroll['status'] !== 'Draft') {
    header('Location: index.php?error=cannot_delete');
    exit;
}

// Delete the payroll record
$deleteSql = "DELETE FROM payroll
              WHERE id = ?
              AND status = 'Draft'";

$deleteStmt = $conn->prepare($deleteSql);
$deleteStmt->bind_param("i", $payroll_id);

if ($deleteStmt->execute() && $deleteStmt->affected_rows === 1) {
    $deleteStmt->close();
    header('Location: index.php?success=deleted');
    exit;
} else {
    $deleteStmt->close();
    header('Location: index.php?error=delete_failed');
    exit;
}
?>
