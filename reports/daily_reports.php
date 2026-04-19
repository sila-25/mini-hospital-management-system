<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
require_once '../config/database.php';
$conn = getConnection();

$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Helper: payment date column
$has_payment_date = $conn->query("SHOW COLUMNS FROM payments LIKE 'payment_date'")->num_rows > 0;
$payment_date_col = $has_payment_date ? 'payment_date' : 'created_at';

// Fetch data
$stmt = $conn->prepare("SELECT COUNT(DISTINCT patient_id) as total FROM appointments WHERE DATE(appointment_date) = ?");
$stmt->bind_param("s", $selected_date);
$stmt->execute();
$total_patients = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(DISTINCT a.patient_id) as new_count FROM appointments a WHERE DATE(a.appointment_date) = ? AND NOT EXISTS (SELECT 1 FROM appointments a2 WHERE a2.patient_id = a.patient_id AND a2.appointment_date < a.appointment_date)");
$stmt->bind_param("s", $selected_date);
$stmt->execute();
$new_patients = $stmt->get_result()->fetch_assoc()['new_count'] ?? 0;
$stmt->close();
$returning_patients = $total_patients - $new_patients;
$consultations = $total_patients;

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM procedures WHERE DATE(procedure_date) = ?");
$stmt->bind_param("s", $selected_date);
$stmt->execute();
$procedures = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM prescriptions WHERE DATE(prescription_date) = ?");
$stmt->bind_param("s", $selected_date);
$stmt->execute();
$prescriptions = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
$stmt->close();

$stmt = $conn->prepare("SELECT SUM(amount) as revenue FROM payments WHERE DATE($payment_date_col) = ? AND status = 'completed'");
$stmt->bind_param("s", $selected_date);
$stmt->execute();
$revenue = $stmt->get_result()->fetch_assoc()['revenue'] ?? 0;
$stmt->close();

$case_breakdown = ['general' => 0, 'chronic' => 0, 'trauma' => 0];
$stmt = $conn->prepare("SELECT p.condition_category, COUNT(DISTINCT a.patient_id) as count FROM appointments a JOIN patients p ON a.patient_id = p.id WHERE DATE(a.appointment_date) = ? GROUP BY p.condition_category");
$stmt->bind_param("s", $selected_date);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    if (isset($case_breakdown[$row['condition_category']])) {
        $case_breakdown[$row['condition_category']] = $row['count'];
    }
}
$stmt->close();

$hourly_flow = array_fill(0, 24, 0);
$stmt = $conn->prepare("SELECT HOUR(appointment_time) as hour, COUNT(*) as count FROM appointments WHERE DATE(appointment_date) = ? GROUP BY HOUR(appointment_time)");
$stmt->bind_param("s", $selected_date);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $hourly_flow[(int)$row['hour']] = $row['count'];
}
$stmt->close();

$busiest_hour = array_keys($hourly_flow, max($hourly_flow))[0] ?? 0;
$busiest_hour_formatted = date('g:i A', mktime($busiest_hour, 0, 0)) . ' - ' . date('g:i A', mktime($busiest_hour + 1, 0, 0));

