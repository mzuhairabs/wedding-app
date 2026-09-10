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

// 1. Simpan Tetapan Apabila Borang Dihantar
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST as $key => $value) {
        if ($key !== 'submit') {
            $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->bind_param("sss", $key, $value, $value);
            $stmt->execute();
        }
    }

    // Muat Naik Logo Syarikat
    if (isset($_FILES['company_logo']) && $_FILES['company_logo']['error'] === UPLOAD_ERR_OK) {
        $targetDir = "uploads/";
        if (!file_exists($targetDir)) {
            mkdir($targetDir, 0777, true);
        }
        $fileName = "logo_" . time() . "_" . basename($_FILES['company_logo']['name']);
        $targetFile = $targetDir . $fileName;

        if (move_uploaded_file($_FILES['company_logo']['tmp_name'], $targetFile)) {
            $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('company_logo', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->bind_param("ss", $targetFile, $targetFile);
            $stmt->execute();
        }
    }

    echo "<script>alert('Tetapan Reka Bentuk Invois berjaya disimpan!'); window.location.href='invoice_settings.php';</script>";
    exit();
}

// 2. Ambil Tetapan Sedia Ada dari Database
$settingsRes = $conn->query("SELECT * FROM settings");
$set = [];
while ($row = $settingsRes->fetch_assoc()) {
    $set[$row['setting_key']] = $row['setting_value'];
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tetapan & Live Preview Invois</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@400;500;600;700&family=Roboto:wght@400;500;700&family=Lora:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        /* Variables Asas Preview */
        #previewContainer {
            font-family: '<?= $set['invoice_font_family'] ?? 'Inter'; ?>', sans-serif;
            font-size: <?= ($set['invoice_font_size'] ?? '12') . 'px'; ?>;
            color: <?= $set['invoice_text_color'] ?? '#1e293b'; ?>;
        }
        .theme-text {
            color: <?= $set['invoice_theme_color'] ?? '#4f46e5'; ?>;
        }
        .theme-bg {
            background-color: <?= $set['invoice_theme_color'] ?? '#4f46e5'; ?>;
        }
    </style>
