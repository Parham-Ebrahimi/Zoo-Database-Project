<?php
require_once __DIR__ . '/../db.php';

$id = $_GET["id"];

$stmt = $pdo->prepare("DELETE FROM animals WHERE AnimalID = ?");
$stmt->execute([(int)$id]);

header("Location: animals_report.php");
exit();
?>
