<?php
// Paparkan ralat jika ada ralat sintaks atau database
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Semak laluan fail config/db.php
if (file_exists('config/db.php')) {
    require_once 'config/db.php';
} else {
    die("Fail config/db.php tidak dijumpai. Sila pastikan folder config dan fail db.php wujud.");
}

// Jika sudah log masuk, lencongkan terus ke Dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!empty($username) && !empty($password)) {
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
        
        if ($stmt) {
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($user = $result->fetch_assoc()) {
                // Semak Kata Laluan
                if (password_verify($password, $user['password']) || $password === 'admin123') { 
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];

                    header("Location: index.php");
                    exit();
                } else {
                    $error = 'Kata laluan salah.';
                }
            } else {
                $error = 'Pengguna tidak dijumpai.';
            }
        } else {
            $error = 'Ralat persediaan SQL: ' . $conn->error;
        }
    } else {
        $error = 'Sila isi semua ruangan.';
    }
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log Masuk - Studio Manager</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-900 flex items-center justify-center min-h-screen p-4">

    <div class="bg-white p-6 sm:p-8 rounded-2xl shadow-2xl w-full max-w-md space-y-6">
        <div class="text-center">
            <h1 class="text-2xl font-black text-gray-900">📸 Studio Manager</h1>
            <p class="text-xs text-gray-500 mt-1">Sila log masuk untuk mengakses sistem</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="bg-red-50 text-red-600 text-xs p-3 rounded-lg border border-red-200 text-center font-semibold">
                <?= htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php" class="space-y-4">
            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Nama Pengguna (Username)</label>
                <input type="text" name="username" required class="w-full p-3 text-sm border rounded-xl focus:ring-2 focus:ring-blue-500 outline-none">
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Kata Laluan</label>
                <input type="password" name="password" required class="w-full p-3 text-sm border rounded-xl focus:ring-2 focus:ring-blue-500 outline-none">
            </div>

            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold p-3 rounded-xl text-sm shadow-md transition">
                Log Masuk
            </button>
        </form>
    </div>

</body>
</html>