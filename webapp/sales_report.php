<?php
require_once __DIR__ . '/session_bootstrap.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit;
}
require_once 'db.php';

$role = $_SESSION['role'] ?? '';
if (($role ?? '') !== 'admin') {
    header('Location: dashboard.php');
    exit;
}
$dashboardBackHref = 'dashboard.php#gift-shop-admin';

$paymentModes = ['Credit Card', 'Debit Card', 'Cash', 'PayPal'];

$f_from       = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$f_to         = $_GET['date_to']   ?? date('Y-m-d');
$f_shop       = $_GET['shop_id']   ?? '';
$f_item       = trim($_GET['item'] ?? '');
$f_payment    = $_GET['payment']  ?? '';
$f_customer   = trim($_GET['customer'] ?? '');
$f_line_min   = $_GET['line_min'] ?? '';
$f_line_max   = $_GET['line_max'] ?? '';
$f_order_min  = $_GET['order_min'] ?? '';
$f_order_max  = $_GET['order_max'] ?? '';

$where  = ['o.OrderCategoryID = 6'];
$params = [];

$where[] = 'o.OrderDate >= ?';
$where[] = 'o.OrderDate <= ?';
$params[] = $f_from;
$params[] = $f_to;

if ($f_shop !== '' && ctype_digit((string) $f_shop)) {
    $where[] = 's.ShopID = ?';
    $params[] = (int) $f_shop;
}
if ($f_item !== '') {
    $where[] = 'si.ItemName LIKE ?';
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
    $where[] = '(osi.Quantity * si.Price) >= ?';
    $params[] = (float) $f_line_min;
}
if ($f_line_max !== '') {
    $where[] = '(osi.Quantity * si.Price) <= ?';
    $params[] = (float) $f_line_max;
}
if ($f_order_min !== '') {
    $where[] = 'o.TransactionAmount >= ?';
    $params[] = (float) $f_order_min;
}
if ($f_order_max !== '') {
    $where[] = 'o.TransactionAmount <= ?';
    $params[] = (float) $f_order_max;
}

$whereSql = implode(' AND ', $where);

$shops = $pdo->query('SELECT ShopID, ShopName FROM shops ORDER BY ShopName')->fetchAll(PDO::FETCH_ASSOC);

$sql = "
    SELECT
        o.OrderID,
        o.OrderDate,
        c.FirstName,
        c.LastName,
        c.Email,
        si.ItemName,
        s.ShopName,
        osi.Quantity,
        si.Price,
        (osi.Quantity * si.Price) AS LineTotal,
        o.PaymentMode,
        o.TransactionAmount AS OrderTotal
    FROM orders o
    JOIN order_shop_items osi ON osi.OrderID = o.OrderID
    JOIN shop_items si ON si.ShopItemID = osi.ShopItemID
    JOIN shops s ON s.ShopID = si.ShopID
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
    SELECT si.ItemName AS name, SUM(osi.Quantity) AS qty
    FROM orders o
    JOIN order_shop_items osi ON osi.OrderID = o.OrderID
    JOIN shop_items si ON si.ShopItemID = osi.ShopItemID
    JOIN shops s ON s.ShopID = si.ShopID
    JOIN customers c ON c.CustomerID = o.CustomerID
    WHERE $whereSql
    GROUP BY si.ShopItemID, si.ItemName
    ORDER BY qty DESC, si.ItemName ASC
    LIMIT 15
");
$chartStmt->execute($params);
$chartItems = $chartStmt->fetchAll(PDO::FETCH_ASSOC);

$chartJson = json_encode(array_map(static function ($r) {
    return ['name' => (string) $r['name'], 'qty' => (int) $r['qty']];
}, $chartItems), JSON_UNESCAPED_UNICODE);

