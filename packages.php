<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'config/db.php';

// Pastikan lajur sort_order wujud
$conn->query("ALTER TABLE packages ADD COLUMN IF NOT EXISTS sort_order INT NOT NULL DEFAULT 0");

$edit_package = null;
if (isset($_GET['edit_id']) && !empty($_GET['edit_id'])) {
    $edit_id = (int)$_GET['edit_id'];
    $stmt_edit = $conn->prepare("SELECT * FROM packages WHERE id = ?");
    $stmt_edit->bind_param("i", $edit_id);
    $stmt_edit->execute();
    $res_edit = $stmt_edit->get_result();
    if ($res_edit->num_rows > 0) {
        $edit_package = $res_edit->fetch_assoc();
    }
    $stmt_edit->close();
}

$packages = $conn->query("SELECT * FROM packages ORDER BY sort_order ASC, base_price ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Studio Manager - Service Packages</title>

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
                    <p class="text-[11px] text-slate-400">Main Control Dashboard</p>
                </div>
            </div>

            <!-- Navigation Menu -->
            <nav class="flex flex-wrap items-center gap-2 text-xs font-medium">
                <a href="index.php" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-md transition border border-slate-700">Dashboard</a>
                
                <a href="booking.php" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-md transition shadow-sm font-bold">+ New Booking</a>

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
                        
                        <div class="py-1">
                            <span class="block px-3 py-1 text-[10px] uppercase tracking-wider text-slate-400 font-bold">1. Finance & Projects</span>
                            <a href="report_job_costing.php" class="block px-3 py-1.5 text-slate-200 hover:bg-slate-700 hover:text-white text-[11px]"> Job Costing Report</a>
                            <a href="financial_report.php" class="block px-3 py-1.5 text-slate-200 hover:bg-slate-700 hover:text-white text-[11px]"> Profit & Loss (P&L)</a>
                            <a href="report_balance_sheet.php" class="block px-3 py-1.5 text-slate-200 hover:bg-slate-700 hover:text-white text-[11px]"> Balance Sheet & Cash Flow</a>
                        </div>

                        <div class="py-1">
                            <span class="block px-3 py-1 text-[10px] uppercase tracking-wider text-slate-400 font-bold">2. Operations & Assets</span>
                            <a href="report_assets.php" class="block px-3 py-1.5 text-slate-200 hover:bg-slate-700 hover:text-white text-[11px]"> Equipment & Maintenance</a>
                            <a href="progress_report.php" class="block px-3 py-1.5 text-slate-200 hover:bg-slate-700 hover:text-white text-[11px]"> Production Pipeline Status</a>
                            <a href="report_productivity.php" class="block px-3 py-1.5 text-slate-200 hover:bg-slate-700 hover:text-white text-[11px]"> Inventory & Crew Output</a>
                        </div>

                        <div class="py-1">
                            <span class="block px-3 py-1 text-[10px] uppercase tracking-wider text-slate-400 font-bold">3. Sales & Marketing</span>
                            <a href="report_conversion.php" class="block px-3 py-1.5 text-slate-200 hover:bg-slate-700 hover:text-white text-[11px]"> Booking Conversion Rates</a>
                            <a href="report_package_performance.php" class="block px-3 py-1.5 text-slate-200 hover:bg-slate-700 hover:text-white text-[11px]"> Package & Portfolio Performance</a>
                            <a href="report_customer_analysis.php" class="block px-3 py-1.5 text-slate-200 hover:bg-slate-700 hover:text-white text-[11px]"> Client Demographics & Trends</a>
                        </div>

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
                    <button class="px-3 py-1.5 bg-indigo-600 text-white rounded-md transition border border-indigo-500 flex items-center gap-1">
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

        <section class="bg-white rounded-xl border border-slate-200/80 shadow-sm p-5" id="package-form">
            <div class="mb-4 border-b pb-3 flex justify-between items-center">
                <div>
                    <h2 class="text-sm font-bold text-slate-900"><?= $edit_package ? '✏️ Edit Service Package' : '➕ Add New Service Package'; ?></h2>
                    <p class="text-slate-500">Set standard package pricing and manual display sequence for the booking form.</p>
                </div>
                <?php if ($edit_package): ?>
                    <a href="packages.php" class="text-xs bg-slate-100 text-slate-600 px-3 py-1.5 rounded hover:bg-slate-200 font-bold transition">✕ Cancel</a>
                <?php endif; ?>
            </div>

            <form action="api/save_package.php" method="POST" class="space-y-4 text-xs">
                <?php if ($edit_package): ?>
                    <input type="hidden" name="package_id" value="<?= $edit_package['id']; ?>">
                <?php endif; ?>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block font-bold mb-1">Package Name *</label>
                        <input type="text" name="package_name" placeholder="e.g. Deluxe Wedding Package" required 
                            value="<?= htmlspecialchars($edit_package['package_name'] ?? ''); ?>"
                            class="w-full border p-2 rounded-lg bg-white">
                    </div>

                    <div>
                        <label class="block font-bold mb-1">Booking Category *</label>
                        <select name="form_category" required class="w-full border p-2 rounded-lg bg-slate-50">
                            <option value="wedding" <?= ($edit_package['form_category'] ?? '') === 'wedding' ? 'selected' : ''; ?>>Wedding / Personal Event</option>
                            <option value="corporate" <?= ($edit_package['form_category'] ?? '') === 'corporate' ? 'selected' : ''; ?>>Corporate Event</option>
                        </select>
                    </div>

                    <div>
                        <label class="block font-bold mb-1">Display Order (Sort Order) *</label>
                        <input type="number" name="sort_order" placeholder="0" required 
                            value="<?= $edit_package['sort_order'] ?? 0; ?>"
                            class="w-full border p-2 rounded-lg bg-white font-mono">
                        <span class="text-[10px] text-slate-400">Lower numbers (0, 1, 2...) appear first on the booking form.</span>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block font-bold mb-1">Required Events Count *</label>
                        <select name="event_count" required class="w-full border p-2 rounded-lg bg-white">
                            <?php for($i = 1; $i <= 5; $i++): ?>
                                <option value="<?= $i; ?>" <?= ($edit_package['event_count'] ?? 1) == $i ? 'selected' : ''; ?>><?= $i; ?> Event(s)</option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block font-bold mb-1">Base Price (RM) *</label>
                        <input type="number" step="0.01" name="base_price" placeholder="2500.00" required 
                            value="<?= $edit_package['base_price'] ?? ''; ?>"
                            class="w-full border p-2 rounded-lg bg-white font-mono font-bold text-slate-900">
                    </div>

                    <div>
                        <label class="block font-bold mb-1">Status *</label>
                        <select name="is_active" required class="w-full border p-2 rounded-lg bg-white">
                            <option value="1" <?= (!isset($edit_package['is_active']) || $edit_package['is_active'] == 1) ? 'selected' : ''; ?>>Active (Visible)</option>
                            <option value="0" <?= (isset($edit_package['is_active']) && $edit_package['is_active'] == 0) ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block font-bold mb-1">Description / Scope of Work</label>
                    <textarea name="description" rows="2" placeholder="e.g. 1x Photographer, Custom Album 12x18, Frame 16x24..." 
                        class="w-full border p-2 rounded-lg bg-white"><?= htmlspecialchars($edit_package['description'] ?? ''); ?></textarea>
                </div>

                <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 text-white font-bold px-5 py-2 rounded-lg shadow transition">
                    💾 <?= $edit_package ? 'Update Package' : 'Save Package'; ?>
                </button>
            </form>
        </section>

        <section class="bg-white rounded-xl border border-slate-200/80 shadow-sm overflow-hidden text-xs">
            <div class="p-4 border-b">
                <h2 class="text-sm font-bold text-slate-900">📋 Existing Service Packages</h2>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead class="text-[10px] font-bold uppercase text-slate-400 bg-slate-50 border-b">
                        <tr>
                            <th class="px-4 py-3">Order</th>
                            <th class="px-4 py-3">Category</th>
                            <th class="px-4 py-3">Package Name</th>
                            <th class="px-4 py-3">Events</th>
                            <th class="px-4 py-3">Price (RM)</th>
                            <th class="px-4 py-3">Description</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if ($packages && $packages->num_rows > 0): ?>
                            <?php while($row = $packages->fetch_assoc()): ?>
                                <tr class="hover:bg-slate-50 transition">
                                    <td class="px-4 py-3 font-mono font-bold text-indigo-600">
                                        #<?= $row['sort_order'] ?? 0; ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="px-2 py-0.5 rounded font-bold text-[10px] <?= ($row['form_category'] ?? 'wedding') === 'corporate' ? 'bg-blue-50 text-blue-700 border border-blue-100' : 'bg-pink-50 text-pink-700 border border-pink-100'; ?>">
                                            <?= ($row['form_category'] ?? 'wedding') === 'corporate' ? 'Corporate' : 'Wedding'; ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 font-bold text-slate-900"><?= htmlspecialchars($row['package_name']); ?></td>
                                    <td class="px-4 py-3 font-medium"><?= $row['event_count'] ?? 1; ?> Event(s)</td>
                                    <td class="px-4 py-3 font-mono font-bold">RM <?= number_format($row['base_price'] ?? 0, 2); ?></td>
                                    <td class="px-4 py-3 text-slate-500 max-w-xs truncate"><?= htmlspecialchars($row['description'] ?? ''); ?></td>
                                    <td class="px-4 py-3">
                                        <span class="px-2 py-0.5 rounded font-bold text-[10px] <?= (!isset($row['is_active']) || $row['is_active'] == 1) ? 'bg-emerald-50 text-emerald-700 border border-emerald-100' : 'bg-rose-50 text-rose-700 border border-rose-100'; ?>">
                                            <?= (!isset($row['is_active']) || $row['is_active'] == 1) ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right space-x-1 whitespace-nowrap">
                                        <a href="packages.php?edit_id=<?= $row['id']; ?>#package-form" class="px-2 py-1 bg-amber-50 text-amber-700 border border-amber-200 rounded font-semibold hover:bg-amber-100 inline-block">✏️ Edit</a>
                                        <a href="api/delete_package.php?id=<?= $row['id']; ?>" onclick="return confirm('Delete this package?');" class="px-2 py-1 bg-rose-50 text-rose-700 border border-rose-200 rounded font-semibold hover:bg-rose-100 inline-block">🗑️ Delete</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="8" class="p-6 text-center text-slate-400">No packages recorded yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

    </main>
</body>
</html>