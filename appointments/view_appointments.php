<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once '../config/database.php';
$conn = getConnection();

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$filter_date = isset($_GET['filter_date']) ? $_GET['filter_date'] : '';
$filter_doctor = isset($_GET['filter_doctor']) ? (int)$_GET['filter_doctor'] : 0;
$filter_status = isset($_GET['filter_status']) ? $_GET['filter_status'] : '';

// Build where clause
$where = "1=1";
$params = [];
$types = "";
if (!empty($filter_date)) {
    $where .= " AND DATE(appointment_date) = ?";
    $params[] = $filter_date;
    $types .= "s";
}
if ($filter_doctor > 0) {
    $where .= " AND doctor_id = ?";
    $params[] = $filter_doctor;
    $types .= "i";
}
if (!empty($filter_status)) {
    $where .= " AND status = ?";
    $params[] = $filter_status;
    $types .= "s";
}

// Count total
$countSql = "SELECT COUNT(*) as total FROM appointments WHERE $where";
$stmt = $conn->prepare($countSql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$total = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();
$totalPages = ceil($total / $limit);

// Fetch appointments
$sql = "SELECT a.*, p.full_name as patient_name, p.patient_id, u.full_name as doctor_name 
        FROM appointments a 
        JOIN patients p ON a.patient_id = p.id 
        LEFT JOIN users u ON a.doctor_id = u.id 
        WHERE $where 
        ORDER BY a.appointment_date DESC, a.appointment_time DESC 
        LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$appointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get doctors for filter
$doctors = [];
$res = $conn->query("SELECT id, full_name FROM users WHERE role = 'doctor'");
if ($res) while ($row = $res->fetch_assoc()) $doctors[] = $row;

$statusColors = ['scheduled' => '#0A84FF', 'completed' => '#34C759', 'cancelled' => '#FF3B30', 'no_show' => '#FF9500'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointments | VeeCare</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Same sidebar and base styles as add_appointment.php, plus table enhancements */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f0f2f5; }
        .sidebar { width: 260px; background: linear-gradient(180deg, #0f0f1a 0%, #1a1a2e 100%); color: #fff; position: fixed; left: 0; top: 0; height: 100vh; overflow-y: auto; z-index: 100; }
        .sidebar-header { padding: 24px 20px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-header h2 { font-size: 20px; display: flex; align-items: center; gap: 10px; }
        .sidebar-header h2 i { color: #0A84FF; }
        .sidebar-header p { font-size: 11px; color: rgba(255,255,255,0.5); margin-top: 5px; }
        .sidebar-nav { padding: 20px; }
        .nav-link { display: flex; align-items: center; gap: 12px; padding: 10px 12px; color: rgba(255,255,255,0.7); text-decoration: none; border-radius: 8px; transition: all 0.2s; font-size: 14px; }
        .nav-link:hover, .nav-link.active { background: rgba(10,132,255,0.15); color: #0A84FF; }
        .nav-link i { width: 20px; }
        .nav-divider { height: 1px; background: rgba(255,255,255,0.08); margin: 12px 0; }
        .nav-category { font-size: 10px; text-transform: uppercase; color: rgba(255,255,255,0.4); padding: 8px 12px; margin-top: 8px; }
        .main-content { margin-left: 260px; padding: 24px; }
        .page-header { background: #fff; border-radius: 16px; padding: 20px 24px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .page-header h1 { font-size: 24px; font-weight: 700; }
        .page-header p { font-size: 13px; color: #666; margin-top: 4px; }
        .btn-back { display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; background: #f0f2f5; color: #666; text-decoration: none; border-radius: 10px; font-weight: 500; transition: all 0.2s; }
        .btn-back:hover { background: #e0e2e5; transform: translateX(-2px); }
        .filter-bar { background: #fff; border-radius: 16px; padding: 16px 24px; margin-bottom: 24px; display: flex; flex-wrap: wrap; gap: 16px; align-items: flex-end; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .filter-group { display: flex; flex-direction: column; gap: 6px; }
        .filter-group label { font-size: 12px; font-weight: 600; color: #666; }
        .filter-group input, .filter-group select { padding: 8px 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; }
        .filter-group button { background: #0A84FF; color: white; border: none; padding: 8px 20px; border-radius: 8px; cursor: pointer; font-weight: 600; }
        .filter-group button:hover { background: #006EDB; }
        .table-card { background: #fff; border-radius: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); overflow: hidden; }
        .table-wrapper { overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; min-width: 800px; }
        .data-table th { text-align: left; padding: 16px 20px; font-size: 12px; font-weight: 600; text-transform: uppercase; color: #8E8E93; background: #f8f9fa; border-bottom: 1px solid #eee; }
        .data-table td { padding: 16px 20px; font-size: 14px; border-bottom: 1px solid #f0f0f0; }
        .data-table tr:hover td { background: #f8f9fa; }
        .status-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; color: white; }
        .action-buttons { display: flex; gap: 8px; }
        .action-btn { padding: 6px 12px; border-radius: 8px; text-decoration: none; font-size: 12px; font-weight: 500; transition: all 0.2s; }
        .btn-edit { background: rgba(255,149,0,0.1); color: #FF9500; }
        .btn-edit:hover { background: #FF9500; color: #fff; }
        .btn-cancel { background: rgba(255,59,48,0.1); color: #FF3B30; }
        .btn-cancel:hover { background: #FF3B30; color: #fff; }
        .pagination { padding: 20px; display: flex; justify-content: center; gap: 8px; border-top: 1px solid #eee; flex-wrap: wrap; }
        .page-link { padding: 8px 14px; background: #f8f9fa; color: #666; text-decoration: none; border-radius: 8px; transition: all 0.2s; }
        .page-link:hover, .page-link.active { background: #0A84FF; color: #fff; }
        .empty-state { text-align: center; padding: 60px 20px; color: #999; }
        .empty-state i { font-size: 60px; margin-bottom: 16px; opacity: 0.5; }
        @media (max-width: 768px) { .sidebar { transform: translateX(-100%); position: fixed; z-index: 1000; } .main-content { margin-left: 0; } }
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
        <div class="nav-item"><a href="add_appointment.php" class="nav-link"><i class="fas fa-plus-circle"></i> New Appointment</a></div>
        <div class="nav-item"><a href="view_appointments.php" class="nav-link active"><i class="fas fa-list"></i> All Appointments</a></div>
        <div class="nav-divider"></div>
        <div class="nav-item"><a href="../logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
    </nav>
</aside>

<main class="main-content">
    <div class="page-header">
        <div><h1>Appointments</h1><p>Manage all scheduled appointments</p></div>
        <a href="../dashboard.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
    </div>

    <form method="GET" class="filter-bar">
        <div class="filter-group"><label>Date</label><input type="date" name="filter_date" value="<?php echo htmlspecialchars($filter_date); ?>"></div>
        <div class="filter-group"><label>Doctor</label>
            <select name="filter_doctor">
                <option value="0">All Doctors</option>
                <?php foreach ($doctors as $d): ?>
                    <option value="<?php echo $d['id']; ?>" <?php echo ($filter_doctor == $d['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($d['full_name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group"><label>Status</label>
            <select name="filter_status">
                <option value="">All</option>
                <option value="scheduled" <?php echo ($filter_status == 'scheduled') ? 'selected' : ''; ?>>Scheduled</option>
                <option value="completed" <?php echo ($filter_status == 'completed') ? 'selected' : ''; ?>>Completed</option>
                <option value="cancelled" <?php echo ($filter_status == 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                <option value="no_show" <?php echo ($filter_status == 'no_show') ? 'selected' : ''; ?>>No Show</option>
            </select>
        </div>
        <div class="filter-group"><button type="submit"><i class="fas fa-filter"></i> Apply Filters</button></div>
        <div class="filter-group"><a href="view_appointments.php" class="btn-back" style="background:#f0f2f5;">Clear</a></div>
    </form>

    <div class="table-card">
        <div class="table-wrapper">
            <?php if (empty($appointments)): ?>
                <div class="empty-state"><i class="fas fa-calendar-alt"></i><p>No appointments found</p><a href="add_appointment.php" style="color:#0A84FF;">Schedule one →</a></div>
            <?php else: ?>
                <table class="data-table">
                    <thead><tr><th>ID</th><th>Patient</th><th>Doctor</th><th>Date</th><th>Time</th><th>Purpose</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php foreach ($appointments as $apt): ?>
                        <tr>
                            <td>#<?php echo $apt['id']; ?></td>
                            <td><strong><?php echo htmlspecialchars($apt['patient_name']); ?></strong><br><span style="font-size:12px;color:#888;"><?php echo htmlspecialchars($apt['patient_id']); ?></span></td>
                            <td><?php echo htmlspecialchars($apt['doctor_name'] ?? 'Unassigned'); ?></td>
                            <td><?php echo date('M d, Y', strtotime($apt['appointment_date'])); ?></td>
                            <td><?php echo date('g:i A', strtotime($apt['appointment_time'])); ?></td>
                            <td><?php echo htmlspecialchars($apt['purpose'] ?? '—'); ?></td>
                            <td><span class="status-badge" style="background: <?php echo $statusColors[$apt['status']] ?? '#888'; ?>"><?php echo ucfirst($apt['status']); ?></span></td>
                            <td class="action-buttons">
                                <a href="edit_appointment.php?id=<?php echo $apt['id']; ?>" class="action-btn btn-edit"><i class="fas fa-edit"></i> Edit</a>
                                <a href="javascript:void(0)" onclick="cancelAppointment(<?php echo $apt['id']; ?>)" class="action-btn btn-cancel"><i class="fas fa-times"></i> Cancel</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?page=<?php echo $i; ?>&filter_date=<?php echo urlencode($filter_date); ?>&filter_doctor=<?php echo $filter_doctor; ?>&filter_status=<?php echo urlencode($filter_status); ?>" class="page-link <?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>
</main>
<script>
function cancelAppointment(id) {
    if (confirm('Cancel this appointment?')) {
        window.location.href = 'cancel_appointment.php?id=' + id;
    }
}
</script>
</body>
</html>