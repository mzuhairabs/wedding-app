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

// Parameter penapis tarikh (Default: Tahun Semasa)
$selected_year = $_GET['year'] ?? date('Y');
$selected_month = $_GET['month'] ?? '';

?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Studio Manager - Modul Laporan Sistem</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen antialiased">

    <!-- Header Navigation -->
    <header class="bg-slate-900 text-white sticky top-0 z-30 shadow-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3.5 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div class="flex items-center space-x-3">
                <div class="w-9 h-9 bg-indigo-600 rounded-lg flex items-center justify-center font-bold text-lg text-white">📊</div>
                <div>
                    <h1 class="text-base font-semibold text-white leading-tight">Pusat Laporan & Analitik Studio</h1>
                    <p class="text-[11px] text-slate-400">Pengurusan Kewangan, Operasi, Jualan & Pematuhan</p>
                </div>
            </div>
            <nav class="flex items-center gap-2 text-xs font-medium">
                <a href="index.php" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-md transition">🏠 Dashboard</a>
                <a href="clients.php" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-md transition">👥 Pelanggan</a>
                <a href="financial_report.php" class="px-3 py-1.5 bg-indigo-600 text-white rounded-md transition font-bold">📊 Laporan</a>
                <a href="logout.php" class="px-3 py-1.5 bg-rose-950/40 text-rose-300 hover:bg-rose-900/50 rounded-md transition border border-rose-800/40">Logout</a>
            </nav>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

        <!-- Bar Penapis Masa -->
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex flex-wrap items-center justify-between gap-4">
            <form method="GET" class="flex flex-wrap items-center gap-3 text-xs">
                <label class="font-bold text-slate-700">Tapis Tempah Laporan:</label>
                <select name="year" class="px-3 py-2 border border-slate-200 rounded-lg bg-white font-medium focus:outline-none focus:border-indigo-500">
                    <?php for($y = date('Y'); $y >= date('Y') - 4; $y--): ?>
                        <option value="<?= $y; ?>" <?= $selected_year == $y ? 'selected' : ''; ?>><?= $y; ?></option>
                    <?php endfor; ?>
                </select>
                <select name="month" class="px-3 py-2 border border-slate-200 rounded-lg bg-white font-medium focus:outline-none focus:border-indigo-500">
                    <option value="">Semua Bulan (Tahunan)</option>
                    <?php for($m = 1; $m <= 12; $m++): ?>
                        <option value="<?= sprintf('%02d', $m); ?>" <?= $selected_month == sprintf('%02d', $m) ? 'selected' : ''; ?>><?= date('F', mktime(0, 0, 0, $m, 1)); ?></option>
                    <?php endfor; ?>
                </select>
                <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold rounded-lg transition">🔍 Tapis Data</button>
            </form>

            <button onclick="window.print()" class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 text-xs font-semibold rounded-lg transition flex items-center gap-1.5">
                🖨️ Cetak / Simpan PDF
            </button>
        </div>

        <!-- 1. LAPORAN KEWANGAAN & TUGASAN -->
        <section class="space-y-4">
            <div class="border-b border-slate-200 pb-2">
                <h2 class="text-sm font-bold uppercase tracking-wider text-indigo-600 flex items-center gap-2">
                    💰 1. Laporan Kewangan & Tugasan
                </h2>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <!-- Keuntungan Per Tugasan (Job Costing) -->
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm hover:border-indigo-300 transition">
                    <div class="flex justify-between items-start mb-2">
                        <h3 class="text-xs font-bold text-slate-900">Keuntungan per Tugasan (Job Costing)</h3>
                        <span class="text-[10px] bg-emerald-50 text-emerald-700 border border-emerald-200 px-2 py-0.5 rounded font-semibold">Tugasan</span>
                    </div>
                    <p class="text-[11px] text-slate-500 mb-4">Mengira untung bersih setiap acara selepas menolak kos langsung (Pengangkutan, Elaun Krew, Kos Edit).</p>
                    <a href="report_job_costing.php?year=<?= $selected_year; ?>&month=<?= $selected_month; ?>" class="inline-block w-full text-center py-2 bg-slate-50 hover:bg-indigo-50 text-indigo-600 hover:text-indigo-700 font-semibold text-xs rounded-lg border border-slate-200 transition">Jana Laporan Job Costing →</a>
                </div>

                <!-- Penyata Pendapatan (P&L) -->
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm hover:border-indigo-300 transition">
                    <div class="flex justify-between items-start mb-2">
                        <h3 class="text-xs font-bold text-slate-900">Penyata Pendapatan (Profit & Loss)</h3>
                        <span class="text-[10px] bg-indigo-50 text-indigo-700 border border-indigo-200 px-2 py-0.5 rounded font-semibold">Bulanan / Tahunan</span>
                    </div>
                    <p class="text-[11px] text-slate-500 mb-4">Memantau aliran pendapatan mengikut segmen (Pakej Perkahwinan, Korporat, atau Product Shoot).</p>
                    <a href="financial_report.php?year=<?= $selected_year; ?>&month=<?= $selected_month; ?>" class="inline-block w-full text-center py-2 bg-slate-50 hover:bg-indigo-50 text-indigo-600 hover:text-indigo-700 font-semibold text-xs rounded-lg border border-slate-200 transition">Jana Penyata P&L →</a>
                </div>

                <!-- Kunci Kira-Kira & Aliran Tunai -->
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm hover:border-indigo-300 transition">
                    <div class="flex justify-between items-start mb-2">
                        <h3 class="text-xs font-bold text-slate-900">Kunci Kira-Kira & Aliran Tunai</h3>
                        <span class="text-[10px] bg-slate-100 text-slate-700 border border-slate-200 px-2 py-0.5 rounded font-semibold">Penyata Imbangan</span>
                    </div>
                    <p class="text-[11px] text-slate-500 mb-4">Menjejaki pergerakan aliran tunai masuk/keluar, baki aset syarikat, liabiliti dan ekuiti pemilik.</p>
                    <a href="report_balance_sheet.php?year=<?= $selected_year; ?>" class="inline-block w-full text-center py-2 bg-slate-50 hover:bg-indigo-50 text-indigo-600 hover:text-indigo-700 font-semibold text-xs rounded-lg border border-slate-200 transition">Lihat Aliran Tunai →</a>
                </div>
            </div>
        </section>

        <!-- 2. LAPORAN OPERASI & PENGURUSAN ASET -->
        <section class="space-y-4">
            <div class="border-b border-slate-200 pb-2">
                <h2 class="text-sm font-bold uppercase tracking-wider text-indigo-600 flex items-center gap-2">
                    📷 2. Laporan Operasi & Pengurusan Aset
                </h2>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <!-- Utiliti & Penyelenggaraan Kamera -->
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm hover:border-indigo-300 transition">
                    <div class="flex justify-between items-start mb-2">
                        <h3 class="text-xs font-bold text-slate-900">Utiliti & Penyelenggaraan Kamera</h3>
                        <span class="text-[10px] bg-amber-50 text-amber-700 border border-amber-200 px-2 py-0.5 rounded font-semibold">Peralatan</span>
                    </div>
                    <p class="text-[11px] text-slate-500 mb-4">Menjejaki shutter count kamera, servis lensa, penggantian lampu studio, dan polisi insurans alat.</p>
                    <a href="report_assets.php" class="inline-block w-full text-center py-2 bg-slate-50 hover:bg-indigo-50 text-indigo-600 hover:text-indigo-700 font-semibold text-xs rounded-lg border border-slate-200 transition">Status Aset & Kamera →</a>
                </div>

                <!-- Status Pengeluaran (Production Pipeline) -->
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm hover:border-indigo-300 transition">
                    <div class="flex justify-between items-start mb-2">
                        <h3 class="text-xs font-bold text-slate-900">Status Pengeluaran (Pipeline)</h3>
                        <span class="text-[10px] bg-blue-50 text-blue-700 border border-blue-200 px-2 py-0.5 rounded font-semibold">Proses Editing</span>
                    </div>
                    <p class="text-[11px] text-slate-500 mb-4">Memantau aliran kerja tugasan dari Pra-Produksi → Penggambaran → Suntingan → Draf → Serahan Akhir.</p>
                    <a href="progress_report.php" class="inline-block w-full text-center py-2 bg-slate-50 hover:bg-indigo-50 text-indigo-600 hover:text-indigo-700 font-semibold text-xs rounded-lg border border-slate-200 transition">Laporan Pipeline →</a>
                </div>

                <!-- Inventori & Produktiviti Pekerja -->
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm hover:border-indigo-300 transition">
                    <div class="flex justify-between items-start mb-2">
                        <h3 class="text-xs font-bold text-slate-900">Inventori & Produktiviti Krew</h3>
                        <span class="text-[10px] bg-purple-50 text-purple-700 border border-purple-200 px-2 py-0.5 rounded font-semibold">Krew & Stok</span>
                    </div>
                    <p class="text-[11px] text-slate-500 mb-4">Mengukur tahap kecekapan, tugasan krew penggambaran, serta baki stok album dan pemacu USB.</p>
                    <a href="report_productivity.php" class="inline-block w-full text-center py-2 bg-slate-50 hover:bg-indigo-50 text-indigo-600 hover:text-indigo-700 font-semibold text-xs rounded-lg border border-slate-200 transition">Prestasi Krew & Stok →</a>
                </div>
            </div>
        </section>

        <!-- 3. LAPORAN JUALAN & PEMASARAN -->
        <section class="space-y-4">
            <div class="border-b border-slate-200 pb-2">
                <h2 class="text-sm font-bold uppercase tracking-wider text-indigo-600 flex items-center gap-2">
                    📈 3. Laporan Jualan & Pemasaran
                </h2>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <!-- Penukaran Tempahan (Booking Conversion) -->
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm hover:border-indigo-300 transition">
                    <div class="flex justify-between items-start mb-2">
                        <h3 class="text-xs font-bold text-slate-900">Penukaran Tempahan (Conversion Rate)</h3>
                        <span class="text-[10px] bg-rose-50 text-rose-700 border border-rose-200 px-2 py-0.5 rounded font-semibold">Leads</span>
                    </div>
                    <p class="text-[11px] text-slate-500 mb-4">Mengukur nisbah pertanyaan (pertanyaan WhatsApp/Web) yang berjaya ditukar menjadi pelanggan berbayar.</p>
                    <a href="report_conversion.php?year=<?= $selected_year; ?>" class="inline-block w-full text-center py-2 bg-slate-50 hover:bg-indigo-50 text-indigo-600 hover:text-indigo-700 font-semibold text-xs rounded-lg border border-slate-200 transition">Analisis Penukaran →</a>
                </div>

                <!-- Analisis Portfolio & Pakej -->
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm hover:border-indigo-300 transition">
                    <div class="flex justify-between items-start mb-2">
                        <h3 class="text-xs font-bold text-slate-900">Analisis Portfolio & Pakej</h3>
                        <span class="text-[10px] bg-emerald-50 text-emerald-700 border border-emerald-200 px-2 py-0.5 rounded font-semibold">Sambutan Pakej</span>
                    </div>
                    <p class="text-[11px] text-slate-500 mb-4">Mengenal pasti pakej yang paling laris dan kurang mendapat sambutan untuk strategi harga baharu.</p>
                    <a href="report_package_performance.php?year=<?= $selected_year; ?>" class="inline-block w-full text-center py-2 bg-slate-50 hover:bg-indigo-50 text-indigo-600 hover:text-indigo-700 font-semibold text-xs rounded-lg border border-slate-200 transition">Prestasi Pakej →</a>
                </div>

                <!-- Analisis Pelanggan -->
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm hover:border-indigo-300 transition">
                    <div class="flex justify-between items-start mb-2">
                        <h3 class="text-xs font-bold text-slate-900">Analisis Pelanggan & Trend</h3>
                        <span class="text-[10px] bg-teal-50 text-teal-700 border border-teal-200 px-2 py-0.5 rounded font-semibold">Demografi</span>
                    </div>
                    <p class="text-[11px] text-slate-500 mb-4">Menilai trend pilihan majlis, purata harapan tempahan, dan tingkah laku pembeli semasa.</p>
                    <a href="report_customer_analysis.php?year=<?= $selected_year; ?>" class="inline-block w-full text-center py-2 bg-slate-50 hover:bg-indigo-50 text-indigo-600 hover:text-indigo-700 font-semibold text-xs rounded-lg border border-slate-200 transition">Analisis Pelanggan →</a>
                </div>
            </div>
        </section>

        <!-- 4. LAPORAN PEMATUHAN & AUDIT -->
        <section class="space-y-4">
            <div class="border-b border-slate-200 pb-2">
                <h2 class="text-sm font-bold uppercase tracking-wider text-indigo-600 flex items-center gap-2">
                    🏛️ 4. Laporan Pematuhan & Cukai
                </h2>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Laporan Cukai (LHDN) -->
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm hover:border-indigo-300 transition">
                    <div class="flex justify-between items-start mb-2">
                        <h3 class="text-xs font-bold text-slate-900">Laporan Fail Cukai (Peringkat LHDN)</h3>
                        <span class="text-[10px] bg-rose-50 text-rose-700 border border-rose-200 px-2 py-0.5 rounded font-semibold">Wajib Cukai</span>
                    </div>
                    <p class="text-[11px] text-slate-500 mb-4">Dokumen rekod pendapatan perniagaan & resit perbelanjaan terperinci untuk urusan percukaian LHDN.</p>
                    <a href="report_tax.php?year=<?= $selected_year; ?>" class="inline-block w-full text-center py-2 bg-slate-50 hover:bg-indigo-50 text-indigo-600 hover:text-indigo-700 font-semibold text-xs rounded-lg border border-slate-200 transition">Sediakan Fail Cukai →</a>
                </div>

                <!-- Laporan Audit Rekod Kewangan -->
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm hover:border-indigo-300 transition">
                    <div class="flex justify-between items-start mb-2">
                        <h3 class="text-xs font-bold text-slate-900">Laporan Audit & Pematuhan Log</h3>
                        <span class="text-[10px] bg-slate-100 text-slate-700 border border-slate-200 px-2 py-0.5 rounded font-semibold">Jejak Audit</span>
                    </div>
                    <p class="text-[11px] text-slate-500 mb-4">Menilai ketepatan rekod transaksi bayaran, penetapan semula invois, dan integriti data pangkalan.</p>
                    <a href="report_audit.php" class="inline-block w-full text-center py-2 bg-slate-50 hover:bg-indigo-50 text-indigo-600 hover:text-indigo-700 font-semibold text-xs rounded-lg border border-slate-200 transition">Semak Jejak Audit →</a>
                </div>
            </div>
        </section>

    </main>

</body>
</html>