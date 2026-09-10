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

// INVOICE & BOOKING QUERY (Retrieve active / non-deleted clients only)
$invoices_query = "SELECT 
                    i.id as invoice_id,
                    i.invoice_number,
                    i.total_amount as total_price,
                    c.client_name,
                    c.phone_number,
                    c.email,
                    e.event_date,
                    e.id as event_id,
                    p_summary.total_paid
                   FROM invoices i
                   LEFT JOIN clients c ON i.client_id = c.id
                   LEFT JOIN events e ON i.event_id = e.id
                   LEFT JOIN (
                       SELECT invoice_id, SUM(amount_paid) as total_paid 
                       FROM payments 
                       GROUP BY invoice_id
                   ) p_summary ON i.id = p_summary.invoice_id
                   WHERE (c.is_deleted = 0 OR c.is_deleted IS NULL)
                   ORDER BY i.id DESC";

$invoices_result = $conn->query($invoices_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Studio Manager - Dashboard</title>

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

    <!-- Main Body Content -->
    <main class="max-w-[95rem] mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

        <!-- Quick Access Modules -->
        <div class="bg-white p-5 rounded-xl border border-slate-200/80 shadow-sm">
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3">Quick Access Modules</h3>
            <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
                <button onclick="openBookingModal()" class="p-3 bg-slate-50 hover:bg-indigo-50 hover:border-indigo-200 border border-slate-200 rounded-lg text-slate-700 hover:text-indigo-700 transition flex items-center gap-2.5 font-medium text-xs">
                    <span>➕</span> Add Booking
                </button>
                <a href="payments.php" class="p-3 bg-slate-50 hover:bg-emerald-50 hover:border-emerald-200 border border-slate-200 rounded-lg text-slate-700 hover:text-emerald-700 transition flex items-center gap-2.5 font-medium text-xs">
                    <span>💳</span> Payment Module
                </a>
                <a href="reports.php" class="p-3 bg-indigo-50 hover:bg-indigo-100 hover:border-indigo-300 border border-indigo-200 rounded-lg text-indigo-700 font-bold transition flex items-center gap-2.5 text-xs">
                    <span>📊</span> Reports Center
                </a>
                <a href="crew.php" class="p-3 bg-slate-50 hover:bg-purple-50 hover:border-purple-200 border border-slate-200 rounded-lg text-slate-700 hover:text-purple-700 transition flex items-center gap-2.5 font-medium text-xs">
                    <span>👤</span> Crew Management
                </a>
                <a href="tasks.php" class="p-3 bg-slate-50 hover:bg-amber-50 hover:border-amber-200 border border-slate-200 rounded-lg text-slate-700 hover:text-amber-700 transition flex items-center gap-2.5 font-medium text-xs">
                    <span>📋</span> Tasks & Projects
                </a>
            </div>
        </div>

        <!-- Invoices & Client Bookings Table -->
        <div class="bg-white rounded-xl border border-slate-200/80 shadow-sm overflow-hidden">
            <div class="p-5 border-b border-slate-100 flex flex-col md:flex-row md:items-center justify-between gap-4 bg-slate-50/50">
                <div>
                    <h2 class="text-sm font-semibold text-slate-900 tracking-tight">🧾 Invoices & Client Bookings</h2>
                    <p class="text-xs text-slate-500">Use search and filter options below to organize invoice records.</p>
                </div>

                <div class="flex flex-wrap items-center gap-2.5">
                    <div class="relative min-w-[240px]">
                        <input type="text" id="invoiceSearchInput" onkeyup="filterInvoices()" placeholder="Search name, phone, or ID..."
                            class="w-full text-xs px-3 py-2 pl-8 border border-slate-200 rounded-lg bg-white text-slate-800 focus:outline-none focus:border-indigo-500 transition shadow-sm">
                        <span class="absolute left-2.5 top-2 text-xs text-slate-400">🔍</span>
                    </div>

                    <select id="statusFilter" onchange="filterInvoices()" class="text-xs px-2.5 py-2 border border-slate-200 rounded-lg bg-white text-slate-700 focus:outline-none focus:border-indigo-500 transition shadow-sm">
                        <option value="ALL">All Statuses</option>
                        <option value="PAID">Fully Paid</option>
                        <option value="DEPOSIT">Deposit Paid</option>
                        <option value="PENDING">Pending Payment</option>
                    </select>

                    <select id="sortOrder" onchange="sortInvoices()" class="text-xs px-2.5 py-2 border border-slate-200 rounded-lg bg-white text-slate-700 focus:outline-none focus:border-indigo-500 transition shadow-sm">
                        <option value="date_desc">Date (Newest)</option>
                        <option value="date_asc">Date (Oldest)</option>
                        <option value="amount_desc">Amount (Highest)</option>
                        <option value="amount_asc">Amount (Lowest)</option>
                    </select>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left text-slate-600" id="invoicesTable">
                    <thead class="text-[10px] font-semibold uppercase text-slate-400 bg-slate-50 border-b border-slate-100">
                        <tr>
                            <th class="px-4 py-3">Invoice & Client</th>
                            <th class="px-4 py-3">Event Date</th>
                            <th class="px-4 py-3">Total (RM)</th>
                            <th class="px-4 py-3">Paid (RM)</th>
                            <th class="px-4 py-3">Balance (RM)</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100" id="invoicesTbody">
                        <?php if ($invoices_result && $invoices_result->num_rows > 0): ?>
                            <?php while($row = $invoices_result->fetch_assoc()): 
                                $total = (float)($row['total_price'] ?? 0);
                                $paid = (float)($row['total_paid'] ?? 0);
                                $balance = $total - $paid;

                                $status_badge = '<span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-rose-50 text-rose-700 border border-rose-200">PENDING</span>';
                                $status_text = 'PENDING';
                                if ($balance <= 0 && $total > 0) {
                                    $status_badge = '<span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">PAID</span>';
                                    $status_text = 'PAID';
                                } elseif ($paid > 0) {
                                    $status_badge = '<span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-amber-50 text-amber-700 border border-amber-200">DEPOSIT</span>';
                                    $status_text = 'DEPOSIT';
                                }

                                $cust_name = !empty($row['client_name']) ? $row['client_name'] : 'Unnamed Client';
                                $cust_phone = !empty($row['phone_number']) ? $row['phone_number'] : '-';
                                $inv_no = !empty($row['invoice_number']) ? $row['invoice_number'] : '#INV-' . str_pad($row['invoice_id'], 5, '0', STR_PAD_LEFT);
                                
                                $raw_date = $row['event_date'] ?? '';
                                $event_date = !empty($raw_date) ? date('d/m/Y', strtotime($raw_date)) : '-';
                            ?>
                                <tr class="hover:bg-slate-50/80 transition invoice-row" 
                                    data-id="<?= strtolower($inv_no); ?>"
                                    data-name="<?= strtolower(htmlspecialchars($cust_name)); ?>"
                                    data-phone="<?= htmlspecialchars($cust_phone); ?>"
                                    data-status="<?= $status_text; ?>"
                                    data-date="<?= $raw_date; ?>"
                                    data-amount="<?= $total; ?>">
                                    
                                    <td class="px-4 py-3">
                                        <div class="font-semibold text-slate-900"><?= htmlspecialchars($cust_name); ?></div>
                                        <div class="text-[10px] text-slate-400 flex items-center gap-2 mt-0.5">
                                            <span class="font-mono text-indigo-600 font-semibold"><?= htmlspecialchars($inv_no); ?></span>
                                            <span>•</span>
                                            <span>📞 <?= htmlspecialchars($cust_phone); ?></span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap font-medium text-slate-800">
                                        <?= $event_date; ?>
                                    </td>
                                    <td class="px-4 py-3 font-mono font-semibold text-slate-800">
                                        RM <?= number_format($total, 2); ?>
                                    </td>
                                    <td class="px-4 py-3 font-mono font-medium text-emerald-600">
                                        RM <?= number_format($paid, 2); ?>
                                    </td>
                                    <td class="px-4 py-3 font-mono font-medium <?= $balance > 0 ? 'text-rose-600' : 'text-slate-400'; ?>">
                                        RM <?= number_format($balance, 2); ?>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <?= $status_badge; ?>
                                    </td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap space-x-1">
                                        <a href="payments.php?invoice_id=<?= $row['invoice_id']; ?>" class="px-2 py-1 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded font-medium text-[11px] hover:bg-emerald-100 transition inline-block">
                                            💳 Payment
                                        </a>
                                        <a href="tasks.php?event_id=<?= $row['event_id']; ?>" class="px-2 py-1 bg-purple-50 text-purple-700 border border-purple-200 rounded font-medium text-[11px] hover:bg-purple-100 transition inline-block">
                                            📋 Tasks
                                        </a>
                                        <a href="print_invoice.php?id=<?= $row['invoice_id']; ?>" target="_blank" class="px-2 py-1 bg-slate-100 text-slate-700 border border-slate-200 rounded font-medium text-[11px] hover:bg-slate-200 transition inline-block">
                                            🖨️ Invoice
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="p-8 text-center text-slate-400">
                                    No invoice/booking records found in the database.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <!-- Booking Type Selection Modal -->
    <div id="bookingModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-100 relative">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-base font-bold text-slate-900">Select Booking Type</h3>
                <button onclick="closeBookingModal()" class="text-slate-400 hover:text-slate-600 font-bold text-lg">&times;</button>
            </div>
            <p class="text-xs text-slate-500 mb-6">Please select the type of booking you would like to register:</p>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <a href="booking.php?type=wedding" class="p-5 border-2 border-slate-100 hover:border-indigo-500 rounded-xl bg-slate-50 hover:bg-indigo-50/50 transition text-center group">
                    <div class="text-3xl mb-2">💍</div>
                    <div class="font-bold text-xs text-slate-800 group-hover:text-indigo-600">Wedding Event</div>
                    <p class="text-[10px] text-slate-400 mt-1">Bride & Groom Form</p>
                </a>

                <a href="booking.php?type=corporate" class="p-5 border-2 border-slate-100 hover:border-indigo-500 rounded-xl bg-slate-50 hover:bg-indigo-50/50 transition text-center group">
                    <div class="text-3xl mb-2">🏢</div>
                    <div class="font-bold text-xs text-slate-800 group-hover:text-indigo-600">Corporate Event</div>
                    <p class="text-[10px] text-slate-400 mt-1">Company / Organization Form</p>
                </a>
            </div>
        </div>
    </div>

    <!-- Modal & Filter Scripts -->
    <script>
        function openBookingModal() {
            document.getElementById('bookingModal').classList.remove('hidden');
            document.getElementById('bookingModal').classList.add('flex');
        }

        function closeBookingModal() {
            document.getElementById('bookingModal').classList.add('hidden');
            document.getElementById('bookingModal').classList.remove('flex');
        }

        function filterInvoices() {
            const query = document.getElementById('invoiceSearchInput').value.toLowerCase().trim();
            const statusValue = document.getElementById('statusFilter').value;
            const rows = document.querySelectorAll('.invoice-row');

            rows.forEach(row => {
                const id = row.getAttribute('data-id');
                const name = row.getAttribute('data-name');
                const phone = row.getAttribute('data-phone').toLowerCase();
                const status = row.getAttribute('data-status');

                const matchesSearch = name.includes(query) || phone.includes(query) || id.includes(query);
                const matchesStatus = (statusValue === 'ALL') || (status === statusValue);

                if (matchesSearch && matchesStatus) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        function sortInvoices() {
            const tbody = document.getElementById('invoicesTbody');
            const rows = Array.from(document.querySelectorAll('.invoice-row'));
            const sortOrder = document.getElementById('sortOrder').value;

            rows.sort((a, b) => {
                if (sortOrder === 'date_desc') {
                    return new Date(b.getAttribute('data-date')) - new Date(a.getAttribute('data-date'));
                } else if (sortOrder === 'date_asc') {
                    return new Date(a.getAttribute('data-date')) - new Date(b.getAttribute('data-date'));
                } else if (sortOrder === 'amount_desc') {
                    return parseFloat(b.getAttribute('data-amount')) - parseFloat(a.getAttribute('data-amount'));
                } else if (sortOrder === 'amount_asc') {
                    return parseFloat(a.getAttribute('data-amount')) - parseFloat(b.getAttribute('data-amount'));
                }
            });

            rows.forEach(row => tbody.appendChild(row));
        }
    </script>

</body>
</html>