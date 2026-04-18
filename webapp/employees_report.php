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
            padding: 20px clamp(12px, 2.4vw, 18px); 
            background-color: rgba(187, 223, 158, 0.95); 
        }
        .dashboard-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 16px; 
            border-bottom: 3px solid var(--accent-color); 
            padding-bottom: 12px; 
        }
        .filter-card {
            background: white;
            border-radius: 12px;
            padding: 12px 14px;
            margin-bottom: 14px;
            box-shadow: 0 3px 8px rgba(0,0,0,0.05);
        }
        .filter-card h2 {
            font-size: 0.95rem;
            font-weight: 700;
            margin-bottom: 10px;
            color: var(--text-color);
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

        .filter-group { 
            display: flex; 
            flex-direction: column; 
            gap: 4px; 
        }
        .filter-group label { 
            font-size: 0.85rem; 
            font-weight: 600; 
            color: var(--text-color); 
        }
        .filter-group input, .filter-group select { 
            padding: 6px 10px; 
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
            gap: 8px;
            align-items: end;
        }
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
            margin-bottom: 14px; 
            padding: 7px 14px; 
            background-color: var(--base-color); 
            border-radius: 8px; 
            color: var(--text-color); 
            font-weight: 600; 
            text-decoration: none; 
            font-size: 0.88rem;
        }
        .back-btn:hover { background-color: var(--accent-color); }

        .ui-modal {
            position: fixed;
            inset: 0;
            z-index: 3000;
            display: grid;
            place-items: center;
            padding: 16px;
        }
        .ui-modal[hidden] { display: none !important; }
        .ui-modal__backdrop {
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, 0.4);
            cursor: pointer;
        }
        .ui-modal__box {
            position: relative;
            background: #fff;
            border-radius: 12px;
            padding: 20px 22px;
            max-width: 400px;
            width: 100%;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.18);
        }
        .ui-modal__box h2 {
            margin: 0 0 10px;
            font-size: 1.05rem;
            color: var(--text-color);
        }
        .ui-modal__box p { margin: 0 0 18px; font-size: 0.9rem; line-height: 1.45; color: #444; }
        .ui-modal__actions { display: flex; flex-wrap: wrap; gap: 8px; justify-content: flex-end; }
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
                                <option value="Admin">Admin</option>
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

        <div class="report-table-scroll">
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
                    <a href="delete_employee.php?id=<?= (int) $row['EmployeeID'] ?>"
                       class="btn btn-delete js-delete-employee-link"
                       data-employee-label="<?= htmlspecialchars(trim(($row['FirstName'] ?? '') . ' ' . ($row['LastName'] ?? '')), ENT_QUOTES, 'UTF-8') ?>">Delete</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
        </div>
        <?php endif; ?>
    </div>

    <div id="delete-employee-modal" class="ui-modal" hidden role="dialog" aria-modal="true" aria-labelledby="delete-employee-modal-title">
        <div class="ui-modal__backdrop" data-emp-modal-dismiss></div>
        <div class="ui-modal__box">
            <h2 id="delete-employee-modal-title">Confirm delete</h2>
            <p id="delete-employee-modal-text"></p>
            <div class="ui-modal__actions">
                <button type="button" class="btn btn-edit" data-emp-modal-dismiss>Cancel</button>
                <button type="button" class="btn btn-delete" id="delete-employee-modal-confirm">Delete</button>
            </div>
        </div>
    </div>
    <script>
    (function () {
        var modal = document.getElementById('delete-employee-modal');
        var textEl = document.getElementById('delete-employee-modal-text');
        var confirmBtn = document.getElementById('delete-employee-modal-confirm');
        if (!modal || !textEl || !confirmBtn) return;
        var pendingUrl = null;

        function closeModal() {
            modal.hidden = true;
            pendingUrl = null;
        }

        document.querySelectorAll('.js-delete-employee-link').forEach(function (a) {
            a.addEventListener('click', function (e) {
                e.preventDefault();
                pendingUrl = a.getAttribute('href');
                var label = a.getAttribute('data-employee-label') || 'this employee';
                textEl.textContent = 'Remove ' + label + ' from the directory? This cannot be undone.';
                modal.hidden = false;
            });
        });

        confirmBtn.addEventListener('click', function () {
            if (pendingUrl) window.location.href = pendingUrl;
        });

        modal.querySelectorAll('[data-emp-modal-dismiss]').forEach(function (el) {
            el.addEventListener('click', closeModal);
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !modal.hidden) closeModal();
        });
    })();
    </script>
</body>
</html>