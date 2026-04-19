<?php
/**
 * Sets systemuser.Role to "Gift Shop Employee" (exact string). The staff gift shop hub
 * on dashboard.php only appears for that role. Wrong dashboard? Fix DB, e.g.:
 * UPDATE systemuser SET Role = 'Gift Shop Employee' WHERE Username = 'gsemployee1';
 */
require_once 'db.php';

$username = 'giftshop1';
$password = 'giftshop123';
$systemRole = 'Gift Shop Employee';
$employeesJobRole = 'Gift Shop Employee';
$firstname = 'Gift';
$lastname = 'Shop';

try {
    $nextId = (int) $pdo->query('SELECT COALESCE(MAX(EmployeeID), 0) + 1 FROM employees')->fetchColumn();

    $checkUser = $pdo->prepare('SELECT UserID, EmployeeID FROM systemuser WHERE Username = ?');
    $checkUser->execute([$username]);
    $existing = $checkUser->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $employeeID = (int) $existing['EmployeeID'];
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $upd = $pdo->prepare('UPDATE systemuser SET PasswordHash = ?, Role = ? WHERE Username = ?');
        $upd->execute([$hash, $systemRole, $username]);
        echo "Updated existing user <strong>{$username}</strong> (EmployeeID {$employeeID}) with role {$systemRole}.<br>";
    } else {
        $emp = $pdo->prepare('INSERT INTO employees
            (EmployeeID, Role, Salary, HireDate, FirstName, LastName, Sex, Address, DOB, Race)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $emp->execute([
            $nextId,
            $employeesJobRole,
            38000,
            date('Y-m-d'),
            $firstname,
            $lastname,
            'M',
            '123 Zoo St',
            '1990-01-01',
            'N/A',
        ]);

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $sys = $pdo->prepare('INSERT INTO systemuser (EmployeeID, Username, PasswordHash, Role)
            VALUES (?, ?, ?, ?)');
        $sys->execute([$nextId, $username, $hash, $systemRole]);

        echo "Created employee ID {$nextId} ({$employeesJobRole}) and user <strong>{$username}</strong> with role {$systemRole}.<br>";
    }

    echo '<br><strong>Done.</strong> Sign in at <code>login.html</code> with <code>' . htmlspecialchars($username) . '</code> / <code>' . htmlspecialchars($password) . '</code>.';
} catch (PDOException $e) {
    die('Failed: ' . htmlspecialchars($e->getMessage()));
}
