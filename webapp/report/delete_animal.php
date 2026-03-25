<?php
require '../db.php';

$id = $_GET["id"];

$stmt = $db->prepare("DELETE FROM animals WHERE AnimalID = ?");
$stmt->bind_param("i", $id);
$stmt->execute();

header("Location: animals_report.php");
exit();
?>