</head>
<body class="bg-slate-100 min-h-screen text-slate-800 text-xs">

    <!-- Header Navigation -->
    <header class="bg-slate-900 text-white sticky top-0 z-30 shadow-md">
        <div class="max-w-[95rem] mx-auto px-4 sm:px-6 py-3.5 flex justify-between items-center">
            <div class="flex items-center space-x-3">
                <span class="text-xl">🎨</span>
                <div>
                    <h1 class="text-sm font-bold leading-tight">Tetapan & Live Preview Invois</h1>
                    <p class="text-[11px] text-slate-400">Pengubahsuaian Reka Bentuk Secara Masa Nyata (Real-Time)</p>
                </div>
            </div>
            <a href="index.php" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-md transition border border-slate-700">← Dashboard</a>
        </div>
    </header>

    <main class="max-w-[95rem] mx-auto px-4 sm:px-6 py-6">
        
        <!-- Grid Layout Split 2 Column (Borang | Live Preview) -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
            
            <!-- KOLUM KIRI: Borang Tetapan (5 Cols) -->
            <div class="lg:col-span-5 bg-white p-5 rounded-xl border border-slate-200 shadow-sm space-y-6">
                
                <form id="settingsForm" method="POST" enctype="multipart/form-data" class="space-y-6">
                    
                    <!-- 1. Maklumat Syarikat -->
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200/80 space-y-3">
                        <h2 class="font-bold text-slate-900 uppercase tracking-wider text-[11px] border-b pb-2">1. Maklumat Syarikat</h2>
                        
                        <div>
                            <label class="block font-semibold mb-1">Nama Syarikat</label>
                            <input type="text" id="company_name" name="company_name" value="<?= htmlspecialchars($set['company_name'] ?? 'Studio Photography Sdn Bhd'); ?>" required class="w-full border p-2 rounded-lg bg-white">
                        </div>

                        <div>
                            <label class="block font-semibold mb-1">No. Pendaftaran (ROC/SSM)</label>
                            <input type="text" id="company_reg_no" name="company_reg_no" value="<?= htmlspecialchars($set['company_reg_no'] ?? '202601009876 (1234567-X)'); ?>" class="w-full border p-2 rounded-lg bg-white">
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block font-semibold mb-1">No. Telefon</label>
                                <input type="text" id="company_phone" name="company_phone" value="<?= htmlspecialchars($set['company_phone'] ?? '+60 12-345 6789'); ?>" required class="w-full border p-2 rounded-lg bg-white">
                            </div>
                            <div>
                                <label class="block font-semibold mb-1">E-mel Syarikat</label>
                                <input type="email" id="company_email" name="company_email" value="<?= htmlspecialchars($set['company_email'] ?? 'info@studiophoto.my'); ?>" required class="w-full border p-2 rounded-lg bg-white">
                            </div>
                        </div>

                        <div>
                            <label class="block font-semibold mb-1">Alamat Penuh Syarikat</label>
                            <textarea id="company_address" name="company_address" rows="2" class="w-full border p-2 rounded-lg bg-white"><?= htmlspecialchars($set['company_address'] ?? "No. 123, Jalan Studio, Seksyen 7,\n40000 Shah Alam, Selangor"); ?></textarea>
                        </div>

                        <div>
                            <label class="block font-semibold mb-1">Logo Syarikat</label>
                            <input type="file" id="company_logo_input" name="company_logo" accept="image/*" class="w-full border p-1.5 rounded-lg bg-white text-xs">
                        </div>
                    </div>

                    <!-- 2. Gaya Warna & Typography -->
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200/80 space-y-3">
                        <h2 class="font-bold text-slate-900 uppercase tracking-wider text-[11px] border-b pb-2">2. Warna & Style Tulisan</h2>
                        
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block font-semibold mb-1">Warna Utama Tema</label>
                                <input type="color" id="invoice_theme_color" name="invoice_theme_color" value="<?= $set['invoice_theme_color'] ?? '#4f46e5'; ?>" class="w-full h-9 border rounded-lg p-0.5 cursor-pointer bg-white">
                            </div>
                            <div>
                                <label class="block font-semibold mb-1">Warna Teks Tulisan</label>
                                <input type="color" id="invoice_text_color" name="invoice_text_color" value="<?= $set['invoice_text_color'] ?? '#1e293b'; ?>" class="w-full h-9 border rounded-lg p-0.5 cursor-pointer bg-white">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block font-semibold mb-1">Jenis Font</label>
                                <select id="invoice_font_family" name="invoice_font_family" class="w-full border p-2 rounded-lg bg-white">
                                    <option value="Inter" <?= ($set['invoice_font_family'] ?? '') === 'Inter' ? 'selected' : ''; ?>>Inter (Standard)</option>
                                    <option value="Poppins" <?= ($set['invoice_font_family'] ?? '') === 'Poppins' ? 'selected' : ''; ?>>Poppins (Moden)</option>
                                    <option value="Roboto" <?= ($set['invoice_font_family'] ?? '') === 'Roboto' ? 'selected' : ''; ?>>Roboto (Kemasa)</option>
                                    <option value="Lora" <?= ($set['invoice_font_family'] ?? '') === 'Lora' ? 'selected' : ''; ?>>Lora (Serif / Klasik)</option>
                                </select>
                            </div>
                            <div>
                                <label class="block font-semibold mb-1">Saiz Font (px)</label>
                                <input type="number" id="invoice_font_size" name="invoice_font_size" value="<?= $set['invoice_font_size'] ?? '12'; ?>" min="10" max="18" class="w-full border p-2 rounded-lg bg-white">
                            </div>
                        </div>
                    </div>

                    <!-- 3. Terma & Syarat -->
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200/80 space-y-3">
                        <h2 class="font-bold text-slate-900 uppercase tracking-wider text-[11px] border-b pb-2">3. Terma & Syarat (Terms & Conditions)</h2>
                        <div>
                            <textarea id="invoice_terms" name="invoice_terms" rows="4" class="w-full border p-2.5 rounded-lg bg-white font-sans text-xs"><?= htmlspecialchars($set['invoice_terms'] ?? "1. Bayaran deposit tidak akan dipulangkan sekiranya berlaku pembatalan.\n2. Baki bayaran hendaklah dijelaskan sekurang-kurangnya 7 hari sebelum tarikh majlis.\n3. Hak cipta gambar tertera milik studio."); ?></textarea>
                        </div>
                    </div>

                    <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 rounded-xl transition shadow-md text-sm flex items-center justify-center gap-2">
                        💾 Simpan Semua Tetapan
                    </button>

                </form>
            </div>

            <!-- KOLUM KANAN: Live Preview Invois (7 Cols) -->
            <div class="lg:col-span-7 sticky top-20">
                <div class="bg-slate-800 p-3 rounded-t-xl flex justify-between items-center text-white">
                    <span class="font-bold flex items-center gap-2">👁️ Live Preview Invois Contoh</span>
                    <span class="text-[10px] bg-slate-700 px-2 py-0.5 rounded text-slate-300">Pratonton Masa Nyata</span>
                </div>

                <!-- Frame Contoh Invois -->
                <div id="previewContainer" class="bg-white p-8 rounded-b-xl border border-slate-200 shadow-2xl space-y-6 transition-all duration-150">
                    
                    <!-- Header Live Preview -->
                    <div class="flex justify-between items-start border-b pb-6">
                        <div class="flex items-center gap-3">
                            <img id="prev_logo" src="<?= !empty($set['company_logo']) ? $set['company_logo'] : 'https://via.placeholder.com/120x60?text=LOGO'; ?>" class="h-14 object-contain">
                            <div>
                                <h2 id="prev_company_name" class="font-bold text-sm text-slate-900"><?= htmlspecialchars($set['company_name'] ?? 'Studio Photography Sdn Bhd'); ?></h2>
                                <p id="prev_company_reg_no" class="text-[10px] text-slate-400"><?= htmlspecialchars($set['company_reg_no'] ?? '202601009876 (1234567-X)'); ?></p>
                                <p id="prev_company_address" class="text-[10px] text-slate-500 whitespace-pre-line leading-relaxed"><?= htmlspecialchars($set['company_address'] ?? "No. 123, Jalan Studio, Seksyen 7,\n40000 Shah Alam, Selangor"); ?></p>
                                <p class="text-[10px] text-slate-500 mt-0.5">📞 <span id="prev_company_phone"><?= htmlspecialchars($set['company_phone'] ?? '+60 12-345 6789'); ?></span> | ✉️ <span id="prev_company_email"><?= htmlspecialchars($set['company_email'] ?? 'info@studiophoto.my'); ?></span></p>
                            </div>
                        </div>

                        <div class="text-right">
                            <h1 class="text-2xl font-black uppercase tracking-tight theme-text">INVOIS</h1>
                            <p class="font-mono font-bold text-xs theme-text">#INV-00102</p>
                            <p class="text-[10px] text-slate-400 mt-1">Tarikh: <span class="font-semibold text-slate-700"><?= date('d/m/Y'); ?></span></p>
                        </div>
                    </div>

                    <!-- Butiran Contoh Pelanggan & Acara -->
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-slate-50 p-3.5 rounded-lg border border-slate-100">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Kepada Pelanggan:</p>
                            <h3 class="font-bold text-slate-900">Ahmad Razali</h3>
                            <p class="text-slate-600">📞 012-9876543</p>
                            <p class="text-slate-600">✉️ ahmad@gmail.com</p>
                        </div>

                        <div class="bg-slate-50 p-3.5 rounded-lg border border-slate-100">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Maklumat Acara:</p>
                            <p class="font-bold text-slate-900">Majlis Perkahwinan Ahmad & Siti</p>
                            <p class="text-slate-600">📅 15/10/2026</p>
                            <p class="text-slate-600">📍 Dewan Perdana, Shah Alam</p>
                        </div>
                    </div>

                    <!-- Jadual Servis Contoh -->
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b text-slate-400 text-[10px] font-bold uppercase tracking-wider">
                                <th class="py-2">Keterangan Perkhidmatan / Pakej</th>
                                <th class="py-2 text-right">Jumlah (RM)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr>
                                <td class="py-2.5">
                                    <p class="font-bold text-slate-800">Pakej Perkahwinan Diamond (Full Day)</p>
                                    <p class="text-[10px] text-slate-400">Liputan 2 Jurufoto, Album Exclusive & USB Pendrive</p>
                                </td>
                                <td class="py-2.5 text-right font-mono font-bold text-slate-800">
                                    RM 2,500.00
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Rekod Pembayaran Contoh -->
                    <div class="border-t pt-3">
                        <h4 class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-2">📜 Rekod Bayaran Pelanggan</h4>
                        <table class="w-full text-left border-collapse text-[10px]">
                            <thead>
                                <tr class="bg-slate-50 text-slate-500 font-bold">
                                    <th class="p-1.5">Tarikh</th>
                                    <th class="p-1.5">Jenis</th>
                                    <th class="p-1.5">Kaedah / Ref</th>
                                    <th class="p-1.5 text-right">Jumlah (RM)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr>
                                    <td class="p-1.5">01/09/2026</td>
                                    <td class="p-1.5">Deposit</td>
                                    <td class="p-1.5">Online Transfer (REF9872)</td>
                                    <td class="p-1.5 text-right font-mono font-semibold text-emerald-600">+ RM 500.00</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Ringkasan Baki Bayaran -->
                    <div class="flex justify-end">
                        <div class="w-56 space-y-1 border-t pt-2 text-xs">
                            <div class="flex justify-between text-slate-600">
                                <span>Jumlah Harga:</span>
                                <span class="font-mono font-bold text-slate-900">RM 2,500.00</span>
                            </div>
                            <div class="flex justify-between text-emerald-600">
                                <span>Telah Dibayar:</span>
                                <span class="font-mono font-bold">RM 500.00</span>
                            </div>
                            <div class="flex justify-between text-slate-900 font-bold border-t pt-1">
                                <span>Baki Tunggakan:</span>
                                <span class="font-mono text-rose-600">RM 2,000.00</span>
                            </div>
                        </div>
                    </div>

                    <!-- Terma & Syarat Preview -->
                    <div class="border-t pt-3">
                        <h4 class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Terma & Syarat (Terms & Conditions):</h4>
                        <div id="prev_terms" class="text-[10px] text-slate-500 whitespace-pre-line leading-relaxed"><?= htmlspecialchars($set['invoice_terms'] ?? "1. Bayaran deposit tidak akan dipulangkan sekiranya berlaku pembatalan.\n2. Baki bayaran hendaklah dijelaskan sekurang-kurangnya 7 hari sebelum tarikh majlis."); ?></div>
                    </div>

                </div>
            </div>

        </div>

    </main>

    <!-- JavaScript Live Preview Engine -->
    <script>
        const formInputs = {
            name: document.getElementById('company_name'),
            regNo: document.getElementById('company_reg_no'),
            phone: document.getElementById('company_phone'),
            email: document.getElementById('company_email'),
            address: document.getElementById('company_address'),
            terms: document.getElementById('invoice_terms'),
            themeColor: document.getElementById('invoice_theme_color'),
            textColor: document.getElementById('invoice_text_color'),
            fontFamily: document.getElementById('invoice_font_family'),
            fontSize: document.getElementById('invoice_font_size'),
            logoInput: document.getElementById('company_logo_input')
        };

        const previewElems = {
            container: document.getElementById('previewContainer'),
            name: document.getElementById('prev_company_name'),
            regNo: document.getElementById('prev_company_reg_no'),
            phone: document.getElementById('prev_company_phone'),
            email: document.getElementById('prev_company_email'),
            address: document.getElementById('prev_company_address'),
            terms: document.getElementById('prev_terms'),
            logo: document.getElementById('prev_logo')
        };

        // Kemaskini Teks Live
        function updateLiveText() {
            previewElems.name.innerText = formInputs.name.value || 'Nama Syarikat';
            previewElems.regNo.innerText = formInputs.regNo.value || '';
            previewElems.phone.innerText = formInputs.phone.value || '';
            previewElems.email.innerText = formInputs.email.value || '';
            previewElems.address.innerText = formInputs.address.value || '';
            previewElems.terms.innerText = formInputs.terms.value || '';
        }

        // Kemaskini Gaya Warna & Font Live
        function updateLiveStyles() {
            const themeColor = formInputs.themeColor.value;
            const textColor = formInputs.textColor.value;
            const fontFamily = formInputs.fontFamily.value;
            const fontSize = formInputs.fontSize.value + 'px';

            previewElems.container.style.fontFamily = `'${fontFamily}', sans-serif`;
            previewElems.container.style.fontSize = fontSize;
            previewElems.container.style.color = textColor;

            // Kemaskini Semua Warna Tema
            document.querySelectorAll('.theme-text').forEach(el => el.style.color = themeColor);
            document.querySelectorAll('.theme-bg').forEach(el => el.style.backgroundColor = themeColor);
        }

        // Kemaskini Pratonton Logo
        formInputs.logoInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    previewElems.logo.src = event.target.result;
                };
                reader.readAsDataURL(file);
            }
        });

        // Dengar Perubahan Pada Semua Input
        Object.values(formInputs).forEach(input => {
            if (input && input !== formInputs.logoInput) {
                input.addEventListener('input', () => {
                    updateLiveText();
                    updateLiveStyles();
                });
                input.addEventListener('change', () => {
                    updateLiveText();
                    updateLiveStyles();
                });
            }
        });

        // Jalankan Sekali Semasa Halaman Dimuatkan
        updateLiveStyles();
    </script>

</body>
</html>