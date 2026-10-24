
<?php

session_start();

require_once __DIR__ . '/../config/db.php';

// Detect HTTPS
$cookieSecure = !empty($_SERVER['HTTPS'])
    && $_SERVER['HTTPS'] !== 'off';

// Delete the current browser's remember-me token
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

// Clear the Remember Me cookie
setcookie('remember_login', '', [
    'expires' => time() - 3600,
    'path' => '/',
    'secure' => $cookieSecure,
    'httponly' => true,
    'samesite' => 'Lax'
]);

// Clear the PHP session
$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();

    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $params['path'],
        'domain' => $params['domain'],
        'secure' => $params['secure'],
        'httponly' => $params['httponly'],
        'samesite' => $params['samesite'] ?? 'Lax'
    ]);
}

session_destroy();

if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}

header('Location: login.php');
exit;

?>
