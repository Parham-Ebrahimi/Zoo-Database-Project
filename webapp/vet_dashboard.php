<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit;
}
if (!in_array($_SESSION['role'], ['vet', 'admin'], true)) {
    header('Location: dashboard.php');
    exit;
}

require_once 'db.php';

$summary = $pdo->query("
    SELECT
        COUNT(*) AS TotalAnimals,
        SUM(CASE WHEN COALESCE(Health_Status, 'Pending') = 'Sick' THEN 1 ELSE 0 END) AS SickAnimals,
        SUM(CASE WHEN COALESCE(Health_Status, 'Pending') = 'Pending' THEN 1 ELSE 0 END) AS PendingAnimals
    FROM animal
")->fetch(PDO::FETCH_ASSOC);

$totalAnimals = (int)($summary['TotalAnimals'] ?? 0);
$sickAnimals = (int)($summary['SickAnimals'] ?? 0);
$pendingAnimals = (int)($summary['PendingAnimals'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vet Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body { overflow: auto; }
        .page-wrapper { 
            box-sizing: border-box; 
            min-height: 100vh; 
            padding: 40px; 
            background-color: rgba(187, 223, 158, 0.97); 
        }
        .page-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 24px; 
            border-bottom: 3px solid var(--accent-color); 
            padding-bottom: 16px; 
            flex-wrap: wrap; 
            gap: 10px; 
        }
        .page-header h1 { margin: 0; }
        .header-actions { 
            display: flex; 
            gap: 10px; 
            flex-wrap: wrap; 
        }
        .btn-nav { 
            padding: 9px 20px; 
            background-color: var(--base-color);
            border: 2px solid var(--accent-color); 
            border-radius: 1000px; 
            font: inherit; 
            font-weight: 600; 
            color: var(--text-color); 
            text-decoration: none; 
        }
        .btn-nav:hover { 
            background-color: var(--accent-color);
            text-decoration: none; 
        }
        .btn-logout { 
            padding: 9px 20px; 
            background-color: var(--accent-color); 
            border: none; 
            border-radius: 1000px; 
            font: inherit; 
            font-weight: 600; 
            color: var(--text-color); 
            text-decoration: none; 
        }
        .btn-logout:hover { 
            background-color: var(--text-color); 
            color: white; 
            text-decoration: none; 
        }
        .stats-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); 
            gap: 12px; 
            margin-bottom: 20px; 
        }
        .stat-card { 
            background: white; 
            border-radius: 12px; 
            padding: 16px; 
            text-align: center; 
            box-shadow: 0 2px 8px rgba(0,0,0,0.06); 
        }
        .stat-number { 
            font-size: 2rem; 
            font-weight: 900; 
            line-height: 1; 
            color: var(--text-color); 
        }
        .stat-label { 
            margin-top: 4px; 
            font-size: 0.8rem; 
            text-transform: uppercase; 
            letter-spacing: 0.05em; 
            color: #777; 
            font-weight: 600; 
        }
        .stat-danger .stat-number { color: #e74c3c; }
        .stat-warning .stat-number { color: #f39c12; }
        .card-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); 
            gap: 16px; 
            margin-bottom: 20px; 
        }
        .nav-card { 
            background: white; 
            border-radius: 14px; 
            padding: 20px; 
            box-shadow: 0 2px 8px rgba(0,0,0,0.06); 
        }
        .nav-card h2 { 
            font-size: 1.05rem; 
            margin: 0 0 12px; 
            color: var(--text-color); 
            border-bottom: 2px solid var(--accent-color); 
            padding-bottom: 8px; 
        }
        .nav-card a { 
            display: block; 
            padding: 9px 12px; 
            margin-bottom: 8px; 
            background: var(--base-color); 
            border-radius: 8px; 
            color: var(--text-color); 
            font-weight: 600; 
            text-decoration: none; 
        }
        .nav-card a:hover { 
            background: var(--accent-color); 
            text-decoration: none; 
        }
        .report-callout { 
            background: white; 
            border-radius: 14px; 
            padding: 18px 20px; 
            box-shadow: 0 2px 8px rgba(0,0,0,0.06); 
            margin-bottom: 16px; 
        }
        .report-callout h3 { 
            margin: 0 0 8px; 
            color: var(--text-color); 
        }
        .report-callout p { margin: 0 0 14px; color: #555; }
        .report-callout a { 
            display: inline-block; 
            padding: 10px 16px; 
            background: var(--accent-color); 
            color: white; 
            font-weight: 600; 
            border-radius: 8px; 
            text-decoration: none; 
        }
        .report-callout a:hover { 
            background: var(--text-color); 
            text-decoration: none; 
        }
    </style>
</head>
<body>
<div class="page-wrapper">
    <div class="page-header">
        <div>
            <h1>Vet Dashboard</h1>
            <p>Welcome, <strong><?= htmlspecialchars($_SESSION['firstname']) ?></strong> | Role: <?= htmlspecialchars($_SESSION['role']) ?></p>
        </div>
        <div class="header-actions">
            <?php if ($_SESSION['role'] === 'admin'): ?>
                <a href="dashboard.php" class="btn-nav">← Admin Dashboard</a>
            <?php endif; ?>
            <a href="logout.php" class="btn-logout">Logout</a>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number"><?= $totalAnimals ?></div>
            <div class="stat-label">Animals in View</div>
        </div>
        <div class="stat-card stat-danger">
            <div class="stat-number"><?= $sickAnimals ?></div>
            <div class="stat-label">Sick Animals</div>
        </div>
        <div class="stat-card stat-warning">
            <div class="stat-number"><?= $pendingAnimals ?></div>
            <div class="stat-label">Pending Review</div>
        </div>
    </div>

    <div class="card-grid">
        <div class="nav-card">
            <h2>Data Entry</h2>
            <a href="caretaker_dashboard.php">Update Animal Status</a>
        </div>
        <div class="nav-card">
            <h2>Reports</h2>
            <a href="animals_report.php">Animals Report</a>
            <a href="health-reports.php">Health Status Reports</a>
        </div>
    </div>
</div>
</body>
</html>
