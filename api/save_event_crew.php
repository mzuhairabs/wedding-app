<?php
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $event_id      = $_POST['event_id'];
    $crew_id       = $_POST['crew_id'];
    $assigned_role = $_POST['assigned_role'];
    $pay_amount    = $_POST['pay_amount'] ?? 0.00;

    $stmt = $conn->prepare("INSERT INTO event_crew (event_id, crew_id, assigned_role, pay_amount) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iisd", $event_id, $crew_id, $assigned_role, $pay_amount);

    if ($stmt->execute()) {
        echo "<script>
            alert('Crew berjaya ditugaskan!');
            window.location.href = '../assign_crew.php?event_id=$event_id';
        </script>";
    } else {
        echo "Ralat: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
}
?>