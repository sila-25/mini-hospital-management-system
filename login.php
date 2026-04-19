<?php
// Start session for login management
session_start();

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

// Include database configuration
require_once 'config/database.php';

// Get database connection
$conn = getConnection();

$error = '';
$email = '';
$remember = false;

// Process login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']) ? true : false;
    
    // Validation
    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Query user from database
        if ($conn) {
            $sql = "SELECT id, email, password_hash, full_name, role, is_active FROM users WHERE email = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                
                // Check if account is active
                if ($user['is_active'] == 0) {
                    $error = 'Your account has been deactivated. Please contact the administrator.';
                } else {
                    // Verify password
                    if (password_verify($password, $user['password_hash'])) {
                        // Login successful - set session variables
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_email'] = $user['email'];
                        $_SESSION['user_name'] = $user['full_name'];
                        $_SESSION['user_role'] = $user['role'];
                        $_SESSION['logged_in_at'] = time();
                        
                        // Handle "Remember Me" functionality
                        if ($remember) {
                            $token = bin2hex(random_bytes(32));
                            $token_hash = hash('sha256', $token);
                            $expiry = time() + (86400 * 30); // 30 days
                            
                            // Store token in database
                            $update_sql = "UPDATE users SET remember_token = ?, token_expiry = ? WHERE id = ?";
                            $update_stmt = $conn->prepare($update_sql);
                            $expiry_date = date('Y-m-d H:i:s', $expiry);
                            $update_stmt->bind_param("ssi", $token_hash, $expiry_date, $user['id']);
                            $update_stmt->execute();
                            $update_stmt->close();
                            
                            // Set cookie
                            setcookie('remember_token', $token, $expiry, '/', '', true, true);
                        }
                        
                        // Regenerate session ID for security
                        session_regenerate_id(true);
                        
                        // Redirect to dashboard
                        header("Location: dashboard.php");
                        exit();
                    } else {
                        $error = 'Invalid email or password.';
                    }
                }
            } else {
                $error = 'Invalid email or password.';
            }
            $stmt->close();
        } else {
            $error = 'Database connection error. Please try again later.';
        }
    }
}

// Check for remember me cookie
if (empty($_SESSION['user_id']) && isset($_COOKIE['remember_token']) && $conn) {
    $token = $_COOKIE['remember_token'];
    $token_hash = hash('sha256', $token);
    
    $sql = "SELECT id, email, full_name, role, is_active, token_expiry FROM users WHERE remember_token = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $token_hash);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        $expiry = strtotime($user['token_expiry']);
        
        if ($expiry > time() && $user['is_active'] == 1) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['logged_in_at'] = time();
            
            header("Location: dashboard.php");
            exit();
        }
    }
    $stmt->close();
}

