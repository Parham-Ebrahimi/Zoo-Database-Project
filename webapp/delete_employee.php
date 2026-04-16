<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit;
}
if (!in_array($_SESSION['role'], ['admin'], true)) {
    die('Access denied');
}
require_once 'db.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: employees_report.php');
    exit;
}

$stmt = $pdo->prepare("DELETE FROM employees WHERE EmployeeID = ?");
$stmt->execute([$id]);

header("Location: employees_report.php");
exit();
?>
