<?php
require_once __DIR__ . '/../config/auth_check.php';
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id'] ?? 0);

    if ($id <= 0) {
        $conn->close();
        header("Location: index.php?error=invalid_id");
        exit();
    }

    // Delete the department
    $stmt = $conn->prepare("DELETE FROM departments WHERE id = ?");

    if (!$stmt) {
        error_log("Department delete preparation failed: " . $conn->error);
        $conn->close();
        header("Location: index.php?error=delete_failed");
        exit();
    }

    $stmt->bind_param("i", $id);

    if (!$stmt->execute()) {
        $errorCode = $stmt->errno;

        error_log("Department delete failed: " . $stmt->error);

        $stmt->close();
        $conn->close();

        if ($errorCode === 1451) {
            header("Location: index.php?error=cannot_delete");
        } else {
            header("Location: index.php?error=delete_failed");
        }

        exit();
    }

    if ($stmt->affected_rows === 0) {
        $stmt->close();
        $conn->close();

        header("Location: index.php?error=not_found");
        exit();
    } else {
        $stmt->close();
        $conn->close();

        header("Location: index.php?success=deleted");
        exit();
    }

} else {
    $conn->close();
    header("Location: index.php?error=invalid_request");
    exit();
}
?>
