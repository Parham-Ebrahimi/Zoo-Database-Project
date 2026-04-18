<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.html'); exit; }
if (!in_array(strtolower($_SESSION['role']), ['admin'])) { header('Location: dashboard.php'); exit; }
require 'db.php';

$f_from     = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$f_to       = $_GET['date_to']   ?? date('Y-m-d');
$f_group    = in_array($_GET['group'] ?? '', ['day','week','month','quarter','year']) ? $_GET['group'] : 'day';
$f_category = $_GET['category'] ?? 'all';
$f_payment  = $_GET['payment']  ?? '';
$f_customer = trim($_GET['customer'] ?? '');
$f_amt_min  = $_GET['amt_min']  ?? '';
$f_amt_max  = $_GET['amt_max']  ?? '';

$periodMap = [
    'day'     => ["DATE_FORMAT(o.OrderDate,'%Y-%m-%d')", "DATE_FORMAT(o.OrderDate,'%b %d, %Y')"],
    'week'    => ["YEARWEEK(o.OrderDate,1)",               "CONCAT('Week of ', DATE_FORMAT(MIN(o.OrderDate),'%b %d'))"],
    'month'   => ["DATE_FORMAT(o.OrderDate,'%Y-%m')",     "DATE_FORMAT(o.OrderDate,'%b %Y')"],
    'quarter' => ["CONCAT(YEAR(o.OrderDate),'-Q',QUARTER(o.OrderDate))", "CONCAT('Q',QUARTER(o.OrderDate),' ',YEAR(o.OrderDate))"],
    'year'    => ["YEAR(o.OrderDate)",                     "YEAR(o.OrderDate)"],
];
[$periodKey, $periodLabel] = $periodMap[$f_group];

$catWhere  = '';
$catParams = [];
if ($f_category === 'ticket') { $catWhere = 'AND o.OrderCategoryID BETWEEN 1 AND 5'; }
elseif ($f_category === 'food')   { $catWhere = 'AND o.OrderCategoryID = 6'; }
elseif ($f_category === 'shop')   { $catWhere = 'AND o.OrderCategoryID = 7'; }

$extraWhere  = '';
$extraParams = [];
if ($f_payment)  { $extraWhere .= ' AND o.PaymentMode = ?'; $extraParams[] = $f_payment; }
if ($f_customer) {
    $extraWhere .= ' AND (c.FirstName LIKE ? OR c.LastName LIKE ? OR c.Email LIKE ?)';
    $extraParams[] = "%$f_customer%"; $extraParams[] = "%$f_customer%"; $extraParams[] = "%$f_customer%";
}
if ($f_amt_min !== '') { $extraWhere .= ' AND o.TransactionAmount >= ?'; $extraParams[] = (float)$f_amt_min; }
if ($f_amt_max !== '') { $extraWhere .= ' AND o.TransactionAmount <= ?'; $extraParams[] = (float)$f_amt_max; }

$baseParams = [$f_from, $f_to, ...$extraParams];

