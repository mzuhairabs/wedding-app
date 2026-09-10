<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/db.php';

$form_type = isset($_GET['type']) && in_array($_GET['type'], ['wedding', 'corporate']) ? $_GET['type'] : 'wedding';

// SEKATAN: Jika kuki atau sesi mengesahkan pelanggan sudah membuat hantaran borang ini
if (isset($_COOKIE["form_submitted_" . $form_type]) || isset($_SESSION["submitted_" . $form_type])) {
    header("Location: booking_success.php?type=" . $form_type . "&already=1");
    exit();
}

$stmt = $conn->prepare("SELECT * FROM packages WHERE is_active = 1 AND form_category = ? ORDER BY sort_order ASC, base_price ASC");
$stmt->bind_param("s", $form_type);
$stmt->execute();
$packages = $stmt->get_result();

$pkg_json = [];
$is_wedding = ($form_type === 'wedding');
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Borang Tempahan Servis - <?= $is_wedding ? 'Perkahwinan' : 'Korporat'; ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <?php if ($is_wedding): ?>
        <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;1,400&family=Plus+Jakarta+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <?php else: ?>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <?php endif; ?>

    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>

    <style>
        <?php if ($is_wedding): ?>
            body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #faf9f6; color: #2c2a29; }
            .font-serif-fine { font-family: 'Cormorant Garamond', serif; }
        <?php else: ?>
            body { font-family: 'Inter', sans-serif; background-color: #0f172a; color: #f8fafc; }
        <?php endif; ?>
        .custom-scrollbar::-webkit-scrollbar { width: 5px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(156, 163, 175, 0.4); border-radius: 10px; }
    </style>
</head>
<body class="<?= $is_wedding ? 'bg-[#faf9f6] text-stone-800' : 'bg-slate-950 text-slate-100'; ?> p-3 md:p-8 min-h-screen">

    <div class="max-w-3xl mx-auto my-6">
        
        <!-- HEADER FORM -->
        <div class="mb-8 <?= $is_wedding ? 'text-center border-b border-stone-200 pb-8' : 'bg-slate-900 border border-slate-800 p-6 rounded-2xl shadow-xl'; ?>">
            <?php if ($is_wedding): ?>
                <span class="text-xs uppercase tracking-[0.3em] text-stone-400 font-medium block mb-2">Borang Tempahan Rasmi</span>
                <h1 class="text-3xl md:text-4xl font-serif-fine font-normal text-stone-800 tracking-wide">Majlis Perkahwinan</h1>
                <p class="text-xs text-stone-500 mt-2 max-w-md mx-auto italic">Sila lengkapkan butiran di bawah bagi mengesahkan tempahan tarikh dan penyediaan pakej khidmat fotografi anda.</p>
            <?php else: ?>
                <div class="flex items-center space-x-3 mb-2">
                    <div class="w-9 h-9 bg-indigo-600 rounded-lg flex items-center justify-center text-white font-bold text-base shadow-lg shadow-indigo-500/30">
                        🏛️
                    </div>
                    <div>
                        <h1 class="text-xl font-bold tracking-tight text-white">Borang Permohonan Servis Korporat</h1>
                        <p class="text-xs text-slate-400">Portal Pengurusan Tempahan & Penjanaan Invois Rasmi Syarikat</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- CONTAINER FORM UTAMA -->
        <div class="<?= $is_wedding ? 'bg-white/80 backdrop-blur-sm border border-stone-200/80 rounded-2xl p-6 md:p-10 shadow-sm' : 'bg-slate-900/90 border border-slate-800 rounded-2xl p-6 md:p-8 shadow-2xl'; ?>">
            
            <form action="api/submit_booking.php" method="POST" id="bookingForm" class="space-y-8">
                <input type="hidden" name="form_type" value="<?= $form_type; ?>">

                <!-- SECTION 1: MAKLUMAT PELANGGAN -->
                <div class="space-y-5">
                    <div class="<?= $is_wedding ? 'border-b border-stone-200 pb-3' : 'border-b border-slate-800 pb-3 flex items-center justify-between'; ?>">
                        <h2 class="<?= $is_wedding ? 'text-lg font-serif-fine font-semibold text-stone-800' : 'text-xs font-bold uppercase tracking-wider text-indigo-400'; ?>">
                            <?= $is_wedding ? '1. Maklumat Pasangan & Pengantin' : '1. MAKLUMAT ENTITI SYARIKAT & PIC'; ?>
                        </h2>
                    </div>

                    <?php if ($is_wedding): ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div>
                                <label class="block text-[11px] uppercase tracking-wider font-semibold text-stone-500 mb-1.5">Nama Penuh Utama / Pengantin *</label>
                                <input type="text" name="client_name" required placeholder="cth: Muhammad Ali" class="w-full text-xs px-3.5 py-2.5 border border-stone-200 rounded-xl bg-stone-50/30 text-stone-800 focus:outline-none focus:border-stone-400 transition">
                            </div>
                            <div>
                                <label class="block text-[11px] uppercase tracking-wider font-semibold text-stone-500 mb-1.5">Nama Pasangan *</label>
                                <input type="text" name="partner_name" required placeholder="cth: Siti Nurhaliza" class="w-full text-xs px-3.5 py-2.5 border border-stone-200 rounded-xl bg-stone-50/30 text-stone-800 focus:outline-none focus:border-stone-400 transition">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div>
                                <label class="block text-[11px] uppercase tracking-wider font-semibold text-stone-500 mb-1.5">No. WhatsApp Utama *</label>
                                <input type="tel" name="phone_number" required placeholder="0123456789" class="w-full text-xs px-3.5 py-2.5 border border-stone-200 rounded-xl bg-stone-50/30 text-stone-800 focus:outline-none focus:border-stone-400 transition font-mono">
                            </div>
                            <div>
                                <label class="block text-[11px] uppercase tracking-wider font-semibold text-stone-500 mb-1.5">No. Kecemasan (Waris) *</label>
                                <input type="tel" name="emergency_phone" required placeholder="0198765432" class="w-full text-xs px-3.5 py-2.5 border border-stone-200 rounded-xl bg-stone-50/30 text-stone-800 focus:outline-none focus:border-stone-400 transition font-mono">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div>
                                <label class="block text-[11px] uppercase tracking-wider font-semibold text-stone-500 mb-1.5">E-mel Rasmi *</label>
                                <input type="email" name="email" required placeholder="contoh@gmail.com" class="w-full text-xs px-3.5 py-2.5 border border-stone-200 rounded-xl bg-stone-50/30 text-stone-800 focus:outline-none focus:border-stone-400 transition">
                            </div>
                            <div>
                                <label class="block text-[11px] uppercase tracking-wider font-semibold text-stone-500 mb-1.5">Ukiran Stamping Album / Video *</label>
                                <input type="text" name="stamping_name" required placeholder="cth: Ali & Siti" class="w-full text-xs px-3.5 py-2.5 border border-stone-200 rounded-xl bg-stone-50/30 text-stone-800 focus:outline-none focus:border-stone-400 transition">
                            </div>
                        </div>

                        <div>
                            <label class="block text-[11px] uppercase tracking-wider font-semibold text-stone-500 mb-1.5">Alamat Penghantaran Album (Postage) *</label>
                            <textarea name="postage_address" rows="2" required placeholder="Alamat lengkap lokasi penerimaan..." class="w-full text-xs px-3.5 py-2.5 border border-stone-200 rounded-xl bg-stone-50/30 text-stone-800 focus:outline-none focus:border-stone-400 transition"></textarea>
                        </div>
                    <?php else: ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] uppercase font-bold text-slate-400 mb-1">Nama Syarikat / Organisasi *</label>
                                <input type="text" name="company_name" required placeholder="cth: Global Tech Sdn Bhd" class="w-full text-xs px-3 py-2 border border-slate-700 rounded-lg bg-slate-950 text-slate-100 focus:outline-none focus:border-indigo-500 transition">
                            </div>
                            <div>
                                <label class="block text-[10px] uppercase font-bold text-slate-400 mb-1">Nama Pegawai Bertanggungjawab (PIC) *</label>
                                <input type="text" name="client_name" required placeholder="cth: Encik Ahmad" class="w-full text-xs px-3 py-2 border border-slate-700 rounded-lg bg-slate-950 text-slate-100 focus:outline-none focus:border-indigo-500 transition">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] uppercase font-bold text-slate-400 mb-1">No. Telefon PIC *</label>
                                <input type="tel" name="phone_number" required placeholder="0123456789" class="w-full text-xs px-3 py-2 border border-slate-700 rounded-lg bg-slate-950 text-slate-100 focus:outline-none focus:border-indigo-500 font-mono transition">
                            </div>
                            <div>
                                <label class="block text-[10px] uppercase font-bold text-slate-400 mb-1">E-mel Rasmi Syarikat *</label>
                                <input type="email" name="email" required placeholder="pic@syarikat.com" class="w-full text-xs px-3 py-2 border border-slate-700 rounded-lg bg-slate-950 text-slate-100 focus:outline-none focus:border-indigo-500 transition">
                            </div>
                        </div>

                        <div>
                            <label class="block text-[10px] uppercase font-bold text-slate-400 mb-1">Alamat Penagihan Invois (Billing Address) *</label>
                            <textarea name="billing_address" rows="2" required placeholder="Alamat pendaftaran syarikat untuk invois..." class="w-full text-xs px-3 py-2 border border-slate-700 rounded-lg bg-slate-950 text-slate-100 focus:outline-none focus:border-indigo-500 transition"></textarea>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- SECTION 2: PILIHAN PAKEJ -->
                <div class="space-y-4">
                    <div class="<?= $is_wedding ? 'border-b border-stone-200 pb-2' : 'border-b border-slate-800 pb-2'; ?>">
                        <h2 class="<?= $is_wedding ? 'text-lg font-serif-fine font-semibold text-stone-800' : 'text-xs font-bold uppercase tracking-wider text-indigo-400'; ?>">
                            <?= $is_wedding ? '2. Pilihan Pakej Khidmat' : '2. SELEKSI PAKEJ PERKHIDMATAN'; ?>
                        </h2>
                    </div>

                    <div class="<?= $is_wedding ? 'border border-stone-200 rounded-xl p-3 bg-stone-50/50 space-y-2' : 'border border-slate-800 rounded-xl p-3 bg-slate-950/60 space-y-2'; ?> max-h-60 overflow-y-auto custom-scrollbar">
                        <?php if ($packages && $packages->num_rows > 0): ?>
                            <?php while($pkg = $packages->fetch_assoc()): 
                                $pname = strtolower($pkg['package_name']);
                                $event_code = 'sanding'; 

                                if ($form_type === 'corporate') {
                                    $event_code = 'corporate';
                                } else {
                                    if (strpos($pname, 'nikah') !== false || strpos($pname, 'akad') !== false) {
                                        $event_code = 'nikah';
                                    } elseif (strpos($pname, 'sanding') !== false || strpos($pname, 'resepsi') !== false || strpos($pname, 'reception') !== false) {
                                        $event_code = 'sanding';
                                    } elseif (strpos($pname, 'bertandang') !== false || strpos($pname, 'groom') !== false) {
                                        $event_code = 'bertandang';
                                    } elseif (strpos($pname, 'tunang') !== false || strpos($pname, 'engagement') !== false) {
                                        $event_code = 'tunang';
                                    } elseif (strpos($pname, 'inai') !== false) {
                                        $event_code = 'berinai';
                                    } elseif (strpos($pname, 'outdoor') !== false || strpos($pname, 'post') !== false || strpos($pname, 'pre') !== false) {
                                        $event_code = 'outdoor';
                                    }

                                    if (strpos($pname, 'combo') !== false || (strpos($pname, 'nikah') !== false && strpos($pname, 'sanding') !== false) || (int)$pkg['event_count'] > 1) {
                                        $event_code = 'combo_multi';
                                    }
                                }

                                $pkg_json[$pkg['id']] = [
                                    'event_count' => (int)($pkg['event_count'] ?? 1),
                                    'name'        => $pkg['package_name'],
                                    'event_code'  => $event_code
                                ];
                            ?>
                                <label class="flex items-center justify-between p-3 <?= $is_wedding ? 'bg-white hover:border-stone-300 border-stone-100' : 'bg-slate-900/80 hover:bg-slate-800 border-slate-800/80'; ?> rounded-lg border cursor-pointer transition group">
                                    <div class="flex items-center space-x-3">
                                        <input type="checkbox" name="package_ids[]" value="<?= $pkg['id']; ?>" class="package-checkbox w-4 h-4 <?= $is_wedding ? 'text-stone-700 focus:ring-stone-400' : 'text-indigo-600 focus:ring-indigo-500'; ?> rounded border-gray-300">
                                        <span class="text-xs font-medium <?= $is_wedding ? 'text-stone-700 group-hover:text-stone-900' : 'text-slate-200 group-hover:text-white'; ?>">
                                            <?= htmlspecialchars($pkg['package_name']); ?>
                                        </span>
                                    </div>
                                </label>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p class="text-xs text-center py-4 <?= $is_wedding ? 'text-stone-400' : 'text-slate-500'; ?>">Tiada pakej ditawarkan buat masa ini.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- SECTION 3: BUTIRAN TARIKH & LOKASI -->
                <div class="space-y-4">
                    <div class="<?= $is_wedding ? 'border-b border-stone-200 pb-2' : 'border-b border-slate-800 pb-2'; ?>">
                        <h2 class="<?= $is_wedding ? 'text-lg font-serif-fine font-semibold text-stone-800' : 'text-xs font-bold uppercase tracking-wider text-indigo-400'; ?>">
                            <?= $is_wedding ? '3. Tarikh & Lokasi Majlis' : '3. JADUAL & LOKASI ACARA'; ?>
                        </h2>
                    </div>

                    <div id="eventsContainer" class="space-y-4">
                        <p class="text-xs <?= $is_wedding ? 'text-stone-500 bg-stone-100/60 border-stone-200' : 'text-amber-400/80 bg-amber-950/20 border-amber-800/40'; ?> p-3.5 rounded-xl border">
                            ⚠️ Sila tandakan pakej di atas terlebih dahulu untuk menetapkan butiran lokasi & tarikh.
                        </p>
                    </div>
                </div>

                <!-- SECTION 4: REMARKS -->
                <div class="space-y-2">
                    <div class="<?= $is_wedding ? 'border-b border-stone-200 pb-2' : 'border-b border-slate-800 pb-2'; ?>">
                        <h2 class="<?= $is_wedding ? 'text-lg font-serif-fine font-semibold text-stone-800' : 'text-xs font-bold uppercase tracking-wider text-indigo-400'; ?>">
                            <?= $is_wedding ? '4. Nota & Permintaan Khas' : '4. REMARKS & NOTA TAMBAHAN'; ?>
                        </h2>
                    </div>
                    <textarea name="remarks" rows="2" placeholder="Sila nyatakan konsep, permintaan khas, atau mesej tambahan..." class="w-full text-xs p-3 border <?= $is_wedding ? 'border-stone-200 bg-stone-50/30 text-stone-800 focus:border-stone-400' : 'border-slate-700 bg-slate-950 text-slate-100 focus:border-indigo-500'; ?> rounded-xl focus:outline-none transition"></textarea>
                </div>

                <!-- SECTION 5: TANDATANGAN DIGITAL -->
                <div class="space-y-3">
                    <div class="<?= $is_wedding ? 'border-b border-stone-200 pb-2' : 'border-b border-slate-800 pb-2'; ?>">
                        <h2 class="<?= $is_wedding ? 'text-lg font-serif-fine font-semibold text-stone-800' : 'text-xs font-bold uppercase tracking-wider text-indigo-400'; ?>">
                            <?= $is_wedding ? '5. Pengesahan & Tandatangan Digital' : '5. PERAKUAN & TANDATANGAN DIGITAL'; ?>
                        </h2>
                    </div>

                    <div class="border <?= $is_wedding ? 'border-dashed border-stone-300 bg-stone-50/30' : 'border-slate-700 bg-slate-950'; ?> rounded-xl p-2">
                        <canvas id="signature-pad" class="w-full h-36 bg-white rounded-lg cursor-crosshair"></canvas>
                    </div>

                    <div class="flex justify-between items-center text-[11px]">
                        <button type="button" id="clear-sig" class="<?= $is_wedding ? 'text-stone-500 hover:text-stone-800' : 'text-rose-400 hover:text-rose-300'; ?> underline font-medium">Padam & Tanda Tangan Semula</button>
                        <span class="<?= $is_wedding ? 'text-stone-400' : 'text-slate-500'; ?>">Tandatangan menggunakan skrin sentuh atau tetikus</span>
                    </div>
                    <input type="hidden" name="signature_data" id="signature_data">
                </div>

                <!-- SECTION 6: TERMA & SYARAT TICKBOX -->
                <div class="pt-2 border-t <?= $is_wedding ? 'border-stone-200' : 'border-slate-800'; ?>">
                    <label class="flex items-start space-x-3 cursor-pointer">
                        <input type="checkbox" id="terms_agreed" class="mt-0.5 w-4 h-4 <?= $is_wedding ? 'text-stone-800 focus:ring-stone-400' : 'text-indigo-600 focus:ring-indigo-500'; ?> rounded border-gray-300">
                        <span class="text-xs <?= $is_wedding ? 'text-stone-600' : 'text-slate-300'; ?>">
                            Saya telah membaca, memahami dan bersetuju dengan 
                            <button type="button" onclick="openTnCModal()" class="font-bold underline <?= $is_wedding ? 'text-stone-800' : 'text-indigo-400'; ?>">Terma & Syarat (T&C) Kaia Film</button>.
                        </span>
                    </label>
                </div>

                <!-- BUTANG HANTAR -->
                <button type="button" onclick="triggerSubmitWithTnC()" class="w-full <?= $is_wedding ? 'bg-stone-800 hover:bg-stone-900 text-stone-100 font-serif-fine text-base tracking-widest uppercase py-3.5' : 'bg-indigo-600 hover:bg-indigo-500 text-white font-bold py-3 text-xs tracking-wider uppercase shadow-lg shadow-indigo-600/30'; ?> rounded-xl transition duration-200 mt-4">
                    <?= $is_wedding ? 'Hantar Tempahan Perkahwinan' : '📩 HANTAR BORANG TEMPAHAN'; ?>
                </button>
            </form>

        </div>

        <p class="text-center text-[10px] mt-6 <?= $is_wedding ? 'text-stone-400' : 'text-slate-600'; ?>">
            © <?= date('Y'); ?> Kaia Film. All rights reserved.
        </p>

    </div>

    <!-- MODAL POPUP TERMA & SYARIKAT -->
    <div id="tncModal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden">
        <div class="bg-white text-slate-800 rounded-2xl max-w-3xl w-full max-h-[85vh] flex flex-col shadow-2xl border border-slate-200 overflow-hidden">
            
            <div class="p-4 border-b border-slate-200 flex flex-wrap justify-between items-center bg-slate-50 gap-2">
                <div>
                    <h3 class="text-xs font-bold text-slate-900 uppercase tracking-wide">KAIA FILM - OFFICIAL CLIENT AGREEMENT & CONTRACT</h3>
                    <p class="text-[10px] text-slate-500">Terma & Syarat Perkhidmatan Fotografi & Videografi</p>
                </div>
            </div>

            <div class="p-6 overflow-y-auto text-xs space-y-4 text-slate-700 leading-relaxed custom-scrollbar">
                <p class="italic text-[11px] text-slate-500">(Hereinafter referred to as "Kaia Film")<br>By booking a package provided by Kaia Film, you (the "Customer") are in strict agreement with the terms and conditions set out below.</p>

                <div class="space-y-1">
                    <h4 class="font-bold text-slate-900 border-b pb-0.5">1. FORCE MAJEURE & POSTPONEMENT</h4>
                    <ul class="list-disc pl-4 space-y-1 text-[11px]">
                        <li>Neither party shall be liable for any failure or delay in performing their obligations if such failure is due to causes beyond their reasonable control, including but not limited to acts of God, natural disasters (floods, earthquakes), government mandates/restrictions (e.g., MCO/CMCO or any related issue), riots, war, or sudden medical emergencies/fatal accidents involving the assigned crew.</li>
                        <li>In such events, events are allowed to be postponed to a later date without any extra charges, subject to slot availabilities.</li>
                        <li>Deposits are strictly non-refundable if the Customer cancels the event, since Kaia Film allows Customers to postpone their event(s) to a later date(s) up to 24 months.</li>
                        <li>In cases where the Customer insists on postponing to a date(s) that are not available on Kaia Film’s schedule, Kaia Film will refund the Customer based on the deposit received or total payment received for the pending event. Refund requests will be processed within 31 working days.</li>
                        <li><strong>Health & Safety Precautions:</strong> For safety and health precautions, our shooters/crew are strictly not allowed to shoot using or handle smartphones belonging to the Customer, family members, or guests (Phonegraphy by our crew is not allowed).</li>
                    </ul>
                </div>

                <div class="space-y-1">
                    <h4 class="font-bold text-slate-900 border-b pb-0.5">2. BOOKING, PRICING & PAYMENT TERMS</h4>
                    <ul class="list-disc pl-4 space-y-1 text-[11px]">
                        <li><strong>Final Pricing:</strong> After the Customer has paid the deposit and agreed to the package, the package price is final and not eligible for any further discounts. However, the Customer is permitted to add items or add-on services if necessary.</li>
                        <li><strong>Deposit & Balance:</strong> A non-refundable booking deposit of RM300 (subject to changes) is required to secure the date. The remaining balance MUST be fully paid at least 14 days before the FIRST event date.</li>
                        <li><strong>Multi-Event Pakej Policy:</strong> For Customers who booked a package of 2 events (or more) but decide to proceed with ONE event first, the payment to be made before the first event is based on the standard total for a ONE-event package, not based on a percentage split.<br><em class="text-slate-500">(Example: Customer booked a 2-event combo package for RM4,000 & requests to proceed with 1 event first. The Customer must pay the standard 1-event package rate of RM3,000 before the first event, NOT 50% of RM4,000).</em></li>
                        <li><strong>Logistics Charges:</strong> Package prices do not include transportation or accommodation charges. These charges will be implemented on top of the agreed package and advised by our Salesperson.
                            <ul class="list-circle pl-4 mt-0.5 space-y-0.5">
                                <li><strong>Accommodation:</strong> Minimum charge of RM130/night (subject to location & currency changes).</li>
                                <li><strong>Transportation:</strong> Charges vary based on the event location.</li>
                            </ul>
                            Both charges must be agreed upon prior to booking, and no disputes will be entertained thereafter.
                        </li>
                        <li><strong>Crew Accommodation:</strong> Photographers and videographers reserve the right NOT to use the accommodation provided by the Customer. Regardless, the Customer must either provide suitable accommodation or agree to the charges invoiced.</li>
                        <li><strong>Free Items Policy:</strong> Free promotional items (e.g., Outdoor Session, Poster Frame) are not inclusive in the package's actual price. Free items are non-transferable to cash and cannot be used as a discount if the Customer is not interested in them.</li>
                    </ul>
                </div>

                <div class="space-y-1">
                    <h4 class="font-bold text-slate-900 border-b pb-0.5">3. CANCELLATION, RESCHEDULING & DOWNGRADING</h4>
                    <ul class="list-disc pl-4 space-y-1 text-[11px]">
                        <li>All deposits paid to Kaia Film are strictly non-refundable. If an event is postponed, the deposit will be retained and credited toward the rescheduled event.</li>
                        <li><strong>Date Availability:</strong> Any changes to the event date must be informed as soon as possible. If the originally assigned photographer/videographer is not available on the new date, another photographer/videographer who meets Kaia Film’s professional standards will be deployed.</li>
                        <li><strong>Downgrading Package:</strong> If the Customer wishes to downgrade an agreed package, the previously booked package will be deemed cancelled, and the new arrangement must follow the latest package rates and structures offered by Kaia Film.</li>
                    </ul>
                </div>

                <div class="space-y-1">
                    <h4 class="font-bold text-slate-900 border-b pb-0.5">4. DEPLOYMENT & CREW RESPONSIBILITIES</h4>
                    <ul class="list-disc pl-4 space-y-1 text-[11px]">
                        <li><strong>Right to Withhold Service:</strong> Kaia Film reserves the absolute right NOT to send any photographer or videographer to the event if the Customer fails to settle the remaining payment balance 14 days prior to the event date.</li>
                        <li><strong>Pre-Event Contact:</strong> The assigned photographer/videographer will contact the Customer 1 to 3 days prior to the event to finalize necessary details.</li>
                        <li><strong>Event Coverage Limits:</strong> Customers are strongly advised to hire additional photographers/videographers if they require comprehensive coverage (e.g., guest coverage, separate preparation locations for both sides, VVIPs, or specific customized shots).</li>
                        <li><strong>Religious/Venue Permissions:</strong> Customers are required to check with their respective Pejabat Agama (Kadi) or venue management if our shooters are permitted to shoot the ceremony. No refunds or compensation will be given if shooters are barred from shooting due to a failure to confirm beforehand, or due to last-minute policy changes by the authorities.</li>
                    </ul>
                </div>

                <div class="space-y-1">
                    <h4 class="font-bold text-slate-900 border-b pb-0.5">5. LIMITATION OF LIABILITY & ACCIDENTS</h4>
                    <ul class="list-disc pl-4 space-y-1 text-[11px]">
                        <li><strong>Maximum Liability Cap:</strong> In the event of equipment malfunction, camera failure, lost/stolen memory cards, storage/backup system failure, human error, or negligence, the maximum liability of Kaia Film toward the Customer for any claims, losses, or damages shall be strictly limited to a refund of the monies paid by the Customer under this agreement. Under no circumstances shall Kaia Film be liable for indirect, incidental, or consequential damages, including emotional distress.</li>
                        <li><strong>Partial Data Loss:</strong> If a technical failure affects only a portion of the media, the refund will be calculated proportionally based on the missing media of the event ONLY.<br><em class="text-slate-500">(Example: If a Customer booked a 2-event Nikah & Sanding video package for RM4,000, and only the Sanding footage is missing, Kaia Film will refund the amount for the Sanding video only based on the package division and deliver the Nikah highlight video).</em></li>
                        <li><strong>Transit & Location Accidents:</strong> In the case of an accident, injury, natural disaster, violence, or death involving Kaia Film’s crew while traveling to the location, the company will attempt to find a replacement shooter. If no replacement is available due to uncontrollable factors, a full refund of payments made will be issued.</li>
                        <li><strong>Kaia Film shall NOT be held responsible for:</strong>
                            <ul class="list-circle pl-4 mt-0.5 space-y-0.5">
                                <li>Crew lateness caused by incomplete addresses, wrong location info, or last-minute changes provided by the Customer.</li>
                                <li>Any accidents, injuries, or deaths occurring during the outdoor photoshoot session that do not arise from the direct mistake or negligence of the crew.</li>
                                <li>Missed shots or failure to capture specific individuals if the Customer fails to provide clear details of who must be included in the photoshoot.</li>
                            </ul>
                        </li>
                    </ul>
                </div>

                <div class="space-y-1">
                    <h4 class="font-bold text-slate-900 border-b pb-0.5">6. COPYRIGHT, INTELLECTUAL PROPERTY & PROMOTIONAL USE</h4>
                    <ul class="list-disc pl-4 space-y-1 text-[11px]">
                        <li>Kaia Film retains absolute copyright and ownership over all photographs, video footages, and RAW files produced under this agreement.</li>
                        <li><strong>Promotional Rights:</strong> Kaia Film reserves the right to use any part of the images, footage, and final products for portfolio and promotional purposes—including but not limited to the company’s website, social media platforms, and advertisements—unless explicitly objected to by the Customer in writing prior to the booking date.</li>
                        <li><strong>Artistic Style:</strong> As creativity is subjective, no refunds or compensation will be issued based on the Customer's dissatisfaction with artistic output, style, or color tones, provided that the total quantity of products delivered is sufficient and matches the agreed package sample.</li>
                    </ul>
                </div>

                <div class="space-y-1">
                    <h4 class="font-bold text-slate-900 border-b pb-0.5">7. DATA RETENTION & ARCHIVE POLICY</h4>
                    <ul class="list-disc pl-4 space-y-1 text-[11px]">
                        <li>Kaia Film will retain and archive the raw photos, raw videos, and final edited products for a maximum period of THIRTY (30) DAYS from the date the product is delivered or collected by the Customer.</li>
                        <li>After this 30-day period, Kaia Film is no longer responsible for archiving the data and reserves the right to permanently delete all files from its storage system. The Customer shall bear all production and recovery costs if they request files after this period, provided the files are still retrievable.</li>
                    </ul>
                </div>

                <div class="space-y-1">
                    <h4 class="font-bold text-slate-900 border-b pb-0.5">8. WORKING HOURS & TIMELINES</h4>
                    <ul class="list-disc pl-4 space-y-1 text-[11px]">
                        <li><strong>Coverage Hours:</strong> Event coverage is strictly based on the quoted package (e.g., Solemnization is max 3 hours; Reception is max 6 hours, inclusive of the outdoor session). Additional time requested by the Customer will be charged at a minimum fee of RM100 per hour.</li>
                        <li><strong>Same-Day Events:</strong> Packages are priced per event. If multiple ceremonies (e.g., Solemnization and Reception) are held on the same day, it is accounted as 2 separate events, and the Customer must book a 2-event package.</li>
                        <li><strong>Unannounced Delays:</strong> If an event is delayed without prior notice and the crew is already on location, an extra working hour fee of RM150/hour per shooter will be charged.</li>
                        <li><strong>Schedule Overlaps:</strong> If an event is delayed or rescheduled to a later time on the same day and the assigned crew has another scheduled commitment, Kaia Film reserves the right to substitute the crew.</li>
                        <li><strong>Rehearsals:</strong> Attendance at event rehearsals will incur an additional charge of RM100 per session. Any venue-related fees (e.g., parking, entrance fees) must be reimbursed by the Customer upon presentation of receipts.</li>
                        <li><strong>Event Swapping:</strong> If a Customer changes the type of event originally booked (e.g., from a Reception to an Aqiqah/Post-wedding/Engagement), package details, pricing, and coverage hours will be adjusted according to the new arrangement by Kaia Film.</li>
                    </ul>
                </div>

                <div class="space-y-1">
                    <h4 class="font-bold text-slate-900 border-b pb-0.5">9. FINAL PRODUCT DELIVABLES & AMENDMENTS</h4>
                    <ul class="list-disc pl-4 space-y-1 text-[11px]">
                        <li>Post-processing (sneak peeks, album, and video editing) will ONLY commence once FULL PAYMENT has been successfully cleared.</li>
                        <li><strong>Standard Delivery Timeline:</strong> Final product delivery is within 3 months from the final payment date or the event date (whichever is later), subject to the Customer's timely amendments and approval.</li>
                        <li><strong>Photo & Album Specifications:</strong>
                            <ul class="list-circle pl-4 mt-0.5 space-y-0.5">
                                <li><strong>Artistic Consistency & Environmental Limitations:</strong> While style, color tones, and photography concepts are intended to be broadly consistent with the samples shown to the Customer, the Customer acknowledges that photography and videography are highly dependent on environmental factors. The final output—including exposure, presentation, and color tones—is strictly subject to the event’s actual location, venue lighting, weather conditions, decorations, and overall ambiance. Kaia Film shall not be held responsible or liable for any variations in style or quality caused by unfavorable venue conditions or lighting constraints beyond the crew's control.</li>
                                <li>Major digital manipulations (e.g., changing backgrounds, removing large objects/people) are NOT offered. Only minor editing (color/tone adjustment, brightness enhancement, cropping) will be performed.</li>
                                <li><strong>Album Drafts:</strong> No album drafts are provided unless requested with an additional fee of 20% of the package price. Customers are allowed ONE round of minor layout amendments before printing. Subsequent amendments will be charged at RM50 per page. Comments must be left directly in the Album draft link within 7 days of receipt. Lateness will directly extend the final delivery timeframe.</li>
                            </ul>
                        </li>
                        <li><strong>Video Specifications:</strong>
                            <ul class="list-circle pl-4 mt-0.5 space-y-0.5">
                                <li>Only the edited highlight video will be returned in softcopy. RAW videos are not included unless requested with an additional fee. The Customer must provide an external/portable hard disk for copying RAW footages.</li>
                                <li>Video highlights consist of a compilation of clips edited with Kaia Film’s cinematic style. Major digital manipulations are not supported.</li>
                                <li><strong>Video Amendments:</strong> ONE round of minor amendments (e.g., replacing a short scene, correcting name spelling, titles, or dates) is permitted. Subsequent amendments will be charged at RM100 per request.</li>
                                <li><strong>Major Video Changes:</strong> Changing the video style or background song after editing constitutes a major amendment as it requires a complete re-edit. A RM300 charge will be imposed, and the standard delivery timeline of 60 days will restart from the date of the new agreement.</li>
                                <li><strong>Fast Editing & Teasers:</strong> Fast editing videos will be delivered within the promised fast-period without accounting for customer changes. Any amendments will only be entertained AFTER the fast-editing period and will follow the standard 60-day timeline. No amendments are allowed for FREE teaser videos.</li>
                            </ul>
                        </li>
                    </ul>
                </div>

                <div class="space-y-1">
                    <h4 class="font-bold text-slate-900 border-b pb-0.5">10. LEGAL AMENDMENTS TO TERMS</h4>
                    <ul class="list-disc pl-4 space-y-1 text-[11px]">
                        <li>Kaia Film reserves the right to amend these terms and conditions for any future bookings. For existing active bookings, any amendments or changes to this contract must be made in writing and mutually signed by both Kaia Film and the Customer to be legally binding.</li>
                    </ul>
                </div>

            </div>

            <div class="p-4 border-t border-slate-200 bg-slate-50 flex justify-between items-center">
                <button type="button" onclick="closeTnCModal()" class="px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 text-xs font-bold rounded-lg transition">Tutup / Close</button>
                <button type="button" onclick="acceptTnCFromModal()" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-lg shadow transition">Saya Bersetuju & Sahkan</button>
            </div>

        </div>
    </div>

    <script>
        let signaturePad = null;

        document.addEventListener("DOMContentLoaded", function() {
            const isWedding = <?= $is_wedding ? 'true' : 'false'; ?>;
            const packageEventMap = <?= json_encode($pkg_json); ?>;
            const checkboxes = document.querySelectorAll('.package-checkbox');
            const eventsContainer = document.getElementById('eventsContainer');

            function updateEventFields() {
                eventsContainer.innerHTML = '';

                const checkedBoxes = document.querySelectorAll('.package-checkbox:checked');
                if (checkedBoxes.length === 0) {
                    const alertStyle = isWedding 
                        ? 'text-stone-500 bg-stone-100/60 border-stone-200' 
                        : 'text-amber-400/80 bg-amber-950/20 border-amber-800/40';
                    
                    eventsContainer.innerHTML = `
                        <p class="text-xs ${alertStyle} p-3.5 rounded-xl border">
                            ⚠️ Sila tandakan pakej di atas terlebih dahulu untuk menetapkan butiran lokasi & tarikh.
                        </p>`;
                    return;
                }

                let finalFormsToRender = [];

                if (!isWedding) {
                    checkedBoxes.forEach((cb) => {
                        const pkg = packageEventMap[cb.value];
                        if (pkg) {
                            const count = pkg.event_count || 1;
                            for (let c = 1; c <= count; c++) {
                                let labelTitle = pkg.name;
                                if (count > 1) {
                                    labelTitle += ` (Acara #${c})`;
                                }
                                finalFormsToRender.push(labelTitle);
                            }
                        }
                    });
                } else {
                    let hasCombo = false;
                    let eventTitlesList = [];

                    checkedBoxes.forEach(cb => {
                        const pkg = packageEventMap[cb.value];
                        if (pkg) {
                            if (pkg.event_code === 'combo_multi' || pkg.event_count > 1) {
                                hasCombo = true;
                            } else {
                                eventTitlesList.push(pkg.event_code);
                            }
                        }
                    });

                    if (hasCombo) {
                        finalFormsToRender.push('Majlis #1: Nikah / Akad Nikah');
                        finalFormsToRender.push('Majlis #2: Sanding / Resepsi');
                    } else {
                        eventTitlesList.forEach((code, idx) => {
                            let label = `Majlis #${idx + 1}`;
                            if (code === 'nikah') label = 'Majlis Nikah / Akad Nikah';
                            else if (code === 'sanding') label = 'Majlis Sanding / Resepsi';
                            else if (code === 'bertandang') label = 'Majlis Bertandang';
                            else if (code === 'tunang') label = 'Majlis Pertunangan';
                            else if (code === 'berinai') label = 'Malam Berinai';
                            else if (code === 'outdoor') label = 'Sesi Outdoor / Pre-Wedding';

                            finalFormsToRender.push(`Butiran Lokasi & Tarikh ${label}`);
                        });
                    }
                }

                finalFormsToRender.forEach((titleText, i) => {
                    const cardStyle = isWedding 
                        ? 'bg-stone-50/40 border-stone-200 text-stone-800' 
                        : 'bg-slate-900 border-slate-800 text-slate-100';
                    
                    const inputStyle = isWedding 
                        ? 'border-stone-200 bg-white text-stone-800 focus:border-stone-400' 
                        : 'border-slate-700 bg-slate-950 text-slate-100 focus:border-indigo-500';

                    const titleStyle = isWedding 
                        ? 'font-serif-fine text-stone-800 text-sm font-semibold' 
                        : 'text-indigo-400 text-[10px] font-bold uppercase tracking-wider';

                    const eventHtml = `
                        <div class="p-4 border rounded-xl space-y-3 ${cardStyle}">
                            <h4 class="${titleStyle} border-b pb-1 ${isWedding ? 'border-stone-200' : 'border-slate-800'}">${titleText}</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-[10px] uppercase tracking-wider font-semibold opacity-75 mb-1">Tarikh Acara *</label>
                                    <input type="date" name="events[${i+1}][date]" required class="w-full text-xs p-2 rounded-lg border ${inputStyle} focus:outline-none">
                                </div>
                                <div>
                                    <label class="block text-[10px] uppercase tracking-wider font-semibold opacity-75 mb-1">Masa Mula *</label>
                                    <input type="time" name="events[${i+1}][time]" required class="w-full text-xs p-2 rounded-lg border ${inputStyle} focus:outline-none">
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10px] uppercase tracking-wider font-semibold opacity-75 mb-1">Lokasi / Alamat Penuh Acara *</label>
                                <textarea name="events[${i+1}][address]" rows="2" required placeholder="cth: Grand Ballroom Hotel / Dewan Utama..." class="w-full text-xs p-2 rounded-lg border ${inputStyle} focus:outline-none"></textarea>
                            </div>
                        </div>
                    `;
                    eventsContainer.insertAdjacentHTML('beforeend', eventHtml);
                });
            }

            checkboxes.forEach(cb => cb.addEventListener('change', updateEventFields));

            const canvas = document.getElementById('signature-pad');
            if (canvas) {
                signaturePad = new SignaturePad(canvas, { backgroundColor: 'rgb(255, 255, 255)' });

                function resizeCanvas() {
                    const ratio = Math.max(window.devicePixelRatio || 1, 1);
                    canvas.width = canvas.offsetWidth * ratio;
                    canvas.height = canvas.offsetHeight * ratio;
                    canvas.getContext("2d").scale(ratio, ratio);
                    if (signaturePad) signaturePad.clear();
                }
                window.addEventListener("resize", resizeCanvas);
                resizeCanvas();

                document.getElementById('clear-sig').addEventListener('click', () => signaturePad.clear());
            }
        });

        window.triggerSubmitWithTnC = function() {
            const form = document.getElementById('bookingForm');
            const checkedBoxes = document.querySelectorAll('.package-checkbox:checked');

            if (checkedBoxes.length === 0) {
                alert("Sila pilih sekurang-kurangnya satu pakej servis!");
                return;
            }

            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            if (!signaturePad || signaturePad.isEmpty()) {
                alert("Sila berikan tandatangan digital anda terlebih dahulu!");
                return;
            }

            document.getElementById('signature_data').value = signaturePad.toDataURL();
            openTnCModal();
        };

        window.openTnCModal = function() { document.getElementById('tncModal').classList.remove('hidden'); };
        window.closeTnCModal = function() { document.getElementById('tncModal').classList.add('hidden'); };

        window.acceptTnCFromModal = function() {
            document.getElementById('terms_agreed').checked = true;
            document.getElementById('tncModal').classList.add('hidden');
            document.getElementById('bookingForm').submit();
        };
    </script>
</body>
</html>