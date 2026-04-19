<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
require_once '../config/database.php';
$conn = getConnection();

$message = '';
$error = '';

// Fetch patients
$patients = [];
$res = $conn->query("SELECT id, full_name, patient_id FROM patients ORDER BY full_name");
while ($row = $res->fetch_assoc()) $patients[] = $row;

// Fetch doctors
$doctors = [];
$res = $conn->query("SELECT id, full_name FROM users WHERE role = 'doctor' ORDER BY full_name");
while ($row = $res->fetch_assoc()) $doctors[] = $row;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_procedure'])) {
    $patient_id = (int)$_POST['patient_id'];
    $doctor_id = (int)$_POST['doctor_id'];
    $procedure_date = $_POST['procedure_date'];
    $procedure_category = $_POST['procedure_category'];
    $procedure_name = trim($_POST['procedure_name']);
    $details = trim($_POST['details']);

    if (empty($patient_id) || empty($doctor_id) || empty($procedure_date) || empty($procedure_name)) {
        $error = "Please fill in all required fields.";
    } else {
        $stmt = $conn->prepare("INSERT INTO procedures (patient_id, doctor_id, procedure_date, procedure_category, procedure_name, details, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("iissss", $patient_id, $doctor_id, $procedure_date, $procedure_category, $procedure_name, $details);
        if ($stmt->execute()) {
            $message = "Procedure logged successfully.";
        } else {
            $error = "Database error: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Fetch recent procedures
$recent_procedures = [];
$stmt = $conn->prepare("
    SELECT p.*, pt.full_name as patient_name, u.full_name as doctor_name 
    FROM procedures p
    JOIN patients pt ON p.patient_id = pt.id
    LEFT JOIN users u ON p.doctor_id = u.id
    ORDER BY p.procedure_date DESC, p.created_at DESC
    LIMIT 10
");
$stmt->execute();
$recent_procedures = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<!-- MAIN CONTENT - With proper margin to avoid sidebar overlap -->
<div style="margin-left: 280px; padding: 24px; background: #f5f7fa; min-height: 100vh;">
    <div style="max-width: 1200px; margin: 0 auto;">
        <!-- Header -->
        <div style="background: white; border-radius: 20px; padding: 20px 28px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
            <div>
                <h1 style="margin: 0; font-size: 24px;">Log Procedure</h1>
                <p style="margin: 4px 0 0; color: #6c86a3;">Record medical procedures performed on patients</p>
            </div>
            <a href="../dashboard.php" style="display: inline-flex; align-items: center; gap: 8px; background: #f0f2f5; padding: 8px 20px; border-radius: 12px; text-decoration: none; color: #4a627a; font-weight: 500;"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        </div>

        <!-- Alert Messages -->
        <?php if ($message): ?>
            <div style="background: #d4edda; color: #155724; padding: 14px 20px; border-radius: 12px; margin-bottom: 24px; border-left: 4px solid #28a745;"><?php echo htmlspecialchars($message); ?></div>
        <?php elseif ($error): ?>
            <div style="background: #f8d7da; color: #721c24; padding: 14px 20px; border-radius: 12px; margin-bottom: 24px; border-left: 4px solid #dc3545;"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Procedure Form -->
        <div style="background: white; border-radius: 24px; padding: 28px; margin-bottom: 32px; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
            <form method="POST">
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px;">
                    <div>
                        <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">Patient <span style="color:#FF3B30;">*</span></label>
                        <select name="patient_id" required style="width:100%; padding: 10px 14px; border:1px solid #ddd; border-radius: 12px;">
                            <option value="">Select Patient</option>
                            <?php foreach ($patients as $p): ?>
                                <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['full_name']); ?> (<?php echo $p['patient_id']; ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">Doctor <span style="color:#FF3B30;">*</span></label>
                        <select name="doctor_id" required style="width:100%; padding: 10px 14px; border:1px solid #ddd; border-radius: 12px;">
                            <option value="">Select Doctor</option>
                            <?php foreach ($doctors as $d): ?>
                                <option value="<?php echo $d['id']; ?>"><?php echo htmlspecialchars($d['full_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">Procedure Date <span style="color:#FF3B30;">*</span></label>
                        <input type="date" name="procedure_date" value="<?php echo date('Y-m-d'); ?>" required style="width:100%; padding: 10px 14px; border:1px solid #ddd; border-radius: 12px;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">Category <span style="color:#FF3B30;">*</span></label>
                        <select name="procedure_category" required style="width:100%; padding: 10px 14px; border:1px solid #ddd; border-radius: 12px;">
                            <option value="general">🔵 General (Injection, Dressing)</option>
                            <option value="trauma">🔴 Trauma (Casting, Suturing)</option>
                        </select>
                    </div>
                    <div style="grid-column: span 2;">
                        <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">Procedure Name <span style="color:#FF3B30;">*</span></label>
                        <input type="text" name="procedure_name" placeholder="e.g., Wound Dressing, Fracture Casting, Suturing" required style="width:100%; padding: 10px 14px; border:1px solid #ddd; border-radius: 12px;">
                    </div>
                    <div style="grid-column: span 2;">
                        <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">Details / Notes</label>
                        <textarea name="details" rows="4" placeholder="Additional details about the procedure..." style="width:100%; padding: 10px 14px; border:1px solid #ddd; border-radius: 12px;"></textarea>
                    </div>
                </div>
                <div style="margin-top: 24px; text-align: right;">
                    <button type="submit" name="add_procedure" style="background: #0A84FF; color: white; border: none; padding: 12px 28px; border-radius: 12px; font-size: 14px; font-weight: 600; cursor: pointer;"><i class="fas fa-save"></i> Log Procedure</button>
                </div>
            </form>
        </div>

        <!-- Recent Procedures -->
        <div style="background: white; border-radius: 24px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
            <h3 style="font-size: 16px; font-weight: 700; margin-bottom: 16px;"><i class="fas fa-history" style="color: #0A84FF;"></i> Recent Procedures</h3>
            <?php if (empty($recent_procedures)): ?>
                <div style="text-align: center; padding: 40px; color: #8E8E93;">
                    <i class="fas fa-syringe" style="font-size: 48px; margin-bottom: 16px; opacity: 0.5;"></i>
                    <p>No procedures logged yet.</p>
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr>
                                <th style="text-align: left; padding: 12px; background: #f8fafc; border-bottom: 1px solid #eef2f6;">Date</th>
                                <th style="text-align: left; padding: 12px; background: #f8fafc; border-bottom: 1px solid #eef2f6;">Patient</th>
                                <th style="text-align: left; padding: 12px; background: #f8fafc; border-bottom: 1px solid #eef2f6;">Procedure</th>
                                <th style="text-align: left; padding: 12px; background: #f8fafc; border-bottom: 1px solid #eef2f6;">Category</th>
                                <th style="text-align: left; padding: 12px; background: #f8fafc; border-bottom: 1px solid #eef2f6;">Doctor</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_procedures as $proc): ?>
                                <tr>
                                    <td style="padding: 12px; border-bottom: 1px solid #eef2f6;"><?php echo date('M d, Y', strtotime($proc['procedure_date'])); ?></td>
                                    <td style="padding: 12px; border-bottom: 1px solid #eef2f6;"><strong><?php echo htmlspecialchars($proc['patient_name']); ?></strong></td>
                                    <td style="padding: 12px; border-bottom: 1px solid #eef2f6;"><?php echo htmlspecialchars($proc['procedure_name']); ?></td>
                                    <td style="padding: 12px; border-bottom: 1px solid #eef2f6;"><span style="background: <?php echo $proc['procedure_category'] == 'general' ? 'rgba(10,132,255,0.1)' : 'rgba(255,59,48,0.1)'; ?>; color: <?php echo $proc['procedure_category'] == 'general' ? '#0A84FF' : '#FF3B30'; ?>; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600;"><?php echo $proc['procedure_category'] == 'general' ? '🔵 General' : '🔴 Trauma'; ?></span></td>
                                    <td style="padding: 12px; border-bottom: 1px solid #eef2f6;"><?php echo htmlspecialchars($proc['doctor_name'] ?? '—'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>