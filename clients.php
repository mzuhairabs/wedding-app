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

// 1. KEMASKINI STRUKTUR JADUAL SECARA AUTOMATIK & SELAMAT
$conn->query("ALTER TABLE clients ADD COLUMN IF NOT EXISTS address TEXT NULL");
$conn->query("ALTER TABLE clients ADD COLUMN IF NOT EXISTS ic_number VARCHAR(50) NULL");
$conn->query("ALTER TABLE clients ADD COLUMN IF NOT EXISTS notes TEXT NULL");
$conn->query("ALTER TABLE clients ADD COLUMN IF NOT EXISTS is_deleted TINYINT(1) NOT NULL DEFAULT 0");
$conn->query("ALTER TABLE events ADD COLUMN IF NOT EXISTS event_time TIME NULL");

// Kemaskini jenis data supaya sepadan dengan struktur pangkalan data
$conn->query("ALTER TABLE events MODIFY COLUMN client_id BIGINT(20) NOT NULL");
$conn->query("ALTER TABLE events MODIFY COLUMN package_id BIGINT(20) NULL DEFAULT NULL");

// 2. TAMBAH PELANGGAN BAHARU BERSAMA MAJLIS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_add_client'])) {
    $name    = trim($_POST['client_name']);
    $phone   = trim($_POST['phone_number']);
    $email   = trim($_POST['email']);
    $ic      = trim($_POST['ic_number']);
    $address = trim($_POST['address']);
    $notes   = trim($_POST['notes']);

    $stmt = $conn->prepare("INSERT INTO clients (client_name, phone_number, email, ic_number, address, notes, is_deleted) VALUES (?, ?, ?, ?, ?, ?, 0)");
    $stmt->bind_param("ssssss", $name, $phone, $email, $ic, $address, $notes);
    
    if ($stmt->execute()) {
        $client_id = $stmt->insert_id;

        if (isset($_POST['new_events']) && is_array($_POST['new_events'])) {
            foreach ($_POST['new_events'] as $e) {
                $e_title = trim($e['title'] ?? '');
                $e_date  = !empty($e['date']) ? $e['date'] : null;
                $e_time  = !empty($e['time']) ? $e['time'] : null;
                $e_venue = trim($e['venue'] ?? '');

                if (!empty($e_title) && !empty($e_date)) {
                    $insEv = $conn->prepare("INSERT INTO events (client_id, package_id, event_title, event_date, event_time, venue_address) VALUES (?, NULL, ?, ?, ?, ?)");
                    $insEv->bind_param("issss", $client_id, $e_title, $e_date, $e_time, $e_venue);
                    $insEv->execute();
                }
            }
        }

        echo "<script>alert('Pelanggan & maklumat majlis berjaya ditambah!'); window.location.href='clients.php';</script>";
        exit();
    }
}

// 3. KEMASKINI LENGKAP PELANGGAN & MULTI-MAJLIS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_edit_client'])) {
    $c_id    = (int)$_POST['client_id'];
    $name    = trim($_POST['client_name']);
    $phone   = trim($_POST['phone_number']);
    $email   = trim($_POST['email']);
    $ic      = trim($_POST['ic_number']);
    $address = trim($_POST['address']);
    $notes   = trim($_POST['notes']);

    $stmt = $conn->prepare("UPDATE clients SET client_name = ?, phone_number = ?, email = ?, ic_number = ?, address = ?, notes = ? WHERE id = ?");
    $stmt->bind_param("ssssssi", $name, $phone, $email, $ic, $address, $notes, $c_id);
    $stmt->execute();

    // Kemaskini Acara Sedia Ada
    if (isset($_POST['events']) && is_array($_POST['events'])) {
        foreach ($_POST['events'] as $e_id => $e_data) {
            $e_id_int = (int)$e_id;
            $e_title  = trim($e_data['title'] ?? '');
            $e_date   = !empty($e_data['date']) ? $e_data['date'] : null;
            $e_time   = !empty($e_data['time']) ? $e_data['time'] : null;
            $e_venue  = trim($e_data['venue'] ?? '');

            if ($e_id_int > 0) {
                $upEvent = $conn->prepare("UPDATE events SET event_title = ?, event_date = ?, event_time = ?, venue_address = ? WHERE id = ?");
                $upEvent->bind_param("ssssi", $e_title, $e_date, $e_time, $e_venue, $e_id_int);
                $upEvent->execute();
            }
        }
    }

    // Tambah Acara Baharu
    if (isset($_POST['new_events']) && is_array($_POST['new_events'])) {
        foreach ($_POST['new_events'] as $new_e) {
            $n_title = trim($new_e['title'] ?? '');
            $n_date  = !empty($new_e['date']) ? $new_e['date'] : null;
            $n_time  = !empty($new_e['time']) ? $new_e['time'] : null;
            $n_venue = trim($new_e['venue'] ?? '');

            if (!empty($n_title) && !empty($n_date)) {
                $insEv = $conn->prepare("INSERT INTO events (client_id, package_id, event_title, event_date, event_time, venue_address) VALUES (?, NULL, ?, ?, ?, ?)");
                $insEv->bind_param("issss", $c_id, $n_title, $n_date, $n_time, $n_venue);
                $insEv->execute();
            }
        }
    }

    echo "<script>alert('Semua maklumat pelanggan & majlis berjaya dikemaskini!'); window.location.href='clients.php';</script>";
    exit();
}

