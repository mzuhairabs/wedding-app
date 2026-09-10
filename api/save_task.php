<?php
// Aktifkan pameran ralat untuk tujuan penyemakan (debugging)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $event_id  = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
    $task_name = trim($_POST['task_name'] ?? '');
    $due_date  = !empty($_POST['due_date']) ? $_POST['due_date'] : NULL;

    if ($event_id > 0 && !empty($task_name)) {
        
        if ($due_date) {
            $stmt = $conn->prepare("INSERT INTO event_tasks (event_id, task_name, due_date) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $event_id, $task_name, $due_date);
        } else {
            $stmt = $conn->prepare("INSERT INTO event_tasks (event_id, task_name) VALUES (?, ?)");
            $stmt->bind_param("is", $event_id, $task_name);
        }

        if ($stmt->execute()) {
            // Gunakan JavaScript Redirect untuk mengelakkan isu Header Browser
            echo "<script>
                alert('Tugasan berjaya ditambah!');
                window.location.href = '../manage_tasks.php?event_id=" . $event_id . "';
            </script>";
            exit();
        } else {
            die("Ralat Pangkalan Data: " . $stmt->error);
        }

        $stmt->close();
    } else {
        die("Sila isi nama tugasan dengan lengkap.");
    }
} else {
    die("Akses tidak dibenarkan.");
}
$conn->close();
?>