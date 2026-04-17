<?php
session_start();
if (!isset($_SESSION['customer_id'])) {
    header('Location: sign-in.html');
    exit;
}
require_once 'db.php';

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = ['food' => [], 'ticket' => []];
}
$_SESSION['cart']['shop'] = [];

$foodItems = [];
$foodTotal = 0;
if (!empty($_SESSION['cart']['food'])) {
    $ids   = implode(',', array_map('intval', array_keys($_SESSION['cart']['food'])));
    $rows  = $pdo->query("SELECT FoodID, FoodName, Price FROM fooditem WHERE FoodID IN ($ids)")->fetchAll();
    foreach ($rows as $r) {
        $qty = $_SESSION['cart']['food'][$r['FoodID']] ?? 0;
        $r['qty']      = $qty;
        $r['subtotal'] = $r['Price'] * $qty;
        $foodTotal    += $r['subtotal'];
        $foodItems[]   = $r;
    }
}

$ticketItems = [];
$ticketTotal = 0;
if (!empty($_SESSION['cart']['ticket'])) {
    foreach ($_SESSION['cart']['ticket'] as $key => $t) {
        $stmt = $pdo->prepare("SELECT CategoryName, Price FROM ordercategories WHERE OrderCategoryID = ?");
        $stmt->execute([$t['category_id']]);
        $cat = $stmt->fetch();
        if ($cat) {
            $subtotal      = $cat['Price'] * $t['qty'];
            $ticketTotal  += $subtotal;
            $ticketItems[] = [
                'key'          => $key,
                'category_id'  => $t['category_id'],
                'CategoryName' => $cat['CategoryName'],
                'Price'        => $cat['Price'],
                'qty'          => $t['qty'],
                'visit_date'   => $t['visit_date'],
                'subtotal'     => $subtotal,
            ];
        }
    }
}

$grandTotal = $foodTotal + $ticketTotal;
$cartCount  = array_sum($_SESSION['cart']['food'])
            + array_sum(array_column($_SESSION['cart']['ticket'], 'qty'));
