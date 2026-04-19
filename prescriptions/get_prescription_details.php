<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}
require_once '../config/database.php';
$conn = getConnection();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    echo json_encode(['success' => false]);
    exit();
}

$sql = "SELECT pr.*, p.full_name as patient_name, p.patient_id as patient_code, p.date_of_birth, u.full_name as doctor_name
        FROM prescriptions pr
        JOIN patients p ON pr.patient_id = p.id
        LEFT JOIN users u ON pr.doctor_id = u.id
        WHERE pr.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$rx = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$rx) {
    echo json_encode(['success' => false]);
    exit();
}

// Fetch items
$items = [];
$stmt = $conn->prepare("SELECT * FROM prescription_items WHERE prescription_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Calculate age
$age = (new DateTime($rx['date_of_birth']))->diff(new DateTime())->y;

$condition_colors = [
    'antimalarial' => ['color' => '#0A84FF', 'label' => '🔵 Antimalarial'],
    'pain_management' => ['color' => '#34C759', 'label' => '🟢 Pain Management'],
    'anti_inflammatory' => ['color' => '#FF9500', 'label' => '🟡 Anti-inflammatory'],
    'antibiotic' => ['color' => '#AF52DE', 'label' => '🟣 Antibiotic'],
    'trauma' => ['color' => '#FF3B30', 'label' => '🔴 Trauma Care']
];
$cat = $rx['treatment_category'];
$condition_label = $condition_colors[$cat]['label'] ?? 'General';
$condition_color = $condition_colors[$cat]['color'] ?? '#8E8E93';

echo json_encode([
    'success' => true,
    'prescription' => [
        'id' => $rx['id'],
        'prescription_number' => $rx['prescription_number'],
        'prescription_date' => date('M d, Y', strtotime($rx['prescription_date'])),
        'doctor_name' => $rx['doctor_name'] ?? 'Unknown',
        'diagnosis' => $rx['diagnosis'],
        'notes' => $rx['notes'],
        'status' => $rx['status'],
        'patient_name' => $rx['patient_name'],
        'patient_code' => $rx['patient_code'],
        'patient_age' => $age
    ],
    'items' => $items,
    'condition_label' => $condition_label,
    'condition_color' => $condition_color
]);
?>