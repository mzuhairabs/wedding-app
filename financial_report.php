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

$selected_year = (int)($_GET['year'] ?? date('Y'));

// 1. Total Pendapatan dari Payments
$sales_query = $conn->query("
    SELECT SUM(amount_paid) AS total_revenue 
    FROM payments 
    WHERE YEAR(payment_date) = '$selected_year'
");
$total_revenue = $sales_query ? (float)($sales_query->fetch_assoc()['total_revenue'] ?? 0) : 0;

// 2. Total Perbelanjaan Operasi dari Expenses
$expense_query = $conn->query("
    SELECT SUM(amount) AS total_expenses 
    FROM expenses 
    WHERE YEAR(expense_date) = '$selected_year'
");
$total_expenses = $expense_query ? (float)($expense_query->fetch_assoc()['total_expenses'] ?? 0) : 0;

// 3. Total Kos Gaji & Caruman Majikan
$payroll_query = $conn->query("
    SELECT 
        SUM(basic_salary + allowance + claims) AS total_gross_salary,
        SUM(epf_employer + socso_employer + eis_employer) AS total_employer_cost
    FROM payroll 
    WHERE LEFT(month_year, 4) = '$selected_year'
");

$total_gross = 0;
$total_employer = 0;
if ($payroll_query) {
    $p_data = $payroll_query->fetch_assoc();
    $total_gross = (float)($p_data['total_gross_salary'] ?? 0);
    $total_employer = (float)($p_data['total_employer_cost'] ?? 0);
}

$total_payroll_cost = $total_gross + $total_employer;
$net_profit = $total_revenue - ($total_expenses + $total_payroll_cost);
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Penyata Untung Rugi LHDN <?= $selected_year; ?> - Studio Manager</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white; padding: 0; }
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 text-xs font-sans min-h-screen">

    <header class="bg-slate-900 text-white sticky top-0 z-30 shadow-md no-print">
        <div class="max-w-7xl mx-auto px-4 py-3.5 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div class="flex items-center space-x-3">
                <span class="text-xl">📊</span>
                <div>
                    <h1 class="text-sm font-bold">Studio Manager</h1>
                    <p class="text-[11px] text-slate-400">Penyata Untung Rugi & Audit Cukai LHDN</p>
                </div>
            </div>

            <nav class="flex flex-wrap gap-1.5 text-xs font-medium">
                <a href="index.php" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-md border border-slate-700">← Dashboard</a>
                <a href="booking.php" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-md shadow-sm">+ Booking</a>
                <a href="expenses.php" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-md border border-slate-700">+ Perbelanjaan</a>
                <a href="packages.php" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-md border border-slate-700">Pakej</a>
                <a href="crew.php" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-md border border-slate-700">Crew</a>
                <a href="payroll.php" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-md border border-slate-700">Gaji</a>
                <a href="financial_report.php" class="px-3 py-1.5 bg-slate-700 text-white rounded-md border border-slate-600">Penyata LHDN</a>
                <a href="logout.php" class="px-3 py-1.5 bg-rose-950/40 text-rose-300 hover:bg-rose-900/50 rounded-md border border-rose-800/40 ml-auto">Logout</a>
            </nav>
        </div>
    </header>

    <main class="max-w-4xl mx-auto px-4 py-8 space-y-6">
        
        <div class="flex justify-between items-center bg-white p-4 rounded-xl border border-slate-200/80 shadow-sm no-print">
            <div>
                <h2 class="text-sm font-bold text-slate-900">📊 Penyata Untung Rugi Tahunan</h2>
                <p class="text-slate-500">Kiraan hasil jualan, kos operasi, dan kos penggajian mengikut piawaian LHDN.</p>
            </div>
            <div class="flex items-center gap-2">
                <form method="GET" class="flex items-center gap-1">
                    <select name="year" onchange="this.form.submit()" class="p-1.5 border border-slate-200 rounded-lg text-xs font-bold bg-slate-50">
                        <?php for($y = date('Y'); $y >= 2024; $y--): ?>
                            <option value="<?= $y; ?>" <?= $y == $selected_year ? 'selected' : ''; ?>><?= $y; ?></option>
                        <?php endfor; ?>
                    </select>
                </form>
                <button onclick="window.print()" class="bg-indigo-600 hover:bg-indigo-500 text-white font-bold px-3 py-1.5 rounded-lg shadow transition">🖨️ Cetak PDF</button>
            </div>
        </div>

        <div class="bg-white p-8 rounded-xl border border-slate-200/80 shadow-sm space-y-6">
            <div class="text-center border-b pb-4">
                <h2 class="text-base font-bold uppercase text-slate-900">STUDIO PHOTOGRAPHY & VIDEO</h2>
                <h3 class="text-xs font-bold text-slate-500">PENYATA UNTUNG RUGI BAGI TAHUN BERAKHIR 31 DISEMBER <?= $selected_year; ?></h3>
            </div>

            <table class="w-full text-xs">
                <tr class="bg-slate-50 font-bold border-b border-slate-200">
                    <td class="p-2.5">HASIL & PENDAPATAN (REVENUE)</td>
                    <td class="p-2.5 text-right font-mono">RM</td>
                </tr>
                <tr>
                    <td class="p-2.5 pl-6 text-slate-700">Hasil Jualan Perkhidmatan (Kutipan Invois)</td>
                    <td class="p-2.5 text-right font-mono font-bold text-slate-900"><?= number_format($total_revenue, 2); ?></td>
                </tr>

                <tr class="bg-slate-50 font-bold border-b border-slate-200">
                    <td class="p-2.5 pt-4">BELANJA OPERASI & GAJI (EXPENSES)</td>
                    <td class="p-2.5 pt-4 text-right font-mono">RM</td>
                </tr>
                
                <tr>
                    <td class="p-2.5 pl-6 text-slate-700">Gaji Pekerja & Elaun</td>
                    <td class="p-2.5 text-right font-mono"><?= number_format($total_gross, 2); ?></td>
                </tr>
                <tr>
                    <td class="p-2.5 pl-6 text-slate-700">Caruman Majikan (KWSP/SOCSO/EIS)</td>
                    <td class="p-2.5 text-right font-mono"><?= number_format($total_employer, 2); ?></td>
                </tr>
                <tr>
                    <td class="p-2.5 pl-6 text-slate-700">Belanja Operasi Studio</td>
                    <td class="p-2.5 text-right font-mono"><?= number_format($total_expenses, 2); ?></td>
                </tr>

                <tr class="border-t font-bold text-rose-600">
                    <td class="p-2.5 pl-6">JUMLAH KOS & BELANJA</td>
                    <td class="p-2.5 text-right font-mono">(RM <?= number_format($total_expenses + $total_payroll_cost, 2); ?>)</td>
                </tr>

                <tr class="border-t-2 border-b-2 border-slate-900 font-bold text-sm <?= $net_profit >= 0 ? 'text-emerald-600' : 'text-rose-600'; ?>">
                    <td class="p-3">UNTUNG BERSIH SEBELUM CUKAI (NET PROFIT)</td>
                    <td class="p-3 text-right font-mono">RM <?= number_format($net_profit, 2); ?></td>
                </tr>
            </table>

            <div class="mt-8 pt-4 border-t text-[10px] text-slate-400 text-center">
                Laporan Kewangan ini dijana secara automatik mengikut standard perakaunan perniagaan.
            </div>
        </div>

    </main>

</body>
</html>