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

$event_id = (int)($_GET['event_id'] ?? 0);

$eventQuery = $conn->query("
    SELECT events.*, clients.client_name 
    FROM events 
    JOIN clients ON events.client_id = clients.id 
    WHERE events.id = $event_id
");
$event = $eventQuery ? $eventQuery->fetch_assoc() : null;

if (!$event) {
    die("Acara tidak dijumpai!");
}

$tasksQuery = $conn->query("SELECT * FROM event_tasks WHERE event_id = $event_id ORDER BY id ASC");
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Urus Tugasan - Studio Manager</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 text-slate-800 text-xs font-sans min-h-screen">

    <header class="bg-slate-900 text-white sticky top-0 z-30 shadow-md">
        <div class="max-w-7xl mx-auto px-4 py-3.5 flex justify-between items-center">
            <div>
                <h1 class="text-sm font-bold">📝 Checklist Tugasan Acara</h1>
                <p class="text-[11px] text-slate-400"><?= htmlspecialchars($event['event_title']); ?> • Pelanggan: <?= htmlspecialchars($event['client_name']); ?></p>
            </div>
            <a href="progress_report.php" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-md border border-slate-700">← Kembali ke Laporan</a>
        </div>
    </header>

    <main class="max-w-4xl mx-auto px-4 py-8 grid grid-cols-1 md:grid-cols-3 gap-6">
            
        <!-- Borang Tambah Tugasan -->
        <div class="bg-white rounded-xl border border-slate-200/80 shadow-sm p-5 space-y-3 h-fit">
            <h3 class="text-sm font-bold text-slate-900 border-b pb-2">➕ Tambah Tugasan</h3>
            <form action="api/save_task.php" method="POST" class="space-y-3">
                <input type="hidden" name="event_id" value="<?= $event_id; ?>">

                <div>
                    <label class="block font-bold mb-1">Nama Tugasan *</label>
                    <input type="text" name="task_name" required placeholder="cth: Edit Video Highlight" class="w-full border p-2 rounded-lg bg-slate-50">
                </div>

                <div>
                    <label class="block font-bold mb-1">Penanggungjawab</label>
                    <input type="text" name="assigned_to" placeholder="Nama Staff / Crew" class="w-full border p-2 rounded-lg bg-slate-50">
                </div>

                <div>
                    <label class="block font-bold mb-1">Tarikh Akhir (Due Date)</label>
                    <input type="date" name="due_date" class="w-full border p-2 rounded-lg bg-slate-50">
                </div>

                <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-500 text-white font-bold py-2 rounded-lg shadow transition">
                    💾 Simpan Tugasan
                </button>
            </form>
        </div>

        <!-- Senarai Semak Tugasan -->
        <div class="md:col-span-2 bg-white rounded-xl border border-slate-200/80 shadow-sm p-5 space-y-3">
            <h3 class="text-sm font-bold text-slate-900 border-b pb-2">📋 Senarai Semak Tugasan</h3>
            
            <div class="space-y-2">
                <?php if ($tasksQuery && $tasksQuery->num_rows > 0): ?>
                    <?php while($task = $tasksQuery->fetch_assoc()): ?>
                        <div class="flex items-center justify-between p-3 bg-slate-50 rounded-lg border border-slate-200">
                            <div>
                                <p class="font-bold text-slate-800 <?= $task['status'] === 'completed' ? 'line-through text-slate-400' : ''; ?>">
                                    <?= htmlspecialchars($task['task_name']); ?>
                                </p>
                                <div class="text-[10px] text-slate-400">
                                    👤 <?= htmlspecialchars($task['assigned_to'] ?? 'Tiada'); ?>
                                    <?= !empty($task['due_date']) ? ' • 📅 Tarikh Akhir: ' . date('d/m/Y', strtotime($task['due_date'])) : ''; ?>
                                </div>
                            </div>

                            <form action="api/update_task_status.php" method="POST" class="flex items-center space-x-2">
                                <input type="hidden" name="task_id" value="<?= $task['id']; ?>">
                                <input type="hidden" name="event_id" value="<?= $event_id; ?>">
                                <select name="status" onchange="this.form.submit()" class="text-[10px] font-bold rounded p-1 border border-slate-200 
                                    <?= $task['status'] === 'completed' ? 'bg-emerald-100 text-emerald-800' : ($task['status'] === 'in_progress' ? 'bg-amber-100 text-amber-800' : 'bg-slate-200 text-slate-700'); ?>">
                                    <option value="pending" <?= $task['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="in_progress" <?= $task['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                    <option value="completed" <?= $task['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                </select>
                            </form>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p class="text-slate-400 italic text-center py-4">Belum ada sebarang tugasan dicipta untuk acara ini.</p>
                <?php endif; ?>
            </div>
        </div>

    </main>

</body>
</html>