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

$event_id = $_GET['event_id'] ?? null;

if (!$event_id) {
    die("ID Acara/Majlis tidak sah.");
}

// 1. TAMBAH ASSIGN CREW BAHARU
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_assign_crew'])) {
    $crew_id       = (int)$_POST['crew_id'];
    $assigned_role = trim($_POST['assigned_role'] ?? '');
    $pay_amount    = (float)($_POST['pay_amount'] ?? 0);
    $status        = $_POST['status'] ?? 'assigned';

    if ($crew_id > 0) {
        $stmt = $conn->prepare("INSERT INTO event_crew (event_id, crew_id, assigned_role, pay_amount, status) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iisds", $event_id, $crew_id, $assigned_role, $pay_amount, $status);
        $stmt->execute();
        echo "<script>alert('Crew berjaya ditugaskan!'); window.location.href='assign_crew.php?event_id={$event_id}';</script>";
        exit();
    }
}

// 2. KEMASKINI STATUS PROGRESS TASK
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_update_task'])) {
    $task_id     = (int)$_POST['task_id'];
    $task_status = $_POST['task_status'] ?? 'pending';

    $stmt = $conn->prepare("UPDATE event_tasks SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $task_status, $task_id);
    $stmt->execute();

    echo "<script>alert('Status tugasan berjaya dikemaskini!'); window.location.href='assign_crew.php?event_id={$event_id}';</script>";
    exit();
}

// 3. TAMBAH TASK BAHARU UNTUK CLIENT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_add_task'])) {
    $task_name   = trim($_POST['task_name'] ?? '');
    $assigned_to = trim($_POST['assigned_to'] ?? '');
    $due_date    = !empty($_POST['due_date']) ? $_POST['due_date'] : null;

    if (!empty($task_name)) {
        $stmt = $conn->prepare("INSERT INTO event_tasks (event_id, task_name, assigned_to, due_date, status) VALUES (?, ?, ?, ?, 'pending')");
        $stmt->bind_param("isss", $event_id, $task_name, $assigned_to, $due_date);
        $stmt->execute();
        echo "<script>alert('Tugasan baharu berjaya ditambah!'); window.location.href='assign_crew.php?event_id={$event_id}';</script>";
        exit();
    }
}

// 4. PADAM CREW DARI EVENT
if (isset($_GET['action']) && $_GET['action'] === 'delete_crew' && isset($_GET['event_crew_id'])) {
    $ec_id = (int)$_GET['event_crew_id'];
    $conn->query("DELETE FROM event_crew WHERE id = $ec_id");
    echo "<script>alert('Tugasan crew berjaya dipadam!'); window.location.href='assign_crew.php?event_id={$event_id}';</script>";
    exit();
}

// AMBIL MAKLUMAT EVENT & CLIENT
$evStmt = $conn->prepare("SELECT e.*, c.client_name, c.phone_number FROM events e JOIN clients c ON e.client_id = c.id WHERE e.id = ?");
$evStmt->bind_param("i", $event_id);
$evStmt->execute();
$eventData = $evStmt->get_result()->fetch_assoc();

if (!$eventData) {
    die("Maklumat majlis tidak ditemui.");
}

