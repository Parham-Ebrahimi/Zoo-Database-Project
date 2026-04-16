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

// Filters
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$date_to   = $_GET['date_to']   ?? date('Y-m-d');
$type      = $_GET['type'] ?? 'all';
$group     = $_GET['group'] ?? 'day';
$sort      = $_GET['sort'] ?? 'period';

$allowedTypes = ['all', 'ticket', 'food', 'shop'];
$allowedGroups = ['day', 'month', 'quarter', 'year'];
$allowedSorts = ['period', 'selected', 'total'];

if (!in_array($type, $allowedTypes, true)) {
    $type = 'all';
}
if (!in_array($group, $allowedGroups, true)) {
    $group = 'day';
}
if (!in_array($sort, $allowedSorts, true)) {
    $sort = 'period';
}

// Determine selected revenue column
$column = "TotalRevenue";
$selectedLabel = "Total Revenue";
if ($type === 'ticket') {
    $column = "TicketRevenue";
    $selectedLabel = "Ticket Revenue";
} elseif ($type === 'food') {
    $column = "FoodRevenue";
    $selectedLabel = "Food Revenue";
} elseif ($type === 'shop') {
    $column = "ShopRevenue";
    $selectedLabel = "Shop Revenue";
}

// Grouping expression
$periodLabelSql = "DATE_FORMAT(RevenueDate, '%Y-%m-%d')";
$periodStartSql = "RevenueDate";
if ($group === 'month') {
    $periodLabelSql = "DATE_FORMAT(RevenueDate, '%Y-%m')";
    $periodStartSql = "DATE_FORMAT(RevenueDate, '%Y-%m-01')";
} elseif ($group === 'quarter') {
    $periodLabelSql = "CONCAT(YEAR(RevenueDate), '-Q', QUARTER(RevenueDate))";
    $periodStartSql = "STR_TO_DATE(CONCAT(YEAR(RevenueDate), '-', LPAD((QUARTER(RevenueDate)-1)*3+1, 2, '0'), '-01'), '%Y-%m-%d')";
} elseif ($group === 'year') {
    $periodLabelSql = "CAST(YEAR(RevenueDate) AS CHAR)";
    $periodStartSql = "DATE_FORMAT(RevenueDate, '%Y-01-01')";
}

// Sorting
$orderBy = "PeriodStart DESC";
if ($sort === 'selected') {
    $orderBy = "SelectedRevenue DESC";
} elseif ($sort === 'total') {
    $orderBy = "TotalRevenue DESC";
}

