<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.html'); exit; }
if (!in_array(strtolower($_SESSION['role']), ['admin'])) { header('Location: dashboard.php'); exit; }
require 'db.php';

// ── Filters ──────────────────────────────────────────────────────
$f_from     = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$f_to       = $_GET['date_to']   ?? date('Y-m-d');
$f_group    = in_array($_GET['group'] ?? '', ['day','week','month','quarter','year']) ? $_GET['group'] : 'day';
$f_category = $_GET['category'] ?? 'all';
$f_payment  = $_GET['payment']  ?? '';
$f_customer = trim($_GET['customer'] ?? '');
$f_amt_min  = $_GET['amt_min']  ?? '';
$f_amt_max  = $_GET['amt_max']  ?? '';

// ── Period grouping SQL ───────────────────────────────────────────
$periodMap = [
    'day'     => ["DATE_FORMAT(o.OrderDate,'%Y-%m-%d')", "DATE_FORMAT(o.OrderDate,'%b %d, %Y')"],
    'week'    => ["YEARWEEK(o.OrderDate,1)",               "CONCAT('Week of ', DATE_FORMAT(MIN(o.OrderDate),'%b %d'))"],
    'month'   => ["DATE_FORMAT(o.OrderDate,'%Y-%m')",     "DATE_FORMAT(o.OrderDate,'%b %Y')"],
    'quarter' => ["CONCAT(YEAR(o.OrderDate),'-Q',QUARTER(o.OrderDate))", "CONCAT('Q',QUARTER(o.OrderDate),' ',YEAR(o.OrderDate))"],
    'year'    => ["YEAR(o.OrderDate)",                     "YEAR(o.OrderDate)"],
];
[$periodKey, $periodLabel] = $periodMap[$f_group];

// ── Category filter ───────────────────────────────────────────────
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

// ── Summary totals ────────────────────────────────────────────────
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

// ── Period breakdown ──────────────────────────────────────────────
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

