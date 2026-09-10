<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $invoice_id     = (int)$_POST['invoice_id'];
    $amount_paid    = (float)$_POST['amount_paid'];
    $payment_type   = $_POST['payment_type'] ?? 'deposit';
    $payment_method = $_POST['payment_method'] ?? 'Online Transfer';
    $receipt_ref_no = $_POST['receipt_ref_no'] ?? '';
    $payment_date   = $_POST['payment_date'] ?? date('Y-m-d');

    if ($invoice_id <= 0 || $amount_paid <= 0) {
        die("<script>alert('Ralat: Maklumat bayaran tidak sah!'); window.history.back();</script>");
    }

    // 1. Simpan rekod ke dalam jadual payments
    $stmt = $conn->prepare("
        INSERT INTO payments (invoice_id, amount_paid, payment_type, payment_method, receipt_ref_no, payment_date) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("idssss", $invoice_id, $amount_paid, $payment_type, $payment_method, $receipt_ref_no, $payment_date);

    if ($stmt->execute()) {
        
        // 2. Semak jumlah terkini yang telah dibayar bagi invois ini
        $sumStmt = $conn->prepare("SELECT SUM(amount_paid) AS total_paid FROM payments WHERE invoice_id = ?");
        $sumStmt->bind_param("i", $invoice_id);
        $sumStmt->execute();
        $total_paid = (float)($sumStmt->get_result()->fetch_assoc()['total_paid'] ?? 0);

        // 3. Ambil jumlah keseluruhan harga dari jadual invoices
        $invStmt = $conn->prepare("SELECT total_amount FROM invoices WHERE id = ?");
        $invStmt->bind_param("i", $invoice_id);
        $invStmt->execute();
        $total_amount = (float)($invStmt->get_result()->fetch_assoc()['total_amount'] ?? 0);

        // 4. Kemaskini status invois (paid, partially_paid, unpaid)
        $new_status = 'unpaid';
        if ($total_paid >= $total_amount) {
            $new_status = 'paid';
        } elseif ($total_paid > 0) {
            $new_status = 'partially_paid';
        }

        $updateStmt = $conn->prepare("UPDATE invoices SET status = ? WHERE id = ?");
        $updateStmt->bind_param("si", $new_status, $invoice_id);
        $updateStmt->execute();

        echo "<script>
            alert('Bayaran RM " . number_format($amount_paid, 2) . " berjaya direkodkan!');
            window.location.href = '../index.php';
        </script>";
    } else {
        echo "<script>
            alert('Gagal menyimpan bayaran: " . addslashes($conn->error) . "');
            window.history.back();
        </script>";
    }

    $stmt->close();
    $conn->close();
}
?>