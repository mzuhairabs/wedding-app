<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/db.php';
require_once 'config/auth.php';

// Semak keberadaan ID Payroll dari URL. Jika tiada, lencongkan semula ke payroll.php
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: payroll.php");
    exit;
}

$payroll_id = (int)$_GET['id'];

// Ambil data rekod gaji & gabungkan maklumat krew
// Ambil data payroll & krew (tanpa lajur c.phone)
$stmt = $conn->prepare("
    SELECT 
        p.*, 
        c.id AS crew_ref_id,
        COALESCE(c.name, 'Krew Tanpa Nama') AS crew_display_name,
        c.employment_type AS crew_type, 
        c.epf_no, 
        c.socso_no, 
        c.bank_name, 
        c.bank_account_no, 
        c.department
    FROM payroll p
    LEFT JOIN crew c ON p.crew_id = c.id
    WHERE p.id = ?
");

if (!$stmt) {
    die("<div style='padding:20px; color:red; font-family:sans-serif;'><b>Ralat DB Query:</b> " . htmlspecialchars($conn->error) . "</div>");
}

$stmt->bind_param("i", $payroll_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("<div style='padding:20px; color:red; font-family:sans-serif;'><b>Rekod Tidak Dijumpai:</b> Rekod gaji ID #" . $payroll_id . " tiada dalam pangkalan data. <a href='payroll.php'>Kembali</a></div>");
}

$payroll = $result->fetch_assoc();

// Tentukan Status Pekerja & Tajuk Dokumen
$emp_type    = !empty($payroll['crew_type']) ? $payroll['crew_type'] : 'freelance';
$is_fulltime = ($emp_type === 'fulltime');
$doc_title   = $is_fulltime ? "SLIP GAJI / PAYSLIP" : "VAUCER BAYARAN / PAYMENT VOUCHER";

// Pengiraan Ringkasan
$gross_pay = (float)$payroll['basic_salary'] + (float)$payroll['allowance'] + (float)$payroll['claims'];
$total_deductions = (float)$payroll['epf_employee'] + (float)$payroll['socso_employee'] + (float)$payroll['eis_employee'];
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $doc_title; ?> - <?= htmlspecialchars($payroll['crew_display_name']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background-color: white !important; padding: 0 !important; }
            .print-card { border: none !important; box-shadow: none !important; }
        }
    </style>
