<?php
session_start();
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: customer-login.html');
    exit;
}

$login = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if ($login === '' || $password === '') {
    header('Location: customer-login.html?error=' . urlencode('All fields are required'));
    exit;
}

// --- Customer (email in the form) ---
$stmt = $pdo->prepare('SELECT CustomerID, FirstName, Password_Hash FROM customers WHERE Email = ?');
$stmt->execute([$login]);
$customer = $stmt->fetch();

if ($customer && password_verify($password, $customer['Password_Hash'])) {
    unset($_SESSION['user_id']);
    $_SESSION['customer_id'] = $customer['CustomerID'];
    $_SESSION['firstname'] = $customer['FirstName'];
    $_SESSION['role'] = 'customer';
    header('Location: index.html');
    exit;
}

// --- Staff (same box: type your system username) ---
$stmt = $pdo->prepare('SELECT s.*, e.FirstName FROM systemuser s
                       JOIN employees e ON s.EmployeeID = e.EmployeeID
                       WHERE s.Username = ?');
$stmt->execute([$login]);
$user = $stmt->fetch();

if ($user && password_verify($password, $user['PasswordHash'])) {
    unset($_SESSION['customer_id']);
    $_SESSION['user_id'] = $user['UserID'];
    $_SESSION['firstname'] = $user['FirstName'];
    $_SESSION['role'] = $user['Role'];

    $role = strtolower((string) $user['Role']);
    if ($role === 'admin') {
        header('Location: admin-dashboard.php');
        exit;
    }
    if ($role === 'employee') {
        header('Location: admin-dashboard.php');
        exit;
    }
    header('Location: admin-dashboard.php');
    exit;
}

header('Location: customer-login.html?error=' . urlencode('Invalid email or password'));
exit;
