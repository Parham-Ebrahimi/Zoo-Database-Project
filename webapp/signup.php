<?php
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstname = trim($_POST['firstname']);
    $lastname  = trim($_POST['lastname']);
    $countrycode = $_POST['countrycode'];
    $phone = $_POST['phone'];
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $repeat = $_POST['repeat-password'];

    // Basic validation
    if (empty($firstname) || empty($lastname) || empty($email) || empty($password) || empty($countrycode) || empty($phone)) {
        header('Location: signup.html?error=All fields are required');
        exit;
    }

    if ($password !== $repeat) {
        header('Location: signup.html?error=Passwords do not match');
        exit;
    }

    // Check if email already exists
    $check = $pdo->prepare("SELECT CustomerID FROM customers WHERE Email = ?");
    $check->execute([$email]);
    if ($check->rowCount() > 0) {
        header('Location: signup.html?error=Email already registered');
        exit;
    }

    // Hash the password
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $date = date('Y-m-d');

    // Insert into database
    $stmt = $pdo->prepare("INSERT INTO customers 
        (FirstName, LastName, Email, Password_Hash, RegistrationDate, CountryCode, PhoneNumber) 
        VALUES (?, ?, ?, ?, ?, ?, ?)");
    
    $stmt->execute([
        $firstname,
        $lastname,
        $email,
        $hash,
        $date,
        $countrycode,
        $phone
    ]);

    header('Location: sign-in.html?success=' . rawurlencode('Account created! Please sign in.'));
    exit;
}
?>