<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
require_once '../config/database.php';
$conn = getConnection();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    die("Invalid prescription ID.");
}

// Fetch prescription details with patient and doctor info
$sql = "SELECT pr.*, p.full_name as patient_name, p.patient_id as patient_code, p.date_of_birth, p.gender, p.address, p.phone,
               u.full_name as doctor_name, u.id as doctor_id
        FROM prescriptions pr
        JOIN patients p ON pr.patient_id = p.id
        LEFT JOIN users u ON pr.doctor_id = u.id
        WHERE pr.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$rx = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$rx) {
    die("Prescription not found.");
}

// Fetch prescription items
$items = [];
$stmt = $conn->prepare("SELECT * FROM prescription_items WHERE prescription_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Calculate age
$dob = new DateTime($rx['date_of_birth']);
$today = new DateTime();
$age = $dob->diff($today)->y;

// Clinic settings
$clinic_name = "VeeCare Medical Centre";
$clinic_address = "PO BOX 4478 - 40200, KISII";
$clinic_phone = "+254791333577";
$clinic_email = "info@veecare.com";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prescription #<?php echo htmlspecialchars($rx['prescription_number']); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Inter', 'Segoe UI', 'Arial', sans-serif;
            background: #eef2f5;
            padding: 40px 20px;
            display: flex;
            justify-content: center;
        }
        .prescription-wrapper {
            max-width: 800px;
            width: 100%;
            background: white;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            border-radius: 12px;
            overflow: hidden;
        }
        /* Print styles */
        @media print {
            body {
                background: white;
                padding: 0;
                margin: 0;
            }
            .prescription-wrapper {
                box-shadow: none;
                border-radius: 0;
            }
            .no-print {
                display: none !important;
            }
            .prescription-header, .footer-note {
                page-break-inside: avoid;
            }
        }
        /* Header */
        .prescription-header {
            background: linear-gradient(135deg, #0A84FF, #006EDB);
            color: white;
            padding: 24px 30px;
            text-align: center;
        }
        .clinic-name {
            font-size: 28px;
            font-weight: 800;
            letter-spacing: 1px;
        }
        .clinic-tagline {
            font-size: 12px;
            opacity: 0.8;
            margin-top: 5px;
        }
        .prescription-title {
            background: #fff;
            color: #0A84FF;
            display: inline-block;
            padding: 6px 20px;
            border-radius: 30px;
            font-size: 14px;
            font-weight: 700;
            margin-top: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        /* Content */
        .prescription-body {
            padding: 30px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px dashed #e0e6ed;
        }
        .info-block h4 {
            font-size: 12px;
            text-transform: uppercase;
            color: #6c86a3;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }
        .info-block p {
            font-size: 15px;
            font-weight: 500;
            color: #1a2c3e;
        }
        .diagnosis-block {
            background: #f0f7ff;
            padding: 15px 20px;
            border-radius: 16px;
            margin-bottom: 25px;
        }
        .diagnosis-block h4 {
            font-size: 13px;
            color: #0A84FF;
            margin-bottom: 8px;
        }
        .medication-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .medication-table th {
            text-align: left;
            background: #f8fafc;
            padding: 12px 10px;
            font-size: 12px;
            font-weight: 700;
            color: #1e2f3e;
            border-bottom: 2px solid #e0e6ed;
        }
        .medication-table td {
            padding: 10px;
            border-bottom: 1px solid #eef2f8;
            font-size: 13px;
            vertical-align: top;
        }
        .doctor-signature {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e0e6ed;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }
        .sign-line {
            width: 250px;
            border-top: 1px solid #333;
            margin-top: 30px;
            padding-top: 8px;
            font-size: 12px;
            color: #555;
        }
        .footer-note {
            background: #f8fafc;
            padding: 15px 30px;
            font-size: 11px;
            color: #7f8c8d;
            text-align: center;
            border-top: 1px solid #e0e6ed;
        }
        .button-bar {
            text-align: center;
            margin-top: 20px;
            margin-bottom: 20px;
        }
        .print-btn {
            background: #0A84FF;
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 30px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        }
        .print-btn:hover {
            background: #006EDB;
            transform: translateY(-1px);
        }
        @media (max-width: 640px) {
            .info-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }
            .prescription-body {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
<div class="prescription-wrapper">
    <div class="prescription-header">
        <div class="clinic-name"><?php echo $clinic_name; ?></div>
        <div class="clinic-tagline">Caring for your health, always.</div>
        <div class="prescription-title">MEDICAL PRESCRIPTION</div>
    </div>

    <div class="prescription-body">
        <div class="info-grid">
            <div class="info-block">
                <h4>Patient Information</h4>
                <p><strong><?php echo htmlspecialchars($rx['patient_name']); ?></strong></p>
                <p style="font-size:13px; margin-top:6px;">
                    ID: <?php echo htmlspecialchars($rx['patient_code']); ?><br>
                    Age: <?php echo $age; ?> years | Gender: <?php echo htmlspecialchars($rx['gender']); ?><br>
                    DOB: <?php echo date('d M Y', strtotime($rx['date_of_birth'])); ?>
                </p>
            </div>
            <div class="info-block">
                <h4>Prescription Details</h4>
                <p>No: <strong><?php echo htmlspecialchars($rx['prescription_number']); ?></strong></p>
                <p>Date: <?php echo date('d M Y', strtotime($rx['prescription_date'])); ?></p>
                <p>Doctor: Dr. <?php echo htmlspecialchars($rx['doctor_name']); ?></p>
            </div>
        </div>

        <?php if (!empty($rx['diagnosis'])): ?>
        <div class="diagnosis-block">
            <h4><i class="fas fa-stethoscope"></i> Diagnosis</h4>
            <p style="font-size:14px;"><?php echo nl2br(htmlspecialchars($rx['diagnosis'])); ?></p>
            <?php if (!empty($rx['treatment_category'])): ?>
                <p style="margin-top:8px;"><span style="background:#eef2ff; padding:2px 8px; border-radius:20px; font-size:11px;">Category: <?php echo ucfirst(str_replace('_', ' ', $rx['treatment_category'])); ?></span></p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <h4 style="margin: 20px 0 10px 0;">Medication</h4>
        <?php if (count($items) > 0): ?>
        <table class="medication-table">
            <thead>
                <tr><th>Medicine</th><th>Dosage</th><th>Frequency</th><th>Duration</th><th>Quantity</th><th>Instructions</th></tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($item['medication_name']); ?></strong></td>
                    <td><?php echo htmlspecialchars($item['dosage']); ?></td>
                    <td><?php echo htmlspecialchars($item['frequency']); ?></td>
                    <td><?php echo htmlspecialchars($item['duration']); ?></td>
                    <td><?php echo $item['quantity']; ?></td>
                    <td><?php echo htmlspecialchars($item['instructions']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
            <p style="color:#888;">No medication items recorded.</p>
        <?php endif; ?>

        <?php if (!empty($rx['notes'])): ?>
        <div style="margin: 20px 0; background:#fef9e6; padding:12px 16px; border-radius:12px;">
            <h4 style="font-size:12px; color:#d97706;">Special Instructions</h4>
            <p style="font-size:13px;"><?php echo nl2br(htmlspecialchars($rx['notes'])); ?></p>
        </div>
        <?php endif; ?>

        <div class="doctor-signature">
            <div></div>
            <div class="sign-line">
                Dr. <?php echo htmlspecialchars($rx['doctor_name']); ?><br>
                <span style="font-size:11px;">(Registered Medical Practitioner)</span>
            </div>
        </div>
    </div>

    <div class="footer-note">
        <div><?php echo $clinic_address; ?> | Tel: <?php echo $clinic_phone; ?> | Email: <?php echo $clinic_email; ?></div>
        <div style="margin-top:5px;">This is a computer-generated prescription. Valid without signature.</div>
    </div>

    <div class="button-bar no-print">
        <button class="print-btn" onclick="window.print();"><i class="fas fa-print"></i> Print / Save as PDF</button>
        <button class="print-btn" style="background:#6c757d; margin-left:10px;" onclick="window.close();">Close</button>
    </div>
</div>

<script>
    // Automatically trigger print dialog? (optional, uncomment if desired)
    // window.onload = function() { window.print(); };
</script>
</body>
</html>