</head>
<body class="bg-gray-100 p-4 md:p-8 text-gray-800">

    <div class="max-w-2xl mx-auto bg-white p-6 md:p-8 rounded-xl shadow-md border border-gray-200 print-card">
        
        <!-- Bar Navigasi Tindakan -->
        <div class="no-print flex justify-between items-center mb-6 pb-4 border-b">
            <a href="payroll.php" class="text-xs text-blue-600 hover:underline flex items-center gap-1 font-semibold">
                ← Kembali ke Pengurusan Gaji
            </a>
            <button onclick="window.print()" class="bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold px-4 py-2 rounded-lg shadow transition">
                🖨️ Cetak / Simpan PDF
            </button>
        </div>

        <!-- Kepala Penyata -->
        <div class="text-center mb-6">
            <h1 class="text-xl font-extrabold uppercase tracking-wider text-gray-800"><?= $doc_title; ?></h1>
            <p class="text-xs text-gray-500 mt-1">
                Bulan Penyata: <span class="font-bold text-gray-700"><?= htmlspecialchars($payroll['month_year']); ?></span> | 
                Tarikh Bayaran: <span class="font-bold text-gray-700"><?= htmlspecialchars($payroll['payment_date']); ?></span>
            </p>
        </div>

        <!-- Maklumat Krew / Pekerja -->
        <div class="grid grid-cols-2 gap-4 text-xs mb-6 p-4 bg-gray-50 rounded-lg border border-gray-200">
            <div>
                <p class="text-gray-500">Nama Pekerja / Krew:</p>
                <p class="font-bold text-gray-800 text-sm"><?= htmlspecialchars($payroll['crew_display_name']); ?></p>
                <p class="text-gray-500 mt-2">Status Pekerjaan:</p>
                <p class="font-bold uppercase <?= $is_fulltime ? 'text-blue-600' : 'text-amber-600'; ?>">
                    <?= $is_fulltime ? 'Tetap (Full-Time)' : 'Sambilan (Freelance)'; ?>
                </p>
            </div>
            <div>
                <p class="text-gray-500">Bank & No. Akaun:</p>
                <p class="font-bold text-gray-800">
                    <?= htmlspecialchars($payroll['bank_name'] ?: '-'); ?> 
                    <?= $payroll['bank_account_no'] ? '(' . htmlspecialchars($payroll['bank_account_no']) . ')' : ''; ?>
                </p>
                <?php if ($is_fulltime): ?>
                    <p class="text-gray-500 mt-2">No. EPF / SOCSO:</p>
                    <p class="font-semibold text-gray-700">
                        <?= htmlspecialchars($payroll['epf_no'] ?: '-'); ?> / <?= htmlspecialchars($payroll['socso_no'] ?: '-'); ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Perincian Pendapatan & Potongan -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-xs mb-6">
            <!-- Komponen Pendapatan -->
            <div class="border rounded-lg p-4 bg-white">
                <h3 class="font-bold text-gray-700 uppercase border-b pb-2 mb-3">Pendapatan</h3>
                <div class="space-y-2">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Gaji Asas / Upah:</span>
                        <span class="font-medium">RM <?= number_format((float)$payroll['basic_salary'], 2); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Elaun:</span>
                        <span class="font-medium">RM <?= number_format((float)$payroll['allowance'], 2); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Tuntutan (Claims):</span>
                        <span class="font-medium">RM <?= number_format((float)$payroll['claims'], 2); ?></span>
                    </div>
                    <div class="flex justify-between border-t pt-2 font-bold text-gray-900">
                        <span>Jumlah Kasar:</span>
                        <span>RM <?= number_format($gross_pay, 2); ?></span>
                    </div>
                </div>
            </div>

            <!-- Komponen Potongan -->
            <div class="border rounded-lg p-4 bg-white">
                <h3 class="font-bold text-gray-700 uppercase border-b pb-2 mb-3">Potongan</h3>
                <?php if ($is_fulltime): ?>
                    <div class="space-y-2">
                        <div class="flex justify-between text-red-600">
                            <span>KWSP Pekerja (11%):</span>
                            <span>- RM <?= number_format((float)$payroll['epf_employee'], 2); ?></span>
                        </div>
                        <div class="flex justify-between text-red-600">
                            <span>SOCSO Pekerja:</span>
                            <span>- RM <?= number_format((float)$payroll['socso_employee'], 2); ?></span>
                        </div>
                        <div class="flex justify-between text-red-600">
                            <span>SIP / EIS Pekerja:</span>
                            <span>- RM <?= number_format((float)$payroll['eis_employee'], 2); ?></span>
                        </div>
                        <div class="flex justify-between border-t pt-2 font-bold text-red-700">
                            <span>Jumlah Potongan:</span>
                            <span>- RM <?= number_format($total_deductions, 2); ?></span>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="h-24 flex items-center justify-center">
                        <p class="text-gray-400 italic text-center">Tiada potongan KWSP/SOCSO bagi pekerja Freelance/Part-time.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Jumlah Bayaran Bersih -->
        <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-4 text-center mb-8">
            <p class="text-xs text-emerald-800 uppercase font-bold tracking-wider">JUMLAH BERSIH DIBAYAR (NET PAY)</p>
            <p class="text-2xl font-extrabold text-emerald-600 mt-1">RM <?= number_format((float)$payroll['net_salary'], 2); ?></p>
        </div>

        <!-- Ruangan Tandatangan -->
        <div class="grid grid-cols-2 gap-8 text-xs text-center mt-12 pt-8 border-t">
            <div>
                <p class="border-b pb-10 border-dashed border-gray-400"></p>
                <p class="mt-2 font-bold text-gray-700">Disediakan Oleh</p>
                <p class="text-gray-400 text-[10px]">Pengurusan / HR</p>
            </div>
            <div>
                <p class="border-b pb-10 border-dashed border-gray-400"></p>
                <p class="mt-2 font-bold text-gray-700">Diterima Oleh</p>
                <p class="text-gray-400 text-[10px]"><?= htmlspecialchars($payroll['crew_display_name']); ?></p>
            </div>
        </div>

    </div>

</body>
</html>