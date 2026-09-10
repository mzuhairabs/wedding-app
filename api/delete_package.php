<?php
require_once '../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

if (isset($_GET['id']) && !empty($_GET['id'])) {
    $package_id = (int)$_GET['id'];

    $stmt = $conn->prepare("DELETE FROM packages WHERE id = ?");
    $stmt->bind_param("i", $package_id);

    if ($stmt->execute()) {
        echo "<script>
            alert('Pakej berjaya dipadam!');
            window.location.href = '../packages.php';
        </script>";
    } else {
        echo "<script>
            alert('Ralat memadam pakej. Pakej mungkin sedang digunakan oleh rekod tempahan lain.');
            window.location.href = '../packages.php';
        </script>";
    }

    $stmt->close();
    $conn->close();
} else {
    header("Location: ../packages.php");
    exit();
}
?>