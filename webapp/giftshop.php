<?php
session_start();
if (!isset($_SESSION['customer_id'])) {
    header('Location: sign-in.html');
    exit;
}
require_once 'db.php';

$customerID = (int) $_SESSION['customer_id'];
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $itemID = (int) ($_POST['shop_item_id'] ?? 0);
    $quantity = (int) ($_POST['quantity'] ?? 1);
    $paymentMode = trim((string) ($_POST['payment_mode'] ?? ''));

    $validPaymentModes = ['Credit Card', 'Debit Card', 'Cash', 'PayPal'];
    if ($itemID <= 0 || $quantity <= 0 || !in_array($paymentMode, $validPaymentModes, true)) {
        $error = 'Please choose an item, quantity, and payment method.';
    } else {
        try {
            $pdo->beginTransaction();

            $itemStmt = $pdo->prepare('SELECT ItemName, Price, StockQty FROM shop_items WHERE ShopItemID = ?');
            $itemStmt->execute([$itemID]);
            $item = $itemStmt->fetch(PDO::FETCH_ASSOC);

            if (!$item) {
                throw new RuntimeException('Selected item no longer exists.');
            }

            $total = round((float) $item['Price'] * $quantity, 2);

            $orderStmt = $pdo->prepare("
                INSERT INTO orders (OrderDate, CustomerID, OrderCategoryID, PaymentMode, TransactionAmount, ScheduledDate)
                VALUES (CURDATE(), ?, 6, ?, ?, NULL)
            ");
            $orderStmt->execute([$customerID, $paymentMode, $total]);
            $orderID = (int) $pdo->lastInsertId();

            $lineStmt = $pdo->prepare('INSERT INTO order_shop_items (OrderID, ShopItemID, Quantity) VALUES (?, ?, ?)');
            $lineStmt->execute([$orderID, $itemID, $quantity]);

            $pdo->commit();
            $success = "Purchase complete: {$quantity} x {$item['ItemName']}.";
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = str_contains($e->getMessage(), 'out of stock')
                ? 'Sorry, this item is out of stock or does not have enough quantity.'
                : 'Could not complete the purchase. Please try again.';

            if ($itemID > 0) {
                try {
                    $alertStmt = $pdo->prepare("
                        INSERT INTO restock_alerts (ShopItemID, AlertType, Message)
                        VALUES (?, 'OUT_OF_STOCK', 'Customer attempted to buy an out-of-stock item. Please restock.')
                        ON DUPLICATE KEY UPDATE
                            CreatedAt = NOW(),
                            Message = VALUES(Message),
                            IsResolved = 0,
                            ResolvedAt = NULL
                    ");
                    $alertStmt->execute([$itemID]);
                } catch (Throwable $ignored) {
                }
            }
        }
    }
}

$itemsStmt = $pdo->query("
    SELECT si.ShopItemID, si.ItemName, si.Price, si.StockQty, s.ShopName
    FROM shop_items si
    JOIN shops s ON s.ShopID = si.ShopID
    ORDER BY s.ShopName, si.ItemName
");
$items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
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
        .buy-form select,
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
        .bad { background: #ffeaea; color: #8a1111; }
    </style>
</head>
<body>
    <header class="site-header">
        <a class="logo" href="index.php">Greenwood Zoo</a>
        <nav aria-label="Main">
            <ul class="nav-links">
                <li><a href="customer-dashboard.php">Dashboard</a></li>
                <li><a href="buy_tickets.php">Buy Tickets</a></li>
                <li><a href="giftshop.php">Gift Shop</a></li>
                <li><a href="customer_profile.php">Profile</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </nav>
    </header>

    <main class="shop-wrap">
        <section class="shop-panel">
            <h1>Gift Shop</h1>
            <p>Buy zoo merchandise online. Purchases update inventory in real time.</p>
            <?php if ($success !== ''): ?>
                <div class="notice ok"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            <?php if ($error !== ''): ?>
                <div class="notice bad"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
        </section>

        <section class="shop-grid" aria-label="Gift shop items">
            <?php foreach ($items as $item): ?>
                <?php
                    $stock = (int) $item['StockQty'];
                    $stockClass = $stock <= 0 ? 'stock-out' : ($stock <= 2 ? 'stock-low' : 'stock-ok');
                ?>
                <article class="shop-card">
                    <div class="shop-name"><?= htmlspecialchars($item['ShopName']) ?></div>
                    <div class="item-name"><?= htmlspecialchars($item['ItemName']) ?></div>
                    <div class="meta">$<?= number_format((float) $item['Price'], 2) ?></div>
                    <div class="meta <?= $stockClass ?>">
                        Stock: <?= $stock <= 0 ? 'Out of stock' : $stock ?>
                    </div>
                    <form class="buy-form" method="POST">
                        <input type="hidden" name="shop_item_id" value="<?= (int) $item['ShopItemID'] ?>">
                        <label>
                            Quantity
                            <input type="number" name="quantity" min="1" max="<?= max(1, $stock) ?>" value="1" <?= $stock <= 0 ? 'disabled' : '' ?>>
                        </label>
                        <label>
                            Payment
                            <select name="payment_mode" <?= $stock <= 0 ? 'disabled' : '' ?>>
                                <option value="Credit Card">Credit Card</option>
                                <option value="Debit Card">Debit Card</option>
                                <option value="Cash">Cash</option>
                                <option value="PayPal">PayPal</option>
                            </select>
                        </label>
                        <button type="submit" <?= $stock <= 0 ? 'disabled' : '' ?>>Buy Item</button>
                    </form>
                </article>
            <?php endforeach; ?>
        </section>
    </main>
</body>
</html>
