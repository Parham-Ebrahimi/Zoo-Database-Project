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
    $shopID = (int) ($_POST['shop_id'] ?? 0);
    $itemID = (int) ($_POST['shop_item_id'] ?? 0);
    $quantity = (int) ($_POST['quantity'] ?? 1);
    $paymentMode = trim((string) ($_POST['payment_mode'] ?? ''));

    $validPaymentModes = ['Credit Card', 'Debit Card', 'Cash', 'PayPal'];

    if ($customerID <= 0 || $shopID <= 0 || $itemID <= 0 || $quantity <= 0 || !in_array($paymentMode, $validPaymentModes, true)) {
        $error = 'Please complete all required fields.';
    } else {
        try {
            $pdo->beginTransaction();

            $itemStmt = $pdo->prepare('SELECT ItemName, Price FROM shop_items WHERE ShopItemID = ? AND ShopID = ?');
            $itemStmt->execute([$itemID, $shopID]);
            $item = $itemStmt->fetch(PDO::FETCH_ASSOC);
            if (!$item) {
                throw new RuntimeException('Selected item does not match the gift type.');
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
    SELECT si.ShopItemID, si.ShopID, si.ItemName, si.Price, si.StockQty, s.ShopName
    FROM shop_items si
    JOIN shops s ON s.ShopID = si.ShopID
    ORDER BY s.ShopName, si.ItemName
")->fetchAll(PDO::FETCH_ASSOC);
$giftTypes = $pdo->query("
    SELECT ShopID, ShopName
    FROM shops
    ORDER BY ShopName
")->fetchAll(PDO::FETCH_ASSOC);
?>
<?php $firstname = htmlspecialchars($_SESSION['firstname'] ?? 'Staff'); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Record Gift Shop Sale</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .gs-shell { box-sizing: border-box; min-height: 100vh; padding: clamp(18px, 3vw, 36px); background: linear-gradient(165deg, rgba(187, 223, 158, 0.55) 0%, rgba(187, 223, 158, 0.92) 42%, var(--base-color) 100%); }
        .gs-inner { max-width: 880px; margin: 0 auto; width: 100%; box-sizing: border-box; }
        .gs-header { display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 22px; padding-bottom: 18px; border-bottom: 3px solid var(--accent-color); }
        .gs-header h1 { margin: 0 0 6px; font-size: clamp(1.35rem, 2.5vw, 1.75rem); font-weight: 800; color: var(--text-color); letter-spacing: -0.02em; }
        .gs-meta { margin-top: 18px; font-size: 0.8rem; color: #888; }
        .gs-back { display: inline-flex; align-items: center; gap: 6px; padding: 10px 20px; border-radius: 999px; background: #fff; border: 2px solid var(--accent-color); color: var(--text-color); font-weight: 700; font-size: 0.88rem; text-decoration: none; box-shadow: 0 2px 8px rgba(46, 90, 26, 0.1); transition: background 0.15s, transform 0.15s; }
        .gs-back:hover { background: var(--accent-color); color: #fff; text-decoration: none; }
        .gs-card { background: #fff; border-radius: 16px; padding: clamp(20px, 3vw, 28px); box-shadow: 0 8px 32px rgba(26, 61, 28, 0.1); border: 1px solid rgba(46, 90, 26, 0.12); }
        .gs-alert { display: flex; align-items: flex-start; gap: 12px; padding: 14px 16px; border-radius: 12px; margin-bottom: 20px; font-size: 0.92rem; line-height: 1.45; }
        .gs-alert .ico { font-size: 1.25rem; line-height: 1; flex-shrink: 0; }
        .gs-alert.ok { background: linear-gradient(135deg, #e8f8e9 0%, #d4edc9 100%); border: 1px solid #a3d49a; color: #1a4a1a; }
        .gs-alert.bad { background: #fff5f5; border: 1px solid #f0b4b4; color: #7a1e1e; }
        .gs-section-title { margin: 0 0 14px; font-size: 0.72rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; color: #5a6b52; }
        .gs-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px 18px; }
        .gs-field { display: flex; flex-direction: column; gap: 6px; }
        .gs-field--full { grid-column: 1 / -1; }
        .gs-field label { font-size: 0.86rem; font-weight: 700; color: var(--text-color); }
        .gs-field input, .gs-field select { width: 100%; box-sizing: border-box; padding: 11px 14px; border: 2px solid #d5e5cd; border-radius: 10px; font: inherit; font-size: 0.95rem; background: #fbfcfa; transition: border-color 0.15s, box-shadow 0.15s, background 0.15s; }
        .gs-field input:focus, .gs-field select:focus { outline: none; border-color: var(--accent-color); background: #fff; box-shadow: 0 0 0 3px rgba(76, 145, 65, 0.2); }
        .gs-actions { margin-top: 26px; display: flex; flex-wrap: wrap; align-items: center; gap: 12px; }
        .gs-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 12px 26px; border-radius: 999px; font: inherit; font-weight: 700; font-size: 0.92rem; cursor: pointer; border: none; text-decoration: none; transition: background 0.15s, transform 0.1s; }
        .gs-btn:active { transform: scale(0.98); }
        .gs-btn--primary { background: var(--accent-color); color: #fff; box-shadow: 0 4px 14px rgba(46, 90, 26, 0.35); }
        .gs-btn--primary:hover { background: var(--text-color); color: #fff; }
        @media (max-width: 760px) { .gs-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="gs-shell">
        <div class="gs-inner">
            <header class="gs-header">
                <div>
                    <h1>Record Gift Shop Sale</h1>
                    <p class="gs-meta">Signed in as <?= $firstname ?></p>
                </div>
                <div class="gs-header-actions">
                    <?php include __DIR__ . '/admin_header_cart_profile.inc.php'; ?>
                    <?php if ($role === 'admin'): ?>
                    <a href="logout.php" class="gs-back">Logout</a>
                    <?php endif; ?>
                    <a class="gs-back" href="<?= htmlspecialchars($dashboardBackHref) ?>">← Back to dashboard</a>
                </div>
            </header>

            <div class="gs-card">
                <?php if ($success !== ''): ?>
                    <div class="gs-alert ok" role="status">
                        <span class="ico" aria-hidden="true">✓</span>
                        <div><?= htmlspecialchars($success) ?></div>
                    </div>
                <?php endif; ?>
                <?php if ($error !== ''): ?>
                    <div class="gs-alert bad" role="alert">
                        <span class="ico" aria-hidden="true">!</span>
                        <div><?= htmlspecialchars($error) ?></div>
                    </div>
                <?php endif; ?>

                <form method="POST" id="record-sale-form">
                    <h2 class="gs-section-title">Sale details</h2>
                    <div class="gs-grid">
                        <div class="gs-field gs-field--full">
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

                        <div class="gs-field">
                            <label for="shop_id">Gift Type</label>
                            <select id="shop_id" name="shop_id" required>
                                <option value="">Select gift type</option>
                                <?php foreach ($giftTypes as $t): ?>
                                    <option value="<?= (int) $t['ShopID'] ?>">
                                        <?= htmlspecialchars($t['ShopName']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="gs-field">
                            <label for="shop_item_id">Item</label>
                            <select id="shop_item_id" name="shop_item_id" required>
                                <option value="">Select item</option>
                                <?php foreach ($items as $i): ?>
                                    <option value="<?= (int) $i['ShopItemID'] ?>" data-shop-id="<?= (int) $i['ShopID'] ?>">
                                        <?= htmlspecialchars($i['ShopName']) ?> - <?= htmlspecialchars($i['ItemName']) ?> ($<?= number_format((float) $i['Price'], 2) ?>, stock: <?= (int) $i['StockQty'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="gs-field">
                            <label for="quantity">Quantity</label>
                            <input id="quantity" type="number" name="quantity" min="1" value="1" required>
                        </div>

                        <div class="gs-field">
                            <label for="payment_mode">Payment</label>
                            <select id="payment_mode" name="payment_mode" required>
                                <option value="Credit Card">Credit Card</option>
                                <option value="Debit Card">Debit Card</option>
                                <option value="Cash">Cash</option>
                                <option value="PayPal">PayPal</option>
                            </select>
                        </div>
                    </div>

                    <div class="gs-actions">
                        <button class="gs-btn gs-btn--primary" type="submit">Record sale</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script>
        (function () {
            const typeSelect = document.getElementById('shop_id');
            const itemSelect = document.getElementById('shop_item_id');
            if (!typeSelect || !itemSelect) return;

            function filterItemsByType() {
                const selectedType = typeSelect.value;
                itemSelect.value = '';
                Array.from(itemSelect.options).forEach((opt, idx) => {
                    if (idx === 0) return; // keep placeholder
                    const shopId = opt.getAttribute('data-shop-id');
                    const show = selectedType !== '' && shopId === selectedType;
                    opt.hidden = !show;
                    opt.disabled = !show;
                });
            }

            typeSelect.addEventListener('change', filterItemsByType);
            filterItemsByType();
        })();
    </script>
</body>
</html>
