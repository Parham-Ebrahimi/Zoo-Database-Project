<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit;
}
require_once 'staff_home.php';

$roleRaw = strtolower(trim((string) ($_SESSION['role'] ?? '')));
$isAdmin = ($roleRaw === 'admin');
if (!$isAdmin && !staff_is_vet_role()) {
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
        html, body { min-height: 100%; }
        body { overflow: auto; margin: 0; }

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
        .role-badge {
            background: var(--accent-color);
            color: var(--text-color);
            padding: 4px 14px;
            border-radius: 1000px;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: capitalize;
        }
        .secondary-nav-btn {
            padding: 9px 18px;
            background-color: var(--base-color);
            border: 2px solid var(--accent-color);
            border-radius: 1000px;
            font: inherit;
            font-weight: 600;
            font-size: 0.88rem;
            color: var(--text-color);
            text-decoration: none;
        }
        .secondary-nav-btn:hover {
            background-color: var(--accent-color);
            text-decoration: none;
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
            text-align: left;
        }
        .stat-card.danger { border-left-color: #e74c3c; }
        .stat-card.warning { border-left-color: #f39c12; }
        .stat-card .stat-label {
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #666;
            font-weight: 600;
            margin-bottom: 8px;
        }
        .stat-card .stat-value {
            font-size: 1.85rem;
            font-weight: 800;
            color: var(--text-color);
            line-height: 1.1;
        }
        .stat-card.danger .stat-value { color: #e74c3c; }
        .stat-card.warning .stat-value { color: #f39c12; }

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
            margin-bottom: 22px;
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
    </style>
</head>
<body>
<div class="dashboard-wrapper">
<div class="dashboard-inner">

    <div class="dashboard-header">
        <div>
            <h1>Veterinarian dashboard</h1>
            <p class="dash-meta"><?= date('l, F j, Y') ?></p>
        </div>
        <div class="dashboard-header-actions">
            <span class="user-name"><?= htmlspecialchars($_SESSION['firstname']) ?></span>
            <span class="role-badge"><?= htmlspecialchars($_SESSION['role']) ?></span>
            <?php if (strtolower((string) $_SESSION['role']) === 'admin'): ?>
                <a href="dashboard.php" class="secondary-nav-btn">← Staff dashboard</a>
            <?php endif; ?>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </div>

    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-label">Animals in view</div>
            <div class="stat-value"><?= $totalAnimals ?></div>
        </div>
        <div class="stat-card <?= $sickAnimals > 0 ? 'danger' : '' ?>">
            <div class="stat-label">Sick animals</div>
            <div class="stat-value"><?= $sickAnimals ?></div>
        </div>
        <div class="stat-card <?= $pendingAnimals > 0 ? 'warning' : '' ?>">
            <div class="stat-label">Pending review</div>
            <div class="stat-value"><?= $pendingAnimals ?></div>
        </div>
    </div>

    <div class="section-title">Animals &amp; enclosures</div>
    <div class="tiles-grid">
        <a href="caretaker_dashboard.php#care-table" class="tile">
            <div class="tile-text"><strong>Health status updates</strong><span>Open the care board table to set each animal to Healthy, Sick, or Pending. Food restock is for caretakers only.</span></div>
        </a>
        <a href="animals_report.php" class="tile">
            <div class="tile-text"><strong>Animals report</strong><span>Search and filter animals</span></div>
        </a>
        <a href="health-reports.php" class="tile">
            <div class="tile-text"><strong>Health records</strong><span>Medical history and status</span></div>
        </a>
    </div>

</div>
</div>
</body>
</html>
