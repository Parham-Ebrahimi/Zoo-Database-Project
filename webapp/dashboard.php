<?php
require_once __DIR__ . '/session_bootstrap.php';
if (!isset($_SESSION['user_id'])) {
    if (!empty($_SESSION['customer_id'])) {
        header('Location: customer-dashboard.php');
        exit;
    }
    header('Location: login.html');
    exit;
}

require 'db.php';

$role = $_SESSION['role'];
$firstname = $_SESSION['firstname'];
$roleLower = strtolower(trim((string) $role));
$isAdmin = ($role === 'admin');
$isVet = ($roleLower === 'vet');
$isCaretaker = ($roleLower === 'caretaker');
$isCashier = ($roleLower === 'cashier');
$isGiftShopEmployee = ($role === 'Gift Shop Employee');
$isRestaurantEmployee = ($role === 'Restaurant Employee');

// Quick stats for admin
$totalAnimals   = $pdo->query("SELECT COUNT(*) FROM animal")->fetchColumn();
$totalEmployees = $pdo->query("SELECT COUNT(*) FROM employees")->fetchColumn();
$pendingHealth  = $pdo->query("SELECT COUNT(*) FROM animal WHERE Health_Status != 'Healthy'")->fetchColumn();
$todayRevenue   = $pdo->query("SELECT TotalRevenue FROM daily_revenue WHERE RevenueDate = CURDATE()")->fetchColumn() ?? 0;
$pendingRestock = 0;
if ($isAdmin || $isGiftShopEmployee) {
    try {
        $pendingRestock = (int) $pdo->query("
            SELECT COUNT(*) FROM restock_alerts WHERE IsResolved = 0
        ")->fetchColumn();
    } catch (Throwable $e) {
        $pendingRestock = 0;
    }
}
$pendingRestaurantRestock = 0;
if ($isRestaurantEmployee || $isAdmin) {
    try {
        $pendingRestaurantRestock = (int) $pdo->query("
            SELECT COUNT(*) FROM restaurant_restock_alerts WHERE IsResolved = 0
        ")->fetchColumn();
    } catch (Throwable $e) {
        $pendingRestaurantRestock = 0;
    }
}

$giftShopSnapshot = null;
if ($isGiftShopEmployee) {
    $gsMonthStart = date('Y-m-01');
    $gsNextMonth  = date('Y-m-01', strtotime('first day of next month'));
    try {
        $st = $pdo->prepare("
            SELECT COALESCE(SUM(o.TransactionAmount), 0)
            FROM orders o
            WHERE o.OrderCategoryID = 6 AND o.OrderDate >= ? AND o.OrderDate < ?
        ");
        $st->execute([$gsMonthStart, $gsNextMonth]);
        $mtdRevenue = (float) $st->fetchColumn();

        $st = $pdo->prepare("
            SELECT COALESCE(SUM(osi.Quantity), 0)
            FROM order_shop_items osi
            INNER JOIN orders o ON o.OrderID = osi.OrderID AND o.OrderCategoryID = 6
            WHERE o.OrderDate >= ? AND o.OrderDate < ?
        ");
        $st->execute([$gsMonthStart, $gsNextMonth]);
        $mtdUnits = (int) $st->fetchColumn();

        $st = $pdo->prepare("
            SELECT COUNT(DISTINCT o.OrderID)
            FROM orders o
            WHERE o.OrderCategoryID = 6 AND o.OrderDate >= ? AND o.OrderDate < ?
        ");
        $st->execute([$gsMonthStart, $gsNextMonth]);
        $mtdOrders = (int) $st->fetchColumn();

        $lowStock = (int) $pdo->query("
            SELECT COUNT(*) FROM shop_items WHERE StockQty > 0 AND StockQty <= 3
        ")->fetchColumn();

        $giftShopSnapshot = [
            'monthLabel' => date('F Y'),
            'revenue'    => $mtdRevenue,
            'units'      => $mtdUnits,
            'orders'     => $mtdOrders,
            'lowStock'   => $lowStock,
        ];
    } catch (Throwable $e) {
        $giftShopSnapshot = [
            'monthLabel' => date('F Y'),
            'revenue'    => 0.0,
            'units'      => 0,
            'orders'     => 0,
            'lowStock'   => 0,
        ];
    }
}

$restaurantSnapshot = null;
if ($isRestaurantEmployee) {
    $rtMonthStart = date('Y-m-01');
    $rtNextMonth  = date('Y-m-01', strtotime('first day of next month'));
    try {
        $st = $pdo->prepare("
            SELECT COALESCE(SUM(o.TransactionAmount), 0)
            FROM orders o
            WHERE o.OrderCategoryID = 5 AND o.OrderDate >= ? AND o.OrderDate < ?
        ");
        $st->execute([$rtMonthStart, $rtNextMonth]);
        $mtdRevenue = (float) $st->fetchColumn();

        $st = $pdo->prepare("
            SELECT COALESCE(SUM(ofi.Quantity), 0)
            FROM order_food_items ofi
            INNER JOIN orders o ON o.OrderID = ofi.OrderID AND o.OrderCategoryID = 5
            WHERE o.OrderDate >= ? AND o.OrderDate < ?
        ");
        $st->execute([$rtMonthStart, $rtNextMonth]);
        $mtdUnits = (int) $st->fetchColumn();

        $st = $pdo->prepare("
            SELECT COUNT(DISTINCT o.OrderID)
            FROM orders o
            WHERE o.OrderCategoryID = 5 AND o.OrderDate >= ? AND o.OrderDate < ?
        ");
        $st->execute([$rtMonthStart, $rtNextMonth]);
        $mtdOrders = (int) $st->fetchColumn();

        $activeMenuItems = (int) $pdo->query("SELECT COUNT(*) FROM fooditem")->fetchColumn();

        $restaurantSnapshot = [
            'monthLabel' => date('F Y'),
            'revenue'    => $mtdRevenue,
            'units'      => $mtdUnits,
            'orders'     => $mtdOrders,
            'menuItems'  => $activeMenuItems,
        ];
    } catch (Throwable $e) {
        $restaurantSnapshot = [
            'monthLabel' => date('F Y'),
            'revenue'    => 0.0,
            'units'      => 0,
            'orders'     => 0,
            'menuItems'  => 0,
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — Greenwood Zoo</title>
    <link rel="stylesheet" href="style.css">
    <style>
        html, body {
            min-height: 100%;
        }
        body {
            overflow: auto;
            margin: 0;
        }
        .dashboard-wrapper {
            box-sizing: border-box;
            width: 100%;
            min-height: 100vh;
            min-height: 100dvh;
            background-color: rgba(187, 223, 158, 0.95);
            text-align: left;
        }

        .dashboard-inner {
            box-sizing: border-box;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px clamp(12px, 2.4vw, 18px);
        }

        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 20px;
            border-bottom: 3px solid var(--accent-color);
            padding-bottom: 14px;
            flex-wrap: wrap;
        }
        .dashboard-header h1 {
            margin: 0;
            font-size: clamp(1.35rem, 2.5vw, 1.75rem);
            font-weight: 800;
            color: var(--text-color);
        }
        .dashboard-header .dash-meta {
            margin: 6px 0 0;
            font-size: 0.9rem;
            color: #666;
            font-weight: 500;
        }
        .dashboard-header-actions {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .dashboard-header-actions .user-name {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-color);
        }
        .secondary-nav-btn {
            padding: 9px 18px;
            background-color: var(--base-color);
            border: 2px solid var(--accent-color);
            border-radius: 1000px;
            font: inherit;
            font-weight: 600;
            font-size: 0.88rem;
            color: var(--text-color);
            text-decoration: none;
        }
        .secondary-nav-btn:hover {
            background-color: var(--accent-color);
            text-decoration: none;
        }
        
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
            display: inline-block;
        }
        .logout-btn:hover {
            background-color: var(--text-color);
            color: white;
            text-decoration: none;
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 14px;
            margin-bottom: 22px;
        }
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 18px;
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.05);
            border-left: 4px solid var(--accent-color);
        }
        .stat-card.warning { border-left-color: #e74c3c; }
        .stat-card.info    { border-left-color: #3498db; }
        .stat-card.money   { border-left-color: #27ae60; }
        .stat-label {
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #666;
            font-weight: 600;
            margin-bottom: 8px;
        }
        .stat-value {
            font-size: 1.85rem;
            font-weight: 800;
            color: var(--text-color);
            line-height: 1.1;
        }
        .stat-value.warning { color: #e74c3c; }

        .section-title {
            font-size: 1rem;
            margin: 22px 0 10px;
            border-bottom: 1px solid #e0e0e0;
            padding-bottom: 6px;
            color: var(--text-color);
            font-weight: 700;
        }
        .section-title:first-of-type { margin-top: 0; }

        .tiles-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 12px;
        }
        .tile {
            background: white;
            border-radius: 12px;
            padding: 16px 18px;
            text-decoration: none;
            color: var(--text-color);
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            gap: 14px;
            transition: transform 120ms ease, box-shadow 120ms ease;
            border: 2px solid transparent;
        }
        .tile:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 14px rgba(23, 103, 7, 0.12);
            border-color: var(--accent-color);
            text-decoration: none;
        }
        .tile-icon {
            font-size: 1.5rem;
            width: 42px;
            height: 42px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--base-color);
            border-radius: 10px;
            flex-shrink: 0;
        }
        .tile-text strong {
            display: block;
            font-size: 0.9rem;
            font-weight: 700;
            margin-bottom: 3px;
        }
        .tile-text span {
            font-size: 0.8rem;
            color: #666;
        }
        .tile.alert-tile {
            border-color: #e74c3c;
            background: #fff8f8;
        }
        .tile.alert-tile .tile-icon { background: #fde8e8; }
        .attention-banner {
            border: 2px solid #e46a5d;
            border-radius: 14px;
            background: #fff;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
            margin-bottom: 14px;
            padding: 10px 12px;
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: var(--text-color);
        }
        .attention-banner:hover {
            text-decoration: none;
            border-color: #d4574a;
            background: #fffdfd;
        }
        .attention-banner .attention-icon {
            width: 34px;
            height: 34px;
            border-radius: 9px;
            background: #fde8e8;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 1.05rem;
        }
        .attention-banner strong {
            display: block;
            font-size: 0.95rem;
            font-weight: 800;
            line-height: 1.15;
            margin-bottom: 2px;
            color: #185f1e;
        }
        .attention-banner span {
            display: block;
            font-size: 0.82rem;
            color: #666;
            line-height: 1.2;
        }
        .gift-shop-snapshot {
            margin-top: 22px;
            background: #fff;
            border-radius: 14px;
            padding: 18px 20px 16px;
            box-shadow: 0 3px 10px rgba(0,0,0,.06);
            border: 1px solid rgba(46, 90, 26, 0.12);
        }
        .gift-shop-snapshot h2 {
            margin: 0 0 4px;
            font-size: 1.05rem;
            font-weight: 800;
            color: var(--text-color);
        }
        .gift-shop-snapshot .snapshot-sub {
            margin: 0 0 14px;
            font-size: 0.85rem;
            color: #666;
        }
        .gift-shop-snapshot-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(148px, 1fr));
            gap: 12px;
        }
        .gift-shop-snapshot .snap-card {
            background: #f7fbf4;
            border: 1px solid rgba(46, 90, 26, 0.12);
            border-radius: 10px;
            padding: 12px 14px;
        }
        .gift-shop-snapshot .snap-label {
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #666;
            font-weight: 600;
            margin-bottom: 6px;
        }
        .gift-shop-snapshot .snap-value {
            font-size: 1.35rem;
            font-weight: 800;
            color: var(--text-color);
            line-height: 1.1;
        }
        .gift-shop-snapshot .snap-value.warn { color: #c0392b; }
        .gift-shop-snapshot .snap-foot {
            margin-top: 14px;
            font-size: 0.88rem;
            color: #555;
            line-height: 1.45;
        }
        .gift-shop-snapshot .snap-foot a {
            font-weight: 700;
            color: var(--accent-color);
        }
        .gift-shop-admin-section {
            margin-top: 12px;
        }
    </style>
</head>
<body>
<div class="dashboard-wrapper">
<div class="dashboard-inner">

    <div class="dashboard-header">
        <div>
            <h1><?php
            if ($isAdmin) echo 'Admin dashboard';
            elseif ($isVet) echo 'Veterinarian dashboard';
            elseif ($isCaretaker) echo 'Caretaker dashboard';
            elseif ($isGiftShopEmployee) echo 'Gift shop dashboard';
            elseif ($isRestaurantEmployee) echo 'Restaurant dashboard';
            elseif ($isCashier) echo 'Cashier dashboard';
            else echo 'Dashboard';
            ?></h1>
            <p class="dash-meta"><?= date('l, F j, Y') ?></p>
        </div>
        <div class="dashboard-header-actions">
            <span class="user-name"><?= htmlspecialchars($firstname) ?></span>
            <a href="change-password.php" class="secondary-nav-btn">🔒 Change Password</a>

            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </div>

        <!-- Stats Row (adminonly) -->
        <?php if ($isAdmin): ?>
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-label">🐾 Total Animals</div>
                <div class="stat-value"><?= $totalAnimals ?></div>
            </div>
            <div class="stat-card info">
                <div class="stat-label">Employees</div>
                <div class="stat-value"><?= $totalEmployees ?></div>
            </div>
            <div class="stat-card <?= $pendingHealth > 0 ? 'warning' : '' ?>">
                <div class="stat-label">Health Alerts</div>
                <div class="stat-value <?= $pendingHealth > 0 ? 'warning' : '' ?>"><?= $pendingHealth ?></div>
            </div>
            <div class="stat-card money">
                <div class="stat-label">Today's Revenue</div>
                <div class="stat-value">$<?= number_format($todayRevenue, 0) ?></div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($pendingHealth > 0 && ($isAdmin || $isVet || $isCaretaker)): ?>
        <!-- Health Alert (animal care roles only) -->
        <a href="health-reports.php" class="attention-banner">
            <div class="attention-icon">🚨</div>
            <div class="tile-text">
                <strong><?= $pendingHealth ?> animal(s) need attention</strong>
                <span>Click to view health report</span>
            </div>
        </a>
        <?php endif; ?>
        <?php if ($isAdmin): ?>
        <a href="shop_alerts.php" class="attention-banner">
            <div class="attention-icon">📦</div>
            <div class="tile-text">
                <strong>
                    <?= (int)$pendingRestock > 0
                        ? $pendingRestock . ' gift shop item(s) need restocking'
                        : 'Gift shop stock status is clear' ?>
                </strong>
                <?php if ((int)$pendingRestock <= 0): ?>
                    <span>No gift shop items currently at low stock</span>
                <?php endif; ?>
            </div>
        </a>
        <a href="restaurant_alerts.php" class="attention-banner">
            <div class="attention-icon">🍽️</div>
            <div class="tile-text">
                <strong>
                    <?= (int)$pendingRestaurantRestock > 0
                        ? $pendingRestaurantRestock . ' restaurant item(s) need restocking'
                        : 'Restaurant stock status is clear' ?>
                </strong>
                <?php if ((int)$pendingRestaurantRestock <= 0): ?>
                    <span>No restaurant items currently at low stock</span>
                <?php endif; ?>
            </div>
        </a>
        <?php endif; ?>

        <!-- Animals Section -->
        <?php if ($isAdmin || $isCaretaker || $isVet): ?>
        <div class="section-title">Animals & Enclosures</div>
        <div class="tiles-grid">
            <?php if ($isAdmin || $isCaretaker): ?>
            <a href="add-animal.php" class="tile">
                <div class="tile-icon">➕</div>
                <div class="tile-text"><strong>Add Animal</strong><span>Register new animal</span></div>
            </a>
            <?php endif; ?>
            <a href="animals_report.php" class="tile">
                <div class="tile-text"><strong>Animals Report</strong><span>Search & filter animals</span></div>
            </a>
            <?php if ($isAdmin || $isVet): ?>
            <a href="health-reports.php" class="tile">
                <div class="tile-text"><strong>Health Records</strong><span>Medical history</span></div>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Staff Section -->
        <?php if ($isAdmin): ?>
        <div class="section-title">Staff Management</div>
        <div class="tiles-grid">
            <a href="add-employee.php" class="tile">
                <div class="tile-text"><strong>Add Employee</strong><span>Register new staff</span></div>
            </a>
            <a href="employees_report.php" class="tile">
                <div class="tile-text"><strong>Employees Report</strong><span>Filter by role & salary</span></div>
            </a>
        </div>
        <?php endif; ?>

        <?php if ($isAdmin || $isCashier): ?>
        <div class="section-title">Revenue & Tickets</div>
        <div class="tiles-grid">
            <?php if ($isAdmin || $isCashier): ?>
            <a href="add-ticket.php" class="tile">
                <div class="tile-text"><strong>Add Ticket</strong><span>Create new ticket</span></div>
            </a>
            <?php endif; ?>
            <?php if ($isAdmin): ?>
            <a href="revenue_report.php" class="tile">
                <div class="tile-text"><strong>Revenue Report</strong><span>Daily financial summary</span></div>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($isAdmin): ?>
        <section id="gift-shop-admin" class="gift-shop-admin-section">
        <div class="section-title">Gift Shop</div>
        <div class="tiles-grid">
            <a href="add-gift-shop-item.php" class="tile">
                <div class="tile-text"><strong>Add item</strong><span>Add new product</span></div>
            </a>
            <a href="shop_alerts.php" class="tile">
                <div class="tile-text"><strong>Shop restock alerts</strong><span>Low stock warnings</span></div>
            </a>
        </div>
        </section>
        <section id="restaurant-shop-admin" class="gift-shop-admin-section">
        <div class="section-title">Restaurant Shop</div>
        <div class="tiles-grid">
            <a href="add-restaurant-item.php" class="tile">
                <div class="tile-text"><strong>Add item</strong><span>Add new food item</span></div>
            </a>
            <a href="restaurant_alerts.php" class="tile">
                <div class="tile-text"><strong>Restaurant restock alerts</strong><span>Low stock warnings</span></div>
            </a>
        </div>
        </section>
        <?php endif; ?>

        <!-- Gift Shop Section (login role must be exactly "Gift Shop Employee" on systemuser) -->
        <?php if ($isGiftShopEmployee): ?>
        <section id="gift-shop" class="gift-shop-staff-section">
        <?php if ($pendingRestock > 0): ?>
        <a href="shop_alerts.php" class="attention-banner">
            <div class="attention-icon">📦</div>
            <div class="tile-text">
                <strong><?= $pendingRestock ?> gift shop item(s) need restocking now</strong>
            </div>
        </a>
        <?php endif; ?>
        <?php if ($isGiftShopEmployee && $giftShopSnapshot !== null): ?>
        <div class="gift-shop-snapshot" aria-labelledby="gift-shop-snapshot-heading">
            <h2 id="gift-shop-snapshot-heading"><?= htmlspecialchars($giftShopSnapshot['monthLabel']) ?></h2>
            <div class="gift-shop-snapshot-grid">
                <div class="snap-card">
                    <div class="snap-label">Gift shop revenue</div>
                    <div class="snap-value">$<?= number_format($giftShopSnapshot['revenue'], 2) ?></div>
                </div>
                <div class="snap-card">
                    <div class="snap-label">Units sold</div>
                    <div class="snap-value"><?= (int) $giftShopSnapshot['units'] ?></div>
                </div>
                <div class="snap-card">
                    <div class="snap-label">Orders</div>
                    <div class="snap-value"><?= (int) $giftShopSnapshot['orders'] ?></div>
                </div>
                <div class="snap-card">
                    <div class="snap-label">Low stock (≤3)</div>
                    <div class="snap-value <?= $giftShopSnapshot['lowStock'] > 0 ? 'warn' : '' ?>"><?= (int) $giftShopSnapshot['lowStock'] ?></div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <div class="tiles-grid" style="margin-top:12px;">
            <a href="add-gift-shop-item.php" class="tile">
                <div class="tile-text"><strong>Add item</strong><span>Add new product</span></div>
            </a>
            <a href="giftshop.php?preview=1" class="tile">
                <div class="tile-text"><strong>View shop</strong><span>Open customer gift shop view</span></div>
            </a>
            <a href="add-order.php" class="tile">
                <div class="tile-text"><strong>Record sale</strong><span>Log a customer gift shop purchase</span></div>
            </a>
            <a href="sales_report.php" class="tile">
                <div class="tile-text"><strong>Gift Shop Sales report</strong><span>Line items, filters &amp; chart</span></div>
            </a>
            <a href="shop_alerts.php" class="tile">
                <div class="tile-text"><strong>Shop restock alerts</strong><span>Low stock warnings</span></div>
            </a>
        </div>
        </section>
        <?php endif; ?>

        <?php if ($isRestaurantEmployee): ?>
        <section id="restaurant-staff" class="restaurant-staff-section">
        <?php if ($pendingRestaurantRestock > 0): ?>
        <a href="restaurant_alerts.php" class="attention-banner">
            <div class="attention-icon">🍽️</div>
            <div class="tile-text">
                <strong><?= $pendingRestaurantRestock ?> restaurant item(s) need restocking</strong>
            </div>
        </a>
        <?php endif; ?>
        <?php if ($restaurantSnapshot !== null): ?>
        <div class="gift-shop-snapshot" aria-labelledby="restaurant-snapshot-heading">
            <h2 id="restaurant-snapshot-heading"><?= htmlspecialchars($restaurantSnapshot['monthLabel']) ?></h2>
            <div class="gift-shop-snapshot-grid">
                <div class="snap-card">
                    <div class="snap-label">Restaurant revenue</div>
                    <div class="snap-value">$<?= number_format($restaurantSnapshot['revenue'], 2) ?></div>
                </div>
                <div class="snap-card">
                    <div class="snap-label">Units sold</div>
                    <div class="snap-value"><?= (int) $restaurantSnapshot['units'] ?></div>
                </div>
                <div class="snap-card">
                    <div class="snap-label">Orders</div>
                    <div class="snap-value"><?= (int) $restaurantSnapshot['orders'] ?></div>
                </div>
                <div class="snap-card">
                    <div class="snap-label">Active menu items</div>
                    <div class="snap-value"><?= (int) $restaurantSnapshot['menuItems'] ?></div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <div class="tiles-grid" style="margin-top:12px;">
            <a href="add-restaurant-item.php" class="tile">
                <div class="tile-text"><strong>Add item</strong><span>Add new food item</span></div>
            </a>
            <a href="add-restaurant-order.php" class="tile">
                <div class="tile-text"><strong>Record sale</strong><span>Log a customer restaurant purchase</span></div>
            </a>
            <a href="restaurant.php" class="tile">
                <div class="tile-text"><strong>Restaurant menu</strong><span>Open current menu and stall view</span></div>
            </a>
            <a href="restaurant_sales_report.php" class="tile">
                <div class="tile-text"><strong>Restaurant sales report</strong><span>Food sales totals and item-level breakdown</span></div>
            </a>
            <a href="restaurant_alerts.php" class="tile">
                <div class="tile-text"><strong>Restaurant restock alerts</strong><span>Items at low stock (≤3) or out of stock</span></div>
            </a>
        </div>
        </section>
        <?php endif; ?>

</div>
</div>
</body>
</html>
