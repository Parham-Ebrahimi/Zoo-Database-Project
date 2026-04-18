<?php
session_start();
if (!isset($_SESSION['customer_id'])) {
    header('Location: login.html');
    exit;
}
require_once 'db.php';

$flash = '';
if (isset($_GET['added'])) {
    $flash = 'Item added to your cart.';
}

$itemsStmt = $pdo->query("
    SELECT si.ShopItemID, si.ItemName, si.Price, si.StockQty, s.ShopName
    FROM shop_items si
    JOIN shops s ON s.ShopID = si.ShopID
    ORDER BY s.ShopName, si.ItemName
");
$items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

$cartShop = [];
if (isset($_SESSION['cart']['shop']) && is_array($_SESSION['cart']['shop'])) {
    $cartShop = $_SESSION['cart']['shop'];
}
$cartCount = array_sum($cartShop);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gift Shop - Greenwood Zoo</title>
    <link rel="stylesheet" href="index.css">
    <style>
        .shop-wrap {
            max-width: 1100px;
            margin: 1.5rem auto 2rem;
            padding: 0 1rem;
        }
        .shop-panel {
            background: var(--surface);
            border-radius: 18px;
            box-shadow: var(--shadow);
            padding: 1.35rem;
            margin-bottom: 1rem;
        }
        .shop-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1rem;
        }
        .shop-card {
            background: #fff;
            border-radius: 12px;
            border: 1px solid rgba(23, 103, 7, 0.12);
            padding: 1rem;
            text-align: left;
        }
        .shop-name {
            font-size: 0.82rem;
            color: #2d7d23;
            font-weight: 600;
            margin-bottom: 0.2rem;
        }
        .item-name {
            font-size: 1.05rem;
            font-weight: 700;
            margin-bottom: 0.4rem;
        }
        .meta {
            font-size: 0.9rem;
            margin-bottom: 0.6rem;
        }
        .stock-ok { color: #1e7a16; }
        .stock-low { color: #9a6700; }
        .stock-out { color: #b50000; font-weight: 600; }
        .buy-form {
            display: grid;
            grid-template-columns: 1fr;
            gap: 0.45rem;
        }
        .buy-form input,
        .buy-form button {
            width: 100%;
            padding: 0.5rem 0.65rem;
            border-radius: 8px;
            border: 1px solid #c7d9bf;
            font: inherit;
        }
        .buy-form button {
            border: none;
            background: var(--accent-color);
            color: #fff;
            font-weight: 600;
            cursor: pointer;
        }
        .buy-form button:disabled {
            background: #b4b4b4;
            cursor: not-allowed;
        }
        .notice {
            margin-bottom: 0.8rem;
            padding: 0.7rem 0.9rem;
            border-radius: 8px;
            font-size: 0.92rem;
        }
        .ok { background: #ecf9e8; color: #205f18; }
        .cart-link {
            font-weight: 700;
            color: var(--accent-color);
            text-decoration: none;
        }
        .cart-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <header class="site-header">
        <a class="logo" href="index.php">Greenwood Zoo</a>
        <nav aria-label="Main">
            <ul class="nav-links">
                <li><a href="customer-dashboard.php">Dashboard</a></li>
                <li><a href="buy_tickets.php">Buy Tickets</a></li>
                <li><a href="cart.php">Cart<?= $cartCount > 0 ? ' (' . (int) $cartCount . ')' : '' ?></a></li>
                <li><a href="giftshop.php">Gift Shop</a></li>
                <li><a href="customer_profile.php">Profile</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </nav>
    </header>

    <main class="shop-wrap">
        <section class="shop-panel">
            <h1>Gift Shop</h1>
            <p>Add souvenirs to your cart, then open <a class="cart-link" href="cart.php">your cart</a> to review and pay.</p>
            <?php if ($flash !== ''): ?>
                <div class="notice ok"><?= htmlspecialchars($flash) ?></div>
            <?php endif; ?>
        </section>

        <section class="shop-grid" aria-label="Gift shop items">
            <?php foreach ($items as $item): ?>
                <?php
                    $stock = (int) $item['StockQty'];
                    $stockClass = $stock <= 0 ? 'stock-out' : ($stock <= 3 ? 'stock-low' : 'stock-ok');
                ?>
                <article class="shop-card">
                    <div class="shop-name"><?= htmlspecialchars($item['ShopName']) ?></div>
                    <div class="item-name"><?= htmlspecialchars($item['ItemName']) ?></div>
                    <div class="meta">$<?= number_format((float) $item['Price'], 2) ?></div>
                    <div class="meta <?= $stockClass ?>">
                        Stock: <?= $stock <= 0 ? 'Out of stock' : $stock ?>
                    </div>
                    <form class="buy-form" method="POST" action="cart_action.php">
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="type" value="shop">
                        <input type="hidden" name="id" value="<?= (int) $item['ShopItemID'] ?>">
                        <input type="hidden" name="redirect" value="giftshop.php?added=1">
                        <label>
                            Quantity
                            <input type="number" name="qty" min="1" max="<?= max(1, $stock) ?>" value="1" <?= $stock <= 0 ? 'disabled' : '' ?>>
                        </label>
                        <button type="submit" <?= $stock <= 0 ? 'disabled' : '' ?>>Add to cart</button>
                    </form>
                </article>
            <?php endforeach; ?>
        </section>
    </main>
</body>
</html>
