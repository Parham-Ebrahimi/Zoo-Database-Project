<?php
require_once __DIR__ . '/session_bootstrap.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit;
}
if (!in_array(strtolower(trim((string) ($_SESSION['role'] ?? ''))), ['admin'], true)) {
    header('HTTP/1.1 403 Forbidden');
    exit('Access denied');
}
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: employees_report.php');
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    header('Location: employees_report.php');
    exit;
}

try {
    $stmt = $pdo->prepare('DELETE FROM employees WHERE EmployeeID = ?');
    $stmt->execute([$id]);
    header('Location: employees_report.php?deleted=1');
} catch (PDOException $e) {
    $msg = 'Could not delete this employee. They may still be linked to animals, health records, orders, or a login account.';
    header('Location: employees_report.php?error=' . rawurlencode($msg));
}
exit;
