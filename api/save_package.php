<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn->query("ALTER TABLE packages ADD COLUMN IF NOT EXISTS sort_order INT NOT NULL DEFAULT 0");

    $package_id    = isset($_POST['package_id']) ? (int)$_POST['package_id'] : 0;
    $package_name  = trim($_POST['package_name'] ?? '');
    $form_category = trim($_POST['form_category'] ?? 'wedding');
    $event_count   = (int)($_POST['event_count'] ?? 1);
    $base_price    = (float)($_POST['base_price'] ?? 0);
    $sort_order    = (int)($_POST['sort_order'] ?? 0);
    $is_active     = (int)($_POST['is_active'] ?? 1);
    $description   = trim($_POST['description'] ?? '');

    if (!empty($package_name)) {
        if ($package_id > 0) {
            $stmt = $conn->prepare("UPDATE packages SET package_name = ?, form_category = ?, event_count = ?, base_price = ?, sort_order = ?, is_active = ?, description = ? WHERE id = ?");
            $stmt->bind_param("ssidissi", $package_name, $form_category, $event_count, $base_price, $sort_order, $is_active, $description, $package_id);
            $stmt->execute();
        } else {
            $stmt = $conn->prepare("INSERT INTO packages (package_name, form_category, event_count, base_price, sort_order, is_active, description) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssidiss", $package_name, $form_category, $event_count, $base_price, $sort_order, $is_active, $description);
            $stmt->execute();
        }
    }
}

header("Location: ../packages.php");
exit();