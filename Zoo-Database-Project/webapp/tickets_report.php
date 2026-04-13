<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: sign-in.html');
    exit;
}
require 'db.php';

$result = $pdo->query("
    SELECT
        o.OrderID,
        CONCAT(c.FirstName, ' ', c.LastName) AS CustomerName,
        oc.CategoryName  AS TicketType,
        ot.Quantity,
        oc.Price         AS UnitPrice,
        o.TransactionAmount,
        o.PaymentMode,
        o.ScheduledDate  AS VisitDate,
        o.OrderDate      AS PurchaseDate
    FROM orders o
    JOIN ordercategories oc ON o.OrderCategoryID = oc.OrderCategoryID
    JOIN order_tickets ot   ON o.OrderID = ot.OrderID
    JOIN customers c        ON o.CustomerID = c.CustomerID
    WHERE o.OrderCategoryID BETWEEN 1 AND 5
    ORDER BY o.OrderDate DESC
");
$tickets = $result->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Tickets Report</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body { overflow: auto; }
        .dashboard-wrapper { box-sizing: border-box; min-height: 100vh; padding: 40px; background-color: rgba(187, 223, 158, 0.95); }
        .dashboard-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; border-bottom: 3px solid var(--accent-color); padding-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; background: white; border-radius: 15px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        th { background-color: var(--accent-color); color: white; padding: 12px 15px; text-align: left; }
        td { padding: 10px 15px; border-bottom: 1px solid #e0e0e0; }
        tr:hover { background-color: var(--base-color); }
        .logout-btn { padding: 10px 25px; background-color: var(--accent-color); border: none; border-radius: 1000px; font: inherit; font-weight: 600; cursor: pointer; color: var(--text-color); text-decoration: none; }
        .logout-btn:hover { background-color: var(--text-color); color: white; }
        .back-btn { display: inline-block; margin-bottom: 20px; padding: 10px 20px; background-color: var(--base-color); border-radius: 8px; color: var(--text-color); font-weight: 600; text-decoration: none; }
        .back-btn:hover { background-color: var(--accent-color); }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <div class="dashboard-header">
            <h1>Tickets Report</h1>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>

        <a href="admin-dashboard.php" class="back-btn">← Back to Dashboard</a>

        <?php if (count($tickets) === 0): ?>
            <p>No ticket orders found.</p>
        <?php else: ?>
        <table>
            <tr>
                <th>Order ID</th>
                <th>Customer</th>
                <th>Ticket Type</th>
                <th>Qty</th>
                <th>Unit Price</th>
                <th>Total</th>
                <th>Payment</th>
                <th>Visit Date</th>
                <th>Purchase Date</th>
            </tr>
            <?php foreach ($tickets as $row): ?>
            <tr>
                <td>#<?= $row['OrderID'] ?></td>
                <td><?= htmlspecialchars($row['CustomerName']) ?></td>
                <td><?= htmlspecialchars($row['TicketType']) ?></td>
                <td><?= $row['Quantity'] ?></td>
                <td>$<?= number_format($row['UnitPrice'], 2) ?></td>
                <td>$<?= number_format($row['TransactionAmount'], 2) ?></td>
                <td><?= htmlspecialchars($row['PaymentMode']) ?></td>
                <td><?= htmlspecialchars($row['VisitDate'] ?? '—') ?></td>
                <td><?= htmlspecialchars($row['PurchaseDate']) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php endif; ?>
    </div>
</body>
</html>
