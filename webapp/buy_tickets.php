<?php
session_start();
if (!isset($_SESSION['customer_id'])) {
    header('Location: login.html');
    exit;
}
require_once 'db.php';

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = ['food' => [], 'ticket' => [], 'shop' => []];
} elseif (!isset($_SESSION['cart']['shop']) || !is_array($_SESSION['cart']['shop'])) {
    $_SESSION['cart']['shop'] = [];
}

$categories = $pdo->query("
    SELECT OrderCategoryID, CategoryName, Price
    FROM ordercategories
    WHERE OrderCategoryID BETWEEN 1 AND 4
    ORDER BY OrderCategoryID
")->fetchAll();

$cartCount = array_sum($_SESSION['cart']['food'])
           + array_sum($_SESSION['cart']['shop'])
           + array_sum(array_column($_SESSION['cart']['ticket'], 'qty'));

$added   = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $categoryID  = (int)$_POST['category_id'];
    $quantity    = max(1, (int)$_POST['quantity']);
    $visitDate   = $_POST['visit_date'] ?? '';
    $categoryName = trim($_POST['category_name'] ?? '');

    if (!$categoryID || !$visitDate) {
        $error = 'Please select a ticket type and visit date.';
    } elseif ($categoryID < 1 || $categoryID > 4) {
        $error = 'Invalid ticket type.';
    } elseif ($quantity < 1 || $quantity > 20) {
        $error = 'Quantity must be between 1 and 20.';
    } elseif (strtotime($visitDate) < strtotime('today')) {
        $error = 'Visit date cannot be in the past.';
    } else {
        // Add to cart
        $key = $categoryID . '_' . $visitDate;
        if (isset($_SESSION['cart']['ticket'][$key])) {
            $_SESSION['cart']['ticket'][$key]['qty'] += $quantity;
        } else {
            $_SESSION['cart']['ticket'][$key] = [
                'category_id' => $categoryID,
                'visit_date'  => $visitDate,
                'qty'         => $quantity,
            ];
        }

        // Recalculate badge count
        $cartCount = array_sum($_SESSION['cart']['food'])
                   + array_sum($_SESSION['cart']['shop'])
                   + array_sum(array_column($_SESSION['cart']['ticket'], 'qty'));

        $added = $quantity . 'x ' . $categoryName . ' ticket(s) for ' . date('M j, Y', strtotime($visitDate)) . ' added to cart!';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buy Tickets — Greenwood Zoo</title>
    <link rel="stylesheet" href="index.css">
    <style>
        .form-card {
            background: var(--cr-surface);
            border-radius: var(--cr-radius);
            box-shadow: var(--cr-shadow);
            border: 1px solid var(--cr-border);
            padding: 2rem;
            max-width: 560px;
        }
        .form-group { display:flex; flex-direction:column; margin-bottom:1.1rem; }
        .form-group label { font-weight:600; font-size:.9rem; margin-bottom:.4rem; color:var(--cr-accent); }
        .form-group select, .form-group input {
            padding:.6rem .85rem; border:1px solid var(--cr-border); border-radius:8px;
            font:inherit; font-size:.95rem; background:white; color:var(--cr-text);
        }
        .form-group select:focus, .form-group input:focus { outline:none; border-color:var(--cr-accent-soft); }

        .ticket-price { margin-top:.4rem; font-size:.85rem; color:var(--cr-muted); }
        .total-row {
            margin:1.25rem 0; padding:.85rem 1rem; background:#eef6ea;
            border-radius:8px; font-weight:700; color:var(--cr-accent); font-size:1.05rem;
        }

        .add-btn {
            padding:.75rem 2.5rem; background:var(--cr-accent); color:white; border:none;
            border-radius:999px; font:inherit; font-weight:600; font-size:.95rem;
            cursor:pointer; text-transform:uppercase; letter-spacing:.04em; transition:background .15s;
        }
        .add-btn:hover { background:#1a5c2b; }

        .view-cart-btn {
            display:inline-block; padding:.65rem 1.5rem; background:white;
            border:2px solid var(--cr-accent); color:var(--cr-accent); border-radius:999px;
            font-weight:600; font-size:.9rem; text-decoration:none; margin-left:.75rem;
            transition:background .15s;
        }
        .view-cart-btn:hover { background:#eef6ea; text-decoration:none; }

        .msg-error   { 
            color:#c0392b; 
            font-weight:600; 
            margin-bottom:1rem; 
            padding:.75rem 1rem; 
            background:#fee2e2; 
            border-radius:8px; 
        }
        .msg-success { 
            color:#155724; 
            font-weight:600; 
            margin-bottom:1rem; 
            padding:.75rem 1rem; 
            background:#d4edda; 
            border-radius:8px; 
            display:flex; 
            align-items:center; 
            justify-content:space-between; 
            flex-wrap:wrap; 
            gap:.5rem; 
        }
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
            <h1>Buy tickets</h1>
            <p>Select your ticket type, quantity and visit date. Tickets are added to your cart so you can check out together with restaurant orders.</p>
        </div>

        <?php if ($error): ?>
            <div class="msg-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($added): ?>
            <div class="msg-success">
                <span>✓ <?= htmlspecialchars($added) ?></span>
                <div>
                    <a href="buy_tickets.php" style="color:var(--cr-accent);font-weight:600;text-decoration:none;margin-right:1rem">+ Add more</a>
                    <a href="cart.php" class="view-cart-btn">View cart (<?= $cartCount ?>)</a>
                </div>
            </div>
        <?php endif; ?>

        <div class="form-card">
            <form method="POST" id="ticketForm">
                <input type="hidden" name="category_name" id="category_name_hidden">

                <div class="form-group">
                    <label for="category_id">Ticket type *</label>
                    <select name="category_id" id="category_id" required onchange="updatePrice()">
                        <option value="">— Select a ticket type —</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['OrderCategoryID'] ?>"
                                    data-price="<?= $cat['Price'] ?>"
                                    data-name="<?= htmlspecialchars($cat['CategoryName'], ENT_QUOTES) ?>">
                                <?= htmlspecialchars($cat['CategoryName']) ?> — $<?= number_format($cat['Price'], 2) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="ticket-price" id="priceHint"></span>
                </div>

                <div class="form-group">
                    <label for="quantity">Quantity *</label>
                    <input type="number" name="quantity" id="quantity"
                           min="1" max="20" value="1" required oninput="updatePrice()">
                </div>

                <div class="form-group">
                    <label for="visit_date">Visit date *</label>
                    <input type="date" name="visit_date" id="visit_date"
                           required min="<?= date('Y-m-d') ?>">
                </div>

                <div class="total-row" id="totalRow" style="display:none">
                    Subtotal: <span id="totalAmount">$0.00</span>
                </div>

                <div style="display:flex;align-items:center;flex-wrap:wrap;gap:.75rem">
                    <button type="submit" class="add-btn">🛒 Add to cart</button>
                    <?php if ($cartCount > 0): ?>
                    <a href="cart.php" class="view-cart-btn">View cart (<?= $cartCount ?>)</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <?php if (!empty($_SESSION['cart']['ticket'])): ?>
        <div style="margin-top:2rem;max-width:560px">
            <h2 style="font-size:1rem;font-weight:700;margin-bottom:.75rem;color:var(--cr-accent)">🎟️ Tickets in your cart</h2>
            <div style="background:var(--cr-surface);border:1px solid var(--cr-border);border-radius:12px;overflow:hidden">
            <?php
            foreach ($_SESSION['cart']['ticket'] as $key => $t):
                $stmt = $pdo->prepare("SELECT CategoryName, Price FROM ordercategories WHERE OrderCategoryID = ?");
                $stmt->execute([$t['category_id']]);
                $cat = $stmt->fetch();
                if (!$cat) continue;
                $subtotal = $cat['Price'] * $t['qty'];
            ?>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:.75rem 1.1rem;border-bottom:1px solid var(--cr-border)">
                <div>
                    <strong style="font-size:.9rem"><?= htmlspecialchars($cat['CategoryName']) ?></strong>
                    <span style="color:var(--cr-muted);font-size:.82rem;margin-left:.5rem">× <?= $t['qty'] ?></span>
                    <div style="font-size:.78rem;color:var(--cr-muted)">Visit: <?= date('M j, Y', strtotime($t['visit_date'])) ?></div>
                </div>
                <div style="display:flex;align-items:center;gap:.75rem">
                    <strong style="color:var(--cr-accent)">$<?= number_format($subtotal, 2) ?></strong>
                    <form method="POST" action="cart_action.php" style="display:inline">
                        <input type="hidden" name="action"   value="remove">
                        <input type="hidden" name="type"     value="ticket">
                        <input type="hidden" name="key"      value="<?= htmlspecialchars($key) ?>">
                        <input type="hidden" name="redirect" value="buy_tickets.php">
                        <button type="submit" style="background:#fee2e2;border:none;color:#991b1b;border-radius:6px;padding:3px 10px;font:inherit;font-size:.78rem;font-weight:600;cursor:pointer">Remove</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
            <div style="padding:.75rem 1.1rem;text-align:right">
                <a href="cart.php" style="color:var(--cr-accent);font-weight:700;text-decoration:none;font-size:.9rem">Proceed to cart & checkout →</a>
            </div>
            </div>
        </div>
        <?php endif; ?>
    </main>

<script>
function updatePrice() {
    const sel    = document.getElementById('category_id');
    const qty    = parseInt(document.getElementById('quantity').value) || 0;
    const opt    = sel.options[sel.selectedIndex];
    const price  = parseFloat(opt.dataset.price) || 0;
    const name   = opt.dataset.name || '';
    const total  = price * qty;
    const hint   = document.getElementById('priceHint');
    const row    = document.getElementById('totalRow');

    document.getElementById('category_name_hidden').value = name;

    if (price > 0) {
        hint.textContent = '$' + price.toFixed(2) + ' per ticket';
        row.style.display = 'block';
        document.getElementById('totalAmount').textContent = '$' + total.toFixed(2);
    } else {
        hint.textContent = '';
        row.style.display = 'none';
    }
}
</script>
</body>
</html>
