<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

$message = '';
$error = '';

// Fetch current settings
$settings = [];
$res = $conn->query("SELECT setting_key, setting_value FROM clinic_settings");
while ($row = $res->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $clinic_name = trim($_POST['clinic_name']);
    $clinic_phone = trim($_POST['clinic_phone']);
    $clinic_email = trim($_POST['clinic_email']);
    $clinic_address = trim($_POST['clinic_address']);
    $operating_hours = trim($_POST['operating_hours']);
    $tax_rate = (float)$_POST['tax_rate'];
    $currency = trim($_POST['currency']);
    $timezone = trim($_POST['timezone']);

    if (empty($clinic_name)) $error = "Clinic name is required.";
    elseif (empty($clinic_phone)) $error = "Phone number is required.";
    elseif (!filter_var($clinic_email, FILTER_VALIDATE_EMAIL)) $error = "Valid email is required.";
    else {
        $stmt = $conn->prepare("INSERT INTO clinic_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        
        $keys = ['clinic_name', 'clinic_phone', 'clinic_email', 'clinic_address', 'clinic_hours', 'tax_rate', 'currency_symbol', 'timezone'];
        $values = [$clinic_name, $clinic_phone, $clinic_email, $clinic_address, $operating_hours, $tax_rate, $currency, $timezone];
        
        $success = true;
        for ($i = 0; $i < count($keys); $i++) {
            $key = $keys[$i];
            $value = (string)$values[$i];
            $stmt->bind_param("ss", $key, $value);
            if (!$stmt->execute()) {
                $success = false;
                $error = "Failed to save: " . $stmt->error;
                break;
            }
        }
        $stmt->close();
        if ($success) {
            $message = "Settings saved successfully.";
            // Refresh settings
            $settings = [];
            $res = $conn->query("SELECT setting_key, setting_value FROM clinic_settings");
            while ($row = $res->fetch_assoc()) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        }
    }
}

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div style="margin-left: 280px; padding: 24px; background: #f5f7fa; min-height: 100vh;">
    <div style="max-width: 1200px; margin: 0 auto;">
        <!-- Page Header -->
        <div style="background: white; border-radius: 20px; padding: 20px 28px; margin-bottom: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
            <h1 style="margin: 0; font-size: 24px;">Clinic Settings</h1>
            <p style="margin: 4px 0 0; color: #6c86a3;">Configure clinic information and system preferences</p>
        </div>

        <?php if ($message): ?>
            <div style="background: #d4edda; color: #155724; padding: 14px 20px; border-radius: 12px; margin-bottom: 24px; border-left: 4px solid #28a745;"><?php echo htmlspecialchars($message); ?></div>
        <?php elseif ($error): ?>
            <div style="background: #f8d7da; color: #721c24; padding: 14px 20px; border-radius: 12px; margin-bottom: 24px; border-left: 4px solid #dc3545;"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(380px, 1fr)); gap: 24px; margin-bottom: 32px;">
                <!-- Clinic Information Card -->
                <div style="background: white; border-radius: 20px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
                    <div style="padding: 16px 20px; background: #f8fafc; border-bottom: 1px solid #eef2f6; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-hospital" style="color: #0A84FF; font-size: 18px;"></i>
                        <h2 style="font-size: 16px; font-weight: 700; margin: 0;">Clinic Information</h2>
                    </div>
                    <div style="padding: 20px;">
                        <div style="margin-bottom: 16px;">
                            <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">Clinic Name</label>
                            <input type="text" name="clinic_name" value="<?php echo htmlspecialchars($settings['clinic_name'] ?? 'VeeCare Medical Centre'); ?>" required style="width:100%; padding: 10px 12px; border:1px solid #ddd; border-radius: 10px;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">Logo Upload</label>
                            <input type="file" accept="image/*" style="width:100%; padding: 8px; border:1px solid #ddd; border-radius: 10px;">
                            <small style="color: #6c86a3;">JPEG, PNG, GIF (optional)</small>
                        </div>
                    </div>
                </div>

                <!-- Contact Details Card -->
                <div style="background: white; border-radius: 20px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
                    <div style="padding: 16px 20px; background: #f8fafc; border-bottom: 1px solid #eef2f6; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-address-card" style="color: #0A84FF; font-size: 18px;"></i>
                        <h2 style="font-size: 16px; font-weight: 700; margin: 0;">Contact Details</h2>
                    </div>
                    <div style="padding: 20px;">
                        <div style="margin-bottom: 16px;">
                            <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">Phone Number</label>
                            <input type="tel" name="clinic_phone" value="<?php echo htmlspecialchars($settings['clinic_phone'] ?? '+254791333577'); ?>" required style="width:100%; padding: 10px 12px; border:1px solid #ddd; border-radius: 10px;">
                        </div>
                        <div style="margin-bottom: 16px;">
                            <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">Email Address</label>
                            <input type="email" name="clinic_email" value="<?php echo htmlspecialchars($settings['clinic_email'] ?? 'info@veecare.com'); ?>" required style="width:100%; padding: 10px 12px; border:1px solid #ddd; border-radius: 10px;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">Physical Address</label>
                            <textarea name="clinic_address" rows="2" style="width:100%; padding: 10px 12px; border:1px solid #ddd; border-radius: 10px;"><?php echo htmlspecialchars($settings['clinic_address'] ?? 'PO BOX 4478 - 40200, KISII'); ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Operational Settings Card -->
                <div style="background: white; border-radius: 20px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
                    <div style="padding: 16px 20px; background: #f8fafc; border-bottom: 1px solid #eef2f6; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-clock" style="color: #0A84FF; font-size: 18px;"></i>
                        <h2 style="font-size: 16px; font-weight: 700; margin: 0;">Operational Settings</h2>
                    </div>
                    <div style="padding: 20px;">
                        <div style="margin-bottom: 16px;">
                            <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">Operating Hours</label>
                            <input type="text" name="operating_hours" value="<?php echo htmlspecialchars($settings['clinic_hours'] ?? 'Mon-Fri: 8:00 AM - 6:00 PM, Sat: 9:00 AM - 1:00 PM'); ?>" style="width:100%; padding: 10px 12px; border:1px solid #ddd; border-radius: 10px;">
                        </div>
                        <div style="margin-bottom: 16px;">
                            <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">Tax Rate (%)</label>
                            <input type="number" step="0.01" name="tax_rate" value="<?php echo htmlspecialchars($settings['tax_rate'] ?? '0.00'); ?>" style="width:100%; padding: 10px 12px; border:1px solid #ddd; border-radius: 10px;">
                        </div>
                        <div style="margin-bottom: 16px;">
                            <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">Currency Symbol</label>
                            <input type="text" name="currency" value="<?php echo htmlspecialchars($settings['currency_symbol'] ?? 'KSh'); ?>" style="width:100%; padding: 10px 12px; border:1px solid #ddd; border-radius: 10px;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">Timezone</label>
                            <select name="timezone" style="width:100%; padding: 10px 12px; border:1px solid #ddd; border-radius: 10px;">
                                <option value="Africa/Nairobi" <?php echo ($settings['timezone'] ?? 'Africa/Nairobi') == 'Africa/Nairobi' ? 'selected' : ''; ?>>Africa/Nairobi (EAT)</option>
                                <option value="UTC">UTC</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div style="text-align: right;">
                <button type="submit" style="background: #0A84FF; color: white; border: none; padding: 12px 28px; border-radius: 12px; cursor: pointer; font-weight: 600;"><i class="fas fa-save"></i> Save All Settings</button>
            </div>
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>