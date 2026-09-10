<?php
// Aktifkan pameran ralat untuk debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/db.php';   // <--- PASTI TAMBAH BARIS INI
require_once 'config/auth.php'; // <--- PASTI TAMBAH BARIS INI

$invoice_id = $_GET['id'] ?? 0;

// Ambil data penuh Invois, Pelanggan, Acara & Pakej
$invQuery = $conn->query("
    SELECT invoices.*, 
           clients.client_name, clients.client_type, clients.ic_or_roc, clients.phone_number, clients.email,
           events.event_title, events.event_date, events.event_time, events.venue_address,
           packages.package_name, packages.description AS package_desc
    FROM invoices 
    JOIN clients ON invoices.client_id = clients.id 
    JOIN events ON invoices.event_id = events.id 
    LEFT JOIN packages ON events.package_id = packages.id
    WHERE invoices.id = $invoice_id
");
$inv = $invQuery->fetch_assoc();

if (!$inv) {
    die("Invois tidak dijumpai!");
}

// Ambil senarai rekod bayaran bagi invois ini
$paymentsQuery = $conn->query("SELECT * FROM payments WHERE invoice_id = $invoice_id ORDER BY id ASC");
$totalPaid = 0;
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invois - <?= $inv['invoice_number']; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background-color: white; padding: 0; }
            .shadow-lg { box-shadow: none !important; }
        }
    </style>
