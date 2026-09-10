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

$edit_crew = null;
if (isset($_GET['edit_id']) && !empty($_GET['edit_id'])) {
    $edit_id = (int)$_GET['edit_id'];
    $stmt_edit = $conn->prepare("SELECT * FROM crew WHERE id = ? AND (is_deleted = 0 OR is_deleted IS NULL)");
    $stmt_edit->bind_param("i", $edit_id);
    $stmt_edit->execute();
    $res_edit = $stmt_edit->get_result();
    if ($res_edit->num_rows > 0) {
        $edit_crew = $res_edit->fetch_assoc();
    }
    $stmt_edit->close();
}

$crewQuery = $conn->query("SELECT * FROM crew WHERE (is_deleted = 0 OR is_deleted IS NULL) ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengurusan Crew - Studio Manager</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        @media print {
            body * { visibility: hidden; }
            #printableProfileArea, #printableProfileArea * { visibility: visible; }
            #printableProfileArea { position: absolute; left: 0; top: 0; width: 100%; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 text-xs min-h-screen">

    <header class="bg-slate-900 text-white sticky top-0 z-30 shadow-md">
        <div class="max-w-7xl mx-auto px-4 py-3.5 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div class="flex items-center space-x-3">
                <div class="w-8 h-8 bg-indigo-600 rounded-lg flex items-center justify-center font-bold text-sm text-white shadow-inner">
                    📷
                </div>
                <div>
                    <h1 class="text-sm font-bold text-white">Studio Manager</h1>
                    <p class="text-[11px] text-slate-400">Pengurusan Crew & Rekod Sumber Manusia</p>
                </div>
            </div>

            <nav class="flex flex-wrap gap-1.5 text-xs font-medium">
                <a href="index.php" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-md transition border border-slate-700">← Dashboard</a>
                <a href="booking.php" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-md transition shadow-sm">+ Booking</a>
                <a href="expenses.php" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-md transition border border-slate-700">+ Perbelanjaan</a>
                <a href="packages.php" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-md transition border border-slate-700">Pakej</a>
                <a href="crew.php" class="px-3 py-1.5 bg-slate-700 text-white rounded-md transition border border-slate-600">Crew</a>
                <a href="payroll.php" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-md transition border border-slate-700">Gaji</a>
                <a href="financial_report.php" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-md transition border border-slate-700">Penyata LHDN</a>
                <a href="logout.php" class="px-3 py-1.5 bg-rose-950/40 text-rose-300 hover:bg-rose-900/50 rounded-md transition border border-rose-800/40 ml-auto md:ml-2">Logout</a>
            </nav>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 py-8">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
            
            <div class="lg:col-span-5 bg-white rounded-xl border border-slate-200/80 shadow-sm p-5 sticky top-20" id="crew-form">
                <div class="mb-4 border-b border-slate-100 pb-3 flex justify-between items-center">
                    <div>
                        <h2 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                            <span><?= $edit_crew ? '✏️ Kemaskini Profil Crew' : '➕ Daftar Crew Baharu'; ?></span>
                        </h2>
                        <p class="text-[11px] text-slate-500">Rekod lengkap IC & Perbankan untuk tujuan audit pencukaian.</p>
                    </div>
                    <?php if ($edit_crew): ?>
                        <a href="crew.php" class="text-xs text-slate-400 hover:text-slate-600 font-medium">✕ Batal</a>
                    <?php endif; ?>
                </div>
                
                <form action="api/save_crew.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                    <?php if ($edit_crew): ?>
                        <input type="hidden" name="crew_id" value="<?= $edit_crew['id']; ?>">
                    <?php endif; ?>

                    <div class="space-y-3">
                        <h4 class="text-[10px] font-bold uppercase tracking-wider text-indigo-600 border-b border-indigo-50 pb-1">1. Gambar & Profil Peribadi</h4>

                        <div>
                            <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Gambar Profil</label>
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full overflow-hidden bg-slate-100 border border-slate-200 shrink-0 flex items-center justify-center text-slate-400 font-bold text-sm">
                                    <?php if (!empty($edit_crew['profile_picture']) && file_exists($edit_crew['profile_picture'])): ?>
                                        <img src="<?= htmlspecialchars($edit_crew['profile_picture']); ?>" id="preview-img" class="w-full h-full object-cover">
                                    <?php else: ?>
                                        <img id="preview-img" class="w-full h-full object-cover hidden">
                                        <span id="preview-placeholder">📷</span>
                                    <?php endif; ?>
                                </div>
                                <div class="grow">
                                    <input type="file" name="profile_picture" accept="image/*" id="profile_input" onchange="previewFile()"
                                        class="w-full text-xs text-slate-500 file:mr-2 file:py-1 file:px-2.5 file:rounded-md file:border-0 file:text-[10px] file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 transition cursor-pointer">
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Nama Penuh *</label>
                            <input type="text" name="name" required placeholder="cth: Muhammad Zul" 
                                value="<?= htmlspecialchars($edit_crew['name'] ?? ''); ?>"
                                class="w-full text-xs px-3 py-2 border border-slate-200 rounded-lg bg-white text-slate-800 focus:outline-none focus:border-indigo-500 transition">
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">No. IC / KP * (Borang EA LHDN)</label>
                                <input type="text" name="ic_number" required placeholder="980101-10-5555" 
                                    value="<?= htmlspecialchars($edit_crew['ic_number'] ?? ''); ?>"
                                    class="w-full text-xs px-3 py-2 border border-slate-200 rounded-lg bg-white text-slate-800 focus:outline-none focus:border-indigo-500 transition">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Status Perkahwinan</label>
                                <select name="marital_status" class="w-full text-xs px-2 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-800 focus:outline-none focus:border-indigo-500 transition">
                                    <option value="single" <?= (isset($edit_crew['marital_status']) && $edit_crew['marital_status'] === 'single') ? 'selected' : ''; ?>>Bujang (Single)</option>
                                    <option value="married" <?= (isset($edit_crew['marital_status']) && $edit_crew['marital_status'] === 'married') ? 'selected' : ''; ?>>Berkahwin (Married)</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Jenis Pekerjaan *</label>
                                <select name="employment_type" required class="w-full text-xs px-2 py-2 border border-slate-200 rounded-lg bg-slate-50 font-medium text-slate-800 focus:outline-none focus:border-indigo-500 transition">
                                    <option value="parttime" <?= ($edit_crew['employment_type'] ?? '') === 'parttime' ? 'selected' : ''; ?>>Parttime / Freelance</option>
                                    <option value="fulltime" <?= ($edit_crew['employment_type'] ?? '') === 'fulltime' ? 'selected' : ''; ?>>Fulltime (Tetap)</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Peranan Utama *</label>
                                <input type="text" name="role" required placeholder="Photographer" 
                                    value="<?= htmlspecialchars($edit_crew['role'] ?? ''); ?>"
                                    class="w-full text-xs px-3 py-2 border border-slate-200 rounded-lg bg-white text-slate-800 focus:outline-none focus:border-indigo-500 transition">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3 bg-amber-50/60 p-2.5 rounded-lg border border-amber-200/80">
                            <div>
                                <label class="block text-[10px] font-bold uppercase text-amber-800 mb-1">Tarikh Mula Kerja</label>
                                <input type="date" name="start_date" 
                                    value="<?= htmlspecialchars($edit_crew['start_date'] ?? ''); ?>"
                                    class="w-full text-xs px-2.5 py-2 border border-amber-200 rounded-lg bg-white text-slate-800 focus:outline-none focus:border-amber-500 transition">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold uppercase text-amber-800 mb-1">Gaji Pokok (RM)</label>
                                <input type="number" step="0.01" name="basic_salary" placeholder="1500.00" 
                                    value="<?= htmlspecialchars($edit_crew['basic_salary'] ?? '0.00'); ?>"
                                    class="w-full text-xs px-3 py-2 border border-amber-200 rounded-lg bg-white text-slate-800 focus:outline-none focus:border-amber-500 transition font-mono font-bold">
                            </div>
                        </div>
                    </div>

                    <div class="space-y-3 pt-1">
                        <h4 class="text-[10px] font-bold uppercase tracking-wider text-indigo-600 border-b border-indigo-50 pb-1">2. No. Telefon & Kecemasan</h4>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">No. WhatsApp *</label>
                                <input type="tel" name="phone_number" required placeholder="0123456789" 
                                    value="<?= htmlspecialchars($edit_crew['phone_number'] ?? ''); ?>"
                                    class="w-full text-xs px-3 py-2 border border-slate-200 rounded-lg bg-white text-slate-800 focus:outline-none focus:border-indigo-500 transition">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">No. Kecemasan</label>
                                <input type="tel" name="emergency_phone" placeholder="0198765432" 
                                    value="<?= htmlspecialchars($edit_crew['emergency_phone'] ?? ''); ?>"
                                    class="w-full text-xs px-3 py-2 border border-slate-200 rounded-lg bg-white text-slate-800 focus:outline-none focus:border-indigo-500 transition">
                            </div>
                        </div>
                    </div>

                    <div class="space-y-3 pt-1">
                        <h4 class="text-[10px] font-bold uppercase tracking-wider text-indigo-600 border-b border-indigo-50 pb-1">3. Bank & Potongan Wajib</h4>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Nama Bank</label>
                                <input type="text" name="bank_name" placeholder="Maybank / CIMB" 
                                    value="<?= htmlspecialchars($edit_crew['bank_name'] ?? ''); ?>"
                                    class="w-full text-xs px-3 py-2 border border-slate-200 rounded-lg bg-white text-slate-800 focus:outline-none focus:border-indigo-500 transition">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">No. Akaun Bank</label>
                                <input type="text" name="bank_account_number" placeholder="1234567890" 
                                    value="<?= htmlspecialchars($edit_crew['bank_account_number'] ?? ''); ?>"
                                    class="w-full text-xs px-3 py-2 border border-slate-200 rounded-lg bg-white text-slate-800 focus:outline-none focus:border-indigo-500 transition">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">No. EPF / KWSP</label>
                                <input type="text" name="epf_number" placeholder="12345678" 
                                    value="<?= htmlspecialchars($edit_crew['epf_number'] ?? ''); ?>"
                                    class="w-full text-xs px-3 py-2 border border-slate-200 rounded-lg bg-white text-slate-800 focus:outline-none focus:border-indigo-500 transition">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">No. SOCSO</label>
                                <input type="text" name="socso_number" placeholder="K12345678" 
                                    value="<?= htmlspecialchars($edit_crew['socso_number'] ?? ''); ?>"
                                    class="w-full text-xs px-3 py-2 border border-slate-200 rounded-lg bg-white text-slate-800 focus:outline-none focus:border-indigo-500 transition">
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-500 text-white font-bold py-2.5 rounded-lg text-xs transition shadow mt-3">
                        💾 <?= $edit_crew ? 'Kemaskini Profil Crew' : 'Simpan Maklumat Crew'; ?>
                    </button>
                </form>
            </div>

            <div class="lg:col-span-7 bg-white rounded-xl border border-slate-200/80 shadow-sm overflow-hidden">
                <div class="p-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900">📋 Senarai Crew Berdaftar</h3>
                        <p class="text-[11px] text-slate-500">Profil ringkas, gaji dan maklumat perhubungan crew.</p>
                    </div>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-xs text-left text-slate-600">
                        <thead class="text-[10px] font-bold uppercase text-slate-400 bg-slate-50 border-b border-slate-100">
                            <tr>
                                <th class="px-3.5 py-3">Nama & Profil</th>
                                <th class="px-3 py-3">Peranan & Gaji</th>
                                <th class="px-3 py-3">Jenis Pekerja</th>
                                <th class="px-3 py-3">Bank & Caruman</th>
                                <th class="px-3.5 py-3 text-right">Tindakan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if ($crewQuery && $crewQuery->num_rows > 0): ?>
                                <?php while($c = $crewQuery->fetch_assoc()): ?>
                                    <tr class="hover:bg-slate-50/70 transition">
                                        <td class="px-3.5 py-3">
                                            <div class="flex items-center gap-2.5">
                                                <div class="w-8 h-8 rounded-full overflow-hidden bg-slate-100 border border-slate-200 shrink-0 flex items-center justify-center font-bold text-slate-400 text-xs">
                                                    <?php if (!empty($c['profile_picture']) && file_exists($c['profile_picture'])): ?>
                                                        <img src="<?= htmlspecialchars($c['profile_picture']); ?>" class="w-full h-full object-cover">
                                                    <?php else: ?>
                                                        <?= strtoupper(substr($c['name'], 0, 1)); ?>
                                                    <?php endif; ?>
                                                </div>
                                                <div>
                                                    <div class="font-bold text-slate-900"><?= htmlspecialchars($c['name']); ?></div>
                                                    <div class="text-[10px] font-mono text-slate-400">IC: <?= htmlspecialchars($c['ic_number'] ?? '-'); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-3 py-3 whitespace-nowrap">
                                            <div class="font-medium text-slate-800"><?= htmlspecialchars($c['role']); ?></div>
                                            <div class="text-[10px] font-mono text-emerald-600 font-bold mt-0.5">
                                                RM <?= number_format((float)($c['basic_salary'] ?? 0), 2); ?>
                                            </div>
                                        </td>
                                        <td class="px-3 py-3 whitespace-nowrap text-[11px]">
                                            <?php if(($c['employment_type'] ?? 'parttime') === 'fulltime'): ?>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-blue-50 text-blue-700 border border-blue-200">Fulltime</span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-purple-50 text-purple-700 border border-purple-200">Parttime</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-3 py-3 text-[10px]">
                                            <div class="font-medium text-slate-800">🏦 <?= !empty($c['bank_name']) ? htmlspecialchars($c['bank_name']) : '-'; ?></div>
                                            <div class="text-slate-500 font-mono"><?= !empty($c['bank_account_number']) ? htmlspecialchars($c['bank_account_number']) : '-'; ?></div>
                                        </td>
                                        <td class="px-3.5 py-3 text-right whitespace-nowrap space-x-1">
                                            <button onclick='viewCrewProfile(<?= htmlspecialchars(json_encode($c), ENT_QUOTES, "UTF-8"); ?>)' 
                                                    class="px-2 py-1 bg-indigo-50 border border-indigo-200 text-indigo-700 font-semibold text-[10px] rounded hover:bg-indigo-100 transition inline-block">
                                                👁️ Lihat
                                            </button>
                                            <a href="crew.php?edit_id=<?= $c['id']; ?>#crew-form" 
                                               class="px-2 py-1 bg-white border border-slate-200 text-slate-700 font-semibold text-[10px] rounded hover:bg-slate-50 transition inline-block">
                                                ✏️ Edit
                                            </a>
                                            <a href="api/delete_crew.php?id=<?= $c['id']; ?>" 
                                               onclick="return confirm('Adakah anda pasti mahu memadam crew <?= htmlspecialchars(addslashes($c['name'])); ?>?');"
                                               class="px-2 py-1 bg-rose-50 border border-rose-200 text-rose-700 font-semibold text-[10px] rounded hover:bg-rose-100 transition inline-block">
                                                🗑️
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="p-6 text-center text-slate-400">Belum ada crew didaftarkan.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

    <div id="viewCrewModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden">
        <div class="bg-white rounded-xl shadow-xl border border-slate-200 max-w-lg w-full overflow-hidden" id="printableProfileArea">
            <div class="px-5 py-3.5 bg-slate-900 text-white flex justify-between items-center no-print">
                <h3 class="text-xs font-bold uppercase tracking-wide text-slate-200">Maklumat Profil Crew</h3>
                <button type="button" onclick="closeCrewModal()" class="text-slate-400 hover:text-white transition text-base font-bold">✕</button>
            </div>

            <div class="p-5 space-y-4">
                <div class="flex items-center gap-4 pb-4 border-b border-slate-100">
                    <div class="w-14 h-14 rounded-full overflow-hidden bg-slate-100 border border-slate-200 shrink-0 flex items-center justify-center text-slate-400 text-xl font-bold">
                        <img id="modal_profile_picture" src="" class="w-full h-full object-cover hidden">
                        <span id="modal_avatar_placeholder">👤</span>
                    </div>
                    <div>
                        <h4 id="modal_name" class="text-base font-bold text-slate-900 leading-tight">---</h4>
                        <div class="flex items-center gap-2 mt-1">
                            <span id="modal_role" class="text-xs text-indigo-600 font-bold">---</span>
                            <span id="modal_employment_type" class="px-1.5 py-0.5 text-[9px] font-bold rounded bg-slate-100 text-slate-700 uppercase">---</span>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-x-4 gap-y-2.5 text-xs">
                    <div>
                        <span class="text-[10px] uppercase font-bold text-slate-400 block">No. IC / KP</span>
                        <span id="modal_ic_number" class="font-mono text-slate-800">-</span>
                    </div>
                    <div>
                        <span class="text-[10px] uppercase font-bold text-slate-400 block">Status Perkahwinan</span>
                        <span id="modal_marital_status" class="font-medium text-slate-800">-</span>
                    </div>
                    <div>
                        <span class="text-[10px] uppercase font-bold text-slate-400 block">No. WhatsApp</span>
                        <span id="modal_phone_number" class="font-mono text-slate-800">-</span>
                    </div>
                    <div>
                        <span class="text-[10px] uppercase font-bold text-slate-400 block">No. Kecemasan</span>
                        <span id="modal_emergency_phone" class="font-mono text-slate-800">-</span>
                    </div>
                    <div>
                        <span class="text-[10px] uppercase font-bold text-slate-400 block">Tarikh Mula Kerja</span>
                        <span id="modal_start_date" class="font-medium text-slate-800">-</span>
                    </div>
                    <div>
                        <span class="text-[10px] uppercase font-bold text-slate-400 block">Gaji Pokok</span>
                        <span id="modal_basic_salary" class="font-bold text-emerald-600 font-mono">RM 0.00</span>
                    </div>
                    <div class="col-span-2 pt-2 border-t border-slate-100">
                        <span class="text-[10px] uppercase font-bold text-slate-400 block">Maklumat Perbankan</span>
                        <span id="modal_bank_info" class="font-medium text-slate-800">-</span>
                    </div>
                    <div class="col-span-2">
                        <span class="text-[10px] uppercase font-bold text-slate-400 block">No. KWSP / PERKESO</span>
                        <span id="modal_statutory_info" class="font-medium text-slate-800">-</span>
                    </div>
                </div>
            </div>

            <div class="px-5 py-3 bg-slate-50 border-t border-slate-100 flex justify-between items-center no-print">
                <button type="button" onclick="printProfile()" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-white text-xs font-bold rounded-lg transition">
                    🖨️ Cetak Profil
                </button>
                <button type="button" onclick="closeCrewModal()" class="px-3 py-1.5 bg-slate-200 hover:bg-slate-300 text-slate-700 text-xs font-bold rounded-lg transition">
                    Tutup
                </button>
            </div>
        </div>
    </div>

    <script>
        let currentCrewData = null;

        function previewFile() {
            const preview = document.getElementById('preview-img');
            const placeholder = document.getElementById('preview-placeholder');
            const file = document.getElementById('profile_input').files[0];
            const reader = new FileReader();

            reader.addEventListener("load", function () {
                preview.src = reader.result;
                preview.classList.remove('hidden');
                if (placeholder) placeholder.classList.add('hidden');
            }, false);

            if (file) { reader.readAsDataURL(file); }
        }

        function viewCrewProfile(crew) {
            currentCrewData = crew;
            document.getElementById('modal_name').innerText = crew.name || '-';
            document.getElementById('modal_role').innerText = crew.role || '-';
            document.getElementById('modal_employment_type').innerText = (crew.employment_type === 'fulltime') ? 'Fulltime' : 'Parttime';
            document.getElementById('modal_ic_number').innerText = crew.ic_number || '-';
            document.getElementById('modal_marital_status').innerText = crew.marital_status === 'married' ? 'Berkahwin' : 'Bujang';
            document.getElementById('modal_phone_number').innerText = crew.phone_number || '-';
            document.getElementById('modal_emergency_phone').innerText = crew.emergency_phone || '-';
            document.getElementById('modal_start_date').innerText = crew.start_date || '-';
            document.getElementById('modal_basic_salary').innerText = 'RM ' + parseFloat(crew.basic_salary || 0).toFixed(2);
            document.getElementById('modal_bank_info').innerText = (crew.bank_name || '-') + ' (' + (crew.bank_account_number || '-') + ')';
            document.getElementById('modal_statutory_info').innerText = 'EPF: ' + (crew.epf_number || '-') + ' | SOCSO: ' + (crew.socso_number || '-');

            const imgEl = document.getElementById('modal_profile_picture');
            const placeholderEl = document.getElementById('modal_avatar_placeholder');
            
            if (crew.profile_picture && crew.profile_picture.trim() !== '') {
                imgEl.src = crew.profile_picture;
                imgEl.classList.remove('hidden');
                placeholderEl.classList.add('hidden');
            } else {
                imgEl.classList.add('hidden');
                placeholderEl.classList.remove('hidden');
            }

            document.getElementById('viewCrewModal').classList.remove('hidden');
        }

        function closeCrewModal() { document.getElementById('viewCrewModal').classList.add('hidden'); }
        function printProfile() { window.print(); }
    </script>
</body>
</html>