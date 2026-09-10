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

$sql = "
    SELECT 
        e.id AS event_id,
        e.event_title,
        e.event_date,
        c.client_name,
        (SELECT COUNT(*) FROM event_tasks WHERE event_id = e.id) AS total_tasks,
        (SELECT COUNT(*) FROM event_tasks WHERE event_id = e.id AND status = 'completed') AS completed_tasks
    FROM events e
    LEFT JOIN clients c ON e.client_id = c.id
    ORDER BY e.event_date DESC
";

$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Kemajuan - Studio Manager</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 text-slate-800 text-xs font-sans min-h-screen">

    <header class="bg-slate-900 text-white sticky top-0 z-30 shadow-md">
        <div class="max-w-7xl mx-auto px-4 py-3.5 flex justify-between items-center">
            <div class="flex items-center space-x-3">
                <span class="text-xl">📈</span>
                <div>
                    <h1 class="text-sm font-bold">Kemajuan Tugasan Acara</h1>
                    <p class="text-[11px] text-slate-400">Status Peratusan Penyiapan Projek Pelanggan</p>
                </div>
            </div>
            <a href="index.php" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-md border border-slate-700">← Dashboard</a>
        </div>
    </header>

    <main class="max-w-5xl mx-auto px-4 py-8 space-y-6">

        <div class="bg-white rounded-xl border border-slate-200/80 shadow-sm p-5 space-y-4">
            <div class="border-b pb-3">
                <h2 class="text-sm font-bold text-slate-900">📊 Laporan Status Penyiapan Projek</h2>
                <p class="text-slate-500">Pantau perkembangan proses suntingan, penghantaran album, dan servis pelanggan.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead class="text-[10px] font-bold uppercase text-slate-400 bg-slate-50 border-b">
                        <tr>
                            <th class="px-4 py-3">Acara & Pelanggan</th>
                            <th class="px-4 py-3">Tarikh Majlis</th>
                            <th class="px-4 py-3">Tugasan Siap</th>
                            <th class="px-4 py-3 text-center">Kemajuan (%)</th>
                            <th class="px-4 py-3 text-right">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while($row = $result->fetch_assoc()): 
                                $total = (int)($row['total_tasks'] ?? 0);
                                $completed = (int)($row['completed_tasks'] ?? 0);
                                $percentage = $total > 0 ? round(($completed / $total) * 100) : 0;
                            ?>
                                <tr class="hover:bg-slate-50 transition">
                                    <td class="px-4 py-3">
                                        <div class="font-bold text-slate-900"><?= htmlspecialchars($row['event_title'] ?? '-'); ?></div>
                                        <div class="text-[10px] text-slate-400">👤 <?= htmlspecialchars($row['client_name'] ?? 'Tiada Pelanggan'); ?></div>
                                    </td>
                                    <td class="px-4 py-3 font-mono"><?= !empty($row['event_date']) ? date('d/m/Y', strtotime($row['event_date'])) : '-'; ?></td>
                                    <td class="px-4 py-3 font-semibold">
                                        <?= $completed; ?> / <?= $total; ?> Tugasan
                                    </td>
                                    <td class="px-4 py-3 w-48">
                                        <div class="flex items-center space-x-2">
                                            <div class="w-full bg-slate-200 rounded-full h-2.5 overflow-hidden">
                                                <div class="bg-emerald-500 h-2.5 rounded-full transition-all duration-500" style="width: <?= $percentage; ?>%"></div>
                                            </div>
                                            <span class="font-mono font-bold text-slate-700 min-w-[32px]"><?= $percentage; ?>%</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <a href="manage_tasks.php?event_id=<?= $row['event_id']; ?>" class="px-2.5 py-1 bg-indigo-600 hover:bg-indigo-500 text-white text-[10px] font-bold rounded shadow transition inline-block">
                                            📝 Urus Tugasan
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="p-6 text-center text-slate-400">Tiada acara direkodkan lagi.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

</body>
</html>