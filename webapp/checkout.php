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


$foodItems   = [];
$ticketItems = [];
$foodTotal   = $ticketTotal = 0;

if (!empty($_SESSION['cart']['food'])) {
    $ids  = implode(',', array_map('intval', array_keys($_SESSION['cart']['food'])));
    $rows = $pdo->query("SELECT FoodID, FoodName, Price FROM fooditem WHERE FoodID IN ($ids)")->fetchAll();
    foreach ($rows as $r) {
        $qty           = $_SESSION['cart']['food'][$r['FoodID']];
        $r['qty']      = $qty;
        $r['subtotal'] = $r['Price'] * $qty;
        $foodTotal    += $r['subtotal'];
        $foodItems[]   = $r;
    }
}

if (!empty($_SESSION['cart']['ticket'])) {
    foreach ($_SESSION['cart']['ticket'] as $key => $t) {
        $stmt = $pdo->prepare("SELECT CategoryName, Price FROM ordercategories WHERE OrderCategoryID = ?");
        $stmt->execute([$t['category_id']]);
        $cat = $stmt->fetch();
        if ($cat) {
            $subtotal      = $cat['Price'] * $t['qty'];
            $ticketTotal  += $subtotal;
            $ticketItems[] = array_merge($t, ['key' => $key, 'CategoryName' => $cat['CategoryName'], 'Price' => $cat['Price'], 'subtotal' => $subtotal]);
        }
    }
}

$grandTotal = $foodTotal + $ticketTotal;
$isEmpty    = ($grandTotal == 0);
$error      = '';
$success    = false;


