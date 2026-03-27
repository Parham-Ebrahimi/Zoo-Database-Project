<?php
require_once __DIR__ . '/../db.php';

$id = (int)($_GET["id"] ?? 0);

$stmt = $pdo->prepare("DELETE FROM tickets WHERE TicketID = ?");
$stmt->execute([$id]);

header("Location: tickets_report.php");
exit();
?>