</head>
<body class="bg-gray-100 p-4 md:p-8 min-h-screen text-gray-800">

    <!-- Butang Tindakan (Tidak akan dicetak) -->
    <div class="max-w-3xl mx-auto mb-4 flex justify-between items-center no-print">
        <a href="index.php" class="text-sm text-gray-600 hover:underline">← Kembali ke Dashboard</a>
        <button onclick="window.print()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-4 py-2 rounded-lg text-sm shadow">
            🖨️ Cetak / Simpan ke PDF
        </button>
    </div>

    <!-- Templat Invois -->
    <div class="max-w-3xl mx-auto bg-white rounded-xl shadow-lg p-8 border border-gray-200">
        
        <!-- Tajuk Header -->
        <div class="flex justify-between items-start border-b pb-6">
            <div>
                <h1 class="text-2xl font-black text-gray-900 tracking-wide">INVOIS RASMI</h1>
                <p class="text-sm font-bold text-blue-600 mt-1">STUDIO PHOTOGRAPHY & VIDEO</p>
                <p class="text-xs text-gray-500">No. Pendaftaran Syarikat: 20260100XXXX</p>
            </div>
            <div class="text-right">
                <h2 class="text-lg font-bold text-gray-800"><?= $inv['invoice_number']; ?></h2>
                <p class="text-xs text-gray-500">Tarikh Invois: <?= date('d/m/Y', strtotime($inv['created_at'])); ?></p>
                <p class="text-xs font-semibold mt-1">
                    Status: 
                    <span class="uppercase text-<?= $inv['status'] === 'paid' ? 'green' : ($inv['status'] === 'partially_paid' ? 'yellow' : 'red'); ?>-600">
                        <?= $inv['status'] === 'paid' ? 'LUNAS' : ($inv['status'] === 'partially_paid' ? 'DEPOSIT DIBAYAR' : 'BELUM BAYAR'); ?>
                    </span>
                </p>
            </div>
        </div>

        <!-- Maklumat Pelanggan & Acara -->
        <div class="grid grid-cols-2 gap-6 my-6 text-sm">
            <div>
                <p class="text-xs font-bold text-gray-400 uppercase">Kepada (Pelanggan):</p>
                <p class="font-bold text-gray-900 mt-1"><?= htmlspecialchars($inv['client_name']); ?></p>
                <p class="text-xs text-gray-600">No. IC/ROC: <?= htmlspecialchars($inv['ic_or_roc']); ?></p>
                <p class="text-xs text-gray-600">Tel: <?= htmlspecialchars($inv['phone_number']); ?></p>
                <p class="text-xs text-gray-600"><?= htmlspecialchars($inv['email']); ?></p>
            </div>
            <div>
                <p class="text-xs font-bold text-gray-400 uppercase">Butiran Acara:</p>
                <p class="font-bold text-gray-900 mt-1"><?= htmlspecialchars($inv['event_title']); ?></p>
                <p class="text-xs text-gray-600">Tarikh: <?= date('d/m/Y', strtotime($inv['event_date'])); ?> (Masa: <?= date('h:i A', strtotime($inv['event_time'])); ?>)</p>
                <p class="text-xs text-gray-600">Lokasi: <?= htmlspecialchars($inv['venue_address']); ?></p>
            </div>
        </div>

        <!-- Jadual Perkhidmatan -->
        <table class="w-full text-sm text-left my-6 border-t border-b">
            <thead class="bg-gray-50 text-xs uppercase text-gray-600 border-b">
                <tr>
                    <th class="py-3 px-2">Perkara / Pakej Servis</th>
                    <th class="py-3 px-2 text-right">Jumlah (RM)</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                <tr>
                    <td class="py-4 px-2">
                        <p class="font-bold text-gray-900"><?= htmlspecialchars($inv['package_name'] ?? 'Pakej Servis'); ?></p>
                        <p class="text-xs text-gray-500 mt-1"><?= nl2br(htmlspecialchars($inv['package_desc'] ?? '')); ?></p>
                    </td>
                    <td class="py-4 px-2 text-right font-bold text-gray-900">
                        RM <?= number_format($inv['total_amount'], 2); ?>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- Sejarah Pembayaran -->
        <div class="my-6">
            <p class="text-xs font-bold text-gray-400 uppercase mb-2">Rekod Bayaran Terimandasi:</p>
            <table class="w-full text-xs text-left bg-gray-50 rounded-lg">
                <thead>
                    <tr class="border-b text-gray-500">
                        <th class="p-2">Tarikh</th>
                        <th class="p-2">Kaedah / Ref</th>
                        <th class="p-2">Jenis</th>
                        <th class="p-2 text-right">Amaun (RM)</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <?php if ($paymentsQuery && $paymentsQuery->num_rows > 0): ?>
                        <?php while($pay = $paymentsQuery->fetch_assoc()): ?>
                            <?php $totalPaid += $pay['amount_paid']; ?>
                            <tr>
                                <td class="p-2"><?= date('d/m/Y', strtotime($pay['payment_date'])); ?></td>
                                <td class="p-2"><?= htmlspecialchars($pay['payment_method']); ?> (<?= htmlspecialchars($pay['receipt_ref_no'] ?? '-'); ?>)</td>
                                <td class="p-2 uppercase font-semibold text-gray-600"><?= htmlspecialchars($pay['payment_type']); ?></td>
                                <td class="p-2 text-right font-bold text-green-600">+ RM <?= number_format($pay['amount_paid'], 2); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="p-2 text-center text-gray-400">Belum ada sebarang pembayaran direkodkan.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Kira Baki -->
        <div class="border-t pt-4 text-sm space-y-1">
            <div class="flex justify-between text-gray-600">
                <span>Jumlah Keseluruhan:</span>
                <span class="font-bold text-gray-900">RM <?= number_format($inv['total_amount'], 2); ?></span>
            </div>
            <div class="flex justify-between text-gray-600">
                <span>Jumlah Telah Dibayar:</span>
                <span class="font-bold text-green-600">RM <?= number_format($totalPaid, 2); ?></span>
            </div>
            <div class="flex justify-between text-base font-bold text-gray-900 border-t pt-2 mt-2">
                <span>Baki Tunggakan (Baki Perlu Dibayar):</span>
                <span class="text-red-600">RM <?= number_format($inv['total_amount'] - $totalPaid, 2); ?></span>
            </div>
        </div>

        <!-- Footer Invois -->
        <div class="mt-8 border-t pt-4 text-center text-xs text-gray-400">
            <p>Terima kasih kerana memilih perkhidmatan kami!</p>
            <p class="mt-1">Invois ini dijana secara komputer melalui Studio Manager.</p>
        </div>

    </div>

</body>
</html>