$shopRevenueStmt = $pdo->prepare("
    SELECT s.ShopName, SUM(osi.Quantity * si.Price) AS Revenue
    FROM orders o
    JOIN order_shop_items osi ON osi.OrderID = o.OrderID
    JOIN shop_items si ON si.ShopItemID = osi.ShopItemID
    JOIN shops s ON s.ShopID = si.ShopID
    JOIN customers c ON c.CustomerID = o.CustomerID
    WHERE $whereSql
    GROUP BY s.ShopID, s.ShopName
    ORDER BY Revenue DESC
");
$shopRevenueStmt->execute($params);
$shopRevenueByShop = $shopRevenueStmt->fetchAll(PDO::FETCH_ASSOC);

$shopRevenueChartJson = json_encode([
    'labels' => array_column($shopRevenueByShop, 'ShopName'),
    'revenue' => array_map('floatval', array_column($shopRevenueByShop, 'Revenue')),
], JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gift Shop Sales report</title>
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
            text-decoration-color: #1a3d1c;
            text-underline-offset: 3px;
            letter-spacing: 0.01em;
            box-shadow: 0 1px 2px rgba(26, 61, 28, 0.12);
        }
        a.back-dash-pill:hover {
            background: #6daa5a;
            color: #132a14;
            text-decoration-color: #132a14;
        }
        .report-nav .nav-sep { color: #6b7f5c; font-weight: 600; }
        .report-nav .nav-link-record {
            font-weight: 600;
            color: #1a3d1c;
            text-decoration: underline;
            text-underline-offset: 2px;
        }
        .report-nav .nav-link-record:hover { color: #0f2410; }
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
        .filter-scope-note {
            font-size: 0.82rem;
            color: #555;
            margin: 0 0 12px;
            line-height: 1.45;
            font-weight: 600;
        }
        .gift-shop-chart-wrap {
            margin-top: 12px;
            background: #fff;
            border-radius: 14px;
            padding: 18px 20px 12px;
            box-shadow: 0 3px 10px rgba(0,0,0,.06);
            border: 1px solid rgba(46, 90, 26, 0.12);
        }
        .gift-shop-chart-wrap h2 {
            margin: 0 0 4px;
            font-size: 1.05rem;
            font-weight: 800;
            color: var(--text-color);
        }
        .gift-shop-chart-wrap .chart-sub {
            margin: 0 0 14px;
            font-size: 0.85rem;
            color: #666;
        }
        .gift-shop-chart-wrap .chart-canvas-box {
            position: relative;
            height: min(420px, 55vh);
            max-width: 100%;
        }
        .gift-shop-chart-wrap .chart-empty {
            margin: 0;
            padding: 24px 12px;
            text-align: center;
            color: #666;
            font-size: 0.95rem;
        }
        .gift-shop-charts-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
            align-items: stretch;
        }
        @media (max-width: 960px) {
            .gift-shop-charts-grid { grid-template-columns: 1fr; }
        }
        .chart-donut-box {
            position: relative;
            height: 260px;
            max-width: 100%;
        }
    </style>
</head>
<body>
    <div class="page-wrap">
        <h1>Gift Shop Sales report</h1>
        <p class="report-nav">
            <?php include __DIR__ . '/admin_header_cart_profile.inc.php'; ?>
            <?php if ($role === 'admin'): ?>
            <a class="back-dash-pill" href="logout.php">Logout</a>
            <?php endif; ?>
            <a class="back-dash-pill" href="<?= htmlspecialchars($dashboardBackHref) ?>">Back to dashboard</a>
        </p>

        <div class="filter-card">
            <h2>Filter gift shop sales</h2>
            <form method="get" action="sales_report.php">
                <div class="filter-grid">
                    <div class="filter-group">
                        <label for="gs_date_from">Date from</label>
                        <input id="gs_date_from" type="date" name="date_from" value="<?= htmlspecialchars($f_from) ?>">
                    </div>
                    <div class="filter-group">
                        <label for="gs_date_to">Date to</label>
                        <input id="gs_date_to" type="date" name="date_to" value="<?= htmlspecialchars($f_to) ?>">
                    </div>
                    <div class="filter-group">
                        <label for="gs_shop">Shop</label>
                        <select id="gs_shop" name="shop_id">
                            <option value="">All shops</option>
                            <?php foreach ($shops as $sh): ?>
                                <option value="<?= (int) $sh['ShopID'] ?>" <?= (string) $f_shop === (string) $sh['ShopID'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($sh['ShopName']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="gs_item">Item name contains</label>
                        <input id="gs_item" type="text" name="item" value="<?= htmlspecialchars($f_item) ?>" placeholder="e.g. mug, plush">
                    </div>
                    <div class="filter-group">
                        <label for="gs_payment">Payment method</label>
                        <select id="gs_payment" name="payment">
                            <option value="">All methods</option>
                            <?php foreach ($paymentModes as $pm): ?>
                                <option value="<?= htmlspecialchars($pm) ?>" <?= $f_payment === $pm ? 'selected' : '' ?>><?= htmlspecialchars($pm) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group filter-group--wide">
                        <label for="gs_customer">Customer name or email</label>
                        <input id="gs_customer" type="text" name="customer" value="<?= htmlspecialchars($f_customer) ?>" placeholder="e.g. Jane or @email">
                    </div>
                    <div class="filter-group">
                        <label for="gs_line_min">Min line total ($)</label>
                        <input id="gs_line_min" type="number" step="0.01" name="line_min" value="<?= htmlspecialchars($f_line_min) ?>" placeholder="0.00">
                    </div>
                    <div class="filter-group">
                        <label for="gs_line_max">Max line total ($)</label>
                        <input id="gs_line_max" type="number" step="0.01" name="line_max" value="<?= htmlspecialchars($f_line_max) ?>" placeholder="999">
                    </div>
                    <div class="filter-group">
                        <label for="gs_order_min">Min order total ($)</label>
                        <input id="gs_order_min" type="number" step="0.01" name="order_min" value="<?= htmlspecialchars($f_order_min) ?>" placeholder="0.00">
                    </div>
                    <div class="filter-group">
                        <label for="gs_order_max">Max order total ($)</label>
                        <input id="gs_order_max" type="number" step="0.01" name="order_max" value="<?= htmlspecialchars($f_order_max) ?>" placeholder="999">
                    </div>
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-edit">Search</button>
                        <a href="sales_report.php" class="btn">Reset</a>
                    </div>
                </div>
            </form>
        </div>

        <div class="gift-shop-charts-grid">
            <div class="gift-shop-chart-wrap">
                <h2>Revenue by shop</h2>
                <p class="chart-empty" id="shopRevenueDonutEmpty" <?= count($shopRevenueByShop) > 0 ? 'hidden' : '' ?>>No revenue to show for these filters.</p>
                <div class="chart-donut-box" id="shopRevenueDonutBox" <?= count($shopRevenueByShop) === 0 ? 'hidden' : '' ?>>
                    <canvas id="giftShopRevenueByShopDonut" aria-label="Revenue by shop"></canvas>
                </div>
            </div>
            <div class="gift-shop-chart-wrap">
                <h2>Top sellers (filtered)</h2>
                <p class="chart-sub" id="giftShopChartSubtitle">Units sold — <?= htmlspecialchars($f_from) ?> to <?= htmlspecialchars($f_to) ?><?= $f_item !== '' ? ' · item contains “' . htmlspecialchars($f_item) . '”' : '' ?>.</p>
                <p class="chart-empty" id="giftShopChartEmpty" <?= count($chartItems) > 0 ? 'hidden' : '' ?>>No gift shop line items match these filters.</p>
                <div class="chart-canvas-box" id="giftShopChartBox" <?= count($chartItems) === 0 ? 'hidden' : '' ?>>
                    <canvas id="giftShopMonthlyChart" aria-label="Top gift shop items by quantity"></canvas>
                </div>
            </div>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
        <script>
        (function () {
            const shopRevData = <?= $shopRevenueChartJson ?>;
            const palette = ['#8e44ad','#2980b9','#e67e22','#27ae60','#9b59b6','#16a085','#d35400','#3498db','#c0392b','#1abc9c','#34495e','#f39c12'];
            const donutCanvas = document.getElementById('giftShopRevenueByShopDonut');
            if (donutCanvas && shopRevData.labels && shopRevData.labels.length > 0) {
                const bg = shopRevData.labels.map(function (_, i) { return palette[i % palette.length]; });
                new Chart(donutCanvas.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: shopRevData.labels,
                        datasets: [{
                            data: shopRevData.revenue,
                            backgroundColor: bg,
                            borderWidth: 2,
                            borderColor: '#fff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '62%',
                        plugins: {
                            legend: { position: 'bottom', labels: { font: { size: 11 }, boxWidth: 12, padding: 10 } },
                            tooltip: {
                                callbacks: {
                                    label: function (ctx) {
                                        var v = typeof ctx.parsed === 'number' ? ctx.parsed : ctx.raw;
                                        return ' $' + Number(v).toFixed(2);
                                    }
                                }
                            }
                        }
                    }
                });
            }
        })();
        (function () {
            const canvas = document.getElementById('giftShopMonthlyChart');
            const items = <?= $chartJson ?>;
            const emptyEl = document.getElementById('giftShopChartEmpty');
            const box = document.getElementById('giftShopChartBox');
            if (!canvas || items.length === 0) return;

            const labels = items.map(function (r) { return r.name; });
            const qty = items.map(function (r) { return r.qty; });
            const colors = {
                bar: 'rgba(76, 145, 65, 0.75)',
                border: 'rgba(46, 90, 26, 0.9)',
                grid: 'rgba(0,0,0,0.06)'
            };

            new Chart(canvas.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Units sold',
                        data: qty,
                        backgroundColor: colors.bar,
                        borderColor: colors.border,
                        borderWidth: 1,
                        borderRadius: 6
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function (ctx) {
                                    const n = ctx.parsed.x;
                                    return n === 1 ? '1 unit' : n + ' units';
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            ticks: { precision: 0 },
                            grid: { color: colors.grid }
                        },
                        y: {
                            grid: { display: false },
                            ticks: { autoSkip: false, font: { size: 11 } }
                        }
                    }
                }
            });
        })();
        </script>

        <div class="panel">
            <?php if (count($rows) === 0): ?>
                <p>No gift shop line items match these filters.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Date</th>
                            <th>Customer</th>
                            <th>Email</th>
                            <th>Shop</th>
                            <th>Item</th>
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
                                <td><?= htmlspecialchars($r['ShopName']) ?></td>
                                <td><?= htmlspecialchars($r['ItemName']) ?></td>
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
