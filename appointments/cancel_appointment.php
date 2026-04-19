<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
require_once '../config/database.php';
$conn = getConnection();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id) {
    $stmt = $conn->prepare("UPDATE appointments SET status = 'cancelled' WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
}
header("Location: view_appointments.php");
exit();
?>