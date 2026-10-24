
<?php

require_once __DIR__ . '/../config/auth_check.php';
require_once __DIR__ . '/../config/db.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    header('Location: index.php?error=invalid_id');
    exit;
}

$sql = "DELETE FROM leave_requests WHERE id = ?";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    error_log("Leave delete preparation failed: " . $conn->error);
    header('Location: index.php?error=delete_failed');
    exit;
}

$stmt->bind_param('i', $id);

if (!$stmt->execute()) {
    $errorCode = $stmt->errno;

    error_log("Leave delete failed: " . $stmt->error);

    $stmt->close();
    $conn->close();

    if ($errorCode === 1451) {
        header('Location: index.php?error=cannot_delete');
    } else {
        header('Location: index.php?error=delete_failed');
    }

    exit;
}

if ($stmt->affected_rows === 0) {
    $stmt->close();
    $conn->close();

    header('Location: index.php?error=not_found');
    exit;
}

$stmt->close();
$conn->close();

header('Location: index.php?success=deleted');
exit;

?>
