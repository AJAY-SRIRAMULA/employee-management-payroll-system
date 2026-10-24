
<?php

session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/remember_secret.php';

$error = '';
$cookieName = 'remember_login';

$cookieSecure = !empty($_SERVER['HTTPS'])
    && $_SERVER['HTTPS'] !== 'off';


// Create a remember-me token and save it in the database
function createRememberToken($conn, $userId, $secure)
{
    $selector = bin2hex(random_bytes(12));
    $validator = bin2hex(random_bytes(32));

    $tokenHash = hash('sha256', $validator);

    $expires = time() + (30 * 24 * 60 * 60);
    $expiresAt = date('Y-m-d H:i:s', $expires);

    $sql = "INSERT INTO remember_tokens
            (user_id, selector, token_hash, expires_at)
            VALUES (?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param(
        "isss",
        $userId,
        $selector,
        $tokenHash,
        $expiresAt
    );

    $inserted = $stmt->execute();

    $stmt->close();

    if (!$inserted) {
        return false;
    }

    $cookieValue = $selector . ':' . $validator;

    return setcookie($GLOBALS['cookieName'], $cookieValue, [
        'expires' => $expires,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}


// Clear the current browser's remember-me token and cookie
function clearRememberToken($conn, $secure)
{
    if (isset($_COOKIE['remember_login'])) {
        $parts = explode(':', $_COOKIE['remember_login']);

        if (
            count($parts) === 2 &&
            preg_match('/^[a-f0-9]{24}$/', $parts[0])
        ) {
            $selector = $parts[0];

            $sql = "DELETE FROM remember_tokens WHERE selector = ?";
            $stmt = $conn->prepare($sql);

            if ($stmt) {
                $stmt->bind_param("s", $selector);
                $stmt->execute();
                $stmt->close();
            }
        }
    }

    setcookie('remember_login', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    unset($_COOKIE['remember_login']);
}


// Restore login when the session is no longer available
if (
    !isset($_SESSION['user_id']) &&
    isset($_COOKIE[$cookieName])
) {
    $cookieParts = explode(':', $_COOKIE[$cookieName]);

    if (
        count($cookieParts) === 2 &&
        preg_match('/^[a-f0-9]{24}$/', $cookieParts[0]) &&
        preg_match('/^[a-f0-9]{64}$/', $cookieParts[1])
    ) {
        $selector = $cookieParts[0];
        $validator = $cookieParts[1];

        $sql = "SELECT id, user_id, token_hash, expires_at
                FROM remember_tokens
                WHERE selector = ?
                LIMIT 1";

        $stmt = $conn->prepare($sql);

        $tokenFound = false;
        $tokenId = null;
        $userId = null;
        $storedHash = null;
        $expiresAt = null;

        if ($stmt) {
            $stmt->bind_param("s", $selector);

            if ($stmt->execute()) {
                $stmt->store_result();

                if ($stmt->num_rows === 1) {
                    $stmt->bind_result(
                        $tokenId,
                        $userId,
                        $storedHash,
                        $expiresAt
                    );

                    $stmt->fetch();
                    $tokenFound = true;
                }
            }

            $stmt->close();
        }

        if (
            $tokenFound &&
            strtotime($expiresAt) > time() &&
            hash_equals($storedHash, hash('sha256', $validator))
        ) {
            $sql = "SELECT id, username
                    FROM users
                    WHERE id = ?
                    LIMIT 1";

            $stmt = $conn->prepare($sql);

            if ($stmt) {
                $stmt->bind_param("i", $userId);

                if ($stmt->execute()) {
                    $stmt->store_result();

                    if ($stmt->num_rows === 1) {
                        $stmt->bind_result($dbUserId, $dbUsername);
                        $stmt->fetch();

                        session_regenerate_id(true);

                        $_SESSION['user_id'] = $dbUserId;
                        $_SESSION['username'] = $dbUsername;

                        // Rotate the token after successful restoration
                        $delete = $conn->prepare(
                            "DELETE FROM remember_tokens WHERE id = ?"
                        );

                        if ($delete) {
                            $delete->bind_param("i", $tokenId);
                            $delete->execute();
                            $delete->close();
                        }

                        createRememberToken(
                            $conn,
                            $dbUserId,
                            $cookieSecure
                        );

                        $stmt->close();
                        $conn->close();

                        header('Location: ../dashboard.php');
                        exit;
                    }
                }

                $stmt->close();
            }
        }

        // Remove invalid or expired token
        if ($tokenFound) {
            $delete = $conn->prepare(
                "DELETE FROM remember_tokens WHERE id = ?"
            );

            if ($delete) {
                $delete->bind_param("i", $tokenId);
                $delete->execute();
                $delete->close();
            }
        }
    }

    // Invalid or unusable cookie
    clearRememberToken($conn, $cookieSecure);
}


// Normal username/password login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $rememberMe = ($_POST['remember_me'] ?? '') === '1';

    if ($username === '' || strlen($username) > 100) {
        $error = 'Please enter a valid username.';
    } elseif ($password === '') {
        $error = 'Please enter your password.';
    } else {

        $sql = "SELECT id, username, password
                FROM users
                WHERE username = ?
                LIMIT 1";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            $error = 'Something went wrong. Please try again.';
        } else {

            $stmt->bind_param("s", $username);

            if (!$stmt->execute()) {
                $error = 'Something went wrong. Please try again.';
            } else {

                $stmt->store_result();

                if ($stmt->num_rows === 1) {

                    $stmt->bind_result(
                        $userId,
                        $dbUsername,
                        $passwordHash
                    );

                    $stmt->fetch();

                    if (password_verify($password, $passwordHash)) {

                        session_regenerate_id(true);

                        $_SESSION['user_id'] = $userId;
                        $_SESSION['username'] = $dbUsername;

                        $stmt->close();

                        if ($rememberMe) {
                            if (!createRememberToken(
                                $conn,
                                $userId,
                                $cookieSecure
                            )) {
                                $error = 'Login successful, but Remember Me could not be enabled. Please try again later.';

                                $conn->close();

                                // The user is still logged in for this session
                                header('Location: ../dashboard.php');
                                exit;
                            }
                        } else {
                            clearRememberToken($conn, $cookieSecure);
                        }

                        $conn->close();

                        header('Location: ../dashboard.php');
                        exit;
                    }
                }

                $error = 'Invalid username or password.';
            }

            $stmt->close();
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Admin Login | Employee Payroll System</title>
    <link rel="stylesheet" href="../assets/css/login.css">
</head>

<body>

<div class="login-container">

    <h1>Employee Payroll System</h1>
    <h2>Admin Login</h2>

    <?php if ($error !== ''): ?>
        <p class="error-message" role="alert">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </p>
    <?php endif; ?>

    <form action="login.php" method="POST">

        <div class="form-group">
            <label for="username">Username:</label>

            <input
                type="text"
                name="username"
                id="username"
                maxlength="100"
                autocomplete="username"
                required
            >
        </div>

        <div class="form-group">
            <label for="password">Password:</label>

            <input
                type="password"
                name="password"
                id="password"
                autocomplete="current-password"
                required
            >
        </div>

        <div class="remember-me">
            <label>
                <input
                    type="checkbox"
                    name="remember_me"
                    value="1"
                >
                Remember me for 30 days
            </label>
        </div>

        <button type="submit">Login</button>

    </form>

</div>

</body>
</html>
