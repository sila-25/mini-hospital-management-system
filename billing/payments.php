<?php
session_start();
if (!isset($_SESSION['user_id'])) { 
    header("Location: login.php"); 
    exit(); 
}
require_once '../config/database.php';
$conn = getConnection();

// Check if payment_date column exists, otherwise use created_at
$check_col = $conn->query("SHOW COLUMNS FROM payments LIKE 'payment_date'");
$has_payment_date = ($check_col && $check_col->num_rows > 0);
$date_column = $has_payment_date ? 'pay.payment_date' : 'pay.created_at';

$invoice_id = isset($_GET['invoice_id']) ? (int)$_GET['invoice_id'] : 0;
$success = false;
$error = '';
$invoice = null;

if ($invoice_id) {
    $stmt = $conn->prepare("SELECT * FROM invoices WHERE id = ?");
    $stmt->bind_param("i", $invoice_id);
    $stmt->execute();
    $invoice = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Process payment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $invoice_id = (int)$_POST['invoice_id'];
    $amount = (float)$_POST['amount'];
    $method = $_POST['payment_method'];
    $reference = trim($_POST['reference']);
    $payment_date = $_POST['payment_date'];
    $notes = trim($_POST['notes']);
    
    $stmt = $conn->prepare("SELECT * FROM invoices WHERE id = ?");
    $stmt->bind_param("i", $invoice_id);
    $stmt->execute();
    $inv = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$inv) {
        $error = "Invoice not found.";
    } elseif ($amount <= 0) {
        $error = "Amount must be positive.";
    } elseif ($amount > $inv['balance_due']) {
        $error = "Amount exceeds outstanding balance.";
    } else {
        $conn->begin_transaction();
        try {
            // Use payment_date if column exists, otherwise use created_at (will be set by NOW())
            if ($has_payment_date) {
                $sql = "INSERT INTO payments (invoice_id, patient_id, amount, payment_method, transaction_id, payment_date, notes, status, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'completed', NOW())";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iidssss", $invoice_id, $inv['patient_id'], $amount, $method, $reference, $payment_date, $notes);
            } else {
                $sql = "INSERT INTO payments (invoice_id, patient_id, amount, payment_method, transaction_id, notes, status, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, 'completed', NOW())";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iidsss", $invoice_id, $inv['patient_id'], $amount, $method, $reference, $notes);
            }
            $stmt->execute();
            $payment_id = $stmt->insert_id;
            $stmt->close();
            
            $new_paid = $inv['amount_paid'] + $amount;
            $new_balance = $inv['total_amount'] - $new_paid;
            $new_status = ($new_balance <= 0) ? 'paid' : (($new_paid > 0) ? 'partially_paid' : 'pending');
            $upd = $conn->prepare("UPDATE invoices SET amount_paid = ?, balance_due = ?, status = ? WHERE id = ?");
            $upd->bind_param("ddsi", $new_paid, $new_balance, $new_status, $invoice_id);
            $upd->execute();
            $upd->close();
            
            $conn->commit();
            $success = true;
            // Refresh invoice data
            $stmt2 = $conn->prepare("SELECT * FROM invoices WHERE id = ?");
            $stmt2->bind_param("i", $invoice_id);
            $stmt2->execute();
            $invoice = $stmt2->get_result()->fetch_assoc();
            $stmt2->close();
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Database error: " . $e->getMessage();
        }
    }
}

// Pagination and filters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;
$filter_patient = isset($_GET['patient']) ? (int)$_GET['patient'] : 0;
$filter_method = isset($_GET['method']) ? $_GET['method'] : '';
$filter_date = isset($_GET['date']) ? $_GET['date'] : '';

$where = "1=1";
$params = [];
$types = "";
if ($filter_patient) {
    $where .= " AND p.id = ?";
    $params[] = $filter_patient;
    $types .= "i";
}
if ($filter_method) {
    $where .= " AND pay.payment_method = ?";
    $params[] = $filter_method;
    $types .= "s";
}
if ($filter_date && $has_payment_date) {
    $where .= " AND DATE(pay.payment_date) = ?";
    $params[] = $filter_date;
    $types .= "s";
} elseif ($filter_date && !$has_payment_date) {
    $where .= " AND DATE(pay.created_at) = ?";
    $params[] = $filter_date;
    $types .= "s";
}

