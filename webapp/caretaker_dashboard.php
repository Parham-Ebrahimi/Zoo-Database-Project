<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit;
}
if (!in_array($_SESSION['role'], ['caretaker', 'admin'], true)) {
    header('Location: dashboard.php');
    exit;
}

require_once 'db.php';

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_health') {
        $animalId = (int)($_POST['animal_id'] ?? 0);
        $allowedHealth = ['Healthy', 'Sick', 'Pending'];
        $healthStatus = in_array($_POST['health_status'] ?? '', $allowedHealth, true)
            ? $_POST['health_status']
            : 'Healthy';

        if ($animalId > 0) {
            $stmt = $pdo->prepare("UPDATE animal SET Health_Status = ? WHERE Animal_ID = ?");
            $stmt->execute([$healthStatus, $animalId]);
            $message = 'Health status updated successfully.';
            $messageType = 'success';
        }
    } elseif ($_POST['action'] === 'restock_food') {
        $animalId = (int)($_POST['animal_id'] ?? 0);
        $restockQty = max(1, (int)($_POST['restock_qty'] ?? 10));

        if ($animalId > 0) {
            $stmt = $pdo->prepare("
                UPDATE animal
                SET food_stock = LEAST(COALESCE(food_stock, 0) + ?, 100)
                WHERE Animal_ID = ?
            ");
            $stmt->execute([$restockQty, $animalId]);
            $message = 'Food restocked successfully.';
            $messageType = 'success';
        }
    }
}