$sumStmt = $pdo->prepare("
    SELECT
        SUM(CASE WHEN o.OrderCategoryID BETWEEN 1 AND 5 THEN o.TransactionAmount ELSE 0 END) AS TicketRev,
        SUM(CASE WHEN o.OrderCategoryID = 6             THEN o.TransactionAmount ELSE 0 END) AS FoodRev,
        SUM(CASE WHEN o.OrderCategoryID = 7             THEN o.TransactionAmount ELSE 0 END) AS ShopRev,
        SUM(o.TransactionAmount) AS TotalRev,
        COUNT(DISTINCT o.OrderID) AS TotalOrders,
        COUNT(DISTINCT o.CustomerID) AS UniqueCustomers,
        SUM(COALESCE(ot.Quantity,1)) AS TotalTickets
    FROM orders o
    LEFT JOIN order_tickets ot ON ot.OrderID = o.OrderID AND o.OrderCategoryID BETWEEN 1 AND 5
    LEFT JOIN customers c ON o.CustomerID = c.CustomerID
    WHERE o.OrderDate BETWEEN ? AND ? $extraWhere
");
$sumStmt->execute($baseParams);
$summary = $sumStmt->fetch(PDO::FETCH_ASSOC);

$periodStmt = $pdo->prepare("
    SELECT
        $periodKey AS PK,
        $periodLabel AS PLabel,
        MIN(o.OrderDate) AS PeriodStart,
        SUM(CASE WHEN o.OrderCategoryID BETWEEN 1 AND 5 THEN o.TransactionAmount ELSE 0 END) AS TicketRev,
        SUM(CASE WHEN o.OrderCategoryID = 6             THEN o.TransactionAmount ELSE 0 END) AS FoodRev,
        SUM(CASE WHEN o.OrderCategoryID = 7             THEN o.TransactionAmount ELSE 0 END) AS ShopRev,
        SUM(o.TransactionAmount) AS TotalRev,
        COUNT(DISTINCT o.OrderID) AS Orders
    FROM orders o
    LEFT JOIN customers c ON o.CustomerID = c.CustomerID
    WHERE o.OrderDate BETWEEN ? AND ? $catWhere $extraWhere
    GROUP BY PK, PLabel
    ORDER BY PeriodStart ASC
");
$periodStmt->execute([$f_from, $f_to, ...$extraParams]);
$periods = $periodStmt->fetchAll(PDO::FETCH_ASSOC);

$topCustomers = $pdo->prepare("
    SELECT CONCAT(c.FirstName,' ',c.LastName) AS Name, c.Email,
           COUNT(DISTINCT o.OrderID) AS Orders,
           SUM(o.TransactionAmount) AS Spent
    FROM orders o
    JOIN customers c ON o.CustomerID = c.CustomerID
    WHERE o.OrderDate BETWEEN ? AND ? $catWhere $extraWhere
    GROUP BY o.CustomerID, c.FirstName, c.LastName, c.Email
    ORDER BY Spent DESC
    LIMIT 10
");
$topCustomers->execute([$f_from, $f_to, ...$extraParams]);
$topCustRows = $topCustomers->fetchAll(PDO::FETCH_ASSOC);


$rawTickets = $pdo->prepare("
    SELECT o.OrderID, o.OrderDate, o.ScheduledDate,
           CONCAT(c.FirstName,' ',c.LastName) AS Customer, c.Email,
           oc.CategoryName AS TicketType, ot.Quantity,
           oc.Price AS UnitPrice, o.TransactionAmount, o.PaymentMode
    FROM orders o
    JOIN ordercategories oc ON o.OrderCategoryID = oc.OrderCategoryID
    JOIN order_tickets ot   ON ot.OrderID = o.OrderID
    JOIN customers c        ON o.CustomerID = c.CustomerID
    WHERE o.OrderDate BETWEEN ? AND ? AND o.OrderCategoryID BETWEEN 1 AND 5 $catWhere $extraWhere
    ORDER BY o.OrderDate DESC
    LIMIT 200
");
$rawTickets->execute($baseParams);
$rawTicketRows = $rawTickets->fetchAll(PDO::FETCH_ASSOC);

$rawFoodParams = [$f_from, $f_to, ...$extraParams];
$rawFoodExtra  = $extraWhere;
$rawFood = $pdo->prepare("
    SELECT o.OrderID, o.OrderDate,
           CONCAT(c.FirstName,' ',c.LastName) AS Customer, c.Email,
           fi.FoodName, ofi.Quantity, fi.Price AS UnitPrice,
           (fi.Price * ofi.Quantity) AS LineTotal,
           o.PaymentMode, fs.Name AS Stall
    FROM orders o
    JOIN order_food_items ofi ON ofi.OrderID = o.OrderID
    JOIN fooditem fi           ON fi.FoodID   = ofi.FoodID
    JOIN foodstall fs          ON fs.StallID  = fi.StallID
    JOIN customers c           ON o.CustomerID = c.CustomerID
    WHERE o.OrderDate BETWEEN ? AND ? $catWhere $rawFoodExtra
    ORDER BY o.OrderDate DESC
    LIMIT 200
");
$rawFood->execute($rawFoodParams);
$rawFoodRows = $rawFood->fetchAll(PDO::FETCH_ASSOC);

$rawShopParams = [$f_from, $f_to, ...$extraParams];
$rawShop = $pdo->prepare("
    SELECT o.OrderID, o.OrderDate,
           CONCAT(c.FirstName,' ',c.LastName) AS Customer, c.Email,
           si.ItemName, osi.Quantity, si.Price AS UnitPrice,
           (si.Price * osi.Quantity) AS LineTotal,
           o.PaymentMode, s.ShopName
    FROM orders o
    JOIN order_shop_items osi ON osi.OrderID   = o.OrderID
    JOIN shop_items si         ON si.ShopItemID = osi.ShopItemID
    JOIN shops s               ON s.ShopID      = si.ShopID
    JOIN customers c           ON o.CustomerID  = c.CustomerID
    WHERE o.OrderDate BETWEEN ? AND ? $catWhere $rawFoodExtra
    ORDER BY o.OrderDate DESC
    LIMIT 200
");
$rawShop->execute($rawShopParams);
$rawShopRows = $rawShop->fetchAll(PDO::FETCH_ASSOC);

$rawPayCatWhere = $catWhere;
$rawPay = $pdo->prepare("
    SELECT o.OrderID, o.OrderDate,
           CONCAT(c.FirstName,' ',c.LastName) AS Customer, c.Email,
           oc.CategoryName AS Category,
           o.PaymentMode, o.TransactionAmount
    FROM orders o
    JOIN ordercategories oc ON o.OrderCategoryID = oc.OrderCategoryID
    JOIN customers c        ON o.CustomerID = c.CustomerID
    WHERE o.OrderDate BETWEEN ? AND ? $rawPayCatWhere $rawFoodExtra
    ORDER BY o.OrderDate DESC
    LIMIT 200
");
$rawPay->execute([$f_from, $f_to, ...$extraParams]);
$rawPayRows = $rawPay->fetchAll(PDO::FETCH_ASSOC);

$chartLabels  = array_column($periods, 'PLabel');
$chartTicket  = array_column($periods, 'TicketRev');
$chartFood    = array_column($periods, 'FoodRev');
$chartShop    = array_column($periods, 'ShopRev');
$chartTotal   = array_column($periods, 'TotalRev');

$totalRev = (float)($summary['TotalRev'] ?? 0);
$ticketRev = (float)($summary['TicketRev'] ?? 0);
$foodRev   = (float)($summary['FoodRev']   ?? 0);
$shopRev   = (float)($summary['ShopRev']   ?? 0);

$hasFilters = array_filter([$f_payment, $f_customer, $f_amt_min, $f_amt_max])
           || $f_category !== 'all'
           || $f_group !== 'day'
           || $f_from !== date('Y-m-d', strtotime('-30 days'))
           || $f_to   !== date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Revenue & Sales Report</title>
<link rel="stylesheet" href="style.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<style>
body { overflow: auto; }
.dashboard-wrapper {
    box-sizing: border-box;
    min-height: 100vh;
    padding: 20px clamp(12px, 2.4vw, 18px);
    background-color: rgba(187, 223, 158, 0.95);
}
.dashboard-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
    border-bottom: 3px solid var(--accent-color);
    padding-bottom: 12px;
    flex-wrap: wrap;
    gap: 12px;
}
.dashboard-header h1 { font-size: 1.5rem; margin: 0; font-weight: 800; color: var(--text-color); }
.header-actions { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
.back-btn {
    display: inline-block;
    margin-bottom: 14px;
    padding: 7px 14px;
    background-color: var(--base-color);
    border-radius: 8px;
    color: var(--text-color);
    font-weight: 600;
    text-decoration: none;
    font-size: 0.88rem;
}
.back-btn:hover { background-color: var(--accent-color); }
.logout-btn {
    padding: 10px 22px;
    background-color: var(--accent-color);
    border: none;
    border-radius: 1000px;
    font: inherit;
    font-weight: 600;
    cursor: pointer;
    color: var(--text-color);
    text-decoration: none;
}
.logout-btn:hover { background-color: var(--text-color); color: white; }

.filter-card {
    background: white;
    border-radius: 12px;
    padding: 12px 14px;
    margin-bottom: 14px;
    box-shadow: 0 3px 8px rgba(0,0,0,0.05);
}
.filter-card h2 {
    font-size: 0.95rem;
    font-weight: 700;
    margin-bottom: 10px;
    color: var(--text-color);
}
.filter-card label {
    background: none;
    color: var(--text-color);
    font-size: 0.85rem;
    font-weight: 600;
    height: auto;
    width: auto;
    border-radius: 0;
    display: block;
    text-align: left;
    padding: 0;
}
.filter-card form { width: 100%; margin: 0; display: block; }
.filter-card form > div { width: auto; display: block; }
.filter-group {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.filter-group label { font-size: 0.85rem; font-weight: 600; color: var(--text-color); }
.filter-group input,
.filter-group select {
    padding: 6px 10px;
    border: 2px solid #ddd;
    border-radius: 8px;
    font: inherit;
    background: white;
}
.filter-group input:focus,
.filter-group select:focus {
    outline: none;
    border-color: var(--accent-color);
}
.filter-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: flex-end;
}
.btn {
    padding: 6px 14px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.85rem;
    border: none;
    cursor: pointer;
    font: inherit;
    display: inline-block;
}
.btn-edit {
    background-color: var(--accent-color);
    color: white;
}
.btn-edit:hover { background-color: var(--text-color); }
.filter-actions .btn:not(.btn-edit) {
    background: #eee;
    color: #555;
}
.filter-actions .btn:not(.btn-edit):hover { background: #ddd; }

/* KPI cards */
.kpi-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(155px,1fr));gap:12px;margin-bottom:20px}
.kpi{background:white;border-radius:14px;padding:16px 18px;box-shadow:0 2px 10px rgba(0,0,0,.07);border-left:4px solid var(--accent-color)}
.kpi.ticket{border-color:#2980b9}
.kpi.food{border-color:#e67e22}
.kpi.shop{border-color:#8e44ad}
.kpi .k-label{font-size:.72rem;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:#888;margin-bottom:6px}
.kpi .k-val{font-size:1.6rem;font-weight:900;color:var(--text-color);line-height:1}
.kpi .k-sub{font-size:.75rem;color:#aaa;margin-top:4px}
.kpi .k-bar{height:4px;border-radius:2px;background:#eee;margin-top:10px;overflow:hidden}
.kpi .k-fill{height:100%;border-radius:2px;background:var(--accent-color)}
.kpi.ticket .k-fill{background:#2980b9}
.kpi.food   .k-fill{background:#e67e22}
.kpi.shop   .k-fill{background:#8e44ad}

.tab-nav{display:flex;gap:4px;margin-bottom:16px;flex-wrap:wrap}
.tab-btn{padding:8px 18px;border:2px solid #ddd;border-radius:8px 8px 0 0;background:white;font:inherit;font-size:.85rem;font-weight:600;cursor:pointer;color:#888;border-bottom:none;transition:all .15s}
.tab-btn.active{border-color:var(--accent-color);color:var(--text-color);background:white;border-bottom:2px solid white;margin-bottom:-2px;z-index:1}
.tab-btn:hover:not(.active){background:#f5f5f5;color:var(--text-color)}
.tab-content{display:none}.tab-content.active{display:block}

.chart-grid{display:grid;grid-template-columns:2fr 1fr;gap:16px;margin-bottom:18px}
@media(max-width:900px){.chart-grid{grid-template-columns:1fr}}
.chart-card{background:white;border-radius:14px;padding:18px 20px;box-shadow:0 2px 10px rgba(0,0,0,.07)}
.chart-card h3{font-size:.82rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#888;margin:0 0 14px}
.chart-wrap{position:relative;height:260px}
.chart-wrap-sm{position:relative;height:220px}

.tw{background:white;border-radius:14px;overflow:hidden;box-shadow:0 4px 14px rgba(0,0,0,.08);overflow-x:auto;margin-bottom:18px}
table{width:100%;border-collapse:collapse;min-width:500px}
th{background:var(--accent-color);color:white;padding:10px 13px;text-align:left;font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;white-space:nowrap}
td{padding:9px 13px;border-bottom:1px solid #f0f0f0;font-size:.86rem;vertical-align:middle}
tr:last-child td{border-bottom:none}
tbody tr:hover td{background:rgba(187,223,158,.15)}
tfoot td{background:var(--base-color);font-weight:700;padding:10px 13px;border-top:2px solid var(--accent-color)}
.amt{font-weight:700;color:#27ae60}
.bar-cell{display:flex;align-items:center;gap:8px;min-width:120px}
.bar-outer{flex:1;background:#eee;border-radius:3px;height:8px;overflow:hidden}
.bar-inner{height:100%;border-radius:3px}
.bar-ticket{background:#2980b9}.bar-food{background:#e67e22}.bar-shop{background:#8e44ad}.bar-total{background:var(--accent-color)}
.bdg{display:inline-block;padding:2px 9px;border-radius:999px;font-size:.72rem;font-weight:700}
.bdg-t{background:#d4edda;color:#155724}
.bdg-f{background:#fde8d0;color:#7d3c00}
.bdg-s{background:#e8d5f0;color:#4a235a}
.bdg-p{background:#e8f4fd;color:#1a5276}
.rc{font-size:.83rem;color:#666;margin-bottom:10px;font-weight:600}
.two-col{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:18px}
@media(max-width:860px){.two-col{grid-template-columns:1fr}}
.section-hdr{font-size:.8rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#888;margin:0 0 10px;padding:0 0 6px;border-bottom:2px solid var(--accent-color);display:block}
.no-data{padding:30px;text-align:center;color:#aaa;font-style:italic;font-size:.88rem}
.pct-cell{color:#888;font-size:.82rem}
.up{color:#27ae60;font-weight:700}
.down{color:#e74c3c;font-weight:700}


.raw-data-intro { font-size: .82rem; color: #555; margin: 0 0 12px; line-height: 1.45; font-weight: 600; }
.tw-raw { background:white; border-radius:10px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,.07); overflow-x:auto; margin-bottom: 18px; }
.tw-raw table { min-width: 600px; }
.tw-raw th { font-size:.72rem; padding:8px 11px; }
.tw-raw td { font-size:.78rem; padding:7px 11px; }
.order-id { color:#888; font-size:.73rem; }
.upcoming { color:#27ae60; font-weight:600; font-size:.78rem; }
.past-date { color:#aaa; font-size:.78rem; }

</style>
</head>
<body>
<div class="dashboard-wrapper">

<div class="dashboard-header">
    <div>
        <h1>Revenue &amp; sales report</h1>
        <p style="margin:4px 0 0;font-size:.85rem;color:#666;font-weight:500">
            <?= htmlspecialchars($f_from) ?> → <?= htmlspecialchars($f_to) ?>
            · <?= htmlspecialchars($_SESSION['firstname'] ?? '') ?>
        </p>
    </div>
    <div class="header-actions">
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>
</div>

<a href="dashboard.php" class="back-btn" style="margin-bottom:12px">← Back to dashboard</a>

<!-- KPI Cards -->
<div class="kpi-grid">
    <div class="kpi">
        <div class="k-label">Total Revenue</div>
        <div class="k-val">$<?= number_format($totalRev, 2) ?></div>
        <div class="k-sub"><?= $summary['TotalOrders'] ?> orders · <?= $summary['UniqueCustomers'] ?> customers</div>
        <div class="k-bar"><div class="k-fill" style="width:100%"></div></div>
    </div>
    <div class="kpi ticket">
        <div class="k-label">🎟 Ticket Revenue</div>
        <div class="k-val">$<?= number_format($ticketRev, 2) ?></div>
        <div class="k-sub"><?= $summary['TotalTickets'] ?? 0 ?> tickets sold</div>
        <div class="k-bar"><div class="k-fill" style="width:<?= $totalRev > 0 ? round($ticketRev/$totalRev*100) : 0 ?>%"></div></div>
    </div>
    <div class="kpi food">
        <div class="k-label">🍽 Food Revenue</div>
        <div class="k-val">$<?= number_format($foodRev, 2) ?></div>
        <div class="k-sub"><?= $totalRev > 0 ? round($foodRev/$totalRev*100, 1) : 0 ?>% of total</div>
        <div class="k-bar"><div class="k-fill" style="width:<?= $totalRev > 0 ? round($foodRev/$totalRev*100) : 0 ?>%"></div></div>
    </div>
    <div class="kpi shop">
        <div class="k-label">🛍 Shop Revenue</div>
        <div class="k-val">$<?= number_format($shopRev, 2) ?></div>
        <div class="k-sub"><?= $totalRev > 0 ? round($shopRev/$totalRev*100, 1) : 0 ?>% of total</div>
        <div class="k-bar"><div class="k-fill" style="width:<?= $totalRev > 0 ? round($shopRev/$totalRev*100) : 0 ?>%"></div></div>
    </div>
    <div class="kpi">
        <div class="k-label">Avg Order Value</div>
        <div class="k-val">$<?= $summary['TotalOrders'] > 0 ? number_format($totalRev / $summary['TotalOrders'], 2) : '0.00' ?></div>
        <div class="k-sub">per transaction</div>
        <div class="k-bar"><div class="k-fill" style="width:60%"></div></div>
    </div>
</div>

<div class="filter-card">
    <h2>Filter revenue</h2>
    <form method="GET">
        <div class="filter-grid">
            <div class="filter-group">
                <label for="rev_date_from">Date from</label>
                <input id="rev_date_from" type="date" name="date_from" value="<?= htmlspecialchars($f_from) ?>">
            </div>
            <div class="filter-group">
                <label for="rev_date_to">Date to</label>
                <input id="rev_date_to" type="date" name="date_to" value="<?= htmlspecialchars($f_to) ?>">
            </div>
            <div class="filter-group">
                <label for="rev_group">Group by</label>
                <select id="rev_group" name="group">
                    <?php foreach (['day' => 'Daily', 'week' => 'Weekly', 'month' => 'Monthly', 'quarter' => 'Quarterly', 'year' => 'Yearly'] as $v => $l): ?>
                    <option value="<?= $v ?>" <?= $f_group === $v ? 'selected' : '' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label for="rev_category">Category</label>
                <select id="rev_category" name="category">
                    <option value="all" <?= $f_category === 'all' ? 'selected' : '' ?>>All categories</option>
                    <option value="ticket" <?= $f_category === 'ticket' ? 'selected' : '' ?>>Tickets only</option>
                    <option value="food" <?= $f_category === 'food' ? 'selected' : '' ?>>Food only</option>
                    <option value="shop" <?= $f_category === 'shop' ? 'selected' : '' ?>>Shop only</option>
                </select>
            </div>
            <div class="filter-group">
                <label for="rev_payment">Payment method</label>
                <select id="rev_payment" name="payment">
                    <option value="">All methods</option>
                    <?php foreach (['Credit Card', 'Debit Card', 'Cash', 'PayPal'] as $pm): ?>
                    <option value="<?= htmlspecialchars($pm) ?>" <?= $f_payment === $pm ? 'selected' : '' ?>><?= htmlspecialchars($pm) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group filter-group--wide">
                <label for="rev_customer">Customer name or email</label>
                <input id="rev_customer" type="text" name="customer" value="<?= htmlspecialchars($f_customer) ?>" placeholder="e.g. Jane or @email">
            </div>
            <div class="filter-group">
                <label for="rev_amt_min">Min amount ($)</label>
                <input id="rev_amt_min" type="number" step="0.01" name="amt_min" value="<?= htmlspecialchars($f_amt_min) ?>" placeholder="0.00">
            </div>
            <div class="filter-group">
                <label for="rev_amt_max">Max amount ($)</label>
                <input id="rev_amt_max" type="number" step="0.01" name="amt_max" value="<?= htmlspecialchars($f_amt_max) ?>" placeholder="999">
            </div>
            <div class="filter-actions">
                <button type="submit" class="btn btn-edit">Search</button>
                <a href="revenue_report.php" class="btn">Reset</a>
            </div>
        </div>
    </form>
</div>

<div class="tab-nav">
    <button type="button" class="tab-btn active" onclick="showTab('overview', this)">Overview</button>
    <button type="button" class="tab-btn" onclick="showTab('tickets', this)"> Tickets</button>
    <button type="button" class="tab-btn" onclick="showTab('food', this)"> Food</button>
    <button type="button" class="tab-btn" onclick="showTab('shop', this)">Shop</button>
    <button type="button" class="tab-btn" onclick="showTab('payments', this)">Payments</button>
    <button type="button" class="tab-btn" onclick="showTab('customers', this)"> Top Customers</button>
    <button type="button" class="tab-btn" onclick="showTab('table', this)"> Period Table</button>
</div>

<div id="tab-overview" class="tab-content active">
    <div class="chart-grid">
        <div class="chart-card">
            <h3>Revenue over time</h3>
            <div class="chart-wrap"><canvas id="lineChart"></canvas></div>
        </div>
        <div class="chart-card">
            <h3>Revenue mix</h3>
            <div class="chart-wrap-sm"><canvas id="donutChart"></canvas></div>
        </div>
    </div>
    <div class="chart-card" style="margin-bottom:18px">
        <h3>Revenue by category per period</h3>
        <div style="height:240px"><canvas id="barChart"></canvas></div>
    </div>
</div>

<div id="tab-tickets" class="tab-content">
    <?php if (empty($rawTicketRows)): ?>
        <div class="tw"><p class="no-data">No ticket orders for this period.</p></div>
    <?php else: ?>
    <div class="tw-raw"><table>
        <thead><tr>
            <th>Order #</th><th>Customer</th><th>Ticket type</th>
            <th>Qty</th><th>Unit price</th><th>Total</th>
            <th>Payment</th><th>Purchase date</th><th>Visit date</th>
        </tr></thead>
        <tbody>
        <?php foreach ($rawTicketRows as $r):
            $isUp = !empty($r['ScheduledDate']) && $r['ScheduledDate'] >= date('Y-m-d');
        ?>
        <tr>
            <td class="order-id">#<?= (int) $r['OrderID'] ?></td>
            <td>
                <strong><?= htmlspecialchars((string) ($r['Customer'] ?? '')) ?></strong>
                <div style="font-size:.7rem;color:#aaa"><?= htmlspecialchars((string) ($r['Email'] ?? '')) ?></div>
            </td>
            <td><span class="bdg bdg-t"><?= htmlspecialchars((string) ($r['TicketType'] ?? '')) ?></span></td>
            <td style="font-weight:700"><?= (int) ($r['Quantity'] ?? 0) ?></td>
            <td>$<?= number_format((float) ($r['UnitPrice'] ?? 0), 2) ?></td>
            <td class="amt">$<?= number_format((float) ($r['TransactionAmount'] ?? 0), 2) ?></td>
            <td><span class="bdg bdg-p"><?= htmlspecialchars((string) ($r['PaymentMode'] ?? '')) ?></span></td>
            <td><?= $r['OrderDate'] ? date('M j, Y', strtotime((string) $r['OrderDate'])) : '—' ?></td>
            <td>
                <?php if (!empty($r['ScheduledDate'])): ?>
                    <span class="<?= $isUp ? 'upcoming' : 'past-date' ?>">
                        <?= date('M j, Y', strtotime((string) $r['ScheduledDate'])) ?><?= $isUp ? ' ✓' : '' ?>
                    </span>
                <?php else: ?><span style="color:#ccc">—</span><?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr>
            <td colspan="3">TOTALS (<?= count($rawTicketRows) ?> rows)</td>
            <td><?= array_sum(array_column($rawTicketRows, 'Quantity')) ?></td>
            <td>—</td>
            <td class="amt">$<?= number_format(array_sum(array_map('floatval', array_column($rawTicketRows, 'TransactionAmount'))), 2) ?></td>
            <td colspan="3"></td>
        </tr></tfoot>
    </table></div>
    <?php endif; ?>
</div>


<div id="tab-food" class="tab-content">    
    <?php if (empty($rawFoodRows)): ?>
        <div class="tw"><p class="no-data">No food order lines for this period.</p></div>
    <?php else: ?>
    <div class="tw-raw"><table>
        <thead><tr>
            <th>Order #</th><th>Customer</th><th>Item</th>
            <th>Stall</th><th>Qty</th><th>Unit price</th>
            <th>Line total</th><th>Payment</th><th>Date</th>
        </tr></thead>
        <tbody>
        <?php foreach ($rawFoodRows as $r): ?>
        <tr>
            <td class="order-id">#<?= (int) $r['OrderID'] ?></td>
            <td>
                <strong><?= htmlspecialchars((string) ($r['Customer'] ?? '')) ?></strong>
                <div style="font-size:.7rem;color:#aaa"><?= htmlspecialchars((string) ($r['Email'] ?? '')) ?></div>
            </td>
            <td><span class="bdg bdg-f"><?= htmlspecialchars((string) ($r['FoodName'] ?? '')) ?></span></td>
            <td style="font-size:.78rem;color:#888"><?= htmlspecialchars((string) ($r['Stall'] ?? '')) ?></td>
            <td style="font-weight:700"><?= (int) ($r['Quantity'] ?? 0) ?></td>
            <td>$<?= number_format((float) ($r['UnitPrice'] ?? 0), 2) ?></td>
            <td class="amt">$<?= number_format((float) ($r['LineTotal'] ?? 0), 2) ?></td>
            <td><span class="bdg bdg-p"><?= htmlspecialchars((string) ($r['PaymentMode'] ?? '')) ?></span></td>
            <td><?= $r['OrderDate'] ? date('M j, Y', strtotime((string) $r['OrderDate'])) : '—' ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr>
            <td colspan="4">TOTALS (<?= count($rawFoodRows) ?> rows)</td>
            <td><?= array_sum(array_column($rawFoodRows, 'Quantity')) ?></td>
            <td>—</td>
            <td class="amt">$<?= number_format(array_sum(array_map('floatval', array_column($rawFoodRows, 'LineTotal'))), 2) ?></td>
            <td colspan="2"></td>
        </tr></tfoot>
    </table></div>
    <?php endif; ?>
</div>


<div id="tab-shop" class="tab-content">
    <?php if (empty($rawShopRows)): ?>
        <div class="tw"><p class="no-data">No shop order lines for this period.</p></div>
    <?php else: ?>
    <div class="tw-raw"><table>
        <thead><tr>
            <th>Order #</th><th>Customer</th><th>Item</th>
            <th>Shop</th><th>Qty</th><th>Unit price</th>
            <th>Line total</th><th>Payment</th><th>Date</th>
        </tr></thead>
        <tbody>
        <?php foreach ($rawShopRows as $r): ?>
        <tr>
            <td class="order-id">#<?= (int) $r['OrderID'] ?></td>
            <td>
                <strong><?= htmlspecialchars((string) ($r['Customer'] ?? '')) ?></strong>
                <div style="font-size:.7rem;color:#aaa"><?= htmlspecialchars((string) ($r['Email'] ?? '')) ?></div>
            </td>
            <td><span class="bdg bdg-s"><?= htmlspecialchars((string) ($r['ItemName'] ?? '')) ?></span></td>
            <td style="font-size:.78rem;color:#888"><?= htmlspecialchars((string) ($r['ShopName'] ?? '')) ?></td>
            <td style="font-weight:700"><?= (int) ($r['Quantity'] ?? 0) ?></td>
            <td>$<?= number_format((float) ($r['UnitPrice'] ?? 0), 2) ?></td>
            <td class="amt">$<?= number_format((float) ($r['LineTotal'] ?? 0), 2) ?></td>
            <td><span class="bdg bdg-p"><?= htmlspecialchars((string) ($r['PaymentMode'] ?? '')) ?></span></td>
            <td><?= $r['OrderDate'] ? date('M j, Y', strtotime((string) $r['OrderDate'])) : '—' ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr>
            <td colspan="4">TOTALS (<?= count($rawShopRows) ?> rows)</td>
            <td><?= array_sum(array_column($rawShopRows, 'Quantity')) ?></td>
            <td>—</td>
            <td class="amt">$<?= number_format(array_sum(array_map('floatval', array_column($rawShopRows, 'LineTotal'))), 2) ?></td>
            <td colspan="2"></td>
        </tr></tfoot>
    </table></div>
    <?php endif; ?>
</div>

<div id="tab-payments" class="tab-content">
    <?php if (empty($rawPayRows)): ?>
        <div class="tw"><p class="no-data">No transactions for this period.</p></div>
    <?php else: ?>
    <div class="tw-raw"><table>
        <thead><tr>
            <th>Order #</th><th>Customer</th><th>Category</th>
            <th>Payment method</th><th>Amount</th><th>Date</th>
        </tr></thead>
        <tbody>
        <?php foreach ($rawPayRows as $r):
            $cat = (string) ($r['Category'] ?? '');
            $catClass = in_array($cat, ['Adult Ticket', 'Child Ticket', 'Senior Ticket', 'Student Ticket', 'Annual Membership'], true) ? 'bdg-t' : (str_contains($cat, 'Food') || $cat === 'Food' ? 'bdg-f' : 'bdg-s');
        ?>
        <tr>
            <td class="order-id">#<?= (int) $r['OrderID'] ?></td>
            <td>
                <strong><?= htmlspecialchars((string) ($r['Customer'] ?? '')) ?></strong>
                <div style="font-size:.7rem;color:#aaa"><?= htmlspecialchars((string) ($r['Email'] ?? '')) ?></div>
            </td>
            <td><span class="bdg <?= $catClass ?>"><?= htmlspecialchars($cat) ?></span></td>
            <td><span class="bdg bdg-p"><?= htmlspecialchars((string) ($r['PaymentMode'] ?? '')) ?></span></td>
            <td class="amt">$<?= number_format((float) ($r['TransactionAmount'] ?? 0), 2) ?></td>
            <td><?= $r['OrderDate'] ? date('M j, Y', strtotime((string) $r['OrderDate'])) : '—' ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr>
            <td colspan="4">TOTALS (<?= count($rawPayRows) ?> rows)</td>
            <td class="amt">$<?= number_format(array_sum(array_map('floatval', array_column($rawPayRows, 'TransactionAmount'))), 2) ?></td>
            <td></td>
        </tr></tfoot>
    </table></div>
    <?php endif; ?>
</div>

<div id="tab-customers" class="tab-content">
    <span class="section-hdr">Top 10 customers by spend</span>
    <?php if (empty($topCustRows)): ?>
        <div class="tw"><p class="no-data">No customer data for this period.</p></div>
    <?php else: ?>
    <div class="tw"><table>
        <thead><tr><th>#</th><th>Customer</th><th>Orders</th><th>Total spent</th><th>Avg per order</th><th>Bar</th></tr></thead>
        <tbody>
        <?php
        $maxC = max(array_column($topCustRows,'Spent') ?: [1]);
        foreach ($topCustRows as $i => $r):
        ?>
        <tr>
            <td style="color:#aaa;font-weight:700"><?= $i+1 ?></td>
            <td>
                <strong><?= htmlspecialchars($r['Name']) ?></strong>
                <div style="font-size:.73rem;color:#888"><?= htmlspecialchars($r['Email']) ?></div>
            </td>
            <td><?= $r['Orders'] ?></td>
            <td class="amt">$<?= number_format($r['Spent'],2) ?></td>
            <td style="color:#888">$<?= number_format($r['Spent']/$r['Orders'],2) ?></td>
            <td><div class="bar-cell"><div class="bar-outer"><div class="bar-inner bar-total" style="width:<?= round($r['Spent']/$maxC*100) ?>%"></div></div></div></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</div>

<div id="tab-table" class="tab-content">
    <span class="section-hdr">Period-by-period breakdown</span>
    <p class="rc">Showing <?= count($periods) ?> period<?= count($periods)!==1?'s':'' ?> <?= $hasFilters?'<span style="color:var(--accent-color)">(filtered)</span>':'' ?></p>
    <?php if (empty($periods)): ?>
        <div class="tw"><p class="no-data">No data for the selected range.</p></div>
    <?php else: ?>
    <div class="tw"><table>
        <thead>
            <tr>
                <th>Period</th>
                <th>Tickets</th>
                <th>Food</th>
                <th>Shop</th>
                <th>Orders</th>
                <th>Total</th>
                <th>Bar</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $maxP = max(array_column($periods,'TotalRev') ?: [1]);
        foreach ($periods as $r):
        ?>
        <tr>
            <td style="font-weight:600"><?= htmlspecialchars((string)$r['PLabel']) ?></td>
            <td class="<?= $r['TicketRev']>0?'amt':'' ?>">$<?= number_format($r['TicketRev'],2) ?></td>
            <td class="<?= $r['FoodRev']>0?'amt':'' ?>">$<?= number_format($r['FoodRev'],2) ?></td>
            <td class="<?= $r['ShopRev']>0?'amt':'' ?>">$<?= number_format($r['ShopRev'],2) ?></td>
            <td><?= $r['Orders'] ?></td>
            <td class="amt">$<?= number_format($r['TotalRev'],2) ?></td>
            <td><div class="bar-cell"><div class="bar-outer"><div class="bar-inner bar-total" style="width:<?= $maxP>0?round($r['TotalRev']/$maxP*100):0 ?>%"></div></div></div></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td>TOTALS</td>
                <td>$<?= number_format($ticketRev,2) ?></td>
                <td>$<?= number_format($foodRev,2) ?></td>
                <td>$<?= number_format($shopRev,2) ?></td>
                <td><?= $summary['TotalOrders'] ?></td>
                <td>$<?= number_format($totalRev,2) ?></td>
                <td></td>
            </tr>
        </tfoot>
    </table></div>
    <?php endif; ?>
</div>

</div>

<script>
function showTab(name, btn) {
    document.querySelectorAll('.tab-content').forEach(function (el) { el.classList.remove('active'); });
    document.querySelectorAll('.tab-btn').forEach(function (el) { el.classList.remove('active'); });
    var pane = document.getElementById('tab-' + name);
    if (pane) pane.classList.add('active');
    if (btn) btn.classList.add('active');
}

const labels  = <?= json_encode($chartLabels) ?>;
const ticket  = <?= json_encode(array_map('floatval', $chartTicket)) ?>;
const food    = <?= json_encode(array_map('floatval', $chartFood)) ?>;
const shop    = <?= json_encode(array_map('floatval', $chartShop)) ?>;
const total   = <?= json_encode(array_map('floatval', $chartTotal)) ?>;

const green  = '#27ae60';
const blue   = '#2980b9';
const orange = '#e67e22';
const purple = '#8e44ad';
const accent = '#6ac473';

const defaults = { responsive:true, maintainAspectRatio:false,
    plugins:{ legend:{ labels:{ font:{ family:'Montserrat,sans-serif', size:11 } } } },
    scales:{ x:{ ticks:{ font:{ size:10 } } }, y:{ ticks:{ font:{ size:10 } } } }
};

new Chart(document.getElementById('lineChart'), {
    type: 'line',
    data: {
        labels,
        datasets: [
            { label:'Total',   data:total,  borderColor:green,  backgroundColor:green+'22',  fill:true,  tension:.3, borderWidth:2, pointRadius:3 },
            { label:'Tickets', data:ticket, borderColor:blue,   backgroundColor:'transparent', tension:.3, borderWidth:1.5, pointRadius:2 },
            { label:'Food',    data:food,   borderColor:orange, backgroundColor:'transparent', tension:.3, borderWidth:1.5, pointRadius:2 },
            { label:'Shop',    data:shop,   borderColor:purple, backgroundColor:'transparent', tension:.3, borderWidth:1.5, pointRadius:2 },
        ]
    },
    options: { ...defaults, interaction:{ mode:'index', intersect:false },
        plugins:{ ...defaults.plugins, tooltip:{ callbacks:{ label: ctx => ' $' + ctx.parsed.y.toFixed(2) } } } }
});

new Chart(document.getElementById('donutChart'), {
    type: 'doughnut',
    data: {
        labels: ['Tickets','Food','Shop'],
        datasets:[{ data:[<?= $ticketRev ?>,<?= $foodRev ?>,<?= $shopRev ?>],
            backgroundColor:[blue,orange,purple], borderWidth:2, borderColor:'#fff' }]
    },
    options: { responsive:true, maintainAspectRatio:false, cutout:'62%',
        plugins:{ legend:{ position:'bottom', labels:{ font:{ size:11 } } },
            tooltip:{ callbacks:{ label: ctx => ' $' + ctx.parsed.toFixed(2) } } } }
});

new Chart(document.getElementById('barChart'), {
    type: 'bar',
    data: {
        labels,
        datasets: [
            { label:'Tickets', data:ticket, backgroundColor:blue+'cc',  stack:'s' },
            { label:'Food',    data:food,   backgroundColor:orange+'cc', stack:'s' },
            { label:'Shop',    data:shop,   backgroundColor:purple+'cc', stack:'s' },
        ]
    },
    options: { ...defaults, plugins:{ ...defaults.plugins, tooltip:{ mode:'index' } },
        scales:{ x:{ stacked:true, ticks:{ font:{size:10} } }, y:{ stacked:true, ticks:{ font:{size:10}, callback: v => '$'+v } } } }
});

</script>
</body>
</html>