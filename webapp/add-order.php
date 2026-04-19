<?php
require_once __DIR__ . '/session_bootstrap.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit;
}
require_once 'db.php';

$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['admin', 'Gift Shop Employee'], true)) {
    header('Location: dashboard.php');
    exit;
}
$dashboardBackHref = $role === 'Gift Shop Employee'
    ? 'dashboard.php#gift-shop'
    : 'dashboard.php#gift-shop-admin';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customerID = (int) ($_POST['customer_id'] ?? 0);
    $itemID = (int) ($_POST['shop_item_id'] ?? 0);
    $quantity = (int) ($_POST['quantity'] ?? 1);
    $paymentMode = trim((string) ($_POST['payment_mode'] ?? ''));

    $validPaymentModes = ['Credit Card', 'Debit Card', 'Cash', 'PayPal'];

    if ($customerID <= 0 || $itemID <= 0 || $quantity <= 0 || !in_array($paymentMode, $validPaymentModes, true)) {
        $error = 'Please complete all required fields.';
    } else {
        try {
            $pdo->beginTransaction();

            $itemStmt = $pdo->prepare('SELECT ItemName, Price FROM shop_items WHERE ShopItemID = ?');
            $itemStmt->execute([$itemID]);
            $item = $itemStmt->fetch(PDO::FETCH_ASSOC);
            if (!$item) {
                throw new RuntimeException('Selected item not found.');
            }

            $total = round((float) $item['Price'] * $quantity, 2);

            $orderStmt = $pdo->prepare("
                INSERT INTO orders (OrderDate, CustomerID, OrderCategoryID, PaymentMode, TransactionAmount, ScheduledDate)
                VALUES (CURDATE(), ?, 6, ?, ?, NULL)
            ");
            $orderStmt->execute([$customerID, $paymentMode, $total]);
            $orderID = (int) $pdo->lastInsertId();

            // Trigger handles stock validation/deduction.
            $lineStmt = $pdo->prepare('INSERT INTO order_shop_items (OrderID, ShopItemID, Quantity) VALUES (?, ?, ?)');
            $lineStmt->execute([$orderID, $itemID, $quantity]);

            $pdo->commit();
            $success = "Sale recorded successfully (Order #{$orderID}).";
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = str_contains(strtolower($e->getMessage()), 'stock')
                ? 'Could not record sale: item is out of stock or quantity is too high.'
                : 'Could not record sale. Please try again.';
        }
    }
}

$customers = $pdo->query("
    SELECT CustomerID, FirstName, LastName, Email
    FROM customers
    ORDER BY FirstName, LastName, CustomerID
")->fetchAll(PDO::FETCH_ASSOC);

$items = $pdo->query("
    SELECT si.ShopItemID, si.ItemName, si.Price, si.StockQty, s.ShopName
    FROM shop_items si
    JOIN shops s ON s.ShopID = si.ShopID
    ORDER BY s.ShopName, si.ItemName
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Record Gift Shop Sale</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .page-wrap { min-height: 100vh; padding: 30px 40px; background-color: var(--base-color); text-align: left; }
        .panel { background: #fff; border-radius: 14px; padding: 24px; max-width: 760px; }
        .panel form {
            width: 100%;
            margin: 0;
            display: block;
        }
        .panel form > div {
            width: auto;
            display: block;
            justify-content: initial;
        }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .field { display: flex; flex-direction: column; gap: 6px; }
        .field label {
            display: block;
            width: auto;
            height: auto;
            background: transparent;
            color: var(--text-color);
            border-radius: 0;
            padding: 0;
            font-weight: 600;
            font-size: 0.9rem;
            text-align: left;
        }
        .field select, .field input {
            width: 100%;
            height: auto;
            padding: 9px 10px;
            border: 1px solid #cdddc4;
            border-radius: 8px;
            font: inherit;
            background: #fff;
        }
        .field select:focus, .field input:focus {
            outline: none;
            border-color: var(--accent-color);
        }
        .actions { margin-top: 14px; display: flex; gap: 10px; align-items: center; }
        .btn { padding: 9px 14px; border-radius: 999px; border: none; font: inherit; cursor: pointer; font-weight: 600; background: var(--accent-color); color: var(--text-color); }
        .back-btn { display: inline-block; margin-bottom: 14px; text-decoration: none; font-weight: 700; }
        .msg { margin-bottom: 12px; padding: 8px 10px; border-radius: 8px; }
        .ok { background: #e8f8e9; color: #1f6d1f; }
        .bad { background: #ffeaea; color: #8a1111; }
        @media (max-width: 760px) {
            .grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="page-wrap">
        <h1>Record Gift Shop Sale</h1>
        <a class="btn back-btn" href="<?= htmlspecialchars($dashboardBackHref) ?>">Back to dashboard</a>

        <div class="panel">
            <?php if ($success !== ''): ?><div class="msg ok"><?= htmlspecialchars($success) ?></div><?php endif; ?>
            <?php if ($error !== ''): ?><div class="msg bad"><?= htmlspecialchars($error) ?></div><?php endif; ?>

            <form method="POST">
                <div class="grid">
                    <div class="field">
                        <label for="customer_id">Customer</label>
                        <select id="customer_id" name="customer_id" required>
                            <option value="">Select customer</option>
                            <?php foreach ($customers as $c): ?>
                                <option value="<?= (int) $c['CustomerID'] ?>">
                                    #<?= (int) $c['CustomerID'] ?> - <?= htmlspecialchars($c['FirstName'] . ' ' . $c['LastName']) ?> (<?= htmlspecialchars($c['Email']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field">
                        <label for="shop_item_id">Item</label>
                        <select id="shop_item_id" name="shop_item_id" required>
                            <option value="">Select item</option>
                            <?php foreach ($items as $i): ?>
                                <option value="<?= (int) $i['ShopItemID'] ?>">
                                    <?= htmlspecialchars($i['ShopName']) ?> - <?= htmlspecialchars($i['ItemName']) ?> ($<?= number_format((float) $i['Price'], 2) ?>, stock: <?= (int) $i['StockQty'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field">
                        <label for="quantity">Quantity</label>
                        <input id="quantity" type="number" name="quantity" min="1" value="1" required>
                    </div>

                    <div class="field">
                        <label for="payment_mode">Payment</label>
                        <select id="payment_mode" name="payment_mode" required>
                            <option value="Credit Card">Credit Card</option>
                            <option value="Debit Card">Debit Card</option>
                            <option value="Cash">Cash</option>
                            <option value="PayPal">PayPal</option>
                        </select>
                    </div>
                </div>

                <div class="actions">
                    <button class="btn" type="submit">Record Sale</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
