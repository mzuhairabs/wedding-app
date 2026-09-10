<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/db.php';

$client_id = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;

if ($client_id <= 0) {
    die("<div style='padding:20px; font-family:sans-serif;'>❌ ID Pelanggan tidak sah.</div>");
}

// Semak & Tambah lajur jika belum wujud dalam pangkalan data
$conn->query("ALTER TABLE clients ADD COLUMN IF NOT EXISTS partner_name VARCHAR(255) NULL");
$conn->query("ALTER TABLE clients ADD COLUMN IF NOT EXISTS signature_path VARCHAR(255) NULL");
$conn->query("ALTER TABLE clients ADD COLUMN IF NOT EXISTS terms_agreed_at DATETIME NULL");

// Ambil Maklumat Pelanggan
$stmt = $conn->prepare("SELECT * FROM clients WHERE id = ?");
$stmt->bind_param("i", $client_id);
$stmt->execute();
$client = $stmt->get_result()->fetch_assoc();

if (!$client) {
    die("<div style='padding:20px; font-family:sans-serif;'>❌ Rekod pelanggan tidak ditemui dalam database.</div>");
}

// Ambil Maklumat Acara Majlis
$ev_res = $conn->query("SELECT * FROM events WHERE client_id = $client_id ORDER BY event_date ASC");
$events = [];
if ($ev_res) {
    while($r = $ev_res->fetch_assoc()) { $events[] = $r; }
}

