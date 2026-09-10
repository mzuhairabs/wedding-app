<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/db.php';

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$invoice_id = (int)($_GET['invoice_id'] ?? $_GET['id'] ?? 0);

if ($invoice_id <= 0) {
    die("ID Invois tidak sah.");
}

$stmt = $conn->prepare("
    SELECT invoices.*, clients.client_name, clients.phone_number 
    FROM invoices 
    LEFT JOIN clients ON invoices.client_id = clients.id 
    WHERE invoices.id = ?
");
$stmt->bind_param("i", $invoice_id);
$stmt->execute();
$invoice = $stmt->get_result()->fetch_assoc();

if (!$invoice) {
    die("Invois tidak dijumpai.");
}

$paidStmt = $conn->prepare("SELECT SUM(amount_paid) AS paid FROM payments WHERE invoice_id = ?");
$paidStmt->bind_param("i", $invoice_id);
$paidStmt->execute();
$totalPaid = (float)($paidStmt->get_result()->fetch_assoc()['paid'] ?? 0);

$totalAmount = (float)$invoice['total_amount'];
$balance     = $totalAmount - $totalPaid;

$historyStmt = $conn->prepare("SELECT * FROM payments WHERE invoice_id = ? ORDER BY payment_date DESC, id DESC");
$historyStmt->bind_param("i", $invoice_id);
$historyStmt->execute();
$paymentHistory = $historyStmt->get_result();
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rekod Bayaran - <?= htmlspecialchars($invoice['invoice_number']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 text-slate-800 text-xs font-sans min-h-screen">

    <header class="bg-slate-900 text-white sticky top-0 z-30 shadow-md">
        <div class="max-w-7xl mx-auto px-4 py-3.5 flex justify-between items-center">
            <div>
                <h1 class="text-sm font-bold">💳 Penerimaan Bayaran Invois</h1>
                <p class="text-[11px] text-slate-400"><?= htmlspecialchars($invoice['invoice_number']); ?> • <?= htmlspecialchars($invoice['client_name']); ?></p>
            </div>
            <a href="clients.php" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-md border border-slate-700">← Kembali ke Pelanggan</a>
        </div>
    </header>

    <main class="max-w-md mx-auto px-4 py-8">
        <div class="bg-white rounded-xl border border-slate-200/80 shadow-sm p-6 space-y-5">
            
            <div class="bg-slate-50 p-3.5 rounded-lg border border-slate-200 space-y-1.5">
                <div class="flex justify-between">
                    <span class="text-slate-500">Jumlah Invois:</span> 
                    <strong class="font-mono text-slate-900">RM <?= number_format($totalAmount, 2); ?></strong>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Telah Dibayar:</span> 
                    <strong class="font-mono text-emerald-600">RM <?= number_format($totalPaid, 2); ?></strong>
                </div>
                <div class="flex justify-between text-sm font-bold border-t border-slate-200 pt-2 mt-1">
                    <span>Baki Tunggakan:</span> 
                    <strong class="font-mono <?= $balance > 0 ? 'text-rose-600' : 'text-emerald-600'; ?>">RM <?= number_format($balance, 2); ?></strong>
                </div>
            </div>

            <?php if($balance > 0.01): ?>
                <form action="api/save_payment.php" method="POST" class="space-y-3.5">
                    <input type="hidden" name="invoice_id" value="<?= $invoice_id; ?>">

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Jumlah Bayaran (RM) *</label>
                        <input type="number" step="0.01" name="amount_paid" max="<?= $balance; ?>" value="<?= number_format($balance, 2, '.', ''); ?>" required 
                            class="w-full border border-slate-200 rounded-lg p-2.5 text-base font-bold font-mono focus:outline-none focus:border-indigo-500">
                    </div>

                    <div class="grid grid-cols-2 gap-2.5">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Jenis Bayaran *</label>
                            <select name="payment_type" required class="w-full border border-slate-200 rounded-lg p-2 bg-slate-50">
                                <option value="deposit">Deposit</option>
                                <option value="progress">Kemajuan / Progress</option>
                                <option value="final_balance">Baki Penuh / Final</option>
                            </select>
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Kaedah Bayaran *</label>
                            <select name="payment_method" required class="w-full border border-slate-200 rounded-lg p-2 bg-slate-50">
                                <option value="Online Transfer">Online Transfer</option>
                                <option value="Cash">Tunai (Cash)</option>
                                <option value="Cheque">Cek</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">No. Rujukan Transaksi / Resit Bank *</label>
                        <input type="text" name="receipt_ref_no" required placeholder="cth: Ref No. 9823719" class="w-full border border-slate-200 rounded-lg p-2 font-mono">
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Tarikh Bayaran *</label>
                        <input type="date" name="payment_date" value="<?= date('Y-m-d'); ?>" required class="w-full border border-slate-200 rounded-lg p-2">
                    </div>

                    <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-500 text-white font-bold py-2.5 rounded-lg shadow transition">
                        💾 Simpan Rekod Bayaran
                    </button>
                </form>
            <?php else: ?>
                <div class="bg-emerald-50 text-emerald-800 p-3 rounded-lg text-center font-bold border border-emerald-200">
                    🎉 Invois ini telah dijelaskan sepenuhnya!
                </div>
            <?php endif; ?>

            <?php if ($paymentHistory && $paymentHistory->num_rows > 0): ?>
                <div class="border-t pt-3 space-y-2">
                    <h3 class="font-bold text-slate-400 uppercase text-[10px]">📋 Sejarah Pembayaran</h3>
                    <?php while($p = $paymentHistory->fetch_assoc()): ?>
                        <div class="p-2 bg-slate-50 rounded border border-slate-200 flex justify-between items-center">
                            <div>
                                <div class="font-bold text-slate-800">RM <?= number_format($p['amount_paid'], 2); ?> <span class="font-normal text-slate-400 text-[10px]">(<?= htmlspecialchars($p['payment_type']); ?>)</span></div>
                                <div class="text-[10px] text-slate-400"><?= date('d/m/Y', strtotime($p['payment_date'])); ?> • <?= htmlspecialchars($p['payment_method']); ?></div>
                            </div>
                            <?php if(!empty($p['receipt_ref_no'])): ?>
                                <span class="font-mono bg-indigo-50 text-indigo-700 px-1.5 py-0.5 rounded border border-indigo-100 text-[10px]">
                                    <?= htmlspecialchars($p['receipt_ref_no']); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php endif; ?>

        </div>
    </main>
</body>
</html>