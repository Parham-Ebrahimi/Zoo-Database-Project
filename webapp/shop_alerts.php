<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit;
}
require_once 'db.php';

$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['admin', 'Gift Shop Employee'], true)) {
    header('Location: dashboard.php');
    exit;
}

$restockError = '';
$restockOk = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restock_item_id'])) {
    $shopItemID = (int) $_POST['restock_item_id'];
    $restockQty = max(1, (int) ($_POST['restock_qty'] ?? 1));

    try {
        $pdo->beginTransaction();

        // uq_open_alert may be unique on (ShopItemID, AlertType) without IsResolved, so a resolved
        // OUT_OF_STOCK row still blocks a new insert from the AFTER UPDATE trigger. Remove gift-shop
        // alert rows for this item so the trigger can insert a fresh LOW_STOCK / OUT_OF_STOCK if needed.
        $purge = $pdo->prepare('
            DELETE FROM restock_alerts
            WHERE ShopItemID = ?
              AND AlertType IN (\'OUT_OF_STOCK\', \'LOW_STOCK\')
        ');
        $purge->execute([$shopItemID]);

        // Add units to shelf (not replace total).
        $stmt = $pdo->prepare('
            UPDATE shop_items
            SET StockQty = COALESCE(StockQty, 0) + ?
            WHERE ShopItemID = ?
        ');
        $stmt->execute([$restockQty, $shopItemID]);
        if ($stmt->rowCount() === 0) {
            throw new RuntimeException('Item not found.');
        }

        $pdo->commit();
        $restockOk = true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $restockError = 'Could not restock: ' . htmlspecialchars($e->getMessage());
    }
}

$alertsStmt = $pdo->query("
    SELECT ra.AlertID, ra.ShopItemID, ra.AlertType, ra.Message, ra.CreatedAt, ra.IsResolved, si.ItemName, si.StockQty
    FROM restock_alerts ra
    JOIN shop_items si ON si.ShopItemID = ra.ShopItemID
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
    <title>Shop Restock Alerts</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .page-wrap {
            min-height: 100vh;
            padding: 28px 36px;
            background-color: var(--base-color);
            text-align: left;
        }
        .panel {
            background: #fff;
            border-radius: 14px;
            padding: 22px;
            margin-top: 14px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 10px 12px;
            border-bottom: 1px solid #e6ecd9;
            font-size: 0.92rem;
            vertical-align: top;
        }
        th { background: #f2f8eb; }
        .pill {
            border-radius: 999px;
            padding: 2px 9px;
            font-size: 0.78rem;
            font-weight: 700;
            display: inline-block;
        }
        .out { background: #ffe7e7; color: #8a1111; }
        .low { background: #fff4db; color: #8b5a00; }
        .open { color: #8a1111; font-weight: 700; }
        .resolved { color: #2a6e26; font-weight: 700; }
        .top-actions a {
            display: inline-block;
            margin-right: 1rem;
            font-weight: 600;
        }
        button {
            border: none;
            background: var(--accent-color);
            color: var(--text-color);
            padding: 6px 10px;
            border-radius: 8px;
            cursor: pointer;
            font: inherit;
            font-size: 0.82rem;
            font-weight: 600;
        }
        .restock-form {
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }
        .restock-form input {
            width: 76px;
            padding: 6px 8px;
            border: 1px solid #c6d8bc;
            border-radius: 8px;
            font: inherit;
            font-size: 0.82rem;
        }
        .action-stack {
            display: flex;
            flex-direction: column;
            gap: 0.45rem;
            align-items: flex-start;
        }
    </style>
</head>
<body>
    <div class="page-wrap">
        <h1>Gift Shop Restock Alerts</h1>
        <?php if ($restockOk): ?>
            <p style="background:#ecf9e8;color:#205f18;padding:.65rem 1rem;border-radius:8px;font-weight:600;margin:.75rem 0 0;">Restock saved. Inventory updated.</p>
        <?php elseif ($restockError !== ''): ?>
            <p style="background:#ffeaea;color:#8a1111;padding:.65rem 1rem;border-radius:8px;font-weight:600;margin:.75rem 0 0;"><?= htmlspecialchars($restockError) ?></p>
        <?php endif; ?>
        <div class="top-actions">
            <a href="dashboard.php">Back to Dashboard</a>
            <a href="giftshop.php">Customer Gift Shop</a>
        </div>

        <div class="panel">
            <?php if (count($alerts) === 0): ?>
                <p>No alerts yet.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Item</th>
                            <th>Current Stock</th>
                            <th>Message</th>
                            <th>Created</th>
                            <th>Restock</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($alerts as $a): ?>
                            <tr>
                                <td>
                                    <?php if ($a['AlertType'] === 'OUT_OF_STOCK'): ?>
                                        <span class="pill out">OUT OF STOCK</span>
                                    <?php else: ?>
                                        <span class="pill low">LOW STOCK</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($a['ItemName']) ?></td>
                                <td><?= (int) $a['StockQty'] ?></td>
                                <td><?= htmlspecialchars($a['Message']) ?></td>
                                <td><?= htmlspecialchars($a['CreatedAt']) ?></td>
                                <td>
                                    <div class="action-stack">
                                        <?php if ((int) $a['StockQty'] <= 3): ?>
                                            <form method="POST" class="restock-form">
                                                <input type="hidden" name="restock_item_id" value="<?= (int) $a['ShopItemID'] ?>">
                                                <input type="number" name="restock_qty" min="1" value="10" required title="Units to add to current stock">
                                                <button type="submit">Add stock</button>
                                            </form>
                                        <?php else: ?>
                                            <span>Stock above threshold</span>
                                        <?php endif; ?>
                                    </div>
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
