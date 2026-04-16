<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit;
}
if (!in_array($_SESSION['role'], ['admin', 'caretaker', 'vet'], true)) {
    die('Access denied');
}
require_once 'db.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: animals_report.php');
    exit;
}

$stmt = $pdo->prepare("DELETE FROM animal WHERE Animal_ID = ?");
$stmt->execute([$id]);

header("Location: animals_report.php");
exit();
?>
