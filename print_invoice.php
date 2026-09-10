<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/db.php';

$invoice_id = $_GET['id'] ?? null;
$doc_type   = $_GET['type'] ?? 'invoice';

if (!$invoice_id) {
    die("ID Invois/Quotation tidak sah.");
}

// Selaraskan struktur jadual events
$conn->query("ALTER TABLE events MODIFY COLUMN client_id BIGINT(20) NOT NULL");
$conn->query("ALTER TABLE events MODIFY COLUMN package_id BIGINT(20) NULL DEFAULT NULL");

// Ensure essential columns exist
$conn->query("ALTER TABLE clients ADD COLUMN IF NOT EXISTS address TEXT NULL");
$conn->query("ALTER TABLE clients ADD COLUMN IF NOT EXISTS ic_number VARCHAR(50) NULL");
$conn->query("ALTER TABLE events ADD COLUMN IF NOT EXISTS event_time TIME NULL");

$conn->query("ALTER TABLE invoices ADD COLUMN IF NOT EXISTS discount_amount DECIMAL(10,2) DEFAULT 0.00");
$conn->query("ALTER TABLE invoices ADD COLUMN IF NOT EXISTS transport_charge DECIMAL(10,2) DEFAULT 0.00");
$conn->query("ALTER TABLE invoices ADD COLUMN IF NOT EXISTS accommodation_charge DECIMAL(10,2) DEFAULT 0.00");
$conn->query("ALTER TABLE invoices ADD COLUMN IF NOT EXISTS created_at DATE NULL");

