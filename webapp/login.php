<?php
session_start();
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];


// username & password is required, error if left empty
    if (empty($username) || empty($password)) {
        header('Location: login.html?error=All fields are required');
        exit;
    }

    $stmt = $pdo->prepare("SELECT s.*, e.FirstName FROM systemuser s 
                           JOIN employees e ON s.EmployeeID = e.EmployeeID 
                           WHERE s.Username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['PasswordHash'])) {
        $_SESSION['user_id']   = $user['UserID'];
        $_SESSION['firstname'] = $user['FirstName'];
        $_SESSION['role']      = $user['Role'];
        header('Location: admin-dashboard.php'); // takes you to admin dashboard
        exit;
    }

    header('Location: login.html?error=Invalid username or password');
    exit;
}
?>