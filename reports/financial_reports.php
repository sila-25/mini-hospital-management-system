<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
require_once '../config/database.php';
$conn = getConnection();

// Get currency symbol from database
$currency_symbol = 'KSh'; // default fallback
$result = $conn->query("SELECT setting_value FROM clinic_settings WHERE setting_key = 'currency_symbol'");
if ($result && $row = $result->fetch_assoc()) {
    $currency_symbol = $row['setting_value'];
}

// Get filters
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$service_category = isset($_GET['service']) ? $_GET['service'] : '';

// Helper: payment date column
$has_payment_date = $conn->query("SHOW COLUMNS FROM payments LIKE 'payment_date'")->num_rows > 0;
$payment_date_col = $has_payment_date ? 'payment_date' : 'created_at';

// 1. Total revenue (payments completed)
$stmt = $conn->prepare("SELECT SUM(amount) as total_revenue FROM payments WHERE DATE($payment_date_col) BETWEEN ? AND ? AND status = 'completed'");
$stmt->bind_param("ss", $date_from, $date_to);
$stmt->execute();
$total_revenue = $stmt->get_result()->fetch_assoc()['total_revenue'] ?? 0;
$stmt->close();

// 2. Total payments received (same as revenue for completed)
$total_payments = $total_revenue;

// 3. Outstanding balance (unpaid invoices)
$outstanding_result = $conn->query("SELECT SUM(balance_due) as outstanding FROM invoices WHERE status != 'paid'");
$outstanding_balance = $outstanding_result->fetch_assoc()['outstanding'] ?? 0;

// 4. Number of invoices (in the period)
$stmt = $conn->prepare("SELECT COUNT(*) as invoice_count FROM invoices WHERE invoice_date BETWEEN ? AND ?");
$stmt->bind_param("ss", $date_from, $date_to);
$stmt->execute();
$invoice_count = $stmt->get_result()->fetch_assoc()['invoice_count'] ?? 0;
$stmt->close();

// 5. Revenue breakdown by service category (from invoice_items)
$service_revenue = ['Consultation' => 0, 'Procedure' => 0, 'Imaging' => 0, 'Medication' => 0, 'Other' => 0];
$stmt = $conn->prepare("SELECT ii.description, ii.total_price FROM invoice_items ii JOIN invoices inv ON ii.invoice_id = inv.id WHERE inv.invoice_date BETWEEN ? AND ?");
$stmt->bind_param("ss", $date_from, $date_to);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
foreach ($items as $item) {
    $desc = strtolower($item['description']);
    if (strpos($desc, 'consult') !== false || strpos($desc, 'visit') !== false) {
        $service_revenue['Consultation'] += $item['total_price'];
    } elseif (strpos($desc, 'procedure') !== false || strpos($desc, 'injection') !== false || strpos($desc, 'dressing') !== false) {
        $service_revenue['Procedure'] += $item['total_price'];
    } elseif (strpos($desc, 'x-ray') !== false || strpos($desc, 'imaging') !== false || strpos($desc, 'scan') !== false) {
        $service_revenue['Imaging'] += $item['total_price'];
    } elseif (strpos($desc, 'medication') !== false || strpos($desc, 'drug') !== false || strpos($desc, 'prescription') !== false) {
        $service_revenue['Medication'] += $item['total_price'];
    } else {
        $service_revenue['Other'] += $item['total_price'];
    }
}

// 6. Payment methods distribution
$payment_methods = [];
$stmt = $conn->prepare("SELECT payment_method, SUM(amount) as total FROM payments WHERE DATE($payment_date_col) BETWEEN ? AND ? AND status = 'completed' GROUP BY payment_method");
$stmt->bind_param("ss", $date_from, $date_to);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $payment_methods[$row['payment_method']] = $row['total'];
}
$stmt->close();

