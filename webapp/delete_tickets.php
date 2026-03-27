<?php
require_once 'db.php';

$id = (int)($_GET["id"] ?? 0);

$stmt = $pdo->prepare("DELETE FROM tickets WHERE Ticket_ID = ?");
$stmt->execute([$id]);

header("Location: tickets_report.php");
exit();
?>