$isEmpty    = $cartCount === 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Cart — Greenwood Zoo</title>
    <link rel="stylesheet" href="index.css">
    <style>
        .cart-layout { display:grid; grid-template-columns:1fr 320px; gap:1.5rem; align-items:start; }
        @media(max-width:860px){ .cart-layout { grid-template-columns:1fr; } }

        .section-title { font-size:.75rem; text-transform:uppercase; letter-spacing:.07em; color:var(--cr-muted); font-weight:600; margin:0 0 .75rem; }

        .cart-section { margin-bottom:1.75rem; }
        .cart-section-header {
            display:flex; align-items:center; gap:.6rem;
            padding:.75rem 1rem; background:var(--cr-surface);
            border:1px solid var(--cr-border); border-radius:12px 12px 0 0;
            border-bottom:none; font-weight:700; font-size:.95rem; color:var(--cr-text);
        }
        .cart-section-header .icon { font-size:1.1rem; }

        .cart-table { width:100%; border-collapse:collapse; background:var(--cr-surface); border:1px solid var(--cr-border); border-radius:0 0 12px 12px; overflow:hidden; }
        .cart-table th { background:linear-gradient(180deg,#eef6ea,#e4f0de); color:var(--cr-accent); font-weight:700; font-size:.82rem; text-transform:uppercase; letter-spacing:.05em; padding:.65rem 1rem; text-align:left; border-bottom:1px solid var(--cr-border); }
        .cart-table td { padding:.75rem 1rem; border-bottom:1px solid #eef2eb; vertical-align:middle; font-size:.9rem; }
        .cart-table tr:last-child td { border-bottom:none; }
        .cart-table tr:hover td { background:#fafcf8; }

        .qty-form { display:flex; align-items:center; gap:.4rem; }
        .qty-input { width:52px; padding:.35rem .5rem; border:1px solid var(--cr-border); border-radius:6px; font:inherit; font-size:.88rem; text-align:center; }
        .qty-input:focus { outline:none; border-color:var(--cr-accent-soft); }
        .update-btn { padding:.35rem .75rem; background:var(--cr-accent); color:white; border:none; border-radius:6px; font:inherit; font-size:.8rem; font-weight:600; cursor:pointer; }
        .update-btn:hover { background:#1a5c2b; }

        .remove-btn { padding:.3rem .7rem; background:#fee2e2; color:#991b1b; border:none; border-radius:6px; font:inherit; font-size:.8rem; font-weight:600; cursor:pointer; transition:background .15s; }
        .remove-btn:hover { background:#fca5a5; }

        .subtotal { font-weight:700; color:var(--cr-accent); }

        /* Summary card */
        .summary-card { background:var(--cr-surface); border:1px solid var(--cr-border); border-radius:16px; padding:1.5rem; box-shadow:0 4px 24px rgba(26,46,22,.08); position:sticky; top:1rem; }
        .summary-card h2 { font-size:1rem; font-weight:700; margin:0 0 1.25rem; padding-bottom:.75rem; border-bottom:1px solid var(--cr-border); }
        .summary-row { display:flex; justify-content:space-between; font-size:.9rem; margin-bottom:.6rem; }
        .summary-row.total { font-weight:700; font-size:1.05rem; padding-top:.75rem; border-top:2px solid var(--cr-border); margin-top:.5rem; color:var(--cr-text); }
        .checkout-btn { display:block; width:100%; padding:.85rem; background:var(--cr-accent); color:white; border:none; border-radius:999px; font:inherit; font-weight:700; font-size:1rem; cursor:pointer; text-align:center; text-decoration:none; margin-top:1.25rem; transition:background .15s; }
        .checkout-btn:hover { background:#1a5c2b; text-decoration:none; }
        .checkout-btn:disabled, .checkout-btn.disabled { background:#ccc; cursor:not-allowed; }

        .continue-links { margin-top:1rem; display:flex; flex-direction:column; gap:.4rem; }
        .continue-links a { color:var(--cr-muted); font-size:.85rem; font-weight:600; text-decoration:none; }
        .continue-links a:hover { color:var(--cr-accent); }

        .empty-state { text-align:center; padding:4rem 1rem; color:var(--cr-muted); }
        .empty-state .emoji { font-size:3rem; display:block; margin-bottom:1rem; }
        .empty-state h2 { font-size:1.2rem; margin-bottom:.5rem; color:var(--cr-text); }
        .empty-state p { margin-bottom:1.5rem; }
        .shop-links { display:flex; flex-wrap:wrap; gap:.75rem; justify-content:center; }
        .shop-link { padding:.65rem 1.5rem; background:var(--cr-accent); color:white; border-radius:999px; font-weight:600; font-size:.9rem; text-decoration:none; }
        .shop-link:hover { background:#1a5c2b; text-decoration:none; }
        .shop-link.outline { background:transparent; border:1px solid var(--cr-accent); color:var(--cr-accent); }
        .shop-link.outline:hover { background:#eef6ea; }
    </style>
</head>
<body>
    <header class="site-header">
        <a class="logo" href="index.php">Greenwood Zoo</a>
        <nav aria-label="Main">
            <ul class="nav-links">
                <li><a href="index.php">Home</a></li>
                <li><a href="customer-dashboard.php">Dashboard</a></li>
                <li><a href="restaurant.php">Restaurant</a></li>
                <li><a href="buy_tickets.php">Buy tickets</a></li>
                <li><a href="giftshop.php">Gift shop</a></li>
                <li>
                    <a href="cart.php" class="nav-cart-link">🛒 Cart<?php if ($cartCount > 0): ?><span class="nav-cart-badge" id="cart-count"><?= (int) $cartCount ?></span><?php endif; ?></a>
                </li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </nav>
    </header>

    <main>
        <div class="shop-page-header">
            <h1>Your cart</h1>
            <p>Review tickets and restaurant items before checkout.</p>
        </div>

        <?php if ($isEmpty): ?>
        <div class="empty-state">
            <span class="emoji">🛒</span>
            <h2>Your cart is empty</h2>
            <p>Browse our restaurant or buy tickets to get started.</p>
            <div class="shop-links">
                <a href="restaurant.php"  class="shop-link">🍽️ Restaurant</a>
                <a href="buy_tickets.php" class="shop-link outline">🎟️ Tickets</a>
            </div>
        </div>
        <?php else: ?>

        <div class="cart-layout">
            <div>
                <?php if (!empty($ticketItems)): ?>
                <div class="cart-section">
                    <div class="cart-section-header"><span class="icon">🎟️</span> Zoo Tickets</div>
                    <table class="cart-table">
                        <thead><tr><th>Type</th><th>Visit Date</th><th>Price</th><th>Qty</th><th>Subtotal</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($ticketItems as $t): ?>
                        <tr>
                            <td><?= htmlspecialchars($t['CategoryName']) ?></td>
                            <td><?= date('M j, Y', strtotime($t['visit_date'])) ?></td>
                            <td>$<?= number_format($t['Price'], 2) ?></td>
                            <td>
                                <form class="qty-form" method="POST" action="cart_action.php">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="type"   value="ticket">
                                    <input type="hidden" name="key"    value="<?= htmlspecialchars($t['key']) ?>">
                                    <input type="hidden" name="redirect" value="cart.php">
                                    <input class="qty-input" type="number" name="qty" value="<?= $t['qty'] ?>" min="1" max="20">
                                    <button class="update-btn" type="submit">↻</button>
                                </form>
                            </td>
                            <td class="subtotal">$<?= number_format($t['subtotal'], 2) ?></td>
                            <td>
                                <form method="POST" action="cart_action.php">
                                    <input type="hidden" name="action"   value="remove">
                                    <input type="hidden" name="type"     value="ticket">
                                    <input type="hidden" name="key"      value="<?= htmlspecialchars($t['key']) ?>">
                                    <input type="hidden" name="redirect" value="cart.php">
                                    <button class="remove-btn" type="submit">Remove</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>

                <?php if (!empty($foodItems)): ?>
                <div class="cart-section">
                    <div class="cart-section-header"><span class="icon">🍽️</span> Restaurant</div>
                    <table class="cart-table">
                        <thead><tr><th>Item</th><th>Price</th><th>Qty</th><th>Subtotal</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($foodItems as $f): ?>
                        <tr>
                            <td><?= htmlspecialchars($f['FoodName']) ?></td>
                            <td>$<?= number_format($f['Price'], 2) ?></td>
                            <td>
                                <form class="qty-form" method="POST" action="cart_action.php">
                                    <input type="hidden" name="action"   value="update">
                                    <input type="hidden" name="type"     value="food">
                                    <input type="hidden" name="id"       value="<?= $f['FoodID'] ?>">
                                    <input type="hidden" name="redirect" value="cart.php">
                                    <input class="qty-input" type="number" name="qty" value="<?= $f['qty'] ?>" min="1" max="20">
                                    <button class="update-btn" type="submit">↻</button>
                                </form>
                            </td>
                            <td class="subtotal">$<?= number_format($f['subtotal'], 2) ?></td>
                            <td>
                                <form method="POST" action="cart_action.php">
                                    <input type="hidden" name="action"   value="remove">
                                    <input type="hidden" name="type"     value="food">
                                    <input type="hidden" name="id"       value="<?= $f['FoodID'] ?>">
                                    <input type="hidden" name="redirect" value="cart.php">
                                    <button class="remove-btn" type="submit">Remove</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>

                <form method="POST" action="cart_action.php" style="margin-top:.5rem">
                    <input type="hidden" name="action"   value="clear">
                    <input type="hidden" name="redirect" value="cart.php">
                    <button type="submit" style="background:none;border:none;color:var(--cr-muted);font:inherit;font-size:.85rem;font-weight:600;cursor:pointer;text-decoration:underline;padding:0"
                        onclick="return confirm('Clear your entire cart?')">Clear cart</button>
                </form>
            </div>

            <!-- Order Summary -->
            <div>
                <div class="summary-card">
                    <h2>Order summary</h2>
                    <?php if ($ticketTotal > 0): ?>
                    <div class="summary-row"><span>Tickets</span><span>$<?= number_format($ticketTotal, 2) ?></span></div>
                    <?php endif; ?>
                    <?php if ($foodTotal > 0): ?>
                    <div class="summary-row"><span>Restaurant</span><span>$<?= number_format($foodTotal, 2) ?></span></div>
                    <?php endif; ?>
                    <div class="summary-row total"><span>Total</span><span>$<?= number_format($grandTotal, 2) ?></span></div>
                    <a href="checkout.php" class="checkout-btn">Proceed to checkout →</a>
                    <div class="continue-links">
                        <a href="restaurant.php">+ Add food items</a>
                        <a href="buy_tickets.php">+ Add tickets</a>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </main>
</body>
</html>
