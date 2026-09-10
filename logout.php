<?php
// Aktifkan pameran ralat untuk debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 1. Memulakan sesi jika belum bermula
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Kosongkan semua pembolehubah sesi
$_SESSION = array();

// 3. Padamkan kuki sesi jika ada
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), 
        '', 
        time() - 42000,
        $params["path"], 
        $params["domain"],
        $params["secure"], 
        $params["httponly"]
    );
}

// 4. Musnahkan sesi sepenuhnya
session_destroy();

// 5. Lencongkan ke halaman login.php
header("Location: login.php");
exit();
?>