
<?php
session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth_check.php';

// Accept POST requests only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?error=invalid_request');
    exit;
}

// Get submitted values
$payroll_id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$new_status = $_POST['status'] ?? '';

// Validate payroll ID
if (!$payroll_id || $payroll_id < 1) {
    header('Location: index.php?error=invalid_id');
    exit;
}

// Allow only valid status transitions
if (!in_array($new_status, ['Finalized', 'Paid'], true)) {
    header('Location: index.php?error=invalid_status');
    exit;
}

// Draft can become Finalized; Finalized can become Paid
if ($new_status === 'Finalized') {

    $sql = "UPDATE payroll
            SET status = 'Finalized',
                finalized_at = NOW()
            WHERE id = ?
            AND status = 'Draft'";

} else {

    $sql = "UPDATE payroll
            SET status = 'Paid'
            WHERE id = ?
            AND status = 'Finalized'";
}

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $payroll_id);

if (!$stmt->execute()) {
    $stmt->close();
    header('Location: index.php?error=update_failed');
    exit;
}

if ($stmt->affected_rows === 1) {
    $stmt->close();
    header('Location: index.php?success=status_updated');
    exit;
}

$stmt->close();

// Record may not exist or its status may have changed
header('Location: index.php?error=invalid_transition');
exit;
?>