// Note: Don't close connection here - let it close automatically at script end
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Login | VeeCare Medical Centre</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #F5F7FA 0%, #E2E8F2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }

        .login-container {
            width: 100%;
            max-width: 460px;
            margin: 0 auto;
        }

        /* Logo / Brand header */
        .brand-header {
            text-align: center;
            margin-bottom: 1.8rem;
        }

        .brand-icon {
            background: #0A84FF;
            width: 56px;
            height: 56px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem auto;
            box-shadow: 0 8px 16px -6px rgba(10, 132, 255, 0.25);
        }

        .brand-icon i {
            font-size: 28px;
            color: white;
        }

        .brand-header h1 {
            font-size: 1.6rem;
            font-weight: 700;
            color: #1C1C1E;
            margin-bottom: 0.25rem;
        }

        .brand-header p {
            font-size: 0.85rem;
            color: #5E5E66;
        }

        /* Login Card */
        .login-card {
            background: #FFFFFF;
            border-radius: 1.5rem;
            padding: 2rem;
            box-shadow: 0 20px 35px -12px rgba(0, 0, 0, 0.1), 0 0 0 1px rgba(10, 132, 255, 0.05);
            transition: box-shadow 0.2s ease;
        }

        .login-card:hover {
            box-shadow: 0 24px 42px -14px rgba(0, 0, 0, 0.15);
        }

        /* Error alert */
        .error-alert {
            background: #FFF5F5;
            border-left: 3px solid #FF3B30;
            padding: 0.9rem 1rem;
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            animation: shake 0.4s ease;
        }

        .error-alert i {
            color: #FF3B30;
            font-size: 1.1rem;
        }

        .error-alert span {
            color: #D32F2F;
            font-size: 0.85rem;
            font-weight: 500;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        /* Form group */
        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-group label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            color: #2C2C2E;
            margin-bottom: 0.5rem;
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-wrapper i {
            position: absolute;
            left: 1rem;
            color: #8E8E93;
            font-size: 1rem;
            transition: color 0.2s ease;
        }

        .input-wrapper input {
            width: 100%;
            padding: 0.85rem 1rem 0.85rem 2.6rem;
            font-size: 0.95rem;
            font-family: 'Inter', sans-serif;
            border: 1.5px solid #E9ECEF;
            border-radius: 0.9rem;
            background: #FFFFFF;
            transition: all 0.2s ease;
            outline: none;
            color: #1C1C1E;
        }

        .input-wrapper input:focus {
            border-color: #0A84FF;
            box-shadow: 0 0 0 3px rgba(10, 132, 255, 0.15);
        }

        .input-wrapper input:focus + i {
            color: #0A84FF;
        }

        /* Remember me row */
        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            font-size: 0.85rem;
            color: #5E5E66;
        }

        .checkbox-label input {
            width: 1rem;
            height: 1rem;
            cursor: pointer;
            accent-color: #0A84FF;
        }

        .forgot-link {
            font-size: 0.85rem;
            color: #0A84FF;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
        }

        .forgot-link:hover {
            color: #006EDB;
            text-decoration: underline;
        }

        /* Button */
        .login-btn {
            width: 100%;
            background: #0A84FF;
            color: white;
            border: none;
            padding: 0.9rem;
            font-size: 1rem;
            font-weight: 600;
            font-family: 'Inter', sans-serif;
            border-radius: 0.9rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.6rem;
            transition: all 0.25s ease;
            margin-bottom: 1.25rem;
            box-shadow: 0 2px 6px rgba(10, 132, 255, 0.2);
        }

        .login-btn:hover {
            background: #006EDB;
            transform: translateY(-1px);
            box-shadow: 0 8px 18px -6px rgba(10, 132, 255, 0.4);
        }

        .login-btn:active {
            transform: translateY(0);
        }

        /* Register link */
        .register-link {
            text-align: center;
            font-size: 0.85rem;
            color: #5E5E66;
            border-top: 1px solid #E9ECEF;
            padding-top: 1.25rem;
            margin-top: 0.25rem;
        }

        .register-link a {
            color: #0A84FF;
            text-decoration: none;
            font-weight: 600;
        }

        .register-link a:hover {
            text-decoration: underline;
        }

        /* Footer note */
        .footer-note {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.7rem;
            color: #8E8E93;
        }

        @media (max-width: 480px) {
            .login-card {
                padding: 1.5rem;
            }
            .brand-icon {
                width: 48px;
                height: 48px;
            }
            .brand-icon i {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
<div class="login-container">
    <div class="brand-header">
        <div class="brand-icon">
            <i class="fas fa-heartbeat"></i>
        </div>
        <h1>VeeCare Medical Centre</h1>
        <p>Staff secure access portal</p>
    </div>

    <div class="login-card">
        <?php if (!empty($error)): ?>
            <div class="error-alert">
                <i class="fas fa-exclamation-triangle"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" novalidate>
            <div class="form-group">
                <label for="email">Email Address</label>
                <div class="input-wrapper">
                    <i class="fas fa-envelope"></i>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" 
                           placeholder="doctor@veecare.com" required autofocus>
                </div>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-wrapper">
                    <i class="fas fa-lock"></i>
                    <input type="password" id="password" name="password" 
                           placeholder="••••••••" required>
                </div>
            </div>

            <div class="form-options">
                <label class="checkbox-label">
                    <input type="checkbox" name="remember" <?php echo $remember ? 'checked' : ''; ?>>
                    <span>Remember me</span>
                </label>
                <a href="#" class="forgot-link">Forgot password?</a>
            </div>

            <button type="submit" class="login-btn">
                <i class="fas fa-arrow-right-to-bracket"></i> Sign In
            </button>

            <div class="register-link">
                Don't have an account? <a href="register.php">Register new account</a>
            </div>
        </form>
    </div>
    <div class="footer-note">
        <i class="fas fa-shield-alt"></i> Secure & Encrypted Connection
    </div>
</div>
</body>
</html>