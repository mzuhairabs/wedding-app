<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Semak jika pengguna belum log masuk, lencongkan terus ke halaman login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
?>