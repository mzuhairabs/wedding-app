<?php
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category_id   = $_POST['category_id'];
    $merchant_name = $_POST['merchant_name'];
    $amount        = $_POST['amount'];
    $expense_date  = $_POST['expense_date'];
    $description   = $_POST['description'];
    
    $receipt_path = null;

    if (isset($_FILES['receipt_image']) && $_FILES['receipt_image']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath   = $_FILES['receipt_image']['tmp_name'];
        $fileName      = $_FILES['receipt_image']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];

        if (in_array($fileExtension, $allowedExtensions)) {
            $newFileName = 'resit_' . time() . '_' . uniqid() . '.' . $fileExtension;
            
            // Laluan mutlak terus ke folder uploads
            $targetDir  = '/Applications/XAMPP/xamppfiles/htdocs/wedding-app/assets/uploads/';
            $targetPath = $targetDir . $newFileName;

            // Pastikan folder wujud
            if (!file_exists($targetDir)) {
                mkdir($targetDir, 0777, true);
            }

            if (move_uploaded_file($fileTmpPath, $targetPath)) {
                $receipt_path = 'assets/uploads/' . $newFileName;
            } else {
                $lastError = error_get_last();
                die("Gagal memindahkan fail. Punca: " . ($lastError['message'] ?? 'Permisi dinafikan'));
            }
        } else {
            die("Format fail tidak dibenarkan! Gunakan JPG, PNG, WEBP, atau PDF.");
        }
    }

    // Simpan ke database
    $stmt = $conn->prepare("INSERT INTO expenses (category_id, merchant_name, amount, expense_date, receipt_image_path, description) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isdsss", $category_id, $merchant_name, $amount, $expense_date, $receipt_path, $description);

    if ($stmt->execute()) {
        echo "<script>
            alert('Perbelanjaan & Resit Berjaya Disimpan!');
            window.location.href = '../expenses.php';
        </script>";
    } else {
        echo "Ralat Database: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
}
?>