// 7. Recent unpaid invoices (top 10)
$unpaid_invoices = [];
$stmt = $conn->prepare("SELECT inv.invoice_number, p.full_name as patient_name, inv.invoice_date, inv.due_date, inv.balance_due, inv.status FROM invoices inv JOIN patients p ON inv.patient_id = p.id WHERE inv.balance_due > 0 AND inv.invoice_date BETWEEN ? AND ? ORDER BY inv.due_date ASC LIMIT 10");
$stmt->bind_param("ss", $date_from, $date_to);
$stmt->execute();
$unpaid_invoices = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Helper for modal summary – avoid max() on empty arrays
$top_service = 'None';
if (!empty($service_revenue) && max($service_revenue) > 0) {
    $top_service = array_keys($service_revenue, max($service_revenue))[0];
}
$top_payment_method = 'None';
if (!empty($payment_methods) && max($payment_methods) > 0) {
    $top_payment_method = array_keys($payment_methods, max($payment_methods))[0];
}

include '../includes/header.php';
include '../includes/sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Reports | VeeCare</title>
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
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
        .stat-number { font-size: 28px; font-weight: 800; }
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
            <div class="nav-item"><a href="patient_reports.php" class="nav-link"><i class="fas fa-users"></i> Patient Report</a></div>
            <div class="nav-item"><a href="financial_reports.php" class="nav-link active"><i class="fas fa-chart-pie"></i> Financial Report</a></div>
            <div class="nav-divider"></div>
            <div class="nav-category">System</div>
            <div class="nav-item"><a href="../settings/clinic_settings.php" class="nav-link"><i class="fas fa-cog"></i> Settings</a></div>
            <div class="nav-item"><a href="../logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
        </nav>
    </aside>

    <main class="main-content">
        <div class="report-header">
            <div>
                <h1>Financial Performance Report</h1>
                <p><?php echo date('F d, Y', strtotime($date_from)); ?> – <?php echo date('F d, Y', strtotime($date_to)); ?></p>
            </div>
            <div style="display: flex; gap: 10px;">
                <button class="btn-print" onclick="window.print();"><i class="fas fa-print"></i> Print</button>
            </div>
        </div>

        <form method="GET" class="filter-bar">
            <div class="filter-group"><label>Date From</label><input type="date" name="date_from" value="<?php echo $date_from; ?>"></div>
            <div class="filter-group"><label>Date To</label><input type="date" name="date_to" value="<?php echo $date_to; ?>"></div>
            <div class="filter-group"><label>Service Category</label>
                <select name="service">
                    <option value="">All Services</option>
                    <option value="consultation" <?php echo $service_category=='consultation'?'selected':''; ?>>Consultation</option>
                    <option value="procedure" <?php echo $service_category=='procedure'?'selected':''; ?>>Procedure</option>
                    <option value="imaging" <?php echo $service_category=='imaging'?'selected':''; ?>>Imaging</option>
                    <option value="medication" <?php echo $service_category=='medication'?'selected':''; ?>>Medication</option>
                </select>
            </div>
            <div class="filter-group"><button type="submit" class="btn-primary">View Report</button></div>
            <div class="filter-group"><a href="financial_reports.php" class="btn-primary" style="background:#6c86a3;">Clear</a></div>
        </form>

        <div class="stats-grid">
            <div class="stat-card"><h4>Total Revenue</h4><div class="stat-number" style="color:#34C759;"><?php echo $currency_symbol; ?> <?php echo number_format($total_revenue, 2); ?></div></div>
            <div class="stat-card"><h4>Payments Received</h4><div class="stat-number"><?php echo $currency_symbol; ?> <?php echo number_format($total_payments, 2); ?></div></div>
            <div class="stat-card"><h4>Outstanding Balance</h4><div class="stat-number" style="color:#FF3B30;"><?php echo $currency_symbol; ?> <?php echo number_format($outstanding_balance, 2); ?></div></div>
            <div class="stat-card"><h4>Invoices Issued</h4><div class="stat-number"><?php echo $invoice_count; ?></div></div>
        </div>

        <div class="charts-grid">
            <div class="chart-card">
                <h3>Revenue by Service Category</h3>
                <canvas id="serviceChart" style="height: 250px; width: 100%;"></canvas>
            </div>
            <div class="chart-card">
                <h3>Payment Methods Distribution</h3>
                <canvas id="paymentChart" style="height: 250px; width: 100%;"></canvas>
            </div>
        </div>

        <div class="data-card">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 16px;">
                <h3 style="margin: 0;">Recent Unpaid Invoices</h3>
                <button id="viewSummaryBtn" class="btn-primary"><i class="fas fa-eye"></i> View Summary</button>
            </div>
            <?php if (empty($unpaid_invoices)): ?>
                <p>No unpaid invoices in this period.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead><tr><th>Invoice #</th><th>Patient</th><th>Date</th><th>Due Date</th><th>Balance Due</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php foreach ($unpaid_invoices as $inv): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($inv['invoice_number']); ?></td>
                                <td><?php echo htmlspecialchars($inv['patient_name']); ?></td>
                                <td><?php echo date('d M Y', strtotime($inv['invoice_date'])); ?></td>
                                <td><?php echo date('d M Y', strtotime($inv['due_date'])); ?></td>
                                <td><?php echo $currency_symbol; ?> <?php echo number_format($inv['balance_due'], 2); ?></td>
                                <td><?php echo ucfirst(str_replace('_', ' ', $inv['status'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </main>

    <div id="summaryModal" class="modal">
        <div class="modal-content">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="margin: 0;">Financial Insights</h2>
                <button id="closeModalBtn" style="background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button>
            </div>
            <div class="info-row"><strong>Period:</strong> <?php echo date('d M Y', strtotime($date_from)); ?> – <?php echo date('d M Y', strtotime($date_to)); ?></div>
            <div class="info-row"><strong>Total Earnings:</strong> <?php echo $currency_symbol; ?> <?php echo number_format($total_revenue, 2); ?></div>
            <div class="info-row"><strong>Outstanding Amount:</strong> <?php echo $currency_symbol; ?> <?php echo number_format($outstanding_balance, 2); ?></div>
            <div class="info-row"><strong>Top Revenue Source:</strong> <?php echo ucfirst($top_service); ?></div>
            <div class="info-row"><strong>Most Used Payment Method:</strong> <?php echo ucfirst(str_replace('_', ' ', $top_payment_method)); ?></div>
            <div class="info-row"><strong>Invoices with Balance:</strong> <?php echo count($unpaid_invoices); ?></div>
            <button id="closeModalBtn2" class="btn-primary" style="width: 100%; margin-top: 10px;">Close</button>
        </div>
    </div>

    <script>
        const serviceLabels = <?php echo json_encode(array_keys($service_revenue)); ?>;
        const serviceData = <?php echo json_encode(array_values($service_revenue)); ?>;
        new Chart(document.getElementById('serviceChart'), {
            type: 'bar',
            data: {
                labels: serviceLabels,
                datasets: [{
                    label: 'Revenue (<?php echo $currency_symbol; ?>)',
                    data: serviceData,
                    backgroundColor: '#0A84FF',
                    borderRadius: 8
                }]
            },
            options: { responsive: true, maintainAspectRatio: true, scales: { y: { beginAtZero: true, title: { display: true, text: '<?php echo $currency_symbol; ?>' } } } }
        });

        const methodLabels = <?php echo json_encode(array_keys($payment_methods)); ?>;
        const methodData = <?php echo json_encode(array_values($payment_methods)); ?>;
        if (methodLabels.length > 0) {
            new Chart(document.getElementById('paymentChart'), {
                type: 'doughnut',
                data: {
                    labels: methodLabels.map(l => l.replace('_', ' ').toUpperCase()),
                    datasets: [{ data: methodData, backgroundColor: ['#0A84FF', '#34C759', '#FF9500', '#AF52DE'] }]
                },
                options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { position: 'bottom' } } }
            });
        } else {
            document.getElementById('paymentChart').parentElement.innerHTML = '<p class="info-row">No payment data available for this period.</p>';
        }

        const modal = document.getElementById('summaryModal');
        const viewBtn = document.getElementById('viewSummaryBtn');
        const closeBtns = document.querySelectorAll('#closeModalBtn, #closeModalBtn2');
        if (viewBtn) viewBtn.onclick = () => modal.style.display = 'flex';
        closeBtns.forEach(btn => btn.onclick = () => modal.style.display = 'none');
        window.onclick = (e) => { if (e.target === modal) modal.style.display = 'none'; };
    </script>
</body>
</html>