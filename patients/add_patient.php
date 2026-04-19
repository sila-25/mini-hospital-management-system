<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
require_once '../config/database.php';
$conn = getConnection();

$success = false;
$error = '';
$new_patient_id = '';
$submitted = [
    'full_name' => '', 'date_of_birth' => '', 'gender' => '', 'email' => '', 'phone' => '', 'address' => '',
    'is_child' => 0, 'guardian_name' => '', 'guardian_phone' => '', 'guardian_email' => '', 'guardian_relationship' => '',
    'next_of_kin_name' => '', 'next_of_kin_relationship' => '', 'next_of_kin_phone' => '', 'next_of_kin_email' => '',
    'blood_group' => '', 'allergies' => '', 'medical_history' => '', 'condition_category' => '',
    'general_symptoms' => '', 'chronic_condition_name' => '', 'chronic_duration' => '', 'trauma_injury_type' => '', 'trauma_cause' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($submitted as $key => $val) {
        if (isset($_POST[$key])) $submitted[$key] = trim($_POST[$key]);
    }
    $submitted['is_child'] = isset($_POST['is_child']) ? 1 : 0;
    
    $errors = [];
    if (empty($submitted['full_name'])) $errors[] = 'Full name required.';
    if (empty($submitted['date_of_birth'])) $errors[] = 'Date of birth required.';
    if (empty($submitted['gender'])) $errors[] = 'Gender required.';
    if ($submitted['is_child'] == 0 && empty($submitted['phone'])) $errors[] = 'Phone number required.';
    if (empty($submitted['condition_category'])) $errors[] = 'Please select a condition category.';
    
    // Duplicate check
    $check = $conn->prepare("SELECT id FROM patients WHERE full_name = ? AND date_of_birth = ?");
    $check->bind_param("ss", $submitted['full_name'], $submitted['date_of_birth']);
    $check->execute();
    if ($check->get_result()->num_rows > 0) $errors[] = 'Patient already exists.';
    $check->close();
    
    if (empty($errors)) {
        // Generate patient ID
        $year = date('Y'); $month = date('m');
        $res = $conn->query("SELECT patient_id FROM patients WHERE patient_id LIKE 'PAT{$year}{$month}%' ORDER BY id DESC LIMIT 1");
        if ($res && $row = $res->fetch_assoc()) {
            $lastNum = intval(substr($row['patient_id'], -4));
            $newNum = str_pad($lastNum + 1, 4, '0', STR_PAD_LEFT);
        } else { $newNum = '0001'; }
        $auto_patient_id = "PAT{$year}{$month}{$newNum}";
        
        // FIXED: Correct SQL with proper column count and order
        $sql = "INSERT INTO patients (
            patient_id, full_name, date_of_birth, gender, email, phone, address, 
            next_of_kin_name, next_of_kin_relationship, next_of_kin_phone, next_of_kin_email,
            is_child, guardian_name, guardian_phone, guardian_email, guardian_relationship,
            blood_group, allergies, medical_history, condition_category, created_by, created_at
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, NOW())";
        
        $stmt = $conn->prepare($sql);
        
        // FIXED: Assign variables to avoid the bind_param reference error
        $patient_id_val = $auto_patient_id;
        $full_name_val = $submitted['full_name'];
        $date_of_birth_val = $submitted['date_of_birth'];
        $gender_val = $submitted['gender'];
        $email_val = $submitted['email'];
        $phone_val = $submitted['phone'];
        $address_val = $submitted['address'];
        $next_of_kin_name_val = $submitted['next_of_kin_name'];
        $next_of_kin_relationship_val = $submitted['next_of_kin_relationship'];
        $next_of_kin_phone_val = $submitted['next_of_kin_phone'];
        $next_of_kin_email_val = $submitted['next_of_kin_email'];
        $is_child_val = $submitted['is_child'];
        $guardian_name_val = $submitted['guardian_name'];
        $guardian_phone_val = $submitted['guardian_phone'];
        $guardian_email_val = $submitted['guardian_email'];
        $guardian_relationship_val = $submitted['guardian_relationship'];
        $blood_group_val = $submitted['blood_group'];
        $allergies_val = $submitted['allergies'];
        $medical_history_val = $submitted['medical_history'];
        $condition_category_val = $submitted['condition_category'];
        $created_by_val = $_SESSION['user_id'];
        
        // FIXED: Correct bind_param with 21 variables (20 fields + created_by)
        $stmt->bind_param(
            "sssssssssssissssssssi",
            $patient_id_val, $full_name_val, $date_of_birth_val, $gender_val,
            $email_val, $phone_val, $address_val,
            $next_of_kin_name_val, $next_of_kin_relationship_val, $next_of_kin_phone_val, $next_of_kin_email_val,
            $is_child_val, $guardian_name_val, $guardian_phone_val, $guardian_email_val, $guardian_relationship_val,
            $blood_group_val, $allergies_val, $medical_history_val, $condition_category_val, $created_by_val
        );
        
        if ($stmt->execute()) {
            $success = true;
            $new_patient_id = $auto_patient_id;
            
            // Get the auto-increment ID for diagnosis table
            $patient_db_id = $stmt->insert_id;
            
            // Store dynamic condition data in diagnosis table
            if (!empty($submitted['condition_category'])) {
                $diagnosis_text = '';
                $symptoms_text = '';
                
                switch($submitted['condition_category']) {
                    case 'general':
                        $diagnosis_text = 'General Illness';
                        $symptoms_text = $submitted['general_symptoms'];
                        break;
                    case 'chronic':
                        $diagnosis_text = 'Chronic: ' . $submitted['chronic_condition_name'] . ' (' . $submitted['chronic_duration'] . ')';
                        $symptoms_text = $submitted['general_symptoms'];
                        break;
                    case 'trauma':
                        $diagnosis_text = 'Trauma/Injury: ' . $submitted['trauma_injury_type'] . ' - Cause: ' . $submitted['trauma_cause'];
                        $symptoms_text = $submitted['general_symptoms'];
                        break;
                }
                
                $diag_sql = "INSERT INTO diagnosis (patient_id, doctor_id, diagnosis_date, condition_type, diagnosis, symptoms) VALUES (?, ?, CURDATE(), ?, ?, ?)";
                $diag_stmt = $conn->prepare($diag_sql);
                $diag_stmt->bind_param("iisss", $patient_db_id, $_SESSION['user_id'], $submitted['condition_category'], $diagnosis_text, $symptoms_text);
                $diag_stmt->execute();
                $diag_stmt->close();
            }
            
            $submitted = array_fill_keys(array_keys($submitted), '');
        } else { 
            $error = 'DB error: ' . $conn->error; 
        }
        $stmt->close();
    } else { 
        $error = implode('<br>', $errors); 
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Patient | VeeCare</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f0f2f5; }
        .sidebar { width: 260px; background: linear-gradient(180deg, #0f0f1a 0%, #1a1a2e 100%); color: #fff; position: fixed; left: 0; top: 0; height: 100vh; overflow-y: auto; z-index: 100; }
        .sidebar-header { padding: 24px 20px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-header h2 { font-size: 20px; display: flex; align-items: center; gap: 10px; }
        .sidebar-header h2 i { color: #0A84FF; }
        .sidebar-header p { font-size: 11px; color: rgba(255,255,255,0.5); margin-top: 5px; }
        .sidebar-nav { padding: 20px; }
        .nav-link { display: flex; align-items: center; gap: 12px; padding: 10px 12px; color: rgba(255,255,255,0.7); text-decoration: none; border-radius: 8px; transition: all 0.2s; font-size: 14px; }
        .nav-link:hover, .nav-link.active { background: rgba(10,132,255,0.15); color: #0A84FF; }
        .nav-link i { width: 20px; }
        .nav-divider { height: 1px; background: rgba(255,255,255,0.08); margin: 12px 0; }
        .nav-category { font-size: 10px; text-transform: uppercase; color: rgba(255,255,255,0.4); padding: 8px 12px; margin-top: 8px; }
        .main-content { margin-left: 260px; padding: 24px; }
        .page-header { background: #fff; border-radius: 16px; padding: 20px 24px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .page-header h1 { font-size: 24px; font-weight: 700; }
        .page-header p { font-size: 13px; color: #666; margin-top: 4px; }
        .btn-back { display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; background: #f0f2f5; color: #666; text-decoration: none; border-radius: 10px; font-weight: 500; transition: all 0.2s; }
        .btn-back:hover { background: #e0e2e5; transform: translateX(-2px); }
        .form-card { background: #fff; border-radius: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); overflow: hidden; }
        .form-section { padding: 24px; border-bottom: 1px solid #eee; }
        .section-title { font-size: 16px; font-weight: 700; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; color: #1a1a2e; }
        .section-title i { color: #0A84FF; font-size: 18px; }
        .form-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
        .form-group.full-width { grid-column: span 3; }
        label { display: block; font-size: 12px; font-weight: 600; color: #666; margin-bottom: 6px; }
        label.required::after { content: '*'; color: #FF3B30; margin-left: 4px; }
        input, select, textarea { width: 100%; padding: 10px 14px; border: 1px solid #ddd; border-radius: 10px; font-size: 14px; font-family: 'Inter', sans-serif; transition: all 0.2s; }
        input:focus, select:focus, textarea:focus { outline: none; border-color: #0A84FF; box-shadow: 0 0 0 3px rgba(10,132,255,0.1); }
        .dynamic-section { display: none; margin-top: 20px; animation: fadeIn 0.3s ease; }
        .dynamic-section.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        .form-actions { padding: 20px 24px; background: #f8f9fa; display: flex; gap: 12px; justify-content: flex-end; }
        .btn-submit { padding: 12px 28px; background: linear-gradient(135deg, #0A84FF, #006EDB); color: #fff; border: none; border-radius: 10px; font-weight: 600; cursor: pointer; transition: all 0.2s; }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(10,132,255,0.3); }
        .btn-cancel { padding: 12px 28px; background: #f0f2f5; color: #666; border: none; border-radius: 10px; font-weight: 600; cursor: pointer; text-decoration: none; }
        .alert { padding: 14px 20px; border-radius: 12px; margin: 20px 24px 0 24px; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: rgba(52,199,89,0.1); color: #34C759; border-left: 3px solid #34C759; }
        .alert-error { background: rgba(255,59,48,0.1); color: #FF3B30; border-left: 3px solid #FF3B30; }
        @media (max-width: 768px) { .sidebar { transform: translateX(-100%); position: fixed; } .main-content { margin-left: 0; } .form-grid { grid-template-columns: 1fr; } .form-group.full-width { grid-column: span 1; } }
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
        <div class="nav-item"><a href="add_patient.php" class="nav-link active"><i class="fas fa-user-plus"></i> Add Patient</a></div>
        <div class="nav-divider"></div>
        <div class="nav-item"><a href="../logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
    </nav>
</aside>
<main class="main-content">
    <div class="page-header">
        <div><h1>Add New Patient</h1><p>Register patient with condition category</p></div>
        <a href="view_patients.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Patients</a>
    </div>
    <div class="form-card">
        <?php if ($success): ?>
            <div class="alert alert-success">Patient registered! ID: <?php echo $new_patient_id; ?> <a href="patient_profile.php?id=<?php echo $new_patient_id; ?>">View Profile →</a></div>
        <?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><?php echo $error; ?></div><?php endif; ?>
        <form method="POST">
            <!-- Patient Type (Adult/Child) -->
            <div class="form-section">
                <div class="section-title"><i class="fas fa-user-tag"></i> Patient Type</div>
                <div class="patient-type-toggle" style="display:flex; gap:20px; margin-bottom:20px;">
                    <label><input type="radio" name="is_child" value="0" <?php echo ($submitted['is_child']==0)?'checked':''; ?> onchange="toggleChildFields()"> Adult</label>
                    <label><input type="radio" name="is_child" value="1" <?php echo ($submitted['is_child']==1)?'checked':''; ?> onchange="toggleChildFields()"> Child</label>
                </div>
            </div>
            <!-- Personal Information -->
            <div class="form-section">
                <div class="section-title"><i class="fas fa-user-circle"></i> Personal Information</div>
                <div class="form-grid">
                    <div class="form-group full-width"><label class="required">Full Name</label><input type="text" name="full_name" value="<?php echo htmlspecialchars($submitted['full_name']); ?>" required></div>
                    <div class="form-group"><label class="required">Date of Birth</label><input type="date" name="date_of_birth" value="<?php echo htmlspecialchars($submitted['date_of_birth']); ?>" required></div>
                    <div class="form-group"><label class="required">Gender</label><select name="gender" required><option value="">Select</option><option <?php echo ($submitted['gender']=='Male')?'selected':''; ?>>Male</option><option <?php echo ($submitted['gender']=='Female')?'selected':''; ?>>Female</option><option <?php echo ($submitted['gender']=='Other')?'selected':''; ?>>Other</option></select></div>
                    <div class="form-group"><label>Blood Group</label><select name="blood_group"><option value="">Select</option><?php $bg=['A+','A-','B+','B-','O+','O-','AB+','AB-']; foreach($bg as $b){ echo "<option ".($submitted['blood_group']==$b?'selected':'').">$b</option>"; } ?></select></div>
                </div>
            </div>
            <!-- Contact Information (Adult/Child) -->
            <div id="adultContact" style="<?php echo ($submitted['is_child']==0)?'display:block;':'display:none;'; ?>">
                <div class="form-section"><div class="section-title"><i class="fas fa-address-card"></i> Contact Information</div>
                <div class="form-grid"><div class="form-group"><label>Email</label><input type="email" name="email" value="<?php echo htmlspecialchars($submitted['email']); ?>"></div>
                <div class="form-group"><label class="required">Phone</label><input type="tel" name="phone" value="<?php echo htmlspecialchars($submitted['phone']); ?>"></div>
                <div class="form-group full-width"><label>Address</label><textarea name="address"><?php echo htmlspecialchars($submitted['address']); ?></textarea></div></div></div>
            </div>
            <div id="childContact" style="<?php echo ($submitted['is_child']==1)?'display:block;':'display:none;'; ?>">
                <div class="form-section"><div class="section-title"><i class="fas fa-address-card"></i> Contact Information (Optional)</div>
                <div class="form-grid"><div class="form-group"><label>Email</label><input type="email" name="email" value="<?php echo htmlspecialchars($submitted['email']); ?>"></div>
                <div class="form-group"><label>Phone</label><input type="tel" name="phone" value="<?php echo htmlspecialchars($submitted['phone']); ?>"></div>
                <div class="form-group full-width"><label>Address</label><textarea name="address"><?php echo htmlspecialchars($submitted['address']); ?></textarea></div></div></div>
            </div>
            <!-- Guardian (for children) -->
            <div id="guardianSection" style="<?php echo ($submitted['is_child']==1)?'display:block;':'display:none;'; ?>">
                <div class="form-section"><div class="section-title"><i class="fas fa-user-shield"></i> Guardian Information</div>
                <div class="form-grid"><div class="form-group"><label>Guardian Name</label><input type="text" name="guardian_name" value="<?php echo htmlspecialchars($submitted['guardian_name']); ?>"></div>
                <div class="form-group"><label>Guardian Phone</label><input type="tel" name="guardian_phone" value="<?php echo htmlspecialchars($submitted['guardian_phone']); ?>"></div>
                <div class="form-group"><label>Guardian Email</label><input type="email" name="guardian_email" value="<?php echo htmlspecialchars($submitted['guardian_email']); ?>"></div>
                <div class="form-group"><label>Relationship</label><select name="guardian_relationship"><option value="">Select</option><option <?php echo ($submitted['guardian_relationship']=='Father')?'selected':''; ?>>Father</option><option <?php echo ($submitted['guardian_relationship']=='Mother')?'selected':''; ?>>Mother</option><option <?php echo ($submitted['guardian_relationship']=='Legal Guardian')?'selected':''; ?>>Legal Guardian</option></select></div></div></div>
            </div>
            <!-- Next of Kin (for adults) -->
            <div id="nextOfKinSection" style="<?php echo ($submitted['is_child']==0)?'display:block;':'display:none;'; ?>">
                <div class="form-section"><div class="section-title"><i class="fas fa-ambulance"></i> Next of Kin</div>
                <div class="form-grid"><div class="form-group"><label>Name</label><input type="text" name="next_of_kin_name" value="<?php echo htmlspecialchars($submitted['next_of_kin_name']); ?>"></div>
                <div class="form-group"><label>Relationship</label><input type="text" name="next_of_kin_relationship" value="<?php echo htmlspecialchars($submitted['next_of_kin_relationship']); ?>"></div>
                <div class="form-group"><label>Phone</label><input type="tel" name="next_of_kin_phone" value="<?php echo htmlspecialchars($submitted['next_of_kin_phone']); ?>"></div>
                <div class="form-group"><label>Email</label><input type="email" name="next_of_kin_email" value="<?php echo htmlspecialchars($submitted['next_of_kin_email']); ?>"></div></div></div>
            </div>
            <!-- Condition Category -->
            <div class="form-section">
                <div class="section-title"><i class="fas fa-stethoscope"></i> Condition Category</div>
                <div class="form-grid">
                    <div class="form-group"><label class="required">Primary Condition</label>
                        <select name="condition_category" id="condition_category" required onchange="toggleConditionFields()">
                            <option value="">Select</option>
                            <option value="general" <?php echo ($submitted['condition_category']=='general')?'selected':''; ?>>🔵 General Illness (e.g., malaria, flu)</option>
                            <option value="chronic" <?php echo ($submitted['condition_category']=='chronic')?'selected':''; ?>>🟡 Chronic Disease (e.g., arthritis, diabetes)</option>
                            <option value="trauma" <?php echo ($submitted['condition_category']=='trauma')?'selected':''; ?>>🔴 Trauma / Injury</option>
                        </select>
                    </div>
                </div>
                <div id="general_fields" class="dynamic-section">
                    <div class="form-group full-width"><label>Symptoms (e.g., fever, headache)</label><textarea name="general_symptoms" rows="2"><?php echo htmlspecialchars($submitted['general_symptoms']); ?></textarea></div>
                </div>
                <div id="chronic_fields" class="dynamic-section">
                    <div class="form-group"><label>Chronic Condition Name</label><input type="text" name="chronic_condition_name" value="<?php echo htmlspecialchars($submitted['chronic_condition_name']); ?>"></div>
                    <div class="form-group"><label>Duration (e.g., 5 years)</label><input type="text" name="chronic_duration" value="<?php echo htmlspecialchars($submitted['chronic_duration']); ?>"></div>
                </div>
                <div id="trauma_fields" class="dynamic-section">
                    <div class="form-group"><label>Injury Type</label><input type="text" name="trauma_injury_type" value="<?php echo htmlspecialchars($submitted['trauma_injury_type']); ?>"></div>
                    <div class="form-group"><label>Cause of Injury</label><input type="text" name="trauma_cause" value="<?php echo htmlspecialchars($submitted['trauma_cause']); ?>"></div>
                </div>
            </div>
            <!-- Medical History -->
            <div class="form-section">
                <div class="section-title"><i class="fas fa-notes-medical"></i> Medical Information</div>
                <div class="form-grid">
                    <div class="form-group full-width"><label>Allergies</label><textarea name="allergies"><?php echo htmlspecialchars($submitted['allergies']); ?></textarea></div>
                    <div class="form-group full-width"><label>Past Medical History</label><textarea name="medical_history"><?php echo htmlspecialchars($submitted['medical_history']); ?></textarea></div>
                </div>
            </div>
            <div class="form-actions">
                <a href="view_patients.php" class="btn-cancel">Cancel</a>
                <button type="submit" class="btn-submit">Register Patient</button>
            </div>
        </form>
    </div>
</main>
<script>
function toggleChildFields() {
    var radios = document.querySelectorAll('input[name="is_child"]');
    var isChild = false;
    for(var i=0; i<radios.length; i++) {
        if(radios[i].checked && radios[i].value == '1') {
            isChild = true;
            break;
        }
    }
    document.getElementById('adultContact').style.display = isChild ? 'none' : 'block';
    document.getElementById('childContact').style.display = isChild ? 'block' : 'none';
    document.getElementById('guardianSection').style.display = isChild ? 'block' : 'none';
    document.getElementById('nextOfKinSection').style.display = isChild ? 'none' : 'block';
}
function toggleConditionFields() {
    var val = document.getElementById('condition_category').value;
    document.getElementById('general_fields').classList.remove('active');
    document.getElementById('chronic_fields').classList.remove('active');
    document.getElementById('trauma_fields').classList.remove('active');
    if (val === 'general') document.getElementById('general_fields').classList.add('active');
    else if (val === 'chronic') document.getElementById('chronic_fields').classList.add('active');
    else if (val === 'trauma') document.getElementById('trauma_fields').classList.add('active');
}
// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    toggleChildFields();
    toggleConditionFields();
});
</script>
</body>
</html>