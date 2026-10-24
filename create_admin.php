<?php
// Your PHP code here
require_once __DIR__.'/config/db.php';

$username="admin";
$email="admin@example.com";
$password="admin123";

$passwordHash = password_hash($password, PASSWORD_DEFAULT);
$sql="INSERT INTO users (username,email,password) VALUES (?,?,?)";

$stmt=$conn->prepare($sql);
if(!$stmt){
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param("sss", $username, $email, $passwordHash);
if($stmt->execute()){
    echo "Admin user created successfully.";
} else {
    echo "Error creating admin user: " . $stmt->error;
}
$stmt->close();
$conn->close();
?>