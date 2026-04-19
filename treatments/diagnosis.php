<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'doctor') {
    header("Location: login.php");
    exit();
}
require_once '../config/database.php';
$conn = getConnection();

$patients = [];
$res = $conn->query("SELECT id, full_name FROM patients ORDER BY full_name");
while($row = $res->fetch_assoc()) $patients[] = $row;

$success = false;
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patient_id = (int)$_POST['patient_id'];
    $condition_type = $_POST['condition_type'];
    $diagnosis = trim($_POST['diagnosis']);
    $symptoms = trim($_POST['symptoms']);
    $severity = $_POST['severity'] ?? null;
    $imaging_recommended = trim($_POST['imaging_recommended'] ?? '');
    $injury_details = trim($_POST['injury_details'] ?? '');
    $treatment_notes = trim($_POST['treatment_notes'] ?? '');
    
    $errors = [];
    if (!$patient_id) $errors[] = "Select patient.";
    if (!$condition_type) $errors[] = "Select condition type.";
    if (!$diagnosis) $errors[] = "Diagnosis required.";
    
    if (empty($errors)) {
        $sql = "INSERT INTO diagnosis (patient_id, doctor_id, diagnosis_date, condition_type, diagnosis, symptoms, severity, imaging_recommended, injury_details, treatment_notes) VALUES (?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iisssssss", $patient_id, $_SESSION['user_id'], $condition_type, $diagnosis, $symptoms, $severity, $imaging_recommended, $injury_details, $treatment_notes);
        if ($stmt->execute()) {
            $success = true;
        } else { $error = "DB error."; }
        $stmt->close();
    } else { $error = implode("<br>", $errors); }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Record Diagnosis | VeeCare</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Same base CSS as before */
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
        .btn-back { display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; background: #f0f2f5; color: #666; text-decoration: none; border-radius: 10px; font-weight: 500; transition: all 0.2s; }
        .btn-back:hover { background: #e0e2e5; transform: translateX(-2px); }
        .form-card { background: #fff; border-radius: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); overflow: hidden; }
        .form-section { padding: 24px; border-bottom: 1px solid #eee; }
        .section-title { font-size: 16px; font-weight: 700; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; color: #1a1a2e; }
        .section-title i { color: #0A84FF; }
        .form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
        .form-group.full-width { grid-column: span 2; }
        label { display: block; font-size: 12px; font-weight: 600; color: #666; margin-bottom: 6px; }
        label.required::after { content: '*'; color: #FF3B30; margin-left: 4px; }
        input, select, textarea { width: 100%; padding: 10px 14px; border: 1px solid #ddd; border-radius: 10px; font-size: 14px; font-family: 'Inter', sans-serif; }
        .dynamic-field { display: none; }
        .form-actions { padding: 20px 24px; background: #f8f9fa; display: flex; justify-content: flex-end; gap: 12px; }
        .btn-submit { padding: 12px 28px; background: linear-gradient(135deg, #0A84FF, #006EDB); color: #fff; border: none; border-radius: 10px; font-weight: 600; cursor: pointer; }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(10,132,255,0.3); }
        .alert { padding: 14px 20px; border-radius: 12px; margin: 20px 24px 0 24px; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: rgba(52,199,89,0.1); color: #34C759; border-left: 3px solid #34C759; }
        .alert-error { background: rgba(255,59,48,0.1); color: #FF3B30; border-left: 3px solid #FF3B30; }
        @media (max-width: 768px) { .sidebar { transform: translateX(-100%); } .main-content { margin-left: 0; } .form-grid { grid-template-columns: 1fr; } .form-group.full-width { grid-column: span 1; } }
    </style>
</head>
<body>
<aside class="sidebar">
    <div class="sidebar-header"><h2><i class="fas fa-heartbeat"></i> VeeCare</h2><p>Medical Centre</p></div>
    <nav class="sidebar-nav">
        <div class="nav-item"><a href="../dashboard.php" class="nav-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a></div>
        <div class="nav-divider"></div>
        <div class="nav-category">Treatments</div>
        <div class="nav-item"><a href="diagnosis.php" class="nav-link active"><i class="fas fa-stethoscope"></i> New Diagnosis</a></div>
        <div class="nav-item"><a href="procedures.php" class="nav-link"><i class="fas fa-syringe"></i> Procedures</a></div>
        <div class="nav-item"><a href="notes.php" class="nav-link"><i class="fas fa-notes-medical"></i> Clinical Notes</a></div>
        <div class="nav-divider"></div>
        <div class="nav-item"><a href="../logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
    </nav>
</aside>
<main class="main-content">
    <div class="page-header">
        <h1>Record Diagnosis</h1>
        <a href="../dashboard.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
    </div>
    <div class="form-card">
        <?php if ($success): ?><div class="alert alert-success">Diagnosis recorded successfully.</div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><?php echo $error; ?></div><?php endif; ?>
        <form method="POST">
            <div class="form-section">
                <div class="form-grid">
                    <div class="form-group"><label class="required">Patient</label><select name="patient_id" required><?php foreach($patients as $p): ?><option value="<?=$p['id']?>"><?=htmlspecialchars($p['full_name'])?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label class="required">Condition Type</label>
                        <select name="condition_type" id="condition_type" required onchange="toggleFields()">
                            <option value="">Select</option>
                            <option value="general">🔵 General / Infectious (e.g., malaria)</option>
                            <option value="chronic">🟡 Chronic (e.g., arthritis)</option>
                            <option value="trauma">🔴 Trauma (e.g., fracture)</option>
                        </select>
                    </div>
                    <div class="form-group full-width"><label class="required">Diagnosis</label><input type="text" name="diagnosis" required></div>
                    <div class="form-group full-width"><label>Symptoms</label><textarea name="symptoms" rows="3"></textarea></div>
                    <div class="form-group"><label>Severity</label><select name="severity"><option value="">Select</option><option>mild</option><option>moderate</option><option>severe</option><option>critical</option></select></div>
                    <div id="imaging_field" class="form-group" style="display:none;"><label>Imaging Recommended</label><input type="text" name="imaging_recommended" placeholder="e.g., X-ray, CT scan"></div>
                    <div id="injury_field" class="form-group full-width" style="display:none;"><label>Injury Details</label><textarea name="injury_details" rows="2"></textarea></div>
                    <div class="form-group full-width"><label>Treatment Notes</label><textarea name="treatment_notes" rows="3"></textarea></div>
                </div>
            </div>
            <div class="form-actions"><button type="submit" class="btn-submit">Save Diagnosis</button></div>
        </form>
    </div>
</main>
<script>
function toggleFields() {
    var type = document.getElementById('condition_type').value;
    var imaging = document.getElementById('imaging_field');
    var injury = document.getElementById('injury_field');
    imaging.style.display = (type === 'trauma' || type === 'general') ? 'block' : 'none';
    injury.style.display = (type === 'trauma') ? 'block' : 'none';
}
toggleFields();
</script>
</body>
</html>