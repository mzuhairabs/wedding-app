<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/db.php';

// Semak sesi pengguna
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['id'])) {
    $crew_id = $_POST['crew_id'] ?? $_GET['id'] ?? null;

    if ($crew_id) {
        // Soft delete: Kemaskini is_deleted = 1
        $sql = "UPDATE crew SET is_deleted = 1 WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $crew_id);

        if ($stmt->execute()) {
            $stmt->close();
            header("Location: ../crew.php?status=deleted");
            exit();
        } else {
            die("Ralat MySQL: " . $stmt->error);
        }
    }
}

header("Location: ../crew.php");
exit();