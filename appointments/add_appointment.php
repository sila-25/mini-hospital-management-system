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

// Fetch doctors (users with role 'doctor')
$doctors = [];
$res = $conn->query("SELECT id, full_name FROM users WHERE role = 'doctor' ORDER BY full_name");
while ($row = $res->fetch_assoc()) $doctors[] = $row;

$success = false;
$error = '';
$submitted = [
    'patient_id' => '', 'doctor_id' => '', 'appointment_date' => '', 'appointment_time' => '',
    'purpose' => '', 'visit_type' => 'consultation', 'triage_level' => 'blue', 'notes' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submitted['patient_id'] = (int)$_POST['patient_id'];
    $submitted['doctor_id'] = (int)$_POST['doctor_id'];
    $submitted['appointment_date'] = $_POST['appointment_date'];
    $submitted['appointment_time'] = $_POST['appointment_time'];
    $submitted['purpose'] = trim($_POST['purpose']);
    $submitted['visit_type'] = $_POST['visit_type'];
    $submitted['triage_level'] = $_POST['triage_level'];
    $submitted['notes'] = trim($_POST['notes']);
    
    $errors = [];
    if (empty($submitted['patient_id'])) $errors[] = 'Please select a patient.';
    if (empty($submitted['doctor_id'])) $errors[] = 'Please select a doctor.';
    if (empty($submitted['appointment_date'])) $errors[] = 'Please select a date.';
    if (empty($submitted['appointment_time'])) $errors[] = 'Please select a time.';
    
    if (empty($errors)) {
        $sql = "INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, purpose, visit_type, triage_level, notes, status, created_by, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'scheduled', ?, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iissssssi", 
            $submitted['patient_id'], $submitted['doctor_id'], 
            $submitted['appointment_date'], $submitted['appointment_time'], 
            $submitted['purpose'], $submitted['visit_type'], $submitted['triage_level'], 
            $submitted['notes'], $_SESSION['user_id']
        );
        if ($stmt->execute()) {
            $success = true;
            $submitted = array_fill_keys(array_keys($submitted), '');
        } else {
            $error = 'Database error: ' . $conn->error;
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule Appointment | VeeCare</title>
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
        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: #f0f2f5;
            color: #666;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 500;
            transition: all 0.2s;
        }
        .btn-back:hover { background: #e0e2e5; transform: translateX(-2px); }
        .form-card {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        .form-section { padding: 24px; border-bottom: 1px solid #eee; }
        .section-title {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #1a1a2e;
        }
        .section-title i { color: #0A84FF; font-size: 18px; }
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
        .btn-submit {
            padding: 12px 28px;
            background: linear-gradient(135deg, #0A84FF, #006EDB);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(10,132,255,0.3); }
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
        .info-text { font-size: 11px; color: #666; margin-top: 8px; }
        .info-text i { margin-right: 4px; }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); position: fixed; z-index: 1000; }
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
        <div class="nav-category">Appointments</div>
        <div class="nav-item"><a href="calendar.php" class="nav-link"><i class="fas fa-calendar-alt"></i> Calendar</a></div>
        <div class="nav-item"><a href="add_appointment.php" class="nav-link active"><i class="fas fa-plus-circle"></i> New Appointment</a></div>
        <div class="nav-item"><a href="view_appointments.php" class="nav-link"><i class="fas fa-list"></i> All Appointments</a></div>
        <div class="nav-divider"></div>
        <div class="nav-item"><a href="../logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
    </nav>
</aside>

<main class="main-content">
    <div class="page-header">
        <div><h1>Schedule New Appointment</h1><p>Book an appointment for a patient</p></div>
        <a href="view_appointments.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Appointments</a>
    </div>

    <div class="form-card">
        <?php if ($success): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> Appointment scheduled successfully! <a href="view_appointments.php" style="color:#0A84FF;">View all appointments →</a></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-section">
                <div class="section-title"><i class="fas fa-info-circle"></i> Appointment Details</div>
                <div class="form-grid">
                    <div class="form-group"><label class="required">Patient</label>
                        <select name="patient_id" required>
                            <option value="">Select Patient</option>
                            <?php foreach ($patients as $p): ?>
                                <option value="<?php echo $p['id']; ?>" <?php echo ($submitted['patient_id'] == $p['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($p['full_name']); ?> (<?php echo htmlspecialchars($p['patient_id']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label class="required">Doctor</label>
                        <select name="doctor_id" required>
                            <option value="">Select Doctor</option>
                            <?php foreach ($doctors as $d): ?>
                                <option value="<?php echo $d['id']; ?>" <?php echo ($submitted['doctor_id'] == $d['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($d['full_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label class="required">Date</label>
                        <input type="date" name="appointment_date" value="<?php echo htmlspecialchars($submitted['appointment_date']); ?>" required>
                    </div>
                    <div class="form-group"><label class="required">Time</label>
                        <input type="time" name="appointment_time" value="<?php echo htmlspecialchars($submitted['appointment_time']); ?>" required>
                    </div>
                    <div class="form-group"><label>Purpose</label>
                        <input type="text" name="purpose" value="<?php echo htmlspecialchars($submitted['purpose']); ?>" placeholder="e.g., Routine checkup, Follow-up, Vaccination">
                    </div>
                    <div class="form-group"><label class="required">Visit Type</label>
                        <select name="visit_type" id="visit_type" required onchange="updateTriage()">
                            <option value="consultation" <?php echo ($submitted['visit_type'] == 'consultation') ? 'selected' : ''; ?>>🔵 Consultation</option>
                            <option value="follow_up" <?php echo ($submitted['visit_type'] == 'follow_up') ? 'selected' : ''; ?>>🟢 Follow-up / Recovery</option>
                            <option value="chronic_care" <?php echo ($submitted['visit_type'] == 'chronic_care') ? 'selected' : ''; ?>>🟡 Chronic Care Review</option>
                            <option value="emergency" <?php echo ($submitted['visit_type'] == 'emergency') ? 'selected' : ''; ?>>🔴 Emergency</option>
                        </select>
                    </div>
                    <div class="form-group"><label class="required">Triage Level (Color)</label>
                        <select name="triage_level" id="triage_level" required>
                            <option value="blue" <?php echo ($submitted['triage_level'] == 'blue') ? 'selected' : ''; ?>>🔵 Blue – Non-urgent</option>
                            <option value="green" <?php echo ($submitted['triage_level'] == 'green') ? 'selected' : ''; ?>>🟢 Green – Stable</option>
                            <option value="yellow" <?php echo ($submitted['triage_level'] == 'yellow') ? 'selected' : ''; ?>>🟡 Yellow – Chronic monitoring</option>
                            <option value="red" <?php echo ($submitted['triage_level'] == 'red') ? 'selected' : ''; ?>>🔴 Red – Emergency</option>
                        </select>
                    </div>
                    <div class="form-group full-width"><label>Notes (Optional)</label>
                        <textarea name="notes" rows="3" placeholder="Any additional notes..."><?php echo htmlspecialchars($submitted['notes']); ?></textarea>
                    </div>
                </div>
            </div>
            <div class="form-actions">
                <a href="view_appointments.php" class="btn-cancel">Cancel</a>
                <button type="submit" class="btn-submit"><i class="fas fa-save"></i> Schedule Appointment</button>
            </div>
        </form>
    </div>
</main>

<script>
function updateTriage() {
    var visitType = document.getElementById('visit_type').value;
    var triageSelect = document.getElementById('triage_level');
    if (visitType === 'emergency') triageSelect.value = 'red';
    else if (visitType === 'chronic_care') triageSelect.value = 'yellow';
    else if (visitType === 'follow_up') triageSelect.value = 'green';
    else triageSelect.value = 'blue';
}
</script>
</body>
</html>