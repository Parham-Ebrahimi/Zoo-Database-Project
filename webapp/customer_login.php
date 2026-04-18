<?php
session_start();
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        header('Location: login.html?error=' . rawurlencode('All fields are required'));
        exit;
    }

    $stmt = $pdo->prepare("SELECT CustomerID, FirstName, Password_Hash FROM customers WHERE Email = ?");
    $stmt->execute([$email]);
    $customer = $stmt->fetch();

    if ($customer && password_verify($password, $customer['Password_Hash'])) {
        $_SESSION['customer_id']   = $customer['CustomerID'];
        $_SESSION['firstname']     = $customer['FirstName'];
        $_SESSION['role']          = 'customer';
        header('Location: customer-dashboard.php');
        exit;
    }

    header('Location: login.html?error=' . rawurlencode('Invalid email or password'));
    exit;
}
?>
