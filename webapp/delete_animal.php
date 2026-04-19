<?php
require_once __DIR__ . '/session_bootstrap.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit;
}
require_once 'staff_home.php';
$roleGate = strtolower(trim((string) ($_SESSION['role'] ?? '')));
if (!in_array($roleGate, ['admin', 'caretaker', 'vet', 'keeper'], true) && !staff_is_vet_role()) {
    header('HTTP/1.1 403 Forbidden');
    exit('Access denied');
}
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: animals_report.php');
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    header('Location: animals_report.php');
    exit;
}

try {
    $stmt = $pdo->prepare('DELETE FROM animal WHERE Animal_ID = ?');
    $stmt->execute([$id]);
    header('Location: animals_report.php?deleted=1');
} catch (PDOException $e) {
    $msg = 'Could not delete this animal. It may still be linked to health records, feeding data, or other zoo data.';
    header('Location: animals_report.php?error=' . rawurlencode($msg));
}
exit;
