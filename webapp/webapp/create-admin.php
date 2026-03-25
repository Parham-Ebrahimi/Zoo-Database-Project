<?php
require_once 'db.php';

$username = 'admin';
$password = password_hash('admin123', PASSWORD_BCRYPT);
$role = 'admin';
$employeeID = 1;

// First insert a test employee
$stmt = $pdo->prepare("INSERT INTO employees 
    (EmployeeID, Role, Salary, HireDate, FirstName, LastName, Sex, Address, DOB, Race) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->execute([1, 'Admin', 50000, '2024-01-01', 'Admin', 'User', 'M', '123 Zoo St', '1990-01-01', 'N/A']);

// Then insert the system user
$stmt2 = $pdo->prepare("INSERT INTO systemuser (EmployeeID, Username, PasswordHash, Role) 
    VALUES (?, ?, ?, ?)");
$stmt2->execute([$employeeID, $username, $password, $role]);

echo "Admin created successfully!";
?>