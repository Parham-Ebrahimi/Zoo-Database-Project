<?php
require_once __DIR__ . '/session_bootstrap.php';
if (!isset($_SESSION['customer_id'])) {
    if (!empty($_SESSION['user_id'])) { header('Location: dashboard.php'); exit; }
    header('Location: login.html');
    exit;
}
require 'db.php';

$customerID = (int) $_SESSION['customer_id'];

// Ticket orders
$stmt = $pdo->prepare("
    SELECT o.OrderID, oc.CategoryName AS TicketType, oc.Price AS UnitPrice,
           ot.Quantity, o.TransactionAmount, o.PaymentMode,
           o.ScheduledDate AS VisitDate, o.OrderDate AS PurchaseDate
    FROM orders o
    JOIN ordercategories oc ON o.OrderCategoryID = oc.OrderCategoryID
    JOIN order_tickets ot   ON o.OrderID = ot.OrderID
    WHERE o.CustomerID = ?
    ORDER BY o.OrderDate DESC
");
$stmt->execute([$customerID]);
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Food orders
$stmt2 = $pdo->prepare("
    SELECT o.OrderID, o.OrderDate AS PurchaseDate, o.TransactionAmount,
           o.PaymentMode, fi.FoodName, ofi.Quantity, fi.Price AS UnitPrice,
           fs.Name AS StallName
    FROM orders o
    JOIN order_food_items ofi ON ofi.OrderID = o.OrderID
    JOIN fooditem fi           ON fi.FoodID = ofi.FoodID
    JOIN foodstall fs          ON fs.StallID = fi.StallID
    WHERE o.CustomerID = ?
    ORDER BY o.OrderDate DESC
");
$stmt2->execute([$customerID]);
$foodOrders = $stmt2->fetchAll(PDO::FETCH_ASSOC);

// Shop orders
$stmt3 = $pdo->prepare("
    SELECT o.OrderID, o.OrderDate AS PurchaseDate, o.TransactionAmount,
           o.PaymentMode, si.ItemName, osi.Quantity, si.Price AS UnitPrice,
           s.ShopName
    FROM orders o
    JOIN order_shop_items osi ON osi.OrderID = o.OrderID
    JOIN shop_items si         ON si.ShopItemID = osi.ShopItemID
    JOIN shops s               ON s.ShopID = si.ShopID
    WHERE o.CustomerID = ?
    ORDER BY o.OrderDate DESC
");
$stmt3->execute([$customerID]);
$shopOrders = $stmt3->fetchAll(PDO::FETCH_ASSOC);

$totalSpent = array_sum(array_column($tickets, 'TransactionAmount'))
            + array_sum(array_map(fn($r) => $r['UnitPrice'] * $r['Quantity'], $foodOrders))
            + array_sum(array_map(fn($r) => $r['UnitPrice'] * $r['Quantity'], $shopOrders));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My purchases — Greenwood Zoo</title>
    <link rel="stylesheet" href="index.css">
    <style>
        .page-wrapper { max-width: 1000px; margin: 2rem auto; padding: 0 1rem; }
        .page-header { margin-bottom: 1.5rem; }
        .page-header h1 { font-size: 1.5rem; font-weight: 800; margin-bottom: 0.25rem; }
        .page-header p { color: #666; margin: 0; }

        .summary-bar {
            display: flex; flex-wrap: wrap; gap: 12px;
            margin-bottom: 1.75rem;
        }
        .summary-pill {
            background: white; border: 1px solid var(--cr-border);
            border-radius: 12px; padding: 14px 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,.05); flex: 1; min-width: 140px;
        }
        .summary-pill .sp-label { font-size: .72rem; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; color: #888; margin-bottom: 5px; }
        .summary-pill .sp-val   { font-size: 1.4rem; font-weight: 800; color: var(--text-color); }

        .section-card { background: white; border-radius: 16px; padding: 1.4rem 1.6rem; box-shadow: var(--shadow); margin-bottom: 1.5rem; }
        .section-card h2 { font-size: 1rem; font-weight: 700; margin: 0 0 1rem; color: var(--text-color); display: flex; align-items: center; gap: .5rem; padding-bottom: .6rem; border-bottom: 2px solid var(--accent-color); }
        .section-card h2 .count { font-size: .78rem; font-weight: 600; color: #888; margin-left: auto; }

        table { width: 100%; border-collapse: collapse; font-size: 0.88rem; }
        th { background: var(--accent-color); color: white; padding: 9px 12px; text-align: left; font-size: .78rem; text-transform: uppercase; letter-spacing: .04em; }
        td { padding: 9px 12px; border-bottom: 1px solid #eee; vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #f9fff9; }
        .amt { font-weight: 700; color: #27ae60; }
        .badge { display: inline-block; padding: 2px 9px; border-radius: 999px; font-size: .72rem; font-weight: 700; }
        .badge-ticket { background: #d4edda; color: #155724; }
        .badge-food   { background: #fde8d0; color: #7d3c00; }
        .badge-shop   { background: #e8d5f0; color: #4a235a; }
        .badge-pay    { background: #e8f4fd; color: #1a5276; }
        .empty-msg { color: #aaa; font-style: italic; font-size: .9rem; padding: 1rem 0; }

        .back-link { display: inline-block; margin-bottom: 1rem; color: var(--text-color); font-weight: 600; text-decoration: none; }
        .back-link:hover { text-decoration: underline; }
        .footnote { font-size: 0.85rem; color: #777; }
    </style>
</head>
<body class="cr-body">
<div class="page-wrapper">
    <header class="site-header">
        <a class="logo" href="index.php">Greenwood Zoo</a>
        <?php require __DIR__ . '/customer_nav.php'; ?>
    </header>

    <main id="main">
        <div class="page-header">
            <a class="back-link" href="customer-dashboard.php">← Back to dashboard</a>
            <h1>My purchases</h1>
            <p>Your full history of tickets, food, and gift shop orders.</p>
        </div>

        <!-- Summary pills -->
        <div class="summary-bar">
            <div class="summary-pill">
                <div class="sp-label">Total spent</div>
                <div class="sp-val">$<?= number_format($totalSpent, 2) ?></div>
            </div>
            <div class="summary-pill">
                <div class="sp-label">Ticket orders</div>
                <div class="sp-val"><?= count($tickets) ?></div>
            </div>
            <div class="summary-pill">
                <div class="sp-label">Food orders</div>
                <div class="sp-val"><?= count($foodOrders) ?></div>
            </div>
            <div class="summary-pill">
                <div class="sp-label">Shop orders</div>
                <div class="sp-val"><?= count($shopOrders) ?></div>
            </div>
        </div>

        <!-- Tickets -->
        <div class="section-card">
            <h2>🎟️ Tickets <span class="count"><?= count($tickets) ?> order<?= count($tickets) !== 1 ? 's' : '' ?></span></h2>
            <?php if (empty($tickets)): ?>
                <p class="empty-msg">No ticket purchases yet. <a href="buy_tickets.php">Buy tickets</a> to plan your visit.</p>
            <?php else: ?>
            <table>
                <thead><tr>
                    <th>Order #</th><th>Type</th><th>Qty</th>
                    <th>Unit price</th><th>Total</th><th>Payment</th>
                    <th>Visit date</th><th>Purchased</th>
                </tr></thead>
                <tbody>
                <?php foreach ($tickets as $row): ?>
                <tr>
                    <td style="color:#aaa">#<?= $row['OrderID'] ?></td>
                    <td><span class="badge badge-ticket"><?= htmlspecialchars($row['TicketType']) ?></span></td>
                    <td><?= $row['Quantity'] ?></td>
                    <td>$<?= number_format($row['UnitPrice'], 2) ?></td>
                    <td class="amt">$<?= number_format($row['TransactionAmount'], 2) ?></td>
                    <td><span class="badge badge-pay"><?= htmlspecialchars($row['PaymentMode']) ?></span></td>
                    <td><?= $row['VisitDate'] ? date('M j, Y', strtotime($row['VisitDate'])) : '—' ?></td>
                    <td><?= date('M j, Y', strtotime($row['PurchaseDate'])) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- Food -->
        <div class="section-card">
            <h2>🍽️ Restaurant <span class="count"><?= count($foodOrders) ?> item<?= count($foodOrders) !== 1 ? 's' : '' ?></span></h2>
            <?php if (empty($foodOrders)): ?>
                <p class="empty-msg">No restaurant orders yet. <a href="restaurant.php">Browse the menu</a>.</p>
            <?php else: ?>
            <table>
                <thead><tr>
                    <th>Order #</th><th>Item</th><th>Stall</th>
                    <th>Qty</th><th>Unit price</th><th>Total</th>
                    <th>Payment</th><th>Date</th>
                </tr></thead>
                <tbody>
                <?php foreach ($foodOrders as $row): ?>
                <tr>
                    <td style="color:#aaa">#<?= $row['OrderID'] ?></td>
                    <td><span class="badge badge-food"><?= htmlspecialchars($row['FoodName']) ?></span></td>
                    <td style="font-size:.78rem;color:#888"><?= htmlspecialchars($row['StallName']) ?></td>
                    <td><?= $row['Quantity'] ?></td>
                    <td>$<?= number_format($row['UnitPrice'], 2) ?></td>
                    <td class="amt">$<?= number_format($row['UnitPrice'] * $row['Quantity'], 2) ?></td>
                    <td><span class="badge badge-pay"><?= htmlspecialchars($row['PaymentMode']) ?></span></td>
                    <td><?= date('M j, Y', strtotime($row['PurchaseDate'])) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- Shop -->
        <div class="section-card">
            <h2>🛍️ Gift Shop <span class="count"><?= count($shopOrders) ?> item<?= count($shopOrders) !== 1 ? 's' : '' ?></span></h2>
            <?php if (empty($shopOrders)): ?>
                <p class="empty-msg">No gift shop purchases yet. <a href="giftshop.php">Visit the shop</a>.</p>
            <?php else: ?>
            <table>
                <thead><tr>
                    <th>Order #</th><th>Item</th><th>Shop</th>
                    <th>Qty</th><th>Unit price</th><th>Total</th>
                    <th>Payment</th><th>Date</th>
                </tr></thead>
                <tbody>
                <?php foreach ($shopOrders as $row): ?>
                <tr>
                    <td style="color:#aaa">#<?= $row['OrderID'] ?></td>
                    <td><span class="badge badge-shop"><?= htmlspecialchars($row['ItemName']) ?></span></td>
                    <td style="font-size:.78rem;color:#888"><?= htmlspecialchars($row['ShopName']) ?></td>
                    <td><?= $row['Quantity'] ?></td>
                    <td>$<?= number_format($row['UnitPrice'], 2) ?></td>
                    <td class="amt">$<?= number_format($row['UnitPrice'] * $row['Quantity'], 2) ?></td>
                    <td><span class="badge badge-pay"><?= htmlspecialchars($row['PaymentMode']) ?></span></td>
                    <td><?= date('M j, Y', strtotime($row['PurchaseDate'])) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <p class="footnote">Questions about a purchase? Contact guest services with your order number.</p>
    </main>
</div>
</body>
</html>