$conn->query("CREATE TABLE IF NOT EXISTS invoice_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    package_id INT NULL,
    item_name VARCHAR(255) NOT NULL,
    item_description TEXT NULL,
    unit_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    INDEX (invoice_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// PADAM ACARA SPECIFIK DARI INVOIS
if (isset($_GET['action']) && $_GET['action'] === 'delete_event' && isset($_GET['event_id'])) {
    $del_e_id = (int)$_GET['event_id'];
    $conn->query("DELETE FROM events WHERE id = $del_e_id");
    echo "<script>alert('Acara majlis berjaya dipadam!'); window.location.href='print_invoice.php?id={$invoice_id}&type={$doc_type}';</script>";
    exit();
}

// 1. KEMASKINI LENGKAP INVOIS, PELANGGAN, MAJLIS & PAKEJ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_update_invoice'])) {
    
    // A. Kemaskini Maklumat Pelanggan
    $client_id     = (int)($_POST['client_id'] ?? 0);
    $client_name   = trim($_POST['client_name'] ?? '');
    $ic_number     = trim($_POST['ic_number'] ?? '');
    $phone_number  = trim($_POST['phone_number'] ?? '');
    $email         = trim($_POST['email'] ?? '');
    $address       = trim($_POST['address'] ?? '');

    if ($client_id > 0) {
        $updateClient = $conn->prepare("UPDATE clients SET client_name = ?, ic_number = ?, phone_number = ?, email = ?, address = ? WHERE id = ?");
        $updateClient->bind_param("sssssi", $client_name, $ic_number, $phone_number, $email, $address, $client_id);
        $updateClient->execute();
    }

    // B. Kemaskini Maklumat Invois Utama
    $invoice_number = trim($_POST['invoice_number'] ?? '');
    $created_at     = !empty($_POST['created_at']) ? $_POST['created_at'] : date('Y-m-d');
    
    // C. Kemaskini Event Sedia Ada
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

    // D. Tambah Event Baharu
    if (isset($_POST['new_events']) && is_array($_POST['new_events'])) {
        foreach ($_POST['new_events'] as $new_e) {
            $n_title = trim($new_e['title'] ?? '');
            $n_date  = !empty($new_e['date']) ? $new_e['date'] : null;
            $n_time  = !empty($new_e['time']) ? $new_e['time'] : null;
            $n_venue = trim($new_e['venue'] ?? '');

            if (!empty($n_title) && !empty($n_date)) {
                $insEv = $conn->prepare("INSERT INTO events (client_id, package_id, event_title, event_date, event_time, venue_address) VALUES (?, NULL, ?, ?, ?, ?)");
                $insEv->bind_param("issss", $client_id, $n_title, $n_date, $n_time, $n_venue);
                $insEv->execute();
            }
        }
    }

    // E. Kemaskini Pakej Kustom & Harga
    $new_subtotal = 0;
    if (isset($_POST['packages']) && is_array($_POST['packages'])) {
        $conn->query("DELETE FROM invoice_items WHERE invoice_id = $invoice_id");

        foreach ($_POST['packages'] as $pkg_id => $pkg_data) {
            if (isset($pkg_data['selected']) && $pkg_data['selected'] == '1') {
                $pkg_id_int = (int)$pkg_id;
                $custom_price = (float)($pkg_data['price'] ?? 0);
                
                $pkg_query = $conn->query("SELECT package_name, description FROM packages WHERE id = $pkg_id_int");
                if ($pkg_query && $pkg_row = $pkg_query->fetch_assoc()) {
                    $item_name = $pkg_row['package_name'];
                    $item_desc = $pkg_row['description'];

                    $insStmt = $conn->prepare("INSERT INTO invoice_items (invoice_id, package_id, item_name, item_description, unit_price) VALUES (?, ?, ?, ?, ?)");
                    $insStmt->bind_param("iissd", $invoice_id, $pkg_id_int, $item_name, $item_desc, $custom_price);
                    $insStmt->execute();

                    $new_subtotal += $custom_price;
                }
            }
        }
    }

    // F. Diskaun & Caj
    $discount_amount      = (float)($_POST['discount_amount'] ?? 0);
    $transport_charge     = (float)($_POST['transport_charge'] ?? 0);
    $accommodation_charge = (float)($_POST['accommodation_charge'] ?? 0);

    $new_total_amount = $new_subtotal - $discount_amount + $transport_charge + $accommodation_charge;

    $updateStmt = $conn->prepare("UPDATE invoices SET invoice_number = ?, created_at = ?, total_amount = ?, discount_amount = ?, transport_charge = ?, accommodation_charge = ? WHERE id = ?");
    $updateStmt->bind_param("ssddddi", $invoice_number, $created_at, $new_total_amount, $discount_amount, $transport_charge, $accommodation_charge, $invoice_id);
    $updateStmt->execute();

    echo "<script>alert('Semua maklumat invois, pelanggan, majlis & pakej berjaya dikemaskini!'); window.location.href='print_invoice.php?id={$invoice_id}&type={$doc_type}';</script>";
    exit();
}

// 2. TETAPAN REKA BENTUK
$settingsRes = $conn->query("SELECT * FROM settings");
$set = [];
if ($settingsRes) {
    while ($sRow = $settingsRes->fetch_assoc()) {
        $set[$sRow['setting_key']] = $sRow['setting_value'];
    }
}

$themeColor = $set['invoice_theme_color'] ?? '#4f46e5';
$textColor  = $set['invoice_text_color'] ?? '#1e293b';
$fontFamily = $set['invoice_font_family'] ?? 'Inter';
$fontSize   = ($set['invoice_font_size'] ?? '12') . 'px';

// 3. AMBIL DATA INVOIS & CUSTOMER
$stmt = $conn->prepare("SELECT i.*, c.id as client_id, c.client_name, c.ic_number, c.phone_number, c.email, c.address 
                        FROM invoices i 
                        LEFT JOIN clients c ON i.client_id = c.id 
                        WHERE i.id = ?");
$stmt->bind_param("i", $invoice_id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();

if (!$data) {
    die("Data Invois/Quotation tidak ditemui.");
}

$client_id = $data['client_id'];

// Ambil Acara Pelanggan
$events_list = [];
if ($client_id > 0) {
    $evRes = $conn->query("SELECT * FROM events WHERE client_id = $client_id ORDER BY event_date ASC");
    if ($evRes) {
        while ($eRow = $evRes->fetch_assoc()) {
            $events_list[] = $eRow;
        }
    }
}

// Ambil Pakej
$packages_list = [];
$subtotal_packages = 0;

$itemsQuery = $conn->query("SELECT * FROM invoice_items WHERE invoice_id = $invoice_id");
if ($itemsQuery && $itemsQuery->num_rows > 0) {
    while ($item = $itemsQuery->fetch_assoc()) {
        $packages_list[] = [
            'id' => $item['package_id'],
            'package_name' => $item['item_name'],
            'description' => $item['item_description'],
            'base_price' => (float)$item['unit_price']
        ];
        $subtotal_packages += (float)$item['unit_price'];
    }
} else {
    $subtotal_packages = (float)$data['total_amount'] + (float)($data['discount_amount'] ?? 0) - (float)($data['transport_charge'] ?? 0) - (float)($data['accommodation_charge'] ?? 0);
}

// Sejarah Bayaran
$payments_query = $conn->prepare("SELECT * FROM payments WHERE invoice_id = ? ORDER BY payment_date ASC");
$payments_query->bind_param("i", $invoice_id);
$payments_query->execute();
$paymentsHistory = $payments_query->get_result();

$totalPaid = 0;

$discount_amount      = (float)($data['discount_amount'] ?? 0);
$transport_charge     = (float)($data['transport_charge'] ?? 0);
$accommodation_charge = (float)($data['accommodation_charge'] ?? 0);

$grand_total = $subtotal_packages - $discount_amount + $transport_charge + $accommodation_charge;

$all_packages = $conn->query("SELECT * FROM packages WHERE is_active = 1 ORDER BY package_name ASC");
$selected_pkg_ids = array_column($packages_list, 'id');
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <title><?= strtoupper($doc_type); ?> - <?= htmlspecialchars($data['invoice_number']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=<?= urlencode($fontFamily); ?>:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { 
            font-family: '<?= $fontFamily; ?>', sans-serif; 
            font-size: <?= $fontSize; ?>;
            color: <?= $textColor; ?>;
        }
        @media print {
            .no-print { display: none !important; }
            body { background-color: #ffffff; padding: 0; }
            .print-border { border: none !important; box-shadow: none !important; }
        }
    </style>
</head>
<body class="bg-slate-100 min-h-screen py-8">

    <div class="max-w-3xl mx-auto bg-white p-8 rounded-2xl border border-slate-200 shadow-xl print-border relative">
        
        <div class="no-print mb-6 p-3 bg-slate-900 text-white rounded-xl flex items-center justify-between shadow-md">
            <div class="flex items-center gap-2 text-xs">
                <span class="text-amber-400 font-bold">⚙️ Panel Admin:</span>
                <span>Kemaskini Customer, IC, Alamat, Events, Pakej & Harga.</span>
            </div>
            <div class="flex items-center gap-2">
                <button onclick="openEditModal()" class="bg-amber-500 hover:bg-amber-600 text-slate-900 font-bold px-3.5 py-1.5 rounded-lg text-xs transition">
                    ✏️ Edit Semua Maklumat
                </button>
                <button onclick="window.print()" class="bg-indigo-600 hover:bg-indigo-500 text-white font-bold px-3.5 py-1.5 rounded-lg text-xs transition">
                    🖨️ Cetak / PDF
                </button>
            </div>
        </div>

        <!-- HEADER COMPANY & INVOICE -->
        <div class="flex justify-between items-start border-b pb-6 mb-6">
            <div class="flex items-center gap-4">
                <?php if(!empty($set['company_logo'])): ?>
                    <img src="<?= $set['company_logo']; ?>" class="h-16 object-contain">
                <?php endif; ?>
                <div>
                    <h2 class="text-base font-bold text-slate-900"><?= htmlspecialchars($set['company_name'] ?? ''); ?></h2>
                    <p class="text-[10px] text-slate-500"><?= htmlspecialchars($set['company_reg_no'] ?? ''); ?></p>
                    <p class="text-[10px] text-slate-500 whitespace-pre-line"><?= htmlspecialchars($set['company_address'] ?? ''); ?></p>
                    <p class="text-[10px] text-slate-500">📞 <?= htmlspecialchars($set['company_phone'] ?? ''); ?> | ✉️ <?= htmlspecialchars($set['company_email'] ?? ''); ?></p>
                </div>
            </div>

            <div class="text-right">
                <h1 class="text-2xl font-black uppercase tracking-tight" style="color: <?= $themeColor; ?>;">
                    <?= $doc_type === 'quotation' ? 'SEBUT HARGA' : 'INVOIS'; ?>
                </h1>
                <p class="font-mono font-bold text-xs mt-1" style="color: <?= $themeColor; ?>;"><?= htmlspecialchars($data['invoice_number']); ?></p>
                <p class="text-[10px] text-slate-400 mt-1">Tarikh: <span class="font-semibold text-slate-700"><?= !empty($data['created_at']) ? date('d/m/Y', strtotime($data['created_at'])) : date('d/m/Y'); ?></span></p>
            </div>
        </div>

        <!-- BUTIRAN CUSTOMER & MULTI-EVENTS -->
        <div class="grid grid-cols-2 gap-6 mb-6">
            <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Maklumat Customer:</p>
                <h3 class="font-bold text-slate-900 text-sm"><?= htmlspecialchars($data['client_name'] ?? 'Pelanggan'); ?></h3>
                <?php if(!empty($data['ic_number'])): ?>
                    <p class="text-slate-600 font-mono">IC/Pasport: <?= htmlspecialchars($data['ic_number']); ?></p>
                <?php endif; ?>
                <p class="text-slate-600">📞 <?= htmlspecialchars($data['phone_number'] ?? '-'); ?></p>
                <p class="text-slate-600">✉️ <?= htmlspecialchars($data['email'] ?? '-'); ?></p>
                <?php if(!empty($data['address'])): ?>
                    <p class="text-slate-600 mt-1">📍 <?= htmlspecialchars($data['address']); ?></p>
                <?php endif; ?>
            </div>

            <div class="bg-slate-50 p-4 rounded-xl border border-slate-100 space-y-2">
                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Senarai Acara / Majlis:</p>
                <?php if (!empty($events_list)): ?>
                    <?php foreach($events_list as $ev): ?>
                        <div class="border-b border-slate-200/60 pb-1.5 last:border-0 last:pb-0">
                            <p class="font-bold text-slate-900"><?= htmlspecialchars($ev['event_title']); ?></p>
                            <p class="text-slate-600">
                                📅 <?= !empty($ev['event_date']) ? date('d/m/Y', strtotime($ev['event_date'])) : '-'; ?>
                                <?= !empty($ev['event_time']) ? ' • ⏰ ' . date('h:i A', strtotime($ev['event_time'])) : ''; ?>
                            </p>
                            <p class="text-slate-600">📍 Lokasi: <?= htmlspecialchars($ev['venue_address'] ?? '-'); ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-slate-400 italic">Tiada acara majlis direkodkan.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- JADUAL PAKEJ & SERVIS -->
        <table class="w-full text-left border-collapse mb-6">
            <thead>
                <tr class="border-b text-slate-400 text-[10px] font-bold uppercase tracking-wider">
                    <th class="py-2.5">Pakej & Keterangan Perkhidmatan</th>
                    <th class="py-2.5 text-right">Harga (RM)</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (!empty($packages_list)): ?>
                    <?php foreach($packages_list as $pkg): ?>
                        <tr>
                            <td class="py-3">
                                <p class="font-bold text-slate-900"><?= htmlspecialchars($pkg['package_name']); ?></p>
                                <p class="text-[11px] text-slate-500 mt-0.5 whitespace-pre-line"><?= htmlspecialchars($pkg['description'] ?? 'Liputan fotografi profesional studio'); ?></p>
                            </td>
                            <td class="py-3 text-right font-mono font-bold text-slate-800">
                                RM <?= number_format($pkg['base_price'], 2); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td class="py-3">
                            <p class="font-bold text-slate-900">Pakej Servis Fotografi</p>
                            <p class="text-[11px] text-slate-400">Pakej Perkhidmatan Liputan Acara</p>
                        </td>
                        <td class="py-3 text-right font-mono font-bold text-slate-800">
                            RM <?= number_format($subtotal_packages, 2); ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- SEJARAH BAYARAN (TAJUK TULISAN DIKECILKAN) -->
        <?php if ($doc_type !== 'quotation'): ?>
            <div class="mb-6 border-t pt-4">
                <h4 class="text-[9px] font-bold uppercase tracking-wider text-slate-400 mb-2">📜 Rekod Bayaran Pelanggan</h4>
                <?php if ($paymentsHistory && $paymentsHistory->num_rows > 0): ?>
                    <table class="w-full text-left border-collapse text-[10px]">
                        <thead>
                            <tr class="bg-slate-50 text-slate-500 font-bold">
                                <th class="p-1.5">Tarikh</th>
                                <th class="p-1.5">Jenis</th>
                                <th class="p-1.5">Kaedah / Ref</th>
                                <th class="p-1.5 text-right">Jumlah Dibayar</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php while($p = $paymentsHistory->fetch_assoc()): 
                                $totalPaid += (float)$p['amount_paid'];
                            ?>
                                <tr>
                                    <td class="p-1.5"><?= date('d/m/Y', strtotime($p['payment_date'])); ?></td>
                                    <td class="p-1.5 capitalize"><?= htmlspecialchars($p['payment_type']); ?></td>
                                    <td class="p-1.5"><?= htmlspecialchars($p['payment_method']); ?> <?= !empty($p['receipt_ref_no']) ? '('.$p['receipt_ref_no'].')' : ''; ?></td>
                                    <td class="p-1.5 text-right font-mono font-semibold text-emerald-600">+ RM <?= number_format($p['amount_paid'], 2); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="text-[10px] text-slate-400 italic bg-slate-50 p-2 rounded">Belum ada sebarang rekod bayaran direkodkan.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- RINGKASAN HARGA -->
        <?php $balance = $grand_total - $totalPaid; ?>
        <div class="flex justify-end mb-6">
            <div class="w-72 space-y-1.5 border-t pt-3 text-xs">
                <div class="flex justify-between text-slate-600">
                    <span>Subtotal Pakej:</span>
                    <span class="font-mono font-semibold text-slate-800">RM <?= number_format($subtotal_packages, 2); ?></span>
                </div>

                <?php if ($discount_amount > 0): ?>
                    <div class="flex justify-between text-rose-600">
                        <span>Diskaun:</span>
                        <span class="font-mono font-semibold">- RM <?= number_format($discount_amount, 2); ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($transport_charge > 0): ?>
                    <div class="flex justify-between text-slate-600">
                        <span>Caj Transport:</span>
                        <span class="font-mono font-semibold">+ RM <?= number_format($transport_charge, 2); ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($accommodation_charge > 0): ?>
                    <div class="flex justify-between text-slate-600">
                        <span>Caj Penginapan (Accommodation):</span>
                        <span class="font-mono font-semibold">+ RM <?= number_format($accommodation_charge, 2); ?></span>
                    </div>
                <?php endif; ?>

                <div class="flex justify-between text-slate-900 font-bold border-t pt-2 text-sm">
                    <span>Jumlah Keseluruhan:</span>
                    <span class="font-mono text-indigo-600">RM <?= number_format($grand_total, 2); ?></span>
                </div>

                <?php if ($doc_type !== 'quotation'): ?>
                    <div class="flex justify-between text-emerald-600 font-medium">
                        <span>Telah Dibayar:</span>
                        <span class="font-mono font-bold">RM <?= number_format($totalPaid, 2); ?></span>
                    </div>
                    <div class="flex justify-between text-slate-900 font-bold border-t pt-2 text-sm">
                        <span>Baki Tunggakan:</span>
                        <span class="font-mono <?= $balance > 0 ? 'text-rose-600' : 'text-emerald-600'; ?>">
                            RM <?= number_format($balance, 2); ?>
                        </span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="border-t pt-4">
            <h4 class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Terma & Syarat (Terms & Conditions):</h4>
            <div class="text-[10px] text-slate-500 whitespace-pre-line leading-relaxed">
                <?= htmlspecialchars($set['invoice_terms'] ?? '1. Bayaran deposit tidak akan dipulangkan sekiranya berlaku pembatalan.'); ?>
            </div>
        </div>

    </div>

    <!-- MODAL EDIT ADMIN -->
    <div id="editInvoiceModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center z-50 p-4 no-print">
        <div class="bg-white rounded-2xl max-w-xl w-full p-6 shadow-2xl border border-slate-200 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-4 border-b pb-3">
                <h3 class="text-sm font-bold text-slate-900">✏️ Kemaskini Invois, Customer, Event & Pakej</h3>
                <button onclick="closeEditModal()" class="text-slate-400 hover:text-slate-600 font-bold text-lg">&times;</button>
            </div>

            <form method="POST" class="space-y-4 text-xs">
                <input type="hidden" name="action_update_invoice" value="1">
                <input type="hidden" name="client_id" value="<?= $data['client_id']; ?>">

                <!-- 1. MAKLUMAT INVOIS UTAMA -->
                <div class="bg-slate-50 p-3.5 rounded-xl border border-slate-200 space-y-2">
                    <h4 class="font-bold text-indigo-600 uppercase text-[10px]">1. Maklumat Invois Utama</h4>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">No. Invois *</label>
                            <input type="text" name="invoice_number" value="<?= htmlspecialchars($data['invoice_number']); ?>" required class="w-full border p-2 rounded-lg bg-white font-mono font-bold">
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Tarikh Dikeluarkan</label>
                            <input type="date" name="created_at" value="<?= !empty($data['created_at']) ? date('Y-m-d', strtotime($data['created_at'])) : date('Y-m-d'); ?>" class="w-full border p-2 rounded-lg bg-white">
                        </div>
                    </div>
                </div>

                <!-- 2. EDIT MAKLUMAT CUSTOMER -->
                <div class="bg-slate-50 p-3.5 rounded-xl border border-slate-200 space-y-3">
                    <h4 class="font-bold text-indigo-600 uppercase text-[10px]">2. Maklumat Customer</h4>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Nama Customer *</label>
                            <input type="text" name="client_name" value="<?= htmlspecialchars($data['client_name'] ?? ''); ?>" required class="w-full border p-2 rounded-lg bg-white">
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">No. IC / Pasport</label>
                            <input type="text" name="ic_number" value="<?= htmlspecialchars($data['ic_number'] ?? ''); ?>" class="w-full border p-2 rounded-lg bg-white">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">No. Telefon *</label>
                            <input type="text" name="phone_number" value="<?= htmlspecialchars($data['phone_number'] ?? ''); ?>" required class="w-full border p-2 rounded-lg bg-white">
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">E-mel Customer</label>
                            <input type="email" name="email" value="<?= htmlspecialchars($data['email'] ?? ''); ?>" class="w-full border p-2 rounded-lg bg-white">
                        </div>
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Alamat Customer</label>
                        <textarea name="address" rows="2" class="w-full border p-2 rounded-lg bg-white"><?= htmlspecialchars($data['address'] ?? ''); ?></textarea>
                    </div>
                </div>

                <!-- 3. EDIT & TAMBAH EVENT MAJLIS -->
                <div class="bg-slate-50 p-3.5 rounded-xl border border-slate-200 space-y-3">
                    <div class="flex justify-between items-center">
                        <h4 class="font-bold text-indigo-600 uppercase text-[10px]">3. Senarai Acara, Masa & Lokasi Majlis</h4>
                        <button type="button" onclick="addNewEventRow()" class="px-2 py-1 bg-indigo-600 text-white rounded font-bold text-[10px] hover:bg-indigo-700">
                            ➕ Tambah Acara
                        </button>
                    </div>
                    
                    <?php if(!empty($events_list)): ?>
                        <?php foreach($events_list as $ev): ?>
                            <div class="p-2.5 bg-white rounded-lg border border-slate-200 space-y-2 relative">
                                <div class="flex justify-between items-center">
                                    <span class="font-bold text-slate-400 text-[10px]">ID Acara: #<?= $ev['id']; ?></span>
                                    <a href="print_invoice.php?id=<?= $invoice_id; ?>&type=<?= $doc_type; ?>&action=delete_event&event_id=<?= $ev['id']; ?>" 
                                       onclick="return confirm('Adakah anda pasti ingin memadam acara majlis ini?');" 
                                       class="text-rose-600 font-bold text-[10px] hover:underline">
                                       🗑️ Padam Acara
                                    </a>
                                </div>
                                <div class="grid grid-cols-3 gap-2">
                                    <input type="text" name="events[<?= $ev['id']; ?>][title]" value="<?= htmlspecialchars($ev['event_title']); ?>" placeholder="Jenis Majlis" class="border p-1.5 rounded font-bold">
                                    <input type="date" name="events[<?= $ev['id']; ?>][date]" value="<?= $ev['event_date']; ?>" class="border p-1.5 rounded">
                                    <input type="time" name="events[<?= $ev['id']; ?>][time]" value="<?= $ev['event_time'] ?? ''; ?>" class="border p-1.5 rounded">
                                </div>
                                <input type="text" name="events[<?= $ev['id']; ?>][venue]" value="<?= htmlspecialchars($ev['venue_address'] ?? ''); ?>" placeholder="Lokasi Majlis" class="w-full border p-1.5 rounded">
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <div id="dynamicEventsContainer" class="space-y-2"></div>
                </div>

                <!-- 4. PILIHAN MULTI-PAKEJ & TUKAR HARGA -->
                <div class="bg-slate-50 p-3.5 rounded-xl border border-slate-200 space-y-2">
                    <h4 class="font-bold text-indigo-600 uppercase text-[10px]">4. Pilihan Pakej & Tukar Harga Kustom</h4>
                    <div class="space-y-2 max-h-48 overflow-y-auto border p-2.5 rounded-lg bg-white">
                        <?php if($all_packages && $all_packages->num_rows > 0): ?>
                            <?php while($p = $all_packages->fetch_assoc()): 
                                $is_checked = in_array($p['id'], $selected_pkg_ids);
                                $current_custom_price = $p['base_price'];
                                foreach ($packages_list as $existing_pkg) {
                                    if ($existing_pkg['id'] == $p['id']) {
                                        $current_custom_price = $existing_pkg['base_price'];
                                        break;
                                    }
                                }
                            ?>
                                <div class="p-2 bg-slate-50 rounded border border-slate-200 flex items-center justify-between gap-3">
                                    <label class="flex items-center gap-2 cursor-pointer flex-1">
                                        <input type="checkbox" name="packages[<?= $p['id']; ?>][selected]" value="1" <?= $is_checked ? 'checked' : ''; ?> class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                        <div>
                                            <div class="font-bold text-slate-800"><?= htmlspecialchars($p['package_name']); ?></div>
                                            <div class="text-[10px] text-slate-400">Harga Asal: RM <?= number_format($p['base_price'], 2); ?></div>
                                        </div>
                                    </label>
                                    <div class="flex items-center gap-1">
                                        <span class="text-slate-400 font-bold">RM</span>
                                        <input type="number" step="0.01" name="packages[<?= $p['id']; ?>][price]" value="<?= number_format($current_custom_price, 2, '.', ''); ?>" class="w-24 border p-1 rounded font-mono font-bold text-right bg-white">
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 5. DISKAUN & CAJ -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 bg-slate-50 p-3.5 rounded-xl border border-slate-200">
                    <div>
                        <label class="block font-bold text-rose-600 mb-1">Diskaun (RM)</label>
                        <input type="number" step="0.01" name="discount_amount" value="<?= number_format($discount_amount, 2, '.', ''); ?>" class="w-full border p-2 rounded-lg font-mono bg-white">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Transport (RM)</label>
                        <input type="number" step="0.01" name="transport_charge" value="<?= number_format($transport_charge, 2, '.', ''); ?>" class="w-full border p-2 rounded-lg font-mono bg-white">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Penginapan (RM)</label>
                        <input type="number" step="0.01" name="accommodation_charge" value="<?= number_format($accommodation_charge, 2, '.', ''); ?>" class="w-full border p-2 rounded-lg font-mono bg-white">
                    </div>
                </div>

                <div class="pt-3 border-t flex justify-end gap-2">
                    <button type="button" onclick="closeEditModal()" class="px-4 py-2 bg-slate-100 text-slate-700 font-bold rounded-lg hover:bg-slate-200">Batal</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-lg shadow">💾 Simpan Semua Kemaskini</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let newEventCount = 0;

        function openEditModal() {
            document.getElementById('editInvoiceModal').classList.remove('hidden');
            document.getElementById('editInvoiceModal').classList.add('flex');
        }

        function closeEditModal() {
            document.getElementById('editInvoiceModal').classList.add('hidden');
            document.getElementById('editInvoiceModal').classList.remove('flex');
        }

        function addNewEventRow() {
            newEventCount++;
            const container = document.getElementById('dynamicEventsContainer');
            const div = document.createElement('div');
            div.className = 'p-2.5 bg-indigo-50/70 rounded-lg border border-indigo-200 space-y-2 relative';
            div.innerHTML = `
                <div class="flex justify-between items-center">
                    <span class="font-bold text-indigo-900 text-[10px]">Acara Majlis Baharu #${newEventCount}</span>
                    <button type="button" onclick="this.parentElement.parentElement.remove()" class="text-rose-600 font-bold text-xs">&times; Padam</button>
                </div>
                <div class="grid grid-cols-3 gap-2">
                    <input type="text" name="new_events[${newEventCount}][title]" placeholder="Jenis Majlis" required class="border p-1.5 rounded bg-white font-bold">
                    <input type="date" name="new_events[${newEventCount}][date]" required class="border p-1.5 rounded bg-white">
                    <input type="time" name="new_events[${newEventCount}][time]" class="border p-1.5 rounded bg-white">
                </div>
                <input type="text" name="new_events[${newEventCount}][venue]" placeholder="Lokasi / Alamat Majlis" class="w-full border p-1.5 rounded bg-white">
            `;
            container.appendChild(div);
        }
    </script>

</body>
</html>