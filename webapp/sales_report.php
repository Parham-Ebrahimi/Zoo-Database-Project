<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: sign-in.html');
    exit;
}
require_once 'db.php';

$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['admin', 'Gift Shop Employee'], true)) {
    header('Location: dashboard.php');
    exit;
}

$rows = $pdo->query("
    SELECT
        o.OrderID,
        o.OrderDate,
        c.FirstName,
        c.LastName,
        c.Email,
        si.ItemName,
        s.ShopName,
        osi.Quantity,
        si.Price,
        (osi.Quantity * si.Price) AS LineTotal,
        o.PaymentMode
    FROM orders o
    JOIN order_shop_items osi ON osi.OrderID = o.OrderID
    JOIN shop_items si ON si.ShopItemID = osi.ShopItemID
    JOIN shops s ON s.ShopID = si.ShopID
    JOIN customers c ON c.CustomerID = o.CustomerID
    WHERE o.OrderCategoryID = 6
    ORDER BY o.OrderDate DESC, o.OrderID DESC
")->fetchAll(PDO::FETCH_ASSOC);

$grandTotal = 0.0;
foreach ($rows as $r) {
    $grandTotal += (float) $r['LineTotal'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gift Shop Sales Report</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .page-wrap { min-height: 100vh; padding: 30px 40px; background-color: var(--base-color); text-align: left; }
        .panel { background: #fff; border-radius: 14px; padding: 20px; margin-top: 12px; }
        table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        th, td { padding: 9px 10px; border-bottom: 1px solid #e6ecd9; }
        th { background: #f2f8eb; text-align: left; }
        .summary { margin-top: 10px; font-weight: 700; }
    </style>
</head>
<body>
    <div class="page-wrap">
        <h1>Gift Shop Sales Report</h1>
        <p><a href="dashboard.php">Back to Dashboard</a> | <a href="add-order.php">Record Sale</a></p>
        <div class="panel">
            <?php if (count($rows) === 0): ?>
                <p>No gift shop sales recorded yet.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Date</th>
                            <th>Customer</th>
                            <th>Email</th>
                            <th>Shop</th>
                            <th>Item</th>
                            <th>Qty</th>
                            <th>Unit Price</th>
                            <th>Line Total</th>
                            <th>Payment</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td>#<?= (int) $r['OrderID'] ?></td>
                                <td><?= htmlspecialchars($r['OrderDate']) ?></td>
                                <td><?= htmlspecialchars($r['FirstName'] . ' ' . $r['LastName']) ?></td>
                                <td><?= htmlspecialchars($r['Email']) ?></td>
                                <td><?= htmlspecialchars($r['ShopName']) ?></td>
                                <td><?= htmlspecialchars($r['ItemName']) ?></td>
                                <td><?= (int) $r['Quantity'] ?></td>
                                <td>$<?= number_format((float) $r['Price'], 2) ?></td>
                                <td>$<?= number_format((float) $r['LineTotal'], 2) ?></td>
                                <td><?= htmlspecialchars($r['PaymentMode']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="summary">Total gift shop sales: $<?= number_format($grandTotal, 2) ?></div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
