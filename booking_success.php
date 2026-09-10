<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$type = isset($_GET['type']) && $_GET['type'] === 'corporate' ? 'corporate' : 'wedding';
$already = isset($_GET['already']) && $_GET['already'] == 1;
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tempahan Berjaya - Kaia Film</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style> body { font-family: 'Inter', sans-serif; } </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-md w-full bg-slate-900 border border-slate-800 rounded-2xl p-8 text-center shadow-2xl space-y-5">
        
        <?php if ($already): ?>
            <div class="w-16 h-16 bg-amber-500/10 text-amber-400 rounded-full flex items-center justify-center mx-auto text-3xl font-bold border border-amber-500/20">
                🔒
            </div>
            <h1 class="text-xl font-bold text-white">Borang Telah Dihantar Sebelum Ini</h1>
            <p class="text-xs text-slate-300 leading-relaxed">
                Anda telah pun menghantar Borang Tempahan <span class="font-bold text-indigo-400"><?= $type === 'corporate' ? 'Korporat' : 'Perkahwinan'; ?></span> ini. Pihak kami sedang memproses permohonan anda.
            </p>
        <?php else: ?>
            <div class="w-16 h-16 bg-emerald-500/10 text-emerald-400 rounded-full flex items-center justify-center mx-auto text-3xl font-bold border border-emerald-500/20">
                ✓
            </div>
            <h1 class="text-xl font-bold text-white">Tempahan <?= $type === 'corporate' ? 'Korporat' : 'Perkahwinan'; ?> Berjaya!</h1>
            <p class="text-xs text-slate-300 leading-relaxed">
                Terima kasih kerana memilih <span class="font-bold text-white">Kaia Film</span>. Maklumat borang tempahan anda telah selamat diterima.
            </p>
        <?php endif; ?>

        <div class="bg-slate-950/80 p-4 rounded-xl border border-slate-800 text-left text-xs space-y-2 text-slate-400">
            <p>📌 <strong class="text-slate-200">Langkah Seterusnya:</strong></p>
            <p>1. Pihak admin kami akan menyemak tarikh & maklumat pakej anda.</p>
            <p>2. Invois rasmi serta maklumat bayaran akan dihantar terus oleh Admin menerusi WhatsApp / E-mel.</p>
        </div>

        <p class="text-[11px] text-slate-500 italic">
            *Untuk keselamatan, satu peranti/browser hanya dibenarkan menghantar borang ini sekali sahaja.
        </p>
    </div>
</body>
</html>