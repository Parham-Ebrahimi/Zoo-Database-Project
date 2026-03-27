<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit;
}
require 'db.php';
 
$id = $_GET['id'];
$stmt = $pdo->prepare("DELETE FROM animal WHERE Animal_ID = ?");
$stmt->execute([$id]);
 
header('Location: animals_report.php');
exit;
?>
 