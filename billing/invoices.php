<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
require_once '../config/database.php';
$conn = getConnection();

// Pagination & filters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 12;
$offset = ($page - 1) * $limit;
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$where = "1=1";
$params = [];
$types = "";
if (!empty($filter_status)) {
    $where .= " AND i.status = ?";
    $params[] = $filter_status;
    $types .= "s";
}
if (!empty($search)) {
    $where .= " AND (p.full_name LIKE ? OR i.invoice_number LIKE ?)";
    $s = "%$search%";
    $params[] = $s; $params[] = $s;
    $types .= "ss";
}

// Count total
$countSql = "SELECT COUNT(*) as total FROM invoices i JOIN patients p ON i.patient_id = p.id WHERE $where";
$stmt = $conn->prepare($countSql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$total = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();
$totalPages = ceil($total / $limit);

// Fetch invoices
$sql = "SELECT i.*, p.full_name as patient_name, p.patient_id as patient_code 
        FROM invoices i 
        JOIN patients p ON i.patient_id = p.id 
        WHERE $where 
        ORDER BY i.invoice_date DESC 
        LIMIT ? OFFSET ?";
$params[] = $limit; $params[] = $offset;
$types .= "ii";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$invoices = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch items for each invoice
$invoice_items = [];
if (!empty($invoices)) {
    $ids = array_column($invoices, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $item_sql = "SELECT * FROM invoice_items WHERE invoice_id IN ($placeholders)";
    $item_stmt = $conn->prepare($item_sql);
    $item_stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
    $item_stmt->execute();
    $res = $item_stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $invoice_items[$row['invoice_id']][] = $row;
    }
    $item_stmt->close();
}

$statusColors = [
    'pending' => '#FF9500',
    'partially_paid' => '#AF52DE',
    'paid' => '#34C759',
    'overdue' => '#FF3B30'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoices | VeeCare</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* ========== SIDEBAR & GLOBAL STYLES ========== */
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
        .page-header { background: #fff; border-radius: 20px; padding: 20px 28px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
        .page-header h1 { font-size: 24px; font-weight: 700; }
        .btn-back { display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; background: #f0f2f5; color: #666; text-decoration: none; border-radius: 12px; font-weight: 500; }
        .filter-bar { background: #fff; border-radius: 20px; padding: 20px 24px; margin-bottom: 24px; display: flex; flex-wrap: wrap; gap: 16px; align-items: flex-end; }
        .filter-group { display: flex; flex-direction: column; gap: 6px; }
        .filter-group label { font-size: 12px; font-weight: 600; }
        .filter-group input, .filter-group select { padding: 8px 12px; border: 1px solid #ddd; border-radius: 10px; }
        .filter-group button { background: #0A84FF; color: white; border: none; padding: 8px 20px; border-radius: 10px; cursor: pointer; }
        .invoice-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(380px, 1fr)); gap: 20px; }
        .invoice-card { background: #fff; border-radius: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); overflow: hidden; transition: all 0.2s; }
        .invoice-card:hover { transform: translateY(-4px); box-shadow: 0 12px 24px rgba(0,0,0,0.08); }
        .card-header { padding: 16px 20px; background: #fafcff; border-bottom: 1px solid #eef2f6; display: flex; justify-content: space-between; align-items: center; }
        .status-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; color: white; }
        .invoice-number { font-family: monospace; font-weight: 700; }
        .card-body { padding: 16px 20px; }
        .patient-info { display: flex; justify-content: space-between; margin-bottom: 12px; }
        .amount { font-size: 24px; font-weight: 800; margin: 12px 0; }
        .card-footer { padding: 12px 20px; background: #fafcff; border-top: 1px solid #eef2f6; display: flex; gap: 12px; flex-wrap: wrap; }
        .action-btn { padding: 6px 12px; border-radius: 8px; text-decoration: none; font-size: 12px; font-weight: 500; display: inline-flex; align-items: center; gap: 6px; background: none; border: none; cursor: pointer; }
        .btn-view { background: rgba(10,132,255,0.1); color: #0A84FF; }
        .btn-print { background: rgba(52,199,89,0.1); color: #34C759; }
        .btn-pay { background: rgba(255,149,0,0.1); color: #FF9500; }
        .pagination { margin-top: 30px; display: flex; justify-content: center; gap: 8px; }
        .page-link { padding: 8px 14px; background: #fff; color: #666; text-decoration: none; border-radius: 10px; border: 1px solid #eef2f6; }
        .page-link.active, .page-link:hover { background: #0A84FF; color: #fff; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
        .modal-content { background: #fff; border-radius: 24px; max-width: 700px; width: 90%; max-height: 85vh; overflow-y: auto; }
        @media (max-width: 768px) { .sidebar { transform: translateX(-100%); } .main-content { margin-left: 0; } .invoice-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<aside class="sidebar">
    <div class="sidebar-header"><h2><i class="fas fa-heartbeat"></i> VeeCare</h2><p>Medical Centre</p></div>
    <nav class="sidebar-nav">
        <div class="nav-item"><a href="../dashboard.php" class="nav-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a></div>
        <div class="nav-divider"></div>
        <div class="nav-category">Billing</div>
        <div class="nav-item"><a href="invoices.php" class="nav-link active"><i class="fas fa-file-invoice"></i> Invoices</a></div>
        <div class="nav-item"><a href="payments.php" class="nav-link"><i class="fas fa-credit-card"></i> Payments</a></div>
        <div class="nav-item"><a href="receipts.php" class="nav-link"><i class="fas fa-receipt"></i> Receipts</a></div>
        <div class="nav-divider"></div>
        <div class="nav-item"><a href="../logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
    </nav>
</aside>

<main class="main-content">
    <div class="page-header">
        <div><h1>Invoices</h1><p>Manage patient invoices and billing</p></div>
        <a href="../dashboard.php" class="btn-back"><i class="fas fa-arrow-left"></i> Dashboard</a>
    </div>

    <form method="GET" class="filter-bar">
        <div class="filter-group"><label>Search</label><input type="text" name="search" placeholder="Patient or Invoice #" value="<?php echo htmlspecialchars($search); ?>"></div>
        <div class="filter-group"><label>Status</label>
            <select name="status">
                <option value="">All</option>
                <option value="pending" <?php echo $filter_status=='pending'?'selected':''; ?>>Pending</option>
                <option value="partially_paid" <?php echo $filter_status=='partially_paid'?'selected':''; ?>>Partially Paid</option>
                <option value="paid" <?php echo $filter_status=='paid'?'selected':''; ?>>Paid</option>
                <option value="overdue" <?php echo $filter_status=='overdue'?'selected':''; ?>>Overdue</option>
            </select>
        </div>
        <div class="filter-group"><button type="submit"><i class="fas fa-filter"></i> Apply</button></div>
        <div class="filter-group"><a href="invoices.php" class="btn-back" style="background:#f0f2f5;">Clear</a></div>
    </form>

    <div class="invoice-grid">
        <?php foreach ($invoices as $inv): 
            $items = $invoice_items[$inv['id']] ?? [];
            $statusColor = $statusColors[$inv['status']] ?? '#888';
        ?>
        <div class="invoice-card">
            <div class="card-header">
                <span class="invoice-number">#<?php echo htmlspecialchars($inv['invoice_number']); ?></span>
                <span class="status-badge" style="background: <?php echo $statusColor; ?>"><?php echo ucfirst(str_replace('_', ' ', $inv['status'])); ?></span>
            </div>
            <div class="card-body">
                <div class="patient-info">
                    <div><strong><?php echo htmlspecialchars($inv['patient_name']); ?></strong><br><span style="font-size:12px;">ID: <?php echo htmlspecialchars($inv['patient_code']); ?></span></div>
                    <div style="text-align:right;"><?php echo date('d M Y', strtotime($inv['invoice_date'])); ?></div>
                </div>
                <div class="amount"><?php echo number_format($inv['total_amount'], 2); ?> <span style="font-size:14px;">USD</span></div>
                <div>Paid: <?php echo number_format($inv['amount_paid'], 2); ?> | Balance: <?php echo number_format($inv['balance_due'], 2); ?></div>
            </div>
            <div class="card-footer">
                <button class="action-btn btn-view" onclick="viewInvoice(<?php echo $inv['id']; ?>)"><i class="fas fa-eye"></i> View</button>
                <a href="print_invoice.php?id=<?php echo $inv['id']; ?>" target="_blank" class="action-btn btn-print"><i class="fas fa-print"></i> Print</a>
                <a href="payments.php?invoice_id=<?php echo $inv['id']; ?>" class="action-btn btn-pay"><i class="fas fa-money-bill"></i> Record Payment</a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?page=<?php echo $i; ?>&status=<?php echo urlencode($filter_status); ?>&search=<?php echo urlencode($search); ?>" class="page-link <?php echo $i==$page?'active':''; ?>"><?php echo $i; ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</main>

<div id="invoiceModal" class="modal">
    <div class="modal-content">
        <div class="modal-header" style="padding:20px; border-bottom:1px solid #eef2f6; display:flex; justify-content:space-between;"><h2>Invoice Details</h2><button onclick="closeModal()" style="background:none; border:none; font-size:24px;">&times;</button></div>
        <div class="modal-body" id="modalBody" style="padding:20px;"></div>
    </div>
</div>

<script>
function viewInvoice(id) {
    const modal = document.getElementById('invoiceModal');
    const modalBody = document.getElementById('modalBody');
    modalBody.innerHTML = '<div style="text-align:center; padding:40px;"><i class="fas fa-spinner fa-pulse"></i> Loading...</div>';
    modal.style.display = 'flex';
    fetch(`get_invoice_details.php?id=${id}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                let html = `<div><h4>Invoice #${data.invoice.invoice_number}</h4><p>Date: ${data.invoice.invoice_date} | Due: ${data.invoice.due_date}</p><p>Patient: ${data.invoice.patient_name} (${data.invoice.patient_code})</p><p>Status: <span style="background:${data.status_color}; color:white; padding:2px 8px; border-radius:20px;">${data.invoice.status}</span></p></div>`;
                html += `<table style="width:100%; border-collapse:collapse; margin-top:20px;"><thead><tr><th>Description</th><th>Qty</th><th>Unit Price</th><th>Total</th></tr></thead><tbody>`;
                data.items.forEach(item => {
                    html += `<tr><td>${item.description}</td><td>${item.quantity}</td><td>${item.unit_price}</td><td>${item.total_price}</td></tr>`;
                });
                html += `</tbody></table><div style="margin-top:20px;"><strong>Subtotal:</strong> ${data.invoice.subtotal}<br><strong>Tax:</strong> ${data.invoice.tax}<br><strong>Discount:</strong> ${data.invoice.discount}<br><strong>Total:</strong> ${data.invoice.total_amount}<br><strong>Paid:</strong> ${data.invoice.amount_paid}<br><strong>Balance:</strong> ${data.invoice.balance_due}</div>`;
                modalBody.innerHTML = html;
            } else { modalBody.innerHTML = '<div class="alert alert-error">Failed to load.</div>'; }
        }).catch(() => { modalBody.innerHTML = '<div class="alert alert-error">Error.</div>'; });
}
function closeModal() { document.getElementById('invoiceModal').style.display = 'none'; }
</script>
</body>
</html>