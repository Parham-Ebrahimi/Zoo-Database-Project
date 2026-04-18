<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit;
}

require 'db.php';

$role = $_SESSION['role'];
$firstname = $_SESSION['firstname'];

// Quick stats for admin
$totalAnimals   = $pdo->query("SELECT COUNT(*) FROM animal")->fetchColumn();
$totalEmployees = $pdo->query("SELECT COUNT(*) FROM employees")->fetchColumn();
$pendingHealth  = $pdo->query("SELECT COUNT(*) FROM animal WHERE Health_Status != 'Healthy'")->fetchColumn();
$todayRevenue   = $pdo->query("SELECT TotalRevenue FROM daily_revenue WHERE RevenueDate = CURDATE()")->fetchColumn() ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — Greenwood Zoo</title>
    <link rel="stylesheet" href="style.css">
    <style>
        html, body {
            min-height: 100%;
        }
        body {
            overflow: auto;
            margin: 0;
        }
        .dashboard-wrapper {
            box-sizing: border-box;
            width: 100%;
            min-height: 100vh;
            min-height: 100dvh;
            background-color: rgba(187, 223, 158, 0.95);
            text-align: left;
        }

        .dashboard-inner {
            box-sizing: border-box;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px clamp(12px, 2.4vw, 18px);
        }

        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 20px;
            border-bottom: 3px solid var(--accent-color);
            padding-bottom: 14px;
            flex-wrap: wrap;
        }
        .dashboard-header h1 {
            margin: 0;
            font-size: clamp(1.35rem, 2.5vw, 1.75rem);
            font-weight: 800;
            color: var(--text-color);
        }
        .dashboard-header .dash-meta {
            margin: 6px 0 0;
            font-size: 0.9rem;
            color: #666;
            font-weight: 500;
        }
        .dashboard-header-actions {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .dashboard-header-actions .user-name {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-color);
        }
        
        .logout-btn {
            padding: 10px 22px;
            background-color: var(--accent-color);
            border: none;
            border-radius: 1000px;
            font: inherit;
            font-weight: 600;
            cursor: pointer;
            color: var(--text-color);
            text-decoration: none;
            display: inline-block;
        }
        .logout-btn:hover {
            background-color: var(--text-color);
            color: white;
            text-decoration: none;
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 14px;
            margin-bottom: 22px;
        }
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 18px;
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.05);
            border-left: 4px solid var(--accent-color);
        }
        .stat-card.warning { border-left-color: #e74c3c; }
        .stat-card.info    { border-left-color: #3498db; }
        .stat-card.money   { border-left-color: #27ae60; }
        .stat-label {
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #666;
            font-weight: 600;
            margin-bottom: 8px;
        }
        .stat-value {
            font-size: 1.85rem;
            font-weight: 800;
            color: var(--text-color);
            line-height: 1.1;
        }
        .stat-value.warning { color: #e74c3c; }

        .section-title {
            font-size: 1rem;
            margin: 22px 0 10px;
            border-bottom: 1px solid #e0e0e0;
            padding-bottom: 6px;
            color: var(--text-color);
            font-weight: 700;
        }
        .section-title:first-of-type { margin-top: 0; }

        .tiles-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 12px;
        }
        .tile {
            background: white;
            border-radius: 12px;
            padding: 16px 18px;
            text-decoration: none;
            color: var(--text-color);
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            gap: 14px;
            transition: transform 120ms ease, box-shadow 120ms ease;
            border: 2px solid transparent;
        }
        .tile:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 14px rgba(23, 103, 7, 0.12);
            border-color: var(--accent-color);
            text-decoration: none;
        }
        .tile-icon {
            font-size: 1.5rem;
            width: 42px;
            height: 42px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--base-color);
            border-radius: 10px;
            flex-shrink: 0;
        }
        .tile-text strong {
            display: block;
            font-size: 0.9rem;
            font-weight: 700;
            margin-bottom: 3px;
        }
        .tile-text span {
            font-size: 0.8rem;
            color: #666;
        }
        .tile.alert-tile {
            border-color: #e74c3c;
            background: #fff8f8;
        }
        .tile.alert-tile .tile-icon { background: #fde8e8; }
    </style>
</head>
<body>
<div class="dashboard-wrapper">
<div class="dashboard-inner">

    <div class="dashboard-header">
        <div>
            <h1><?php
            if ($role === 'admin') echo 'Admin dashboard';
            elseif ($role === 'vet') echo 'Veterinarian dashboard';
            elseif ($role === 'caretaker') echo 'Caretaker dashboard';
            elseif ($role === 'cashier') echo 'Cashier dashboard';
            else echo 'Dashboard';
            ?></h1>
            <p class="dash-meta"><?= date('l, F j, Y') ?></p>
        </div>
        <div class="dashboard-header-actions">
            <span class="user-name"><?= htmlspecialchars($firstname) ?></span>
            <a href="change-password.php" class="secondary-nav-btn">🔒 Change Password</a>

            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </div>

        <!-- Stats Row (adminonly) -->
        <?php if (in_array($role, ['admin'])): ?>
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-label">🐾 Total Animals</div>
                <div class="stat-value"><?= $totalAnimals ?></div>
            </div>
            <div class="stat-card info">
                <div class="stat-label">Employees</div>
                <div class="stat-value"><?= $totalEmployees ?></div>
            </div>
            <div class="stat-card <?= $pendingHealth > 0 ? 'warning' : '' ?>">
                <div class="stat-label">Health Alerts</div>
                <div class="stat-value <?= $pendingHealth > 0 ? 'warning' : '' ?>"><?= $pendingHealth ?></div>
            </div>
            <div class="stat-card money">
                <div class="stat-label">Today's Revenue</div>
                <div class="stat-value">$<?= number_format($todayRevenue, 0) ?></div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($pendingHealth > 0): ?>
        <!-- Health Alert -->
        <a href="health-reports.php" class="tile alert-tile" style="margin-bottom:20px;display:flex">
            <div class="tile-icon">🚨</div>
            <div class="tile-text">
                <strong><?= $pendingHealth ?> animal(s) need attention</strong>
                <span>Click to view health report</span>
            </div>
        </a>
        <?php endif; ?>

        <!-- Animals Section -->
        <?php if (in_array($role, ['admin', 'caretaker', 'vet',])): ?>
        <div class="section-title">Animals & Enclosures</div>
        <div class="tiles-grid">
            <?php if (in_array($role, ['admin', 'caretaker'])): ?>
            <a href="add-animal.php" class="tile">
                <div class="tile-icon">➕</div>
                <div class="tile-text"><strong>Add Animal</strong><span>Register new animal</span></div>
            </a>
            <?php endif; ?>
            <a href="animals_report.php" class="tile">
                <div class="tile-text"><strong>Animals Report</strong><span>Search & filter animals</span></div>
            </a>
            <?php if (in_array($role, ['admin', 'vet'])): ?>
            <a href="health-reports.php" class="tile">
                <div class="tile-text"><strong>Health Records</strong><span>Medical history</span></div>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Staff Section -->
        <?php if (in_array($role, ['admin'])): ?>
        <div class="section-title">Staff Management</div>
        <div class="tiles-grid">
            <a href="add-employee.php" class="tile">
                <div class="tile-text"><strong>Add Employee</strong><span>Register new staff</span></div>
            </a>
            <a href="employees_report.php" class="tile">
                <div class="tile-text"><strong>Employees Report</strong><span>Filter by role & salary</span></div>
            </a>
        </div>
        <?php endif; ?>

        <?php if (in_array($role, ['admin', 'cashier'])): ?>
        <div class="section-title">Revenue & Tickets</div>
        <div class="tiles-grid">
            <?php if (in_array($role, ['admin', 'cashier'])): ?>
            <a href="add-ticket.php" class="tile">
                <div class="tile-text"><strong>Add Ticket</strong><span>Create new ticket</span></div>
            </a>
            <?php endif; ?>
            <?php if (in_array($role, ['admin'])): ?>
            <a href="revenue_report.php" class="tile">
                <div class="tile-text"><strong>Revenue Report</strong><span>Daily financial summary</span></div>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Gift Shop Section -->
        <?php if (in_array($role, ['admin', 'cashier'])): ?>
        <div class="section-title">Gift Shop & Food</div>
        <div class="tiles-grid">
            <a href="shop_alerts.php" class="tile">
                <div class="tile-text"><strong>Shop Restock Alerts</strong><span>Low stock warnings</span></div>
            </a>
        </div>
        <?php endif; ?>

</div>
</div>
</body>
</html>
