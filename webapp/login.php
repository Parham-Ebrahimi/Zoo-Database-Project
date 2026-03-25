<?php
session_start();
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        header('Location: login.html?error=All fields are required');
        exit;
    }

    // Check if customer exists
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE Email = ?");
    $stmt->execute([$email]);
    $customer = $stmt->fetch();

    if ($customer && password_verify($password, $customer['Password_Hash'])) {
        // Password is correct, start session
        $_SESSION['user_id'] = $customer['CustomerID'];
        $_SESSION['firstname'] = $customer['FirstName'];
        $_SESSION['lastname'] = $customer['LastName'];
        $_SESSION['email'] = $customer['Email'];
        $_SESSION['role'] = 'customer';

        header('Location: dashboard.php');
        exit;
    } else {
        header('Location: login.html?error=Invalid email or password');
        exit;
    }
}
?>