<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/db.php';

// 1. Semak kebenaran akses / sesi login
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// 2. Tangkap ID dari URL (?id=X atau ?payroll_id=X)
$payroll_id = (int)($_GET['id'] ?? $_GET['payroll_id'] ?? 0);

if ($payroll_id <= 0) {
    die("<div style='padding:20px; color:red; font-family:sans-serif;'><b>Ralat:</b> ID Rekod Gaji tidak sah! <a href='payroll.php'>Kembali ke Pengurusan Gaji</a></div>");
}

// 3. Ambil data payroll & gabungkan bersama maklumat crew
$stmt = $conn->prepare("
    SELECT 
        p.*, 
        c.id AS crew_ref_id,
        COALESCE(c.name, 'Krew Tanpa Nama') AS crew_display_name,
        c.ic_number AS crew_ic,
        c.employment_type AS crew_type, 
        c.epf_number, 
        c.socso_number, 
        c.bank_name, 
        c.bank_account_number
    FROM payroll p
    LEFT JOIN crew c ON p.crew_id = c.id
    WHERE p.id = ?
");

if (!$stmt) {
    die("<div style='padding:20px; color:red; font-family:sans-serif;'><b>Ralat Database Query:</b> " . htmlspecialchars($conn->error) . "</div>");
}

$stmt->bind_param("i", $payroll_id);
$stmt->execute();
$payroll = $stmt->get_result()->fetch_assoc();

if (!$payroll) {
    die("<div style='padding:20px; color:red; font-family:sans-serif;'><b>Rekod Tidak Dijumpai:</b> Gaji ID #{$payroll_id} tiada dalam pangkalan data. <a href='payroll.php'>Kembali</a></div>");
}

// 4. Pengesahan jenis pekerja & format dokumen
$emp_type    = !empty($payroll['crew_type']) ? $payroll['crew_type'] : 'freelance';
$is_fulltime = ($emp_type === 'fulltime');
$doc_title   = $is_fulltime ? "SLIP GAJI RASMI / PAYSLIP" : "VAUCER BAYARAN / PAYMENT VOUCHER";

// 5. Kiraan kasar & potongan
$gross_pay = (float)($payroll['basic_salary'] ?? 0) + (float)($payroll['allowance'] ?? 0) + (float)($payroll['claims'] ?? 0);
$total_deductions = (float)($payroll['epf_employee'] ?? 0) + (float)($payroll['socso_employee'] ?? 0) + (float)($payroll['eis_employee'] ?? 0);
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
<body class="bg-slate-100 p-4 md:p-8 text-slate-800 text-xs font-sans">

    <div class="max-w-2xl mx-auto bg-white p-6 md:p-8 rounded-xl shadow-sm border border-slate-200 print-card">
        
        <!-- Navigasi Cetak -->
        <div class="no-print flex justify-between items-center mb-6 pb-4 border-b">
            <a href="payroll.php" class="text-xs text-indigo-600 hover:underline flex items-center gap-1 font-bold">
                ← Kembali ke Pengurusan Gaji
            </a>
            <button onclick="window.print()" class="bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold px-4 py-2 rounded-lg shadow transition">
                🖨️ Cetak / Simpan PDF
            </button>
        </div>

        <!-- Tajuk Utama -->
        <div class="text-center mb-6">
            <h1 class="text-base font-bold uppercase tracking-wider text-slate-900"><?= $doc_title; ?></h1>
            <p class="text-[11px] text-slate-500 mt-1">
                Bulan Penyata: <span class="font-bold text-slate-800"><?= htmlspecialchars($payroll['month_year'] ?? date('Y-m')); ?></span> | 
                Tarikh Bayaran: <span class="font-bold text-slate-800"><?= !empty($payroll['payment_date']) ? date('d/m/Y', strtotime($payroll['payment_date'])) : date('d/m/Y'); ?></span>
            </p>
        </div>

        <!-- Butiran Pekerja -->
        <div class="grid grid-cols-2 gap-4 text-xs mb-6 p-4 bg-slate-50 rounded-lg border border-slate-200">
            <div>
                <p class="text-slate-400 text-[10px] uppercase font-bold">Nama Pekerja / Krew:</p>
                <p class="font-bold text-slate-900 text-sm"><?= htmlspecialchars($payroll['crew_display_name']); ?></p>
                <p class="text-slate-400 text-[10px] uppercase font-bold mt-2">No. IC / KP:</p>
                <p class="font-mono text-slate-700"><?= htmlspecialchars($payroll['crew_ic'] ?? '-'); ?></p>
            </div>
            <div>
                <p class="text-slate-400 text-[10px] uppercase font-bold">Status Pekerjaan:</p>
                <p class="font-bold uppercase <?= $is_fulltime ? 'text-indigo-600' : 'text-amber-600'; ?>">
                    <?= $is_fulltime ? 'Tetap (Full-Time)' : 'Sambilan (Part-Time / Freelance)'; ?>
                </p>
                <p class="text-slate-400 text-[10px] uppercase font-bold mt-2">Bank & No. Akaun:</p>
                <p class="font-mono font-bold text-slate-800">
                    <?= htmlspecialchars($payroll['bank_name'] ?? '-'); ?> <?= !empty($payroll['bank_account_number']) ? '(' . htmlspecialchars($payroll['bank_account_number']) . ')' : ''; ?>
                </p>
            </div>
        </div>

        <!-- Perincian Pendapatan & Potongan -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-xs mb-6">
            <div class="border border-slate-200 rounded-lg p-4 bg-white space-y-2">
                <h3 class="font-bold text-slate-800 uppercase border-b pb-2 mb-2">Pendapatan</h3>
                <div class="flex justify-between">
                    <span class="text-slate-600">Gaji Asas / Upah:</span>
                    <span class="font-mono font-bold">RM <?= number_format((float)($payroll['basic_salary'] ?? 0), 2); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-600">Elaun:</span>
                    <span class="font-mono font-bold">RM <?= number_format((float)($payroll['allowance'] ?? 0), 2); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-600">Tuntutan (Claims):</span>
                    <span class="font-mono font-bold">RM <?= number_format((float)($payroll['claims'] ?? 0), 2); ?></span>
                </div>
                <div class="flex justify-between border-t pt-2 font-bold text-slate-900">
                    <span>Jumlah Kasar:</span>
                    <span class="font-mono text-indigo-600">RM <?= number_format($gross_pay, 2); ?></span>
                </div>
            </div>

            <div class="border border-slate-200 rounded-lg p-4 bg-white space-y-2">
                <h3 class="font-bold text-slate-800 uppercase border-b pb-2 mb-2">Potongan Wajib</h3>
                <?php if ($is_fulltime): ?>
                    <div class="flex justify-between text-rose-600">
                        <span>KWSP Pekerja:</span>
                        <span class="font-mono">- RM <?= number_format((float)($payroll['epf_employee'] ?? 0), 2); ?></span>
                    </div>
                    <div class="flex justify-between text-rose-600">
                        <span>SOCSO Pekerja:</span>
                        <span class="font-mono">- RM <?= number_format((float)($payroll['socso_employee'] ?? 0), 2); ?></span>
                    </div>
                    <div class="flex justify-between text-rose-600">
                        <span>SIP / EIS Pekerja:</span>
                        <span class="font-mono">- RM <?= number_format((float)($payroll['eis_employee'] ?? 0), 2); ?></span>
                    </div>
                    <div class="flex justify-between border-t pt-2 font-bold text-rose-700">
                        <span>Jumlah Potongan:</span>
                        <span class="font-mono">- RM <?= number_format($total_deductions, 2); ?></span>
                    </div>
                <?php else: ?>
                    <div class="h-20 flex items-center justify-center">
                        <p class="text-slate-400 italic text-center">Tiada potongan caruman wajib bagi pekerja Sambilan/Freelance.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Jumlah Bayaran Bersih -->
        <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-4 text-center mb-8">
            <p class="text-[10px] text-emerald-800 uppercase font-bold tracking-wider">JUMLAH BERSIH DIBAYAR (NET PAY)</p>
            <p class="text-xl font-bold font-mono text-emerald-600 mt-0.5">RM <?= number_format((float)($payroll['net_salary'] ?? 0), 2); ?></p>
        </div>

        <!-- Ruangan Tandatangan -->
        <div class="grid grid-cols-2 gap-8 text-xs text-center mt-12 pt-8 border-t border-slate-200">
            <div>
                <p class="border-b pb-8 border-dashed border-slate-300"></p>
                <p class="mt-2 font-bold text-slate-800">Disediakan Oleh</p>
                <p class="text-slate-400 text-[10px]">Pengurusan / HR Studio</p>
            </div>
            <div>
                <p class="border-b pb-8 border-dashed border-slate-300"></p>
                <p class="mt-2 font-bold text-slate-800">Diterima Oleh</p>
                <p class="text-slate-400 text-[10px]"><?= htmlspecialchars($payroll['crew_display_name']); ?></p>
            </div>
        </div>

    </div>

</body>
</html>