<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../booking.php");
    exit();
}

// 1. Pembersihan & Dapatkan Input Borang
function clean_input($data) {
    return htmlspecialchars(trim($data ?? ''), ENT_QUOTES, 'UTF-8');
}

$form_type      = clean_input($_POST['form_type'] ?? 'wedding');
$client_name    = clean_input($_POST['client_name'] ?? '');
$phone_number   = clean_input($_POST['phone_number'] ?? '');
$emergency_phone= clean_input($_POST['emergency_phone'] ?? '');
$email          = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL) ? trim($_POST['email']) : '';
$partner_name   = clean_input($_POST['partner_name'] ?? '');
$company_name   = clean_input($_POST['company_name'] ?? '');
$stamping_name  = clean_input($_POST['stamping_name'] ?? '');
$postage_address= clean_input($_POST['postage_address'] ?? '');
$billing_address= clean_input($_POST['billing_address'] ?? '');
$address        = !empty($postage_address) ? $postage_address : $billing_address;
$remarks        = clean_input($_POST['remarks'] ?? '');
$signature_data = $_POST['signature_data'] ?? '';

if (empty($client_name) || empty($phone_number)) {
    die("Sila lengkapkan maklumat mandatori yang diperlukan.");
}

if ($form_type === 'wedding' && !empty($partner_name)) {
    $full_client_title = $client_name . " & " . $partner_name;
} else if ($form_type === 'corporate' && !empty($company_name)) {
    $full_client_title = $company_name . " (PIC: " . $client_name . ")";
} else {
    $full_client_title = $client_name;
}

// 2. Pengiraan Pakej Terpilih & Jumlah Harga
$selected_package_ids = $_POST['package_ids'] ?? [];
$total_amount = 0.00;
$packages_list = [];

if (!empty($selected_package_ids)) {
    $ids_clean = array_map('intval', $selected_package_ids);
    $ids_string = implode(',', $ids_clean);
    
    $pkgQuery = $conn->query("SELECT * FROM packages WHERE id IN ($ids_string)");
    if ($pkgQuery) {
        while ($pkg = $pkgQuery->fetch_assoc()) {
            $price = (float)$pkg['base_price'];
            $total_amount += $price;
            $packages_list[] = $pkg;
        }
    }
}

// 3. Simpan Rekod Ke Jadual `clients`
$stmtClient = $conn->prepare("INSERT INTO clients (client_type, client_name, ic_or_roc, phone_number, email, address, notes) VALUES (?, ?, '', ?, ?, ?, ?)");
$client_type_enum = ($form_type === 'corporate') ? 'company' : 'individual';
$stmtClient->bind_param("ssssss", $client_type_enum, $full_client_title, $phone_number, $email, $address, $remarks);
$stmtClient->execute();
$client_id = $stmtClient->insert_id;

// 4. Simpan Rekod Ke Jadual `bookings`
$stmtBooking = $conn->prepare("INSERT INTO bookings (form_type, client_name, partner_name, company_name, phone_number, emergency_phone, email, stamping_name, postage_address, billing_address, remarks, signature_data, status, payment_status, total_price, is_deleted) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Baru', 'pending', ?, 0)");
$stmtBooking->bind_param("ssssssssssssd", $form_type, $client_name, $partner_name, $company_name, $phone_number, $emergency_phone, $email, $stamping_name, $postage_address, $billing_address, $remarks, $signature_data, $total_amount);
$stmtBooking->execute();
$booking_id = $stmtBooking->insert_id;

// 5. Simpan Item Pakej Terpilih
if (!empty($packages_list)) {
    $stmtPkgItem = $conn->prepare("INSERT INTO booking_packages (booking_id, package_id) VALUES (?, ?)");
    foreach ($packages_list as $pkg) {
        $p_id = (int)$pkg['id'];
        $stmtPkgItem->bind_param("ii", $booking_id, $p_id);
        $stmtPkgItem->execute();
    }
}

// 6. Simpan Acara
$first_event_id = NULL;
if (isset($_POST['events']) && is_array($_POST['events'])) {
    $stmtBEvent = $conn->prepare("INSERT INTO booking_events (booking_id, event_number, event_date, event_time, event_address) VALUES (?, ?, ?, ?, ?)");
    $stmtEvent  = $conn->prepare("INSERT INTO events (client_id, package_id, event_title, event_date, event_time, venue_address, status) VALUES (?, NULL, ?, ?, ?, ?, 'booked')");

    foreach ($_POST['events'] as $idx => $ev) {
        $e_date_raw  = trim($ev['date'] ?? '');
        $e_time_raw  = trim($ev['time'] ?? '');
        $e_venue     = clean_input($ev['address'] ?? '');
        
        $e_date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $e_date_raw) ? $e_date_raw : date('Y-m-d');
        $e_time = preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $e_time_raw) ? $e_time_raw : '00:00:00';
        $event_num = (int)$idx;
        
        $stmtBEvent->bind_param("iisss", $booking_id, $event_num, $e_date, $e_time, $e_venue);
        $stmtBEvent->execute();

        $e_title = ($form_type === 'wedding') ? "Majlis #" . $event_num : "Acara Korporat #" . $event_num;
        $stmtEvent->bind_param("issss", $client_id, $e_title, $e_date, $e_time, $e_venue);
        $stmtEvent->execute();

        if ($first_event_id === NULL) {
            $first_event_id = $stmtEvent->insert_id;
        }
    }
}

// 7. Auto Generate Invois
$invoice_number = "INV-" . date('Ymd') . "-" . str_pad($booking_id, 3, "0", STR_PAD_LEFT);
$created_at     = date('Y-m-d H:i:s');

$stmtInv = $conn->prepare("INSERT INTO invoices (invoice_number, client_id, event_id, total_amount, status, created_at, discount_amount, transport_charge, accommodation_charge) VALUES (?, ?, ?, ?, 'unpaid', ?, 0.00, 0.00, 0.00)");
$stmtInv->bind_param("siids", $invoice_number, $client_id, $first_event_id, $total_amount, $created_at);
$stmtInv->execute();
$invoice_id = $stmtInv->insert_id;

if (!empty($packages_list)) {
    $stmtInvItem = $conn->prepare("INSERT INTO invoice_items (invoice_id, package_id, item_name, item_description, unit_price) VALUES (?, ?, ?, ?, ?)");
    foreach ($packages_list as $pkg) {
        $p_id    = (int)$pkg['id'];
        $p_name  = $pkg['package_name'];
        $p_desc  = $pkg['description'] ?? '';
        $p_price = (float)$pkg['base_price'];

        $stmtInvItem->bind_param("iissd", $invoice_id, $p_id, $p_name, $p_desc, $p_price);
        $stmtInvItem->execute();
    }
}

// 8. SEKATAN: Simpan Kuki & Sesi Bahawa Pelanggan Dah Hantar
setcookie("form_submitted_" . $form_type, "1", time() + (86400 * 30), "/"); // Kuki tahan 30 hari
$_SESSION["submitted_" . $form_type] = true;

// Lencongkan Ke Halaman Berjaya mengikut jenis borang
header("Location: ../booking_success.php?type=" . $form_type);
exit();