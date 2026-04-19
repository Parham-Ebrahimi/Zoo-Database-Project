<?php
require_once __DIR__ . '/session_bootstrap.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit;
}
require_once 'staff_home.php';
$roleGate = strtolower(trim((string) ($_SESSION['role'] ?? '')));
if (!in_array($roleGate, ['admin', 'vet', 'caretaker', 'keeper'], true) && !staff_is_vet_role()) {
    header('Location: dashboard.php');
    exit;
}

require_once 'db.php';

$healthFilter = $_GET['health'] ?? 'all';
$search = trim($_GET['search'] ?? '');
$animalId = (int)($_GET['animal_id'] ?? 0);

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
if ($animalId > 0) {
    $sql .= " AND a.Animal_ID = ?";
    $params[] = $animalId;
}

$sql .= " ORDER BY FIELD(COALESCE(a.Health_Status, 'Pending'), 'Sick', 'Pending', 'Healthy'), a.Name";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$animals = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalAnimals = count($animals);
$sickAnimals = count(array_filter($animals, static fn($a) => $a['Health_Status'] === 'Sick'));
$pendingAnimals = count(array_filter($animals, static fn($a) => $a['Health_Status'] === 'Pending'));
$dashHref = staff_home_href();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Health Status Reports</title>
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
        .filter-group input,
        .filter-group select {
            padding: 6px 10px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font: inherit;
            background: white;
        }
        .filter-group input:focus,
        .filter-group select:focus {
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
            border: none;
            cursor: pointer;
            font: inherit;
            display: inline-block;
        }
        .btn-edit {
            background-color: var(--accent-color);
            color: white;
        }
        .btn-edit:hover {
            background-color: var(--text-color);
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
        .badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        .badge-healthy { background: #d4edda; color: #155724; }
        .badge-sick { background: #f8d7da; color: #721c24; }
        .badge-pending { background: #fff3cd; color: #856505; }
        .muted { color: #888; }
    </style>
</head>
<body>
<div class="dashboard-wrapper">
    <div class="dashboard-header">
        <h1>Health Status Reports</h1>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>

    <a href="<?= htmlspecialchars($dashHref) ?>" class="back-btn">← Back to Dashboard</a>

    <div class="filter-card">
        <h2>Filter Health Records</h2>
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
                <div class="filter-group filter-group--wide">
                    <label>Search Name / Species</label>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="e.g. Lion, Zara">
                </div>
                <div class="filter-group">
                    <label>Animal ID</label>
                    <input type="number" name="animal_id" value="<?= $animalId > 0 ? $animalId : '' ?>" min="1" placeholder="Optional">
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn btn-edit">Search</button>
                    <a href="health-reports.php" class="btn">Reset</a>
                </div>
            </div>
        </form>
    </div>

    <p><strong>Animals in view:</strong> <?= $totalAnimals ?></p>
    <p><strong>Sick:</strong> <?= $sickAnimals ?> &nbsp;|&nbsp; <strong>Pending review:</strong> <?= $pendingAnimals ?></p>

    <div class="report-table-scroll">
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