if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isEmpty) {
    $paymentMode = trim($_POST['payment_mode'] ?? '');
    $validModes  = ['Credit Card', 'Debit Card', 'Cash', 'PayPal'];

    if (!in_array($paymentMode, $validModes)) {
        $error = 'Please select a payment method.';
    } else {
        try {
            $pdo->beginTransaction();
            $customerID = (int) $_SESSION['customer_id'];
            $today      = date('Y-m-d');

        
            if (!empty($foodItems)) {
                $stmt = $pdo->prepare("INSERT INTO orders (OrderDate, CustomerID, OrderCategoryID, PaymentMode, TransactionAmount, ScheduledDate) VALUES (?, ?, 5, ?, ?, NULL)");
                $stmt->execute([$today, $customerID, $paymentMode, $foodTotal]);
                $foodOrderID = $pdo->lastInsertId();

                $stmt2 = $pdo->prepare("INSERT INTO order_food_items (OrderID, FoodID, Quantity) VALUES (?, ?, ?)");
                foreach ($foodItems as $f) {
                    $stmt2->execute([$foodOrderID, $f['FoodID'], $f['qty']]);
                }
            }

            
            if (!empty($ticketItems)) {
                foreach ($ticketItems as $t) {
                    $stmt = $pdo->prepare("INSERT INTO orders (OrderDate, CustomerID, OrderCategoryID, PaymentMode, TransactionAmount, ScheduledDate) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$today, $customerID, $t['category_id'], $paymentMode, $t['subtotal'], $t['visit_date']]);
                    $ticketOrderID = $pdo->lastInsertId();

                    $stmt2 = $pdo->prepare("INSERT INTO order_tickets (OrderID, OrderCategoryID, Quantity) VALUES (?, ?, ?)");
                    $stmt2->execute([$ticketOrderID, $t['category_id'], $t['qty']]);
                }
            }

            $pdo->commit();

            // Clear the cart
            $_SESSION['cart'] = ['food' => [], 'ticket' => []];
            $_SESSION['cart']['shop'] = [];
            $success = true;

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Checkout failed: ' . $e->getMessage();
        }
    }
}

$navCartCount = array_sum($_SESSION['cart']['food'] ?? [])
    + array_sum(array_column($_SESSION['cart']['ticket'] ?? [], 'qty'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout — Greenwood Zoo</title>
    <link rel="stylesheet" href="index.css">
    <style>
        .checkout-layout { 
            display:grid; 
            grid-template-columns:1fr 340px; 
            gap:1.5rem; 
            align-items:start; 
        }
        @media(max-width:860px){ .checkout-layout { grid-template-columns:1fr; } }

        .checkout-card { 
            background:var(--cr-surface); 
            border:1px solid var(--cr-border); 
            border-radius:16px; 
            padding:1.5rem; 
            box-shadow:0 4px 24px rgba(26,46,22,.07); 
            margin-bottom:1.25rem; 
        }
        .checkout-card h2 { 
            font-size:.95rem; 
            font-weight:700; 
            text-transform:uppercase; 
            letter-spacing:.05em; 
            color:var(--cr-muted); 
            margin:0 0 1rem; 
            padding-bottom:.6rem; 
            border-bottom:1px solid var(--cr-border); 
        }

        .review-row { 
            display:flex; 
            justify-content:space-between; 
            align-items:center; 
            padding:.55rem 0; 
            border-bottom:1px solid #eef2eb; 
            font-size:.9rem; 
        }
        .review-row:last-child { border-bottom:none; }
        .review-row .label { color:var(--cr-text); }
        .review-row .price { font-weight:700; color:var(--cr-accent); }
        .review-row .meta  { 
            font-size:.78rem; 
            color:var(--cr-muted); 
            margin-top:.15rem; 
        }
        .section-sub { 
            font-size:.78rem; 
            text-transform:uppercase; 
            letter-spacing:.06em; 
            color:var(--cr-muted); 
            font-weight:600; 
            margin:.85rem 0 .4rem; 
        }
        .payment-grid { display:grid; grid-template-columns:1fr 1fr; gap:.65rem; }
        .payment-option { position:relative; }
        .payment-option input[type="radio"] { 
            position:absolute; 
            opacity:0;
            width:0; 
            height:0; 
        }
        .payment-label {
            display:flex; 
            align-items:center; 
            gap:.6rem; 
            padding:.75rem 1rem;
            border:2px solid var(--cr-border); 
            border-radius:10px; 
            cursor:pointer;
            font-weight:600; 
            font-size:.9rem; 
            transition:border-color .15s, background .15s;
        }
        .payment-option input:checked + .payment-label { border-color:var(--cr-accent); background:#eef6ea; }
        .payment-label:hover { border-color:var(--cr-accent-soft); }
        .payment-icon { font-size:1.2rem; }

        .total-section { padding:1rem 0 0; }
        .total-row { display:flex; justify-content:space-between; font-size:.9rem; margin-bottom:.5rem; }
        .total-row.grand { font-weight:700; font-size:1.1rem; padding-top:.75rem; border-top:2px solid var(--cr-border); margin-top:.25rem; }

        .place-btn { 
            display:block; 
            width:100%; 
            padding:1rem; 
            background:var(--cr-accent); 
            color:white; 
            border:none; 
            border-radius:999px; 
            font:inherit; 
            font-weight:700; 
            font-size:1rem; 
            cursor:pointer; 
            text-align:center; 
            margin-top:1.25rem; 
            transition:background .15s; 
        }
        .place-btn:hover { background:#1a5c2b; }

        .msg-error   { background:#fee2e2; color:#991b1b; padding:.85rem 1rem; border-radius:10px; font-weight:600; margin-bottom:1rem; }

       
        .success-wrapper { 
            text-align:center; 
            padding:3rem 1rem; 
            max-width:540px; 
            margin:0 auto; }
        .success-icon { 
            font-size:4rem; 
            display:block; 
            margin-bottom:1rem; }
        .success-wrapper h1 { 
            font-size:1.75rem; 
            font-weight:700; 
            margin:0 0 .5rem; 
            color:var(--cr-accent); }
        .success-wrapper p  { 
            color:var(--cr-muted); 
            margin:0 0 2rem; 
            line-height:1.6; }
        .success-links { 
            display:flex; 
            flex-wrap:wrap; 
            gap:.75rem; 
            justify-content:center; }
        .success-link { 
            padding:.65rem 1.5rem; 
            border-radius:999px; 
            font-weight:600; 
            font-size:.9rem; 
            text-decoration:none; }
        .success-link.primary { 
            background:var(--cr-accent); 
            color:white; }
        .success-link.primary:hover { 
            background:#1a5c2b; }
        .success-link.outline { 
            border:1px solid var(--cr-accent); 
            color:var(--cr-accent); }
        .success-link.outline:hover { 
            background:#eef6ea; }

        .back-link { color:var(--cr-muted); font-size:.875rem; font-weight:600; text-decoration:none; display:inline-block; margin-bottom:1.5rem; }
        .back-link:hover { color:var(--cr-accent); }
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
                    <a href="cart.php" class="nav-cart-link">🛒 Cart<?php if ($navCartCount > 0): ?><span class="nav-cart-badge" id="cart-count"><?= (int) $navCartCount ?></span><?php endif; ?></a>
                </li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </nav>
    </header>

    <main>
        <?php if ($success): ?>
        
        <div class="success-wrapper">
            <span class="success-icon">🎉</span>
            <h1>Order confirmed!</h1>
            <p>Your order has been placed successfully. We look forward to seeing you at Greenwood Zoo! Check your ticket history for your visit details.</p>
            <div class="success-links">
                <a href="customer_tickets_report.php" class="success-link primary">View my tickets</a>
                <a href="customer-dashboard.php"      class="success-link outline">Back to dashboard</a>
                <a href="restaurant.php"              class="success-link outline">Order more food</a>
            </div>
        </div>

        <?php elseif ($isEmpty): ?>
     
        <div style="text-align:center; padding:3rem 1rem; color:var(--cr-muted)">
            <p style="font-size:1.1rem; margin-bottom:1rem">Your cart is empty.</p>
            <a href="cart.php" style="color:var(--cr-accent); font-weight:600">← Go back to cart</a>
        </div>

        <?php else: ?>
    
        <a href="cart.php" class="back-link">← Back to cart</a>
        <h1 style="font-size:clamp(1.6rem,3vw,2rem);font-weight:700;margin:0 0 1.5rem">Checkout</h1>

        <?php if ($error): ?>
            <div class="msg-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
        <div class="checkout-layout">
         
            <div>
               
                <div class="checkout-card">
                    <h2>Order review</h2>

                    <?php if (!empty($ticketItems)): ?>
                    <p class="section-sub"> Tickets</p>
                    <?php foreach ($ticketItems as $t): ?>
                    <div class="review-row">
                        <div>
                            <div class="label"><?= htmlspecialchars($t['CategoryName']) ?> × <?= $t['qty'] ?></div>
                            <div class="meta">Visit: <?= date('M j, Y', strtotime($t['visit_date'])) ?></div>
                        </div>
                        <div class="price">$<?= number_format($t['subtotal'], 2) ?></div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>

                    <?php if (!empty($foodItems)): ?>
                    <p class="section-sub"> Restaurant</p>
                    <?php foreach ($foodItems as $f): ?>
                    <div class="review-row">
                        <div class="label"><?= htmlspecialchars($f['FoodName']) ?> × <?= $f['qty'] ?></div>
                        <div class="price">$<?= number_format($f['subtotal'], 2) ?></div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>

                </div>

                <!-- Payment Method -->
                <div class="checkout-card">
                    <h2>Payment method</h2>
                    <div class="payment-grid">
                        <?php
                        $methods = [
                            'Credit Card' => '💳',
                            'Debit Card'  => '💳',
                            'Cash'        => '💵',
                            'PayPal'      => '🅿️',
                        ];
                        foreach ($methods as $method => $icon):
                        ?>
                        <div class="payment-option">
                            <input type="radio" name="payment_mode"
                                   id="pm_<?= str_replace(' ','_',$method) ?>"
                                   value="<?= $method ?>"
                                   <?= ($method === 'Credit Card') ? 'checked' : '' ?>>
                            <label class="payment-label" for="pm_<?= str_replace(' ','_',$method) ?>">
                                <span class="payment-icon"><?= $icon ?></span>
                                <?= $method ?>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div>
                <div class="checkout-card">
                    <h2>Summary</h2>
                    <div class="total-section">
                        <?php if ($ticketTotal > 0): ?>
                        <div class="total-row"><span>Tickets</span><span>$<?= number_format($ticketTotal, 2) ?></span></div>
                        <?php endif; ?>
                        <?php if ($foodTotal > 0): ?>
                        <div class="total-row"><span>Restaurant</span><span>$<?= number_format($foodTotal, 2) ?></span></div>
                        <?php endif; ?>
                        <div class="total-row grand">
                            <span>Total</span>
                            <span>$<?= number_format($grandTotal, 2) ?></span>
                        </div>
                    </div>
                    <button type="submit" class="place-btn">Place order — $<?= number_format($grandTotal, 2) ?></button>
                    <p style="margin:.75rem 0 0; font-size:.78rem; color:var(--cr-muted); text-align:center; line-height:1.5">
                        By placing your order you agree to our terms. No refunds on ticket purchases.
                    </p>
                </div>
            </div>
        </div>
        </form>
        <?php endif; ?>
    </main>
</body>
</html>
