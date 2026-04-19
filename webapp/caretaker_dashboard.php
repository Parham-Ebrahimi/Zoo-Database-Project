<?php
require_once __DIR__ . '/session_bootstrap.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit;
}
require_once 'staff_home.php';

$roleRaw = strtolower(trim((string) ($_SESSION['role'] ?? '')));
$isVet = staff_is_vet_role();
$isAdmin = ($roleRaw === 'admin');
$isCaretakerSide = in_array($roleRaw, ['caretaker', 'keeper'], true);

if (!$isAdmin && !$isCaretakerSide && !$isVet) {
    header('Location: dashboard.php');
    exit;
}

require_once 'db.php';

if ($isVet) {
    $pageTitle = 'Animal care board';
} elseif ($isAdmin) {
    $pageTitle = 'Caretaker tools';
} else {
    $pageTitle = 'Caretaker dashboard';
}

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
        if ($isVet) {
            $message = 'Veterinarians cannot update food stock here. Use animal care staff for feeding inventory.';
            $messageType = 'warning';
        } else {
            $animalId = (int) ($_POST['animal_id'] ?? 0);
            $restockQty = max(1, (int) ($_POST['restock_qty'] ?? 10));

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
        .table-wrap {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.05);
            scroll-margin-top: 1.25rem;
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
        .food-vet-note {
            margin: 8px 0 0;
            font-size: 0.78rem;
            color: #666;
            line-height: 1.35;
        }
        tr.low-food td:first-child { border-left: 4px solid #e74c3c; }
        .empty-state { 
            padding: 50px 20px; 
            text-align: center; 
            color: #888; 
        }
    </style>
</head>
<body>
<div class="dashboard-wrapper">
<div class="dashboard-inner">

    <div class="dashboard-header">
        <div>
            <h1><?= htmlspecialchars($pageTitle) ?></h1>
            <p class="dash-meta"><?= date('l, F j, Y') ?></p>
        </div>
        <div class="dashboard-header-actions">
            <span class="user-name"><?= htmlspecialchars($_SESSION['firstname']) ?></span>
            <span class="role-badge"><?= htmlspecialchars($_SESSION['role']) ?></span>
            <?php if ($isAdmin): ?>
                <a href="dashboard.php" class="secondary-nav-btn">← Staff dashboard</a>
            <?php elseif ($isVet): ?>
                <a href="vet_dashboard.php" class="secondary-nav-btn">← Vet dashboard</a>
            <?php endif; ?>
            <a href="change-password.php" class="secondary-nav-btn">🔒 Change Password</a>

            <a href="logout.php" class="logout-btn">Logout</a>
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

    <div class="section-title">Animals &amp; enclosures</div>
    <div class="tiles-grid">
        <a href="add-animal.php" class="tile">
            <div class="tile-icon">➕</div>
            <div class="tile-text"><strong>Add animal</strong><span>Register a new animal</span></div>
        </a>
        <a href="animals_report.php" class="tile">
            <div class="tile-text"><strong>Animals report</strong><span>Search and filter animals</span></div>
        </a>
        <a href="health-reports.php" class="tile">
            <div class="tile-text"><strong>Health records</strong><span>Medical history and status</span></div>
        </a>
        <a href="#care-table" class="tile">
            <div class="tile-text">
                <strong>Health status table</strong>
                <span><?= $isVet ? 'Jump to the list to set Healthy, Sick, or Pending for each animal.' : 'Jump to the list to update health and restock food.' ?></span>
            </div>
        </a>
    </div>

    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-label">Total animals</div>
            <div class="stat-value"><?= $totalAnimals ?></div>
        </div>
        <div class="stat-card <?= $sickAnimals > 0 ? 'danger' : '' ?>">
            <div class="stat-label">Sick animals</div>
            <div class="stat-value"><?= $sickAnimals ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Healthy animals</div>
            <div class="stat-value"><?= $totalAnimals - $sickAnimals ?></div>
        </div>
        <div class="stat-card <?= $lowFoodAnimals > 0 ? 'warning' : '' ?>">
            <div class="stat-label">Low food stock</div>
            <div class="stat-value"><?= $lowFoodAnimals ?></div>
        </div>
    </div>

    <div class="table-wrap" id="care-table">
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
                        <th><?= $isVet ? 'Food stock (view)' : 'Food Stock' ?></th>
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
                            <?php if (!$isVet): ?>
                            <form method="POST" action="caretaker_dashboard.php" class="restock-form">
                                <input type="hidden" name="action" value="restock_food">
                                <input type="hidden" name="animal_id" value="<?= (int) $a['Animal_ID'] ?>">
                                <input type="number" name="restock_qty" class="restock-qty" value="20" min="1" max="100" title="Amount to add">
                                <button type="submit" class="btn-sm btn-restock">Restock</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

</div>
</div>
</body>
</html>
