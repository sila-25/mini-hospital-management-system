<?php
require_once __DIR__ . '/../config/database.php';

$GLOBAL_SETTINGS = [];

$res = $conn->query("SELECT setting_key, setting_value FROM clinic_settings");

while ($row = $res->fetch_assoc()) {
    $GLOBAL_SETTINGS[$row['setting_key']] = $row['setting_value'];
}