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

$healthFilter = $_GET['health'] ?? 'all';
$search = trim($_GET['search'] ?? '');
$allowedHealth = ['all', 'Sick', 'Pending', 'Healthy'];
if (!in_array($healthFilter, $allowedHealth, true)) {
    $healthFilter = 'all';
}

$sql = "
    SELECT
        a.Animal_ID,
        a.Name,
        a.Species,
        a.Category,
        COALESCE(a.Health_Status, 'Pending') AS Health_Status,
        COALESCE(a.food_stock, 50) AS food_stock,
        e.Enclosure_Name,
        hr.Diagnosis,
        hr.Treatment,
        hr.Record_Date AS LastCheckup
    FROM animal a
    LEFT JOIN enclosure e ON a.Enclosure_ID = e.Enclosure_ID
    LEFT JOIN (
        SELECT hr1.*
        FROM health_record hr1
        INNER JOIN (
            SELECT Animal_ID, MAX(Record_Date) AS MaxDate
            FROM health_record
            GROUP BY Animal_ID
        ) latest
        ON hr1.Animal_ID = latest.Animal_ID AND hr1.Record_Date = latest.MaxDate
    ) hr ON hr.Animal_ID = a.Animal_ID
    WHERE 1=1
";
$params = [];

if ($healthFilter !== 'all') {
    $sql .= " AND COALESCE(a.Health_Status, 'Pending') = ?";
    $params[] = $healthFilter;
}
if ($search !== '') {
    $sql .= " AND (a.Name LIKE ? OR a.Species LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY FIELD(COALESCE(a.Health_Status, 'Pending'), 'Sick', 'Pending', 'Healthy'), a.Name";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$animals = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalAnimals = count($animals);
$sickAnimals = count(array_filter($animals, static fn($a) => $a['Health_Status'] === 'Sick'));
$pendingAnimals = count(array_filter($animals, static fn($a) => $a['Health_Status'] === 'Pending'));
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
        .page-wrapper { box-sizing: border-box; min-height: 100vh; padding: 40px; background-color: rgba(187, 223, 158, 0.97); }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; border-bottom: 3px solid var(--accent-color); padding-bottom: 16px; flex-wrap: wrap; gap: 10px; }
        .page-header h1 { margin: 0; }
        .header-actions { display: flex; gap: 10px; flex-wrap: wrap; }
        .btn-nav { padding: 9px 20px; background-color: var(--base-color); border: 2px solid var(--accent-color); border-radius: 1000px; font: inherit; font-weight: 600; color: var(--text-color); text-decoration: none; }
        .btn-nav:hover { background-color: var(--accent-color); text-decoration: none; }
        .btn-logout { padding: 9px 20px; background-color: var(--accent-color); border: none; border-radius: 1000px; font: inherit; font-weight: 600; color: var(--text-color); text-decoration: none; }
        .btn-logout:hover { background-color: var(--text-color); color: white; text-decoration: none; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 12px; margin-bottom: 20px; }
        .stat-card { background: white; border-radius: 12px; padding: 16px; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        .stat-number { font-size: 2rem; font-weight: 900; line-height: 1; color: var(--text-color); }
        .stat-label { margin-top: 4px; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; color: #777; font-weight: 600; }
        .stat-danger .stat-number { color: #e74c3c; }
        .stat-warning .stat-number { color: #f39c12; }
        .filter-card { background: white; border-radius: 12px; padding: 14px; margin-bottom: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        .filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; align-items: end; }
        .filter-group { display: flex; flex-direction: column; gap: 5px; }
        .filter-group label { font-size: 0.8rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; }
        .filter-group input, .filter-group select { padding: 8px 10px; border: 2px solid #ddd; border-radius: 8px; font: inherit; background: white; }
        .filter-group input:focus, .filter-group select:focus { outline: none; border-color: var(--accent-color); }
        .filter-actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .btn-apply { padding: 8px 18px; border: none; border-radius: 8px; background: var(--accent-color); color: white; font: inherit; font-weight: 600; cursor: pointer; }
        .btn-apply:hover { background: var(--text-color); }
        .btn-reset { padding: 8px 18px; border-radius: 8px; background: #eee; color: #555; text-decoration: none; font-weight: 600; }
        .btn-reset:hover { background: #ddd; text-decoration: none; }
        .table-wrap { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08); overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 860px; }
        th { background-color: var(--accent-color); color: white; text-align: left; padding: 10px 12px; }
        td { padding: 10px 12px; border-bottom: 1px solid #f0f0f0; vertical-align: top; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: rgba(187, 223, 158, 0.2); }
        .badge { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; }
        .badge-healthy { background: #d4edda; color: #155724; }
        .badge-sick { background: #f8d7da; color: #721c24; }
        .badge-pending { background: #fff3cd; color: #856505; }
        .muted { color: #888; }
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
            <a href="animals_report.php" class="btn-nav">Full Animal Report</a>
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

    <div class="filter-card">
        <form method="GET">
            <div class="filter-grid">
                <div class="filter-group">
                    <label>Health Status</label>
                    <select name="health">
                        <option value="all" <?= $healthFilter === 'all' ? 'selected' : '' ?>>All</option>
                        <option value="Sick" <?= $healthFilter === 'Sick' ? 'selected' : '' ?>>Sick</option>
                        <option value="Pending" <?= $healthFilter === 'Pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="Healthy" <?= $healthFilter === 'Healthy' ? 'selected' : '' ?>>Healthy</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Search Name / Species</label>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="e.g. Lion, Zara">
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn-apply">Apply</button>
                    <a href="vet_dashboard.php" class="btn-reset">Reset</a>
                </div>
            </div>
        </form>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Species</th>
                    <th>Category</th>
                    <th>Health</th>
                    <th>Enclosure</th>
                    <th>Last Checkup</th>
                    <th>Diagnosis</th>
                    <th>Treatment</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($animals) === 0): ?>
                    <tr><td colspan="9" class="muted">No animals match the selected filters.</td></tr>
                <?php else: ?>
                    <?php foreach ($animals as $a): ?>
                        <?php $badgeClass = strtolower($a['Health_Status']); ?>
                        <tr>
                            <td><?= (int)$a['Animal_ID'] ?></td>
                            <td><strong><?= htmlspecialchars($a['Name'] ?? '') ?></strong></td>
                            <td><?= htmlspecialchars($a['Species'] ?? '') ?></td>
                            <td><?= htmlspecialchars($a['Category'] ?? '') ?></td>
                            <td><span class="badge badge-<?= htmlspecialchars($badgeClass) ?>"><?= htmlspecialchars($a['Health_Status']) ?></span></td>
                            <td><?= htmlspecialchars($a['Enclosure_Name'] ?? 'N/A') ?></td>
                            <td><?= $a['LastCheckup'] ? htmlspecialchars((string)$a['LastCheckup']) : '<span class="muted">No record</span>' ?></td>
                            <td><?= $a['Diagnosis'] ? htmlspecialchars((string)$a['Diagnosis']) : '<span class="muted">None</span>' ?></td>
                            <td><?= $a['Treatment'] ? htmlspecialchars((string)$a['Treatment']) : '<span class="muted">None</span>' ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
