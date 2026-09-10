<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Jika tiada session ID, sekat serta-merta
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    // Lencongkan guna Header PHP
    header("Location: login.php");
    
    // Backup: Lencongkan guna JavaScript jika header PHP terhalang
    echo "<script>window.location.href='login.php';</script>";
    exit();
}

$invoice_id = $_GET['id'] ?? 0;

// Ambil maklumat invois & pembayaran terdahulu
$invQuery = $conn->query("
    SELECT invoices.*, clients.client_name, events.event_title 
    FROM invoices 
    JOIN clients ON invoices.client_id = clients.id 
    JOIN events ON invoices.event_id = events.id 
    WHERE invoices.id = $invoice_id
");
$invoice = $invQuery->fetch_assoc();

if (!$invoice) {
    die("Invois tidak dijumpai!");
}

// Kira jumlah yang telah dibayar
$paidQuery = $conn->query("SELECT SUM(amount_paid) AS paid FROM payments WHERE invoice_id = $invoice_id");
$totalPaid = $paidQuery->fetch_assoc()['paid'] ?? 0;
$balance   = $invoice['total_amount'] - $totalPaid;
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rekod Bayaran Invois</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 text-gray-800 p-4 min-h-screen">

    <div class="max-w-md mx-auto bg-white rounded-xl shadow-md p-6 mt-4">
        <h2 class="text-xl font-bold text-gray-900 mb-2">💵 Rekod Bayaran Pelanggan</h2>
        <p class="text-sm text-gray-500 mb-4"><?= $invoice['invoice_number']; ?> - <?= htmlspecialchars($invoice['client_name']); ?></p>

        <div class="bg-gray-50 p-3 rounded-lg mb-4 space-y-1 text-sm">
            <div class="flex justify-between"><span>Jumlah Invois:</span> <strong class="text-gray-900">RM <?= number_format($invoice['total_amount'], 2); ?></strong></div>
            <div class="flex justify-between"><span>Telah Dibayar:</span> <strong class="text-green-600">RM <?= number_format($totalPaid, 2); ?></strong></div>
            <div class="flex justify-between text-base font-bold border-t pt-1"><span>Baki Tunggakan:</span> <strong class="text-red-600">RM <?= number_format($balance, 2); ?></strong></div>
        </div>

        <?php if($balance > 0): ?>
            <form action="api/save_payment.php" method="POST" class="space-y-4">
                <input type="hidden" name="invoice_id" value="<?= $invoice_id; ?>">

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Jumlah Bayaran (RM) *</label>
                    <input type="number" step="0.01" name="amount_paid" max="<?= $balance; ?>" value="<?= $balance; ?>" required 
                        class="w-full border border-gray-300 rounded-lg p-2.5 text-lg font-bold">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Jenis Bayaran *</label>
                    <select name="payment_type" required class="w-full border border-gray-300 rounded-lg p-2.5">
                        <option value="deposit">Deposit</option>
                        <option value="progress">Kemajuan / Progress</option>
                        <option value="final_balance">Baki Penuh / Final</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Kaedah Bayaran *</label>
                    <select name="payment_method" required class="w-full border border-gray-300 rounded-lg p-2.5">
                        <option value="Online Transfer">Online Transfer / Instant Transfer</option>
                        <option value="Cash">Tunai (Cash)</option>
                        <option value="Cheque">Cek</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">No. Rujukan Resit / Transaksi</label>
                    <input type="text" name="receipt_ref_no" placeholder="cth: Ref No. 9823719" class="w-full border border-gray-300 rounded-lg p-2.5">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tarikh Bayaran *</label>
                    <input type="date" name="payment_date" value="<?= date('Y-m-d'); ?>" required class="w-full border border-gray-300 rounded-lg p-2.5">
                </div>

                <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-4 rounded-lg shadow">
                    💾 Simpan Rekod Bayaran
                </button>
            </form>
        <?php else: ?>
            <div class="bg-green-100 text-green-800 p-3 rounded-lg text-center font-bold">
                🎉 Invois ini telah dilaskan sepenuhnya!
            </div>
            <a href="index.php" class="block text-center mt-4 text-blue-600 underline">Kembali ke Dashboard</a>
        <?php endif; ?>
    </div>

</body>
</html>