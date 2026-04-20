<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once '../config/database.php';
$conn = getConnection();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$patient = null;

$sql = "SELECT * FROM patients WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$patient) {
    header("Location: view_patients.php");
    exit();
}

$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $date_of_birth = $_POST['date_of_birth'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $emergency_contact = trim($_POST['emergency_contact'] ?? '');
    $emergency_phone = trim($_POST['emergency_phone'] ?? '');
    $blood_group = $_POST['blood_group'] ?? '';
    $allergies = trim($_POST['allergies'] ?? '');
    $medical_history = trim($_POST['medical_history'] ?? '');
    
    if (empty($full_name) || empty($date_of_birth) || empty($gender) || empty($phone)) {
        $error = 'Please fill in all required fields.';
    } else {
        $sql = "UPDATE patients SET full_name=?, date_of_birth=?, gender=?, email=?, phone=?, address=?, emergency_contact_name=?, emergency_contact_phone=?, blood_group=?, allergies=?, medical_history=?, updated_at=NOW() WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssssssssi", $full_name, $date_of_birth, $gender, $email, $phone, $address, $emergency_contact, $emergency_phone, $blood_group, $allergies, $medical_history, $id);
        
        if ($stmt->execute()) {
            $success = true;
            // Refresh patient data
            $stmt2 = $conn->prepare("SELECT * FROM patients WHERE id = ?");
            $stmt2->bind_param("i", $id);
            $stmt2->execute();
            $patient = $stmt2->get_result()->fetch_assoc();
            $stmt2->close();
        } else {
            $error = 'Error updating patient: ' . $conn->error;
        }
        $stmt->close();
    }
}

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<main class="main-content" style="margin-left: 280px; padding: 24px; background: #f5f7fa; min-height: 100vh;">
    <div style="max-width: 1200px; margin: 0 auto;">
        <div style="background: white; border-radius: 20px; padding: 20px 28px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
            <div>
                <h1 style="margin: 0; font-size: 24px;">Edit Patient</h1>
                <p style="margin: 4px 0 0; color: #6c86a3;">Update patient information</p>
            </div>
            <a href="patient_profile.php?id=<?php echo $patient['id']; ?>" style="display: inline-flex; align-items: center; gap: 8px; background: #f0f2f5; padding: 8px 20px; border-radius: 12px; text-decoration: none; color: #4a627a;"><i class="fas fa-arrow-left"></i> Back to Profile</a>
        </div>

        <?php if ($success): ?>
            <div style="background: #d4edda; color: #155724; padding: 14px 20px; border-radius: 12px; margin-bottom: 24px; border-left: 4px solid #28a745;"> Patient information updated successfully!</div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div style="background: #f8d7da; color: #721c24; padding: 14px 20px; border-radius: 12px; margin-bottom: 24px; border-left: 4px solid #dc3545;"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" style="background: white; border-radius: 24px; padding: 28px; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
            <!-- Personal Information -->
            <div style="margin-bottom: 28px;">
                <h2 style="font-size: 18px; margin-bottom: 20px; display: flex; align-items: center; gap: 8px;"><i class="fas fa-user-circle" style="color: #0A84FF;"></i> Personal Information</h2>
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
                    <div><label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">Full Name <span style="color:#FF3B30;">*</span></label><input type="text" name="full_name" value="<?php echo htmlspecialchars($patient['full_name'] ?? ''); ?>" required style="width:100%; padding: 10px 12px; border:1px solid #ddd; border-radius: 10px;"></div>
                    <div><label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">Date of Birth <span style="color:#FF3B30;">*</span></label><input type="date" name="date_of_birth" value="<?php echo htmlspecialchars($patient['date_of_birth'] ?? ''); ?>" required style="width:100%; padding: 10px 12px; border:1px solid #ddd; border-radius: 10px;"></div>
                    <div><label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">Gender <span style="color:#FF3B30;">*</span></label>
                        <select name="gender" required style="width:100%; padding: 10px 12px; border:1px solid #ddd; border-radius: 10px;">
                            <option value="Male" <?php echo ($patient['gender'] ?? '') == 'Male' ? 'selected' : ''; ?>>Male</option>
                            <option value="Female" <?php echo ($patient['gender'] ?? '') == 'Female' ? 'selected' : ''; ?>>Female</option>
                            <option value="Other" <?php echo ($patient['gender'] ?? '') == 'Other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Contact Information -->
            <div style="margin-bottom: 28px;">
                <h2 style="font-size: 18px; margin-bottom: 20px; display: flex; align-items: center; gap: 8px;"><i class="fas fa-address-card" style="color: #0A84FF;"></i> Contact Information</h2>
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
                    <div><label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">Email</label><input type="email" name="email" value="<?php echo htmlspecialchars($patient['email'] ?? ''); ?>" style="width:100%; padding: 10px 12px; border:1px solid #ddd; border-radius: 10px;"></div>
                    <div><label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">Phone Number <span style="color:#FF3B30;">*</span></label><input type="tel" name="phone" value="<?php echo htmlspecialchars($patient['phone'] ?? ''); ?>" required style="width:100%; padding: 10px 12px; border:1px solid #ddd; border-radius: 10px;"></div>
                    <div style="grid-column: span 3;"><label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">Address</label><textarea name="address" rows="2" style="width:100%; padding: 10px 12px; border:1px solid #ddd; border-radius: 10px;"><?php echo htmlspecialchars($patient['address'] ?? ''); ?></textarea></div>
                </div>
            </div>

            <!-- Emergency Contact -->
            <div style="margin-bottom: 28px;">
                <h2 style="font-size: 18px; margin-bottom: 20px; display: flex; align-items: center; gap: 8px;"><i class="fas fa-ambulance" style="color: #0A84FF;"></i> Emergency Contact</h2>
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
                    <div><label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">Emergency Contact Name</label><input type="text" name="emergency_contact" value="<?php echo htmlspecialchars($patient['emergency_contact_name'] ?? ''); ?>" style="width:100%; padding: 10px 12px; border:1px solid #ddd; border-radius: 10px;"></div>
                    <div><label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">Emergency Contact Phone</label><input type="tel" name="emergency_phone" value="<?php echo htmlspecialchars($patient['emergency_contact_phone'] ?? ''); ?>" style="width:100%; padding: 10px 12px; border:1px solid #ddd; border-radius: 10px;"></div>
                    <div><label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">Blood Group</label>
                        <select name="blood_group" style="width:100%; padding: 10px 12px; border:1px solid #ddd; border-radius: 10px;">
                            <option value="">Select</option>
                            <?php $groups = ['A+','A-','B+','B-','O+','O-','AB+','AB-']; foreach ($groups as $bg): ?>
                                <option value="<?php echo $bg; ?>" <?php echo ($patient['blood_group'] ?? '') == $bg ? 'selected' : ''; ?>><?php echo $bg; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Medical Information -->
            <div style="margin-bottom: 28px;">
                <h2 style="font-size: 18px; margin-bottom: 20px; display: flex; align-items: center; gap: 8px;"><i class="fas fa-notes-medical" style="color: #0A84FF;"></i> Medical Information</h2>
                <div style="display: grid; grid-template-columns: 1fr; gap: 20px;">
                    <div><label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">Allergies</label><textarea name="allergies" rows="2" style="width:100%; padding: 10px 12px; border:1px solid #ddd; border-radius: 10px;"><?php echo htmlspecialchars($patient['allergies'] ?? ''); ?></textarea></div>
                    <div><label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">Medical History</label><textarea name="medical_history" rows="3" style="width:100%; padding: 10px 12px; border:1px solid #ddd; border-radius: 10px;"><?php echo htmlspecialchars($patient['medical_history'] ?? ''); ?></textarea></div>
                </div>
            </div>

            <div style="text-align: right; margin-top: 24px;">
                <a href="patient_profile.php?id=<?php echo $patient['id']; ?>" style="background: #f0f2f5; color: #4a627a; padding: 10px 20px; border-radius: 12px; text-decoration: none; margin-right: 12px;">Cancel</a>
                <button type="submit" style="background: #0A84FF; color: white; border: none; padding: 10px 24px; border-radius: 12px; cursor: pointer;"><i class="fas fa-save"></i> Save Changes</button>
            </div>
        </form>
    </div>
</main>

<?php include '../includes/footer.php'; ?>