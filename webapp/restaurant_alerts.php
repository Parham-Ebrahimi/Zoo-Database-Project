<?php
require_once __DIR__ . '/session_bootstrap.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit;
}
require_once 'db.php';

$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['admin', 'Restaurant Employee'], true)) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restock_food_id'])) {
    $foodID = (int) $_POST['restock_food_id'];
    $restockQty = max(1, (int) ($_POST['restock_qty'] ?? 1));
    $stmt = $pdo->prepare('UPDATE fooditem SET StockQty = ? WHERE FoodID = ?');
    $stmt->execute([$restockQty, $foodID]);
}

$alertsStmt = $pdo->query("
    SELECT ra.AlertID, ra.FoodID, ra.AlertType, ra.Message, ra.CreatedAt, fi.FoodName, fi.StockQty, fs.Name AS StallName
    FROM restaurant_restock_alerts ra
    JOIN fooditem fi ON fi.FoodID = ra.FoodID
    JOIN foodstall fs ON fs.StallID = fi.StallID
    WHERE ra.IsResolved = 0
    ORDER BY ra.CreatedAt DESC
");
$alerts = $alertsStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restaurant Restock Alerts</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .page-wrap { min-height: 100vh; padding: 28px 36px; background-color: var(--base-color); text-align: left; }
        .panel { background: #fff; border-radius: 14px; padding: 22px; margin-top: 14px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px 12px; border-bottom: 1px solid #e6ecd9; font-size: 0.92rem; vertical-align: top; }
        th { background: #f2f8eb; }
        .pill { border-radius: 999px; padding: 2px 9px; font-size: 0.78rem; font-weight: 700; display: inline-block; }
        .out { background: #ffe7e7; color: #8a1111; }
        .low { background: #fff4db; color: #8b5a00; }
        .restock-form { display: flex; align-items: center; gap: 0.4rem; }
        .restock-form input { width: 76px; padding: 6px 8px; border: 1px solid #c6d8bc; border-radius: 8px; font: inherit; font-size: 0.82rem; }
        button { border: none; background: var(--accent-color); color: var(--text-color); padding: 6px 10px; border-radius: 8px; cursor: pointer; font: inherit; font-size: 0.82rem; font-weight: 600; }
    </style>
</head>
<body>
    <div class="page-wrap">
        <h1>Restaurant Restock Alerts</h1>
        <p style="margin:.45rem 0 0; padding:.55rem .8rem; border-radius:8px; background:#fff1f1; color:#8a1111; font-weight:700;">
            Auto-triggered alerts: any restaurant item at stock ≤ 3 appears here.
        </p>
        <p class="top-actions" style="display:flex;flex-wrap:wrap;align-items:center;gap:10px 14px;margin:.5rem 0 0">
            <?php include __DIR__ . '/admin_header_cart_profile.inc.php'; ?>
            <?php if ($role === 'admin'): ?>
            <a href="logout.php" style="font-weight:700;color:#1a3d1c">Logout</a>
            <?php endif; ?>
            <a href="<?= $role === 'admin' ? 'dashboard.php#restaurant-shop-admin' : 'dashboard.php#restaurant-staff' ?>">Back to Dashboard</a>
            <?php if ($role === 'admin'): ?>
            <span>|</span>
            <a href="restaurant_sales_report.php">Restaurant Sales Report</a>
            <?php endif; ?>
        </p>

        <div class="panel">
            <?php if (count($alerts) === 0): ?>
                <p>No alerts yet.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Food Item</th>
                            <th>Stall</th>
                            <th>Current Stock</th>
                            <th>Message</th>
                            <th>Created</th>
                            <th>Restock</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($alerts as $a): ?>
                            <tr style="<?= ((int)$a['StockQty'] <= 3) ? 'background:#fff4f4;' : '' ?>">
                                <td>
                                    <?php if ($a['AlertType'] === 'OUT_OF_STOCK'): ?>
                                        <span class="pill out">OUT OF STOCK</span>
                                    <?php else: ?>
                                        <span class="pill low">LOW STOCK</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($a['FoodName']) ?></td>
                                <td><?= htmlspecialchars($a['StallName']) ?></td>
                                <td><?= (int) $a['StockQty'] ?></td>
                                <td><?= htmlspecialchars($a['Message']) ?></td>
                                <td><?= htmlspecialchars($a['CreatedAt']) ?></td>
                                <td>
                                    <form method="POST" class="restock-form">
                                        <input type="hidden" name="restock_food_id" value="<?= (int) $a['FoodID'] ?>">
                                        <input type="number" name="restock_qty" min="1" value="10" required>
                                        <button type="submit">Restock</button>
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
