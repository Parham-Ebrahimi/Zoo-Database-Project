<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit;
}

if (!in_array($_SESSION['role'], ['admin'])) {
    die("Access denied");
}
require_once 'db.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstname  = trim($_POST['firstname']);
    $midinit    = trim($_POST['midinit']);
    $lastname   = trim($_POST['lastname']);
    $role       = trim($_POST['role']);
    $salary     = $_POST['salary'];
    $hiredate   = $_POST['hiredate'];
    $sex        = $_POST['sex'];
    $address    = trim($_POST['address']);
    $dob        = $_POST['dob'];
    $race       = trim($_POST['race']);

    if (empty($firstname) || empty($lastname) || empty($role) || empty($sex)) {
        $error = 'Please fill in all required fields.';
    } else {
        $stmt = $pdo->prepare("INSERT INTO employees (FirstName, MidInitial, LastName, Role, Salary, HireDate, Sex, Address, DOB, Race) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$firstname, $midinit ?: null, $lastname, $role, $salary ?: null, $hiredate ?: null, $sex, $address ?: null, $dob ?: null, $race ?: null]);
        $success = 'Employee added successfully!';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Employee</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body { overflow: auto; }
        .dashboard-wrapper { box-sizing: border-box; min-height: 100vh; padding: 30px 40px; background-color: var(--base-color); }
        .dashboard-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 3px solid var(--accent-color); padding-bottom: 15px; }
        .form-card { background: white; border-radius: 15px; padding: 25px 30px; max-width: 700px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .form-group { display: flex; flex-direction: column; }
        .form-group.full { grid-column: 1 / -1; }
        .form-group label { font-weight: 600; margin-bottom: 4px; color: var(--text-color); font-size: 0.9rem; width: auto; height: auto; background: none; border-radius: 0; text-align: left; }
        .form-group input, .form-group select { width: 100%; padding: 9px 12px; border: 2px solid #ddd; border-radius: 8px; font: inherit; font-size: 0.95rem; box-sizing: border-box; background-color: white; height: auto; flex-grow: 0; }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: var(--accent-color); }
        form > div { width: auto; display: block; justify-content: unset; }
        .submit-btn { margin-top: 16px; padding: 10px 28px; background-color: var(--accent-color); border: none; border-radius: 1000px; font: inherit; font-weight: 600; cursor: pointer; color: var(--text-color); }
        .submit-btn:hover { background-color: var(--text-color); color: white; }
        .logout-btn { padding: 9px 22px; background-color: var(--accent-color); border: none; border-radius: 1000px; font: inherit; font-weight: 600; cursor: pointer; color: var(--text-color); text-decoration: none; }
        .logout-btn:hover { background-color: var(--text-color); color: white; }
        .back-btn { display: inline-block; margin-bottom: 15px; padding: 8px 18px; background-color: var(--base-color); border-radius: 8px; color: var(--text-color); font-weight: 600; text-decoration: none; border: 2px solid var(--accent-color); font-size: 0.9rem; }
        .back-btn:hover { background-color: var(--accent-color); }
        .msg-error { color: #e74c3c; font-weight: 600; margin-bottom: 12px; }
        .msg-success { color: #27ae60; font-weight: 600; margin-bottom: 12px; }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <div class="dashboard-header">
            <h1>Add Employee</h1>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>

        <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>

        <div class="form-card">
            <?php if ($error): ?><p class="msg-error"><?= $error ?></p><?php endif; ?>
            <?php if ($success): ?><p class="msg-success"><?= $success ?></p><?php endif; ?>

            <form method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label>First Name *</label>
                        <input type="text" name="firstname" required>
                    </div>
                    <div class="form-group">
                        <label>Middle Initial</label>
                        <input type="text" name="midinit" maxlength="1">
                    </div>
                    <div class="form-group">
                        <label>Last Name *</label>
                        <input type="text" name="lastname" required>
                    </div>
                    <div class="form-group">
                        <label>Role *</label>
                        <input type="text" name="role" placeholder="e.g. Zookeeper, Vet" required>
                    </div>
                    <div class="form-group">
                        <label>Salary</label>
                        <input type="number" name="salary" step="0.01" min="0">
                    </div>
                    <div class="form-group">
                        <label>Hire Date</label>
                        <input type="date" name="hiredate">
                    </div>
                    <div class="form-group">
                        <label>Sex *</label>
                        <select name="sex" required>
                            <option value="">-- Select --</option>
                            <option value="M">Male</option>
                            <option value="F">Female</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Date of Birth</label>
                        <input type="date" name="dob">
                    </div>
                    <div class="form-group full">
                        <label>Address</label>
                        <input type="text" name="address">
                    </div>
                    <div class="form-group">
                        <label>Race</label>
                        <input type="text" name="race">
                    </div>
                </div>
                <button type="submit" class="submit-btn">Add Employee</button>
            </form>
        </div>
    </div>
</body>
</html>