// 4. PADAM ACARA TERTENTU
if (isset($_GET['action']) && $_GET['action'] === 'delete_event' && isset($_GET['event_id'])) {
    $del_event_id = (int)$_GET['event_id'];
    $conn->query("DELETE FROM events WHERE id = $del_event_id");
    echo "<script>alert('Acara majlis berjaya dipadam!'); window.location.href='clients.php';</script>";
    exit();
}

// 5. NYAHAKTIFKAN / SOFT DELETE PELANGGAN (DIBERSIHKAN & DIBERIKAN SEMAKAN)
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $client_id = (int)$_GET['id'];
    
    // Semak jika lajur is_deleted wujud
    $colCheck = $conn->query("SHOW COLUMNS FROM clients LIKE 'is_deleted'");
    if ($colCheck && $colCheck->num_rows > 0) {
        $stmt = $conn->prepare("UPDATE clients SET is_deleted = 1 WHERE id = ?");
    } else {
        $stmt = $conn->prepare("UPDATE clients SET status = 'inactive' WHERE id = ?");
    }
    
    if ($stmt) {
        $stmt->bind_param("i", $client_id);
        $stmt->execute();
        echo "<script>alert('Rekod pelanggan berjaya dinyahaktifkan!'); window.location.href='clients.php';</script>";
        exit();
    }
}

// 6. QUERY SENARAI PELANGGAN & ACARA (TIDAK TERMASUK REKOD DIPADAM)
$clientsQuery = "SELECT c.*, COUNT(e.id) as total_events 
                 FROM clients c 
                 LEFT JOIN events e ON c.id = e.client_id 
                 WHERE (c.is_deleted = 0 OR c.is_deleted IS NULL)
                 GROUP BY c.id 
                 ORDER BY c.id DESC";
