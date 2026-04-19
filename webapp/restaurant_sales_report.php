<?php
require_once __DIR__ . '/session_bootstrap.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit;
}

$role = $_SESSION['role'] ?? '';
if (($role ?? '') !== 'admin') {
    header('Location: dashboard.php');
    exit;
}
$dashboardBackHref = 'dashboard.php#restaurant-shop-admin';

require_once 'db.php';

$paymentModes = ['Credit Card', 'Debit Card', 'Cash', 'PayPal'];
$f_from      = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$f_to        = $_GET['date_to']   ?? date('Y-m-d');
$f_stall     = $_GET['stall_id']  ?? '';
$f_item      = trim($_GET['item'] ?? '');
$f_payment   = $_GET['payment'] ?? '';
$f_customer  = trim($_GET['customer'] ?? '');
$f_line_min  = $_GET['line_min'] ?? '';
$f_line_max  = $_GET['line_max'] ?? '';

$where = ['o.OrderCategoryID = 5', 'o.OrderDate >= ?', 'o.OrderDate <= ?'];
$params = [$f_from, $f_to];

if ($f_stall !== '' && ctype_digit((string) $f_stall)) {
    $where[] = 'fs.StallID = ?';
    $params[] = (int) $f_stall;
}
if ($f_item !== '') {
    $where[] = 'fi.FoodName LIKE ?';
    $params[] = '%' . $f_item . '%';
}
if ($f_payment !== '') {
    $where[] = 'o.PaymentMode = ?';
    $params[] = $f_payment;
}
if ($f_customer !== '') {
    $where[] = '(c.FirstName LIKE ? OR c.LastName LIKE ? OR c.Email LIKE ?)';
    $like = '%' . $f_customer . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
if ($f_line_min !== '') {
    $where[] = '(ofi.Quantity * fi.Price) >= ?';
    $params[] = (float) $f_line_min;
}
if ($f_line_max !== '') {
    $where[] = '(ofi.Quantity * fi.Price) <= ?';
    $params[] = (float) $f_line_max;
}
$whereSql = implode(' AND ', $where);

$stalls = $pdo->query('SELECT StallID, Name FROM foodstall ORDER BY Name')->fetchAll(PDO::FETCH_ASSOC);

$sql = "
    SELECT
        o.OrderID,
        o.OrderDate,
        c.FirstName,
        c.LastName,
        c.Email,
        fs.Name AS StallName,
        fi.FoodName,
        ofi.Quantity,
        fi.Price,
        (ofi.Quantity * fi.Price) AS LineTotal,
        o.PaymentMode,
        o.TransactionAmount AS OrderTotal
    FROM orders o
    JOIN order_food_items ofi ON ofi.OrderID = o.OrderID
    JOIN fooditem fi ON fi.FoodID = ofi.FoodID
    JOIN foodstall fs ON fs.StallID = fi.StallID
    JOIN customers c ON c.CustomerID = o.CustomerID
    WHERE $whereSql
    ORDER BY o.OrderDate DESC, o.OrderID DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$grandTotal = 0.0;
foreach ($rows as $r) {
    $grandTotal += (float) $r['LineTotal'];
}

$chartStmt = $pdo->prepare("
    SELECT fi.FoodName AS name, SUM(ofi.Quantity) AS qty
    FROM orders o
    JOIN order_food_items ofi ON ofi.OrderID = o.OrderID
    JOIN fooditem fi ON fi.FoodID = ofi.FoodID
    JOIN foodstall fs ON fs.StallID = fi.StallID
    JOIN customers c ON c.CustomerID = o.CustomerID
    WHERE $whereSql
    GROUP BY fi.FoodID, fi.FoodName
    ORDER BY qty DESC, fi.FoodName ASC
    LIMIT 15
");
$chartStmt->execute($params);
$chartItems = $chartStmt->fetchAll(PDO::FETCH_ASSOC);
$chartJson = json_encode(array_map(static function ($r) {
    return ['name' => (string) $r['name'], 'qty' => (int) $r['qty']];
}, $chartItems), JSON_UNESCAPED_UNICODE);

$stallRevenueStmt = $pdo->prepare("
    SELECT fs.Name AS StallName, SUM(ofi.Quantity * fi.Price) AS Revenue
    FROM orders o
    JOIN order_food_items ofi ON ofi.OrderID = o.OrderID
    JOIN fooditem fi ON fi.FoodID = ofi.FoodID
    JOIN foodstall fs ON fs.StallID = fi.StallID
    JOIN customers c ON c.CustomerID = o.CustomerID
    WHERE $whereSql
    GROUP BY fs.StallID, fs.Name
    ORDER BY Revenue DESC
");
$stallRevenueStmt->execute($params);
$stallRevenue = $stallRevenueStmt->fetchAll(PDO::FETCH_ASSOC);
$stallRevenueChartJson = json_encode([
    'labels' => array_column($stallRevenue, 'StallName'),
    'revenue' => array_map('floatval', array_column($stallRevenue, 'Revenue')),
], JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restaurant Sales report</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .page-wrap { min-height: 100vh; padding: 30px 40px; background-color: var(--base-color); text-align: left; }
        .panel { background: #fff; border-radius: 14px; padding: 20px; margin-top: 12px; }
        table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        th, td { padding: 9px 10px; border-bottom: 1px solid #e6ecd9; }
        th { background: #f2f8eb; text-align: left; }
        .summary { margin-top: 10px; font-weight: 700; }
        .report-nav {
            margin: 0.75rem 0 1rem;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.65rem 1rem;
        }
        a.back-dash-pill {
            display: inline-block;
            border-radius: 9999px;
            padding: 14px 36px;
            background: #7cb869;
            color: #1a3d1c;
            font-weight: 700;
            font-size: 0.95rem;
            text-decoration: underline;
            text-underline-offset: 3px;
            box-shadow: 0 1px 2px rgba(26, 61, 28, 0.12);
        }
        .report-nav .nav-link-record { font-weight: 600; color: #1a3d1c; text-decoration: underline; text-underline-offset: 2px; }
        .filter-card {
            background: white;
            border-radius: 12px;
            padding: 12px 14px;
            margin-bottom: 14px;
            box-shadow: 0 3px 8px rgba(0,0,0,0.05);
        }
        .filter-card h2 { font-size: 0.95rem; font-weight: 700; margin-bottom: 10px; color: var(--text-color); }
        .btn { padding: 6px 14px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 0.85rem; border: none; cursor: pointer; font: inherit; display: inline-block; }
        .btn-edit { background-color: var(--accent-color); color: white; }
        .filter-actions .btn:not(.btn-edit) { background: #eee; color: #555; }
        .filter-scope-note { font-size: 0.82rem; color: #555; margin: 0 0 12px; line-height: 1.45; font-weight: 600; }
        #rt_line_max { max-width: 170px; }
        .gift-shop-chart-wrap {
            margin-top: 12px;
            background: #fff;
            border-radius: 14px;
            padding: 18px 20px 12px;
            box-shadow: 0 3px 10px rgba(0,0,0,.06);
            border: 1px solid rgba(46, 90, 26, 0.12);
        }
        .gift-shop-chart-wrap h2 { margin: 0 0 4px; font-size: 1.05rem; font-weight: 800; color: var(--text-color); }
        .gift-shop-chart-wrap .chart-sub { margin: 0 0 14px; font-size: 0.85rem; color: #666; }
        .gift-shop-chart-wrap .chart-canvas-box { position: relative; height: min(420px, 55vh); max-width: 100%; }
        .gift-shop-chart-wrap .chart-empty { margin: 0; padding: 24px 12px; text-align: center; color: #666; font-size: 0.95rem; }
        .gift-shop-charts-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; align-items: stretch; }
        @media (max-width: 960px) { .gift-shop-charts-grid { grid-template-columns: 1fr; } }
        .chart-donut-box { position: relative; height: 260px; max-width: 100%; }
    </style>
</head>
<body>
    <div class="page-wrap">
        <h1>Restaurant Sales report</h1>
        <p class="report-nav">
            <?php include __DIR__ . '/admin_header_cart_profile.inc.php'; ?>
            <?php if ($role === 'admin'): ?>
            <a class="back-dash-pill" href="logout.php">Logout</a>
            <?php endif; ?>
            <a class="back-dash-pill" href="<?= htmlspecialchars($dashboardBackHref) ?>">Back to dashboard</a>
        </p>

        <div class="filter-card">
            <h2>Filter restaurant sales</h2>
            <form method="get" action="restaurant_sales_report.php">
                <div class="filter-grid">
                    <div class="filter-group">
                        <label for="rt_date_from">Date from</label>
                        <input id="rt_date_from" type="date" name="date_from" value="<?= htmlspecialchars($f_from) ?>">
                    </div>
                    <div class="filter-group">
                        <label for="rt_date_to">Date to</label>
                        <input id="rt_date_to" type="date" name="date_to" value="<?= htmlspecialchars($f_to) ?>">
                    </div>
                    <div class="filter-group">
                        <label for="rt_stall">Stall</label>
                        <select id="rt_stall" name="stall_id">
                            <option value="">All stalls</option>
                            <?php foreach ($stalls as $st): ?>
                                <option value="<?= (int) $st['StallID'] ?>" <?= (string) $f_stall === (string) $st['StallID'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($st['Name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="rt_item">Food item contains</label>
                        <input id="rt_item" type="text" name="item" value="<?= htmlspecialchars($f_item) ?>" placeholder="e.g. burger, salad">
                    </div>
                    <div class="filter-group">
                        <label for="rt_payment">Payment method</label>
                        <select id="rt_payment" name="payment">
                            <option value="">All methods</option>
                            <?php foreach ($paymentModes as $pm): ?>
                                <option value="<?= htmlspecialchars($pm) ?>" <?= $f_payment === $pm ? 'selected' : '' ?>><?= htmlspecialchars($pm) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group filter-group--wide">
                        <label for="rt_customer">Customer name or email</label>
                        <input id="rt_customer" type="text" name="customer" value="<?= htmlspecialchars($f_customer) ?>" placeholder="e.g. Jane or @email">
                    </div>
                    <div class="filter-group">
                        <label for="rt_line_min">Min line total ($)</label>
                        <input id="rt_line_min" type="number" step="0.01" name="line_min" value="<?= htmlspecialchars($f_line_min) ?>">
                    </div>
                    <div class="filter-group">
                        <label for="rt_line_max">Max line total ($)</label>
                        <input id="rt_line_max" type="number" step="0.01" name="line_max" value="<?= htmlspecialchars($f_line_max) ?>">
                    </div>
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-edit">Search</button>
                        <a href="restaurant_sales_report.php" class="btn">Reset</a>
                    </div>
                </div>
            </form>
        </div>

        <div class="gift-shop-charts-grid">
            <div class="gift-shop-chart-wrap">
                <h2>Revenue by stall</h2>
                <p class="chart-empty" id="stallRevenueDonutEmpty" <?= count($stallRevenue) > 0 ? 'hidden' : '' ?>>No revenue to show for these filters.</p>
                <div class="chart-donut-box" id="stallRevenueDonutBox" <?= count($stallRevenue) === 0 ? 'hidden' : '' ?>>
                    <canvas id="restaurantRevenueByStallDonut" aria-label="Revenue by stall"></canvas>
                </div>
            </div>
            <div class="gift-shop-chart-wrap">
                <h2>Top food items (filtered)</h2>
                <p class="chart-sub">Units sold — <?= htmlspecialchars($f_from) ?> to <?= htmlspecialchars($f_to) ?><?= $f_item !== '' ? ' · item contains “' . htmlspecialchars($f_item) . '”' : '' ?>.</p>
                <p class="chart-empty" id="foodChartEmpty" <?= count($chartItems) > 0 ? 'hidden' : '' ?>>No food line items match these filters.</p>
                <div class="chart-canvas-box" id="foodChartBox" <?= count($chartItems) === 0 ? 'hidden' : '' ?>>
                    <canvas id="restaurantTopFoodChart" aria-label="Top food items by quantity"></canvas>
                </div>
            </div>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
        <script>
        (function () {
            const stallRevData = <?= $stallRevenueChartJson ?>;
            const palette = ['#8e44ad','#2980b9','#e67e22','#27ae60','#9b59b6','#16a085','#d35400','#3498db','#c0392b','#1abc9c','#34495e','#f39c12'];
            const donutCanvas = document.getElementById('restaurantRevenueByStallDonut');
            if (donutCanvas && stallRevData.labels && stallRevData.labels.length > 0) {
                const bg = stallRevData.labels.map((_, i) => palette[i % palette.length]);
                new Chart(donutCanvas.getContext('2d'), {
                    type: 'doughnut',
                    data: { labels: stallRevData.labels, datasets: [{ data: stallRevData.revenue, backgroundColor: bg, borderWidth: 2, borderColor: '#fff' }] },
                    options: { responsive: true, maintainAspectRatio: false, cutout: '62%', plugins: { legend: { position: 'bottom', labels: { font: { size: 11 }, boxWidth: 12, padding: 10 } } } }
                });
            }
        })();
        (function () {
            const canvas = document.getElementById('restaurantTopFoodChart');
            const items = <?= $chartJson ?>;
            if (!canvas || items.length === 0) return;
            const labels = items.map(r => r.name);
            const qty = items.map(r => r.qty);
            new Chart(canvas.getContext('2d'), {
                type: 'bar',
                data: { labels, datasets: [{ label: 'Units sold', data: qty, backgroundColor: 'rgba(76, 145, 65, 0.75)', borderColor: 'rgba(46, 90, 26, 0.9)', borderWidth: 1, borderRadius: 6 }] },
                options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, ticks: { precision: 0 } }, y: { ticks: { autoSkip: false, font: { size: 11 } } } } }
            });
        })();
        </script>

        <div class="panel">
            <?php if (count($rows) === 0): ?>
                <p>No restaurant line items match these filters.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Date</th>
                            <th>Customer</th>
                            <th>Email</th>
                            <th>Stall</th>
                            <th>Food Item</th>
                            <th>Qty</th>
                            <th>Unit Price</th>
                            <th>Line Total</th>
                            <th>Order total</th>
                            <th>Payment</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td>#<?= (int) $r['OrderID'] ?></td>
                                <td><?= htmlspecialchars($r['OrderDate']) ?></td>
                                <td><?= htmlspecialchars($r['FirstName'] . ' ' . $r['LastName']) ?></td>
                                <td><?= htmlspecialchars($r['Email']) ?></td>
                                <td><?= htmlspecialchars($r['StallName']) ?></td>
                                <td><?= htmlspecialchars($r['FoodName']) ?></td>
                                <td><?= (int) $r['Quantity'] ?></td>
                                <td>$<?= number_format((float) $r['Price'], 2) ?></td>
                                <td>$<?= number_format((float) $r['LineTotal'], 2) ?></td>
                                <td>$<?= number_format((float) $r['OrderTotal'], 2) ?></td>
                                <td><?= htmlspecialchars($r['PaymentMode']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="summary">Total line sales (filtered): $<?= number_format($grandTotal, 2) ?></div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
