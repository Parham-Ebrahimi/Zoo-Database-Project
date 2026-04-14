<?php
session_start();
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.html');
    exit;
}

$identifier = trim($_POST['identifier'] ?? '');
$password = $_POST['password'] ?? '';

if ($identifier === '' || $password === '') {
    header('Location: login.html?error=' . rawurlencode('All fields are required'));
    exit;
}

/** Remove the other role’s session keys before setting a new login. */
function clear_auth_session(): void
{
    unset(
        $_SESSION['user_id'],
        $_SESSION['customer_id'],
        $_SESSION['firstname'],
        $_SESSION['role']
    );
}

// Staff: lookup by username. If the row exists, only that account is tried (no fallback on bad password).
$stmt = $pdo->prepare(
    'SELECT s.UserID, s.PasswordHash, s.Role, e.FirstName
     FROM systemuser s
     JOIN employees e ON s.EmployeeID = e.EmployeeID
     WHERE s.Username = ?'
);
$stmt->execute([$identifier]);
$staff = $stmt->fetch(PDO::FETCH_ASSOC);

if ($staff) {
    if (password_verify($password, $staff['PasswordHash'])) {
        clear_auth_session();
        $_SESSION['user_id'] = $staff['UserID'];
        $_SESSION['firstname'] = $staff['FirstName'];
        $_SESSION['role'] = $staff['Role'];
        header('Location: admin-dashboard.php');
        exit;
    }
    header('Location: login.html?error=' . rawurlencode('Invalid email/username or password'));
    exit;
}

// Customer: lookup by email
$stmt = $pdo->prepare('SELECT CustomerID, FirstName, Password_Hash FROM customers WHERE Email = ?');
$stmt->execute([$identifier]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

if ($customer && password_verify($password, $customer['Password_Hash'])) {
    clear_auth_session();
    $_SESSION['customer_id'] = (int) $customer['CustomerID'];
    $_SESSION['firstname'] = $customer['FirstName'];
    $_SESSION['role'] = 'customer';
    header('Location: customer-dashboard.php');
    exit;
}

header('Location: login.html?error=' . rawurlencode('Invalid email/username or password'));
exit;
