<?php
session_start();

if (!isset($_SESSION['customer_id'])) {
    header('Location: customer-login.html');
    exit;
}

require 'db.php';

$customerId = (int) $_SESSION['customer_id'];

$tickets = [];
$ticketLoadError = null;

try {
    $stmt = $pdo->prepare("
        SELECT Ticket_ID, Ticket_type, Price, Payment_type, Visit_date, Purchase_date
        FROM tickets
        WHERE CustomerID = ?
        ORDER BY Purchase_date DESC
    ");
    $stmt->execute([$customerId]);
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    try {
        $stmt = $pdo->prepare("
            SELECT Ticket_ID, Ticket_type, Price, Payment_type, Visit_date, Purchase_date
            FROM tickets
            WHERE Customer_ID = ?
            ORDER BY Purchase_date DESC
        ");
        $stmt->execute([$customerId]);
        $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e2) {
        $ticketLoadError = 'We could not load your tickets right now. Please try again later or contact guest services.';
    }
}
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
            <nav class="cr-nav" aria-label="Report navigation">
                <a href="customer-dashboard.php">Dashboard</a>
                <a href="customer_animals_report.php">Animals</a>
                <a class="cr-btn-outline" href="logout.php">Sign out</a>
            </nav>
        </header>

        <main id="main">
            <div class="cr-hero">
                <h1>My tickets</h1>
                <p>Your ticket purchases and visit details. Only tickets linked to your account are shown.</p>
            </div>

            <div class="cr-card">
                <?php if ($ticketLoadError !== null): ?>
                    <p class="cr-empty"><?= htmlspecialchars($ticketLoadError) ?></p>
                <?php elseif (count($tickets) === 0): ?>
                    <p class="cr-empty">You do not have any tickets yet. Purchase tickets when you plan your visit.</p>
                <?php else: ?>
                    <div class="cr-table-wrap">
                        <table class="cr-table">
                            <thead>
                                <tr>
                                    <th scope="col">Ticket</th>
                                    <th scope="col">Type</th>
                                    <th scope="col">Price</th>
                                    <th scope="col">Payment</th>
                                    <th scope="col">Visit date</th>
                                    <th scope="col">Purchased</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tickets as $row): ?>
                                <tr>
                                    <td data-label="Ticket">#<?= htmlspecialchars((string)($row['Ticket_ID'] ?? '')) ?></td>
                                    <td data-label="Type"><?= htmlspecialchars($row['Ticket_type'] ?? '') ?></td>
                                    <td data-label="Price"><?= isset($row['Price']) ? '$' . number_format((float)$row['Price'], 2) : '—' ?></td>
                                    <td data-label="Payment"><?= htmlspecialchars($row['Payment_type'] ?? '') ?></td>
                                    <td data-label="Visit date"><?= htmlspecialchars((string)($row['Visit_date'] ?? '')) ?></td>
                                    <td data-label="Purchased"><?= htmlspecialchars((string)($row['Purchase_date'] ?? '')) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <p class="cr-footnote">Questions about a ticket? Contact guest services with your ticket number.</p>
        </main>
    </div>
</body>
</html>
