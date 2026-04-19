<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
require_once '../config/database.php';
$conn = getConnection();

// Get filters
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$condition_filter = isset($_GET['condition']) ? $_GET['condition'] : '';
$age_group = isset($_GET['age_group']) ? $_GET['age_group'] : '';
$gender_filter = isset($_GET['gender']) ? $_GET['gender'] : '';

// Build WHERE clause for patients (based on appointments within date range)
$where = "1=1";
$params = [];
$types = "";
if (!empty($condition_filter)) {
    $where .= " AND p.condition_category = ?";
    $params[] = $condition_filter;
    $types .= "s";
}
if (!empty($gender_filter)) {
    $where .= " AND p.gender = ?";
    $params[] = $gender_filter;
    $types .= "s";
}
if (!empty($age_group)) {
    if ($age_group == '0-18') $where .= " AND TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) BETWEEN 0 AND 18";
    elseif ($age_group == '19-35') $where .= " AND TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) BETWEEN 19 AND 35";
    elseif ($age_group == '36-50') $where .= " AND TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) BETWEEN 36 AND 50";
    elseif ($age_group == '51-65') $where .= " AND TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) BETWEEN 51 AND 65";
    elseif ($age_group == '65+') $where .= " AND TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) > 65";
}

// 1. Total patients (in the selected date range, those who had appointments)
$stmt = $conn->prepare("SELECT COUNT(DISTINCT p.id) as total FROM patients p JOIN appointments a ON p.id = a.patient_id WHERE a.appointment_date BETWEEN ? AND ? AND $where");
$params2 = [$date_from, $date_to];
$all_params = array_merge($params2, $params);
$types2 = "ss" . $types;
$stmt->bind_param($types2, ...$all_params);
$stmt->execute();
$total_patients = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
$stmt->close();

// 2. New patients (first appointment in the period)
$stmt = $conn->prepare("SELECT COUNT(DISTINCT p.id) as new_count FROM patients p JOIN appointments a ON p.id = a.patient_id WHERE a.appointment_date BETWEEN ? AND ? AND NOT EXISTS (SELECT 1 FROM appointments a2 WHERE a2.patient_id = p.id AND a2.appointment_date < ?) AND $where");
$all_params_new = array_merge($params2, [$date_from], $params);
$types_new = "sss" . $types;
$stmt->bind_param($types_new, ...$all_params_new);
$stmt->execute();
$new_patients = $stmt->get_result()->fetch_assoc()['new_count'] ?? 0;
$stmt->close();
$returning_patients = $total_patients - $new_patients;

// 3. Chronic cases (patients with chronic condition category)
$stmt = $conn->prepare("SELECT COUNT(DISTINCT p.id) as chronic FROM patients p JOIN appointments a ON p.id = a.patient_id WHERE a.appointment_date BETWEEN ? AND ? AND p.condition_category = 'chronic' AND $where");
$stmt->bind_param($types2, ...$all_params);
$stmt->execute();
$chronic_cases = $stmt->get_result()->fetch_assoc()['chronic'] ?? 0;
$stmt->close();

// 4. Trauma cases
$stmt = $conn->prepare("SELECT COUNT(DISTINCT p.id) as trauma FROM patients p JOIN appointments a ON p.id = a.patient_id WHERE a.appointment_date BETWEEN ? AND ? AND p.condition_category = 'trauma' AND $where");
$stmt->bind_param($types2, ...$all_params);
$stmt->execute();
$trauma_cases = $stmt->get_result()->fetch_assoc()['trauma'] ?? 0;
$stmt->close();

// 5. Top diagnoses (disease distribution)
$top_diagnoses = [];
$stmt = $conn->prepare("SELECT d.diagnosis, COUNT(*) as count FROM diagnosis d JOIN patients p ON d.patient_id = p.id WHERE d.diagnosis_date BETWEEN ? AND ? AND $where GROUP BY d.diagnosis ORDER BY count DESC LIMIT 5");
$stmt->bind_param($types2, ...$all_params);
$stmt->execute();
$top_diagnoses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// 6. Gender distribution
$gender_stats = ['Male' => 0, 'Female' => 0, 'Other' => 0];
$stmt = $conn->prepare("SELECT p.gender, COUNT(DISTINCT p.id) as count FROM patients p JOIN appointments a ON p.id = a.patient_id WHERE a.appointment_date BETWEEN ? AND ? AND $where GROUP BY p.gender");
$stmt->bind_param($types2, ...$all_params);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $gender_stats[$row['gender']] = $row['count'];
}
$stmt->close();

