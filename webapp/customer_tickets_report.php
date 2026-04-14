<?php
session_start();
if (!isset($_SESSION['customer_id'])) {
    header('Location: login.html');
    exit;
}
require 'db.php';

$customerID = (int) $_SESSION['customer_id'];

$stmt = $pdo->prepare("
    SELECT
        o.OrderID,
        oc.CategoryName  AS TicketType,
        oc.Price         AS UnitPrice,
        ot.Quantity,
        o.TransactionAmount,
        o.PaymentMode,
        o.ScheduledDate  AS VisitDate,
        o.OrderDate      AS PurchaseDate
    FROM orders o
    JOIN ordercategories oc ON o.OrderCategoryID = oc.OrderCategoryID
    JOIN order_tickets ot   ON o.OrderID = ot.OrderID
    WHERE o.CustomerID = ?
    ORDER BY o.OrderDate DESC
");
$stmt->execute([$customerID]);
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My tickets — Greenwood Zoo</title>
    <link rel="stylesheet" href="customer-reports.css">
</head>
<body class="cr-body">
    <a class="cr-skip" href="#main">Skip to content</a>
    <div class="cr-shell">
        <header class="cr-topbar">
            <span class="cr-brand">Greenwood Zoo</span>
            <nav class="cr-nav">
                <a href="customer-dashboard.php">Dashboard</a>
                <a href="customer_animals_report.php">Animals</a>
                <a href="buy-tickets.php">Buy tickets</a>
                <a class="cr-btn-outline" href="logout.php">Sign out</a>
            </nav>
        </header>

        <main id="main">
            <div class="cr-hero">
                <h1>My tickets</h1>
                <p>Your ticket purchase history and upcoming visit dates.</p>
            </div>

            <div class="cr-card">
                <?php if (count($tickets) === 0): ?>
                    <p class="cr-empty">You have not purchased any tickets yet. <a href="buy-tickets.php">Buy tickets</a> to plan your visit.</p>
                <?php else: ?>
                    <div class="cr-table-wrap">
                        <table class="cr-table">
                            <thead>
                                <tr>
                                    <th>Order #</th>
                                    <th>Type</th>
                                    <th>Qty</th>
                                    <th>Unit price</th>
                                    <th>Total</th>
                                    <th>Payment</th>
                                    <th>Visit date</th>
                                    <th>Purchased</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tickets as $row): ?>
                                <tr>
                                    <td data-label="Order #">#<?= $row['OrderID'] ?></td>
                                    <td data-label="Type"><?= htmlspecialchars($row['TicketType']) ?></td>
                                    <td data-label="Qty"><?= $row['Quantity'] ?></td>
                                    <td data-label="Unit price">$<?= number_format($row['UnitPrice'], 2) ?></td>
                                    <td data-label="Total">$<?= number_format($row['TransactionAmount'], 2) ?></td>
                                    <td data-label="Payment"><?= htmlspecialchars($row['PaymentMode']) ?></td>
                                    <td data-label="Visit date"><?= htmlspecialchars($row['VisitDate'] ?? '—') ?></td>
                                    <td data-label="Purchased"><?= htmlspecialchars($row['PurchaseDate']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            <p class="cr-footnote">Questions about a ticket? Contact guest services with your order number.</p>
        </main>
    </div>
</body>
</html>
