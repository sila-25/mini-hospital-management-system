<?php
/**
 * Authentication Guard - VeeCare Medical Centre
 * Secure access control for all protected pages
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    // Store the requested URL to redirect back after login
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    
    // Clear any existing session data
    session_regenerate_id(true);
    
    // Redirect to login page with error message
    header("Location: login.php?error=unauthorized");
    exit();
}

// Check if session has expired (optional: set session timeout)
$session_timeout = 3600; // 1 hour
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $session_timeout)) {
    // Session expired
    session_unset();
    session_destroy();
    header("Location: login.php?error=session_expired");
    exit();
}

// Update last activity time
$_SESSION['last_activity'] = time();

// Optional: Check if user account is still active in database
function isUserActive($user_id, $conn) {
    $stmt = $conn->prepare("SELECT is_active, role FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        if ($row['is_active'] == 0) {
            return false;
        }
        // Update session role in case it changed
        $_SESSION['user_role'] = $row['role'];
        return true;
    }
    return false;
}

// Optional: Role-based access control function
function hasRole($allowed_roles = []) {
    if (empty($allowed_roles)) {
        return true;
    }
    
    $user_role = $_SESSION['user_role'] ?? '';
    return in_array($user_role, $allowed_roles);
}

// Optional: Permission check function
function hasPermission($permission) {
    // Define role-based permissions
    $permissions = [
        'admin' => ['all'],
        'doctor' => ['view_patients', 'add_prescription', 'view_appointments', 'add_treatment'],
        'receptionist' => ['view_patients', 'add_patient', 'view_appointments', 'add_appointment'],
        'staff' => ['view_patients', 'view_appointments']
    ];
    
    $user_role = $_SESSION['user_role'] ?? 'staff';
    
    if ($user_role === 'admin') {
        return true;
    }
    
    return in_array($permission, $permissions[$user_role] ?? []);
}

// Optional: CSRF Protection
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Generate CSRF token for forms
$csrf_token = generateCSRFToken();

// Note: Database connection should be established in the calling page
// This file only handles authentication logic
?>