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
                <h1>Animals</h1>
                <p>Meet the animals in our care. Information is updated regularly for visitors.</p>
            </div>

            <div class="ccard">
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
