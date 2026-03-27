<?php
require_once 'db.php';

$id = $_GET["id"];

$stmt = $pdo->prepare("DELETE FROM animal WHERE Animal_ID = ?");
$stmt->execute([(int)$id]);

header("Location: animals_report.php");
exit();
?>
