<?php
require_once 'config/db.php';
require_once 'config/auth.php';

$generated_link = '';
if (isset($_POST['generate'])) {
    $category = $_POST['category'];
    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
    $generated_link = $baseUrl . "/wedding-app/booking.php?type=" . $category;
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <title>Jana Pautan Borang Tempahan</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-6">
    <div class="max-w-md mx-auto bg-white p-6 rounded-xl shadow-md border">
        <h2 class="text-lg font-bold mb-4">🔗 Jana Pautan Borang Tempahan</h2>
        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-xs font-bold uppercase mb-1">Pilih Jenis Majlis</label>
                <select name="category" class="w-full border p-2 rounded-lg text-sm" required>
                    <option value="wedding">Perkahwinan / Majlis Peribadi</option>
                    <option value="corporate">Syarikat / Korporat</option>
                </select>
            </div>
            <button type="submit" name="generate" class="w-full bg-emerald-600 text-white font-bold py-2 rounded-lg text-sm">
                Jana Pautan
            </button>
        </form>

        <?php if ($generated_link): ?>
            <div class="mt-4 p-3 bg-gray-50 border rounded-lg">
                <p class="text-xs text-gray-500 mb-1">Pautan Borang:</p>
                <input type="text" value="<?= $generated_link; ?>" id="linkInput" readonly class="w-full text-xs p-2 border rounded bg-white font-mono">
                <button onclick="navigator.clipboard.writeText(document.getElementById('linkInput').value); alert('Pautan disalin!');" class="mt-2 w-full bg-blue-600 text-white text-xs py-1.5 rounded font-bold">
                    📋 Salin Pautan
                </button>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>