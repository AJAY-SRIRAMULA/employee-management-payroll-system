
<?php
require_once __DIR__ . '/config/auth_check.php';

$username = htmlspecialchars(
    $_SESSION['username'] ?? 'Admin',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Employee Payroll System</title>
    <link rel="stylesheet" href="assets/css/dashboard.css">
</head>
<body>

<header>
    <h1>Employee Management & Payroll System</h1>
    <p>Administrator Dashboard</p>
    <a class="logout" href="auth/logout.php">Logout</a>
</header>

<main>
    <section class="welcome">
        <h2>Welcome, <?= $username ?>!</h2>
        <p>Manage employees, attendance, leave requests, and payroll from here.</p>
    </section>

    <section class="modules">
        <a class="module" href="departments/">Departments</a>
        <a class="module" href="employees/">Employees</a>
        <a class="module" href="attendance/">Attendance</a>
        <a class="module" href="leaves/">Leave Requests</a>
        <a class="module" href="payroll/">Payroll</a>
        <a class="module" href="reports/">Reports</a>
    </section>
</main>

<footer>
    Employee Management & Payroll System
</footer>

</body>
</html>
