<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
require_once 'config/database.php';
$conn = getConnection();

$user_name = $_SESSION['user_name'] ?? 'Staff';
$user_role = $_SESSION['user_role'] ?? '';

// Get currency symbol from database
$currency_symbol = 'KSh';
$result = $conn->query("SELECT setting_value FROM clinic_settings WHERE setting_key = 'currency_symbol'");
if ($result && $row = $result->fetch_assoc()) {
    $currency_symbol = $row['setting_value'];
}

function formatCurrency($amount, $symbol) {
    return $symbol . ' ' . number_format($amount, 2);
}

// Fetch dashboard metrics
$total_patients = 0;
$today_appointments = 0;
$monthly_revenue = 0;
$pending_tasks = 0;
$recent_appointments = [];
$recent_patients = [];
$upcoming_appointments = [];

if ($conn) {
    $res = $conn->query("SELECT COUNT(*) as total FROM patients");
    if ($res) $total_patients = $res->fetch_assoc()['total'] ?? 0;
    
    $today = date('Y-m-d');
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM appointments WHERE DATE(appointment_date) = ?");
    if ($stmt) {
        $stmt->bind_param("s", $today);
        $stmt->execute();
        $today_appointments = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
        $stmt->close();
    }
    
    $first_day = date('Y-m-01');
    $has_payment_date = $conn->query("SHOW COLUMNS FROM payments LIKE 'payment_date'")->num_rows > 0;
    $date_col = $has_payment_date ? 'payment_date' : 'created_at';
    $stmt = $conn->prepare("SELECT SUM(amount) as total FROM payments WHERE DATE($date_col) >= ? AND status = 'completed'");
    if ($stmt) {
        $stmt->bind_param("s", $first_day);
        $stmt->execute();
        $monthly_revenue = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
        $stmt->close();
    }
    
    $res = $conn->query("SELECT COUNT(*) as total FROM appointments WHERE status = 'scheduled' AND appointment_date >= CURDATE()");
    if ($res) $pending_tasks = $res->fetch_assoc()['total'] ?? 0;
    
    $res = $conn->query("
        SELECT a.id, a.appointment_date, a.status, p.full_name as patient_name
        FROM appointments a JOIN patients p ON a.patient_id = p.id
        ORDER BY a.appointment_date DESC LIMIT 5
    ");
    if ($res) while ($row = $res->fetch_assoc()) $recent_appointments[] = $row;
    
    $res = $conn->query("
        SELECT id, full_name, email, phone, created_at
        FROM patients ORDER BY created_at DESC LIMIT 5
    ");
    if ($res) while ($row = $res->fetch_assoc()) $recent_patients[] = $row;
    
    $res = $conn->query("
        SELECT a.id, a.appointment_date, a.appointment_time, a.status, p.full_name as patient_name
        FROM appointments a JOIN patients p ON a.patient_id = p.id
        WHERE a.appointment_date >= CURDATE() AND a.status = 'scheduled'
        ORDER BY a.appointment_date ASC LIMIT 5
    ");
    if ($res) while ($row = $res->fetch_assoc()) $upcoming_appointments[] = $row;
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
    
    * {
        font-family: 'Plus Jakarta Sans', 'Inter', sans-serif;
    }
    
    .dashboard-wrapper {
        margin-left: 280px;
        padding: 28px 32px;
        background: linear-gradient(135deg, #f5f7fa 0%, #eef2f8 100%);
        min-height: 100vh;
    }
    
    /* Header Section */
    .dashboard-header {
        background: rgba(255,255,255,0.98);
        backdrop-filter: blur(10px);
        border-radius: 28px;
        padding: 20px 32px;
        margin-bottom: 32px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 20px;
        box-shadow: 0 8px 24px rgba(0,0,0,0.04), 0 0 0 1px rgba(10,132,255,0.05);
        border: 1px solid rgba(10,132,255,0.1);
    }
    .welcome h1 {
        font-size: 28px;
        font-weight: 800;
        margin: 0 0 4px 0;
        background: linear-gradient(135deg, #1a1a2e, #0A84FF);
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
    }
    .welcome p {
        color: #6c86a3;
        margin: 0;
        font-size: 14px;
        font-weight: 500;
    }
    .user-card {
        display: flex;
        align-items: center;
        gap: 16px;
        background: linear-gradient(135deg, #f8fafc, #ffffff);
        padding: 6px 20px 6px 12px;
        border-radius: 60px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        border: 1px solid rgba(10,132,255,0.1);
    }
    .user-avatar-lg {
        width: 48px;
        height: 48px;
        background: linear-gradient(135deg, #0A84FF, #34C759);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 800;
        font-size: 18px;
        box-shadow: 0 4px 12px rgba(10,132,255,0.3);
    }
    .user-details {
        text-align: right;
    }
    .user-name-lg {
        font-weight: 800;
        font-size: 15px;
        color: #1a1a2e;
    }
    .user-role-lg {
        font-size: 12px;
        color: #0A84FF;
        font-weight: 600;
    }
    .logout-link {
        background: linear-gradient(135deg, #FF3B30, #d32f2f);
        color: white;
        padding: 8px 20px;
        border-radius: 40px;
        text-decoration: none;
        font-size: 13px;
        font-weight: 600;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        gap: 8px;
        box-shadow: 0 2px 8px rgba(255,59,48,0.2);
    }
    .logout-link:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(255,59,48,0.3);
    }

    /* Stats Grid */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 24px;
        margin-bottom: 32px;
    }
    .stat-card {
        background: white;
        border-radius: 24px;
        padding: 24px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: 0 4px 12px rgba(0,0,0,0.04);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        border: 1px solid rgba(10,132,255,0.08);
        position: relative;
        overflow: hidden;
    }
    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 4px;
        background: linear-gradient(90deg, #0A84FF, #34C759);
    }
    .stat-card:nth-child(2)::before { background: linear-gradient(90deg, #AF52DE, #FF9500); }
    .stat-card:nth-child(3)::before { background: linear-gradient(90deg, #34C759, #0A84FF); }
    .stat-card:nth-child(4)::before { background: linear-gradient(90deg, #FF9500, #FF3B30); }
    .stat-card:hover {
        transform: translateY(-6px);
        box-shadow: 0 20px 30px -12px rgba(0,0,0,0.12);
    }
    .stat-info h3 {
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: #8E8E93;
        margin-bottom: 8px;
    }
    .stat-number {
        font-size: 34px;
        font-weight: 800;
        color: #1a1a2e;
    }
    .stat-trend {
        font-size: 12px;
        margin-top: 8px;
        display: flex;
        align-items: center;
        gap: 4px;
        font-weight: 600;
    }
    .stat-trend.up { color: #34C759; }
    .stat-icon {
        width: 56px;
        height: 56px;
        border-radius: 18px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .stat-card:nth-child(1) .stat-icon { background: linear-gradient(135deg, rgba(10,132,255,0.15), rgba(10,132,255,0.05)); }
    .stat-card:nth-child(2) .stat-icon { background: linear-gradient(135deg, rgba(175,82,222,0.15), rgba(175,82,222,0.05)); }
    .stat-card:nth-child(3) .stat-icon { background: linear-gradient(135deg, rgba(52,199,89,0.15), rgba(52,199,89,0.05)); }
    .stat-card:nth-child(4) .stat-icon { background: linear-gradient(135deg, rgba(255,149,0,0.15), rgba(255,149,0,0.05)); }
    .stat-icon i { font-size: 26px; }
    .stat-card:nth-child(1) .stat-icon i { color: #0A84FF; }
    .stat-card:nth-child(2) .stat-icon i { color: #AF52DE; }
    .stat-card:nth-child(3) .stat-icon i { color: #34C759; }
    .stat-card:nth-child(4) .stat-icon i { color: #FF9500; }

    /* Content Grid */
    .content-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 28px;
        margin-bottom: 32px;
    }
    .card {
        background: white;
        border-radius: 24px;
        overflow: hidden;
        box-shadow: 0 4px 12px rgba(0,0,0,0.04);
        transition: all 0.3s ease;
        border: 1px solid rgba(10,132,255,0.08);
    }
    .card:hover {
        transform: translateY(-4px);
        box-shadow: 0 20px 30px -12px rgba(0,0,0,0.1);
        border-color: rgba(10,132,255,0.2);
    }
    .card-header {
        padding: 20px 24px;
        border-bottom: 1px solid #eef2f6;
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: linear-gradient(135deg, #fafcff, #ffffff);
    }
    .card-header h3 {
        font-size: 16px;
        font-weight: 800;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
        color: #1a1a2e;
    }
    .card-header h3 i { font-size: 18px; }
    .card-header a {
        color: #0A84FF;
        text-decoration: none;
        font-size: 13px;
        font-weight: 600;
        padding: 6px 12px;
        border-radius: 20px;
        transition: all 0.2s;
    }
    .card-header a:hover {
        background: rgba(10,132,255,0.1);
    }
    .card-body { padding: 20px 24px; }

    /* Tables */
    .data-table {
        width: 100%;
        border-collapse: collapse;
    }
    .data-table th {
        text-align: left;
        padding: 12px 0;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #8E8E93;
        border-bottom: 1px solid #eef2f6;
    }
    .data-table td {
        padding: 12px 0;
        font-size: 13px;
        font-weight: 500;
        border-bottom: 1px solid #f5f5f7;
    }
    .data-table tr:last-child td { border-bottom: none; }
    .data-table tr:hover td { color: #0A84FF; }
    .badge {
        display: inline-block;
        padding: 5px 14px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 700;
    }
    .badge-scheduled { background: linear-gradient(135deg, rgba(10,132,255,0.12), rgba(10,132,255,0.06)); color: #0A84FF; }
    .badge-completed { background: linear-gradient(135deg, rgba(52,199,89,0.12), rgba(52,199,89,0.06)); color: #34C759; }
    .badge-cancelled { background: linear-gradient(135deg, rgba(255,59,48,0.12), rgba(255,59,48,0.06)); color: #FF3B30; }
    .empty-state {
        text-align: center;
        padding: 48px 20px;
        color: #8E8E93;
    }
    .empty-state i { font-size: 48px; margin-bottom: 12px; opacity: 0.5; }

    /* Quick Actions */
    .quick-actions {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    .action-btn {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 14px 20px;
        background: linear-gradient(135deg, #f8fafc, #ffffff);
        border-radius: 16px;
        text-decoration: none;
        color: #1a1a2e;
        transition: all 0.3s ease;
        border: 1px solid rgba(10,132,255,0.08);
        font-weight: 600;
    }
    .action-btn:hover {
        background: linear-gradient(135deg, rgba(10,132,255,0.05), rgba(52,199,89,0.03));
        border-color: rgba(10,132,255,0.2);
        transform: translateX(8px);
    }
    .action-btn i { width: 28px; font-size: 18px; }

    /* Footer */
    .dashboard-footer {
        text-align: center;
        padding-top: 28px;
        border-top: 1px solid #eef2f6;
        font-size: 12px;
        font-weight: 500;
        color: #8E8E93;
    }

    @media (max-width: 1200px) {
        .stats-grid { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 768px) {
        .dashboard-wrapper { margin-left: 0; padding: 20px; }
        .stats-grid { grid-template-columns: 1fr; }
        .content-grid { grid-template-columns: 1fr; }
        .dashboard-header { flex-direction: column; text-align: center; }
        .user-card { justify-content: center; }
    }
</style>

<div class="dashboard-wrapper">
    <!-- Header -->
    <div class="dashboard-header">
        <div class="welcome">
            <h1>✨ Dashboard</h1>
            <p>Welcome back, <?php echo htmlspecialchars($user_name); ?>!</p>
        </div>
        <div class="user-card">
            <div class="user-details">
                <div class="user-name-lg"><?php echo htmlspecialchars($user_name); ?></div>
                <div class="user-role-lg"><?php echo ucfirst(htmlspecialchars($user_role)); ?></div>
            </div>
            <div class="user-avatar-lg"><?php echo strtoupper(substr($user_name, 0, 1)); ?></div>
            <a href="logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-info">
                <h3>Total Patients</h3>
                <div class="stat-number"><?php echo number_format($total_patients); ?></div>
                <div class="stat-trend up"><i class="fas fa-arrow-up"></i> +12% this month</div>
            </div>
            <div class="stat-icon"><i class="fas fa-user-injured"></i></div>
        </div>
        <div class="stat-card">
            <div class="stat-info">
                <h3>Today's Appointments</h3>
                <div class="stat-number"><?php echo $today_appointments; ?></div>
                <div class="stat-trend"><i class="fas fa-clock"></i> Scheduled today</div>
            </div>
            <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
        </div>
        <div class="stat-card">
            <div class="stat-info">
                <h3>Monthly Revenue</h3>
                <div class="stat-number" style="color:#34C759;"><?php echo formatCurrency($monthly_revenue, $currency_symbol); ?></div>
                <div class="stat-trend up"><i class="fas fa-arrow-up"></i> +8% vs last month</div>
            </div>
            <div class="stat-icon"><i class="fas fa-dollar-sign"></i></div>
        </div>
        <div class="stat-card">
            <div class="stat-info">
                <h3>Pending Tasks</h3>
                <div class="stat-number"><?php echo $pending_tasks; ?></div>
                <div class="stat-trend"><i class="fas fa-tasks"></i> Awaiting action</div>
            </div>
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
        </div>
    </div>

    <!-- Content Grid -->
    <div class="content-grid">
        <!-- Upcoming Appointments -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-calendar-week" style="color:#0A84FF;"></i> Upcoming Appointments</h3>
                <a href="appointments/calendar.php">View all →</a>
            </div>
            <div class="card-body">
                <?php if (empty($upcoming_appointments)): ?>
                    <div class="empty-state"><i class="fas fa-calendar-alt"></i><p>No upcoming appointments</p></div>
                <?php else: ?>
                    <table class="data-table">
                        <thead><tr><th>Patient</th><th>Date</th><th>Time</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php foreach ($upcoming_appointments as $apt): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($apt['patient_name']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($apt['appointment_date'])); ?></td>
                                <td><?php echo htmlspecialchars($apt['appointment_time']); ?></td>
                                <td><span class="badge badge-scheduled">Scheduled</span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Patients -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-user-plus" style="color:#34C759;"></i> Recent Patients</h3>
                <a href="patients/view_patients.php">View all →</a>
            </div>
            <div class="card-body">
                <?php if (empty($recent_patients)): ?>
                    <div class="empty-state"><i class="fas fa-users"></i><p>No patients registered</p></div>
                <?php else: ?>
                    <table class="data-table">
                        <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Registered</th></tr></thead>
                        <tbody>
                            <?php foreach ($recent_patients as $patient): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($patient['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($patient['email']); ?></td>
                                <td><?php echo htmlspecialchars($patient['phone'] ?? 'N/A'); ?></td>
                                <td><?php echo date('M d', strtotime($patient['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Appointments -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-history" style="color:#FF9500;"></i> Recent Appointments</h3>
                <a href="appointments/view_appointments.php">View all →</a>
            </div>
            <div class="card-body">
                <?php if (empty($recent_appointments)): ?>
                    <div class="empty-state"><i class="fas fa-calendar-check"></i><p>No appointment history</p></div>
                <?php else: ?>
                    <table class="data-table">
                        <thead><tr><th>Patient</th><th>Date</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php foreach ($recent_appointments as $apt): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($apt['patient_name']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($apt['appointment_date'])); ?></td>
                                <td><span class="badge badge-<?php echo strtolower($apt['status']); ?>"><?php echo ucfirst($apt['status']); ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-bolt" style="color:#AF52DE;"></i> Quick Actions</h3>
            </div>
            <div class="card-body">
                <div class="quick-actions">
                    <a href="patients/add_patient.php" class="action-btn"><i class="fas fa-user-plus" style="color:#34C759;"></i><span>Register New Patient</span></a>
                    <a href="appointments/add_appointment.php" class="action-btn"><i class="fas fa-calendar-plus" style="color:#0A84FF;"></i><span>Schedule Appointment</span></a>
                    <a href="prescriptions/add_prescription.php" class="action-btn"><i class="fas fa-prescription" style="color:#FF9500;"></i><span>Create Prescription</span></a>
                    <a href="billing/invoices.php" class="action-btn"><i class="fas fa-receipt" style="color:#34C759;"></i><span>Generate Invoice</span></a>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="dashboard-footer">
        <p>© <?php echo date('Y'); ?> VeeCare Medical Centre. All rights reserved. | Version 3.0</p>
    </div>
</div>

<?php include 'includes/footer.php'; ?>