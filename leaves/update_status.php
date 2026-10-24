<?php

session_start();

require_once __DIR__ . '/../config/auth_check.php';
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $id = (int) ($_POST['id'] ?? 0);
    $status = $_POST['status'] ?? '';

    if ($id > 0 && in_array($status, ['Approved', 'Rejected'], true)) {

        $sql = "UPDATE leave_requests
                SET status = ?, approved_by = ?
                WHERE id = ? AND status = 'Pending'";

        $stmt = $conn->prepare($sql);

        if ($stmt) {
            $userId = (int) $_SESSION['user_id'];

            $stmt->bind_param("sii", $status, $userId, $id);
            $stmt->execute();
            $stmt->close();
        }
    }
}

$conn->close();

header('Location: index.php');
exit;

?>
