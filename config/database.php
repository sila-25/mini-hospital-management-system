<?php
/**
 * Database Configuration File
 * VeeCare Medical Centre
 */

// Prevent direct access
if (basename($_SERVER['PHP_SELF']) == 'database.php') {
    header('HTTP/1.0 403 Forbidden');
    exit('Direct access not allowed.');
}

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');  // XAMPP default username
define('DB_PASS', '');      // XAMPP default password is empty
define('DB_NAME', 'veecare_medical');
define('DB_PORT', 3306);
define('DB_CHARSET', 'utf8mb4');

// Enable error reporting for MySQLi
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Global connection variable
$conn = null;
$connection_active = false;

// Create connection function
function getConnection() {
    global $conn, $connection_active;
    
    // Check if connection exists and is still valid
    if ($conn !== null && $connection_active) {
        try {
            if (@$conn->ping()) {
                return $conn;
            }
        } catch (Exception $e) {
            // Connection is dead, create new one
            $connection_active = false;
        }
    }
    
    try {
        // Create new connection
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
        
        // Check connection
        if ($conn->connect_error) {
            throw new Exception("Connection failed: " . $conn->connect_error);
        }
        
        // Set charset
        $conn->set_charset(DB_CHARSET);
        
        // Set timezone
        $conn->query("SET time_zone = '+00:00'");
        
        $connection_active = true;
        return $conn;
        
    } catch (Exception $e) {
        error_log("Database Connection Error: " . $e->getMessage());
        $conn = null;
        $connection_active = false;
        return null;
    }
}

// Close connection function
function closeConnection() {
    global $conn, $connection_active;
    
    if ($conn !== null && $connection_active) {
        try {
            @$conn->close();
        } catch (Exception $e) {
            // Ignore close errors
        }
        $conn = null;
        $connection_active = false;
    }
}

// Initialize connection
try {
    $conn = getConnection();
} catch (Exception $e) {
    $conn = null;
}

// Register shutdown function to close connection only once
register_shutdown_function(function() {
    global $conn, $connection_active;
    if ($conn !== null && $connection_active) {
        try {
            @$conn->close();
        } catch (Exception $e) {
            // Ignore errors during shutdown
        }
        $conn = null;
        $connection_active = false;
    }
});

// Note: formatCurrency function removed from here - it should only be in dashboard.php
?>