// LALUAN FOLDER TANDATANGAN (assets/signature)
$sig_image_url = '';
if (!empty($client['signature_path'])) {
    $filename = basename($client['signature_path']);
    $file_on_disk = __DIR__ . '/assets/signature/' . $filename;
    
    if (file_exists($file_on_disk)) {
        $sig_image_url = 'assets/signature/' . $filename;
    } elseif (file_exists(__DIR__ . '/' . $client['signature_path'])) {
        $sig_image_url = $client['signature_path'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Official Contract & Agreement - <?= htmlspecialchars($client['client_name']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; color: black !important; padding: 0 !important; }
            .print-container { border: none !important; box-shadow: none !important; width: 100% !important; max-width: 100% !important; padding: 0 !important; }
        }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 font-sans p-3 md:p-8 min-h-screen">

    <!-- BUTANG NAVIGASI / CETAK (NO PRINT) -->
    <div class="max-w-4xl mx-auto mb-4 flex justify-between items-center no-print">
        <a href="clients.php" class="px-4 py-2 bg-slate-700 text-white rounded-lg text-xs font-bold hover:bg-slate-800 transition">
            ← Kembali ke Senarai Pelanggan
        </a>

        <button onclick="window.print()" class="px-5 py-2 bg-emerald-600 text-white rounded-lg text-xs font-bold hover:bg-emerald-700 shadow">
            🖨️ Cetak / Simpan PDF
        </button>
    </div>

    <!-- DOKUMEN UTAMA -->
    <div class="max-w-4xl mx-auto bg-white p-6 md:p-12 rounded-2xl shadow-xl border border-slate-200 print-container space-y-6">
        
        <!-- ==========================================
             1. BAHAGIAN ATAS: NAMA & MAKLUMAT PELANGGAN
             ========================================== -->
        <header class="border-b-2 border-slate-900 pb-5 space-y-4">
            <div class="flex justify-between items-start">
                <div>
                    <h1 class="text-2xl font-black tracking-wider text-slate-900">KAIA FILM</h1>
                    <p class="text-xs text-slate-500 font-medium">Official Photography & Videography Client Agreement</p>
                </div>
                <div class="text-right text-[11px] font-mono text-slate-600">
                    <div><strong>NO. PERJANJIAN:</strong> #KF-AGR-<?= str_pad($client['id'], 4, '0', STR_PAD_LEFT); ?></div>
                    <div><strong>TARIKH PERSETUJUAN:</strong> <?= !empty($client['terms_agreed_at']) ? date('d/m/Y h:i A', strtotime($client['terms_agreed_at'])) : date('d/m/Y'); ?></div>
                </div>
            </div>

            <!-- JADUAL MAKLUMAT PELANGGAN & MAJLIS -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 bg-slate-50 p-4 rounded-xl border border-slate-200 text-xs">
                <div class="space-y-1">
                    <h3 class="font-bold text-indigo-700 uppercase text-[10px] tracking-wider mb-1.5 border-b pb-1">Maklumat Pelanggan</h3>
                    <div><strong>Nama Utama / Pengantin:</strong> <?= htmlspecialchars($client['client_name']); ?></div>
                    <div><strong>Nama Pasangan:</strong> <?= htmlspecialchars($client['partner_name'] ?? '-'); ?></div>
                    <div><strong>No. Telefon Utama:</strong> <?= htmlspecialchars($client['phone_number']); ?></div>
                    <div><strong>E-mel Rasmi:</strong> <?= htmlspecialchars($client['email'] ?? '-'); ?></div>
                    <div><strong>Alamat Penghantaran:</strong> <?= htmlspecialchars($client['address'] ?? '-'); ?></div>
                </div>

                <div class="space-y-1">
                    <h3 class="font-bold text-indigo-700 uppercase text-[10px] tracking-wider mb-1.5 border-b pb-1">Butiran Majlis & Tempahan</h3>
                    <?php if (!empty($events)): ?>
                        <?php foreach($events as $idx => $ev): ?>
                            <div class="mb-1">
                                <strong>• <?= htmlspecialchars($ev['event_title']); ?>:</strong> <?= date('d/m/Y', strtotime($ev['event_date'])); ?> <?= $ev['event_time'] ? '('.$ev['event_time'].')' : ''; ?><br>
                                <span class="text-[10px] text-slate-500">📍 <?= htmlspecialchars($ev['venue_address'] ?? 'Lokasi belum ditetapkan'); ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-slate-400 italic">Tiada acara khusus direkodkan.</div>
                    <?php endif; ?>
                    <?php if (!empty($client['notes'])): ?>
                        <div class="mt-2 pt-1 border-t text-[10px] text-slate-500"><strong>Nota Admin:</strong> <?= htmlspecialchars($client['notes']); ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </header>


        <!-- ==========================================
             2. BAHAGIAN BODY: TERMA & SYARIKAT (100% FULL T&C)
             ========================================== -->
        <main class="space-y-5 text-xs text-slate-700 leading-relaxed border-b pb-6">
            
            <div class="p-3 bg-slate-50 border border-slate-200 rounded-lg italic text-slate-600 text-[11px]">
                (Hereinafter referred to as "Kaia Film")<br>
                By booking a package provided by Kaia Film, you (the "Customer") are in strict agreement with the terms and conditions set out below.
            </div>

            <!-- Fasal 1 -->
            <div class="space-y-1.5">
                <h3 class="font-bold text-slate-900 border-b pb-1 uppercase tracking-wide">1. FORCE MAJEURE & POSTPONEMENT</h3>
                <ul class="list-disc pl-5 space-y-1 text-[11px]">
                    <li>Neither party shall be liable for any failure or delay in performing their obligations if such failure is due to causes beyond their reasonable control, including but not limited to acts of God, natural disasters (floods, earthquakes), government mandates/restrictions (e.g., MCO/CMCO or any related issue), riots, war, or sudden medical emergencies/fatal accidents involving the assigned crew.</li>
                    <li>In such events, events are allowed to be postponed to a later date without any extra charges, subject to slot availabilities.</li>
                    <li>Deposits are strictly non-refundable if the Customer cancels the event, since Kaia Film allows Customers to postpone their event(s) to a later date(s) up to 24 months.</li>
                    <li>In cases where the Customer insists on postponing to a date(s) that are not available on Kaia Film’s schedule, Kaia Film will refund the Customer based on the deposit received or total payment received for the pending event. Refund requests will be processed within 31 working days.</li>
                    <li><strong>Health & Safety Precautions:</strong> For safety and health precautions, our shooters/crew are strictly not allowed to shoot using or handle smartphones belonging to the Customer, family members, or guests (Phonegraphy by our crew is not allowed).</li>
                </ul>
            </div>

            <!-- Fasal 2 -->
            <div class="space-y-1.5">
                <h3 class="font-bold text-slate-900 border-b pb-1 uppercase tracking-wide">2. BOOKING, PRICING & PAYMENT TERMS</h3>
                <ul class="list-disc pl-5 space-y-1 text-[11px]">
                    <li><strong>Final Pricing:</strong> After the Customer has paid the deposit and agreed to the package, the package price is final and not eligible for any further discounts. However, the Customer is permitted to add items or add-on services if necessary.</li>
                    <li><strong>Deposit & Balance:</strong> A non-refundable booking deposit of RM300 (subject to changes) is required to secure the date. The remaining balance MUST be fully paid at least 14 days before the FIRST event date.</li>
                    <li><strong>Multi-Event Pakej Policy:</strong> For Customers who booked a package of 2 events (or more) but decide to proceed with ONE event first, the payment to be made before the first event is based on the standard total for a ONE-event package, not based on a percentage split.<br>
                    <i>(Example: Customer booked a 2-event combo package for RM4,000 & requests to proceed with 1 event first. The Customer must pay the standard 1-event package rate of RM3,000 before the first event, NOT 50% of RM4,000).</i></li>
                    <li><strong>Logistics Charges:</strong> Package prices do not include transportation or accommodation charges. These charges will be implemented on top of the agreed package and advised by our Salesperson.
                        <ul class="list-circle pl-4 mt-0.5 space-y-0.5">
                            <li>- Accommodation: Minimum charge of RM130/night (subject to location & currency changes).</li>
                            <li>- Transportation: Charges vary based on the event location.</li>
                        </ul>
                        Both charges must be agreed upon prior to booking, and no disputes will be entertained thereafter.
                    </li>
                    <li><strong>Crew Accommodation:</strong> Photographers and videographers reserve the right NOT to use the accommodation provided by the Customer. Regardless, the Customer must either provide suitable accommodation or agree to the charges invoiced.</li>
                    <li><strong>Free Items Policy:</strong> Free promotional items (e.g., Outdoor Session, Poster Frame) are not inclusive in the package's actual price. Free items are non-transferable to cash and cannot be used as a discount if the Customer is not interested in them.</li>
                </ul>
            </div>

            <!-- Fasal 3 -->
            <div class="space-y-1.5">
                <h3 class="font-bold text-slate-900 border-b pb-1 uppercase tracking-wide">3. CANCELLATION, RESCHEDULING & DOWNGRADING</h3>
                <ul class="list-disc pl-5 space-y-1 text-[11px]">
                    <li>All deposits paid to Kaia Film are strictly non-refundable. If an event is postponed, the deposit will be retained and credited toward the rescheduled event.</li>
                    <li><strong>Date Availability:</strong> Any changes to the event date must be informed as soon as possible. If the originally assigned photographer/videographer is not available on the new date, another photographer/videographer who meets Kaia Film’s professional standards will be deployed.</li>
                    <li><strong>Downgrading Package:</strong> If the Customer wishes to downgrade an agreed package, the previously booked package will be deemed cancelled, and the new arrangement must follow the latest package rates and structures offered by Kaia Film.</li>
                </ul>
            </div>

            <!-- Fasal 4 -->
            <div class="space-y-1.5">
                <h3 class="font-bold text-slate-900 border-b pb-1 uppercase tracking-wide">4. DEPLOYMENT & CREW RESPONSIBILITIES</h3>
                <ul class="list-disc pl-5 space-y-1 text-[11px]">
                    <li><strong>Right to Withhold Service:</strong> Kaia Film reserves the absolute right NOT to send any photographer or videographer to the event if the Customer fails to settle the remaining payment balance 14 days prior to the event date.</li>
                    <li><strong>Pre-Event Contact:</strong> The assigned photographer/videographer will contact the Customer 1 to 3 days prior to the event to finalize necessary details.</li>
                    <li><strong>Event Coverage Limits:</strong> Customers are strongly advised to hire additional photographers/videographers if they require comprehensive coverage (e.g., guest coverage, separate preparation locations for both sides, VVIPs, or specific customized shots).</li>
                    <li><strong>Religious/Venue Permissions:</strong> Customers are required to check with their respective Pejabat Agama (Kadi) or venue management if our shooters are permitted to shoot the ceremony. No refunds or compensation will be given if shooters are barred from shooting due to a failure to confirm beforehand, or due to last-minute policy changes by the authorities.</li>
                </ul>
            </div>

            <!-- Fasal 5 -->
            <div class="space-y-1.5">
                <h3 class="font-bold text-slate-900 border-b pb-1 uppercase tracking-wide">5. LIMITATION OF LIABILITY & ACCIDENTS</h3>
                <ul class="list-disc pl-5 space-y-1 text-[11px]">
                    <li><strong>Maximum Liability Cap:</strong> In the event of equipment malfunction, camera failure, lost/stolen memory cards, storage/backup system failure, human error, or negligence, the maximum liability of Kaia Film toward the Customer for any claims, losses, or damages shall be strictly limited to a refund of the monies paid by the Customer under this agreement. Under no circumstances shall Kaia Film be liable for indirect, incidental, or consequential damages, including emotional distress.</li>
                    <li><strong>Partial Data Loss:</strong> If a technical failure affects only a portion of the media, the refund will be calculated proportionally based on the missing media of the event ONLY.<br>
                    <i>(Example: If a Customer booked a 2-event Nikah & Sanding video package for RM4,000, and only the Sanding footage is missing, Kaia Film will refund the amount for the Sanding video only based on the package division and deliver the Nikah highlight video).</i></li>
                    <li><strong>Transit & Location Accidents:</strong> In the case of an accident, injury, natural disaster, violence, or death involving Kaia Film’s crew while traveling to the location, the company will attempt to find a replacement shooter. If no replacement is available due to uncontrollable factors, a full refund of payments made will be issued.</li>
                    <li><strong>Kaia Film shall NOT be held responsible for:</strong>
                        <ul class="list-circle pl-4 mt-0.5 space-y-0.5">
                            <li>- Crew lateness caused by incomplete addresses, wrong location info, or last-minute changes provided by the Customer.</li>
                            <li>- Any accidents, injuries, or deaths occurring during the outdoor photoshoot session that do not arise from the direct mistake or negligence of the crew.</li>
                            <li>- Missed shots or failure to capture specific individuals if the Customer fails to provide clear details of who must be included in the photoshoot.</li>
                        </ul>
                    </li>
                </ul>
            </div>

            <!-- Fasal 6 -->
            <div class="space-y-1.5">
                <h3 class="font-bold text-slate-900 border-b pb-1 uppercase tracking-wide">6. COPYRIGHT, INTELLECTUAL PROPERTY & PROMOTIONAL USE</h3>
                <ul class="list-disc pl-5 space-y-1 text-[11px]">
                    <li>Kaia Film retains absolute copyright and ownership over all photographs, video footages, and RAW files produced under this agreement.</li>
                    <li><strong>Promotional Rights:</strong> Kaia Film reserves the right to use any part of the images, footage, and final products for portfolio and promotional purposes—including but not limited to the company’s website, social media platforms, and advertisements—unless explicitly objected to by the Customer in writing prior to the booking date.</li>
                    <li><strong>Artistic Style:</strong> As creativity is subjective, no refunds or compensation will be issued based on the Customer's dissatisfaction with artistic output, style, or color tones, provided that the total quantity of products delivered is sufficient and matches the agreed package sample.</li>
                </ul>
            </div>

            <!-- Fasal 7 -->
            <div class="space-y-1.5">
                <h3 class="font-bold text-slate-900 border-b pb-1 uppercase tracking-wide">7. DATA RETENTION & ARCHIVE POLICY</h3>
                <ul class="list-disc pl-5 space-y-1 text-[11px]">
                    <li>Kaia Film will retain and archive the raw photos, raw videos, and final edited products for a maximum period of THIRTY (30) DAYS from the date the product is delivered or collected by the Customer.</li>
                    <li>After this 30-day period, Kaia Film is no longer responsible for archiving the data and reserves the right to permanently delete all files from its storage system. The Customer shall bear all production and recovery costs if they request files after this period, provided the files are still retrievable.</li>
                </ul>
            </div>

            <!-- Fasal 8 -->
            <div class="space-y-1.5">
                <h3 class="font-bold text-slate-900 border-b pb-1 uppercase tracking-wide">8. WORKING HOURS & TIMELINES</h3>
                <ul class="list-disc pl-5 space-y-1 text-[11px]">
                    <li><strong>Coverage Hours:</strong> Event coverage is strictly based on the quoted package (e.g., Solemnization is max 3 hours; Reception is max 6 hours, inclusive of the outdoor session). Additional time requested by the Customer will be charged at a minimum fee of RM100 per hour.</li>
                    <li><strong>Same-Day Events:</strong> Packages are priced per event. If multiple ceremonies (e.g., Solemnization and Reception) are held on the same day, it is accounted as 2 separate events, and the Customer must book a 2-event package.</li>
                    <li><strong>Unannounced Delays:</strong> If an event is delayed without prior notice and the crew is already on location, an extra working hour fee of RM150/hour per shooter will be charged.</li>
                    <li><strong>Schedule Overlaps:</strong> If an event is delayed or rescheduled to a later time on the same day and the assigned crew has another scheduled commitment, Kaia Film reserves the right to substitute the crew.</li>
                    <li><strong>Rehearsals:</strong> Attendance at event rehearsals will incur an additional charge of RM100 per session. Any venue-related fees (e.g., parking, entrance fees) must be reimbursed by the Customer upon presentation of receipts.</li>
                    <li><strong>Event Swapping:</strong> If a Customer changes the type of event originally booked (e.g., from a Reception to an Aqiqah/Post-wedding/Engagement), package details, pricing, and coverage hours will be adjusted according to the new arrangement by Kaia Film.</li>
                </ul>
            </div>

            <!-- Fasal 9 -->
            <div class="space-y-1.5">
                <h3 class="font-bold text-slate-900 border-b pb-1 uppercase tracking-wide">9. FINAL PRODUCT DELIVABLES & AMENDMENTS</h3>
                <ul class="list-disc pl-5 space-y-1 text-[11px]">
                    <li>Post-processing (sneak peeks, album, and video editing) will ONLY commence once FULL PAYMENT has been successfully cleared.</li>
                    <li><strong>Standard Delivery Timeline:</strong> Final product delivery is within 3 months from the final payment date or the event date (whichever is later), subject to the Customer's timely amendments and approval.</li>
                    <li><strong>Photo & Album Specifications:</strong>
                        <ul class="list-circle pl-4 mt-0.5 space-y-0.5">
                            <li>- <i>Artistic Consistency & Environmental Limitations:</i> While style, color tones, and photography concepts are intended to be broadly consistent with the samples shown to the Customer, the Customer acknowledges that photography and videography are highly dependent on environmental factors. The final output—including exposure, presentation, and color tones—is strictly subject to the event’s actual location, venue lighting, weather conditions, decorations, and overall ambiance. Kaia Film shall not be held responsible or liable for any variations in style or quality caused by unfavorable venue conditions or lighting constraints beyond the crew's control.</li>
                            <li>- Major digital manipulations (e.g., changing backgrounds, removing large objects/people) are NOT offered. Only minor editing (color/tone adjustment, brightness enhancement, cropping) will be performed.</li>
                            <li>- <i>Album Drafts:</i> No album drafts are provided unless requested with an additional fee of 20% of the package price. Customers are allowed ONE round of minor layout amendments before printing. Subsequent amendments will be charged at RM50 per page. Comments must be left directly in the Album draft link within 7 days of receipt. Lateness will directly extend the final delivery timeframe.</li>
                        </ul>
                    </li>
                    <li><strong>Video Specifications:</strong>
                        <ul class="list-circle pl-4 mt-0.5 space-y-0.5">
                            <li>- Only the edited highlight video will be returned in softcopy. RAW videos are not included unless requested with an additional fee. The Customer must provide an external/portable hard disk for copying RAW footages.</li>
                            <li>- Video highlights consist of a compilation of clips edited with Kaia Film’s cinematic style. Major digital manipulations are not supported.</li>
                            <li>- <i>Video Amendments:</i> ONE round of minor amendments (e.g., replacing a short scene, correcting name spelling, titles, or dates) is permitted. Subsequent amendments will be charged at RM100 per request.</li>
                            <li>- <i>Major Video Changes:</i> Changing the video style or background song after editing constitutes a major amendment as it requires a complete re-edit. A RM300 charge will be imposed, and the standard delivery timeline of 60 days will restart from the date of the new agreement.</li>
                            <li>- <i>Fast Editing & Teasers:</i> Fast editing videos will be delivered within the promised fast-period without accounting for customer changes. Any amendments will only be entertained AFTER the fast-editing period and will follow the standard 60-day timeline. No amendments are allowed for FREE teaser videos.</li>
                        </ul>
                    </li>
                </ul>
            </div>

            <!-- Fasal 10 -->
            <div class="space-y-1.5">
                <h3 class="font-bold text-slate-900 border-b pb-1 uppercase tracking-wide">10. LEGAL AMENDMENTS TO TERMS</h3>
                <ul class="list-disc pl-5 space-y-1 text-[11px]">
                    <li>Kaia Film reserves the right to amend these terms and conditions for any future bookings. For existing active bookings, any amendments or changes to this contract must be made in writing and mutually signed by both Kaia Film and the Customer to be legally binding.</li>
                </ul>
            </div>

        </main>


        <!-- ==========================================
             3. BAHAGIAN BAWAH: TANDATANGAN & NAMA PELANGGAN
             ========================================== -->
        <footer class="pt-2 space-y-6">
            <div class="text-center font-bold text-xs text-slate-800 uppercase tracking-wider">
                CONFIRMATION & DIGITAL SIGNATURE ACKNOWLEDGEMENT
            </div>

            <div class="grid grid-cols-2 gap-8 text-xs">
                <!-- WAKIL KAIA FILM -->
                <div class="text-center space-y-2">
                    <p class="font-bold text-slate-900">Wakil / Pengurusan Kaia Film</p>
                    <div class="h-24 flex flex-col items-center justify-center border border-dashed border-slate-300 rounded-xl bg-slate-50/50 p-2">
                        <span class="text-indigo-600 font-bold text-sm tracking-widest">KAIA FILM</span>
                        <span class="text-[9px] text-slate-400 mt-1">Disahkan secara sistem automatik</span>
                    </div>
                    <p class="text-[10px] text-slate-500 font-semibold">Kaia Film Management</p>
                </div>

                <!-- TANDATANGAN PELANGGAN -->
                <div class="text-center space-y-2">
                    <p class="font-bold text-slate-900">Tandatangan Pelanggan</p>
                    <div class="h-24 flex items-center justify-center border border-slate-300 rounded-xl bg-white overflow-hidden p-2 shadow-inner">
                        <?php if (!empty($sig_image_url)): ?>
                            <img src="<?= htmlspecialchars($sig_image_url); ?>" alt="Tandatangan Pelanggan" class="max-h-full max-w-full object-contain">
                        <?php else: ?>
                            <span class="text-slate-400 italic text-[10px]">Tiada Fail Tandatangan Ditemui</span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <p class="text-[11px] font-bold text-slate-900 uppercase"><?= htmlspecialchars($client['client_name']); ?></p>
                        <p class="text-[9px] text-slate-400 font-mono">Tarikh: <?= !empty($client['terms_agreed_at']) ? date('d/m/Y', strtotime($client['terms_agreed_at'])) : date('d/m/Y'); ?></p>
                    </div>
                </div>
            </div>

            <div class="text-center text-[9px] text-slate-400 pt-4 border-t border-slate-100">
                Dokumen ini dijana secara elektronik oleh sistem Kaia Film dan dikira sah berkuat kuasa setelah disahkan oleh pelanggan.
            </div>
        </footer>

    </div>

</body>
</html>