// Main query (grouped report)
$stmt = $pdo->prepare("
    SELECT
        $periodLabelSql AS PeriodLabel,
        $periodStartSql AS PeriodStart,
        SUM(TicketRevenue) AS TicketRevenue,
        SUM(FoodRevenue) AS FoodRevenue,
        SUM(ShopRevenue) AS ShopRevenue,
        SUM(TotalRevenue) AS TotalRevenue,
        SUM($column) AS SelectedRevenue
    FROM daily_revenue
    WHERE RevenueDate BETWEEN ? AND ?
    GROUP BY PeriodLabel, PeriodStart
    ORDER BY $orderBy
");
$stmt->execute([$date_from, $date_to]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Totals
$totals = $pdo->prepare("
    SELECT 
        SUM(TicketRevenue) as TotalTicket,
        SUM(FoodRevenue)   as TotalFood,
        SUM(ShopRevenue)   as TotalShop,
        SUM(TotalRevenue)  as GrandTotal,
        SUM($column)       as SelectedTotal
    FROM daily_revenue
    WHERE RevenueDate BETWEEN ? AND ?
");
$totals->execute([$date_from, $date_to]);
$summary = $totals->fetch();

// Average revenue
$avg = $pdo->prepare("
    SELECT AVG(TotalRevenue) as AvgRevenue
    FROM daily_revenue
    WHERE RevenueDate BETWEEN ? AND ?
");
$avg->execute([$date_from, $date_to]);
$avgRow = $avg->fetch();

$groupLabelMap = [
    'day' => 'Daily',
    'month' => 'Monthly',
    'quarter' => 'Quarterly',
    'year' => 'Yearly',
];
$groupLabel = $groupLabelMap[$group];

$topPeriod = null;
if (count($rows) > 0) {
    $topPeriod = $rows[0];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Revenue Report — Greenwood Zoo</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body { 
            overflow: auto; 
        }
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
        .filter-card form {
            width: 100%;
            margin: 0;
            display: block;
        }
        .filter-card form > div {
            width: auto;
            display: block;
        }
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            align-items: end;
        }

        .filter-group { 
            display: flex; 
            flex-direction: column; 
            gap: 8px; 
        }
        .filter-group label { 
            font-size: 0.85rem; 
            font-weight: 600; 
            color: var(--text-color); 
        }
        .filter-group input, .filter-group select { 
            padding: 8px 12px; 
            border: 2px solid #ddd; 
            border-radius: 8px; 
            font: inherit;
            background: white;
        }
        .filter-group input:focus, .filter-group select:focus { 
            outline: none; 
            border-color: var(--accent-color); 
        }
        .search-btn { 
            padding: 10px 24px; 
            background: var(--accent-color); 
            border: none; 
            border-radius: 8px; 
            font: inherit; 
            font-weight: 600; 
            cursor: pointer; 
            color: white; 
        }
        .reset-btn { 
            padding: 10px 24px; 
            background: #eee; 
            border: none; 
            border-radius: 8px; 
            font: inherit; 
            font-weight: 600; 
            cursor: pointer; 
            color: #555; 
            text-decoration: none; 
        }
        .reset-btn:hover { background: #ddd; }
        .filter-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: end;
        }
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
                <div class="filter-group">
                    <label>Revenue Type</label>
                    <select name="type">
                        <option value="all" <?= $type=='all'?'selected':'' ?>>All</option>
                        <option value="ticket" <?= $type=='ticket'?'selected':'' ?>>Tickets</option>
                        <option value="food" <?= $type=='food'?'selected':'' ?>>Food</option>
                        <option value="shop" <?= $type=='shop'?'selected':'' ?>>Shop</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Group By</label>
                    <select name="group">
                        <option value="day" <?= $group==='day'?'selected':'' ?>>Daily</option>
                        <option value="month" <?= $group==='month'?'selected':'' ?>>Monthly</option>
                        <option value="quarter" <?= $group==='quarter'?'selected':'' ?>>Quarterly</option>
                        <option value="year" <?= $group==='year'?'selected':'' ?>>Yearly</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Sort By</label>
                    <select name="sort">
                        <option value="period" <?= $sort==='period'?'selected':'' ?>>Latest Period</option>
                        <option value="selected" <?= $sort==='selected'?'selected':'' ?>>Highest <?= htmlspecialchars($selectedLabel) ?></option>
                        <option value="total" <?= $sort==='total'?'selected':'' ?>>Highest Total Revenue</option>
                    </select>
                </div>

                <div class="filter-actions">
                    <button type="submit" class="search-btn">Search</button>
                    <a href="revenue_report.php" class="reset-btn">Reset</a>
                </div>
            </div>
        </form>
    </div>

    <!-- Results Table -->
    <p>
        Showing <strong><?= htmlspecialchars($groupLabel) ?></strong> results from
        <strong><?= htmlspecialchars($date_from) ?></strong> to <strong><?= htmlspecialchars($date_to) ?></strong>.
        Type: <strong><?= htmlspecialchars(ucfirst($type)) ?></strong>.
    </p>

    <?php if ($topPeriod): ?>
    <p>
        Top period by current sort:
        <strong><?= htmlspecialchars((string)$topPeriod['PeriodLabel']) ?></strong>
        with <strong>$<?= number_format((float)$topPeriod['SelectedRevenue'], 2) ?></strong> (<?= htmlspecialchars($selectedLabel) ?>).
    </p>
    <?php endif; ?>

    <?php if (count($rows) === 0): ?>
        <p class="no-results">No revenue data found for the selected date range.</p>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Period</th>
                <?php if ($type === 'all'): ?>
                <th>Ticket Revenue</th>
                <th>Food Revenue</th>
                <th>Shop Revenue</th>
                <?php else: ?>
                <th><?= htmlspecialchars($selectedLabel) ?></th>
                <th>Share of Total</th>
                <?php endif; ?>
                <th>Total Revenue</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $row): ?>
            <tr>
                <td><?= htmlspecialchars((string)$row['PeriodLabel']) ?></td>
                <?php if ($type === 'all'): ?>
                <td class="<?= (float)$row['TicketRevenue'] > 0 ? 'positive' : 'zero' ?>">
                    $<?= number_format($row['TicketRevenue'], 2) ?>
                </td>
                <td class="<?= (float)$row['FoodRevenue'] > 0 ? 'positive' : 'zero' ?>">
                    $<?= number_format($row['FoodRevenue'], 2) ?>
                </td>
                <td class="<?= (float)$row['ShopRevenue'] > 0 ? 'positive' : 'zero' ?>">
                    $<?= number_format($row['ShopRevenue'], 2) ?>
                </td>
                <?php else: ?>
                <td class="<?= (float)$row['SelectedRevenue'] > 0 ? 'positive' : 'zero' ?>">
                    $<?= number_format((float)$row['SelectedRevenue'], 2) ?>
                </td>
                <td>
                    <?php
                        $share = ((float)$row['TotalRevenue'] > 0)
                            ? ((float)$row['SelectedRevenue'] / (float)$row['TotalRevenue']) * 100
                            : 0;
                    ?>
                    <?= number_format($share, 1) ?>%
                </td>
                <?php endif; ?>
                <td class="positive">
                    $<?= number_format($row['TotalRevenue'], 2) ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr class="tfoot-row">
                <td>TOTALS</td>
                <?php if ($type === 'all'): ?>
                <td>$<?= number_format($summary['TotalTicket'] ?? 0, 2) ?></td>
                <td>$<?= number_format($summary['TotalFood'] ?? 0, 2) ?></td>
                <td>$<?= number_format($summary['TotalShop'] ?? 0, 2) ?></td>
                <?php else: ?>
                <td>$<?= number_format($summary['SelectedTotal'] ?? 0, 2) ?></td>
                <td>
                    <?=
                        number_format(
                            (($summary['GrandTotal'] ?? 0) > 0)
                                ? (($summary['SelectedTotal'] ?? 0) / $summary['GrandTotal']) * 100
                                : 0,
                            1
                        )
                    ?>%
                </td>
                <?php endif; ?>
                <td>$<?= number_format($summary['GrandTotal'] ?? 0, 2) ?></td>
            </tr>
        </tfoot>
    </table>
    <?php endif; ?>
</div>
</body>
</html>