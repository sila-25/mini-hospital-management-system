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

// Fetch patient by numeric ID or patient_id string
if (is_numeric($id) && $id > 0) {
    $stmt = $conn->prepare("SELECT * FROM patients WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $patient = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} else {
    $stmt = $conn->prepare("SELECT * FROM patients WHERE patient_id = ?");
    $stmt->bind_param("s", $id);
    $stmt->execute();
    $patient = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (!$patient) {
    header("Location: view_patients.php");
    exit();
}

// Fetch diagnoses (medical conditions)
$diagnoses = [];
$stmt = $conn->prepare("SELECT d.*, u.full_name as doctor_name FROM diagnosis d LEFT JOIN users u ON d.doctor_id = u.id WHERE d.patient_id = ? ORDER BY d.diagnosis_date DESC");
$stmt->bind_param("i", $patient['id']);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) $diagnoses[] = $row;
$stmt->close();

// Fetch appointments
$appointments = [];
$stmt = $conn->prepare("SELECT a.*, u.full_name as doctor_name FROM appointments a LEFT JOIN users u ON a.doctor_id = u.id WHERE a.patient_id = ? ORDER BY a.appointment_date DESC LIMIT 10");
$stmt->bind_param("i", $patient['id']);
$stmt->execute();
$appointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch prescriptions
$prescriptions = [];
$stmt = $conn->prepare("SELECT p.*, u.full_name as doctor_name FROM prescriptions p LEFT JOIN users u ON p.doctor_id = u.id WHERE p.patient_id = ? ORDER BY p.prescription_date DESC LIMIT 10");
$stmt->bind_param("i", $patient['id']);
$stmt->execute();
$prescriptions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch procedures
$procedures = [];
$stmt = $conn->prepare("SELECT pr.*, u.full_name as doctor_name FROM procedures pr LEFT JOIN users u ON pr.doctor_id = u.id WHERE pr.patient_id = ? ORDER BY pr.procedure_date DESC LIMIT 10");
$stmt->bind_param("i", $patient['id']);
$stmt->execute();
$procedures = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

function calculateAge($dob) {
    $birthDate = new DateTime($dob);
    $today = new DateTime();
    $age = $today->diff($birthDate);
    return $age->y;
}
$age = calculateAge($patient['date_of_birth']);

// Helper to get status color class
function getStatusClass($status) {
    switch ($status) {
        case 'scheduled': return 'badge-scheduled';
        case 'completed': return 'badge-completed';
        case 'cancelled': return 'badge-cancelled';
        default: return 'badge-scheduled';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($patient['full_name']); ?> | Patient Profile</title>
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
        .profile-info { display: flex; align-items: center; gap: 20px; }
        .profile-avatar {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #0A84FF, #34C759);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            font-weight: 700;
            color: #fff;
        }
        .profile-title h1 { font-size: 24px; font-weight: 700; }
        .profile-title p { font-size: 13px; color: #666; margin-top: 4px; }
        .action-buttons { display: flex; gap: 12px; }
        .edit-btn {
            padding: 10px 20px;
            background: #0A84FF;
            color: #fff;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 500;
        }
        .back-btn {
            padding: 10px 20px;
            background: #f0f2f5;
            color: #666;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 500;
        }
        .tabs {
            display: flex;
            gap: 4px;
            background: #fff;
            border-radius: 16px;
            padding: 8px;
            margin-bottom: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            flex-wrap: wrap;
        }
        .tab {
            padding: 12px 24px;
            background: none;
            border: none;
            font-size: 14px;
            font-weight: 600;
            color: #666;
            cursor: pointer;
            border-radius: 12px;
            transition: all 0.2s;
        }
        .tab.active { background: #0A84FF; color: #fff; }
        .tab:hover:not(.active) { background: #f0f2f5; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 24px;
        }
        .info-card {
            background: #fff;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .info-card h3 {
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
            color: #1a1a2e;
        }
        .info-card h3 i { color: #0A84FF; }
        .info-row {
            display: flex;
            margin-bottom: 12px;
            font-size: 13px;
        }
        .info-label {
            width: 140px;
            font-weight: 600;
            color: #666;
        }
        .info-value { color: #1a1a2e; flex: 1; }
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        .data-table th {
            text-align: left;
            padding: 12px 16px;
            font-size: 11px;
            font-weight: 600;
            color: #8E8E93;
            background: #f8f9fa;
            border-bottom: 1px solid #eee;
        }
        .data-table td {
            padding: 12px 16px;
            font-size: 13px;
            border-bottom: 1px solid #f0f0f0;
        }
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        .badge-scheduled { background: rgba(10,132,255,0.1); color: #0A84FF; }
        .badge-completed { background: rgba(52,199,89,0.1); color: #34C759; }
        .badge-cancelled { background: rgba(255,59,48,0.1); color: #FF3B30; }
        .badge-active { background: rgba(52,199,89,0.1); color: #34C759; }
        .badge-dispensed { background: rgba(255,149,0,0.1); color: #FF9500; }
        .child-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            background: rgba(255,149,0,0.1);
            color: #FF9500;
        }
        .diagnosis-card {
            background: #f8fafc;
            border-left: 3px solid #0A84FF;
            padding: 12px;
            margin-bottom: 12px;
            border-radius: 8px;
        }
        .diagnosis-card strong { display: block; margin-bottom: 6px; }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); position: fixed; }
            .main-content { margin-left: 0; }
            .info-grid { grid-template-columns: 1fr; }
            .profile-info { flex-direction: column; text-align: center; }
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
        <div class="profile-info">
            <div class="profile-avatar"><?php echo strtoupper(substr($patient['full_name'], 0, 1)); ?></div>
            <div class="profile-title">
                <h1><?php echo htmlspecialchars($patient['full_name']); ?></h1>
                <p>Patient ID: <?php echo htmlspecialchars($patient['patient_id']); ?> • Age: <?php echo $age; ?> years • <?php echo $patient['is_child'] ? '<span class="child-badge"><i class="fas fa-child"></i> Child Patient</span>' : 'Adult Patient'; ?></p>
            </div>
        </div>
        <div class="action-buttons">
            <a href="edit_patient.php?id=<?php echo $patient['id']; ?>" class="edit-btn"><i class="fas fa-edit"></i> Edit Profile</a>
            <a href="view_patients.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back</a>
        </div>
    </div>

    <div class="tabs">
        <button class="tab active" onclick="showTab('personal')"><i class="fas fa-user"></i> Personal Info</button>
        <button class="tab" onclick="showTab('medical')"><i class="fas fa-stethoscope"></i> Medical Conditions</button>
        <button class="tab" onclick="showTab('appointments')"><i class="fas fa-calendar"></i> Appointments</button>
        <button class="tab" onclick="showTab('prescriptions')"><i class="fas fa-prescription-bottle"></i> Prescriptions</button>
        <button class="tab" onclick="showTab('procedures')"><i class="fas fa-syringe"></i> Procedures</button>
    </div>

    <!-- Personal Info Tab -->
    <div id="personal" class="tab-content active">
        <div class="info-grid">
            <div class="info-card">
                <h3><i class="fas fa-address-card"></i> Personal Information</h3>
                <div class="info-row"><div class="info-label">Full Name:</div><div class="info-value"><?php echo htmlspecialchars($patient['full_name']); ?></div></div>
                <div class="info-row"><div class="info-label">Patient ID:</div><div class="info-value"><?php echo htmlspecialchars($patient['patient_id']); ?></div></div>
                <div class="info-row"><div class="info-label">Date of Birth:</div><div class="info-value"><?php echo date('F d, Y', strtotime($patient['date_of_birth'])); ?> (Age: <?php echo $age; ?>)</div></div>
                <div class="info-row"><div class="info-label">Gender:</div><div class="info-value"><?php echo htmlspecialchars($patient['gender']); ?></div></div>
                <div class="info-row"><div class="info-label">Blood Group:</div><div class="info-value"><?php echo htmlspecialchars($patient['blood_group'] ?? 'Not specified'); ?></div></div>
                <div class="info-row"><div class="info-label">Condition Category:</div><div class="info-value">
                    <?php 
                    $cat = $patient['condition_category'];
                    if ($cat == 'general') echo '🔵 General Illness';
                    elseif ($cat == 'chronic') echo '🟡 Chronic Disease';
                    elseif ($cat == 'trauma') echo '🔴 Trauma / Injury';
                    else echo 'Not specified';
                    ?>
                </div></div>
                <?php if ($patient['is_child']): ?>
                    <div class="info-row"><div class="info-label">Guardian Name:</div><div class="info-value"><?php echo htmlspecialchars($patient['guardian_name']); ?></div></div>
                    <div class="info-row"><div class="info-label">Guardian Phone:</div><div class="info-value"><?php echo htmlspecialchars($patient['guardian_phone']); ?></div></div>
                    <div class="info-row"><div class="info-label">Guardian Relationship:</div><div class="info-value"><?php echo htmlspecialchars($patient['guardian_relationship']); ?></div></div>
                <?php endif; ?>
            </div>
            <div class="info-card">
                <h3><i class="fas fa-phone-alt"></i> Contact Information</h3>
                <div class="info-row"><div class="info-label">Email:</div><div class="info-value"><?php echo htmlspecialchars($patient['email'] ?? 'Not provided'); ?></div></div>
                <div class="info-row"><div class="info-label">Phone:</div><div class="info-value"><?php echo htmlspecialchars($patient['phone']); ?></div></div>
                <div class="info-row"><div class="info-label">Address:</div><div class="info-value"><?php echo nl2br(htmlspecialchars($patient['address'] ?? 'Not provided')); ?></div></div>
            </div>
            <div class="info-card">
                <h3><i class="fas fa-ambulance"></i> Emergency Contact (Next of Kin)</h3>
                <div class="info-row"><div class="info-label">Name:</div><div class="info-value"><?php echo htmlspecialchars($patient['next_of_kin_name'] ?? 'Not provided'); ?></div></div>
                <div class="info-row"><div class="info-label">Relationship:</div><div class="info-value"><?php echo htmlspecialchars($patient['next_of_kin_relationship'] ?? 'Not provided'); ?></div></div>
                <div class="info-row"><div class="info-label">Phone:</div><div class="info-value"><?php echo htmlspecialchars($patient['next_of_kin_phone'] ?? 'Not provided'); ?></div></div>
                <div class="info-row"><div class="info-label">Email:</div><div class="info-value"><?php echo htmlspecialchars($patient['next_of_kin_email'] ?? 'Not provided'); ?></div></div>
            </div>
            <div class="info-card">
                <h3><i class="fas fa-notes-medical"></i> Medical Information</h3>
                <div class="info-row"><div class="info-label">Allergies:</div><div class="info-value"><?php echo nl2br(htmlspecialchars($patient['allergies'] ?? 'None')); ?></div></div>
                <div class="info-row"><div class="info-label">Past Medical History:</div><div class="info-value"><?php echo nl2br(htmlspecialchars($patient['medical_history'] ?? 'None')); ?></div></div>
            </div>
        </div>
    </div>

    <!-- Medical Conditions Tab -->
    <div id="medical" class="tab-content">
        <div class="info-card">
            <h3><i class="fas fa-stethoscope"></i> Categorized Medical History</h3>
            <?php if (empty($diagnoses)): ?>
                <p>No medical conditions recorded.</p>
            <?php else: ?>
                <?php
                $general = array_filter($diagnoses, fn($d) => $d['condition_type'] == 'general');
                $chronic = array_filter($diagnoses, fn($d) => $d['condition_type'] == 'chronic');
                $trauma = array_filter($diagnoses, fn($d) => $d['condition_type'] == 'trauma');
                ?>
                <?php if (!empty($general)): ?>
                    <h4 style="color:#0A84FF; margin-top:1rem; margin-bottom:0.5rem;">🔵 General / Infectious</h4>
                    <?php foreach ($general as $d): ?>
                        <div class="diagnosis-card">
                            <strong><?php echo htmlspecialchars($d['diagnosis']); ?></strong> (<?php echo date('M d, Y', strtotime($d['diagnosis_date'])); ?>)
                            <div>Symptoms: <?php echo htmlspecialchars($d['symptoms']); ?></div>
                            <div>Severity: <?php echo ucfirst($d['severity']); ?></div>
                            <div>Doctor: <?php echo htmlspecialchars($d['doctor_name']); ?></div>
                            <?php if ($d['treatment_notes']): ?>
                                <div>Notes: <?php echo htmlspecialchars($d['treatment_notes']); ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                <?php if (!empty($chronic)): ?>
                    <h4 style="color:#FF9500; margin-top:1rem; margin-bottom:0.5rem;">🟡 Chronic</h4>
                    <?php foreach ($chronic as $d): ?>
                        <div class="diagnosis-card">
                            <strong><?php echo htmlspecialchars($d['diagnosis']); ?></strong> (<?php echo date('M d, Y', strtotime($d['diagnosis_date'])); ?>)
                            <div>Symptoms: <?php echo htmlspecialchars($d['symptoms']); ?></div>
                            <div>Severity: <?php echo ucfirst($d['severity']); ?></div>
                            <div>Doctor: <?php echo htmlspecialchars($d['doctor_name']); ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                <?php if (!empty($trauma)): ?>
                    <h4 style="color:#FF3B30; margin-top:1rem; margin-bottom:0.5rem;">🔴 Trauma</h4>
                    <?php foreach ($trauma as $d): ?>
                        <div class="diagnosis-card">
                            <strong><?php echo htmlspecialchars($d['diagnosis']); ?></strong> (<?php echo date('M d, Y', strtotime($d['diagnosis_date'])); ?>)
                            <div>Injury Details: <?php echo htmlspecialchars($d['injury_details']); ?></div>
                            <div>Imaging Recommended: <?php echo htmlspecialchars($d['imaging_recommended']); ?></div>
                            <div>Doctor: <?php echo htmlspecialchars($d['doctor_name']); ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Appointments Tab -->
    <div id="appointments" class="tab-content">
        <div class="info-card">
            <h3><i class="fas fa-calendar-check"></i> Appointment History</h3>
            <?php if (empty($appointments)): ?>
                <p style="text-align: center; padding: 40px; color: #999;">No appointments found</p>
            <?php else: ?>
                <table class="data-table">
                    <thead><tr><th>Date</th><th>Time</th><th>Doctor</th><th>Visit Type</th><th>Triage</th><th>Status</th><th>Purpose</th></tr></thead>
                    <tbody>
                        <?php foreach ($appointments as $apt): ?>
                        <tr>
                            <td><?php echo date('M d, Y', strtotime($apt['appointment_date'])); ?></td>
                            <td><?php echo date('g:i A', strtotime($apt['appointment_time'])); ?></td>
                            <td><?php echo htmlspecialchars($apt['doctor_name'] ?? '—'); ?></td>
                            <td><?php echo ucfirst(str_replace('_', ' ', $apt['visit_type'] ?? 'consultation')); ?></td>
                            <td><span class="badge" style="background:<?php echo $apt['triage_level'] == 'red' ? '#FF3B30' : ($apt['triage_level'] == 'yellow' ? '#FF9500' : ($apt['triage_level'] == 'green' ? '#34C759' : '#0A84FF')); ?>20; color:white;"><?php echo ucfirst($apt['triage_level']); ?></span></td>
                            <td><span class="badge <?php echo getStatusClass($apt['status']); ?>"><?php echo ucfirst($apt['status']); ?></span></td>
                            <td><?php echo htmlspecialchars($apt['purpose'] ?? '—'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Prescriptions Tab -->
    <div id="prescriptions" class="tab-content">
        <div class="info-card">
            <h3><i class="fas fa-prescription-bottle"></i> Prescriptions</h3>
            <?php if (empty($prescriptions)): ?>
                <p style="text-align: center; padding: 40px; color: #999;">No prescriptions found</p>
            <?php else: ?>
                <table class="data-table">
                    <thead><tr><th>Date</th><th>Prescription #</th><th>Diagnosis</th><th>Treatment Category</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php foreach ($prescriptions as $rx): ?>
                        <tr>
                            <td><?php echo date('M d, Y', strtotime($rx['prescription_date'])); ?></td>
                            <td><?php echo htmlspecialchars($rx['prescription_number']); ?></td>
                            <td><?php echo htmlspecialchars(substr($rx['diagnosis'] ?? '', 0, 50)); ?></td>
                            <td><?php echo str_replace('_', ' ', ucfirst($rx['treatment_category'] ?? '—')); ?></td>
                            <td><span class="badge badge-<?php echo $rx['status']; ?>"><?php echo ucfirst($rx['status']); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Procedures Tab -->
    <div id="procedures" class="tab-content">
        <div class="info-card">
            <h3><i class="fas fa-syringe"></i> Procedures Log</h3>
            <?php if (empty($procedures)): ?>
                <p style="text-align: center; padding: 40px; color: #999;">No procedures recorded</p>
            <?php else: ?>
                <table class="data-table">
                    <thead><tr><th>Date</th><th>Procedure Name</th><th>Category</th><th>Doctor</th><th>Details</th></tr></thead>
                    <tbody>
                        <?php foreach ($procedures as $proc): ?>
                        <tr>
                            <td><?php echo date('M d, Y', strtotime($proc['procedure_date'])); ?></td>
                            <td><strong><?php echo htmlspecialchars($proc['procedure_name']); ?></strong></td>
                            <td><?php echo $proc['procedure_category'] == 'trauma' ? '🔴 Trauma' : '🔵 General'; ?></td>
                            <td><?php echo htmlspecialchars($proc['doctor_name'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($proc['details'] ?? '—'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
function showTab(tabName) {
    document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
    document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
    document.getElementById(tabName).classList.add('active');
    event.target.classList.add('active');
}
</script>
</body>
</html>