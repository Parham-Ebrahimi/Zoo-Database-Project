<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit;
}
if (!in_array($_SESSION['role'], ['admin'])) {
    header('Location: dashboard.php');
    exit;
}
require 'db.php';

// Default date range — last 30 days
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$date_to   = $_GET['date_to']   ?? date('Y-m-d');

// Build query with filters
$stmt = $pdo->prepare("
    SELECT 
        RevenueDate,
        TicketRevenue,
        FoodRevenue,
        ShopRevenue,
        TotalRevenue
    FROM daily_revenue
    WHERE RevenueDate BETWEEN ? AND ?
    ORDER BY RevenueDate DESC
");
$stmt->execute([$date_from, $date_to]);
$rows = $stmt->fetchAll();

// Summary totals
$totals = $pdo->prepare("
    SELECT 
        SUM(TicketRevenue) as TotalTicket,
        SUM(FoodRevenue)   as TotalFood,
        SUM(ShopRevenue)   as TotalShop,
        SUM(TotalRevenue)  as GrandTotal
    FROM daily_revenue
    WHERE RevenueDate BETWEEN ? AND ?
");
$totals->execute([$date_from, $date_to]);
$summary = $totals->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Revenue Report — Greenwood Zoo</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body { overflow: auto; }
        .dashboard-wrapper { 
            box-sizing: border-box; 
            min-height: 100vh; 
            padding: 40px; 
            background-color: var(--base-color); 
        }
        .dashboard-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 30px; 
            border-bottom: 3px solid var(--accent-color); 
            padding-bottom: 20px; 
        }
        .filter-card { 
            background: white; 
            border-radius: 15px; 
            padding: 20px 25px; 
            margin-bottom: 25px; 
            box-shadow: 0 4px 10px rgba(0,0,0,0.05); 
        }
        .filter-card h2 { 
            font-size: 1rem; 
            font-weight: 700; 
            margin-bottom: 15px; 
            color: var(--text-color); 
        }
        .filter-grid { 
            display: flex; 
            flex-wrap: wrap; 
            gap: 15px; 
            align-items: flex-end; 
        }
        .filter-group { 
            display: flex; 
            flex-direction: column; 
            gap: 4px; 
        }
        .filter-group label { 
            font-size: 0.85rem; 
            font-weight: 600; 
            color: var(--text-color); 
        }
        .filter-group input { 
            padding: 8px 12px; 
            border: 2px solid #ddd; 
            border-radius: 8px; 
            font: inherit; 
        }
        .filter-group input:focus { 
            outline: none; 
            border-color: var(--accent-color); 
        }
        .search-btn { 
            padding: 9px 24px; 
            background: var(--accent-color); 
            border: none; 
            border-radius: 8px; 
            font: inherit; 
            font-weight: 600; 
            cursor: pointer; 
            color: white; 
        }
        .search-btn:hover { background: var(--text-color); }
        .reset-btn { 
            padding: 9px 24px; 
            background: #eee; 
            border: none; 
            border-radius: 8px; 
            font: inherit; 
            font-weight: 600; 
            cursor: pointer; 
            color: #555; 
            text-decoration: none; 
            display: inline-block; 
        }
        .reset-btn:hover { background: #ddd; }
        .summary-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); 
            gap: 15px; 
            margin-bottom: 25px; 
        }
        .summary-card { 
            background: white; 
            border-radius: 15px; 
            padding: 20px; 
            box-shadow: 0 4px 10px rgba(0,0,0,0.05); 
            text-align: center; 
        }
        .summary-card .label { 
            font-size: 0.85rem; 
            font-weight: 600; 
            color: #777; 
            text-transform: uppercase; 
            letter-spacing: 0.05em; 
            margin-bottom: 8px; 
        }
        .summary-card .amount { 
            font-size: 1.6rem; 
            font-weight: 900; 
            color: var(--text-color); 
        }
        .summary-card.total { 
            background: var(--text-color); 
            color: white;
        }
        .summary-card.total .label { color: rgba(255,255,255,0.7); }
        .summary-card.total .amount { color: white; }
        table {
             width: 100%; 
             border-collapse: collapse; 
             background: white; 
             border-radius: 15px; 
             overflow: hidden; 
             box-shadow: 0 4px 10px rgba(0,0,0,0.05); 
            }
        th { 
            background-color: var(--accent-color); 
            color: white; 
            padding: 12px 15px; 
            text-align: left; 
        }
        td { 
            padding: 10px 15px; 
            border-bottom: 1px solid #eee; 
        }
        tr:hover td { background: #f9fff9; }
        .tfoot-row td { 
            font-weight: 700; 
            background: var(--base-color); 
            border-top: 2px solid var(--accent-color); 
        }
        .positive { 
            color: #27ae60; 
            font-weight: 700; 
        }
        .zero { color: #aaa; }
        .back-btn { 
            display: inline-block; 
            margin-bottom: 20px; 
            padding: 8px 18px; 
            background: white; 
            border-radius: 8px; 
            color: var(--text-color); 
            font-weight: 600; 
            text-decoration: none; 
            border: 2px solid var(--accent-color); 
            font-size: 0.9rem; 
        }
        .back-btn:hover { 
            background: var(--accent-color); 
            color: white; 
        }
        .logout-btn { 
            padding: 10px 25px; 
            background-color: var(--accent-color); 
            border: none; 
            border-radius: 1000px; 
            font: inherit; 
            font-weight: 600; 
            cursor: pointer; 
            color: var(--text-color); 
            text-decoration: none; 
        }
        .logout-btn:hover { 
            background-color: var(--text-color); 
            color: white; 
        }
        .no-results { 
            text-align: center; 
            padding: 2rem; 
            color: #999; 
            font-style: italic; 
        }
    </style>
</head>
<body>
<div class="dashboard-wrapper">
    <div class="dashboard-header">
        <div>
            <h1>Revenue Report</h1>
            <p>Welcome, <?= htmlspecialchars($_SESSION['firstname']) ?> | Role: <?= $_SESSION['role'] ?></p>
        </div>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>

    <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>

    <!-- Filter Form -->
    <div class="filter-card">
        <h2>Filter by Date Range</h2>
        <form method="GET">
            <div class="filter-grid">
                <div class="filter-group">
                    <label>Date From</label>
                    <input type="date" name="date_from" value="<?= $date_from ?>">
                </div>
                <div class="filter-group">
                    <label>Date To</label>
                    <input type="date" name="date_to" value="<?= $date_to ?>">
                </div>
                <button type="submit" class="search-btn">Search</button>
                <a href="revenue_report.php" class="reset-btn">Reset</a>
            </div>
        </form>
    </div>

    <!-- Results Table -->
    <?php if (count($rows) === 0): ?>
        <p class="no-results">No revenue data found for the selected date range.</p>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Ticket Revenue</th>
                <th>Food Revenue</th>
                <th>Shop Revenue</th>
                <th>Total Revenue</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $row): ?>
            <tr>
                <td><?= $row['RevenueDate'] ?></td>
                <td class="<?= $row['TicketRevenue'] > 0 ? 'positive' : 'zero' ?>">
                    $<?= number_format($row['TicketRevenue'], 2) ?>
                </td>
                <td class="<?= $row['FoodRevenue'] > 0 ? 'positive' : 'zero' ?>">
                    $<?= number_format($row['FoodRevenue'], 2) ?>
                </td>
                <td class="<?= $row['ShopRevenue'] > 0 ? 'positive' : 'zero' ?>">
                    $<?= number_format($row['ShopRevenue'], 2) ?>
                </td>
                <td class="positive">
                    $<?= number_format($row['TotalRevenue'], 2) ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr class="tfoot-row">
                <td>TOTALS</td>
                <td>$<?= number_format($summary['TotalTicket'] ?? 0, 2) ?></td>
                <td>$<?= number_format($summary['TotalFood'] ?? 0, 2) ?></td>
                <td>$<?= number_format($summary['TotalShop'] ?? 0, 2) ?></td>
                <td>$<?= number_format($summary['GrandTotal'] ?? 0, 2) ?></td>
            </tr>
        </tfoot>
    </table>
    <?php endif; ?>
</div>
</body>
</html>