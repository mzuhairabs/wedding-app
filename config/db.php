<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host = 'localhost';
$user = 'root'; 
$pass = '';     
$db   = 'wedding_pwa_db'; // Nama database asal mengikut struktur phpMyAdmin anda

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Sambungan Pangkalan Data Gagal: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");