<?php
// Aktifkan pameran ralat untuk debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/db.php';
require_once 'config/auth.php';

// 1. Kira Jumlah Duit Masuk (Kutipan)
$totalIncomeQuery = $conn->query("SELECT SUM(amount_paid) AS total FROM payments");
$totalIncome = $totalIncomeQuery->fetch_assoc()['total'] ?? 0;

// 2. Kira Jumlah Duit Keluar (Perbelanjaan)
$totalExpenseQuery = $conn->query("SELECT SUM(amount) AS total FROM expenses");
$totalExpense = $totalExpenseQuery->fetch_assoc()['total'] ?? 0;

// 3. Untung Bersih
$netProfit = $totalIncome - $totalExpense;

// 4. Ambil Senarai Invois
$invoicesQuery = $conn->query("
    SELECT invoices.*, clients.client_name, events.event_title, events.event_date 
    FROM invoices 
    JOIN clients ON invoices.client_id = clients.id 
    JOIN events ON invoices.event_id = events.id 
    ORDER BY invoices.id DESC
");

// 5. Auto-Detect URL untuk Pautan Borang Tempahan
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$script_dir = dirname($_SERVER['SCRIPT_NAME']);
$script_dir = ($script_dir === '/' || $script_dir === '\\') ? '' : $script_dir;

$baseUrl = $protocol . "://" . $host . $script_dir;
$weddingLink   = $baseUrl . "/booking.php?type=wedding";
$corporateLink = $baseUrl . "/booking.php?type=corporate";
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Studio Manager - Dashboard</title>

    <!-- Google Font: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#0f172a">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen">

    <!-- Top Navigation / Header -->
    <header class="bg-slate-900 text-white sticky top-0 z-30 shadow-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3.5 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div class="flex items-center space-x-3">
                <div class="w-9 h-9 bg-indigo-600 rounded-lg flex items-center justify-center font-bold text-lg text-white shadow-inner">
                    📷
                </div>
                <div>
                    <h1 class="text-base font-semibold tracking-tight text-white leading-tight">Studio Manager</h1>
                    <p class="text-[11px] text-slate-400">Sistem Pengurusan Kewangan & Tempahan</p>
                </div>
            </div>

            <!-- Quick Action Links -->
            <nav class="flex flex-wrap gap-1.5 text-xs font-medium">
                <a href="booking.php" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-md transition shadow-sm">+ Booking</a>
                <a href="expenses.php" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-md transition border border-slate-700">+ Perbelanjaan</a>
                <a href="packages.php" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-md transition border border-slate-700">Pakej</a>
                <a href="crew.php" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-md transition border border-slate-700">Crew</a>
                <a href="payroll.php" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-md transition border border-slate-700">Gaji</a>
                <a href="progress_report.php" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-md transition border border-slate-700">Kemajuan</a>
                <a href="financial_report.php" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-md transition border border-slate-700">Penyata</a>
                <a href="reminders.php" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-md transition border border-slate-700">Peringatan</a>
                <a href="logout.php" class="px-3 py-1.5 bg-rose-950/40 text-rose-300 hover:bg-rose-900/50 rounded-md transition border border-rose-800/40 ml-auto md:ml-2">Logout</a>
            </nav>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

        <!-- Ringkasan Kewangan (Stat Cards) -->
        <section class="grid grid-cols-1 md:grid-cols-3 gap-5">
            <!-- Duit Masuk -->
            <div class="bg-white p-5 rounded-xl border border-slate-200/80 shadow-sm flex flex-col justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Total Kutipan (Duit Masuk)</span>
                <div class="mt-2 flex items-baseline justify-between">
                    <h3 class="text-2xl font-bold text-slate-900">RM <?= number_format($totalIncome, 2); ?></h3>
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 inline-block"></span>
                </div>
            </div>

            <!-- Duit Keluar -->
            <div class="bg-white p-5 rounded-xl border border-slate-200/80 shadow-sm flex flex-col justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Total Perbelanjaan</span>
                <div class="mt-2 flex items-baseline justify-between">
                    <h3 class="text-2xl font-bold text-slate-900">RM <?= number_format($totalExpense, 2); ?></h3>
                    <span class="w-2.5 h-2.5 rounded-full bg-rose-500 inline-block"></span>
                </div>
            </div>

            <!-- Untung Bersih -->
            <div class="bg-white p-5 rounded-xl border border-slate-200/80 shadow-sm flex flex-col justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Untung Bersih</span>
                <div class="mt-2 flex items-baseline justify-between">
                    <h3 class="text-2xl font-bold <?= $netProfit >= 0 ? 'text-indigo-600' : 'text-rose-600'; ?>">
                        RM <?= number_format($netProfit, 2); ?>
                    </h3>
                    <span class="w-2.5 h-2.5 rounded-full bg-indigo-500 inline-block"></span>
                </div>
            </div>
        </section>

        <!-- Widget Link Generator Borang Tempahan -->
        <section class="bg-white rounded-xl border border-slate-200/80 shadow-sm p-6">
            <div class="mb-4">
                <h2 class="text-sm font-semibold text-slate-900 tracking-tight">🔗 Pautan Borang Tempahan Direct</h2>
                <p class="text-xs text-slate-500">Salin pautan rasmi borang ini untuk dihantar terus kepada bakal pelanggan anda.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Pautan Perkahwinan -->
                <div class="p-3.5 bg-slate-50 border border-slate-200/80 rounded-lg space-y-2">
                    <div class="flex justify-between items-center text-xs">
                        <span class="font-medium text-slate-700">💍 Borang Perkahwinan & Event Peribadi</span>
                        <a href="<?= $weddingLink; ?>" target="_blank" class="text-indigo-600 hover:text-indigo-700 font-medium">Pratonton ↗</a>
                    </div>
                    <div class="flex gap-2">
                        <input type="text" id="weddingUrl" value="<?= $weddingLink; ?>" readonly class="w-full text-xs px-3 py-2 border border-slate-200 rounded bg-white text-slate-600 font-mono focus:outline-none">
                        <button onclick="copyLink('weddingUrl', this)" class="bg-slate-900 hover:bg-slate-800 text-white text-xs font-medium px-4 py-2 rounded transition whitespace-nowrap shadow-sm">
                            Salin Pautan
                        </button>
                    </div>
                </div>

                <!-- Pautan Korporat -->
                <div class="p-3.5 bg-slate-50 border border-slate-200/80 rounded-lg space-y-2">
                    <div class="flex justify-between items-center text-xs">
                        <span class="font-medium text-slate-700">🏢 Borang Acara Korporat & Syarikat</span>
                        <a href="<?= $corporateLink; ?>" target="_blank" class="text-indigo-600 hover:text-indigo-700 font-medium">Pratonton ↗</a>
                    </div>
                    <div class="flex gap-2">
                        <input type="text" id="corpUrl" value="<?= $corporateLink; ?>" readonly class="w-full text-xs px-3 py-2 border border-slate-200 rounded bg-white text-slate-600 font-mono focus:outline-none">
                        <button onclick="copyLink('corpUrl', this)" class="bg-slate-900 hover:bg-slate-800 text-white text-xs font-medium px-4 py-2 rounded transition whitespace-nowrap shadow-sm">
                            Salin Pautan
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <!-- Senarai Invois & Rekod Pelanggan -->
        <section class="bg-white rounded-xl border border-slate-200/80 shadow-sm overflow-hidden">
            <div class="p-5 border-b border-slate-100 flex justify-between items-center">
                <div>
                    <h2 class="text-sm font-semibold text-slate-900 tracking-tight">📑 Senarai Invois & Bayaran Pelanggan</h2>
                    <p class="text-xs text-slate-500">Rekod bayaran dan status kemajuan tugasan semasa.</p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left text-slate-600">
                    <thead class="text-[11px] font-semibold uppercase text-slate-400 bg-slate-50/80 border-b border-slate-100">
                        <tr>
                            <th class="px-5 py-3.5">No. Invois</th>
                            <th class="px-5 py-3.5">Pelanggan</th>
                            <th class="px-5 py-3.5">Tarikh Majlis</th>
                            <th class="px-5 py-3.5">Progress Tugasan</th>
                            <th class="px-5 py-3.5">Jumlah</th>
                            <th class="px-5 py-3.5">Status</th>
                            <th class="px-5 py-3.5 text-right">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if ($invoicesQuery && $invoicesQuery->num_rows > 0): ?>
                            <?php while($inv = $invoicesQuery->fetch_assoc()): 
                                $eid = (int)$inv['event_id'];
                                
                                // Kira Kemajuan Tugasan
                                $t_q = $conn->query("SELECT COUNT(*) AS total FROM event_tasks WHERE event_id = $eid");
                                $tot = $t_q ? (int)$t_q->fetch_assoc()['total'] : 0;
                                
                                $c_q = $conn->query("SELECT COUNT(*) AS completed FROM event_tasks WHERE event_id = $eid AND status = 'completed'");
                                $com = $c_q ? (int)$c_q->fetch_assoc()['completed'] : 0;
                                
                                $pct = $tot > 0 ? round(($com / $tot) * 100) : 0;
                            ?>
                                <tr class="hover:bg-slate-50/60 transition">
                                    <td class="px-5 py-4 font-semibold text-slate-900 whitespace-nowrap">
                                        <?= htmlspecialchars($inv['invoice_number']); ?>
                                    </td>
                                    <td class="px-5 py-4">
                                        <div class="font-medium text-slate-900"><?= htmlspecialchars($inv['client_name']); ?></div>
                                        <div class="text-[11px] text-slate-400"><?= htmlspecialchars($inv['event_title']); ?></div>
                                    </td>
                                    <td class="px-5 py-4 whitespace-nowrap text-slate-500">
                                        <?= date('d M Y', strtotime($inv['event_date'])); ?>
                                    </td>
                                    
                                    <!-- Progress Bar -->
                                    <td class="px-5 py-4 min-w-[150px]">
                                        <div class="flex justify-between items-center text-[10px] font-medium text-slate-500 mb-1">
                                            <span><?= $com; ?>/<?= $tot; ?> Selesai</span>
                                            <span class="font-semibold text-slate-700"><?= $pct; ?>%</span>
                                        </div>
                                        <div class="w-full bg-slate-100 rounded-full h-1.5 overflow-hidden">
                                            <div class="bg-indigo-600 h-1.5 rounded-full transition-all duration-300" style="width: <?= $pct; ?>%"></div>
                                        </div>
                                    </td>

                                    <td class="px-5 py-4 font-semibold text-slate-900 whitespace-nowrap">
                                        RM <?= number_format($inv['total_amount'], 2); ?>
                                    </td>
                                    <td class="px-5 py-4 whitespace-nowrap">
                                        <?php if($inv['status'] === 'paid'): ?>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">Selesai</span>
                                        <?php elseif($inv['status'] === 'partially_paid'): ?>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-amber-50 text-amber-700 border border-amber-200">Deposit</span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-rose-50 text-rose-700 border border-rose-200">Belum Bayar</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-5 py-4 text-right whitespace-nowrap space-x-1">
                                        <a href="view_invoice.php?id=<?= $inv['id']; ?>" class="px-2.5 py-1.5 bg-white border border-slate-200 text-slate-700 font-medium rounded hover:bg-slate-50 transition inline-block">
                                            📄 Invois
                                        </a>
                                        <a href="pay_invoice.php?id=<?= $inv['id']; ?>" class="px-2.5 py-1.5 bg-emerald-600 text-white font-medium rounded hover:bg-emerald-700 transition inline-block">
                                            💵 Bayar
                                        </a>
                                        <a href="assign_crew.php?event_id=<?= $inv['event_id']; ?>" class="px-2.5 py-1.5 bg-slate-100 text-slate-700 font-medium rounded hover:bg-slate-200 transition inline-block">
                                            👥 Crew
                                        </a>
                                        <a href="manage_tasks.php?event_id=<?= $inv['event_id']; ?>" class="px-2.5 py-1.5 bg-slate-100 text-slate-700 font-medium rounded hover:bg-slate-200 transition inline-block">
                                            📝 Tugasan
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="p-8 text-center text-slate-400">
                                    Tiada invois direkodkan. Sila cipta tempahan baru di <a href="booking.php" class="text-indigo-600 font-medium hover:underline">Borang Tempahan</a>.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

    </main>

    <!-- Banner Install PWA -->
    <div id="pwa-install-banner" class="fixed bottom-4 right-4 bg-slate-900 text-white p-4 rounded-xl shadow-xl hidden flex-col gap-2 z-50 border border-slate-800 max-w-xs">
        <div class="flex justify-between items-center">
            <span class="font-semibold text-xs text-slate-200">📱 Pasang Aplikasi Studio</span>
            <button onclick="dismissPWA()" class="text-xs text-slate-400 hover:text-white">✕</button>
        </div>
        <p class="text-[11px] text-slate-400">Pasang Studio Manager pada skrin telefon untuk akses pantas.</p>
        <button id="pwa-install-btn" class="bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-medium py-1.5 px-3 rounded transition shadow-sm mt-1">
            Install Aplikasi
        </button>
    </div>

    <!-- JavaScript Scripts -->
    <script>
    function copyLink(inputId, btn) {
        const copyText = document.getElementById(inputId);
        copyText.select();
        copyText.setSelectionRange(0, 99999);
        navigator.clipboard.writeText(copyText.value);

        const originalText = btn.innerHTML;
        btn.innerHTML = "✅ Disalin";
        btn.classList.replace("bg-slate-900", "bg-emerald-600");
        
        setTimeout(() => {
            btn.innerHTML = originalText;
            btn.classList.replace("bg-emerald-600", "bg-slate-900");
        }, 2000);
    }

    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('sw.js')
                .then(reg => console.log('Service Worker Registered'))
                .catch(err => console.log('Service Worker Failed', err));
        });
    }

    let deferredPrompt;
    const installBanner = document.getElementById('pwa-install-banner');
    const installBtn = document.getElementById('pwa-install-btn');

    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt = e;
        installBanner.classList.remove('hidden');
        installBanner.classList.add('flex');
    });

    installBtn.addEventListener('click', async () => {
        if (deferredPrompt) {
            deferredPrompt.prompt();
            const { outcome } = await deferredPrompt.userChoice;
            deferredPrompt = null;
            installBanner.classList.add('hidden');
        }
    });

    function dismissPWA() {
        installBanner.classList.add('hidden');
    }
    </script>
</body>
</html>