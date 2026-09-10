<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $task_id  = isset($_POST['task_id']) ? intval($_POST['task_id']) : 0;
    $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
    $status   = $_POST['status'] ?? 'pending';

    if ($task_id > 0 && $event_id > 0) {
        $stmt = $conn->prepare("UPDATE event_tasks SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $task_id);

        if ($stmt->execute()) {
            header("Location: ../manage_tasks.php?event_id=" . $event_id);
            exit();
        } else {
            die("Ralat Kemaskini: " . $stmt->error);
        }

        $stmt->close();
    }
}
$conn->close();
?>