$dominant_case = array_keys($case_breakdown, max($case_breakdown))[0] ?? 'none';
$dominant_label = match($dominant_case) {
    'general' => '🔵 General Illness',
    'chronic' => '🟡 Chronic Condition',
    'trauma' => '🔴 Trauma / Emergency',
    default => 'No cases'
};
$total_activity = $total_patients + $procedures + $prescriptions;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Operations Report | VeeCare</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Inter', sans-serif;
            background: #f0f2f5;
            display: flex;
            min-height: 100vh;
        }
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
        .nav-item { margin-bottom: 4px; }
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
        .main-content { margin-left: 260px; flex: 1; padding: 20px; }
        .report-header {
            background: white;
            border-radius: 20px;
            padding: 20px 24px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .report-header h1 { font-size: 24px; margin-bottom: 4px; }
        .report-header p { color: #6c86a3; }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            transition: transform 0.2s;
        }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,0.08); }
        .stat-card h4 { font-size: 13px; color: #6c86a3; margin-bottom: 8px; }
        .stat-number { font-size: 32px; font-weight: 800; }
        .charts-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 30px;
        }
        .chart-card {
            background: white;
            border-radius: 20px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .chart-card h3 { font-size: 16px; margin-bottom: 16px; }
        .highlights-card {
            background: white;
            border-radius: 20px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .highlights-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 16px;
        }
        .btn-primary {
            background: #0A84FF;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 500;
        }
        .btn-print {
            background: #34C759;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 10px;
            cursor: pointer;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            background: white;
            border-radius: 24px;
            max-width: 500px;
            width: 90%;
            padding: 24px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
        }
        .info-row { margin-bottom: 16px; }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); position: fixed; z-index: 1000; }
            .main-content { margin-left: 0; }
            .charts-grid { grid-template-columns: 1fr; }
        }
        @media print {
            .sidebar, button, form, .modal { display: none !important; }
            .main-content { margin-left: 0; }
            .stat-card, .chart-card, .highlights-card { box-shadow: none; border: 1px solid #ddd; }
        }
    </style>
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-header">
            <h2><i class="fas fa-heartbeat"></i> VeeCare</h2>
            <p>Medical Centre</p>
        </div>
        <nav class="sidebar-nav">
            <div class="nav-item"><a href="../dashboard.php" class="nav-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a></div>
            <div class="nav-divider"></div>
            <div class="nav-category">Patients</div>
            <div class="nav-item"><a href="../patients/view_patients.php" class="nav-link"><i class="fas fa-users"></i> All Patients</a></div>
            <div class="nav-item"><a href="../patients/add_patient.php" class="nav-link"><i class="fas fa-user-plus"></i> Add Patient</a></div>
            <div class="nav-divider"></div>
            <div class="nav-category">Appointments</div>
            <div class="nav-item"><a href="../appointments/calendar.php" class="nav-link"><i class="fas fa-calendar-alt"></i> Calendar</a></div>
            <div class="nav-item"><a href="../appointments/add_appointment.php" class="nav-link"><i class="fas fa-calendar-plus"></i> Schedule</a></div>
            <div class="nav-item"><a href="../appointments/view_appointments.php" class="nav-link"><i class="fas fa-list"></i> All Appointments</a></div>
            <div class="nav-divider"></div>
            <div class="nav-category">Clinical</div>
            <div class="nav-item"><a href="../prescriptions/view_prescriptions.php" class="nav-link"><i class="fas fa-prescription-bottle"></i> Prescriptions</a></div>
            <div class="nav-item"><a href="../treatments/diagnosis.php" class="nav-link"><i class="fas fa-notes-medical"></i> Diagnosis</a></div>
            <div class="nav-item"><a href="../treatments/procedures.php" class="nav-link"><i class="fas fa-syringe"></i> Procedures</a></div>
            <div class="nav-divider"></div>
            <div class="nav-category">Billing</div>
            <div class="nav-item"><a href="../billing/invoices.php" class="nav-link"><i class="fas fa-file-invoice-dollar"></i> Invoices</a></div>
            <div class="nav-item"><a href="../billing/payments.php" class="nav-link"><i class="fas fa-credit-card"></i> Payments</a></div>
            <div class="nav-item"><a href="../billing/receipts.php" class="nav-link"><i class="fas fa-receipt"></i> Receipts</a></div>
            <div class="nav-divider"></div>
            <div class="nav-category">Reports</div>
            <div class="nav-item"><a href="daily_reports.php" class="nav-link active"><i class="fas fa-chart-line"></i> Daily Report</a></div>
            <div class="nav-item"><a href="patient_reports.php" class="nav-link"><i class="fas fa-users"></i> Patient Report</a></div>
            <div class="nav-item"><a href="financial_reports.php" class="nav-link"><i class="fas fa-chart-pie"></i> Financial Report</a></div>
            <div class="nav-divider"></div>
            <div class="nav-category">System</div>
            <div class="nav-item"><a href="../settings/clinic_settings.php" class="nav-link"><i class="fas fa-cog"></i> Settings</a></div>
            <div class="nav-item"><a href="../logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
        </nav>
    </aside>

    <main class="main-content">
        <div class="report-header">
            <div>
                <h1>Daily Operations Report</h1>
                <p><?php echo date('l, F d, Y', strtotime($selected_date)); ?></p>
            </div>
            <div style="display: flex; gap: 10px;">
                <form method="GET" action="" id="dateForm">
                    <input type="date" name="date" value="<?php echo $selected_date; ?>" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 10px;">
                    <button type="submit" class="btn-primary">View Report</button>
                </form>
                <button class="btn-print" onclick="window.print();"><i class="fas fa-print"></i> Print</button>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="stats-grid">
            <div class="stat-card"><h4>Total Patients</h4><div class="stat-number"><?php echo $total_patients; ?></div><small>New: <?php echo $new_patients; ?> | Returning: <?php echo $returning_patients; ?></small></div>
            <div class="stat-card"><h4>Consultations</h4><div class="stat-number"><?php echo $consultations; ?></div></div>
            <div class="stat-card"><h4>Procedures</h4><div class="stat-number"><?php echo $procedures; ?></div></div>
            <div class="stat-card"><h4>Prescriptions</h4><div class="stat-number"><?php echo $prescriptions; ?></div></div>
            <div class="stat-card"><h4>Total Revenue</h4><div class="stat-number">$<?php echo number_format($revenue, 2); ?></div></div>
        </div>

        <!-- Two-Column Charts -->
        <div class="charts-grid">
            <div class="chart-card">
                <h3>Patient Distribution by Condition</h3>
                <canvas id="conditionChart" style="height: 250px; width: 100%;"></canvas>
                <div style="margin-top: 16px; text-align: center;">
                    <span style="color:#0A84FF;">🔵 General: <?php echo $case_breakdown['general']; ?></span> |
                    <span style="color:#FF9500;">🟡 Chronic: <?php echo $case_breakdown['chronic']; ?></span> |
                    <span style="color:#FF3B30;">🔴 Trauma: <?php echo $case_breakdown['trauma']; ?></span>
                </div>
            </div>
            <div class="chart-card">
                <h3>Patient Flow by Hour</h3>
                <canvas id="flowChart" style="height: 250px; width: 100%;"></canvas>
            </div>
        </div>

        <!-- Highlights Card with "View" Button -->
        <div class="highlights-card">
            <div class="highlights-header">
                <h3>Daily Highlights</h3>
                <button id="viewSummaryBtn" class="btn-primary"><i class="fas fa-eye"></i> View Summary</button>
            </div>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                <div><strong>Busiest Hour:</strong> <?php echo $busiest_hour_formatted; ?> (<?php echo max($hourly_flow); ?> patients)</div>
                <div><strong>Dominant Case Type:</strong> <?php echo $dominant_label; ?></div>
                <div><strong>Total Activity:</strong> <?php echo $total_activity; ?> events</div>
            </div>
        </div>
    </main>

    <!-- Modal for Detailed Summary -->
    <div id="summaryModal" class="modal">
        <div class="modal-content">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="margin: 0;">Daily Report Summary</h2>
                <button id="closeModalBtn" style="background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button>
            </div>
            <div class="info-row"><strong>Date:</strong> <?php echo date('F d, Y', strtotime($selected_date)); ?></div>
            <div class="info-row"><strong>Total Patients:</strong> <?php echo $total_patients; ?> (New: <?php echo $new_patients; ?>, Returning: <?php echo $returning_patients; ?>)</div>
            <div class="info-row"><strong>Busiest Hour:</strong> <?php echo $busiest_hour_formatted; ?> (<?php echo max($hourly_flow); ?> patients)</div>
            <div class="info-row"><strong>Dominant Case Type:</strong> <?php echo $dominant_label; ?></div>
            <div class="info-row"><strong>Total Activity:</strong> <?php echo $total_activity; ?> (Consultations + Procedures + Prescriptions)</div>
            <div class="info-row"><strong>Revenue:</strong> $<?php echo number_format($revenue, 2); ?></div>
            <div class="info-row"><strong>Procedures:</strong> <?php echo $procedures; ?> | <strong>Prescriptions:</strong> <?php echo $prescriptions; ?></div>
            <button id="closeModalBtn2" class="btn-primary" style="width: 100%; margin-top: 10px;">Close</button>
        </div>
    </div>

    <script>
        // Condition Chart
        new Chart(document.getElementById('conditionChart'), {
            type: 'doughnut',
            data: {
                labels: ['General', 'Chronic', 'Trauma'],
                datasets: [{
                    data: [<?php echo $case_breakdown['general']; ?>, <?php echo $case_breakdown['chronic']; ?>, <?php echo $case_breakdown['trauma']; ?>],
                    backgroundColor: ['#0A84FF', '#FF9500', '#FF3B30']
                }]
            },
            options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { position: 'bottom' } } }
        });

        // Hourly Flow Chart
        const hours = <?php echo json_encode(array_map(function($h){ return date('g A', mktime($h,0,0)); }, range(0,23))); ?>;
        const flowData = <?php echo json_encode($hourly_flow); ?>;
        new Chart(document.getElementById('flowChart'), {
            type: 'line',
            data: {
                labels: hours,
                datasets: [{
                    label: 'Patients',
                    data: flowData,
                    borderColor: '#0A84FF',
                    backgroundColor: 'rgba(10,132,255,0.05)',
                    fill: true,
                    tension: 0.3,
                    pointBackgroundColor: '#0A84FF'
                }]
            },
            options: { responsive: true, maintainAspectRatio: true, scales: { y: { beginAtZero: true } } }
        });

        // Modal logic
        const modal = document.getElementById('summaryModal');
        const viewBtn = document.getElementById('viewSummaryBtn');
        const closeBtns = document.querySelectorAll('#closeModalBtn, #closeModalBtn2');
        viewBtn.onclick = () => modal.style.display = 'flex';
        closeBtns.forEach(btn => btn.onclick = () => modal.style.display = 'none');
        window.onclick = (e) => { if (e.target === modal) modal.style.display = 'none'; };

        // Optional: Ensure form submission works (already works, but prevent default if needed)
        // No extra code needed – the form will submit and reload the page with the new date.
    </script>
</body>
</html>