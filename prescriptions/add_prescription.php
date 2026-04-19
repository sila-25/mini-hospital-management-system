<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
require_once '../config/database.php';
$conn = getConnection();

// Fetch patients
$patients = [];
$res = $conn->query("SELECT id, full_name, patient_id FROM patients ORDER BY full_name");
while ($row = $res->fetch_assoc()) $patients[] = $row;

// Fetch doctors
$doctors = [];
$res = $conn->query("SELECT id, full_name FROM users WHERE role = 'doctor' ORDER BY full_name");
while ($row = $res->fetch_assoc()) $doctors[] = $row;

// Fetch recent diagnoses for linking (optional)
$diagnoses = [];
$res = $conn->query("SELECT d.id, d.diagnosis, d.condition_type, p.full_name as patient_name 
                     FROM diagnosis d JOIN patients p ON d.patient_id = p.id 
                     ORDER BY d.diagnosis_date DESC LIMIT 20");
while ($row = $res->fetch_assoc()) $diagnoses[] = $row;

$success = false;
$error = '';
$medications = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patient_id = (int)$_POST['patient_id'];
    $doctor_id = (int)$_POST['doctor_id'];
    $prescription_date = $_POST['prescription_date'];
    $diagnosis_text = trim($_POST['diagnosis_text']);
    $condition_category = $_POST['condition_category'];
    $treatment_category = $_POST['treatment_category'] ?? null;
    $notes = trim($_POST['notes']);
    
    $long_term_plan = trim($_POST['long_term_plan'] ?? '');
    $follow_up_notes = trim($_POST['follow_up_notes'] ?? '');
    $post_procedure_meds = trim($_POST['post_procedure_meds'] ?? '');
    $pain_management_plan = trim($_POST['pain_management_plan'] ?? '');
    $imaging_references = trim($_POST['imaging_references'] ?? '');
    $procedure_references = trim($_POST['procedure_references'] ?? '');
    
    $med_names = $_POST['med_name'] ?? [];
    $dosages = $_POST['dosage'] ?? [];
    $frequencies = $_POST['frequency'] ?? [];
    $durations = $_POST['duration'] ?? [];
    $quantities = $_POST['quantity'] ?? [];
    $instructions = $_POST['instructions'] ?? [];
    
    $errors = [];
    if (!$patient_id) $errors[] = "Please select a patient.";
    if (!$doctor_id) $errors[] = "Please select a doctor.";
    if (!$prescription_date) $errors[] = "Please select a date.";
    if (empty($diagnosis_text)) $errors[] = "Diagnosis is required.";
    if (!$condition_category) $errors[] = "Please select a condition category.";
    
    $has_med = false;
    foreach ($med_names as $mn) {
        if (!empty(trim($mn))) { $has_med = true; break; }
    }
    if (!$has_med) $errors[] = "Please add at least one medication.";
    
    if (empty($errors)) {
        // Insert prescription
        $sql = "INSERT INTO prescriptions (patient_id, doctor_id, prescription_date, diagnosis, treatment_category, notes, status, created_by, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, 'active', ?, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iissssi", $patient_id, $doctor_id, $prescription_date, $diagnosis_text, $treatment_category, $notes, $_SESSION['user_id']);
        if ($stmt->execute()) {
            $prescription_id = $stmt->insert_id;
            // Insert medication items
            $item_sql = "INSERT INTO prescription_items (prescription_id, medication_name, dosage, frequency, duration, quantity, instructions) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $item_stmt = $conn->prepare($item_sql);
            for ($i = 0; $i < count($med_names); $i++) {
                if (empty(trim($med_names[$i]))) continue;
                $item_stmt->bind_param("issssis", $prescription_id, $med_names[$i], $dosages[$i], $frequencies[$i], $durations[$i], $quantities[$i], $instructions[$i]);
                $item_stmt->execute();
            }
            $item_stmt->close();
            $success = true;
            $medications = [];
        } else {
            $error = "Database error: " . $conn->error;
        }
        $stmt->close();
    } else {
        $error = implode("<br>", $errors);
        // Keep submitted medications for redisplay
        for ($i = 0; $i < count($med_names); $i++) {
            if (!empty(trim($med_names[$i]))) {
                $medications[] = [
                    'name' => htmlspecialchars($med_names[$i]),
                    'dosage' => htmlspecialchars($dosages[$i]),
                    'frequency' => htmlspecialchars($frequencies[$i]),
                    'duration' => htmlspecialchars($durations[$i]),
                    'quantity' => htmlspecialchars($quantities[$i]),
                    'instructions' => htmlspecialchars($instructions[$i])
                ];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Prescription | VeeCare</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* CSS same as before (include all styles) */
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
        .sidebar-header p { font-size: 11px; color: rgba(255,255,255,0.5); margin-top: 5px; }
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
            border-radius: 20px;
            padding: 20px 28px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .page-header h1 { font-size: 24px; font-weight: 700; color: #1a1a2e; }
        .page-header p { font-size: 13px; color: #5a6e8a; margin-top: 4px; }
        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: #f0f2f5;
            color: #4a627a;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 500;
            transition: all 0.2s;
        }
        .btn-back:hover { background: #e4e8ef; transform: translateX(-2px); }
        .form-card {
            background: #fff;
            border-radius: 24px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            overflow: hidden;
            margin-bottom: 24px;
        }
        .form-section { padding: 24px 28px; border-bottom: 1px solid #eef2f6; }
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
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        .form-group.full-width { grid-column: span 2; }
        label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: #4a627a;
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
        .dynamic-section { display: none; margin-top: 20px; animation: fadeIn 0.3s ease; }
        .dynamic-section.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        .medication-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1.5fr 1fr 0.8fr 2fr 0.5fr;
            gap: 12px;
            margin-bottom: 12px;
            align-items: center;
        }
        .medication-header {
            display: grid;
            grid-template-columns: 2fr 1fr 1.5fr 1fr 0.8fr 2fr 0.5fr;
            gap: 12px;
            margin-bottom: 8px;
            font-size: 11px;
            font-weight: 700;
            color: #6c86a3;
            padding: 0 5px;
        }
        .btn-icon {
            background: none;
            border: none;
            font-size: 18px;
            cursor: pointer;
            color: #FF3B30;
            transition: all 0.2s;
        }
        .btn-icon:hover { transform: scale(1.1); }
        .btn-add-med {
            background: #0A84FF;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 12px;
        }
        .form-actions {
            padding: 20px 28px;
            background: #fafcff;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }
        .btn-submit {
            padding: 12px 28px;
            background: linear-gradient(135deg, #0A84FF, #006EDB);
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
        }
        .btn-cancel {
            padding: 12px 28px;
            background: #f0f2f5;
            color: #666;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
        }
        .alert {
            padding: 14px 20px;
            border-radius: 12px;
            margin: 0 28px 20px 28px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-success { background: rgba(52,199,89,0.1); color: #34C759; border-left: 3px solid #34C759; }
        .alert-error { background: rgba(255,59,48,0.1); color: #FF3B30; border-left: 3px solid #FF3B30; }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); position: fixed; z-index: 1000; }
            .main-content { margin-left: 0; }
            .form-grid { grid-template-columns: 1fr; }
            .form-group.full-width { grid-column: span 1; }
            .medication-row, .medication-header {
                grid-template-columns: 1fr;
                gap: 8px;
                background: #f8fafc;
                padding: 12px;
                border-radius: 12px;
                margin-bottom: 16px;
            }
            .medication-header { display: none; }
        }
    </style>
</head>
<body>
<aside class="sidebar">
    <div class="sidebar-header"><h2><i class="fas fa-heartbeat"></i> VeeCare</h2><p>Medical Centre</p></div>
    <nav class="sidebar-nav">
        <div class="nav-item"><a href="../dashboard.php" class="nav-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a></div>
        <div class="nav-divider"></div>
        <div class="nav-category">Prescriptions</div>
        <div class="nav-item"><a href="view_prescriptions.php" class="nav-link"><i class="fas fa-list"></i> All Prescriptions</a></div>
        <div class="nav-item"><a href="add_prescription.php" class="nav-link active"><i class="fas fa-plus-circle"></i> New Prescription</a></div>
        <div class="nav-divider"></div>
        <div class="nav-item"><a href="../logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
    </nav>
</aside>

<main class="main-content">
    <div class="page-header">
        <div><h1>Create Prescription</h1><p>Issue a new prescription for a patient</p></div>
        <a href="view_prescriptions.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Prescriptions</a>
    </div>

    <div class="form-card">
        <?php if ($success): ?>
            <div class="alert alert-success">Prescription saved successfully! <a href="view_prescriptions.php">View all prescriptions →</a></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" id="prescriptionForm">
            <!-- Basic Info -->
            <div class="form-section">
                <div class="section-title"><i class="fas fa-info-circle"></i> Prescription Information</div>
                <div class="form-grid">
                    <div class="form-group"><label class="required">Patient</label>
                        <select name="patient_id" required>
                            <option value="">Select Patient</option>
                            <?php foreach ($patients as $p): ?>
                                <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['full_name']); ?> (<?php echo htmlspecialchars($p['patient_id']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label class="required">Doctor</label>
                        <select name="doctor_id" required>
                            <option value="">Select Doctor</option>
                            <?php foreach ($doctors as $d): ?>
                                <option value="<?php echo $d['id']; ?>"><?php echo htmlspecialchars($d['full_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label class="required">Date</label><input type="date" name="prescription_date" value="<?php echo date('Y-m-d'); ?>" required></div>
                    <div class="form-group"><label class="required">Condition Category</label>
                        <select name="condition_category" id="condition_category" required onchange="toggleConditionFields()">
                            <option value="">Select</option>
                            <option value="general">🔵 General Illness (e.g., malaria)</option>
                            <option value="chronic">🟡 Chronic Condition (e.g., arthritis)</option>
                            <option value="trauma">🔴 Trauma / Emergency</option>
                        </select>
                    </div>
                    <div class="form-group full-width"><label class="required">Diagnosis</label>
                        <textarea name="diagnosis_text" rows="2" placeholder="Enter diagnosis details..." required></textarea>
                    </div>
                    <div class="form-group"><label>Treatment Category</label>
                        <select name="treatment_category">
                            <option value="">Select</option>
                            <option value="antimalarial">Antimalarial</option>
                            <option value="pain_management">Pain Management</option>
                            <option value="anti_inflammatory">Anti-inflammatory</option>
                            <option value="antibiotic">Antibiotic</option>
                            <option value="trauma">Trauma Care</option>
                        </select>
                    </div>
                    <div class="form-group full-width"><label>Additional Notes</label><textarea name="notes" rows="2"></textarea></div>
                </div>
            </div>

            <!-- Dynamic Condition-Specific Fields -->
            <div id="chronic_fields" class="dynamic-section">
                <div class="form-section">
                    <div class="section-title"><i class="fas fa-chart-line"></i> Chronic Care Plan</div>
                    <div class="form-grid">
                        <div class="form-group full-width"><label>Long-term Medication Plan</label><textarea name="long_term_plan" rows="2"></textarea></div>
                        <div class="form-group full-width"><label>Follow-up Notes</label><textarea name="follow_up_notes" rows="2"></textarea></div>
                    </div>
                </div>
            </div>
            <div id="trauma_fields" class="dynamic-section">
                <div class="form-section">
                    <div class="section-title"><i class="fas fa-briefcase-medical"></i> Trauma Care Details</div>
                    <div class="form-grid">
                        <div class="form-group full-width"><label>Post-Procedure Medications</label><textarea name="post_procedure_meds" rows="2"></textarea></div>
                        <div class="form-group full-width"><label>Pain Management Plan</label><textarea name="pain_management_plan" rows="2"></textarea></div>
                    </div>
                </div>
            </div>

            <!-- Imaging / Procedure References -->
            <div class="form-section">
                <div class="section-title"><i class="fas fa-x-ray"></i> References (Optional)</div>
                <div class="form-grid">
                    <div class="form-group"><label>Imaging Results (X-ray, CT, etc.)</label><input type="text" name="imaging_references" placeholder="e.g., X-ray left arm - #XR123"></div>
                    <div class="form-group"><label>Procedures Performed</label><input type="text" name="procedure_references" placeholder="e.g., Fracture casting"></div>
                </div>
            </div>

            <!-- Medications Section -->
            <div class="form-section">
                <div class="section-title"><i class="fas fa-capsules"></i> Medications</div>
                <div class="medication-header">
                    <span>Medication Name</span><span>Dosage</span><span>Frequency</span><span>Duration</span><span>Quantity</span><span>Instructions</span><span></span>
                </div>
                <div id="medications-container">
                    <?php if (!empty($medications)): ?>
                        <?php foreach ($medications as $idx => $med): ?>
                            <div class="medication-row">
                                <input type="text" name="med_name[]" value="<?php echo $med['name']; ?>" placeholder="e.g., Paracetamol" required>
                                <input type="text" name="dosage[]" value="<?php echo $med['dosage']; ?>" placeholder="500mg">
                                <input type="text" name="frequency[]" value="<?php echo $med['frequency']; ?>" placeholder="Twice daily">
                                <input type="text" name="duration[]" value="<?php echo $med['duration']; ?>" placeholder="5 days">
                                <input type="number" name="quantity[]" value="<?php echo $med['quantity']; ?>" placeholder="Qty">
                                <input type="text" name="instructions[]" value="<?php echo $med['instructions']; ?>" placeholder="Take after meals">
                                <button type="button" class="btn-icon" onclick="this.parentElement.remove()"><i class="fas fa-trash-alt"></i></button>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="medication-row">
                            <input type="text" name="med_name[]" placeholder="e.g., Paracetamol" required>
                            <input type="text" name="dosage[]" placeholder="500mg">
                            <input type="text" name="frequency[]" placeholder="Twice daily">
                            <input type="text" name="duration[]" placeholder="5 days">
                            <input type="number" name="quantity[]" placeholder="Qty">
                            <input type="text" name="instructions[]" placeholder="Take after meals">
                            <button type="button" class="btn-icon" onclick="this.parentElement.remove()"><i class="fas fa-trash-alt"></i></button>
                        </div>
                    <?php endif; ?>
                </div>
                <button type="button" class="btn-add-med" onclick="addMedicationRow()"><i class="fas fa-plus"></i> Add Another Medication</button>
            </div>

            <div class="form-actions">
                <a href="view_prescriptions.php" class="btn-cancel">Cancel</a>
                <button type="submit" class="btn-submit">Issue Prescription</button>
            </div>
        </form>
    </div>
</main>

<script>
function addMedicationRow() {
    const container = document.getElementById('medications-container');
    const newRow = document.createElement('div');
    newRow.className = 'medication-row';
    newRow.innerHTML = `
        <input type="text" name="med_name[]" placeholder="e.g., Paracetamol" required>
        <input type="text" name="dosage[]" placeholder="500mg">
        <input type="text" name="frequency[]" placeholder="Twice daily">
        <input type="text" name="duration[]" placeholder="5 days">
        <input type="number" name="quantity[]" placeholder="Qty">
        <input type="text" name="instructions[]" placeholder="Take after meals">
        <button type="button" class="btn-icon" onclick="this.parentElement.remove()"><i class="fas fa-trash-alt"></i></button>
    `;
    container.appendChild(newRow);
}

function toggleConditionFields() {
    const category = document.getElementById('condition_category').value;
    const chronicDiv = document.getElementById('chronic_fields');
    const traumaDiv = document.getElementById('trauma_fields');
    chronicDiv.classList.remove('active');
    traumaDiv.classList.remove('active');
    if (category === 'chronic') chronicDiv.classList.add('active');
    if (category === 'trauma') traumaDiv.classList.add('active');
}

toggleConditionFields();
</script>
</body>
</html>