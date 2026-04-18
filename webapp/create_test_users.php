<?php
require_once 'db.php';

$users = [
    [
        'employeeID' => 2,
        'firstname'  => 'John',
        'lastname'   => 'Caretaker',
        'role'       => 'caretaker',
        'username'   => 'caretaker1',
        'password'   => 'caretaker123',
    ],
    [
        'employeeID' => 5,
        'firstname'  => 'Joe',
        'lastname'   => 'Banana',
        'role'       => 'Caretaker',
        'username'   => 'caretaker2',
        'password'   => 'caretaker123',
    ],
    [
        'employeeID' => 3,
        'firstname'  => 'Jane',
        'lastname'   => 'Vet',
        'role'       => 'vet',
        'username'   => 'vet1',
        'password'   => 'vet123',
    ],
];

foreach ($users as $u) {
    try {
        // Check if employee exists
        $check = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE EmployeeID = ?");
        $check->execute([$u['employeeID']]);
        if ((int)$check->fetchColumn() === 0) {
            $emp = $pdo->prepare("INSERT INTO employees 
                (EmployeeID, Role, Salary, HireDate, FirstName, LastName, Sex, Address, DOB, Race)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $emp->execute([
                $u['employeeID'],
                $u['role'],
                40000,
                '2024-01-01',
                $u['firstname'],
                $u['lastname'],
                'M',
                '123 Zoo St',
                '1990-01-01',
                'N/A'
            ]);
            echo "Employee {$u['firstname']} inserted.<br>";
        } else {
            echo "Employee {$u['firstname']} already exists, skipping.<br>";
        }

        // Check if system user exists
        $checkUser = $pdo->prepare("SELECT COUNT(*) FROM systemuser WHERE Username = ?");
        $checkUser->execute([$u['username']]);
        if ((int)$checkUser->fetchColumn() === 0) {
            $hash = password_hash($u['password'], PASSWORD_BCRYPT);
            $sys = $pdo->prepare("INSERT INTO systemuser (EmployeeID, Username, PasswordHash, Role)
                VALUES (?, ?, ?, ?)");
            $sys->execute([$u['employeeID'], $u['username'], $hash, $u['role']]);
            echo "System user {$u['username']} inserted.<br>";
        } else {
            echo "System user {$u['username']} already exists, skipping.<br>";
        }

    } catch (PDOException $e) {
        echo "Error for {$u['firstname']}: " . $e->getMessage() . "<br>";
    }
}

echo "<br>Done! Test accounts ready.";
?>
