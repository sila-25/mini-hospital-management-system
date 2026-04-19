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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Patient | VeeCare</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f0f2f5; }
        
        .sidebar {
            width: 260px;
            background: linear-gradient(180deg, #0f0f1a 0%, #1a1a2e 100%);
            color: #fff;
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            overflow-y: auto;
            z-index: 100;
        }
        .sidebar-header { padding: 24px 20px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-header h2 { font-size: 20px; display: flex; align-items: center; gap: 10px; }
        .sidebar-header h2 i { color: #0A84FF; }
        .sidebar-nav { padding: 20px; }
        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px;
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.2s;
            font-size: 14px;
        }
        .nav-link:hover, .nav-link.active { background: rgba(10,132,255,0.15); color: #0A84FF; }
        .nav-link i { width: 20px; }
        .nav-divider { height: 1px; background: rgba(255,255,255,0.08); margin: 12px 0; }
        .nav-category { font-size: 10px; text-transform: uppercase; color: rgba(255,255,255,0.4); padding: 8px 12px; margin-top: 8px; }
        
        .main-content { margin-left: 260px; padding: 24px; }
        
        .page-header {
            background: #fff;
            border-radius: 16px;
            padding: 20px 24px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .page-header h1 { font-size: 24px; font-weight: 700; }
        .page-header p { font-size: 13px; color: #666; margin-top: 4px; }
        
        .form-card {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        .form-section {
            padding: 24px;
            border-bottom: 1px solid #eee;
        }
        .section-title {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #1a1a2e;
        }
        .section-title i { color: #0A84FF; }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }
        .form-group { margin-bottom: 0; }
        .form-group.full-width { grid-column: span 3; }
        label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: #666;
            margin-bottom: 6px;
        }
        label.required::after { content: '*'; color: #FF3B30; margin-left: 4px; }
        input, select, textarea {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #ddd;
            border-radius: 10px;
            font-size: 14px;
            font-family: 'Inter', sans-serif;
            transition: all 0.2s;
        }
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #0A84FF;
            box-shadow: 0 0 0 3px rgba(10,132,255,0.1);
        }
        textarea { resize: vertical; min-height: 80px; }
        
        .form-actions {
            padding: 20px 24px;
            background: #f8f9fa;
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }
        .btn-save {
            padding: 12px 28px;
            background: linear-gradient(135deg, #0A84FF, #006EDB);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-save:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(10,132,255,0.3); }
        .btn-cancel {
            padding: 12px 28px;
            background: #f0f2f5;
            color: #666;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
        }
        
        .alert {
            padding: 14px 20px;
            border-radius: 12px;
            margin: 20px 24px 0 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-success { background: rgba(52,199,89,0.1); color: #34C759; border-left: 3px solid #34C759; }
        .alert-error { background: rgba(255,59,48,0.1); color: #FF3B30; border-left: 3px solid #FF3B30; }
        
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); position: fixed; }
            .main-content { margin-left: 0; }
            .form-grid { grid-template-columns: 1fr; }
            .form-group.full-width { grid-column: span 1; }
        }
    </style>
</head>
<body>
<aside class="sidebar">
    <div class="sidebar-header"><h2><i class="fas fa-heartbeat"></i> VeeCare</h2><p>Medical Centre</p></div>
    <nav class="sidebar-nav">
        <div class="nav-item"><a href="../dashboard.php" class="nav-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a></div>
        <div class="nav-divider"></div>
        <div class="nav-category">Patients</div>
        <div class="nav-item"><a href="view_patients.php" class="nav-link"><i class="fas fa-users"></i> All Patients</a></div>
        <div class="nav-item"><a href="add_patient.php" class="nav-link"><i class="fas fa-user-plus"></i> Add Patient</a></div>
        <div class="nav-divider"></div>
        <div class="nav-item"><a href="../logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
    </nav>
</aside>

<main class="main-content">
    <div class="page-header">
        <div><h1>Edit Patient</h1><p>Update patient information</p></div>
        <a href="patient_profile.php?id=<?php echo $patient['id']; ?>" class="btn-cancel"><i class="fas fa-arrow-left"></i> Back to Profile</a>
    </div>
    
    <div class="form-card">
        <?php if ($success): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> Patient information updated successfully!</div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-section">
                <div class="section-title"><i class="fas fa-user-circle"></i> Personal Information</div>
                <div class="form-grid">
                    <div class="form-group"><label class="required">Full Name</label><input type="text" name="full_name" value="<?php echo htmlspecialchars($patient['full_name']); ?>" required></div>
                    <div class="form-group"><label class="required">Date of Birth</label><input type="date" name="date_of_birth" value="<?php echo $patient['date_of_birth']; ?>" required></div>
                    <div class="form-group"><label class="required">Gender</label><select name="gender" required>
                        <option value="Male" <?php echo $patient['gender'] == 'Male' ? 'selected' : ''; ?>>Male</option>
                        <option value="Female" <?php echo $patient['gender'] == 'Female' ? 'selected' : ''; ?>>Female</option>
                        <option value="Other" <?php echo $patient['gender'] == 'Other' ? 'selected' : ''; ?>>Other</option>
                    </select></div>
                </div>
            </div>
            
            <div class="form-section">
                <div class="section-title"><i class="fas fa-address-card"></i> Contact Information</div>
                <div class="form-grid">
                    <div class="form-group"><label>Email</label><input type="email" name="email" value="<?php echo htmlspecialchars($patient['email']); ?>"></div>
                    <div class="form-group"><label class="required">Phone Number</label><input type="tel" name="phone" value="<?php echo htmlspecialchars($patient['phone']); ?>" required></div>
                    <div class="form-group full-width"><label>Address</label><textarea name="address" rows="2"><?php echo htmlspecialchars($patient['address']); ?></textarea></div>
                </div>
            </div>
            
            <div class="form-section">
                <div class="section-title"><i class="fas fa-ambulance"></i> Emergency Contact</div>
                <div class="form-grid">
                    <div class="form-group"><label>Emergency Contact Name</label><input type="text" name="emergency_contact" value="<?php echo htmlspecialchars($patient['emergency_contact_name']); ?>"></div>
                    <div class="form-group"><label>Emergency Contact Phone</label><input type="tel" name="emergency_phone" value="<?php echo htmlspecialchars($patient['emergency_contact_phone']); ?>"></div>
                    <div class="form-group"><label>Blood Group</label><select name="blood_group">
                        <option value="">Select</option>
                        <?php $groups = ['A+','A-','B+','B-','O+','O-','AB+','AB-']; foreach ($groups as $bg): ?>
                            <option value="<?php echo $bg; ?>" <?php echo $patient['blood_group'] == $bg ? 'selected' : ''; ?>><?php echo $bg; ?></option>
                        <?php endforeach; ?>
                    </select></div>
                </div>
            </div>
            
            <div class="form-section">
                <div class="section-title"><i class="fas fa-notes-medical"></i> Medical Information</div>
                <div class="form-grid">
                    <div class="form-group full-width"><label>Allergies</label><textarea name="allergies" rows="2"><?php echo htmlspecialchars($patient['allergies']); ?></textarea></div>
                    <div class="form-group full-width"><label>Medical History</label><textarea name="medical_history" rows="3"><?php echo htmlspecialchars($patient['medical_history']); ?></textarea></div>
                </div>
            </div>
            
            <div class="form-actions">
                <a href="patient_profile.php?id=<?php echo $patient['id']; ?>" class="btn-cancel">Cancel</a>
                <button type="submit" class="btn-save"><i class="fas fa-save"></i> Save Changes</button>
            </div>
        </form>
    </div>
</main>
</body>
</html>