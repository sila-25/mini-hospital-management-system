<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_name = $_SESSION['user_name'] ?? 'Guest';
$user_role = $_SESSION['user_role'] ?? '';
$user_avatar = strtoupper(substr($user_name, 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VeeCare Medical Centre</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
<div class="app-container">