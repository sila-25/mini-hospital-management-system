<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

$stmt = $conn->prepare("SELECT full_name, email, role FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    if (empty($full_name)) $error = "Full name is required.";
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $error = "Valid email required.";
    else {
        $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ? WHERE id = ?");
        $stmt->bind_param("ssi", $full_name, $email, $user_id);
        if ($stmt->execute()) {
            $_SESSION['user_name'] = $full_name;
            $_SESSION['user_email'] = $email;
            $message = "Profile updated successfully.";
            $user['full_name'] = $full_name;
            $user['email'] = $email;
        } else $error = "Update failed.";
        $stmt->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current = $_POST['current_password'];
    $new = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];
    
    $stmt = $conn->prepare("SELECT password_hash FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $hash = $stmt->get_result()->fetch_assoc()['password_hash'];
    $stmt->close();
    
    if (!password_verify($current, $hash)) $error = "Current password is incorrect.";
    elseif (strlen($new) < 8) $error = "New password must be at least 8 characters.";
    elseif (!preg_match('/[A-Z]/', $new) || !preg_match('/[0-9]/', $new)) $error = "Password must contain at least one uppercase letter and one number.";
    elseif ($new !== $confirm) $error = "New passwords do not match.";
    else {
        $new_hash = password_hash($new, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->bind_param("si", $new_hash, $user_id);
        if ($stmt->execute()) $message = "Password changed successfully.";
        else $error = "Failed to update password.";
        $stmt->close();
    }
}

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<!-- MAIN CONTENT - Proper margin-left to avoid sidebar overlap -->
<div style="margin-left: 280px; padding: 24px; background: #f5f7fa; min-height: 100vh;">
    <div style="max-width: 1000px; margin: 0 auto;">
        <!-- Page Header -->
        <div style="background: white; border-radius: 20px; padding: 20px 28px; margin-bottom: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
            <h1 style="margin: 0; font-size: 24px;">My Profile</h1>
            <p style="margin: 4px 0 0; color: #6c86a3;">Manage your personal information and security settings</p>
        </div>

        <?php if ($message): ?>
            <div style="background: #d4edda; color: #155724; padding: 14px 20px; border-radius: 12px; margin-bottom: 24px; border-left: 4px solid #28a745;"><?php echo htmlspecialchars($message); ?></div>
        <?php elseif ($error): ?>
            <div style="background: #f8d7da; color: #721c24; padding: 14px 20px; border-radius: 12px; margin-bottom: 24px; border-left: 4px solid #dc3545;"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 24px;">
            <!-- Profile Information Card -->
            <div style="background: white; border-radius: 20px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
                <div style="padding: 16px 20px; background: #f8fafc; border-bottom: 1px solid #eef2f6; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-id-card" style="color: #0A84FF; font-size: 18px;"></i>
                    <h2 style="font-size: 16px; font-weight: 700; margin: 0;">Profile Information</h2>
                </div>
                <div style="padding: 20px;">
                    <form method="POST">
                        <div style="margin-bottom: 16px;">
                            <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">Full Name</label>
                            <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required style="width:100%; padding: 10px 12px; border:1px solid #ddd; border-radius: 10px;">
                        </div>
                        <div style="margin-bottom: 16px;">
                            <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">Email Address</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required style="width:100%; padding: 10px 12px; border:1px solid #ddd; border-radius: 10px;">
                        </div>
                        <div style="margin-bottom: 16px;">
                            <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">Role</label>
                            <input type="text" value="<?php echo ucfirst($user['role']); ?>" disabled style="width:100%; padding: 10px 12px; border:1px solid #ddd; border-radius: 10px; background:#f8f9fa;">
                        </div>
                        <div style="text-align: right; margin-top: 16px;">
                            <button type="submit" name="update_profile" style="background: #0A84FF; color: white; border: none; padding: 10px 20px; border-radius: 10px; cursor: pointer;"><i class="fas fa-save"></i> Update Profile</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Security Settings Card -->
            <div style="background: white; border-radius: 20px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
                <div style="padding: 16px 20px; background: #f8fafc; border-bottom: 1px solid #eef2f6; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-lock" style="color: #0A84FF; font-size: 18px;"></i>
                    <h2 style="font-size: 16px; font-weight: 700; margin: 0;">Security Settings</h2>
                </div>
                <div style="padding: 20px;">
                    <form method="POST">
                        <div style="margin-bottom: 16px;">
                            <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">Current Password</label>
                            <input type="password" name="current_password" required style="width:100%; padding: 10px 12px; border:1px solid #ddd; border-radius: 10px;">
                        </div>
                        <div style="margin-bottom: 16px;">
                            <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">New Password</label>
                            <input type="password" name="new_password" required style="width:100%; padding: 10px 12px; border:1px solid #ddd; border-radius: 10px;">
                        </div>
                        <div style="margin-bottom: 16px;">
                            <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">Confirm New Password</label>
                            <input type="password" name="confirm_password" required style="width:100%; padding: 10px 12px; border:1px solid #ddd; border-radius: 10px;">
                        </div>
                        <div style="text-align: right; margin-top: 16px;">
                            <button type="submit" name="change_password" style="background: #0A84FF; color: white; border: none; padding: 10px 20px; border-radius: 10px; cursor: pointer;"><i class="fas fa-key"></i> Change Password</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>