// 7. Frequently visiting patients (top 5 by appointment count)
$frequent_patients = [];
$stmt = $conn->prepare("SELECT p.full_name, COUNT(a.id) as visits FROM patients p JOIN appointments a ON p.id = a.patient_id WHERE a.appointment_date BETWEEN ? AND ? AND $where GROUP BY p.id ORDER BY visits DESC LIMIT 5");
$stmt->bind_param($types2, ...$all_params);
$stmt->execute();
$frequent_patients = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// 8. High-risk patients (more than 3 visits in the period OR chronic condition)
$high_risk = [];
$stmt = $conn->prepare("SELECT p.full_name, COUNT(a.id) as visits, p.condition_category FROM patients p JOIN appointments a ON p.id = a.patient_id WHERE a.appointment_date BETWEEN ? AND ? AND $where GROUP BY p.id HAVING visits > 3 OR p.condition_category = 'chronic' ORDER BY visits DESC LIMIT 10");
$stmt->bind_param($types2, ...$all_params);
$stmt->execute();
$high_risk = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Helper for modal summary
$most_common_diagnosis = !empty($top_diagnoses) ? $top_diagnoses[0]['diagnosis'] : 'None';
$most_frequent_patient = !empty($frequent_patients) ? $frequent_patients[0]['full_name'] : 'None';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Analytics | VeeCare</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f0f2f5; display: flex; min-height: 100vh; }
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
        .filter-bar {
            background: white;
            border-radius: 20px;
            padding: 20px 24px;
            margin-bottom: 24px;
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            align-items: flex-end;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .filter-group { display: flex; flex-direction: column; gap: 6px; }
        .filter-group label { font-size: 12px; font-weight: 600; }
        .filter-group input, .filter-group select { padding: 8px 12px; border: 1px solid #ddd; border-radius: 10px; }
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
        .data-card {
            background: white;
            border-radius: 20px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            margin-bottom: 24px;
        }
        .data-card h3 { font-size: 16px; margin-bottom: 16px; }
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        .data-table th, .data-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eef2f6;
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
            .stat-card, .chart-card, .data-card { box-shadow: none; border: 1px solid #ddd; }
        }
    </style>
</head>
<body>
    <!-- Sidebar (same as daily_reports.php) -->
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
            <div class="nav-item"><a href="daily_reports.php" class="nav-link"><i class="fas fa-chart-line"></i> Daily Report</a></div>
            <div class="nav-item"><a href="patient_reports.php" class="nav-link active"><i class="fas fa-users"></i> Patient Report</a></div>
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
                <h1>Patient Analytics Report</h1>
                <p><?php echo date('F d, Y', strtotime($date_from)); ?> – <?php echo date('F d, Y', strtotime($date_to)); ?></p>
            </div>
            <div style="display: flex; gap: 10px;">
                <button class="btn-print" onclick="window.print();"><i class="fas fa-print"></i> Print</button>
            </div>
        </div>

        <!-- Filter Bar -->
        <form method="GET" class="filter-bar">
            <div class="filter-group"><label>Date From</label><input type="date" name="date_from" value="<?php echo $date_from; ?>"></div>
            <div class="filter-group"><label>Date To</label><input type="date" name="date_to" value="<?php echo $date_to; ?>"></div>
            <div class="filter-group"><label>Condition</label>
                <select name="condition">
                    <option value="">All</option>
                    <option value="general" <?php echo $condition_filter=='general'?'selected':''; ?>>General</option>
                    <option value="chronic" <?php echo $condition_filter=='chronic'?'selected':''; ?>>Chronic</option>
                    <option value="trauma" <?php echo $condition_filter=='trauma'?'selected':''; ?>>Trauma</option>
                </select>
            </div>
            <div class="filter-group"><label>Age Group</label>
                <select name="age_group">
                    <option value="">All</option>
                    <option <?php echo $age_group=='0-18'?'selected':''; ?>>0-18</option>
                    <option <?php echo $age_group=='19-35'?'selected':''; ?>>19-35</option>
                    <option <?php echo $age_group=='36-50'?'selected':''; ?>>36-50</option>
                    <option <?php echo $age_group=='51-65'?'selected':''; ?>>51-65</option>
                    <option <?php echo $age_group=='65+'?'selected':''; ?>>65+</option>
                </select>
            </div>
            <div class="filter-group"><label>Gender</label>
                <select name="gender">
                    <option value="">All</option>
                    <option value="Male" <?php echo $gender_filter=='Male'?'selected':''; ?>>Male</option>
                    <option value="Female" <?php echo $gender_filter=='Female'?'selected':''; ?>>Female</option>
                    <option value="Other" <?php echo $gender_filter=='Other'?'selected':''; ?>>Other</option>
                </select>
            </div>
            <div class="filter-group"><button type="submit" class="btn-primary">View Report</button></div>
            <div class="filter-group"><a href="patient_reports.php" class="btn-primary" style="background:#6c86a3;">Clear</a></div>
        </form>

        <!-- Summary Cards -->
        <div class="stats-grid">
            <div class="stat-card"><h4>Total Patients</h4><div class="stat-number"><?php echo $total_patients; ?></div><small>New: <?php echo $new_patients; ?> | Returning: <?php echo $returning_patients; ?></small></div>
            <div class="stat-card"><h4>Chronic Cases</h4><div class="stat-number"><?php echo $chronic_cases; ?></div></div>
            <div class="stat-card"><h4>Trauma Cases</h4><div class="stat-number"><?php echo $trauma_cases; ?></div></div>
            <div class="stat-card"><h4>Gender Distribution</h4><div class="stat-number">♂ <?php echo $gender_stats['Male']; ?> | ♀ <?php echo $gender_stats['Female']; ?></div></div>
        </div>

        <!-- Charts Grid -->
        <div class="charts-grid">
            <div class="chart-card">
                <h3>Top Diagnoses</h3>
                <canvas id="diagnosisChart" style="height: 250px; width: 100%;"></canvas>
            </div>
            <div class="chart-card">
                <h3>Gender Distribution</h3>
                <canvas id="genderChart" style="height: 250px; width: 100%;"></canvas>
            </div>
        </div>

        <!-- Frequently Visiting Patients -->
        <div class="data-card">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 16px;">
                <h3 style="margin: 0;">Frequently Visiting Patients</h3>
                <button id="viewSummaryBtn" class="btn-primary"><i class="fas fa-eye"></i> View Summary</button>
            </div>
            <?php if (empty($frequent_patients)): ?>
                <p>No data available.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead><tr><th>Patient Name</th><th>Visits (within period)</th></tr></thead>
                    <tbody>
                        <?php foreach ($frequent_patients as $p): ?>
                            <tr><td><?php echo htmlspecialchars($p['full_name']); ?></td><td><?php echo $p['visits']; ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- High-Risk Patients -->
        <div class="data-card">
            <h3>High‑Risk Patients</h3>
            <?php if (empty($high_risk)): ?>
                <p>No high‑risk patients identified.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead><tr><th>Patient Name</th><th>Visits</th><th>Condition Category</th></tr></thead>
                    <tbody>
                        <?php foreach ($high_risk as $hr): ?>
                            <tr><td><?php echo htmlspecialchars($hr['full_name']); ?></td><td><?php echo $hr['visits']; ?></td><td><?php echo ucfirst($hr['condition_category'] ?? 'General'); ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </main>

    <!-- Modal for Patient Summary -->
    <div id="summaryModal" class="modal">
        <div class="modal-content">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="margin: 0;">Patient Insights Summary</h2>
                <button id="closeModalBtn" style="background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button>
            </div>
            <div class="info-row"><strong>Period:</strong> <?php echo date('d M Y', strtotime($date_from)); ?> – <?php echo date('d M Y', strtotime($date_to)); ?></div>
            <div class="info-row"><strong>Most Common Diagnosis:</strong> <?php echo htmlspecialchars($most_common_diagnosis); ?></div>
            <div class="info-row"><strong>Most Frequent Patient:</strong> <?php echo htmlspecialchars($most_frequent_patient); ?></div>
            <div class="info-row"><strong>Follow‑up Needs:</strong> <?php echo $chronic_cases + $trauma_cases; ?> patients require ongoing care (chronic/trauma).</div>
            <div class="info-row"><strong>New vs Returning:</strong> <?php echo $new_patients; ?> new, <?php echo $returning_patients; ?> returning.</div>
            <button id="closeModalBtn2" class="btn-primary" style="width: 100%; margin-top: 10px;">Close</button>
        </div>
    </div>

    <script>
        // Diagnosis Chart (bar)
        const diagLabels = <?php echo json_encode(array_column($top_diagnoses, 'diagnosis')); ?>;
        const diagData = <?php echo json_encode(array_column($top_diagnoses, 'count')); ?>;
        new Chart(document.getElementById('diagnosisChart'), {
            type: 'bar',
            data: {
                labels: diagLabels,
                datasets: [{
                    label: 'Number of Cases',
                    data: diagData,
                    backgroundColor: '#0A84FF',
                    borderRadius: 8
                }]
            },
            options: { responsive: true, maintainAspectRatio: true, scales: { y: { beginAtZero: true } } }
        });

        // Gender Chart (doughnut)
        new Chart(document.getElementById('genderChart'), {
            type: 'doughnut',
            data: {
                labels: ['Male', 'Female', 'Other'],
                datasets: [{
                    data: [<?php echo $gender_stats['Male']; ?>, <?php echo $gender_stats['Female']; ?>, <?php echo $gender_stats['Other']; ?>],
                    backgroundColor: ['#0A84FF', '#34C759', '#AF52DE']
                }]
            },
            options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { position: 'bottom' } } }
        });

        // Modal logic
        const modal = document.getElementById('summaryModal');
        const viewBtn = document.getElementById('viewSummaryBtn');
        const closeBtns = document.querySelectorAll('#closeModalBtn, #closeModalBtn2');
        if (viewBtn) viewBtn.onclick = () => modal.style.display = 'flex';
        closeBtns.forEach(btn => btn.onclick = () => modal.style.display = 'none');
        window.onclick = (e) => { if (e.target === modal) modal.style.display = 'none'; };
    </script>
</body>
</html>