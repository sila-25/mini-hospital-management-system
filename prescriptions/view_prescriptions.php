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
$limit = 12;
$offset = ($page - 1) * $limit;

// Filters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$condition_filter = isset($_GET['condition']) ? $_GET['condition'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build WHERE clause
$where = "1=1";
$params = [];
$types = "";

if (!empty($search)) {
    $where .= " AND (p.full_name LIKE ? OR pr.diagnosis LIKE ? OR pr.prescription_number LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}
if (!empty($condition_filter)) {
    $where .= " AND pr.treatment_category = ?";
    $params[] = $condition_filter;
    $types .= "s";
}
if (!empty($date_from)) {
    $where .= " AND pr.prescription_date >= ?";
    $params[] = $date_from;
    $types .= "s";
}
if (!empty($date_to)) {
    $where .= " AND pr.prescription_date <= ?";
    $params[] = $date_to;
    $types .= "s";
}

// Count total
$countSql = "SELECT COUNT(DISTINCT pr.id) as total 
             FROM prescriptions pr 
             JOIN patients p ON pr.patient_id = p.id 
             WHERE $where";
$stmt = $conn->prepare($countSql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$total = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();
$totalPages = ceil($total / $limit);

// Fetch prescriptions with patient and doctor info
$sql = "SELECT pr.id, pr.prescription_number, pr.prescription_date, pr.diagnosis, pr.treatment_category, pr.status,
               p.id as patient_id, p.full_name as patient_name, p.patient_id as patient_code,
               u.full_name as doctor_name
        FROM prescriptions pr
        JOIN patients p ON pr.patient_id = p.id
        LEFT JOIN users u ON pr.doctor_id = u.id
        WHERE $where
        ORDER BY pr.prescription_date DESC
        LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$prescriptions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch prescription items for each prescription (for quick view)
$prescription_items = [];
if (!empty($prescriptions)) {
    $ids = array_column($prescriptions, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $item_sql = "SELECT prescription_id, medication_name, dosage, frequency, duration, quantity, instructions FROM prescription_items WHERE prescription_id IN ($placeholders)";
    $item_stmt = $conn->prepare($item_sql);
    $item_stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
    $item_stmt->execute();
    $items_result = $item_stmt->get_result();
    while ($row = $items_result->fetch_assoc()) {
        $prescription_items[$row['prescription_id']][] = $row;
    }
    $item_stmt->close();
}

// Condition type colors and labels
$condition_colors = [
    'antimalarial' => ['color' => '#0A84FF', 'label' => '🔵 Antimalarial'],
    'pain_management' => ['color' => '#34C759', 'label' => '🟢 Pain Management'],
    'anti_inflammatory' => ['color' => '#FF9500', 'label' => '🟡 Anti-inflammatory'],
    'antibiotic' => ['color' => '#AF52DE', 'label' => '🟣 Antibiotic'],
    'trauma' => ['color' => '#FF3B30', 'label' => '🔴 Trauma Care']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prescriptions | VeeCare</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f0f2f5; }
        
        /* Sidebar (same as other pages) */
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
            border-radius: 20px;
            padding: 20px 28px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .page-header h1 { font-size: 24px; font-weight: 700; color: #1a1a2e; }
        .page-header p { font-size: 13px; color: #5a6e8a; margin-top: 4px; }
        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: #f0f2f5;
            color: #4a627a;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 500;
            transition: all 0.2s;
        }
        .btn-back:hover { background: #e4e8ef; transform: translateX(-2px); }
        .btn-add {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: linear-gradient(135deg, #0A84FF, #006EDB);
            color: white;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.2s;
        }
        .btn-add:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(10,132,255,0.3); }
        
        /* Filter Bar */
        .filter-bar {
            background: #fff;
            border-radius: 20px;
            padding: 20px 24px;
            margin-bottom: 24px;
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            align-items: flex-end;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .filter-group label {
            font-size: 12px;
            font-weight: 600;
            color: #4a627a;
        }
        .filter-group input, .filter-group select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 10px;
            font-size: 14px;
            min-width: 160px;
        }
        .filter-group button {
            background: #0A84FF;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
        }
        .filter-group button:hover { background: #006EDB; }
        .clear-btn {
            background: #f0f2f5 !important;
            color: #666 !important;
        }
        
        /* Prescriptions Grid */
        .prescriptions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 20px;
        }
        .prescription-card {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            overflow: hidden;
            transition: all 0.2s;
            border: 1px solid rgba(0,0,0,0.03);
        }
        .prescription-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(0,0,0,0.08);
        }
        .card-header {
            padding: 16px 20px;
            background: #fafcff;
            border-bottom: 1px solid #eef2f6;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }
        .condition-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            color: white;
        }
        .prescription-number {
            font-family: monospace;
            font-size: 12px;
            background: #f0f2f5;
            padding: 4px 8px;
            border-radius: 8px;
        }
        .card-body {
            padding: 16px 20px;
        }
        .patient-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            padding-bottom: 12px;
            border-bottom: 1px solid #eef2f6;
        }
        .patient-name {
            font-weight: 700;
            font-size: 16px;
            color: #1a1a2e;
        }
        .patient-id {
            font-size: 11px;
            color: #8E8E93;
        }
        .diagnosis {
            background: #f8fafc;
            padding: 10px;
            border-radius: 12px;
            margin: 12px 0;
            font-size: 13px;
        }
        .diagnosis strong { color: #0A84FF; }
        .medication-list {
            margin: 12px 0;
            padding-left: 16px;
            font-size: 12px;
            color: #4a627a;
        }
        .medication-list li {
            margin-bottom: 6px;
        }
        .card-footer {
            padding: 12px 20px;
            background: #fafcff;
            border-top: 1px solid #eef2f6;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .action-btn {
            padding: 6px 12px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 12px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
            cursor: pointer;
            background: none;
            border: none;
        }
        .btn-view { background: rgba(10,132,255,0.1); color: #0A84FF; }
        .btn-view:hover { background: #0A84FF; color: #fff; }
        .btn-print { background: rgba(52,199,89,0.1); color: #34C759; }
        .btn-print:hover { background: #34C759; color: #fff; }
        .btn-edit { background: rgba(255,149,0,0.1); color: #FF9500; }
        .btn-edit:hover { background: #FF9500; color: #fff; }
        
        /* Modal Styles */
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
        .modal.active { display: flex; }
        .modal-content {
            background: #fff;
            border-radius: 24px;
            max-width: 700px;
            width: 90%;
            max-height: 85vh;
            overflow-y: auto;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
        }
        .modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid #eef2f6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-header h2 { font-size: 20px; font-weight: 700; }
        .close-modal { background: none; border: none; font-size: 24px; cursor: pointer; color: #8E8E93; }
        .modal-body { padding: 24px; }
        .detail-section { margin-bottom: 20px; }
        .detail-section h4 { font-size: 14px; font-weight: 700; margin-bottom: 12px; color: #1a1a2e; }
        .medication-table { width: 100%; border-collapse: collapse; }
        .medication-table th, .medication-table td { padding: 8px; text-align: left; border-bottom: 1px solid #eef2f6; font-size: 13px; }
        
        .pagination {
            margin-top: 30px;
            display: flex;
            justify-content: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .page-link {
            padding: 8px 14px;
            background: #fff;
            color: #666;
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.2s;
            border: 1px solid #eef2f6;
        }
        .page-link:hover, .page-link.active { background: #0A84FF; color: #fff; border-color: #0A84FF; }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: #fff;
            border-radius: 20px;
            color: #999;
        }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); position: fixed; z-index: 1000; }
            .main-content { margin-left: 0; }
            .prescriptions-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<aside class="sidebar">
    <div class="sidebar-header"><h2><i class="fas fa-heartbeat"></i> VeeCare</h2><p>Medical Centre</p></div>
    <nav class="sidebar-nav">
        <div class="nav-item"><a href="../dashboard.php" class="nav-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a></div>
        <div class="nav-divider"></div>
        <div class="nav-category">Prescriptions</div>
        <div class="nav-item"><a href="view_prescriptions.php" class="nav-link active"><i class="fas fa-list"></i> All Prescriptions</a></div>
        <div class="nav-item"><a href="add_prescription.php" class="nav-link"><i class="fas fa-plus-circle"></i> New Prescription</a></div>
        <div class="nav-divider"></div>
        <div class="nav-item"><a href="../logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
    </nav>
</aside>

<main class="main-content">
    <div class="page-header">
        <div><h1>Prescriptions</h1><p>Manage all issued prescriptions</p></div>
        <div style="display: flex; gap: 12px;">
            <a href="../dashboard.php" class="btn-back"><i class="fas fa-arrow-left"></i> Dashboard</a>
            <a href="add_prescription.php" class="btn-add"><i class="fas fa-plus"></i> New Prescription</a>
        </div>
    </div>

    <!-- Filter Bar -->
    <form method="GET" class="filter-bar">
        <div class="filter-group"><label>Search</label><input type="text" name="search" placeholder="Patient, diagnosis, #" value="<?php echo htmlspecialchars($search); ?>"></div>
        <div class="filter-group"><label>Condition Category</label>
            <select name="condition">
                <option value="">All</option>
                <option value="antimalarial" <?php echo $condition_filter=='antimalarial'?'selected':''; ?>>🔵 Antimalarial</option>
                <option value="pain_management" <?php echo $condition_filter=='pain_management'?'selected':''; ?>>🟢 Pain Management</option>
                <option value="anti_inflammatory" <?php echo $condition_filter=='anti_inflammatory'?'selected':''; ?>>🟡 Anti-inflammatory</option>
                <option value="antibiotic" <?php echo $condition_filter=='antibiotic'?'selected':''; ?>>🟣 Antibiotic</option>
                <option value="trauma" <?php echo $condition_filter=='trauma'?'selected':''; ?>>🔴 Trauma</option>
            </select>
        </div>
        <div class="filter-group"><label>Date From</label><input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>"></div>
        <div class="filter-group"><label>Date To</label><input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>"></div>
        <div class="filter-group"><button type="submit"><i class="fas fa-filter"></i> Apply</button></div>
        <div class="filter-group"><a href="view_prescriptions.php" class="clear-btn" style="background:#f0f2f5; padding:8px 20px; border-radius:10px; text-decoration:none; color:#666;"><i class="fas fa-times"></i> Clear</a></div>
    </form>

    <!-- Prescriptions Grid -->
    <?php if (empty($prescriptions)): ?>
        <div class="empty-state"><i class="fas fa-prescription-bottle" style="font-size:48px; margin-bottom:16px;"></i><p>No prescriptions found.</p><a href="add_prescription.php" style="color:#0A84FF;">Create your first prescription →</a></div>
    <?php else: ?>
        <div class="prescriptions-grid">
            <?php foreach ($prescriptions as $rx): 
                $items = $prescription_items[$rx['id']] ?? [];
                $colorInfo = $condition_colors[$rx['treatment_category']] ?? ['color' => '#8E8E93', 'label' => 'General'];
            ?>
                <div class="prescription-card">
                    <div class="card-header">
                        <span class="condition-badge" style="background: <?php echo $colorInfo['color']; ?>;"><?php echo $colorInfo['label']; ?></span>
                        <span class="prescription-number"><?php echo htmlspecialchars($rx['prescription_number']); ?></span>
                    </div>
                    <div class="card-body">
                        <div class="patient-info">
                            <div>
                                <div class="patient-name"><?php echo htmlspecialchars($rx['patient_name']); ?></div>
                                <div class="patient-id">ID: <?php echo htmlspecialchars($rx['patient_code']); ?></div>
                            </div>
                            <div style="text-align:right; font-size:11px; color:#666;"><?php echo date('M d, Y', strtotime($rx['prescription_date'])); ?></div>
                        </div>
                        <div class="diagnosis">
                            <strong>Diagnosis:</strong> <?php echo htmlspecialchars($rx['diagnosis'] ?: 'Not specified'); ?>
                        </div>
                        <div class="medication-list">
                            <strong>Medications:</strong>
                            <ul style="margin-top: 6px;">
                                <?php foreach (array_slice($items, 0, 2) as $item): ?>
                                    <li><?php echo htmlspecialchars($item['medication_name']); ?> (<?php echo htmlspecialchars($item['dosage']); ?>)</li>
                                <?php endforeach; ?>
                                <?php if (count($items) > 2): ?>
                                    <li>+<?php echo count($items)-2; ?> more</li>
                                <?php elseif (empty($items)): ?>
                                    <li>—</li>
                                <?php endif; ?>
                            </ul>
                        </div>
                        <div style="font-size:12px; color:#8E8E93;"><i class="fas fa-user-md"></i> Dr. <?php echo htmlspecialchars($rx['doctor_name'] ?? 'Unknown'); ?></div>
                    </div>
                    <div class="card-footer">
                        <button class="action-btn btn-view" onclick="viewPrescription(<?php echo $rx['id']; ?>)"><i class="fas fa-eye"></i> View</button>
                        <a href="print_prescription.php?id=<?php echo $rx['id']; ?>" target="_blank" class="action-btn btn-print"><i class="fas fa-print"></i> Print</a>
                        <a href="edit_prescription.php?id=<?php echo $rx['id']; ?>" class="action-btn btn-edit"><i class="fas fa-edit"></i> Edit</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&condition=<?php echo urlencode($condition_filter); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>" class="page-link <?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</main>

<!-- Modal for Detailed View -->
<div id="prescriptionModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Prescription Details</h2>
            <button class="close-modal" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body" id="modalBody">
            <!-- Dynamic content -->
        </div>
    </div>
</div>

<script>
async function viewPrescription(id) {
    const modal = document.getElementById('prescriptionModal');
    const modalBody = document.getElementById('modalBody');
    modalBody.innerHTML = '<div style="text-align:center; padding:40px;"><i class="fas fa-spinner fa-pulse"></i> Loading...</div>';
    modal.classList.add('active');
    
    try {
        const response = await fetch(`get_prescription_details.php?id=${id}`);
        const data = await response.json();
        if (data.success) {
            const rx = data.prescription;
            const items = data.items || [];
            const conditionLabel = data.condition_label || 'General';
            const conditionColor = data.condition_color || '#8E8E93';
            
            let html = `
                <div class="detail-section">
                    <h4><i class="fas fa-file-prescription"></i> Prescription Information</h4>
                    <table style="width:100%;">
                        <tr><td style="padding:4px 0; width:140px;">Number:</td><td><strong>${rx.prescription_number}</strong></td></tr>
                        <tr><td style="padding:4px 0;">Date:</td><td>${rx.prescription_date}</td></tr>
                        <tr><td style="padding:4px 0;">Doctor:</td><td>Dr. ${rx.doctor_name}</td></tr>
                        <tr><td style="padding:4px 0;">Condition Category:</td><td><span style="background:${conditionColor}; color:white; padding:2px 8px; border-radius:20px; font-size:11px;">${conditionLabel}</span></td></tr>
                        <tr><td style="padding:4px 0;">Status:</td><td><span class="badge badge-${rx.status}">${rx.status}</span></td></tr>
                    </table>
                </div>
                <div class="detail-section">
                    <h4><i class="fas fa-stethoscope"></i> Diagnosis & Clinical Notes</h4>
                    <p><strong>Diagnosis:</strong> ${rx.diagnosis || 'Not specified'}</p>
                    <p><strong>Notes:</strong> ${rx.notes || 'None'}</p>
                </div>
                <div class="detail-section">
                    <h4><i class="fas fa-capsules"></i> Medications</h4>
                    ${items.length ? `
                        <table class="medication-table">
                            <thead><tr><th>Medication</th><th>Dosage</th><th>Frequency</th><th>Duration</th><th>Quantity</th><th>Instructions</th></tr></thead>
                            <tbody>
                                ${items.map(item => `
                                    <tr>
                                        <td>${item.medication_name}</td>
                                        <td>${item.dosage}</td>
                                        <td>${item.frequency}</td>
                                        <td>${item.duration}</td>
                                        <td>${item.quantity}</td>
                                        <td>${item.instructions || '-'}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    ` : '<p>No medication items recorded.</p>'}
                </div>
                <div class="detail-section">
                    <h4><i class="fas fa-user"></i> Patient Information</h4>
                    <p><strong>Name:</strong> ${rx.patient_name}<br>
                    <strong>Patient ID:</strong> ${rx.patient_code}<br>
                    <strong>Age:</strong> ${rx.patient_age} years</p>
                </div>
                <div class="detail-section">
                    <h4><i class="fas fa-print"></i> Actions</h4>
                    <button class="action-btn btn-print" onclick="window.open('print_prescription.php?id=${rx.id}', '_blank')"><i class="fas fa-print"></i> Print Prescription</button>
                </div>
            `;
            modalBody.innerHTML = html;
        } else {
            modalBody.innerHTML = '<div class="alert alert-error">Failed to load prescription details.</div>';
        }
    } catch (err) {
        modalBody.innerHTML = '<div class="alert alert-error">Error loading details.</div>';
    }
}

function closeModal() {
    document.getElementById('prescriptionModal').classList.remove('active');
}
</script>
</body>
</html>