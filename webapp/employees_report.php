<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: customer-login.html');
    exit;
}
require 'db.php';
 
// Filters
$role      = $_GET['role'] ?? '';
$minSalary = $_GET['min_salary'] ?? '';
$maxSalary = $_GET['max_salary'] ?? '';
$hireFrom  = $_GET['hire_from'] ?? '';
$hireTo    = $_GET['hire_to'] ?? '';

$query = "SELECT * FROM employees WHERE 1=1";
$params = [];

if ($role !== '') {
    $query .= " AND Role = ?";
    $params[] = $role;
}

if ($minSalary !== '') {
    $query .= " AND Salary >= ?";
    $params[] = $minSalary;
}

if ($maxSalary !== '') {
    $query .= " AND Salary <= ?";
    $params[] = $maxSalary;
}

if ($hireFrom !== '') {
    $query .= " AND HireDate >= ?";
    $params[] = $hireFrom;
}

if ($hireTo !== '') {
    $query .= " AND HireDate <= ?";
    $params[] = $hireTo;
}

$query .= " ORDER BY HireDate DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$employees = $stmt->fetchAll();
$totalEmployees = count($employees);
$avgSalary = $pdo->query(
    "SELECT AVG(Salary)
     FROM employees")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Employees Report</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body { overflow: auto; }
        .dashboard-wrapper { 
            box-sizing: border-box; 
            min-height: 100vh; 
            padding: 40px; 
            background-color: rgba(187, 223, 158, 0.95); 
        }
        .dashboard-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 30px; 
            border-bottom: 3px solid var(--accent-color); 
            padding-bottom: 20px; 
        }
        .filter-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
        }
        .filter-card label {
            background: none;
            color: var(--text-color);
            font-size: 0.85rem;
            font-weight: 600;
            height: auto;
            width: auto;
            border-radius: 0;
            display: block;
            text-align: left;
            padding: 0;
            fill: none;
            flex-shrink: unset;
        }
        .filter-card form {
            width: 100%;
            margin: 0;
            display: block;
        }
        .filter-card form > div {
            width: auto;
            display: block;
        }

        .filter-grid { 
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px; 
            align-items: end; 
        }

        .filter-group { 
            display: flex; 
            flex-direction: column; 
            gap: 6px; 
        }
        .filter-group label { 
            font-size: 0.85rem; 
            font-weight: 600; 
            color: var(--text-color); 
        }
        .filter-group input, .filter-group select { 
            padding: 8px 12px; 
            border: 2px solid #ddd; 
            border-radius: 8px; 
            font: inherit;
            background: white;
        }
        .filter-group input:focus, .filter-group select:focus { 
            outline: none; 
            border-color: var(--accent-color); 
        }
        .filter-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: end;
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            background: white; 
            border-radius: 15px; 
            overflow: hidden; 
            box-shadow: 0 4px 10px rgba(0,0,0,0.1); 
        }
        th { 
            background-color: var(--accent-color); 
            color: white; 
            padding: 12px 15px; 
            text-align: left; 
        }
        td { 
            padding: 10px 15px; 
            border-bottom: 1px solid #e0e0e0; 
        }
        tr:hover { background-color: var(--base-color); }
        .btn { 
            padding: 6px 14px; 
            border-radius: 8px; 
            text-decoration: none; 
            font-weight: 600; 
            font-size: 0.85rem; 
        }
        .btn-edit { 
            background-color: var(--accent-color); 
            color: white; 
        }
        .btn-delete { 
            background-color: #e74c3c; 
            color: white; 
        }
        .btn-edit:hover { 
            background-color: var(--text-color); 
        }
        .btn-delete:hover { 
            background-color: #c0392b; 
        }
        .logout-btn { 
            padding: 10px 25px; 
            background-color: var(--accent-color); 
            border: none; 
            border-radius: 1000px; 
            font: inherit; 
            font-weight: 600; 
            cursor: pointer; 
            color: var(--text-color); 
            text-decoration: none; 
        }
        .logout-btn:hover { 
            background-color: var(--text-color); 
            color: white; 
        }
        .back-btn { 
            display: inline-block; 
            margin-bottom: 20px; 
            padding: 10px 20px; 
            background-color: var(--base-color); 
            border-radius: 8px; 
            color: var(--text-color); 
            font-weight: 600; 
            text-decoration: none; 
        }
        .back-btn:hover { background-color: var(--accent-color); }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <div class="dashboard-header">
            <h1>Employees Report</h1>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
 
        <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
 
        <?php if (count($employees) === 0): ?>
            <p>No employees found in the database.</p>
        <?php else: ?>

        <div class="filter-card">
            <h2>Filter Employees</h2>
            <form method="GET">
                <div class="filter-grid">
                    <div class="filter-group">
                        <label>Role</label>
                        <select name="role">
                                <option value="">All</option>
                                <option value="Manager">Manager</option>
                                <option value="Keeper">Keeper</option>
                                <option value="Cashier">Cashier</option>
                                <option value="Vet">Vet</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label>Min Salary</label>
                            <input type="number" name="min_salary">
                        </div>

                        <div class="filter-group">
                            <label>Max Salary</label>
                            <input type="number" name="max_salary">
                        </div>

                        <div class="filter-group">
                            <label>Hire From</label>
                            <input type="date" name="hire_from">
                        </div>

                        <div class="filter-group">
                            <label>Hire To</label>
                            <input type="date" name="hire_to">
                        </div>

                        <div class="filter-actions">
                            <button type="submit" class="btn btn-edit">Search</button>
                            <a href="employees_report.php" class="btn">Reset</a>
                        </div>
                </div>
            </form>
        </div>


        <p><strong>Total Employees:</strong> <?= $totalEmployees ?></p>
        <p><strong>Average Salary:</strong> $<?= number_format($avgSalary, 2) ?></p>

        <table>
            <tr>
                <th>ID</th>
                <th>First Name</th>
                <th>M.I.</th>
                <th>Last Name</th>
                <th>Role</th>
                <th>Salary</th>
                <th>Hire Date</th>
                <th>Sex</th>
                <th>Actions</th>
            </tr>
            <?php foreach ($employees as $row): ?>
            <tr>
                <td><?= $row['EmployeeID'] ?></td>
                <td><?= $row['FirstName'] ?></td>
                <td><?= $row['MidInitial'] ?></td>
                <td><?= $row['LastName'] ?></td>
                <td><?= $row['Role'] ?></td>
                <td>$<?= number_format($row['Salary'], 2) ?></td>
                <td><?= $row['HireDate'] ?></td>
                <td><?= $row['Sex'] ?></td>
                <td>
                    <a href="edit_employee.php?id=<?= $row['EmployeeID'] ?>" class="btn btn-edit">Edit</a>
                    <a href="delete_employee.php?id=<?= $row['EmployeeID'] ?>" class="btn btn-delete" onclick="return confirm('Are you sure?')">Delete</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php endif; ?>
    </div>
</body>
</html>