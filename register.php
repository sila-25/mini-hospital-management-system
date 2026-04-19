<?php
// Start session for registration flow
session_start();

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

// Include database configuration
require_once 'config/database.php';

// Initialize variables
$full_name = '';
$email = '';
$role = 'staff';
$errors = [];
$success = false;

// Process registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? 'staff';
    
    // Validation
    if (empty($full_name)) {
        $errors['full_name'] = 'Full name is required.';
    } elseif (strlen($full_name) < 3) {
        $errors['full_name'] = 'Name must be at least 3 characters.';
    }
    
    if (empty($email)) {
        $errors['email'] = 'Email address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    } else {
        // Check if email already exists
        try {
            if ($conn) {
                $check_sql = "SELECT id FROM users WHERE email = ?";
                $check_stmt = $conn->prepare($check_sql);
                if ($check_stmt) {
                    $check_stmt->bind_param("s", $email);
                    $check_stmt->execute();
                    $check_stmt->store_result();
                    if ($check_stmt->num_rows > 0) {
                        $errors['email'] = 'This email is already registered.';
                    }
                    $check_stmt->close();
                }
            } else {
                $errors['general'] = 'Database connection error.';
            }
        } catch (Exception $e) {
            error_log("Email check error: " . $e->getMessage());
            $errors['general'] = 'System error. Please try again later.';
        }
    }
    
    if (empty($password)) {
        $errors['password'] = 'Password is required.';
    } elseif (strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters.';
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $errors['password'] = 'Password must contain at least one uppercase letter.';
    } elseif (!preg_match('/[a-z]/', $password)) {
        $errors['password'] = 'Password must contain at least one lowercase letter.';
    } elseif (!preg_match('/[0-9]/', $password)) {
        $errors['password'] = 'Password must contain at least one number.';
    }
    
    if (empty($confirm_password)) {
        $errors['confirm_password'] = 'Please confirm your password.';
    } elseif ($password !== $confirm_password) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }
    
    if (empty($role)) {
        $errors['role'] = 'Please select a role.';
    }
    
    // If no errors, create user account
    if (empty($errors) && $conn) {
        try {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $is_active = 1; // New accounts are active by default
            
            $insert_sql = "INSERT INTO users (full_name, email, password_hash, role, is_active, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
            $insert_stmt = $conn->prepare($insert_sql);
            
            if ($insert_stmt) {
                $insert_stmt->bind_param("ssssi", $full_name, $email, $password_hash, $role, $is_active);
                
                if ($insert_stmt->execute()) {
                    $success = true;
                    // Clear form data on success
                    $full_name = '';
                    $email = '';
                    $role = 'staff';
                    
                    // Redirect after 2 seconds
                    echo '<meta http-equiv="refresh" content="2;url=login.php">';
                } else {
                    $errors['general'] = 'Registration failed: ' . $insert_stmt->error;
                }
                $insert_stmt->close();
            } else {
                $errors['general'] = 'Database error. Please try again later.';
            }
        } catch (Exception $e) {
            error_log("Registration error: " . $e->getMessage());
            $errors['general'] = 'Registration failed. Please try again later.';
        }
    } elseif (empty($errors) && !$conn) {
        $errors['general'] = 'Database connection unavailable.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Register | VeeCare Medical Centre</title>
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

        .register-container {
            width: 100%;
            max-width: 560px;
            margin: 0 auto;
        }

        /* Brand Header */
        .brand-header {
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .brand-icon {
            background: #34C759;
            width: 56px;
            height: 56px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem auto;
            box-shadow: 0 8px 16px -6px rgba(52, 199, 89, 0.25);
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

        /* Registration Card */
        .register-card {
            background: #FFFFFF;
            border-radius: 1.5rem;
            padding: 2rem;
            box-shadow: 0 20px 35px -12px rgba(0, 0, 0, 0.1);
            transition: box-shadow 0.2s ease;
        }

        .register-card:hover {
            box-shadow: 0 24px 42px -14px rgba(0, 0, 0, 0.15);
        }

        /* Success Message */
        .success-alert {
            background: #E8F7EF;
            border-left: 3px solid #34C759;
            padding: 1rem;
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            animation: slideDown 0.4s ease;
        }

        .success-alert i {
            color: #34C759;
            font-size: 1.2rem;
        }

        .success-alert div {
            flex: 1;
        }

        .success-alert strong {
            color: #1C1C1E;
            font-size: 0.9rem;
        }

        .success-alert p {
            color: #5E5E66;
            font-size: 0.8rem;
            margin-top: 0.2rem;
        }

        .redirect-note {
            margin-top: 0.5rem;
            font-size: 0.75rem;
            color: #0A84FF;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* General Error Alert */
        .error-alert {
            background: #FFF5F5;
            border-left: 3px solid #FF3B30;
            padding: 0.9rem 1rem;
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .error-alert i {
            color: #FF3B30;
            font-size: 1rem;
        }

        .error-alert span {
            color: #D32F2F;
            font-size: 0.85rem;
        }

        /* Form Groups */
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

        .label-required::after {
            content: '*';
            color: #FF3B30;
            margin-left: 0.25rem;
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

        .input-wrapper input, .input-wrapper select {
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

        .input-wrapper select {
            padding-right: 2rem;
            appearance: none;
            cursor: pointer;
        }

        .input-wrapper input:focus, .input-wrapper select:focus {
            border-color: #0A84FF;
            box-shadow: 0 0 0 3px rgba(10, 132, 255, 0.15);
        }

        .input-wrapper input:focus + i, .input-wrapper select:focus + i {
            color: #0A84FF;
        }

        /* Password Strength Indicator */
        .password-strength {
            margin-top: 0.5rem;
        }

        .strength-bar {
            display: flex;
            gap: 0.4rem;
            margin-bottom: 0.4rem;
        }

        .strength-segment {
            flex: 1;
            height: 4px;
            background: #E9ECEF;
            border-radius: 4px;
            transition: all 0.2s ease;
        }

        .strength-segment.weak {
            background: #FF3B30;
        }

        .strength-segment.fair {
            background: #FF9500;
        }

        .strength-segment.good {
            background: #FFCC00;
        }

        .strength-segment.strong {
            background: #34C759;
        }

        .strength-text {
            font-size: 0.7rem;
            color: #8E8E93;
        }

        /* Inline Error Message */
        .error-message {
            font-size: 0.7rem;
            color: #FF3B30;
            margin-top: 0.4rem;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        .error-message i {
            font-size: 0.65rem;
        }

        /* Role Hint */
        .role-hint {
            font-size: 0.7rem;
            color: #8E8E93;
            margin-top: 0.3rem;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        /* Register Button */
        .register-btn {
            width: 100%;
            background: #34C759;
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
            margin-top: 0.5rem;
            margin-bottom: 1.25rem;
            box-shadow: 0 2px 6px rgba(52, 199, 89, 0.25);
        }

        .register-btn:hover {
            background: #2BA54A;
            transform: translateY(-1px);
            box-shadow: 0 8px 18px -6px rgba(52, 199, 89, 0.4);
        }

        .register-btn:active {
            transform: translateY(0);
        }

        /* Login Link */
        .login-link {
            text-align: center;
            font-size: 0.85rem;
            color: #5E5E66;
            border-top: 1px solid #E9ECEF;
            padding-top: 1.25rem;
        }

        .login-link a {
            color: #0A84FF;
            text-decoration: none;
            font-weight: 600;
        }

        .login-link a:hover {
            text-decoration: underline;
        }

        .footer-note {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.7rem;
            color: #8E8E93;
        }

        @media (max-width: 480px) {
            .register-card {
                padding: 1.5rem;
            }
            .brand-icon {
                width: 48px;
                height: 48px;
            }
        }
    </style>
</head>
<body>
<div class="register-container">
    <div class="brand-header">
        <div class="brand-icon">
            <i class="fas fa-user-md"></i>
        </div>
        <h1>Create Account</h1>
        <p>Join VeeCare Medical Centre staff</p>
    </div>

    <div class="register-card">
        <?php if ($success): ?>
            <div class="success-alert">
                <i class="fas fa-check-circle"></i>
                <div>
                    <strong>Registration successful!</strong>
                    <p>Your account has been created. You can now log in to access the system.</p>
                    <div class="redirect-note">
                        <i class="fas fa-spinner fa-pulse"></i>
                        <span>Redirecting to login page...</span>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (isset($errors['general'])): ?>
            <div class="error-alert">
                <i class="fas fa-exclamation-triangle"></i>
                <span><?php echo htmlspecialchars($errors['general']); ?></span>
            </div>
        <?php endif; ?>

        <?php if (!$success): ?>
        <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" novalidate id="registerForm">
            <!-- Full Name -->
            <div class="form-group">
                <label class="label-required">Full Name</label>
                <div class="input-wrapper">
                    <i class="fas fa-user"></i>
                    <input type="text" name="full_name" value="<?php echo htmlspecialchars($full_name); ?>" 
                           placeholder="Dr. Sarah Johnson" required>
                </div>
                <?php if (isset($errors['full_name'])): ?>
                    <div class="error-message"><i class="fas fa-circle-exclamation"></i> <?php echo $errors['full_name']; ?></div>
                <?php endif; ?>
            </div>

            <!-- Email -->
            <div class="form-group">
                <label class="label-required">Email Address</label>
                <div class="input-wrapper">
                    <i class="fas fa-envelope"></i>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($email); ?>" 
                           placeholder="sarah.johnson@veecare.com" required>
                </div>
                <?php if (isset($errors['email'])): ?>
                    <div class="error-message"><i class="fas fa-circle-exclamation"></i> <?php echo $errors['email']; ?></div>
                <?php endif; ?>
            </div>

            <!-- Password -->
            <div class="form-group">
                <label class="label-required">Password</label>
                <div class="input-wrapper">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="password" id="password" 
                           placeholder="Create a strong password" required>
                </div>
                <div class="password-strength" id="passwordStrength">
                    <div class="strength-bar">
                        <div class="strength-segment" id="seg1"></div>
                        <div class="strength-segment" id="seg2"></div>
                        <div class="strength-segment" id="seg3"></div>
                        <div class="strength-segment" id="seg4"></div>
                    </div>
                    <div class="strength-text" id="strengthText">Password strength</div>
                </div>
                <?php if (isset($errors['password'])): ?>
                    <div class="error-message"><i class="fas fa-circle-exclamation"></i> <?php echo $errors['password']; ?></div>
                <?php endif; ?>
            </div>

            <!-- Confirm Password -->
            <div class="form-group">
                <label class="label-required">Confirm Password</label>
                <div class="input-wrapper">
                    <i class="fas fa-check-circle"></i>
                    <input type="password" name="confirm_password" id="confirm_password" 
                           placeholder="Confirm your password" required>
                </div>
                <?php if (isset($errors['confirm_password'])): ?>
                    <div class="error-message"><i class="fas fa-circle-exclamation"></i> <?php echo $errors['confirm_password']; ?></div>
                <?php endif; ?>
            </div>

            <!-- Role Selection -->
            <div class="form-group">
                <label class="label-required">Role</label>
                <div class="input-wrapper">
                    <i class="fas fa-briefcase"></i>
                    <select name="role" required>
                        <option value="staff" <?php echo $role === 'staff' ? 'selected' : ''; ?>>Staff Member</option>
                        <option value="doctor" <?php echo $role === 'doctor' ? 'selected' : ''; ?>>Doctor / Physician</option>
                        <option value="admin" <?php echo $role === 'admin' ? 'selected' : ''; ?>>Administrator</option>
                        <option value="receptionist" <?php echo $role === 'receptionist' ? 'selected' : ''; ?>>Receptionist</option>
                    </select>
                </div>
                <div class="role-hint">
                    <i class="fas fa-info-circle"></i> Select the appropriate role for system access permissions
                </div>
                <?php if (isset($errors['role'])): ?>
                    <div class="error-message"><i class="fas fa-circle-exclamation"></i> <?php echo $errors['role']; ?></div>
                <?php endif; ?>
            </div>

            <button type="submit" class="register-btn">
                <i class="fas fa-user-plus"></i> Register Account
            </button>

            <div class="login-link">
                Already have an account? <a href="login.php">Sign in here</a>
            </div>
        </form>
        <?php endif; ?>
    </div>
    <div class="footer-note">
        <i class="fas fa-shield-alt"></i> Secure registration · All data is encrypted
    </div>
</div>

<script>
    // Password strength indicator
    const passwordInput = document.getElementById('password');
    const confirmInput = document.getElementById('confirm_password');
    const seg1 = document.getElementById('seg1');
    const seg2 = document.getElementById('seg2');
    const seg3 = document.getElementById('seg3');
    const seg4 = document.getElementById('seg4');
    const strengthText = document.getElementById('strengthText');

    function checkPasswordStrength(password) {
        let score = 0;
        
        if (password.length >= 8) score++;
        if (password.length >= 12) score++;
        if (/[A-Z]/.test(password)) score++;
        if (/[a-z]/.test(password)) score++;
        if (/[0-9]/.test(password)) score++;
        if (/[^A-Za-z0-9]/.test(password)) score++;
        
        // Cap at 4 for display
        return Math.min(4, Math.floor(score / 1.5));
    }

    function updateStrengthIndicator() {
        const password = passwordInput.value;
        const strength = checkPasswordStrength(password);
        
        // Reset all segments
        [seg1, seg2, seg3, seg4].forEach(seg => {
            seg.className = 'strength-segment';
        });
        
        if (password.length === 0) {
            strengthText.textContent = 'Password strength';
            strengthText.style.color = '#8E8E93';
            return;
        }
        
        let strengthLevel = '';
        let colorClass = '';
        
        if (strength === 1) {
            strengthLevel = 'Weak';
            colorClass = 'weak';
            seg1.className = 'strength-segment weak';
        } else if (strength === 2) {
            strengthLevel = 'Fair';
            colorClass = 'fair';
            seg1.className = 'strength-segment fair';
            seg2.className = 'strength-segment fair';
        } else if (strength === 3) {
            strengthLevel = 'Good';
            colorClass = 'good';
            seg1.className = 'strength-segment good';
            seg2.className = 'strength-segment good';
            seg3.className = 'strength-segment good';
        } else if (strength >= 4) {
            strengthLevel = 'Strong';
            colorClass = 'strong';
            seg1.className = 'strength-segment strong';
            seg2.className = 'strength-segment strong';
            seg3.className = 'strength-segment strong';
            seg4.className = 'strength-segment strong';
        }
        
        strengthText.textContent = strengthLevel + ' password';
        strengthText.style.color = colorClass === 'weak' ? '#FF3B30' : (colorClass === 'fair' ? '#FF9500' : (colorClass === 'good' ? '#FFCC00' : '#34C759'));
    }
    
    passwordInput.addEventListener('input', updateStrengthIndicator);
    
    // Real-time confirm password validation
    function validateConfirmPassword() {
        const password = passwordInput.value;
        const confirm = confirmInput.value;
        
        if (confirm.length > 0 && password !== confirm) {
            confirmInput.style.borderColor = '#FF3B30';
        } else if (confirm.length > 0 && password === confirm) {
            confirmInput.style.borderColor = '#34C759';
        } else {
            confirmInput.style.borderColor = '#E9ECEF';
        }
    }
    
    if (confirmInput) {
        confirmInput.addEventListener('input', validateConfirmPassword);
        passwordInput.addEventListener('input', validateConfirmPassword);
    }
</script>
</body>
</html>