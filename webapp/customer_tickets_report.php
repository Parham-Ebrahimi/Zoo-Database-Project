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
    <link rel="stylesheet" href="index.css">

    <style>
        .page-wrapper {
            max-width: 1000px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .page-header {
            margin-bottom: 1.5rem;
        }

        .page-header h1 {
            font-size: 1.5rem;
            font-weight: 800;
            margin-bottom: 0.25rem;
        }

        .page-header p {
            color: #666;
            margin: 0;
        }

        .card {
            background: white;
            border-radius: 20px;
            padding: 1.8rem;
            box-shadow: var(--shadow);
            margin-bottom: 1.5rem;
        }

        .empty-msg {
            font-size: 0.95rem;
            color: #666;
        }

        .empty-msg a {
            color: var(--accent-color);
            font-weight: 600;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }

        th {
            background: var(--accent-color);
            color: white;
            padding: 10px 14px;
            text-align: left;
        }

        td {
            padding: 9px 14px;
            border-bottom: 1px solid #eee;
        }

        tr:hover td {
            background: #f9fff9;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 1rem;
            color: var(--text-color);
            font-weight: 600;
            text-decoration: none;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        .footnote {
            font-size: 0.85rem;
            color: #777;
        }
    </style>
</head>

<body class="cr-body">
    <div class="page-wrapper">
        <header class="site-header">
            <a class="logo" href="index.php">Greenwood Zoo</a>
            <nav>
                <a href="customer-dashboard.php">Dashboard</a>
                <a href="customer_animals_report.php">Animals</a>
                <a href="buy-tickets.php">Buy tickets</a>
                <a href="logout.php">Logout</a>
            </nav>
        </header>

        <main id="main">
            <div class="page-header">
                <a class="back-link" href="customer-dashboard.php">← Back to Home</a>
                <h1>My tickets</h1>
                <p>Your ticket purchase history and upcoming visit dates.</p>
            </div>

            <div class="card">
                <?php if (count($tickets) === 0): ?>
                    <p class="empty">You have not purchased any tickets yet. <a href="buy-tickets.php">Buy tickets</a> to plan your visit.</p>
                <?php else: ?>
                    <div>
                        <table>
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
            <p class="footnote">Questions about a ticket? Contact guest services with your order number.</p>
        </main>
    </div>
</body>
</html>
