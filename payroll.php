<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/db.php';
require_once 'config/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$message = '';

// Proses Pengiraan & Pemprosesan Gaji
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['calculate_payroll'])) {
    $crew_id         = (int)$_POST['crew_id'];
    $month_year      = $_POST['month_year'];
    $employment_type = $_POST['employment_type'] ?? 'freelance';
    $basic_salary    = (float)$_POST['basic_salary'];
    $allowance       = (float)$_POST['allowance'];
    $claims          = (float)$_POST['claims'];

    // Pengiraan Potongan KWSP, SOCSO, EIS jika Full-Time
    if ($employment_type === 'fulltime') {
        $epf_employee   = round($basic_salary * 0.11, 2);
        $epf_employer   = round($basic_salary * 0.13, 2);
        $socso_employee = round($basic_salary * 0.005, 2);
        $socso_employer = round($basic_salary * 0.0175, 2);
        $eis_employee   = round($basic_salary * 0.002, 2);
        $eis_employer   = round($basic_salary * 0.002, 2);
    } else {
        // Freelance / Part-time: Tiada Potongan Wajib
        $epf_employee   = $epf_employer   = 0.00;
        $socso_employee = $socso_employer = 0.00;
        $eis_employee   = $eis_employer   = 0.00;
    }

    $total_deductions = $epf_employee + $socso_employee + $eis_employee;
    $net_salary       = ($basic_salary + $allowance + $claims) - $total_deductions;
    $payment_date     = date('Y-m-d');

    $stmt = $conn->prepare("
        INSERT INTO payroll (crew_id, month_year, basic_salary, allowance, claims, epf_employee, epf_employer, socso_employee, socso_employer, eis_employee, eis_employer, net_salary, payment_date, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'paid')
    ");
    
    if ($stmt) {
        $stmt->bind_param("isdddddddddds", 
            $crew_id, $month_year, $basic_salary, $allowance, $claims, $epf_employee, $epf_employer, $socso_employee, $socso_employer, $eis_employee, $eis_employer, $net_salary, $payment_date
        );

        if ($stmt->execute()) {
            $message = '<div class="p-3 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg text-xs font-bold flex items-center gap-2">✅ Rekod bayaran/gaji krew berjaya disimpan!</div>';
        } else {
            $message = '<div class="p-3 bg-rose-50 border border-rose-200 text-rose-800 rounded-lg text-xs font-bold flex items-center gap-2">❌ Ralat Simpan: ' . htmlspecialchars($conn->error) . '</div>';
        }
    }
}

// 1. Ambil Senarai Crew
$crew_list = $conn->query("SELECT * FROM crew WHERE (is_deleted = 0 OR is_deleted IS NULL) ORDER BY name ASC");

// 2. Ambil Rekod Sejarah Gaji
$payroll_history = $conn->query("
    SELECT p.*, c.name AS crew_person_name, c.employment_type 
    FROM payroll p 
    LEFT JOIN crew c ON p.crew_id = c.id 
    ORDER BY p.id DESC
");
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengurusan Gaji - Studio Manager</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 text-xs min-h-screen">

    <!-- Header Unified Navigation Bar -->
    <header class="bg-slate-900 text-white sticky top-0 z-30 shadow-md">
        <div class="max-w-7xl mx-auto px-4 py-3.5 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div class="flex items-center space-x-3">
                <div class="w-8 h-8 bg-indigo-600 rounded-lg flex items-center justify-center font-bold text-sm text-white shadow-inner">
                    💵
                </div>
                <div>
                    <h1 class="text-sm font-bold text-white">Studio Manager</h1>
                    <p class="text-[11px] text-slate-400">Pengurusan Bayaran Krew & Gaji</p>
                </div>
            </div>

            <nav class="flex flex-wrap gap-1.5 text-xs font-medium">
                <a href="index.php" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-md transition border border-slate-700">← Dashboard</a>
                <a href="booking.php" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-md transition shadow-sm">+ Booking</a>
                <a href="expenses.php" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-md transition border border-slate-700">+ Perbelanjaan</a>
                <a href="packages.php" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-md transition border border-slate-700">Pakej</a>
                <a href="crew.php" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-md transition border border-slate-700">Crew</a>
                <a href="payroll.php" class="px-3 py-1.5 bg-slate-700 text-white rounded-md transition border border-slate-600">Gaji</a>
                <a href="financial_report.php" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-md transition border border-slate-700">Penyata LHDN</a>
                <a href="logout.php" class="px-3 py-1.5 bg-rose-950/40 text-rose-300 hover:bg-rose-900/50 rounded-md transition border border-rose-800/40 ml-auto md:ml-2">Logout</a>
            </nav>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 py-8 space-y-6">

        <?= $message; ?>

        <!-- Borang Rekod Bayaran -->
        <div class="bg-white rounded-xl border border-slate-200/80 shadow-sm p-5 space-y-4">
            <div class="border-b border-slate-100 pb-3">
                <h2 class="text-sm font-bold text-slate-900">💵 Process Bayaran & Slip Gaji Krew</h2>
                <p class="text-[11px] text-slate-500">Bayaran elaun freelance dan kiraan potongan caruman wajib (KWSP/SOCSO/EIS) bagi pekerja tetap.</p>
            </div>

            <form method="POST" action="payroll.php" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Pilih Krew / Pekerja *</label>
                    <select name="crew_id" required class="w-full text-xs px-3 py-2 border border-slate-200 rounded-lg bg-slate-50 font-medium text-slate-800 focus:outline-none focus:border-indigo-500 transition">
                        <option value="">-- Pilih Krew --</option>
                        <?php if($crew_list && $crew_list->num_rows > 0): ?>
                            <?php while($c = $crew_list->fetch_assoc()): 
                                $type_label = ($c['employment_type'] ?? '') === 'fulltime' ? '[Full-Time]' : '[Freelance]';
                            ?>
                                <option value="<?= $c['id']; ?>">
                                    <?= htmlspecialchars($c['name'] ?? 'Crew #' . $c['id']); ?> <?= $type_label; ?>
                                </option>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Jenis Perkhidmatan *</label>
                    <select name="employment_type" required class="w-full text-xs px-3 py-2 border border-slate-200 rounded-lg bg-slate-50 font-medium text-slate-800 focus:outline-none focus:border-indigo-500 transition">
                        <option value="freelance">Freelance / Part-Time (Tiada Potongan KWSP)</option>
                        <option value="fulltime">Full-Time (Kira Potongan KWSP/SOCSO/EIS)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Bulan & Tahun Penyata *</label>
                    <input type="month" name="month_year" value="<?= date('Y-m'); ?>" required class="w-full text-xs px-3 py-2 border border-slate-200 rounded-lg bg-white text-slate-800 focus:outline-none focus:border-indigo-500 transition font-medium">
                </div>

                <div>
                    <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Gaji Asas / Upah Job (RM) *</label>
                    <input type="number" step="0.01" name="basic_salary" placeholder="0.00" required class="w-full text-xs px-3 py-2 border border-slate-200 rounded-lg bg-white text-slate-800 focus:outline-none focus:border-indigo-500 transition font-mono font-bold">
                </div>

                <div>
                    <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Elaun Tambahan (RM)</label>
                    <input type="number" step="0.01" name="allowance" value="0.00" class="w-full text-xs px-3 py-2 border border-slate-200 rounded-lg bg-white text-slate-800 focus:outline-none focus:border-indigo-500 transition font-mono">
                </div>

                <div>
                    <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Tuntutan / Claims (RM)</label>
                    <input type="number" step="0.01" name="claims" value="0.00" class="w-full text-xs px-3 py-2 border border-slate-200 rounded-lg bg-white text-slate-800 focus:outline-none focus:border-indigo-500 transition font-mono">
                </div>

                <div class="md:col-span-3 flex justify-end mt-2">
                    <button type="submit" name="calculate_payroll" class="bg-indigo-600 hover:bg-indigo-500 text-white font-bold px-6 py-2.5 rounded-lg shadow transition text-xs">
                        💾 Simpan & Rekod Bayaran
                    </button>
                </div>
            </form>
        </div>

        <!-- Jadual Sejarah Pembayaran -->
        <div class="bg-white rounded-xl border border-slate-200/80 shadow-sm overflow-hidden">
            <div class="p-4 border-b border-slate-100 bg-slate-50/50">
                <h3 class="text-sm font-bold text-slate-900">📋 Sejarah Pembayaran Gaji / Upah</h3>
                <p class="text-[11px] text-slate-500">Rekod bayaran yang diproses dan cetakan baucar/slip gaji.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left text-slate-600">
                    <thead class="text-[10px] font-bold uppercase text-slate-400 bg-slate-50 border-b border-slate-100">
                        <tr>
                            <th class="px-4 py-3">Bulan</th>
                            <th class="px-4 py-3">Pekerja / Krew</th>
                            <th class="px-4 py-3">Gaji Kasar (RM)</th>
                            <th class="px-4 py-3">Potongan Wajib</th>
                            <th class="px-4 py-3 font-bold text-emerald-700">Bayaran Bersih (RM)</th>
                            <th class="px-4 py-3 text-right">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if ($payroll_history && $payroll_history->num_rows > 0): ?>
                            <?php while($p = $payroll_history->fetch_assoc()): 
                                $gross = (float)$p['basic_salary'] + (float)$p['allowance'] + (float)$p['claims'];
                                $deductions = (float)$p['epf_employee'] + (float)$p['socso_employee'] + (float)$p['eis_employee'];
                                $cname = $p['crew_person_name'] ?? 'Krew #' . $p['crew_id'];
                            ?>
                                <tr class="hover:bg-slate-50/70 transition">
                                    <td class="px-4 py-3 font-bold text-slate-900 font-mono"><?= htmlspecialchars($p['month_year']); ?></td>
                                    <td class="px-4 py-3 font-bold text-slate-800"><?= htmlspecialchars($cname); ?></td>
                                    <td class="px-4 py-3 font-mono font-medium">RM <?= number_format($gross, 2); ?></td>
                                    <td class="px-4 py-3 font-mono text-rose-600 font-medium">
                                        <?= $deductions > 0 ? '- RM ' . number_format($deductions, 2) : '<span class="text-slate-400 text-[10px] font-normal">Tiada (Part-Time)</span>'; ?>
                                    </td>
                                    <td class="px-4 py-3 font-mono font-bold text-emerald-600">RM <?= number_format((float)$p['net_salary'], 2); ?></td>
                                    <td class="px-4 py-3 text-right">
                                        <a href="print_payslip.php?id=<?= $p['id']; ?>" target="_blank" class="px-2.5 py-1 bg-indigo-50 border border-indigo-200 text-indigo-700 font-bold text-[10px] rounded hover:bg-indigo-100 transition inline-block">
                                            📄 Voucher / Slip
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="p-6 text-center text-slate-400">Belum ada rekod gaji diproses.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

</body>
</html>