<?php
session_start();

if (!isset($_SESSION['customer_id'])) {
    header('Location: login.html');
    exit;
}

require 'db.php';

$result = $pdo->query("
    SELECT a.Animal_ID, a.Name, a.Species, a.Category, a.Age, a.Sex,
           e.Enclosure_Name
    FROM animal a
    LEFT JOIN enclosure e ON a.Enclosure_ID = e.Enclosure_ID
    ORDER BY a.Name
");
$animals = $result->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Our animals — Greenwood Zoo</title>
    <link rel="stylesheet" href="customer-reports.css">
</head>
<body class="cr-body">
    <a class="cr-skip" href="#main">Skip to content</a>
    <div class="cr-shell">
        <header class="cr-topbar">
            <span class="cr-brand">Greenwood Zoo</span>
            <nav class="cr-nav" aria-label="Report navigation">
                <a href="customer-dashboard.php">Dashboard</a>
                <a href="customer_tickets_report.php">My tickets</a>
                <a class="cr-btn-outline" href="logout.php">Sign out</a>
            </nav>
        </header>

        <main id="main">
            <div class="cr-hero">
                <h1>Animals</h1>
                <p>Meet the animals in our care. Information is updated regularly for visitors.</p>
            </div>

            <div class="cr-card">
                <?php if (count($animals) === 0): ?>
                    <p class="cr-empty">No animals are listed in the directory right now. Please check back later.</p>
                <?php else: ?>
                    <div class="cr-table-wrap">
                        <table class="cr-table">
                            <thead>
                                <tr>
                                    <th scope="col">Name</th>
                                    <th scope="col">Species</th>
                                    <th scope="col">Category</th>
                                    <th scope="col">Age</th>
                                    <th scope="col">Sex</th>
                                    <th scope="col">Enclosure</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($animals as $row): ?>
                                <tr>
                                    <td data-label="Name"><?= htmlspecialchars($row['Name'] ?? '') ?></td>
                                    <td data-label="Species"><?= htmlspecialchars($row['Species'] ?? '') ?></td>
                                    <td data-label="Category"><?= htmlspecialchars($row['Category'] ?? '') ?></td>
                                    <td data-label="Age"><?= htmlspecialchars((string)($row['Age'] ?? '')) ?></td>
                                    <td data-label="Sex"><?= htmlspecialchars($row['Sex'] ?? '') ?></td>
                                    <td data-label="Enclosure"><?= htmlspecialchars($row['Enclosure_Name'] ?? '—') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <p class="cr-footnote">For staff changes to animal records, please contact zoo administration.</p>
        </main>
    </div>
</body>
</html>