$clientsResult = $conn->query($clientsQuery);
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengurusan Pelanggan - Studio Manager</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 text-slate-800 text-xs font-sans min-h-screen">

        <!-- Top Navigation / Header -->
    <header class="bg-slate-900 text-white sticky top-0 z-30 shadow-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3.5 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div class="flex items-center space-x-3">
                <div class="w-9 h-9 bg-indigo-600 rounded-lg flex items-center justify-center font-bold text-lg text-white shadow-inner">
                    📷
                </div>
                <div>
                    <h1 class="text-base font-semibold tracking-tight text-white leading-tight">Studio Manager</h1>
                    <p class="text-[11px] text-slate-400">Main Control Dashboard</p>
                </div>
            </div>

            <!-- Navigation Menu -->
            <nav class="flex flex-wrap items-center gap-2 text-xs font-medium">
                <a href="index.php" class="px-3 py-1.5 bg-indigo-600 text-white rounded-md transition border border-indigo-500">Dashboard</a>
                
                <button onclick="openBookingModal()" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-md transition shadow-sm font-bold">+ New Booking</button>

                <a href="clients.php" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-md transition border border-slate-700">Clients</a>

                <!-- DROPDOWN: ACCOUNTS & FINANCE -->
                <div class="relative group">
                    <button class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-md transition border border-slate-700 flex items-center gap-1">
                        Accounts & Finance ▾
                    </button>
                    <div class="absolute left-0 mt-1 w-48 bg-slate-800 border border-slate-700 rounded-lg shadow-xl hidden group-hover:block z-50 py-1">
                        <a href="payments.php" class="block px-3 py-2 text-slate-200 hover:bg-slate-700 hover:text-white"> Payment Module</a>
                        <a href="expenses.php" class="block px-3 py-2 text-slate-200 hover:bg-slate-700 hover:text-white"> Expense Tracking</a>
                        <a href="invoice_settings.php" class="block px-3 py-2 text-slate-200 hover:bg-slate-700 hover:text-white"> Invoice Settings</a>
                    </div>
                </div>

                <!-- DROPDOWN: REPORTS -->
                <div class="relative group">
                    <button class="px-3 py-1.5 bg-indigo-900/60 hover:bg-indigo-800 text-indigo-200 rounded-md transition border border-indigo-700/60 flex items-center gap-1 font-bold">
                         Reports ▾
                    </button>
                    <div class="absolute left-0 mt-1 w-64 bg-slate-800 border border-slate-700 rounded-lg shadow-xl hidden group-hover:block z-50 py-1 divide-y divide-slate-700/50">
                        <a href="reports.php" class="block px-3 py-2 text-indigo-400 hover:bg-slate-700 font-bold"> Main Report Center</a>
                        
                        <!-- Category 1: Finance -->
                        <div class="py-1">
                            <span class="block px-3 py-1 text-[10px] uppercase tracking-wider text-slate-400 font-bold">1. Finance & Projects</span>
                            <a href="report_job_costing.php" class="block px-3 py-1.5 text-slate-200 hover:bg-slate-700 hover:text-white text-[11px]"> Job Costing Report</a>
                            <a href="financial_report.php" class="block px-3 py-1.5 text-slate-200 hover:bg-slate-700 hover:text-white text-[11px]"> Profit & Loss (P&L)</a>
                            <a href="report_balance_sheet.php" class="block px-3 py-1.5 text-slate-200 hover:bg-slate-700 hover:text-white text-[11px]"> Balance Sheet & Cash Flow</a>
                        </div>

                        <!-- Category 2: Operations -->
                        <div class="py-1">
                            <span class="block px-3 py-1 text-[10px] uppercase tracking-wider text-slate-400 font-bold">2. Operations & Assets</span>
                            <a href="report_assets.php" class="block px-3 py-1.5 text-slate-200 hover:bg-slate-700 hover:text-white text-[11px]"> Equipment & Maintenance</a>
                            <a href="progress_report.php" class="block px-3 py-1.5 text-slate-200 hover:bg-slate-700 hover:text-white text-[11px]"> Production Pipeline Status</a>
                            <a href="report_productivity.php" class="block px-3 py-1.5 text-slate-200 hover:bg-slate-700 hover:text-white text-[11px]"> Inventory & Crew Output</a>
                        </div>

                        <!-- Category 3: Sales -->
                        <div class="py-1">
                            <span class="block px-3 py-1 text-[10px] uppercase tracking-wider text-slate-400 font-bold">3. Sales & Marketing</span>
                            <a href="report_conversion.php" class="block px-3 py-1.5 text-slate-200 hover:bg-slate-700 hover:text-white text-[11px]"> Booking Conversion Rates</a>
                            <a href="report_package_performance.php" class="block px-3 py-1.5 text-slate-200 hover:bg-slate-700 hover:text-white text-[11px]"> Package & Portfolio Performance</a>
                            <a href="report_customer_analysis.php" class="block px-3 py-1.5 text-slate-200 hover:bg-slate-700 hover:text-white text-[11px]"> Client Demographics & Trends</a>
                        </div>

                        <!-- Category 4: Compliance -->
                        <div class="py-1">
                            <span class="block px-3 py-1 text-[10px] uppercase tracking-wider text-slate-400 font-bold">4. Compliance & Audit</span>
                            <a href="report_tax.php" class="block px-3 py-1.5 text-slate-200 hover:bg-slate-700 hover:text-white text-[11px]"> Tax Reports (LHDN)</a>
                            <a href="report_audit.php" class="block px-3 py-1.5 text-slate-200 hover:bg-slate-700 hover:text-white text-[11px]"> System Audit Logs</a>
                        </div>
                    </div>
                </div>

                <!-- DROPDOWN: CREW & TASKS -->
                <div class="relative group">
                    <button class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-md transition border border-slate-700 flex items-center gap-1">
                         Crew & Tasks ▾
                    </button>
                    <div class="absolute left-0 mt-1 w-44 bg-slate-800 border border-slate-700 rounded-lg shadow-xl hidden group-hover:block z-50 py-1">
                        <a href="crew.php" class="block px-3 py-2 text-slate-200 hover:bg-slate-700 hover:text-white"> Crew Management</a>
                        <a href="payroll.php" class="block px-3 py-2 text-slate-200 hover:bg-slate-700 hover:text-white"> Payroll System</a>
                    </div>
                </div>

                <!-- DROPDOWN: SETTINGS -->
                <div class="relative group">
                    <button class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-md transition border border-slate-700 flex items-center gap-1">
                         Settings ▾
                    </button>
                    <div class="absolute left-0 mt-1 w-40 bg-slate-800 border border-slate-700 rounded-lg shadow-xl hidden group-hover:block z-50 py-1">
                        <a href="packages.php" class="block px-3 py-2 text-slate-200 hover:bg-slate-700 hover:text-white"> Service Packages</a>
                        <a href="reminders.php" class="block px-3 py-2 text-slate-200 hover:bg-slate-700 hover:text-white"> Reminders</a>
                    </div>
                </div>

                <a href="logout.php" class="px-3 py-1.5 bg-rose-950/40 text-rose-300 hover:bg-rose-900/50 rounded-md transition border border-rose-800/40 ml-auto md:ml-2">Logout</a>
            </nav>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 py-8 space-y-6">

        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-5 rounded-xl border border-slate-200/80 shadow-sm">
            <div>
                <h2 class="text-sm font-bold text-slate-900">📑 Senarai Pelanggan</h2>
                <p class="text-slate-500">Uruskan pendaftaran, invois, bayaran, dan rekod acara majlis pelanggan.</p>
            </div>

            <div class="flex flex-wrap items-center gap-2.5">
                <div class="relative min-w-[220px]">
                    <input type="text" id="clientSearch" onkeyup="filterClients()" placeholder="Cari nama, IC, telefon, e-mel..."
                        class="w-full text-xs px-3 py-2 pl-8 border border-slate-200 rounded-lg bg-white focus:outline-none focus:border-indigo-500 shadow-sm">
                    <span class="absolute left-2.5 top-2 text-slate-400">🔍</span>
                </div>

                <select id="clientSort" onchange="sortClients()" class="text-xs px-2.5 py-2 border border-slate-200 rounded-lg bg-white font-medium shadow-sm">
                    <option value="id_desc">Terkini Didaftar</option>
                    <option value="name_asc">Nama (A-Z)</option>
                    <option value="name_desc">Nama (Z-A)</option>
                </select>

                <button onclick="openAddModal()" class="px-3 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-bold rounded-lg shadow transition flex items-center gap-1.5">
                    ➕ Pelanggan Baharu
                </button>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-slate-200/80 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse" id="clientsTable">
                    <thead class="text-[10px] font-bold uppercase text-slate-400 bg-slate-50 border-b border-slate-100">
                        <tr>
                            <th class="px-4 py-3">ID & Nama Pelanggan</th>
                            <th class="px-4 py-3">No. IC / Pasport</th>
                            <th class="px-4 py-3">No. Telefon & E-mel</th>
                            <th class="px-4 py-3">Alamat Pelanggan</th>
                            <th class="px-4 py-3">Jumlah Acara</th>
                            <th class="px-4 py-3 text-right">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100" id="clientsTbody">
                        <?php if ($clientsResult && $clientsResult->num_rows > 0): ?>
                            <?php while($c = $clientsResult->fetch_assoc()): 
                                $c_id = $c['id'];
                                
                                $ev_res = $conn->query("SELECT * FROM events WHERE client_id = $c_id ORDER BY event_date ASC");
                                $ev_list = [];
                                if ($ev_res) { while($er = $ev_res->fetch_assoc()) { $ev_list[] = $er; } }

                                $inv_res = $conn->query("
                                    SELECT i.*, COALESCE(SUM(p.amount_paid), 0) as paid_sum 
                                    FROM invoices i 
                                    LEFT JOIN payments p ON i.id = p.invoice_id 
                                    WHERE i.client_id = $c_id 
                                    GROUP BY i.id
                                ");
                                $inv_list = [];
                                if ($inv_res) { while($ir = $inv_res->fetch_assoc()) { $inv_list[] = $ir; } }

                                $pay_res = $conn->query("
                                    SELECT p.*, i.invoice_number 
                                    FROM payments p 
                                    JOIN invoices i ON p.invoice_id = i.id 
                                    WHERE i.client_id = $c_id 
                                    ORDER BY p.payment_date DESC
                                ");
                                $pay_list = [];
                                if ($pay_res) { while($pr = $pay_res->fetch_assoc()) { $pay_list[] = $pr; } }
                            ?>
                                <tr class="hover:bg-slate-50 transition client-row"
                                    data-id="<?= $c['id']; ?>"
                                    data-name="<?= htmlspecialchars(strtolower($c['client_name'])); ?>"
                                    data-ic="<?= htmlspecialchars(strtolower($c['ic_number'] ?? '')); ?>"
                                    data-phone="<?= htmlspecialchars($c['phone_number']); ?>"
                                    data-email="<?= htmlspecialchars(strtolower($c['email'])); ?>">
                                    
                                    <td class="px-4 py-3 font-semibold text-slate-900">
                                        #C-<?= str_pad($c['id'], 4, '0', STR_PAD_LEFT); ?> • <?= htmlspecialchars($c['client_name']); ?>
                                    </td>
                                    <td class="px-4 py-3 font-mono text-slate-700">
                                        <?= htmlspecialchars($c['ic_number'] ?: '-'); ?>
                                    </td>
                                    <td class="px-4 py-3 font-mono text-slate-700">
                                        <div>📞 <?= htmlspecialchars($c['phone_number']); ?></div>
                                        <div class="text-[10px] text-slate-400">✉️ <?= htmlspecialchars($c['email'] ?: '-'); ?></div>
                                    </td>
                                    <td class="px-4 py-3 text-slate-600 max-w-xs truncate">
                                        📍 <?= htmlspecialchars($c['address'] ?: '-'); ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="px-2 py-0.5 rounded font-bold bg-indigo-50 text-indigo-700 border border-indigo-100">
                                            <?= $c['total_events']; ?> Acara
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right space-x-1 whitespace-nowrap">
    <!-- BUTANG BAHARU: LIHAT T&C BERSAMA TANDATANGAN -->
    <a href="view_tnc.php?client_id=<?= $c['id']; ?>" target="_blank" class="px-2 py-1 bg-indigo-50 text-indigo-700 border border-indigo-200 rounded font-semibold hover:bg-indigo-100 transition inline-block">
        📄 Lihat T&C
    </a>

    <button onclick="openFinanceModal(<?= htmlspecialchars(json_encode($c)); ?>, <?= htmlspecialchars(json_encode($inv_list)); ?>, <?= htmlspecialchars(json_encode($pay_list)); ?>)" class="px-2 py-1 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded font-semibold hover:bg-emerald-100 transition">
        🧾 Invois & Bayaran
    </button>
    <button onclick="openEditClientModal(<?= htmlspecialchars(json_encode($c)); ?>, <?= htmlspecialchars(json_encode($ev_list)); ?>)" class="px-2 py-1 bg-amber-50 text-amber-700 border border-amber-200 rounded font-semibold hover:bg-amber-100 transition">
        ✏️ Edit / Events
    </button>
    <a href="clients.php?action=delete&id=<?= $c['id']; ?>" onclick="return confirm('Adakah anda pasti ingin memadam pelanggan ini?');" class="px-2 py-1 bg-rose-50 text-rose-700 border border-rose-200 rounded font-semibold hover:bg-rose-100 transition inline-block">
        🗑️ Padam
    </a>
</td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="p-8 text-center text-slate-400">Tiada rekod pelanggan ditemui.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <!-- MODAL TAMBAH PELANGGAN BAHARU -->
    <div id="addModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-4 border-b pb-3">
                <h3 class="text-sm font-bold text-slate-900">➕ Tambah Pelanggan & Majlis Baharu</h3>
                <button onclick="closeAddModal()" class="text-slate-400 font-bold text-lg">&times;</button>
            </div>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action_add_client" value="1">
                
                <div class="bg-slate-50 p-3.5 rounded-xl border border-slate-200 space-y-2">
                    <h4 class="font-bold text-indigo-600 uppercase text-[10px]">1. Maklumat Pelanggan</h4>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block font-bold mb-1">Nama Pelanggan *</label>
                            <input type="text" name="client_name" required class="w-full border p-2 rounded-lg bg-white">
                        </div>
                        <div>
                            <label class="block font-bold mb-1">No. IC / Pasport</label>
                            <input type="text" name="ic_number" placeholder="e.g. 950101-10-5555" class="w-full border p-2 rounded-lg bg-white">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block font-bold mb-1">No. Telefon *</label>
                            <input type="text" name="phone_number" required class="w-full border p-2 rounded-lg bg-white">
                        </div>
                        <div>
                            <label class="block font-bold mb-1">E-mel</label>
                            <input type="email" name="email" class="w-full border p-2 rounded-lg bg-white">
                        </div>
                    </div>
                    <div>
                        <label class="block font-bold mb-1">Alamat Pelanggan</label>
                        <textarea name="address" rows="2" class="w-full border p-2 rounded-lg bg-white"></textarea>
                    </div>
                    <div>
                        <label class="block font-bold mb-1">Nota / Catatan Admin</label>
                        <textarea name="notes" rows="2" placeholder="Nota khas studio..." class="w-full border p-2 rounded-lg bg-white"></textarea>
                    </div>
                </div>

                <div class="bg-slate-50 p-3.5 rounded-xl border border-slate-200 space-y-2">
                    <div class="flex justify-between items-center">
                        <h4 class="font-bold text-indigo-600 uppercase text-[10px]">2. Acara Majlis Pertama</h4>
                        <button type="button" onclick="addEventRowToAddModal()" class="px-2 py-0.5 bg-indigo-600 text-white rounded font-bold text-[10px]">+ Acara</button>
                    </div>
                    <div id="addModalEventsContainer" class="space-y-2">
                        <div class="p-2.5 bg-white rounded border space-y-1.5">
                            <div class="grid grid-cols-3 gap-2">
                                <input type="text" name="new_events[0][title]" placeholder="Jenis Majlis" required class="border p-1.5 rounded bg-slate-50 font-bold">
                                <input type="date" name="new_events[0][date]" required class="border p-1.5 rounded bg-slate-50">
                                <input type="time" name="new_events[0][time]" class="border p-1.5 rounded bg-slate-50">
                            </div>
                            <input type="text" name="new_events[0][venue]" placeholder="Lokasi / Alamat Majlis" class="w-full border p-1.5 rounded bg-slate-50">
                        </div>
                    </div>
                </div>

                <button type="submit" class="w-full bg-indigo-600 text-white font-bold py-2.5 rounded-lg hover:bg-indigo-700 shadow mt-2">💾 Simpan Pelanggan Baharu</button>
            </form>
        </div>
    </div>

    <!-- MODAL EDIT PELANGGAN & MULTI-MAJLIS -->
    <div id="editClientModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-4 border-b pb-3">
                <h3 class="text-sm font-bold text-slate-900">✏️ Edit Maklumat Pelanggan & Acara Majlis</h3>
                <button onclick="closeEditClientModal()" class="text-slate-400 font-bold text-lg">&times;</button>
            </div>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action_edit_client" value="1">
                <input type="hidden" id="edit_client_id" name="client_id">

                <div class="bg-slate-50 p-3.5 rounded-xl border border-slate-200 space-y-2">
                    <h4 class="font-bold text-indigo-600 uppercase text-[10px]">1. Maklumat Pelanggan</h4>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block font-bold mb-1">Nama Pelanggan *</label>
                            <input type="text" id="edit_client_name" name="client_name" required class="w-full border p-2 rounded-lg bg-white">
                        </div>
                        <div>
                            <label class="block font-bold mb-1">No. IC / Pasport</label>
                            <input type="text" id="edit_ic_number" name="ic_number" class="w-full border p-2 rounded-lg bg-white">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block font-bold mb-1">No. Telefon *</label>
                            <input type="text" id="edit_phone_number" name="phone_number" required class="w-full border p-2 rounded-lg bg-white">
                        </div>
                        <div>
                            <label class="block font-bold mb-1">E-mel</label>
                            <input type="email" id="edit_email" name="email" class="w-full border p-2 rounded-lg bg-white">
                        </div>
                    </div>
                    <div>
                        <label class="block font-bold mb-1">Alamat Pelanggan</label>
                        <textarea id="edit_address" name="address" rows="2" class="w-full border p-2 rounded-lg bg-white"></textarea>
                    </div>
                    <div>
                        <label class="block font-bold mb-1">Nota / Catatan Admin</label>
                        <textarea id="edit_notes" name="notes" rows="2" class="w-full border p-2 rounded-lg bg-white"></textarea>
                    </div>
                </div>

                <div class="bg-slate-50 p-3.5 rounded-xl border border-slate-200 space-y-2">
                    <div class="flex justify-between items-center">
                        <h4 class="font-bold text-indigo-600 uppercase text-[10px]">2. Senarai Acara, Masa & Lokasi Majlis</h4>
                        <button type="button" onclick="addEventRowToEditModal()" class="px-2 py-0.5 bg-indigo-600 text-white rounded font-bold text-[10px]">+ Tambah Majlis</button>
                    </div>

                    <div id="existingEventsContainer" class="space-y-2"></div>
                    <div id="dynamicEditEventsContainer" class="space-y-2"></div>
                </div>

                <button type="submit" class="w-full bg-indigo-600 text-white font-bold py-2.5 rounded-lg hover:bg-indigo-700 shadow mt-2">💾 Simpan Semua Kemaskini</button>
            </form>
        </div>
    </div>

    <!-- MODAL INVOIS & SEJARAH BAYARAN PELANGGAN -->
    <div id="financeModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl max-w-xl w-full p-6 shadow-2xl border border-slate-200 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-4 border-b pb-3">
                <div>
                    <h3 class="text-sm font-bold text-slate-900">🧾 Invois & Sejarah Bayaran</h3>
                    <p id="financeClientName" class="text-[10px] text-indigo-600 font-semibold"></p>
                </div>
                <button onclick="closeFinanceModal()" class="text-slate-400 font-bold text-lg">&times;</button>
            </div>

            <div class="space-y-5 text-xs">
                <div class="bg-slate-50 p-3.5 rounded-xl border border-slate-200">
                    <h4 class="font-bold text-slate-800 uppercase text-[10px] mb-2">📑 Senarai Invois Pelanggan Ini</h4>
                    <div id="invoicesContainer" class="space-y-2"></div>
                </div>

                <div class="bg-slate-50 p-3.5 rounded-xl border border-slate-200">
                    <h4 class="font-bold text-slate-800 uppercase text-[10px] mb-2">📜 Sejarah Transaksi Pembayaran</h4>
                    <div id="paymentsContainer" class="space-y-2"></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let addEventCount = 0;
        let editEventCount = 0;

        function openAddModal() { document.getElementById('addModal').classList.remove('hidden'); document.getElementById('addModal').classList.add('flex'); }
        function closeAddModal() { document.getElementById('addModal').classList.add('hidden'); }

        function addEventRowToAddModal() {
            addEventCount++;
            const container = document.getElementById('addModalEventsContainer');
            const div = document.createElement('div');
            div.className = 'p-2.5 bg-white rounded border space-y-1.5';
            div.innerHTML = `
                <div class="grid grid-cols-3 gap-2">
                    <input type="text" name="new_events[${addEventCount}][title]" placeholder="Jenis Majlis" required class="border p-1.5 rounded bg-slate-50 font-bold">
                    <input type="date" name="new_events[${addEventCount}][date]" required class="border p-1.5 rounded bg-slate-50">
                    <input type="time" name="new_events[${addEventCount}][time]" class="border p-1.5 rounded bg-slate-50">
                </div>
                <input type="text" name="new_events[${addEventCount}][venue]" placeholder="Lokasi / Alamat Majlis" class="w-full border p-1.5 rounded bg-slate-50">
            `;
            container.appendChild(div);
        }

        function openEditClientModal(client, events) {
            document.getElementById('edit_client_id').value = client.id;
            document.getElementById('edit_client_name').value = client.client_name;
            document.getElementById('edit_ic_number').value = client.ic_number || '';
            document.getElementById('edit_phone_number').value = client.phone_number;
            document.getElementById('edit_email').value = client.email || '';
            document.getElementById('edit_address').value = client.address || '';
            document.getElementById('edit_notes').value = client.notes || '';

            const container = document.getElementById('existingEventsContainer');
            container.innerHTML = '';

            if (events && events.length > 0) {
                events.forEach(ev => {
                    const div = document.createElement('div');
                    div.className = 'p-2.5 bg-white rounded border space-y-1.5 relative';
                    div.innerHTML = `
                        <div class="flex justify-between items-center mb-1">
                            <span class="text-[10px] font-bold text-slate-400">ID Acara: #${ev.id}</span>
                            <div class="flex items-center gap-2">
                                <a href="assign_crew.php?event_id=${ev.id}" 
                                   class="px-2 py-0.5 bg-indigo-600 text-white rounded font-bold text-[10px] hover:bg-indigo-700">
                                   🎬 Assign Crew & Progress
                                </a>
                                <a href="clients.php?action=delete_event&event_id=${ev.id}" 
                                   onclick="return confirm('Adakah anda pasti ingin memadam acara majlis ini?');" 
                                   class="text-rose-600 font-bold text-[10px] hover:underline">
                                   🗑️ Padam
                                </a>
                            </div>
                        </div>
                        <div class="grid grid-cols-3 gap-2">
                            <input type="text" name="events[${ev.id}][title]" value="${ev.event_title}" placeholder="Jenis Majlis" required class="border p-1.5 rounded font-bold bg-slate-50">
                            <input type="date" name="events[${ev.id}][date]" value="${ev.event_date}" required class="border p-1.5 rounded bg-slate-50">
                            <input type="time" name="events[${ev.id}][time]" value="${ev.event_time || ''}" class="border p-1.5 rounded bg-slate-50">
                        </div>
                        <input type="text" name="events[${ev.id}][venue]" value="${ev.venue_address || ''}" placeholder="Lokasi Majlis" class="w-full border p-1.5 rounded bg-slate-50">
                    `;
                    container.appendChild(div);
                });
            }

            document.getElementById('dynamicEditEventsContainer').innerHTML = '';
            document.getElementById('editClientModal').classList.remove('hidden');
            document.getElementById('editClientModal').classList.add('flex');
        }

        function addEventRowToEditModal() {
            editEventCount++;
            const container = document.getElementById('dynamicEditEventsContainer');
            const div = document.createElement('div');
            div.className = 'p-2.5 bg-indigo-50/70 rounded border border-indigo-200 space-y-1.5 relative';
            div.innerHTML = `
                <div class="flex justify-between items-center mb-1">
                    <span class="text-[10px] font-bold text-indigo-900">Acara Baharu #${editEventCount}</span>
                    <button type="button" onclick="this.parentElement.parentElement.remove()" class="text-rose-600 font-bold text-[10px]">&times; Batal</button>
                </div>
                <div class="grid grid-cols-3 gap-2">
                    <input type="text" name="new_events[${editEventCount}][title]" placeholder="Jenis Majlis" required class="border p-1.5 rounded font-bold bg-white">
                    <input type="date" name="new_events[${editEventCount}][date]" required class="border p-1.5 rounded bg-white">
                    <input type="time" name="new_events[${editEventCount}][time]" class="border p-1.5 rounded bg-white">
                </div>
                <input type="text" name="new_events[${editEventCount}][venue]" placeholder="Lokasi / Alamat Majlis" class="w-full border p-1.5 rounded bg-white">
            `;
            container.appendChild(div);
        }

        function closeEditClientModal() { document.getElementById('editClientModal').classList.add('hidden'); }

        function openFinanceModal(client, invoices, payments) {
            document.getElementById('financeClientName').innerText = client.client_name + ' (📞 ' + client.phone_number + ')';

            const invContainer = document.getElementById('invoicesContainer');
            invContainer.innerHTML = '';
            if (invoices && invoices.length > 0) {
                invoices.forEach(inv => {
                    const total = parseFloat(inv.total_amount || 0);
                    const paid = parseFloat(inv.paid_sum || 0);
                    const balance = total - paid;
                    const statusBadge = balance <= 0 ? '<span class="px-1.5 py-0.5 bg-emerald-100 text-emerald-800 font-bold rounded">PAID</span>' : '<span class="px-1.5 py-0.5 bg-rose-100 text-rose-800 font-bold rounded">UNPAID</span>';

                    const div = document.createElement('div');
                    div.className = 'p-2.5 bg-white rounded border flex justify-between items-center';
                    div.innerHTML = `
                        <div>
                            <div class="font-bold text-slate-800 font-mono">${inv.invoice_number}</div>
                            <div class="text-[10px] text-slate-400">Jumlah: RM ${total.toFixed(2)} | Dibayar: RM ${paid.toFixed(2)} | Baki: RM ${balance.toFixed(2)}</div>
                        </div>
                        <div class="flex items-center gap-2">
                            ${statusBadge}
                            <a href="print_invoice.php?id=${inv.id}" target="_blank" class="px-2 py-1 bg-slate-800 text-white rounded font-bold text-[10px]">🖨️ Invois</a>
                            <a href="payments.php?invoice_id=${inv.id}" class="px-2 py-1 bg-emerald-600 text-white rounded font-bold text-[10px]">💳 Bayar</a>
                        </div>
                    `;
                    invContainer.appendChild(div);
                });
            } else {
                invContainer.innerHTML = '<p class="text-slate-400 italic">Tiada invois didaftarkan untuk pelanggan ini.</p>';
            }

            const payContainer = document.getElementById('paymentsContainer');
            payContainer.innerHTML = '';
            if (payments && payments.length > 0) {
                payments.forEach(p => {
                    const div = document.createElement('div');
                    div.className = 'p-2 bg-white rounded border flex justify-between items-center';
                    div.innerHTML = `
                        <div>
                            <div class="font-bold text-slate-800">RM ${parseFloat(p.amount_paid).toFixed(2)} <span class="text-[10px] font-normal text-slate-400">(${p.invoice_number})</span></div>
                            <div class="text-[10px] text-slate-400">${p.payment_date} • ${p.payment_type} • ${p.payment_method}</div>
                        </div>
                        <span class="text-[10px] font-mono text-emerald-600 font-bold">+ DITERIMA</span>
                    `;
                    payContainer.appendChild(div);
                });
            } else {
                payContainer.innerHTML = '<p class="text-slate-400 italic">Belum ada sebarang rekod bayaran.</p>';
            }

            document.getElementById('financeModal').classList.remove('hidden');
            document.getElementById('financeModal').classList.add('flex');
        }

        function closeFinanceModal() { document.getElementById('financeModal').classList.add('hidden'); }

        function filterClients() {
            const query = document.getElementById('clientSearch').value.toLowerCase().trim();
            document.querySelectorAll('.client-row').forEach(row => {
                const name = row.getAttribute('data-name');
                const ic = row.getAttribute('data-ic');
                const phone = row.getAttribute('data-phone');
                const email = row.getAttribute('data-email');
                if (name.includes(query) || ic.includes(query) || phone.includes(query) || email.includes(query)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        function sortClients() {
            const tbody = document.getElementById('clientsTbody');
            const rows = Array.from(document.querySelectorAll('.client-row'));
            const sortVal = document.getElementById('clientSort').value;

            rows.sort((a, b) => {
                if (sortVal === 'name_asc') {
                    return a.getAttribute('data-name').localeCompare(b.getAttribute('data-name'));
                } else if (sortVal === 'name_desc') {
                    return b.getAttribute('data-name').localeCompare(a.getAttribute('data-name'));
                } else {
                    return parseInt(b.getAttribute('data-id')) - parseInt(a.getAttribute('data-id'));
                }
            });

            rows.forEach(row => tbody.appendChild(row));
        }
    </script>

</body>
</html>