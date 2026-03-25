<?php
require '../db.php';

$id = $_GET["id"];

$stmt = $db->prepare("DELETE FROM tickets WHERE TicketID = ?");
$stmt->bind_param("i", $id);
$stmt->execute();

header("Location: tickets_report.php");
exit();
?>
