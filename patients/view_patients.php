<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once '../config/database.php';
$conn = getConnection();

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter = isset($_GET['filter']) ? $_GET['filter'] : '';

// Build query
$where = "";
$params = [];
if (!empty($search)) {
    $where = "WHERE (full_name LIKE ? OR patient_id LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $params = ["%$search%", "%$search%", "%$search%", "%$search%"];
}
if (!empty($filter) && $filter !== 'all') {
    $where = empty($where) ? "WHERE blood_group = ?" : "$where AND blood_group = ?";
    $params[] = $filter;
}

// Count total
$countSql = "SELECT COUNT(*) as total FROM patients $where";
$stmt = $conn->prepare($countSql);
if (!empty($params)) {
    $types = str_repeat('s', count($params));
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();
$totalPages = ceil($total / $limit);

// Fetch data
$sql = "SELECT id, patient_id, full_name, email, phone, date_of_birth, blood_group, is_child, created_at 
        FROM patients $where 
        ORDER BY created_at DESC 
        LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$stmt = $conn->prepare($sql);
$types = str_repeat('s', count($params) - 2) . 'ii';
$stmt->bind_param($types, ...$params);
$stmt->execute();
$patients = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$bloodGroups = ['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'];

function calculateAge($dob) {
    $birthDate = new DateTime($dob);
    $today = new DateTime();
    $age = $today->diff($birthDate);
    return $age->y;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patients | VeeCare</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f0f2f5; }
        
        /* Sidebar */
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
        
        /* Page Header */
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
        
        /* Buttons */
        .btn-back-dashboard {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: #0A84FF;
            color: #fff;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.2s;
        }
        .btn-back-dashboard:hover { background: #006EDB; transform: translateX(-2px); }
        
        .btn-add {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: linear-gradient(135deg, #34C759, #2BA54A);
            color: #fff;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.2s;
        }
        .btn-add:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(52,199,89,0.3); }
        
        /* Search & Filter */
        .search-filter-area {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: center;
        }
        .search-box {
            display: flex;
            align-items: center;
            background: #f8f9fa;
            border-radius: 12px;
            padding: 8px 16px;
            gap: 8px;
            border: 1px solid #eee;
        }
        .search-box i { color: #999; }
        .search-box input {
            border: none;
            background: none;
            padding: 8px 0;
            width: 250px;
            font-size: 14px;
            outline: none;
        }
        .filter-select {
            padding: 8px 16px;
            border: 1px solid #ddd;
            border-radius: 12px;
            font-size: 14px;
            background: #fff;
            cursor: pointer;
        }
        
        /* Table Card */
        .table-card {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        .table-wrapper { overflow-x: auto; }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }
        .data-table th {
            text-align: left;
            padding: 16px 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            color: #8E8E93;
            background: #f8f9fa;
            border-bottom: 1px solid #eee;
        }
        .data-table td {
            padding: 16px 20px;
            font-size: 14px;
            border-bottom: 1px solid #f0f0f0;
        }
        .data-table tr:hover td { background: #f8f9fa; }
        
        .patient-id {
            font-family: monospace;
            font-size: 12px;
            background: #f0f2f5;
            padding: 4px 8px;
            border-radius: 6px;
            display: inline-block;
        }
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        .badge-adult { background: rgba(10,132,255,0.1); color: #0A84FF; }
        .badge-child { background: rgba(255,149,0,0.1); color: #FF9500; }
        .badge-blood { background: rgba(52,199,89,0.1); color: #34C759; }
        
        .action-buttons { display: flex; gap: 8px; flex-wrap: wrap; }
        .action-btn {
            padding: 6px 12px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .btn-view { background: rgba(10,132,255,0.1); color: #0A84FF; }
        .btn-view:hover { background: #0A84FF; color: #fff; }
        .btn-edit { background: rgba(255,149,0,0.1); color: #FF9500; }
        .btn-edit:hover { background: #FF9500; color: #fff; }
        .btn-delete { background: rgba(255,59,48,0.1); color: #FF3B30; }
        .btn-delete:hover { background: #FF3B30; color: #fff; }
        
        .pagination {
            padding: 20px;
            display: flex;
            justify-content: center;
            gap: 8px;
            border-top: 1px solid #eee;
            flex-wrap: wrap;
        }
        .page-link {
            padding: 8px 14px;
            background: #f8f9fa;
            color: #666;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.2s;
        }
        .page-link:hover, .page-link.active { background: #0A84FF; color: #fff; }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        .empty-state i { font-size: 60px; margin-bottom: 16px; opacity: 0.5; }
        
        .alert {
            padding: 14px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-success { background: rgba(52,199,89,0.1); color: #34C759; border-left: 3px solid #34C759; }
        .alert-error { background: rgba(255,59,48,0.1); color: #FF3B30; border-left: 3px solid #FF3B30; }
        
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); position: fixed; z-index: 1000; }
            .main-content { margin-left: 0; }
            .page-header { flex-direction: column; align-items: stretch; }
            .search-filter-area { flex-direction: column; }
            .search-box input { width: 100%; }
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
        <div class="nav-item"><a href="view_patients.php" class="nav-link active"><i class="fas fa-users"></i> All Patients</a></div>
        <div class="nav-item"><a href="add_patient.php" class="nav-link"><i class="fas fa-user-plus"></i> Add Patient</a></div>
        <div class="nav-divider"></div>
        <div class="nav-item"><a href="../logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
    </nav>
</aside>

<main class="main-content">
    <div class="page-header">
        <div>
            <h1>Patients</h1>
            <p>Manage all registered patients</p>
        </div>
        <div class="search-filter-area">
            <form method="GET" style="display: flex; gap: 12px; flex-wrap: wrap;">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" placeholder="Search by name, ID, email, phone..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <select name="filter" class="filter-select" onchange="this.form.submit()">
                    <option value="all">All Blood Types</option>
                    <?php foreach ($bloodGroups as $bg): ?>
                        <option value="<?php echo $bg; ?>" <?php echo $filter == $bg ? 'selected' : ''; ?>><?php echo $bg; ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" style="display: none;">Filter</button>
            </form>
            <div style="display: flex; gap: 12px;">
                <a href="../dashboard.php" class="btn-back-dashboard"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
                <a href="add_patient.php" class="btn-add"><i class="fas fa-plus"></i> Add New Patient</a>
            </div>
        </div>
    </div>
    
    <?php if (isset($_SESSION['delete_success'])): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $_SESSION['delete_success']; unset($_SESSION['delete_success']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['delete_error'])): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> <?php echo $_SESSION['delete_error']; unset($_SESSION['delete_error']); ?></div>
    <?php endif; ?>
    
    <div class="table-card">
        <div class="table-wrapper">
            <?php if (empty($patients)): ?>
                <div class="empty-state">
                    <i class="fas fa-users"></i>
                    <p>No patients found</p>
                    <a href="add_patient.php" style="color: #0A84FF; margin-top: 10px; display: inline-block;">Add your first patient →</a>
                </div>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Patient ID</th>
                            <th>Full Name</th>
                            <th>Age</th>
                            <th>Type</th>
                            <th>Contact</th>
                            <th>Blood</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($patients as $patient): ?>
                            <?php $age = calculateAge($patient['date_of_birth']); ?>
                            <tr>
                                <td>#<?php echo $patient['id']; ?></td>
                                <td><span class="patient-id"><?php echo htmlspecialchars($patient['patient_id']); ?></span></td>
                                <td><strong><?php echo htmlspecialchars($patient['full_name']); ?></strong></td>
                                <td><?php echo $age; ?> yrs</td>
                                <td>
                                    <?php if ($patient['is_child']): ?>
                                        <span class="badge badge-child"><i class="fas fa-child"></i> Child</span>
                                    <?php else: ?>
                                        <span class="badge badge-adult"><i class="fas fa-user"></i> Adult</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($patient['email'])): ?>
                                        <div><i class="fas fa-envelope" style="font-size: 11px; color:#888;"></i> <?php echo htmlspecialchars(substr($patient['email'], 0, 20)); ?></div>
                                    <?php endif; ?>
                                    <div><i class="fas fa-phone" style="font-size: 11px; color:#888;"></i> <?php echo htmlspecialchars($patient['phone'] ?? '—'); ?></div>
                                </td>
                                <td><span class="badge badge-blood"><?php echo htmlspecialchars($patient['blood_group'] ?? 'N/A'); ?></span></td>
                                <td class="action-buttons">
                                    <a href="patient_profile.php?id=<?php echo $patient['id']; ?>" class="action-btn btn-view"><i class="fas fa-eye"></i> View</a>
                                    <a href="edit_patient.php?id=<?php echo $patient['id']; ?>" class="action-btn btn-edit"><i class="fas fa-edit"></i> Edit</a>
                                    <a href="javascript:void(0)" onclick="confirmDelete(<?php echo $patient['id']; ?>)" class="action-btn btn-delete"><i class="fas fa-trash"></i> Delete</a>
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
                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&filter=<?php echo urlencode($filter); ?>" class="page-link <?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>
</main>

<script>
function confirmDelete(id) {
    if (confirm('Are you sure you want to delete this patient? This action cannot be undone.')) {
        window.location.href = 'delete_patient.php?id=' + id;
    }
}
</script>
</body>
</html>