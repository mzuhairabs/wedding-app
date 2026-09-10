<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/db.php';   

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$categories = $conn->query("SELECT * FROM expense_categories ORDER BY category_name ASC");
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rekod Perbelanjaan - Studio Manager</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 text-slate-800 text-xs font-sans min-h-screen">

    <header class="bg-slate-900 text-white sticky top-0 z-30 shadow-md">
        <div class="max-w-7xl mx-auto px-4 py-3.5 flex justify-between items-center">
            <div class="flex items-center space-x-3">
                <span class="text-xl">💸</span>
                <div>
                    <h1 class="text-sm font-bold">Rekod Perbelanjaan Perniagaan (Audit LHDN)</h1>
                    <p class="text-[11px] text-slate-400">Muat naik resit dan failkan mengikut kriteria perbelanjaan dibenarkan</p>
                </div>
            </div>
            <a href="index.php" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-md border border-slate-700">← Dashboard</a>
        </div>
    </header>

    <main class="max-w-xl mx-auto px-4 py-8">
        <div class="bg-white rounded-xl border border-slate-200/80 shadow-sm p-6 space-y-4">
            <div class="border-b pb-3">
                <h2 class="text-sm font-bold text-slate-900">📸 Borang Tuntutan / Perbelanjaan</h2>
                <p class="text-slate-500">Gunakan kamera telefon untuk mengambil gambar resit asal bagi tujuan simpanan cukai.</p>
            </div>

            <form action="api/save_expense.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Tangkap Gambar / Muat Naik Resit Asal *</label>
                    <input type="file" name="receipt_image" accept="image/*" capture="environment" required 
                        class="w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 border border-slate-200 rounded-lg bg-slate-50">
                    <p class="text-[10px] text-slate-400 mt-1">Gambar mestilah memaparkan nama kedai, tarikh, dan nilai cukai/RM yang jelas.</p>
                </div>

                <div>
                    <label class="block font-bold text-slate-700 mb-1">Nama Pembekal / Merchant / Freelancer *</label>
                    <input type="text" name="merchant_name" placeholder="cth: Adobe / Canon Store / Ahmad Videographer" required 
                        class="w-full border border-slate-200 rounded-lg p-2.5 bg-white text-slate-800 font-medium focus:outline-none focus:border-indigo-500">
                </div>

                <div>
                    <label class="block font-bold text-slate-700 mb-1">Kategori Potongan Cukai LHDN *</label>
                    <select name="category_id" required class="w-full border border-slate-200 rounded-lg p-2.5 bg-slate-50 font-medium focus:outline-none focus:border-indigo-500">
                        <option value="">-- Pilih Kategori --</option>
                        <?php while($row = $categories->fetch_assoc()): ?>
                            <option value="<?= $row['id']; ?>"><?= htmlspecialchars($row['category_name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Jumlah (RM) *</label>
                        <input type="number" step="0.01" name="amount" placeholder="0.00" required 
                            class="w-full border border-slate-200 rounded-lg p-2.5 text-sm font-bold font-mono text-slate-900 bg-white focus:outline-none focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Tarikh Transaksi *</label>
                        <input type="date" name="expense_date" value="<?= date('Y-m-d'); ?>" required 
                            class="w-full border border-slate-200 rounded-lg p-2.5 bg-white focus:outline-none focus:border-indigo-500">
                    </div>
                </div>

                <div>
                    <label class="block font-bold text-slate-700 mb-1">Keterangan / Tujuan Servis</label>
                    <textarea name="description" rows="2" placeholder="cth: Bayaran sewa lens untuk job corporate syarikat ABC" 
                        class="w-full border border-slate-200 rounded-lg p-2.5 bg-white focus:outline-none focus:border-indigo-500"></textarea>
                </div>

                <button type="submit" 
                    class="w-full bg-indigo-600 hover:bg-indigo-500 text-white font-bold py-3 rounded-lg shadow transition">
                    💾 Simpan Perbelanjaan
                </button>
            </form>
        </div>
    </main>
</body>
</html>