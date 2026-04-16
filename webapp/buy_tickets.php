<?php
session_start();
if (!isset($_SESSION['customer_id'])) {
    header('Location: sign-in.html');
    exit;
}
require_once 'db.php';

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = ['food' => [], 'shop' => [], 'ticket' => []];
}

// Zoo visit tickets only (1–4); matches order_tickets + cart_action + checkout
$categories = $pdo->query("
    SELECT OrderCategoryID, CategoryName, Price
    FROM ordercategories
    WHERE OrderCategoryID BETWEEN 1 AND 4
    ORDER BY OrderCategoryID
")->fetchAll();

$cartCount = array_sum($_SESSION['cart']['food'])
           + array_sum($_SESSION['cart']['shop'])
           + array_sum(array_column($_SESSION['cart']['ticket'], 'qty'));

$added = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $categoryID   = (int)$_POST['category_id'];
    $quantity     = max(1, (int)$_POST['quantity']);
    $visitDate    = $_POST['visit_date'] ?? '';
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
        $cartCount = array_sum($_SESSION['cart']['food'])
                   + array_sum($_SESSION['cart']['shop'])
                   + array_sum(array_column($_SESSION['cart']['ticket'], 'qty'));
        $added = $quantity . 'x ' . $categoryName . ' ticket(s) for '
               . date('M j, Y', strtotime($visitDate)) . ' added to your cart.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buy Tickets — Greenwood Zoo</title>
    <link rel="stylesheet" href="customer-reports.css">
    <style>
        /* Form card matches cr-card style */
        .ticket-form-card {
            background: var(--cr-surface);
            border-radius: var(--cr-radius);
            box-shadow: var(--cr-shadow);
            border: 1px solid var(--cr-border);
            padding: 2rem;
            max-width: 580px;
            margin-bottom: 2rem;
        }

        .form-section-title {
            font-size: .75rem;
            text-transform: uppercase;
            letter-spacing: .07em;
            color: var(--cr-muted);
            font-weight: 600;
            margin: 0 0 .85rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            margin-bottom: 1.1rem;
        }
        .form-group label {
            font-weight: 700;
            font-size: .88rem;
            margin-bottom: .4rem;
            color: var(--cr-text);
        }
        .form-group select,
        .form-group input {
            padding: .65rem .9rem;
            border: 1px solid var(--cr-border);
            border-radius: 8px;
            font: inherit;
            font-size: .92rem;
            background: var(--cr-surface);
            color: var(--cr-text);
            transition: border-color .15s;
        }
        .form-group select:focus,
        .form-group input:focus {
            outline: none;
            border-color: var(--cr-accent-soft);
            box-shadow: 0 0 0 3px rgba(45,106,62,.08);
        }

        .price-hint {
            font-size: .8rem;
            color: var(--cr-muted);
            margin-top: .3rem;
        }

        /* Subtotal row — matches table header gradient */
        .subtotal-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: .85rem 1rem;
            background: linear-gradient(180deg, #eef6ea 0%, #e4f0de 100%);
            border-radius: 8px;
            margin: 1.25rem 0;
            border: 1px solid var(--cr-border);
        }
        .subtotal-row .label {
            font-size: .88rem;
            font-weight: 600;
            color: var(--cr-accent);
            text-transform: uppercase;
            letter-spacing: .05em;
        }
        .subtotal-row .amount {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--cr-accent);
        }

        /* Add to cart button — matches checkout-btn style */
        .add-to-cart-btn {
            display: inline-block;
            padding: .75rem 2rem;
            background: var(--cr-accent);
            color: white;
            border: none;
            border-radius: 999px;
            font: inherit;
            font-weight: 700;
            font-size: .95rem;
            cursor: pointer;
            text-decoration: none;
            transition: background .15s;
            letter-spacing: .02em;
        }
        .add-to-cart-btn:hover { background: #1a5c2b; text-decoration: none; }

        .view-cart-link {
            display: inline-block;
            padding: .72rem 1.5rem;
            border: 1px solid var(--cr-border);
            border-radius: 999px;
            font-weight: 600;
            font-size: .88rem;
            color: var(--cr-accent);
            text-decoration: none;
            background: var(--cr-surface);
            transition: border-color .15s, background .15s;
        }
        .view-cart-link:hover {
            border-color: var(--cr-accent-soft);
            background: #f4f9f4;
            text-decoration: none;
        }

        /* Success / error banners */
        .banner {
            padding: .9rem 1.1rem;
            border-radius: 10px;
            font-size: .9rem;
            font-weight: 600;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: .5rem;
        }
        .banner-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .banner-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Cart tickets preview — matches cr-table style */
        .cart-preview-card {
            background: var(--cr-surface);
            border-radius: var(--cr-radius);
            box-shadow: var(--cr-shadow);
            border: 1px solid var(--cr-border);
            overflow: hidden;
            max-width: 580px;
            margin-bottom: 2rem;
        }
        .cart-preview-header {
            padding: .85rem 1rem;
            background: linear-gradient(180deg, #eef6ea 0%, #e4f0de 100%);
            border-bottom: 1px solid var(--cr-border);
            font-size: .82rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: var(--cr-accent);
        }
        .cart-preview-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: .75rem 1rem;
            border-bottom: 1px solid #eef2eb;
            font-size: .9rem;
        }
        .cart-preview-row:last-of-type { border-bottom: none; }
        .cart-preview-row:hover { background: #fafcf8; }
        .cart-preview-row .name { font-weight: 600; color: var(--cr-text); }
        .cart-preview-row .meta { font-size: .78rem; color: var(--cr-muted); margin-top: .15rem; }
        .cart-preview-row .price { font-weight: 700; color: var(--cr-accent); }
        .cart-preview-footer {
            padding: .75rem 1rem;
            border-top: 1px solid var(--cr-border);
            text-align: right;
            background: #fafcf8;
        }
        .cart-preview-footer a {
            color: var(--cr-accent);
            font-weight: 700;
            font-size: .88rem;
            text-decoration: none;
        }
        .cart-preview-footer a:hover { text-decoration: underline; }

        .remove-btn {
            padding: 3px 10px;
            background: #fee2e2;
            border: none;
            color: #991b1b;
            border-radius: 6px;
            font: inherit;
            font-size: .75rem;
            font-weight: 600;
            cursor: pointer;
            transition: background .15s;
        }
        .remove-btn:hover { background: #fca5a5; }

        /* Cart badge in nav */
        .cart-nav-link {
            position: relative;
            display: inline-flex;
            align-items: center;
            gap: .3rem;
        }
        .cart-nav-badge {
            position: absolute;
            top: -7px;
            right: -10px;
            background: var(--cr-accent);
            color: white;
            font-size: .6rem;
            font-weight: 700;
            padding: 1px 5px;
            border-radius: 999px;
            min-width: 16px;
            text-align: center;
        }
    </style>
</head>
<body class="cr-body">
<div class="cr-shell">

    <!-- Nav — matches ticket history page exactly -->
    <header class="cr-topbar">
        <span class="cr-brand">Greenwood Zoo</span>
        <nav class="cr-nav">
            <a href="customer-dashboard.php">Dashboard</a>
            <a href="customer_animals_report.php">Animals</a>
            <a href="customer_tickets_report.php">My tickets</a>
            <a href="restaurant.php">Restaurant</a>
            <a href="giftshop.php">Gift Shop</a>
            <a href="cart.php" class="cr-btn-outline cart-nav-link">
                🛒 Cart
                <?php if ($cartCount > 0): ?>
                    <span class="cart-nav-badge"><?= $cartCount ?></span>
                <?php endif; ?>
            </a>
            <a href="logout.php" class="cr-btn-outline">Sign out</a>
        </nav>
    </header>

    <main id="main">

        <!-- Hero — matches ticket history page -->
        <div class="cr-hero">
            <h1>Buy tickets</h1>
            <p>Select your ticket type, quantity and visit date. Tickets go into your cart — checkout when you're ready.</p>
        </div>

        <!-- Banners -->
        <?php if ($error): ?>
        <div class="banner banner-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($added): ?>
        <div class="banner banner-success">
            <span>✓ <?= htmlspecialchars($added) ?></span>
            <div style="display:flex;gap:.75rem;align-items:center">
                <a href="buy_tickets.php" style="color:#155724;font-weight:600;font-size:.85rem;text-decoration:none">+ Add more</a>
                <a href="cart.php" class="view-cart-link">View cart (<?= $cartCount ?>)</a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Ticket form — styled as a cr-card -->
        <div class="ticket-form-card">
            <p class="form-section-title">Ticket details</p>
            <form method="POST" id="ticketForm">
                <input type="hidden" name="category_name" id="category_name_hidden">

                <div class="form-group">
                    <label for="category_id">Ticket type</label>
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
                    <span class="price-hint" id="priceHint"></span>
                </div>

                <div class="form-group">
                    <label for="quantity">Quantity</label>
                    <input type="number" name="quantity" id="quantity"
                           min="1" max="20" value="1" required oninput="updatePrice()">
                </div>

                <div class="form-group">
                    <label for="visit_date">Visit date</label>
                    <input type="date" name="visit_date" id="visit_date"
                           required min="<?= date('Y-m-d') ?>">
                </div>

                <div class="subtotal-row" id="subtotalRow" style="display:none">
                    <span class="label">Subtotal</span>
                    <span class="amount" id="subtotalAmount">$0.00</span>
                </div>

                <div style="display:flex;align-items:center;gap:.85rem;flex-wrap:wrap">
                    <button type="submit" class="add-to-cart-btn">Add to cart</button>
                    <?php if ($cartCount > 0): ?>
                    <a href="cart.php" class="view-cart-link">View cart (<?= $cartCount ?>)</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Tickets in cart preview — styled as cr-card with cr-table header -->
        <?php if (!empty($_SESSION['cart']['ticket'])): ?>
        <div class="cart-preview-card">
            <div class="cart-preview-header">🎟️ Tickets in your cart</div>

            <?php
            $cartTicketTotal = 0;
            foreach ($_SESSION['cart']['ticket'] as $key => $t):
                $stmt = $pdo->prepare("SELECT CategoryName, Price FROM ordercategories WHERE OrderCategoryID = ?");
                $stmt->execute([$t['category_id']]);
                $cat = $stmt->fetch();
                if (!$cat) continue;
                $subtotal = $cat['Price'] * $t['qty'];
                $cartTicketTotal += $subtotal;
            ?>
            <div class="cart-preview-row">
                <div>
                    <div class="name"><?= htmlspecialchars($cat['CategoryName']) ?> × <?= $t['qty'] ?></div>
                    <div class="meta">Visit date: <?= date('M j, Y', strtotime($t['visit_date'])) ?> &nbsp;·&nbsp; $<?= number_format($cat['Price'], 2) ?> each</div>
                </div>
                <div style="display:flex;align-items:center;gap:.75rem">
                    <span class="price">$<?= number_format($subtotal, 2) ?></span>
                    <form method="POST" action="cart_action.php" style="display:inline">
                        <input type="hidden" name="action"   value="remove">
                        <input type="hidden" name="type"     value="ticket">
                        <input type="hidden" name="key"      value="<?= htmlspecialchars($key) ?>">
                        <input type="hidden" name="redirect" value="buy_tickets.php">
                        <button type="submit" class="remove-btn">Remove</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>

            <div class="cart-preview-footer">
                <span style="color:var(--cr-muted);font-size:.82rem;margin-right:1rem">
                    Ticket subtotal: <strong style="color:var(--cr-accent)">$<?= number_format($cartTicketTotal, 2) ?></strong>
                </span>
                <a href="cart.php">Proceed to cart &amp; checkout →</a>
            </div>
        </div>
        <?php endif; ?>

        <p class="cr-footnote">Questions about your tickets? Contact guest services with your order number after checkout.</p>

    </main>
</div>

<script>
function updatePrice() {
    const sel   = document.getElementById('category_id');
    const qty   = parseInt(document.getElementById('quantity').value) || 0;
    const opt   = sel.options[sel.selectedIndex];
    const price = parseFloat(opt.dataset.price) || 0;
    const name  = opt.dataset.name || '';
    const total = price * qty;

    document.getElementById('category_name_hidden').value = name;

    const hint = document.getElementById('priceHint');
    const row  = document.getElementById('subtotalRow');

    if (price > 0) {
        hint.textContent = '$' + price.toFixed(2) + ' per ticket';
        row.style.display = 'flex';
        document.getElementById('subtotalAmount').textContent = '$' + total.toFixed(2);
    } else {
        hint.textContent = '';
        row.style.display = 'none';
    }
}
</script>
</body>
</html>