$animals = $pdo->query("
    SELECT
        a.Animal_ID,
        a.Name,
        a.Species,
        a.Category,
        a.Age,
        a.Sex,
        COALESCE(a.Health_Status, 'Healthy') AS Health_Status,
        COALESCE(a.food_stock, 50) AS food_stock,
        e.Enclosure_Name
    FROM animal a
    LEFT JOIN enclosure e ON a.Enclosure_ID = e.Enclosure_ID
    ORDER BY a.Name
")->fetchAll(PDO::FETCH_ASSOC);

$totalAnimals = count($animals);
$sickAnimals = count(array_filter($animals, static fn($a) => $a['Health_Status'] === 'Sick'));
$lowFoodAnimals = count(array_filter($animals, static fn($a) => (int)$a['food_stock'] <= 10));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Caretaker Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body  { overflow: auto; }
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
            margin-bottom: 30px; 
            border-bottom: 3px solid var(--accent-color); 
            padding-bottom: 20px; 
            flex-wrap: wrap; 
            gap: 12px; 
        }
        .page-header h1 { 
            font-size: 2rem; 
            margin: 0; 
        }
        .page-header p { 
            font-size: 0.95rem; 
            color: var(--text-color); 
            margin-top: 4px; 
        }
        .header-actions { 
            display: flex; 
            gap: 10px; 
            align-items: center; 
            flex-wrap: wrap; 
        }
        .btn-nav { 
            padding: 9px 22px; 
            background-color: var(--base-color); 
            border: 2px solid var(--accent-color); 
            border-radius: 1000px; 
            font: inherit; 
            font-weight: 600; 
            font-size: 0.88rem; 
            cursor: pointer; 
            color: var(--text-color); 
            text-decoration: none; 
            transition: 150ms ease; 
        }
        .btn-nav:hover { 
            background-color: var(--accent-color); 
            text-decoration: none; 
        }
        .btn-logout { 
            padding: 9px 22px; 
            background-color: var(--accent-color); 
            border: none; 
            border-radius: 1000px; 
            font: inherit; 
            font-weight: 600; 
            cursor: pointer; 
            color: var(--text-color); 
            text-decoration: none; 
            transition: 150ms ease; 
        }
        .btn-logout:hover { 
            background-color: var(--text-color); 
            color: white; 
            text-decoration: none; 
        }
        .alert { 
            padding: 14px 20px; 
            border-radius: 10px; 
            margin-bottom: 22px; 
            font-weight: 600; 
            font-size: 0.95rem; 
        }
        .alert-success { 
            background-color: #d4edda; 
            color: #155724; 
            border: 1px solid #c3e6cb; 
        }
        .alert-warning { 
            background-color: #fff3cd; 
            color: #856404; 
            border: 1px solid #ffeeba; 
        }
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
        .nav-card a:hover { background: var(--accent-color); text-decoration: none; }
        .stats-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); 
            gap: 16px; 
            margin-bottom: 28px; 
        }
        .stat-card { 
            background: white; 
            border-radius: 14px; 
            padding: 20px 18px; 
            text-align: center; 
            box-shadow: 0 3px 8px rgba(0,0,0,0.07);
        }
        .stat-card .stat-number { 
            font-size: 2.2rem; 
            font-weight: 900; 
            color: var(--text-color); 
            line-height: 1; 
        }
        .stat-card .stat-label { 
            font-size: 0.82rem; 
            font-weight: 600; 
            color: #777; 
            margin-top: 6px; 
            text-transform: uppercase; 
            letter-spacing: 0.04em; 
        }
        .stat-card.danger  .stat-number { color: #e74c3c; }
        .stat-card.warning .stat-number { color: #f39c12; }
        .table-wrap { 
            background: white; 
            border-radius: 16px; 
            overflow: hidden; 
            box-shadow: 0 4px 14px rgba(0,0,0,0.08); 
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
        }
        th { 
            background-color: var(--accent-color); 
            color: white; 
            padding: 13px 15px; 
            text-align: left; 
            font-size: 0.88rem; 
            text-transform: uppercase; 
            letter-spacing: 0.04em; 
        }
        td { 
            padding: 11px 15px; 
            border-bottom: 1px solid #f0f0f0; 
            font-size: 0.93rem; 
            vertical-align: middle; 
        }
        tr:last-child td { border-bottom: none; }
        tbody tr:hover { background-color: rgba(187, 223, 158, 0.25); }
        .badge { 
            display: inline-block; 
            padding: 3px 11px; 
            border-radius: 1000px; 
            font-size: 0.78rem; 
            font-weight: 700; 
            text-transform: uppercase; 
            letter-spacing: 0.04em; 
        }
        .badge-healthy { 
            background-color: #d4edda; 
            color: #155724; 
        }
        .badge-sick { 
            background-color: #f8d7da; 
            color: #721c24; 
        }
        .badge-pending { 
            background-color: #fff3cd; 
            color: #856505; 
        }
        .food-bar-wrap { 
            display: flex; 
            align-items: center; 
            gap: 8px;
            min-width: 130px; 
        }
        .food-bar { 
            flex: 1; 
            height: 10px; 
            border-radius: 5px; 
            background: #eee; 
            overflow: hidden; 
        }
        .food-bar-fill { 
            height: 100%; 
            border-radius: 5px; 
        }
        .food-bar-fill.high { background-color: #2ecc71; }
        .food-bar-fill.medium { background-color: #f39c12; }
        .food-bar-fill.low { background-color: #e74c3c; }
        .food-pct { 
            font-size: 0.8rem; 
            font-weight: 700; 
            min-width: 34px; 
            text-align: right; 
        }
        .food-pct.low { color: #e74c3c; }
        .action-cell { 
            display: flex; 
            gap: 6px; 
            align-items: center; 
            flex-wrap: wrap; 
        }
        .health-select { 
            padding: 5px 10px; 
            border: 2px solid #ddd; 
            border-radius: 8px; 
            font: inherit; 
            font-size: 0.83rem; 
            font-weight: 600; 
            cursor: pointer; 
            background: white; 
            color: var(--text-color); 
        }
        .health-select:focus { 
            outline: none; 
            border-color: var(--accent-color); 
        }
        .restock-form { 
            display: flex; 
            align-items: center; 
            gap: 4px; 
            margin-top: 6px; 
        }
        .restock-qty { 
            width: 54px; 
            padding: 5px 8px; 
            border: 2px solid #ddd; 
            border-radius: 8px; 
            font: inherit; 
            font-size: 0.83rem; 
            text-align: center; 
        }
        .restock-qty:focus { 
            outline: none; 
            border-color: var(--accent-color); 
        }
        .btn-sm { 
            padding: 5px 12px; 
            border: none; 
            border-radius: 8px; 
            font: inherit; 
            font-size: 0.82rem; 
            font-weight: 700; 
            cursor: pointer; 
            transition: 150ms ease;
            white-space: nowrap; 
        }
        .btn-health { 
            background-color: var(--accent-color); 
            color: white; 
        }
        .btn-health:hover { 
            background-color: var(--text-color); 
        }
        .btn-restock { 
            background-color: #3498db; 
            color: white; 
        }
        .btn-restock:hover {background-color: #2980b9; }
        tr.low-food td:first-child { border-left: 4px solid #e74c3c; }
        .empty-state { 
            padding: 50px 20px; 
            text-align: center; 
            color: #888; 
        }
    </style>
</head>
<body>
<div class="page-wrapper">
    <div class="page-header">
        <div>
            <h1>Caretaker Dashboard</h1>
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

    <?php if ($message): ?>
        <div class="alert alert-<?= htmlspecialchars($messageType) ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <?php if ($lowFoodAnimals > 0): ?>
        <div class="alert alert-warning">
            <strong><?= $lowFoodAnimals ?> animal<?= $lowFoodAnimals > 1 ? 's' : '' ?></strong>
            <?= $lowFoodAnimals > 1 ? 'have' : 'has' ?> critically low food stock (≤10%). Restock needed.
        </div>
    <?php endif; ?>

    <div class="card-grid">
        <div class="nav-card">
            <h2>Data Entry</h2>
            <a href="add-animal.php">Add Animal</a>
            <a href="caretaker_dashboard.php">Update Animal Status</a>
        </div>
        <div class="nav-card">
            <h2>Reports</h2>
            <a href="animals_report.php">Animals Report</a>
            <a href="health-reports.php">Health Status Reports</a>
            <a href="revenue_report.php">Revenue Report</a>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number"><?= $totalAnimals ?></div>
            <div class="stat-label">Total Animals</div>
        </div>
        <div class="stat-card <?= $sickAnimals > 0 ? 'danger' : '' ?>">
            <div class="stat-number"><?= $sickAnimals ?></div>
            <div class="stat-label">Sick Animals</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= $totalAnimals - $sickAnimals ?></div>
            <div class="stat-label">Healthy Animals</div>
        </div>
        <div class="stat-card <?= $lowFoodAnimals > 0 ? 'warning' : '' ?>">
            <div class="stat-number"><?= $lowFoodAnimals ?></div>
            <div class="stat-label">Low Food Stock</div>
        </div>
    </div>

    <div class="table-wrap">
        <?php if (empty($animals)): ?>
            <div class="empty-state">No animals found in the database.</div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Species</th>
                        <th>Category</th>
                        <th>Age</th>
                        <th>Sex</th>
                        <th>Enclosure</th>
                        <th>Health Status</th>
                        <th>Food Stock</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($animals as $a):
                    $stock = (int)$a['food_stock'];
                    $pct = max(0, min(100, $stock));
                    $barClass = $pct > 40 ? 'high' : ($pct > 10 ? 'medium' : 'low');
                    $isLow = $pct <= 10;
                ?>
                    <tr class="<?= $isLow ? 'low-food' : '' ?>">
                        <td><?= (int)$a['Animal_ID'] ?></td>
                        <td><strong><?= htmlspecialchars($a['Name']) ?></strong></td>
                        <td><?= htmlspecialchars($a['Species']) ?></td>
                        <td><?= htmlspecialchars($a['Category']) ?></td>
                        <td><?= $a['Age'] !== null ? htmlspecialchars((string)$a['Age']) . ' yr' : '—' ?></td>
                        <td><?= htmlspecialchars($a['Sex']) ?></td>
                        <td><?= htmlspecialchars($a['Enclosure_Name'] ?? 'N/A') ?></td>
                        <td>
                            <form method="POST" action="caretaker_dashboard.php">
                                <input type="hidden" name="action" value="update_health">
                                <input type="hidden" name="animal_id" value="<?= (int)$a['Animal_ID'] ?>">
                                <div class="action-cell">
                                    <span class="badge badge-<?= strtolower($a['Health_Status']) ?>"><?= htmlspecialchars($a['Health_Status']) ?></span>
                                    <select name="health_status" class="health-select">
                                        <option value="Healthy" <?= $a['Health_Status'] === 'Healthy' ? 'selected' : '' ?>>Healthy</option>
                                        <option value="Sick" <?= $a['Health_Status'] === 'Sick' ? 'selected' : '' ?>>Sick</option>
                                        <option value="Pending" <?= $a['Health_Status'] === 'Pending' ? 'selected' : '' ?>>Pending</option>
                                    </select>
                                    <button type="submit" class="btn-sm btn-health">Update</button>
                                </div>
                            </form>
                        </td>
                        <td>
                            <div class="food-bar-wrap">
                                <div class="food-bar">
                                    <div class="food-bar-fill <?= $barClass ?>" style="width:<?= $pct ?>%"></div>
                                </div>
                                <span class="food-pct <?= $isLow ? 'low' : '' ?>"><?= $pct ?>%</span>
                            </div>
                            <form method="POST" action="caretaker_dashboard.php" class="restock-form">
                                <input type="hidden" name="action" value="restock_food">
                                <input type="hidden" name="animal_id" value="<?= (int)$a['Animal_ID'] ?>">
                                <input type="number" name="restock_qty" class="restock-qty" value="20" min="1" max="100" title="Amount to add">
                                <button type="submit" class="btn-sm btn-restock">Restock</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
