<?php
session_start();
if (!isset($_SESSION['customer_id'])) {
    header('Location: sign-in.html');
    exit;
}
require_once 'db.php';

$customerID = (int) $_SESSION['customer_id'];

// Count upcoming visits
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM orders
    WHERE CustomerID = ? AND ScheduledDate >= CURDATE()
    AND OrderCategoryID BETWEEN 1 AND 5
");
$stmt->execute([$customerID]);
$upcomingVisits = $stmt->fetchColumn();

$featuredAnimals = [];
try {
    $feat = $pdo->query("
        SELECT a.Name, a.Species, a.Category, e.Enclosure_Name
        FROM animal a
        LEFT JOIN enclosure e ON a.Enclosure_ID = e.Enclosure_ID
        ORDER BY RAND()
        LIMIT 4
    ");
    $featuredAnimals = $feat->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $featuredAnimals = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — Greenwood Zoo</title>
    <link rel="stylesheet" href="customer-reports.css">
    <style>
        .cr-shell--dash {
            max-width: 1200px;
        }
        .welcome-banner {
            background: var(--cr-surface);
            border: 1px solid var(--cr-border);
            border-radius: var(--cr-radius);
            padding: 1.5rem 2rem;
            margin-bottom: 1.75rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .welcome-banner h1 {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0 0 0.25rem;
        }
        .welcome-banner p { margin: 0; color: var(--cr-muted); }
        .stat-pill {
            background: #eef6ea;
            color: var(--cr-accent);
            font-weight: 700;
            padding: 0.5rem 1.25rem;
            border-radius: 999px;
            font-size: 0.9rem;
            white-space: nowrap;
        }

        .dash-layout {
            display: grid;
            grid-template-columns: minmax(220px, 260px) minmax(0, 1fr);
            gap: 1.5rem;
            align-items: start;
        }
        @media (max-width: 900px) {
            .dash-layout {
                grid-template-columns: 1fr;
            }
        }

        .dash-sidebar {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .dash-card {
            background: var(--cr-surface);
            border: 1px solid var(--cr-border);
            border-radius: var(--cr-radius);
            padding: 1.5rem;
            box-shadow: var(--cr-shadow);
        }
        .dash-card h2 {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--cr-muted);
            margin: 0 0 1rem;
            font-weight: 600;
        }
        .dash-card a {
            display: block;
            padding: 0.65rem 0.9rem;
            margin-bottom: 0.5rem;
            background: #f4f7f2;
            border-radius: 8px;
            color: var(--cr-accent);
            font-weight: 600;
            font-size: 0.92rem;
            text-decoration: none;
            transition: background 150ms;
        }
        .dash-card a:last-child { margin-bottom: 0; }
        .dash-card a:hover { background: #ddefd5; text-decoration: none; }
        .dash-card a.primary {
            background: var(--cr-accent);
            color: white;
        }
        .dash-card a.primary:hover { background: #1a5c2b; }

        .dash-main {
            background: var(--cr-surface);
            border: 1px solid var(--cr-border);
            border-radius: var(--cr-radius);
            padding: 1.5rem 1.75rem 2rem;
            box-shadow: var(--cr-shadow);
        }
        .dash-main > h2 {
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--cr-accent);
            margin: 0 0 0.35rem;
        }
        .dash-main .dash-lead {
            margin: 0 0 1.5rem;
            color: var(--cr-muted);
            font-size: 0.92rem;
        }

        .attr-section {
            margin-bottom: 1.75rem;
        }
        .attr-section:last-child { margin-bottom: 0; }
        .attr-section h3 {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--cr-muted);
            margin: 0 0 0.85rem;
            font-weight: 600;
        }

        .animal-mini-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 0.85rem;
        }
        .animal-mini {
            padding: 0.9rem 1rem;
            background: #f8faf6;
            border: 1px solid var(--cr-border);
            border-radius: 10px;
        }
        .animal-mini strong {
            display: block;
            color: var(--cr-text);
            font-size: 0.98rem;
            margin-bottom: 0.25rem;
        }
        .animal-mini span {
            font-size: 0.82rem;
            color: var(--cr-muted);
            display: block;
            line-height: 1.35;
        }

        .show-table-wrap {
            overflow-x: auto;
            border-radius: 10px;
            border: 1px solid var(--cr-border);
        }
        .show-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.88rem;
        }
        .show-table th,
        .show-table td {
            padding: 0.65rem 0.85rem;
            text-align: left;
            border-bottom: 1px solid var(--cr-border);
        }
        .show-table th {
            background: #eef6ea;
            color: var(--cr-accent);
            font-weight: 600;
        }
        .show-table tr:last-child td { border-bottom: none; }
        .show-table td:first-child { font-weight: 600; color: var(--cr-text); }

        .attr-empty {
            color: var(--cr-muted);
            font-size: 0.9rem;
            margin: 0;
        }

        .logout-area { margin-top: 1.75rem; }
        .logout-area a {
            color: var(--cr-muted);
            font-size: 0.9rem;
            text-decoration: none;
        }
        .logout-area a:hover { color: var(--cr-accent); text-decoration: underline; }
    </style>
</head>
<body class="cr-body">
    <div class="cr-shell cr-shell--dash">
        <header class="cr-topbar">
            <span class="cr-brand">Greenwood Zoo</span>
            <nav class="cr-nav">
                <a class="cr-btn-outline" href="logout.php">Sign out</a>
            </nav>
        </header>

        <main>
            <div class="welcome-banner">
                <div>
                    <h1>Welcome back, <?= htmlspecialchars($_SESSION['firstname']) ?>!</h1>
                    <p>Manage your visits and explore our animals.</p>
                </div>
                <?php if ($upcomingVisits > 0): ?>
                    <span class="stat-pill"><?= $upcomingVisits ?> upcoming visit<?= $upcomingVisits > 1 ? 's' : '' ?></span>
                <?php endif; ?>
            </div>

            <div class="dash-layout">
                <aside class="dash-sidebar" aria-label="Quick links">
                    <div class="dash-card">
                        <h2>Tickets</h2>
                        <a href="buy-tickets.php" class="primary">Buy tickets</a>
                        <a href="customer_tickets_report.php">My ticket history</a>
                    </div>
                    <div class="dash-card">
                        <h2>Explore</h2>
                        <a href="customer_animals_report.php">View animals</a>
                    </div>
                </aside>

                <section class="dash-main" aria-labelledby="attractions-heading">
                    <h2 id="attractions-heading">Attractions &amp; schedule</h2>
                    <p class="dash-lead">Highlights from our habitats and live programs. Times may vary on holidays—check at the gate.</p>

                    <div class="attr-section">
                        <h3>Featured animals</h3>
                        <?php if (count($featuredAnimals) > 0): ?>
                            <div class="animal-mini-grid">
                                <?php foreach ($featuredAnimals as $a): ?>
                                <div class="animal-mini">
                                    <strong><?= htmlspecialchars($a['Name']) ?></strong>
                                    <span><?= htmlspecialchars($a['Species']) ?><?= !empty($a['Category']) ? ' · ' . htmlspecialchars($a['Category']) : '' ?></span>
                                    <?php if (!empty($a['Enclosure_Name'])): ?>
                                        <span><?= htmlspecialchars($a['Enclosure_Name']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="attr-empty">Animal highlights will appear here when connected to the collection. <a href="customer_animals_report.php">Browse all animals</a>.</p>
                        <?php endif; ?>
                    </div>

                    <div class="attr-section">
                        <h3>Shows &amp; keeper talks</h3>
                        <div class="show-table-wrap">
                            <table class="show-table">
                                <thead>
                                    <tr>
                                        <th scope="col">Program</th>
                                        <th scope="col">When</th>
                                        <th scope="col">Where</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>Wildlife Discovery Show</td>
                                        <td>Sat &amp; Sun · 11:00 a.m. &amp; 2:00 p.m.</td>
                                        <td>Amphitheater</td>
                                    </tr>
                                    <tr>
                                        <td>Birds of prey flight demo</td>
                                        <td>Daily · 1:00 p.m.</td>
                                        <td>Raptor ridge</td>
                                    </tr>
                                    <tr>
                                        <td>Big cats talk</td>
                                        <td>Mon–Fri · 10:30 a.m. · Weekends · 3:00 p.m.</td>
                                        <td>Feline overlook</td>
                                    </tr>
                                    <tr>
                                        <td>Children’s story &amp; meet a small animal</td>
                                        <td>Daily · 10:00 a.m. &amp; 4:00 p.m.</td>
                                        <td>Discovery barn</td>
                                    </tr>
                                    <tr>
                                        <td>Reptile encounter</td>
                                        <td>Tue, Thu, Sat · 12:30 p.m.</td>
                                        <td>Herpetarium lobby</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            </div>

            <div class="logout-area">
                <a href="logout.php">Sign out of your account</a>
            </div>
        </main>
    </div>
</body>
</html>
