<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['email']) && isset($_POST['username'])) {
        $_POST['email'] = trim((string) $_POST['username']);
    }
    require __DIR__ . '/customer_login.php';
    exit;
}
header('Location: customer-login.html');
exit;
