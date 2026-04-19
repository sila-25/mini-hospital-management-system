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
<html>
<head>
    <title>Record Diagnosis | VeeCare</title>
    <!-- include same CSS as before -->
</head>
<body>
<!-- sidebar -->
<main class="main-content">
    <div class="page-header">
        <h1>Record Diagnosis</h1>
        <a href="../dashboard.php" class="btn-back">Back to Dashboard</a>
    </div>
    <div class="form-card">
        <?php if($success): ?><div class="alert alert-success">Diagnosis recorded.</div><?php endif; ?>
        <?php if($error): ?><div class="alert alert-error"><?php echo $error; ?></div><?php endif; ?>
        <form method="POST">
            <div class="form-section">
                <div class="form-grid">
                    <div class="form-group"><label>Patient</label><select name="patient_id" required><?php foreach($patients as $p): ?><option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['full_name']); ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label>Condition Type</label>
                        <select name="condition_type" id="condition_type" required onchange="toggleFields()">
                            <option value="">Select</option>
                            <option value="general">🔵 General / Infectious (e.g., malaria)</option>
                            <option value="chronic">🟡 Chronic (e.g., arthritis)</option>
                            <option value="trauma">🔴 Trauma (e.g., fracture)</option>
                        </select>
                    </div>
                    <div class="form-group full-width"><label>Diagnosis</label><input type="text" name="diagnosis" required></div>
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