// Count total
$countSql = "SELECT COUNT(*) as total FROM payments pay JOIN patients p ON pay.patient_id = p.id WHERE $where";
$stmt = $conn->prepare($countSql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$total = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();
$totalPages = ceil($total / $limit);

// Fetch payments list - dynamically use correct date column
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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payments | VeeCare</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Include all the CSS from the previous correct version (sidebar, cards, etc.) */
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
        .btn-back { display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; background: #f0f2f5; color: #666; text-decoration: none; border-radius: 12px; font-weight: 500; }
        .filter-bar { background: #fff; border-radius: 20px; padding: 20px 24px; margin-bottom: 24px; display: flex; flex-wrap: wrap; gap: 16px; align-items: flex-end; }
        .filter-group { display: flex; flex-direction: column; gap: 6px; }
        .filter-group label { font-size: 12px; font-weight: 600; }
        .filter-group input, .filter-group select { padding: 8px 12px; border: 1px solid #ddd; border-radius: 10px; }
        .filter-group button { background: #0A84FF; color: white; border: none; padding: 8px 20px; border-radius: 10px; cursor: pointer; }
        .form-card { background: #fff; border-radius: 24px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); overflow: hidden; margin-bottom: 24px; }
        .form-section { padding: 24px; border-bottom: 1px solid #eef2f6; }
        .form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
        .full-width { grid-column: span 2; }
        .form-actions { padding: 20px; display: flex; justify-content: flex-end; gap: 12px; }
        .btn-submit { background: linear-gradient(135deg, #0A84FF, #006EDB); color: white; border: none; padding: 12px 28px; border-radius: 12px; font-weight: 600; cursor: pointer; }
        .data-table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 20px; overflow: hidden; }
        .data-table th, .data-table td { padding: 12px 16px; text-align: left; border-bottom: 1px solid #eef2f6; }
        .action-btn { padding: 4px 10px; border-radius: 6px; text-decoration: none; font-size: 12px; background: rgba(10,132,255,0.1); color: #0A84FF; display: inline-flex; align-items: center; gap: 4px; }
        .pagination { margin-top: 20px; display: flex; justify-content: center; gap: 8px; }
        .page-link { padding: 8px 14px; background: #fff; color: #666; text-decoration: none; border-radius: 10px; border: 1px solid #eef2f6; }
        .alert { padding: 12px 16px; border-radius: 12px; margin-bottom: 20px; }
        .alert-success { background: rgba(52,199,89,0.1); color: #34C759; border-left: 3px solid #34C759; }
        .alert-error { background: rgba(255,59,48,0.1); color: #FF3B30; border-left: 3px solid #FF3B30; }
        @media (max-width: 768px) { .sidebar { transform: translateX(-100%); } .main-content { margin-left: 0; } .form-grid { grid-template-columns: 1fr; } .full-width { grid-column: span 1; } }
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
        <div class="nav-item"><a href="payments.php" class="nav-link active"><i class="fas fa-credit-card"></i> Payments</a></div>
        <div class="nav-item"><a href="receipts.php" class="nav-link"><i class="fas fa-receipt"></i> Receipts</a></div>
        <div class="nav-divider"></div>
        <div class="nav-item"><a href="../logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
    </nav>
</aside>

<main class="main-content">
    <div class="page-header">
        <h1>Payments</h1>
        <a href="../dashboard.php" class="btn-back"><i class="fas fa-arrow-left"></i> Dashboard</a>
    </div>

    <?php if ($invoice_id && $invoice): ?>
    <div class="form-card">
        <div class="form-section">
            <h3>Record Payment for Invoice #<?php echo htmlspecialchars($invoice['invoice_number']); ?></h3>
            <p>Outstanding Balance: <strong><?php echo number_format($invoice['balance_due'], 2); ?> USD</strong></p>
            <?php if ($success): ?>
                <div class="alert alert-success">Payment recorded successfully! <a href="receipts.php?id=<?php echo $payment_id; ?>">View Receipt</a></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            <form method="POST">
                <input type="hidden" name="invoice_id" value="<?php echo $invoice_id; ?>">
                <div class="form-grid">
                    <div><label>Amount</label><input type="number" step="0.01" name="amount" required></div>
                    <div><label>Payment Method</label>
                        <select name="payment_method">
                            <option value="cash">Cash</option>
                            <option value="mobile_money">Mobile Money</option>
                            <option value="credit_card">Credit Card</option>
                            <option value="insurance">Insurance</option>
                        </select>
                    </div>
                    <div><label>Transaction Reference</label><input type="text" name="reference" placeholder="Optional"></div>
                    <div><label>Payment Date</label><input type="date" name="payment_date" value="<?php echo date('Y-m-d'); ?>" required></div>
                    <div class="full-width"><label>Notes</label><textarea name="notes" rows="2"></textarea></div>
                </div>
                <div class="form-actions">
                    <a href="invoices.php" class="btn-back">Cancel</a>
                    <button type="submit" class="btn-submit">Record Payment</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <div class="filter-bar">
        <form method="GET" style="display:flex; flex-wrap:wrap; gap:16px; width:100%;">
            <div class="filter-group">
                <label>Patient</label>
                <select name="patient">
                    <option value="0">All Patients</option>
                    <?php foreach ($patients as $p): ?>
                        <option value="<?php echo $p['id']; ?>" <?php echo ($filter_patient == $p['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($p['full_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Method</label>
                <select name="method">
                    <option value="">All</option>
                    <option value="cash" <?php echo ($filter_method == 'cash') ? 'selected' : ''; ?>>Cash</option>
                    <option value="mobile_money" <?php echo ($filter_method == 'mobile_money') ? 'selected' : ''; ?>>Mobile Money</option>
                    <option value="credit_card" <?php echo ($filter_method == 'credit_card') ? 'selected' : ''; ?>>Credit Card</option>
                    <option value="insurance" <?php echo ($filter_method == 'insurance') ? 'selected' : ''; ?>>Insurance</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Date</label>
                <input type="date" name="date" value="<?php echo $filter_date; ?>">
            </div>
            <div class="filter-group">
                <button type="submit"><i class="fas fa-filter"></i> Apply</button>
            </div>
            <div class="filter-group">
                <a href="payments.php" class="btn-back" style="background:#f0f2f5;">Clear</a>
            </div>
        </form>
    </div>

    <div style="background:#fff; border-radius:20px; overflow:auto;">
        <table class="data-table">
            <thead>
                <tr><th>Date</th><th>Invoice #</th><th>Patient</th><th>Amount</th><th>Method</th><th>Reference</th><th>Receipt</th></tr>
            </thead>
            <tbody>
                <?php if (empty($payments)): ?>
                    <tr><td colspan="7" style="text-align:center; padding:40px;">No payments found. </td></tr>
                <?php else: ?>
                    <?php foreach ($payments as $p): ?>
                    <tr>
                        <td><?php 
                            if ($has_payment_date && isset($p['payment_date'])) {
                                echo date('d M Y', strtotime($p['payment_date']));
                            } else {
                                echo date('d M Y', strtotime($p['created_at']));
                            }
                        ?></td>
                        <td><?php echo htmlspecialchars($p['invoice_number']); ?></td>
                        <td><?php echo htmlspecialchars($p['patient_name']); ?></td>
                        <td><?php echo number_format($p['amount'], 2); ?> USD</td>
                        <td><?php echo str_replace('_', ' ', $p['payment_method']); ?></td>
                        <td><?php echo htmlspecialchars($p['transaction_id'] ?? '—'); ?></td>
                        <td><a href="receipts.php?id=<?php echo $p['id']; ?>" target="_blank" class="action-btn"><i class="fas fa-receipt"></i> Receipt</a></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?page=<?php echo $i; ?>&patient=<?php echo $filter_patient; ?>&method=<?php echo urlencode($filter_method); ?>&date=<?php echo $filter_date; ?>" class="page-link <?php echo ($i == $page) ? 'active' : ''; ?>"><?php echo $i; ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</main>
</body>
</html>