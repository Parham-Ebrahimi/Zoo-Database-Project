<?php
require_once 'db.php';

$username = 'admin';
$password = 'admin123';
$role = 'admin';
$employeeID = 1;

// Create admin only if it doesn't already exist (prevents duplicate-key errors).
try {
    $checkEmp = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE EmployeeID = ?");
    $checkEmp->execute([$employeeID]);
    $employeeExists = (int) $checkEmp->fetchColumn();

    if ($employeeExists === 0) {
        $stmt = $pdo->prepare("INSERT INTO employees
            (EmployeeID, Role, Salary, HireDate, FirstName, LastName, Sex, Address, DOB, Race)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $employeeID,
            'Admin',
            50000,
            '2024-01-01',
            'Admin',
            'User',
            'M',
            '123 Zoo St',
            '1990-01-01',
            'N/A',
        ]);
        echo "Employee inserted.<br>";
    } else {
        echo "Employee already exists, skipping employee insert.<br>";
    }

    $checkUser = $pdo->prepare("SELECT COUNT(*) FROM systemuser WHERE EmployeeID = ? OR Username = ?");
    $checkUser->execute([$employeeID, $username]);
    $userExists = (int) $checkUser->fetchColumn();

    $passwordHash = password_hash($password, PASSWORD_BCRYPT);

    if ($userExists === 0) {
        $stmt2 = $pdo->prepare("INSERT INTO systemuser (EmployeeID, Username, PasswordHash, Role)
            VALUES (?, ?, ?, ?)");
        $stmt2->execute([$employeeID, $username, $passwordHash, $role]);
        echo "System user inserted.<br>";
    } else {
        // Keep it simple: update password+role so re-running the script is safe.
        $update = $pdo->prepare("UPDATE systemuser
            SET PasswordHash = ?, Role = ?
            WHERE EmployeeID = ? OR Username = ?");
        $update->execute([$passwordHash, $role, $employeeID, $username]);
        echo "System user already exists, updated password/role.<br>";
    }

    echo "Admin ready successfully!";
} catch (PDOException $e) {
    die("Admin creation failed: " . $e->getMessage());
}
?>