// AMBIL SENARAI CREW DIBAYAR UNTUK MAJLIS INI
$assignedCrewRes = $conn->query("SELECT ec.*, cr.name as crew_name, cr.phone as crew_phone 
                                 FROM event_crew ec 
                                 JOIN crew cr ON ec.crew_id = cr.id 
                                 WHERE ec.event_id = $event_id");

// AMBIL SENARAI CREW AKTIF UNTUK PILIHAN DROPDOWN
$allCrewRes = $conn->query("SELECT * FROM crew WHERE is_deleted = 0 ORDER BY name ASC");

// AMBIL SENARAI TASKS / PROGRESS UNTUK MAJLIS INI
$tasksRes = $conn->query("SELECT * FROM event_tasks WHERE event_id = $event_id ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <title>Assign Crew & Progress - Studio Manager</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 text-slate-800 text-xs min-h-screen">

    <header class="bg-slate-900 text-white sticky top-0 z-30 shadow-md">
        <div class="max-w-7xl mx-auto px-4 py-3.5 flex justify-between items-center">
            <div>
                <h1 class="text-sm font-bold">🎬 Tugasan Crew & Progress Majlis</h1>
                <p class="text-[11px] text-slate-400"><?= htmlspecialchars($eventData['event_title'] ?? ''); ?> • Pelanggan: <?= htmlspecialchars($eventData['client_name'] ?? ''); ?></p>
            </div>
            <a href="clients.php" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-md border border-slate-700">← Kembali ke Pelanggan</a>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 py-8 grid grid-cols-1 md:grid-cols-2 gap-6">

        <!-- PANEL 1: ASSIGN CREW -->
        <div class="space-y-6">
            <!-- Borang Assign Crew -->
            <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm space-y-4">
                <h2 class="text-sm font-bold text-slate-900 border-b pb-2">➕ Tugaskan Crew Baharu</h2>
                <form method="POST" class="space-y-3">
                    <input type="hidden" name="action_assign_crew" value="1">

                    <div>
                        <label class="block font-bold mb-1">Pilih Crew *</label>
                        <select name="crew_id" required class="w-full border p-2 rounded-lg bg-slate-50">
                            <option value="">-- Pilih Crew --</option>
                            <?php if ($allCrewRes && $allCrewRes->num_rows > 0): ?>
                                <?php while($cr = $allCrewRes->fetch_assoc()): ?>
                                    <option value="<?= $cr['id']; ?>"><?= htmlspecialchars($cr['name'] ?? ''); ?> (<?= htmlspecialchars($cr['role'] ?? ''); ?>)</option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold mb-1">Peranan Tugasan *</label>
                            <input type="text" name="assigned_role" placeholder="e.g. Main Photographer" required class="w-full border p-2 rounded-lg bg-slate-50">
                        </div>
                        <div>
                            <label class="block font-bold mb-1">Bayaran Crew (RM)</label>
                            <input type="number" step="0.01" name="pay_amount" value="0.00" class="w-full border p-2 rounded-lg font-mono bg-slate-50">
                        </div>
                    </div>

                    <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 rounded-lg shadow">💾 Tugaskan Crew</button>
                </form>
            </div>

            <!-- Senarai Crew Ditugaskan -->
            <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                <h2 class="text-sm font-bold text-slate-900 mb-3">👥 Senarai Crew Majlis ini</h2>
                <div class="space-y-2">
                    <?php if ($assignedCrewRes && $assignedCrewRes->num_rows > 0): ?>
                        <?php while($ac = $assignedCrewRes->fetch_assoc()): ?>
                            <div class="p-3 bg-slate-50 rounded-lg border border-slate-200 flex justify-between items-center">
                                <div>
                                    <div class="font-bold text-slate-800 text-xs"><?= htmlspecialchars($ac['crew_name'] ?? ''); ?></div>
                                    <div class="text-[10px] text-slate-500"><?= htmlspecialchars($ac['assigned_role'] ?? ''); ?> • 📞 <?= htmlspecialchars($ac['crew_phone'] ?? ''); ?></div>
                                    <div class="font-mono font-bold text-emerald-600 text-[11px] mt-0.5">RM <?= number_format($ac['pay_amount'], 2); ?></div>
                                </div>
                                <a href="assign_crew.php?event_id=<?= $event_id; ?>&action=delete_crew&event_crew_id=<?= $ac['id']; ?>" 
                                   onclick="return confirm('Adakah anda pasti ingin memadam crew ini?');" 
                                   class="px-2 py-1 bg-rose-50 text-rose-600 rounded font-bold hover:bg-rose-100">
                                   🗑️ Padam
                                </a>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p class="text-slate-400 italic">Belum ada sebarang crew ditugaskan bagi majlis ini.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- PANEL 2: PROGRESS / TASKS CLIENT -->
        <div class="space-y-6">
            <!-- Borang Tambah Tugasan Progress -->
            <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm space-y-4">
                <h2 class="text-sm font-bold text-slate-900 border-b pb-2">📋 Tambah Checklist / Progress Tugasan</h2>
                <form method="POST" class="space-y-3">
                    <input type="hidden" name="action_add_task" value="1">

                    <div>
                        <label class="block font-bold mb-1">Nama Tugasan / Progress *</label>
                        <input type="text" name="task_name" placeholder="e.g. Editing Gambar, Print Album, Serah USB" required class="w-full border p-2 rounded-lg bg-slate-50">
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold mb-1">Penanggungjawab</label>
                            <input type="text" name="assigned_to" placeholder="Nama Admin / Crew" class="w-full border p-2 rounded-lg bg-slate-50">
                        </div>
                        <div>
                            <label class="block font-bold mb-1">Tarikh Akhir (Due Date)</label>
                            <input type="date" name="due_date" class="w-full border p-2 rounded-lg bg-slate-50">
                        </div>
                    </div>

                    <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2 rounded-lg shadow">➕ Tambah Progress</button>
                </form>
            </div>

            <!-- Senarai Task & Kemaskini Status Progress -->
            <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                <h2 class="text-sm font-bold text-slate-900 mb-3">🔄 Status Progress Tugasan Pelanggan</h2>
                <div class="space-y-2">
                    <?php if ($tasksRes && $tasksRes->num_rows > 0): ?>
                        <?php while($task = $tasksRes->fetch_assoc()): ?>
                            <div class="p-3 bg-slate-50 rounded-lg border border-slate-200 flex justify-between items-center gap-3">
                                <div>
                                    <div class="font-bold text-slate-800"><?= htmlspecialchars($task['task_name'] ?? ''); ?></div>
                                    <div class="text-[10px] text-slate-400">
                                        👤 <?= htmlspecialchars($task['assigned_to'] ?? 'Tiada'); ?> 
                                        <?= !empty($task['due_date']) ? ' • 📅 Tarikh Akhir: '.date('d/m/Y', strtotime($task['due_date'])) : ''; ?>
                                    </div>
                                </div>
                                
                                <form method="POST" class="flex items-center gap-1">
                                    <input type="hidden" name="action_update_task" value="1">
                                    <input type="hidden" name="task_id" value="<?= $task['id']; ?>">
                                    <select name="task_status" onchange="this.form.submit()" class="border p-1 rounded font-bold text-[10px] <?= $task['status'] === 'completed' ? 'bg-emerald-100 text-emerald-800' : ($task['status'] === 'in_progress' ? 'bg-amber-100 text-amber-800' : 'bg-slate-200 text-slate-700'); ?>">
                                        <option value="pending" <?= $task['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="in_progress" <?= $task['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                        <option value="completed" <?= $task['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                    </select>
                                </form>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p class="text-slate-400 italic">Belum ada sebarang progress tugasan direkodkan.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </main>

</body>
</html>