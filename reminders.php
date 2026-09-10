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

// 1. Invois Baki Belum Selesai
$unpaid_invoices = $conn->query("
    SELECT i.*, c.client_name, c.phone_number, e.event_title, e.event_date,
           COALESCE((SELECT SUM(p.amount_paid) FROM payments p WHERE p.invoice_id = i.id), 0) as total_paid
    FROM invoices i
    JOIN clients c ON i.client_id = c.id
    LEFT JOIN events e ON i.event_id = e.id
    HAVING (i.total_amount - total_paid) > 0.01
    ORDER BY i.id DESC
");

// 2. Tugasan Belum Siap
$pending_tasks = $conn->query("
    SELECT t.*, e.event_title
    FROM event_tasks t
    JOIN events e ON t.event_id = e.id
    WHERE t.status != 'completed'
    ORDER BY t.id DESC
");
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pusat Peringatan - Studio Manager</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 text-slate-800 text-xs font-sans min-h-screen">

    <header class="bg-slate-900 text-white sticky top-0 z-30 shadow-md">
        <div class="max-w-7xl mx-auto px-4 py-3.5 flex justify-between items-center">
            <div class="flex items-center space-x-3">
                <span class="text-xl">🔔</span>
                <div>
                    <h1 class="text-sm font-bold">Pusat Peringatan Automatik</h1>
                    <p class="text-[11px] text-slate-400">Peringatan WhatsApp untuk Baki Invois & Tugasan Crew</p>
                </div>
            </div>
            <a href="index.php" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-md border border-slate-700">← Dashboard</a>
        </div>
    </header>

    <main class="max-w-5xl mx-auto px-4 py-8 space-y-6">

        <!-- Bahagian 1: Peringatan Bayaran Pelanggan -->
        <div class="bg-white rounded-xl border border-slate-200/80 shadow-sm p-5 space-y-3">
            <h3 class="text-sm font-bold text-slate-900 border-b pb-2 flex items-center gap-2">
                💵 Bayaran Tertunggak Pelanggan
            </h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead class="text-[10px] font-bold uppercase text-slate-400 bg-slate-50 border-b">
                        <tr>
                            <th class="px-4 py-3">Pelanggan</th>
                            <th class="px-4 py-3">Invois & Acara</th>
                            <th class="px-4 py-3">Baki Tunggakan</th>
                            <th class="px-4 py-3 text-right">Tindakan WhatsApp</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if ($unpaid_invoices && $unpaid_invoices->num_rows > 0): ?>
                            <?php while($row = $unpaid_invoices->fetch_assoc()): 
                                $total_amt = (float)($row['total_amount'] ?? 0);
                                $paid_amt  = (float)($row['total_paid'] ?? 0);
                                $balance   = $total_amt - $paid_amt;
                                
                                $raw_phone = $row['phone_number'] ?? '';
                                $phone = preg_replace('/[^0-9]/', '', $raw_phone);
                                if (strpos($phone, '0') === 0) { $phone = '60' . substr($phone, 1); }

                                $msg = rawurlencode("Salam " . $row['client_name'] . ",\n\nIni adalah peringatan mesra bagi invois #" . $row['invoice_number'] . " dengan baki tunggakan sebanyak RM " . number_format($balance, 2) . ".\n\nSila hubungi pihak studio jika terdapat sebarang pertanyaan.\nTerima kasih!");
                            ?>
                                <tr class="hover:bg-slate-50 transition">
                                    <td class="px-4 py-3 font-bold text-slate-900"><?= htmlspecialchars($row['client_name']); ?></td>
                                    <td class="px-4 py-3">
                                        <div class="font-mono font-bold text-indigo-600"><?= htmlspecialchars($row['invoice_number']); ?></div>
                                        <div class="text-[10px] text-slate-400"><?= htmlspecialchars($row['event_title'] ?? 'Majlis'); ?></div>
                                    </td>
                                    <td class="px-4 py-3 font-mono font-bold text-rose-600">RM <?= number_format($balance, 2); ?></td>
                                    <td class="px-4 py-3 text-right">
                                        <?php if(!empty($phone)): ?>
                                            <a href="https://wa.me/<?= $phone; ?>?text=<?= $msg; ?>" target="_blank" class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded shadow transition inline-block">
                                                💬 WhatsApp Peringatan
                                            </a>
                                        <?php else: ?>
                                            <span class="text-slate-400 italic">Tiada No. Tel</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="p-6 text-center text-slate-400">Semua invois telah dijelaskan sepenuhnya.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Bahagian 2: Peringatan Tugasan Crew -->
        <div class="bg-white rounded-xl border border-slate-200/80 shadow-sm p-5 space-y-3">
            <h3 class="text-sm font-bold text-slate-900 border-b pb-2 flex items-center gap-2">
                📋 Tugasan Belum Selesai
            </h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead class="text-[10px] font-bold uppercase text-slate-400 bg-slate-50 border-b">
                        <tr>
                            <th class="px-4 py-3">Tugasan</th>
                            <th class="px-4 py-3">Acara</th>
                            <th class="px-4 py-3">Penanggungjawab</th>
                            <th class="px-4 py-3 text-right">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if ($pending_tasks && $pending_tasks->num_rows > 0): ?>
                            <?php while($task = $pending_tasks->fetch_assoc()): ?>
                                <tr class="hover:bg-slate-50 transition">
                                    <td class="px-4 py-3 font-bold text-slate-900"><?= htmlspecialchars($task['task_name'] ?? '-'); ?></td>
                                    <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars($task['event_title'] ?? '-'); ?></td>
                                    <td class="px-4 py-3 text-slate-700 font-semibold"><?= htmlspecialchars($task['assigned_to'] ?? 'Belum Diagihkan'); ?></td>
                                    <td class="px-4 py-3 text-right">
                                        <a href="manage_tasks.php?event_id=<?= $task['event_id']; ?>" class="px-2.5 py-1 bg-amber-500 hover:bg-amber-600 text-white font-bold rounded shadow transition inline-block">
                                            ✏️ Urus Tugasan
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="p-6 text-center text-slate-400">Semua tugasan telah diselesaikan!</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

</body>
</html>