<?php
require_once __DIR__ . '/session_bootstrap.php';

$role = $_SESSION['role'] ?? '';
$isAdminStaffCart = ($role === 'admin' && !empty($_SESSION['user_id']) && !isset($_SESSION['customer_id']));

if (!isset($_SESSION['customer_id'])) {
    if ($isAdminStaffCart) {
        // Admin can review session cart without a linked customer account.
    } elseif (!empty($_SESSION['user_id'])) {
        header('Location: dashboard.php');
        exit;
    } else {
        header('Location: login.html');
        exit;
    }
}
require_once 'db.php';

$canCheckout = isset($_SESSION['customer_id']);

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = ['food' => [], 'ticket' => [], 'shop' => []];
} elseif (!isset($_SESSION['cart']['shop']) || !is_array($_SESSION['cart']['shop'])) {
    $_SESSION['cart']['shop'] = [];
}

// ── Load gift shop items in cart ─────────────────────────────────
$shopItems = [];
$shopTotal = 0;
if (!empty($_SESSION['cart']['shop'])) {
    $ids = implode(',', array_map('intval', array_keys($_SESSION['cart']['shop'])));
    if ($ids !== '') {
        $rows = $pdo->query("
            SELECT si.ShopItemID, si.ItemName, si.Price, si.StockQty, s.ShopName
            FROM shop_items si
            JOIN shops s ON s.ShopID = si.ShopID
            WHERE si.ShopItemID IN ($ids)
        ")->fetchAll();
        foreach ($rows as $r) {
            $qty = (int) ($_SESSION['cart']['shop'][$r['ShopItemID']] ?? 0);
            if ($qty < 1) {
                continue;
            }
            $maxStock = (int) $r['StockQty'];
            $qty = min($qty, max(0, $maxStock));
            if ($qty < 1) {
                unset($_SESSION['cart']['shop'][$r['ShopItemID']]);
                continue;
            }
            $_SESSION['cart']['shop'][$r['ShopItemID']] = $qty;
            $r['qty'] = $qty;
            $r['subtotal'] = (float) $r['Price'] * $qty;
            $shopTotal += $r['subtotal'];
            $shopItems[] = $r;
        }
    }
}

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

$grandTotal = $foodTotal + $ticketTotal + $shopTotal;
$cartCount  = array_sum($_SESSION['cart']['food'])
            + array_sum(array_column($_SESSION['cart']['ticket'], 'qty'))
            + array_sum($_SESSION['cart']['shop']);
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

        .section-title { 
            font-size:.75rem; 
            text-transform:uppercase; 
            letter-spacing:.07em; 
            color:var(--cr-muted); 
            font-weight:600; 
            margin:0 0 .75rem; 
        }

        .cart-section { margin-bottom:1.75rem; }
        .cart-section-header {
            display:flex; align-items:center; gap:.6rem;
            padding:.75rem 1rem; background:var(--cr-surface);
            border:1px solid var(--cr-border); border-radius:12px 12px 0 0;
            border-bottom:none; font-weight:700; font-size:.95rem; color:var(--cr-text);
        }
        .cart-section-header .icon { font-size:1.1rem; }

        .cart-table { 
            width:100%; 
            border-collapse:collapse; 
            background:var(--cr-surface); 
            border:1px solid var(--cr-border); 
            border-radius:0 0 12px 12px; 
            overflow:hidden; 
        }
        .cart-table th { 
            background:linear-gradient(180deg,#eef6ea,#e4f0de); 
            color:var(--cr-accent); 
            font-weight:700; 
            font-size:.82rem; 
            text-transform:uppercase; 
            letter-spacing:.05em; 
            padding:.65rem 1rem; 
            text-align:left; 
            border-bottom:1px solid var(--cr-border); 
        }
        .cart-table td { padding:.75rem 1rem; border-bottom:1px solid #eef2eb; vertical-align:middle; font-size:.9rem; }
        .cart-table tr:last-child td { border-bottom:none; }
        .cart-table tr:hover td { background:#fafcf8; }

        .qty-form { display:flex; align-items:center; gap:.4rem; }
        .qty-input { 
            width:52px; 
            padding:.35rem .5rem; 
            border:1px solid var(--cr-border); 
            border-radius:6px; 
            font:inherit; 
            font-size:.88rem; 
            text-align:center; 
        }
        .qty-input:focus { outline:none; border-color:var(--cr-accent-soft); }
        .update-btn { 
            padding:.35rem .75rem; 
            background:var(--cr-accent); 
            color:white; 
            border:none; 
            border-radius:6px; 
            font:inherit; 
            font-size:.8rem; 
            font-weight:600; 
            cursor:pointer; 
        }
        .update-btn:hover { background:#1a5c2b; }

        .remove-btn { 
            padding:.3rem .7rem; 
            background:#fee2e2; 
            color:#991b1b; 
            border:none; 
            border-radius:6px; 
            font:inherit; 
            font-size:.8rem; 
            font-weight:600; 
            cursor:pointer; 
            transition:background .15s;
        }
        .remove-btn:hover { background:#fca5a5; }

        .subtotal { font-weight:700; color:var(--cr-accent); }

        .summary-card { 
            background:var(--cr-surface); 
            border:1px solid var(--cr-border); 
            border-radius:16px; 
            padding:1.5rem; 
            box-shadow:0 4px 24px rgba(26,46,22,.08); 
            position:sticky; 
            top:1rem; 
        }
        .summary-card h2 { 
            font-size:1rem; 
            font-weight:700; 
            margin:0 0 1.25rem; 
            padding-bottom:.75rem; 
            border-bottom:1px solid var(--cr-border); 
        }
        .summary-row { display:flex; justify-content:space-between; font-size:.9rem; margin-bottom:.6rem; }
        .summary-row.total { 
            font-weight:700; 
            font-size:1.05rem; 
            padding-top:.75rem; 
            border-top:2px solid var(--cr-border); 
            margin-top:.5rem; 
            color:var(--cr-text); 
        }
        .checkout-btn { 
            display:block; 
            width:100%; 
            padding:.85rem; 
            background:var(--cr-accent); 
            color:white; 
            border:none; 
            border-radius:999px; 
            font:inherit; 
            font-weight:700; 
            font-size:1rem; 
            cursor:pointer; 
            text-align:center; 
            text-decoration:none; 
            margin-top:1.25rem; 
            transition:background .15s;
        }
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
        .shop-link { 
            padding:.65rem 1.5rem; 
            background:var(--cr-accent); 
            color:white; 
            border-radius:999px; 
            font-weight:600; 
            font-size:.9rem; 
            text-decoration:none; 
        }
        .shop-link:hover { background:#1a5c2b; text-decoration:none; }
        .shop-link.outline { background:transparent; border:1px solid var(--cr-accent); color:var(--cr-accent); }
        .shop-link.outline:hover { background:#eef6ea; }
        .admin-cart-topnav {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            margin-left: auto;
        }
    </style>
</head>
<body>
    <header class="site-header">
        <a class="logo" href="index.php">Greenwood Zoo</a>
        <?php if ($isAdminStaffCart): ?>
        <nav class="admin-cart-topnav" aria-label="Account">
            <a href="dashboard.php" class="admin-nav-link">Dashboard</a>
            <?php include __DIR__ . '/admin_header_cart_profile.inc.php'; ?>
            <a href="logout.php" class="admin-nav-link">Logout</a>
        </nav>
        <?php else: ?>
        <?php require __DIR__ . '/customer_nav.php'; ?>
        <?php endif; ?>
    </header>

    <main>
        <div class="shop-page-header">
            <h1>Your cart</h1>
            <p>Review gift shop, ticket, and restaurant items before checkout.</p>
        </div>

        <?php if ($isEmpty): ?>
        <div class="empty-state">
            <span class="emoji">🛒</span>
            <h2>Your cart is empty</h2>
            <p>Browse the gift shop, restaurant, or tickets to get started.</p>
            <div class="shop-links">
                <a href="giftshop.php" class="shop-link">Gift Shop</a>
                <a href="restaurant.php"  class="shop-link outline">Restaurant</a>
                <a href="buy_tickets.php" class="shop-link outline">Tickets</a>
            </div>
        </div>
        <?php else: ?>

        <div class="cart-layout">
            <div>
                <?php if (!empty($shopItems)): ?>
                <div class="cart-section">
                    <div class="cart-section-header"><span class="icon">🎁</span> Gift Shop</div>
                    <table class="cart-table">
                        <thead><tr><th>Item</th><th>Price</th><th>Qty</th><th>Subtotal</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($shopItems as $s): ?>
                        <tr>
                            <td>
                                <div style="font-weight:600"><?= htmlspecialchars($s['ItemName']) ?></div>
                                <div style="font-size:.8rem;color:var(--cr-muted)"><?= htmlspecialchars($s['ShopName']) ?></div>
                            </td>
                            <td>$<?= number_format((float) $s['Price'], 2) ?></td>
                            <td>
                                <form class="qty-form" method="POST" action="cart_action.php">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="type" value="shop">
                                    <input type="hidden" name="id" value="<?= (int) $s['ShopItemID'] ?>">
                                    <input type="hidden" name="redirect" value="cart.php">
                                    <input class="qty-input" type="number" name="qty" value="<?= (int) $s['qty'] ?>" min="1" max="<?= max(1, (int) $s['StockQty']) ?>">
                                    <button class="update-btn" type="submit" title="Update">↻</button>
                                </form>
                            </td>
                            <td class="subtotal">$<?= number_format($s['subtotal'], 2) ?></td>
                            <td>
                                <form method="POST" action="cart_action.php">
                                    <input type="hidden" name="action" value="remove">
                                    <input type="hidden" name="type" value="shop">
                                    <input type="hidden" name="id" value="<?= (int) $s['ShopItemID'] ?>">
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

                <?php if (!empty($ticketItems)): ?>
                <div class="cart-section">
                    <div class="cart-section-header"> Zoo Tickets</div>
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

                <form id="clear-cart-form" method="POST" action="cart_action.php" style="margin-top:.5rem">
                    <input type="hidden" name="action"   value="clear">
                    <input type="hidden" name="redirect" value="cart.php">
                    <button type="button" id="open-clear-cart-modal" style="background:none;border:none;color:var(--cr-muted);font:inherit;font-size:.85rem;font-weight:600;cursor:pointer;text-decoration:underline;padding:0">
                        Clear cart
                    </button>
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
                    <?php if ($shopTotal > 0): ?>
                    <div class="summary-row"><span>Gift shop</span><span>$<?= number_format($shopTotal, 2) ?></span></div>
                    <?php endif; ?>
                    <div class="summary-row total"><span>Total</span><span>$<?= number_format($grandTotal, 2) ?></span></div>
                    <?php if ($canCheckout): ?>
                    <a href="checkout.php" class="checkout-btn">Proceed to checkout →</a>
                    <?php else: ?>
                    <p class="checkout-admin-note" style="margin:10px 0 0;font-size:.82rem;color:var(--cr-muted);line-height:1.45">
                        Checkout uses a customer account. Use the public site as a customer to place orders, or manage sales from the staff dashboard.
                    </p>
                    <?php endif; ?>
                    <div class="continue-links">
                        <a href="giftshop.php">+ Add gift shop items</a>
                        <a href="restaurant.php">+ Add food items</a>
                        <a href="buy_tickets.php">+ Add tickets</a>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </main>

    <div id="clear-cart-modal" class="site-modal" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="clear-cart-modal-title">
        <div class="site-modal__backdrop" data-close-clear-cart></div>
        <div class="site-modal__panel">
            <h2 id="clear-cart-modal-title" class="site-modal__title">Clear your cart?</h2>
            <p class="site-modal__text">All gift shop, ticket, and restaurant items in your cart will be removed. You can add them again later.</p>
            <div class="site-modal__actions">
                <button type="button" class="btn btn-outline" data-close-clear-cart>Keep shopping</button>
                <button type="button" class="btn btn-primary" id="confirm-clear-cart">Clear cart</button>
            </div>
        </div>
    </div>
    <script>
    (function () {
        var openBtn = document.getElementById('open-clear-cart-modal');
        var modal = document.getElementById('clear-cart-modal');
        var form = document.getElementById('clear-cart-form');
        var confirmBtn = document.getElementById('confirm-clear-cart');
        if (!openBtn || !modal || !form || !confirmBtn) return;
        var closers = modal.querySelectorAll('[data-close-clear-cart]');
        function openM() {
            modal.classList.add('site-modal--open');
            modal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
        }
        function closeM() {
            modal.classList.remove('site-modal--open');
            modal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
        }
        openBtn.addEventListener('click', openM);
        closers.forEach(function (el) { el.addEventListener('click', closeM); });
        modal.addEventListener('click', function (e) { if (e.target === modal) closeM(); });
        confirmBtn.addEventListener('click', function () { form.submit(); });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modal.classList.contains('site-modal--open')) closeM();
        });
    })();
    </script>
</body>
</html>
