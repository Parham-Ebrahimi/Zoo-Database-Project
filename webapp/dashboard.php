<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit;
}

$role = $_SESSION['role'];
if ($role === 'caretaker') {
    header('Location: caretaker_dashboard.php');
    exit;
}
if ($role === 'vet') {
    header('Location: vet_dashboard.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            background-size: cover;
        }

        .dashboard-wrapper {
            box-sizing: border-box;
            min-height: 100vh;
            padding: 40px;
            background-color: var(--base-color);
        }

        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
            border-bottom: 3px solid var(--accent-color);
            padding-bottom: 20px;
        }

        .card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .card {
            background-color: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0);
        }

        .card h2 {
            font-size: 1.2rem;
            margin-bottom: 15px;
            color: var(--text-color);
            border-bottom: 2px solid var(--accent-color);
            padding-bottom: 10px;
        }

        .card a {
            display: block;
            padding: 10px 15px;
            margin-bottom: 8px;
            background-color: var(--base-color);
            border-radius: 8px;
            color: var(--text-color);
            font-weight: 600;
            text-decoration: none;
        }

        .card a:hover {
            background-color: var(--accent-color);
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
    </style>
</head>
<body>

<div class="dashboard-wrapper">
    <div class="dashboard-header">
        <div>
            <h1>Admin Dashboard</h1>
            <p>
                Welcome, <?php echo $_SESSION['firstname']; ?> |
                Role: <?php echo $role; ?>
            </p>
        </div>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>

    <div class="card-grid">
        <div class="card">
            <h2>Data Entry</h2>
            <?php if ($role === 'admin' || $role === 'caretaker' || $role === 'vet'): ?>
                <a href="add-animal.php">Add Animal</a>
            <?php endif; ?>

            <?php if ($role === 'admin'): ?>
                <a href="add-employee.php">Add Employee</a>
                <a href="add-ticket.php">Add Ticket</a>
            <?php endif; ?>

            <?php if ($role === 'vet'): ?>
                <a href="add-health-record.php">Add Health Record</a>
            <?php endif; ?>

            <?php if ($role === 'Gift Shop Employee'): ?>
                <a href="add-order.php">Record Sale</a>
            <?php endif; ?>

            <?php if ($role === 'admin' ): ?>
                <a href="caretaker.php">Update Animal Status</a>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2>Reports</h2>
            <?php if ($role === 'admin' || $role === 'caretaker' || $role === 'vet'): ?>
                <a href="animals_report.php">View Animals</a>
            <?php endif; ?>

            <?php if ($role === 'admin'): ?>
                <a href="tickets_report.php"> View Tickets</a>
                <a href="employees_report.php">View Employees</a>
                <a href="revenue_report.php">View Revenue Reports</a>
            <?php endif; ?>

            <?php if ($role === 'vet'): ?>
                <a href="health-reports.php">Health Records</a>
            <?php endif; ?>

            <?php if ($role === 'Gift Shop Employee'): ?>
                <a href="sales_report.php">Sales Report</a>
            <?php endif; ?>
            <?php if ($role === 'admin' || $role === 'Gift Shop Employee'): ?>
                <a href="shop_alerts.php">Gift Shop Restock Alerts</a>
            <?php endif; ?>

        </div>

    </div>
</div>

</body>
</html>