// ── Ticket type breakdown ─────────────────────────────────────────
$ticketBreakdown = $pdo->prepare("
    SELECT oc.CategoryName, oc.Price AS UnitPrice,
           SUM(ot.Quantity) AS TotalQty,
           SUM(o.TransactionAmount) AS Revenue,
           COUNT(DISTINCT o.OrderID) AS Orders
    FROM orders o
    JOIN ordercategories oc ON o.OrderCategoryID = oc.OrderCategoryID
    JOIN order_tickets ot   ON ot.OrderID = o.OrderID
    LEFT JOIN customers c   ON o.CustomerID = c.CustomerID
    WHERE o.OrderDate BETWEEN ? AND ? AND o.OrderCategoryID BETWEEN 1 AND 5 $extraWhere
    GROUP BY oc.OrderCategoryID, oc.CategoryName, oc.Price
    ORDER BY Revenue DESC
");
$ticketBreakdown->execute($baseParams);
$ticketRows = $ticketBreakdown->fetchAll(PDO::FETCH_ASSOC);

// ── Food breakdown ────────────────────────────────────────────────
$foodBreakdown = $pdo->prepare("
    SELECT fi.FoodName, fi.Price AS UnitPrice,
           SUM(ofi.Quantity) AS TotalQty,
           SUM(fi.Price * ofi.Quantity) AS Revenue
    FROM orders o
    JOIN order_food_items ofi ON ofi.OrderID = o.OrderID
    JOIN fooditem fi           ON fi.FoodID  = ofi.FoodID
    LEFT JOIN customers c      ON o.CustomerID = c.CustomerID
    WHERE o.OrderDate BETWEEN ? AND ? $extraWhere
    GROUP BY fi.FoodID, fi.FoodName, fi.Price
    ORDER BY Revenue DESC
");
$foodBreakdown->execute($baseParams);
$foodRows = $foodBreakdown->fetchAll(PDO::FETCH_ASSOC);

// ── Shop breakdown ────────────────────────────────────────────────
$shopBreakdown = $pdo->prepare("
    SELECT si.ItemName, si.Price AS UnitPrice,
           SUM(osi.Quantity) AS TotalQty,
           SUM(si.Price * osi.Quantity) AS Revenue
    FROM orders o
    JOIN order_shop_items osi ON osi.OrderID = o.OrderID
    JOIN shop_items si         ON si.ShopItemID = osi.ShopItemID
    LEFT JOIN customers c      ON o.CustomerID = c.CustomerID
    WHERE o.OrderDate BETWEEN ? AND ? $extraWhere
    GROUP BY si.ShopItemID, si.ItemName, si.Price
    ORDER BY Revenue DESC
");
$shopBreakdown->execute($baseParams);
$shopRows = $shopBreakdown->fetchAll(PDO::FETCH_ASSOC);

// ── Payment method breakdown ──────────────────────────────────────
$paymentBreakdown = $pdo->prepare("
    SELECT o.PaymentMode,
           COUNT(*) AS Orders,
           SUM(o.TransactionAmount) AS Revenue
    FROM orders o
    LEFT JOIN customers c ON o.CustomerID = c.CustomerID
    WHERE o.OrderDate BETWEEN ? AND ? $catWhere $extraWhere
    GROUP BY o.PaymentMode
    ORDER BY Revenue DESC
");
$paymentBreakdown->execute([$f_from, $f_to, ...$extraParams]);
$paymentRows = $paymentBreakdown->fetchAll(PDO::FETCH_ASSOC);

// ── Top customers ─────────────────────────────────────────────────
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

// ── Chart data ────────────────────────────────────────────────────
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
body{overflow:auto}
.pw{box-sizing:border-box;min-height:100vh;padding:28px 36px;background:rgba(187,223,158,.97)}
.ph{display:flex;justify-content:space-between;align-items:center;margin-bottom:22px;border-bottom:3px solid var(--accent-color);padding-bottom:16px;flex-wrap:wrap;gap:12px}
.ph h1{font-size:1.7rem;margin:0}
.hbtns{display:flex;gap:10px;flex-wrap:wrap}
.bn{padding:8px 20px;background:var(--base-color);border:2px solid var(--accent-color);border-radius:1000px;font:inherit;font-weight:600;font-size:.88rem;color:var(--text-color);text-decoration:none;cursor:pointer}
.bn:hover{background:var(--accent-color);text-decoration:none}
.bl{padding:8px 20px;background:var(--accent-color);border:none;border-radius:1000px;font:inherit;font-weight:600;cursor:pointer;color:var(--text-color);text-decoration:none}
.bl:hover{background:var(--text-color);color:white}

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

/* Filter panel */
.fp{background:white;border-radius:14px;padding:16px 20px;margin-bottom:18px;box-shadow:0 2px 8px rgba(0,0,0,.06)}
.fp summary{font-weight:700;font-size:.92rem;cursor:pointer;color:var(--text-color);list-style:none;display:flex;align-items:center;gap:.5rem}
.fp summary::before{content:"🔍"}
.fg-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:11px;margin-top:14px}
.fg{display:flex;flex-direction:column;gap:3px}
.fg label{font-size:.75rem;font-weight:600;color:var(--text-color);text-transform:uppercase;letter-spacing:.04em}
.fg input,.fg select{padding:7px 10px;border:2px solid #ddd;border-radius:8px;font:inherit;font-size:.87rem;background:white}
.fg input:focus,.fg select:focus{outline:none;border-color:var(--accent-color)}
.fa{display:flex;gap:8px;margin-top:12px;flex-wrap:wrap}
.bfil{padding:8px 22px;background:var(--accent-color);border:none;border-radius:8px;font:inherit;font-weight:600;cursor:pointer;color:white}
.bfil:hover{background:var(--text-color)}
.bres{padding:8px 22px;background:#eee;border:none;border-radius:8px;font:inherit;font-weight:600;cursor:pointer;color:#555;text-decoration:none;display:inline-block}
.bres:hover{background:#ddd}

/* Tab nav */
.tab-nav{display:flex;gap:4px;margin-bottom:16px;flex-wrap:wrap}
.tab-btn{padding:8px 18px;border:2px solid #ddd;border-radius:8px 8px 0 0;background:white;font:inherit;font-size:.85rem;font-weight:600;cursor:pointer;color:#888;border-bottom:none;transition:all .15s}
.tab-btn.active{border-color:var(--accent-color);color:var(--text-color);background:white;border-bottom:2px solid white;margin-bottom:-2px;z-index:1}
.tab-btn:hover:not(.active){background:#f5f5f5;color:var(--text-color)}
.tab-content{display:none}.tab-content.active{display:block}

/* Charts */
.chart-grid{display:grid;grid-template-columns:2fr 1fr;gap:16px;margin-bottom:18px}
@media(max-width:900px){.chart-grid{grid-template-columns:1fr}}
.chart-card{background:white;border-radius:14px;padding:18px 20px;box-shadow:0 2px 10px rgba(0,0,0,.07)}
.chart-card h3{font-size:.82rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#888;margin:0 0 14px}
.chart-wrap{position:relative;height:260px}
.chart-wrap-sm{position:relative;height:220px}

/* Tables */
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
</style>
</head>
<body>
<div class="pw">

<!-- Header -->
<div class="ph">
    <div>
        <h1>Revenue &amp; Sales Report</h1>
        <p style="margin:4px 0 0;font-size:.85rem;color:#555">
            <?= htmlspecialchars($f_from) ?> → <?= htmlspecialchars($f_to) ?>
            &nbsp;·&nbsp; Welcome, <?= htmlspecialchars($_SESSION['firstname']) ?>
        </p>
    </div>
    <div class="hbtns">
        <a href="dashboard.php" class="bn">← Dashboard</a>
        <a href="logout.php" class="bl">Logout</a>
    </div>
</div>

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

<!-- Filters -->
<details class="fp" <?= $hasFilters ? 'open' : '' ?>>
    <summary>Filters &amp; Options</summary>
    <form method="GET">
        <div class="fg-grid">
            <div class="fg"><label>Date from</label><input type="date" name="date_from" value="<?= $f_from ?>"></div>
            <div class="fg"><label>Date to</label><input type="date" name="date_to" value="<?= $f_to ?>"></div>
            <div class="fg">
                <label>Group by</label>
                <select name="group">
                    <?php foreach (['day'=>'Daily','week'=>'Weekly','month'=>'Monthly','quarter'=>'Quarterly','year'=>'Yearly'] as $v=>$l): ?>
                    <option value="<?= $v ?>" <?= $f_group===$v?'selected':'' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="fg">
                <label>Category</label>
                <select name="category">
                    <option value="all"    <?= $f_category==='all'   ?'selected':'' ?>>All categories</option>
                    <option value="ticket" <?= $f_category==='ticket'?'selected':'' ?>>Tickets only</option>
                    <option value="food"   <?= $f_category==='food'  ?'selected':'' ?>>Food only</option>
                    <option value="shop"   <?= $f_category==='shop'  ?'selected':'' ?>>Shop only</option>
                </select>
            </div>
            <div class="fg">
                <label>Payment method</label>
                <select name="payment">
                    <option value="">All methods</option>
                    <?php foreach (['Credit Card','Debit Card','Cash','PayPal'] as $pm): ?>
                    <option value="<?= $pm ?>" <?= $f_payment===$pm?'selected':'' ?>><?= $pm ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="fg"><label>Customer name / email</label><input type="text" name="customer" value="<?= htmlspecialchars($f_customer) ?>" placeholder="Search..."></div>
            <div class="fg"><label>Min amount ($)</label><input type="number" step="0.01" name="amt_min" value="<?= htmlspecialchars($f_amt_min) ?>" placeholder="0.00"></div>
            <div class="fg"><label>Max amount ($)</label><input type="number" step="0.01" name="amt_max" value="<?= htmlspecialchars($f_amt_max) ?>" placeholder="999"></div>
        </div>
        <div class="fa">
            <button type="submit" class="bfil">Apply filters</button>
            <a href="revenue_report.php" class="bres">Reset all</a>
        </div>
    </form>
</details>

<!-- Tab navigation -->
<div class="tab-nav">
    <button class="tab-btn active" onclick="showTab('overview')">📊 Overview</button>
    <button class="tab-btn" onclick="showTab('tickets')">🎟 Tickets</button>
    <button class="tab-btn" onclick="showTab('food')">🍽 Food</button>
    <button class="tab-btn" onclick="showTab('shop')">🛍 Shop</button>
    <button class="tab-btn" onclick="showTab('payments')">💳 Payments</button>
    <button class="tab-btn" onclick="showTab('customers')">👥 Top Customers</button>
    <button class="tab-btn" onclick="showTab('table')">📋 Period Table</button>
</div>

<!-- ═══ TAB: OVERVIEW ════════════════════════════════════════════ -->
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

<!-- ═══ TAB: TICKETS ════════════════════════════════════════════ -->
<div id="tab-tickets" class="tab-content">
    <span class="section-hdr">Ticket type breakdown</span>
    <?php if (empty($ticketRows)): ?>
        <div class="tw"><p class="no-data">No ticket data for this period.</p></div>
    <?php else:
        $maxT = max(array_column($ticketRows, 'Revenue') ?: [1]);
    ?>
    <div class="tw"><table>
        <thead><tr><th>Ticket type</th><th>Unit price</th><th>Qty sold</th><th>Revenue</th><th>Share</th><th>Bar</th></tr></thead>
        <tbody>
        <?php foreach ($ticketRows as $r): $pct = $ticketRev > 0 ? round($r['Revenue']/$ticketRev*100,1) : 0; ?>
        <tr>
            <td><span class="bdg bdg-t"><?= htmlspecialchars($r['CategoryName']) ?></span></td>
            <td>$<?= number_format($r['UnitPrice'],2) ?></td>
            <td style="font-weight:700"><?= number_format($r['TotalQty']) ?></td>
            <td class="amt">$<?= number_format($r['Revenue'],2) ?></td>
            <td class="pct-cell"><?= $pct ?>%</td>
            <td><div class="bar-cell"><div class="bar-outer"><div class="bar-inner bar-ticket" style="width:<?= $maxT>0?round($r['Revenue']/$maxT*100):0 ?>%"></div></div></div></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr><td colspan="2">TOTAL</td><td><?= number_format(array_sum(array_column($ticketRows,'TotalQty'))) ?></td><td class="amt">$<?= number_format($ticketRev,2) ?></td><td colspan="2"></td></tr></tfoot>
    </table></div>
    <div class="chart-card">
        <h3>Ticket revenue by type</h3>
        <div style="height:220px"><canvas id="ticketChart"></canvas></div>
    </div>
    <?php endif; ?>
</div>

<!-- ═══ TAB: FOOD ════════════════════════════════════════════════ -->
<div id="tab-food" class="tab-content">
    <span class="section-hdr">Food item breakdown</span>
    <?php if (empty($foodRows)): ?>
        <div class="tw"><p class="no-data">No food orders for this period.</p></div>
    <?php else:
        $maxF = max(array_column($foodRows,'Revenue') ?: [1]);
    ?>
    <div class="tw"><table>
        <thead><tr><th>Item</th><th>Unit price</th><th>Qty sold</th><th>Revenue</th><th>Share</th><th>Bar</th></tr></thead>
        <tbody>
        <?php foreach ($foodRows as $r): $pct = $foodRev > 0 ? round($r['Revenue']/$foodRev*100,1) : 0; ?>
        <tr>
            <td><span class="bdg bdg-f"><?= htmlspecialchars($r['FoodName']) ?></span></td>
            <td>$<?= number_format($r['UnitPrice'],2) ?></td>
            <td style="font-weight:700"><?= number_format($r['TotalQty']) ?></td>
            <td class="amt">$<?= number_format($r['Revenue'],2) ?></td>
            <td class="pct-cell"><?= $pct ?>%</td>
            <td><div class="bar-cell"><div class="bar-outer"><div class="bar-inner bar-food" style="width:<?= $maxF>0?round($r['Revenue']/$maxF*100):0 ?>%"></div></div></div></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr><td colspan="2">TOTAL</td><td><?= number_format(array_sum(array_column($foodRows,'TotalQty'))) ?></td><td class="amt">$<?= number_format($foodRev,2) ?></td><td colspan="2"></td></tr></tfoot>
    </table></div>
    <div class="chart-card">
        <h3>Top food items by revenue</h3>
        <div style="height:240px"><canvas id="foodChart"></canvas></div>
    </div>
    <?php endif; ?>
</div>

<!-- ═══ TAB: SHOP ════════════════════════════════════════════════ -->
<div id="tab-shop" class="tab-content">
    <span class="section-hdr">Shop item breakdown</span>
    <?php if (empty($shopRows)): ?>
        <div class="tw"><p class="no-data">No shop orders for this period.</p></div>
    <?php else:
        $maxS = max(array_column($shopRows,'Revenue') ?: [1]);
    ?>
    <div class="tw"><table>
        <thead><tr><th>Item</th><th>Unit price</th><th>Qty sold</th><th>Revenue</th><th>Share</th><th>Bar</th></tr></thead>
        <tbody>
        <?php foreach ($shopRows as $r): $pct = $shopRev > 0 ? round($r['Revenue']/$shopRev*100,1) : 0; ?>
        <tr>
            <td><span class="bdg bdg-s"><?= htmlspecialchars($r['ItemName']) ?></span></td>
            <td>$<?= number_format($r['UnitPrice'],2) ?></td>
            <td style="font-weight:700"><?= number_format($r['TotalQty']) ?></td>
            <td class="amt">$<?= number_format($r['Revenue'],2) ?></td>
            <td class="pct-cell"><?= $pct ?>%</td>
            <td><div class="bar-cell"><div class="bar-outer"><div class="bar-inner bar-shop" style="width:<?= $maxS>0?round($r['Revenue']/$maxS*100):0 ?>%"></div></div></div></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr><td colspan="2">TOTAL</td><td><?= number_format(array_sum(array_column($shopRows,'TotalQty'))) ?></td><td class="amt">$<?= number_format($shopRev,2) ?></td><td colspan="2"></td></tr></tfoot>
    </table></div>
    <div class="chart-card">
        <h3>Top shop items by revenue</h3>
        <div style="height:240px"><canvas id="shopChart"></canvas></div>
    </div>
    <?php endif; ?>
</div>

<!-- ═══ TAB: PAYMENTS ══════════════════════════════════════════ -->
<div id="tab-payments" class="tab-content">
    <span class="section-hdr">Payment method breakdown</span>
    <div class="two-col">
        <div class="tw"><table>
            <thead><tr><th>Method</th><th>Orders</th><th>Revenue</th><th>Share</th></tr></thead>
            <tbody>
            <?php foreach ($paymentRows as $r): $pct = $totalRev > 0 ? round($r['Revenue']/$totalRev*100,1) : 0; ?>
            <tr>
                <td><span class="bdg bdg-p"><?= htmlspecialchars($r['PaymentMode']) ?></span></td>
                <td><?= $r['Orders'] ?></td>
                <td class="amt">$<?= number_format($r['Revenue'],2) ?></td>
                <td class="pct-cell"><?= $pct ?>%</td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <div class="chart-card">
            <h3>Payment method split</h3>
            <div style="height:220px"><canvas id="paymentChart"></canvas></div>
        </div>
    </div>
</div>

<!-- ═══ TAB: TOP CUSTOMERS ══════════════════════════════════════ -->
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

<!-- ═══ TAB: PERIOD TABLE ══════════════════════════════════════ -->
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
                <th>🎟 Tickets</th>
                <th>🍽 Food</th>
                <th>🛍 Shop</th>
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

</div><!-- end .pw -->

<script>
// ── Tab switching ─────────────────────────────────────────────────
function showTab(name) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    event.currentTarget.classList.add('active');
}

// ── Chart.js data from PHP ────────────────────────────────────────
const labels  = <?= json_encode($chartLabels) ?>;
const ticket  = <?= json_encode(array_map('floatval', $chartTicket)) ?>;
const food    = <?= json_encode(array_map('floatval', $chartFood)) ?>;
const shop    = <?= json_encode(array_map('floatval', $chartShop)) ?>;
const total   = <?= json_encode(array_map('floatval', $chartTotal)) ?>;

const ticketNames = <?= json_encode(array_column($ticketRows, 'CategoryName')) ?>;
const ticketRevs  = <?= json_encode(array_map('floatval', array_column($ticketRows, 'Revenue'))) ?>;
const foodNames   = <?= json_encode(array_column($foodRows, 'FoodName')) ?>;
const foodRevs    = <?= json_encode(array_map('floatval', array_column($foodRows, 'Revenue'))) ?>;
const shopNames   = <?= json_encode(array_column($shopRows, 'ItemName')) ?>;
const shopRevs    = <?= json_encode(array_map('floatval', array_column($shopRows, 'Revenue'))) ?>;
const payNames    = <?= json_encode(array_column($paymentRows, 'PaymentMode')) ?>;
const payRevs     = <?= json_encode(array_map('floatval', array_column($paymentRows, 'Revenue'))) ?>;

const green  = '#27ae60';
const blue   = '#2980b9';
const orange = '#e67e22';
const purple = '#8e44ad';
const accent = '#6ac473';

const defaults = { responsive:true, maintainAspectRatio:false,
    plugins:{ legend:{ labels:{ font:{ family:'Montserrat,sans-serif', size:11 } } } },
    scales:{ x:{ ticks:{ font:{ size:10 } } }, y:{ ticks:{ font:{ size:10 } } } }
};

// Line chart
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

// Donut chart
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

// Stacked bar chart
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

// Ticket horizontal bar
if (ticketNames.length) new Chart(document.getElementById('ticketChart'), {
    type: 'bar',
    data: { labels:ticketNames, datasets:[{ label:'Revenue', data:ticketRevs, backgroundColor:blue+'cc', borderRadius:4 }] },
    options: { ...defaults, indexAxis:'y',
        plugins:{ ...defaults.plugins, legend:{display:false}, tooltip:{ callbacks:{ label: ctx => ' $'+ctx.parsed.x.toFixed(2) } } },
        scales:{ x:{ ticks:{ callback: v => '$'+v, font:{size:10} } }, y:{ ticks:{ font:{size:10} } } } }
});

// Food horizontal bar
if (foodNames.length) new Chart(document.getElementById('foodChart'), {
    type: 'bar',
    data: { labels:foodNames, datasets:[{ label:'Revenue', data:foodRevs, backgroundColor:orange+'cc', borderRadius:4 }] },
    options: { ...defaults, indexAxis:'y',
        plugins:{ ...defaults.plugins, legend:{display:false}, tooltip:{ callbacks:{ label: ctx => ' $'+ctx.parsed.x.toFixed(2) } } },
        scales:{ x:{ ticks:{ callback: v => '$'+v, font:{size:10} } }, y:{ ticks:{ font:{size:10} } } } }
});

// Shop horizontal bar
if (shopNames.length) new Chart(document.getElementById('shopChart'), {
    type: 'bar',
    data: { labels:shopNames, datasets:[{ label:'Revenue', data:shopRevs, backgroundColor:purple+'cc', borderRadius:4 }] },
    options: { ...defaults, indexAxis:'y',
        plugins:{ ...defaults.plugins, legend:{display:false}, tooltip:{ callbacks:{ label: ctx => ' $'+ctx.parsed.x.toFixed(2) } } },
        scales:{ x:{ ticks:{ callback: v => '$'+v, font:{size:10} } }, y:{ ticks:{ font:{size:10} } } } }
});

// Payment pie
if (payNames.length) new Chart(document.getElementById('paymentChart'), {
    type: 'pie',
    data: { labels:payNames, datasets:[{ data:payRevs, backgroundColor:[blue,orange,purple,green+'aa'], borderWidth:2, borderColor:'#fff' }] },
    options: { responsive:true, maintainAspectRatio:false,
        plugins:{ legend:{ position:'bottom', labels:{ font:{size:11} } },
            tooltip:{ callbacks:{ label: ctx => ' $'+ctx.parsed.toFixed(2) } } } }
});
</script>
</body>
</html>