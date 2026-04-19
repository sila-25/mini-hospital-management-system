<?php
/**
 * Logout Script
 * VeeCare Medical Centre
 * 
 * Securely terminates the user session and redirects to login page
 */

// Start session to access session data
session_start();

// Clear all session variables
$_SESSION = array();

// If a session cookie is used, delete it
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Delete remember me cookie if it exists
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/', '', true, true);
}

// Destroy the session completely
session_destroy();

// Clear any additional session-related cookies or variables
unset($_SESSION);

// Optional: Clear remember token from database if needed
// This requires database connection and should be done before destroying session
// Uncomment the following block if you want to clear the remember token on logout

/*
// Include database configuration if you want to clear remember token
if (isset($_COOKIE['remember_token'])) {
    require_once 'config/database.php';
    $token = $_COOKIE['remember_token'];
    $token_hash = hash('sha256', $token);
    
    $sql = "UPDATE users SET remember_token = NULL, token_expiry = NULL WHERE remember_token = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $token_hash);
    $stmt->execute();
    $stmt->close();
    $conn->close();
}
*/

// Redirect to login page with logout success parameter (optional)
header("Location: login.php?logout=success");
exit();
?>