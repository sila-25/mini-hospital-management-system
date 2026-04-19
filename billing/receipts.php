<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
require_once '../config/database.php';
$conn = getConnection();

// Check if payment_date column exists
$has_payment_date = false;
$check = $conn->query("SHOW COLUMNS FROM payments LIKE 'payment_date'");
if ($check && $check->num_rows > 0) {
    $has_payment_date = true;
}
$date_column = $has_payment_date ? 'pay.payment_date' : 'pay.created_at';

$receipt_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$show_receipt = ($receipt_id > 0);

if ($show_receipt) {
    $stmt = $conn->prepare("
        SELECT pay.*, 
               p.full_name as patient_name, p.patient_id as patient_code, p.phone, p.address,
               inv.invoice_number, inv.total_amount, inv.balance_due, inv.invoice_date, inv.due_date,
               u.full_name as clinician_name
        FROM payments pay
        JOIN patients p ON pay.patient_id = p.id
        JOIN invoices inv ON pay.invoice_id = inv.id
        LEFT JOIN users u ON inv.created_by = u.id
        WHERE pay.id = ?
    ");
    $stmt->bind_param("i", $receipt_id);
    $stmt->execute();
    $payment = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$payment) die("Receipt not found.");
    
    $items = [];
    $stmt = $conn->prepare("SELECT description, quantity, unit_price, total_price FROM invoice_items WHERE invoice_id = ?");
    $stmt->bind_param("i", $payment['invoice_id']);
    $stmt->execute();
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    $clinic_name = "VeeCare Medical Centre";
    $clinic_address = "PO BOX 4478 - 40200, KISII";
    $clinic_phone = "+254791333577";
    ?>
    <!DOCTYPE html>
    <html>
    <head><title>Receipt #<?php echo str_pad($payment['id'],6,'0',STR_PAD_LEFT); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Inter',sans-serif; background:#eef2f5; padding:40px 20px; display:flex; justify-content:center; }
        .receipt-container { max-width:800px; width:100%; background:white; border-radius:24px; box-shadow:0 20px 40px rgba(0,0,0,0.1); overflow:hidden; }
        .receipt-header { background:linear-gradient(135deg,#0A84FF,#006EDB); color:white; padding:30px; text-align:center; }
        .clinic-name { font-size:28px; font-weight:800; }
        .receipt-title { background:#fff; color:#0A84FF; display:inline-block; padding:6px 20px; border-radius:30px; margin-top:12px; font-weight:700; }
        .receipt-body { padding:30px; }
        .info-grid { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:30px; border-bottom:2px dashed #e0e6ed; padding-bottom:20px; }
        .info-block h4 { font-size:12px; color:#6c86a3; margin-bottom:6px; }
        .amount-paid { font-size:32px; font-weight:800; color:#34C759; margin:20px 0; text-align:center; }
        .items-table { width:100%; border-collapse:collapse; margin:20px 0; }
        .items-table th,.items-table td { padding:10px; text-align:left; border-bottom:1px solid #eef2f6; font-size:13px; }
        .footer-note { text-align:center; border-top:1px solid #eef2f6; padding:20px; font-size:12px; color:#7f8c8d; }
        .button-bar { text-align:center; margin:20px; }
        .print-btn { background:#0A84FF; color:white; border:none; padding:10px 24px; border-radius:30px; cursor:pointer; }
        @media print { body { background:white; padding:0; } .button-bar { display:none; } .receipt-container { box-shadow:none; } }
    </style>
    </head>
    <body>
    <div class="receipt-container">
        <div class="receipt-header"><div class="clinic-name"><?php echo $clinic_name; ?></div><div class="receipt-title">OFFICIAL RECEIPT</div></div>
        <div class="receipt-body">
            <div class="info-grid">
                <div class="info-block"><h4>Receipt No.</h4><p>RCP-<?php echo str_pad($payment['id'],6,'0',STR_PAD_LEFT); ?></p></div>
                <div class="info-block"><h4>Date</h4><p><?php echo date('d M Y', strtotime($payment[$has_payment_date ? 'payment_date' : 'created_at'])); ?></p></div>
                <div class="info-block"><h4>Patient Name</h4><p><?php echo htmlspecialchars($payment['patient_name']); ?></p></div>
                <div class="info-block"><h4>Patient ID</h4><p><?php echo htmlspecialchars($payment['patient_code']); ?></p></div>
                <div class="info-block"><h4>Invoice #</h4><p><?php echo htmlspecialchars($payment['invoice_number']); ?></p></div>
                <div class="info-block"><h4>Payment Method</h4><p><?php echo str_replace('_',' ',$payment['payment_method']); ?></p></div>
            </div>
            <?php if (!empty($items)): ?>
            <h4 style="margin:20px 0 10px;">Billed Services</h4>
            <table class="items-table"><thead><tr><th>Description</th><th>Qty</th><th>Unit Price</th><th>Total</th></tr></thead>
            <tbody><?php foreach($items as $item): ?><tr><td><?php echo htmlspecialchars($item['description']); ?></td><td><?php echo $item['quantity']; ?></td><td><?php echo number_format($item['unit_price'],2); ?></td><td><?php echo number_format($item['total_price'],2); ?></td></tr><?php endforeach; ?></tbody>
            </table>
            <?php endif; ?>
            <div class="amount-paid">Amount Paid: <?php echo number_format($payment['amount'],2); ?> USD</div>
            <div>Outstanding Balance: <?php echo number_format($payment['balance_due'],2); ?> USD</div>
        </div>
        <div class="footer-note"><?php echo $clinic_address; ?> | Tel: <?php echo $clinic_phone; ?> | Thank you</div>
        <div class="button-bar"><button class="print-btn" onclick="window.print();"><i class="fas fa-print"></i> Print / PDF</button> <a href="receipts.php" class="print-btn" style="background:#6c757d;">Back</a></div>
    </div>
    </body>
    </html>
    <?php
    exit();
}

// =====================================================
// LIST ALL RECEIPTS (with fallback for date column)
// =====================================================
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;
$filter_patient = isset($_GET['patient']) ? (int)$_GET['patient'] : 0;
$filter_date = isset($_GET['date']) ? $_GET['date'] : '';

$where = "1=1";
$params = [];
$types = "";
if ($filter_patient) {
    $where .= " AND p.id = ?";
    $params[] = $filter_patient;
    $types .= "i";
}
if ($filter_date) {
    if ($has_payment_date) {
        $where .= " AND DATE(pay.payment_date) = ?";
    } else {
        $where .= " AND DATE(pay.created_at) = ?";
    }
    $params[] = $filter_date;
    $types .= "s";
}

$countSql = "SELECT COUNT(*) as total FROM payments pay JOIN patients p ON pay.patient_id = p.id WHERE $where";
$stmt = $conn->prepare($countSql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$total = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();
$totalPages = ceil($total / $limit);

$sql = "SELECT pay.*, p.full_name as patient_name, p.patient_id as patient_code, inv.invoice_number 
        FROM payments pay 
        JOIN patients p ON pay.patient_id = p.id 
        JOIN invoices inv ON pay.invoice_id = inv.id 
        WHERE $where 
        ORDER BY $date_column DESC 
        LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";
$stmt = $conn->prepare($sql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$patients = $conn->query("SELECT id, full_name FROM patients ORDER BY full_name")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html>
<head><title>Receipts | VeeCare</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<style>
    /* same sidebar styles as before (included) */
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family:'Inter',sans-serif; background:#f0f2f5; }
    .sidebar { width:260px; background:linear-gradient(180deg,#0f0f1a 0%,#1a1a2e 100%); color:#fff; position:fixed; left:0; top:0; height:100vh; overflow-y:auto; z-index:100; }
    .sidebar-header { padding:24px 20px; border-bottom:1px solid rgba(255,255,255,0.1); }
    .sidebar-header h2 { font-size:20px; display:flex; align-items:center; gap:10px; }
    .sidebar-header h2 i { color:#0A84FF; }
    .sidebar-header p { font-size:11px; color:rgba(255,255,255,0.5); margin-top:5px; }
    .sidebar-nav { padding:20px; }
    .nav-link { display:flex; align-items:center; gap:12px; padding:10px 12px; color:rgba(255,255,255,0.7); text-decoration:none; border-radius:8px; transition:all 0.2s; font-size:14px; }
    .nav-link:hover,.nav-link.active { background:rgba(10,132,255,0.15); color:#0A84FF; }
    .nav-link i { width:20px; }
    .nav-divider { height:1px; background:rgba(255,255,255,0.08); margin:12px 0; }
    .nav-category { font-size:10px; text-transform:uppercase; color:rgba(255,255,255,0.4); padding:8px 12px; margin-top:8px; }
    .main-content { margin-left:260px; padding:24px; }
    .page-header { background:#fff; border-radius:20px; padding:20px 28px; margin-bottom:24px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px; box-shadow:0 2px 8px rgba(0,0,0,0.04); }
    .btn-back { display:inline-flex; align-items:center; gap:8px; padding:10px 20px; background:#f0f2f5; color:#666; text-decoration:none; border-radius:12px; font-weight:500; }
    .filter-bar { background:#fff; border-radius:20px; padding:20px 24px; margin-bottom:24px; display:flex; flex-wrap:wrap; gap:16px; align-items:flex-end; }
    .filter-group { display:flex; flex-direction:column; gap:6px; }
    .filter-group label { font-size:12px; font-weight:600; }
    .filter-group input,.filter-group select { padding:8px 12px; border:1px solid #ddd; border-radius:10px; }
    .filter-group button { background:#0A84FF; color:white; border:none; padding:8px 20px; border-radius:10px; cursor:pointer; }
    .table-card { background:#fff; border-radius:20px; overflow:auto; box-shadow:0 2px 8px rgba(0,0,0,0.04); }
    .data-table { width:100%; border-collapse:collapse; }
    .data-table th,.data-table td { padding:14px 16px; text-align:left; border-bottom:1px solid #eef2f6; }
    .action-btn { padding:6px 12px; border-radius:8px; text-decoration:none; font-size:12px; background:rgba(10,132,255,0.1); color:#0A84FF; display:inline-flex; align-items:center; gap:6px; }
    .pagination { margin-top:24px; display:flex; justify-content:center; gap:8px; }
    .page-link { padding:8px 14px; background:#fff; color:#666; text-decoration:none; border-radius:10px; border:1px solid #eef2f6; }
    .page-link.active,.page-link:hover { background:#0A84FF; color:white; }
    @media (max-width:768px) { .sidebar { transform:translateX(-100%); } .main-content { margin-left:0; } }
</style>
</head>
<body>
<aside class="sidebar">
    <div class="sidebar-header"><h2><i class="fas fa-heartbeat"></i> VeeCare</h2><p>Medical Centre</p></div>
    <nav class="sidebar-nav">
        <div class="nav-item"><a href="../dashboard.php" class="nav-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a></div>
        <div class="nav-divider"></div>
        <div class="nav-category">Billing</div>
        <div class="nav-item"><a href="invoices.php" class="nav-link"><i class="fas fa-file-invoice"></i> Invoices</a></div>
        <div class="nav-item"><a href="payments.php" class="nav-link"><i class="fas fa-credit-card"></i> Payments</a></div>
        <div class="nav-item"><a href="receipts.php" class="nav-link active"><i class="fas fa-receipt"></i> Receipts</a></div>
        <div class="nav-divider"></div>
        <div class="nav-item"><a href="../logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
    </nav>
</aside>
<main class="main-content">
    <div class="page-header"><h1>Payment Receipts</h1><a href="../dashboard.php" class="btn-back"><i class="fas fa-arrow-left"></i> Dashboard</a></div>
    <div class="filter-bar">
        <form method="GET" style="display:flex; flex-wrap:wrap; gap:16px; width:100%;">
            <div class="filter-group"><label>Patient</label><select name="patient"><option value="0">All</option><?php foreach($patients as $p){ echo "<option value='{$p['id']}' ".($filter_patient==$p['id']?'selected':'').">{$p['full_name']}</option>"; } ?></select></div>
            <div class="filter-group"><label>Date</label><input type="date" name="date" value="<?php echo htmlspecialchars($filter_date); ?>"></div>
            <div class="filter-group"><button type="submit"><i class="fas fa-filter"></i> Filter</button></div>
            <div class="filter-group"><a href="receipts.php" class="btn-back" style="background:#f0f2f5;">Clear</a></div>
        </form>
    </div>
    <div class="table-card">
        <?php if (empty($payments)): ?><div class="empty-state" style="text-align:center; padding:40px;">No receipts found.</div>
        <?php else: ?>
        <table class="data-table"><thead><tr><th>Receipt No.</th><th>Date</th><th>Patient</th><th>Invoice #</th><th>Amount Paid</th><th>Method</th><th>Action</th></tr></thead>
        <tbody><?php foreach($payments as $pay): ?><tr>
            <td>RCP-<?php echo str_pad($pay['id'],6,'0',STR_PAD_LEFT); ?></td>
            <td><?php echo date('d M Y', strtotime($pay[$has_payment_date ? 'payment_date' : 'created_at'])); ?></td>
            <td><strong><?php echo htmlspecialchars($pay['patient_name']); ?></strong><br><span style="font-size:12px;"><?php echo htmlspecialchars($pay['patient_code']); ?></span></td>
            <td><?php echo htmlspecialchars($pay['invoice_number']); ?></td>
            <td><?php echo number_format($pay['amount'],2); ?> USD</td>
            <td><?php echo str_replace('_',' ',$pay['payment_method']); ?></td>
            <td><a href="receipts.php?id=<?php echo $pay['id']; ?>" class="action-btn" target="_blank"><i class="fas fa-print"></i> View & Print</a></td>
        </tr><?php endforeach; ?></tbody>
        </table>
        <?php endif; ?>
    </div>
    <?php if ($totalPages>1): ?><div class="pagination"><?php for($i=1;$i<=$totalPages;$i++) echo "<a href='?page=$i&patient=$filter_patient&date=".urlencode($filter_date)."' class='page-link ".($i==$page?'active':'')."'>$i</a>"; ?></div><?php endif; ?>
</main>
</body>
</html>