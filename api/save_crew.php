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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $crew_id             = !empty($_POST['crew_id']) ? (int)$_POST['crew_id'] : null;
    $name                = trim($_POST['name'] ?? '');
    $ic_number           = trim($_POST['ic_number'] ?? '');
    $marital_status      = $_POST['marital_status'] ?? 'single';
    $employment_type    = $_POST['employment_type'] ?? 'parttime';
    $role                = trim($_POST['role'] ?? '');
    $start_date          = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
    $basic_salary        = isset($_POST['basic_salary']) && $_POST['basic_salary'] !== '' ? (float)$_POST['basic_salary'] : 0.00;
    $phone_number        = trim($_POST['phone_number'] ?? '');
    $emergency_phone     = trim($_POST['emergency_phone'] ?? '');
    $bank_name           = trim($_POST['bank_name'] ?? '');
    $bank_account_number = trim($_POST['bank_account_number'] ?? '');
    $epf_number          = trim($_POST['epf_number'] ?? '');
    $socso_number        = trim($_POST['socso_number'] ?? '');

    // === PROSES MUAT NAIK GAMBAR ===
    $profile_picture = null;
    
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath   = $_FILES['profile_picture']['tmp_name'];
        $fileName      = $_FILES['profile_picture']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
        
        if (in_array($fileExtension, $allowedExtensions)) {
            $projectRoot = dirname(__DIR__); // Merujuk ke /wedding-app
            $targetDir   = $projectRoot . '/uploads/';
            
            if (!file_exists($targetDir)) {
                @mkdir($targetDir, 0777, true);
            }

            $newFileName = 'crew_' . time() . '_' . uniqid() . '.' . $fileExtension;
            $destPath    = $targetDir . $newFileName;

            if (move_uploaded_file($fileTmpPath, $destPath)) {
                $profile_picture = 'uploads/' . $newFileName;
            } else {
                die("Ralat: Gagal memindahkan gambar. Sila pastikan folder 'uploads' wujud dan diberi kebenaran 777.");
            }
        } else {
            die("Ralat: Format fail tidak disokong. Sila guna format JPG, JPEG, PNG, atau WEBP.");
        }
    }

    if ($crew_id) {
        // === PROSES UPDATE (KEMASKINI) ===
        if ($profile_picture) {
            $sql = "UPDATE crew SET 
                    name = ?, ic_number = ?, marital_status = ?, employment_type = ?, role = ?, 
                    start_date = ?, basic_salary = ?, phone_number = ?, emergency_phone = ?, 
                    bank_name = ?, bank_account_number = ?, epf_number = ?, socso_number = ?, 
                    profile_picture = ? 
                    WHERE id = ?";
            $stmt = $conn->prepare($sql);
            if (!$stmt) { die("Ralat Prepare (Update 1): " . $conn->error); }
            
            $stmt->bind_param("ssssssdsssssssi", 
                $name, $ic_number, $marital_status, $employment_type, $role, 
                $start_date, $basic_salary, $phone_number, $emergency_phone, 
                $bank_name, $bank_account_number, $epf_number, $socso_number, 
                $profile_picture, $crew_id
            );
        } else {
            $sql = "UPDATE crew SET 
                    name = ?, ic_number = ?, marital_status = ?, employment_type = ?, role = ?, 
                    start_date = ?, basic_salary = ?, phone_number = ?, emergency_phone = ?, 
                    bank_name = ?, bank_account_number = ?, epf_number = ?, socso_number = ? 
                    WHERE id = ?";
            $stmt = $conn->prepare($sql);
            if (!$stmt) { die("Ralat Prepare (Update 2): " . $conn->error); }
            
            $stmt->bind_param("ssssssdssssssi", 
                $name, $ic_number, $marital_status, $employment_type, $role, 
                $start_date, $basic_salary, $phone_number, $emergency_phone, 
                $bank_name, $bank_account_number, $epf_number, $socso_number, 
                $crew_id
            );
        }
    } else {
        // === PROSES INSERT (TAMBAH BAHARU) ===
        $sql = "INSERT INTO crew 
                (name, ic_number, marital_status, employment_type, role, start_date, basic_salary, phone_number, emergency_phone, bank_name, bank_account_number, epf_number, socso_number, profile_picture) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) { die("Ralat Prepare (Insert): " . $conn->error); }
        
        $stmt->bind_param("ssssssdsssssss", 
            $name, $ic_number, $marital_status, $employment_type, $role, 
            $start_date, $basic_salary, $phone_number, $emergency_phone, 
            $bank_name, $bank_account_number, $epf_number, $socso_number, 
            $profile_picture
        );
    }

    if ($stmt->execute()) {
        $stmt->close();
        header("Location: ../crew.php?status=success");
        exit();
    } else {
        die("Ralat Execute MySQL: " . $stmt->error);
    }
}