<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once '../config/database.php';
$conn = getConnection();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id > 0) {
    // Check if patient has appointments or prescriptions
    $check = $conn->prepare("SELECT COUNT(*) as count FROM appointments WHERE patient_id = ?");
    $check->bind_param("i", $id);
    $check->execute();
    $appointments = $check->get_result()->fetch_assoc()['count'];
    $check->close();
    
    $check2 = $conn->prepare("SELECT COUNT(*) as count FROM prescriptions WHERE patient_id = ?");
    $check2->bind_param("i", $id);
    $check2->execute();
    $prescriptions = $check2->get_result()->fetch_assoc()['count'];
    $check2->close();
    
    if ($appointments > 0 || $prescriptions > 0) {
        $_SESSION['delete_error'] = "Cannot delete patient with existing appointments or prescriptions. Archive instead.";
    } else {
        $stmt = $conn->prepare("DELETE FROM patients WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        $_SESSION['delete_success'] = "Patient deleted successfully.";
    }
}

header("Location: view_patients.php");
exit();
?>