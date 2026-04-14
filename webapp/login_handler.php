<?php
session_start();
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.html');
    exit;
}

$login = trim($_POST['login'] ?? '');
$password = $_POST['password'] ?? '';

if ($login === '' || $password === '') {
    header('Location: login.html?error=' . urlencode('All fields are required'));
    exit;
}

//Customer (email in the form)
$stmt = $pdo->prepare(
    'SELECT CustomerID, FirstName, Password_Hash 
    FROM customers 
    WHERE Email = ?
');

$stmt->execute([$login]);
$customer = $stmt->fetch();

if ($customer && password_verify($password, $customer['Password_Hash'])) {
    unset($_SESSION['user_id']);

    $_SESSION['customer_id'] = $customer['CustomerID'];
    $_SESSION['firstname'] = $customer['FirstName'];
    $_SESSION['role'] = 'customer';

    header('Location: customer-dashboard.php');
    exit;
}

// Staff (same box: type your system username)
$stmt = $pdo->prepare(
    'SELECT s.UserID, s.Username, s.PasswordHash, s.Role, e.FirstName
    FROM systemuser s
    JOIN employees e ON s.EmployeeID = e.EmployeeID
    WHERE s.Username = ?
');

$stmt->execute([$login]);
$user = $stmt->fetch();

if ($user && password_verify($password, $user['PasswordHash'])) {
    unset($_SESSION['customer_id']);
    $_SESSION['user_id'] = $user['UserID'];
    $_SESSION['firstname'] = $user['FirstName'];
    $_SESSION['role'] = $user['Role'];

    if ($user['Role'] === 'admin') {
        header('location: dashboard.php');
    }
    else if ($user['Role'] === 'caretaker') {
        header('location: caretaker.php');
    }
    else {
        header ('Location: dashboard.php');
    }

    exit;
}

header('Location: login.html?error=' . urlencode('Invalid email or password'));
exit;