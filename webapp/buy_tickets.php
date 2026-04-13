<?php
session_start();
if (!isset($_SESSION['customer_id'])) {
    header('Location: login.html');
    exit;
}
require 'db.php';

$error = '';
$success = '';

// Fetch ticket categories from database
$categories = $pdo->query("SELECT * FROM ordercategories ORDER BY Price")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $categoryID    = (int)$_POST['category_id'];
    $quantity      = (int)$_POST['quantity'];
    $payment       = $_POST['payment_type'];
    $visit_date    = $_POST['visit_date'];
    $customerID    = $_SESSION['customer_id'];

    if (!$categoryID || !$quantity || !$payment || !$visit_date) {
        $error = 'Please fill in all fields.';
    } else {
        try {
            // Get price from ordercategories
            $cat = $pdo->prepare("SELECT * FROM ordercategories WHERE OrderCategoryID = ?");
            $cat->execute([$categoryID]);
            $category = $cat->fetch();

            $total = $category['Price'] * $quantity;

            // Insert into orders
            $stmt = $pdo->prepare("INSERT INTO orders 
                (OrderDate, CustomerID, OrderCategoryID, PaymentMode, TransactionAmount, ScheduledDate) 
                VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                date('Y-m-d'),
                $customerID,
                $categoryID,
                $payment,
                $total,
                $visit_date
            ]);

            $orderID = $pdo->lastInsertId();

            // Insert into order_tickets
            $stmt2 = $pdo->prepare("INSERT INTO order_tickets (OrderID, OrderCategoryID, Quantity) 
                VALUES (?, ?, ?)");
            $stmt2->execute([$orderID, $categoryID, $quantity]);

            $success = "Successfully purchased $quantity {$category['CategoryName']} ticket(s) for $visit_date! Total: $" . number_format($total, 2);
        } catch (PDOException $e) {
            $error = 'Purchase failed: ' . $e->getMessage();
        }
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
        .tickets-wrapper { max-width: 700px; margin: 2rem auto; padding: 0 1rem; }
        .ticket-card { background: white; border-radius: 20px; padding: 2rem; box-shadow: var(--shadow); }
        .ticket-card h2 { font-size: 1.4rem; font-weight: 800; margin-bottom: 0.5rem; }
        .ticket-card p { margin-bottom: 1.5rem; color: #555; }
        .form-group { display: flex; flex-direction: column; gap: 0.4rem; margin-bottom: 1rem; }
        .form-group label { font-weight: 600; font-size: 0.9rem; }
        .form-group input, .form-group select { padding: 0.65rem 1rem; border: 2px solid #ddd; border-radius: 10px; font: inherit; }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: var(--accent-color); }
        .price-display { background: var(--base-color); border-radius: 10px; padding: 1rem; margin-bottom: 1rem; font-weight: 600; }
        .buy-btn { width: 100%; padding: 0.85rem; background: var(--accent-color); border: none; border-radius: 1000px; font: inherit; font-weight: 700; font-size: 1rem; cursor: pointer; color: white; text-transform: uppercase; }
        .buy-btn:hover { background: var(--text-color); }
        .msg-error { color: #e74c3c; font-weight: 600; margin-bottom: 1rem; }
        .msg-success { color: #27ae60; font-weight: 600; margin-bottom: 1rem; }
        .price-table { width: 100%; border-collapse: collapse; margin-bottom: 1.5rem; font-size: 0.9rem; }
        .price-table th { background: var(--accent-color); color: white; padding: 8px 12px; text-align: left; }
        .price-table td { padding: 8px 12px; border-bottom: 1px solid #eee; }
        .back-link { display: inline-block; margin-bottom: 1rem; color: var(--text-color); font-weight: 600; }
    </style>
</head>
<body>
    <header class="site-header">
        <a class="logo" href="index.php">Greenwood Zoo</a>
        <nav aria-label="Main">
            <ul class="nav-links">
                <li><a href="index.php">Home</a></li>
                <li><a href="customer_profile.php">My Profile</a></li>
                <li><a href="customer_animals_report.php">Animals</a></li>
                <li><span>Welcome, <?= htmlspecialchars($_SESSION['firstname']) ?></span></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </nav>
    </header>

    <div class="tickets-wrapper">
        <a class="back-link" href="index.php">← Back to Home</a>
        <div class="ticket-card">
            <h2>Buy Tickets</h2>
            <p>Purchase tickets for your upcoming visit to Greenwood Zoo!</p>

            <!-- Price table pulled from database -->
            <table class="price-table">
                <tr>
                    <th>Ticket Type</th>
                    <th>Price</th>
                </tr>
                <?php foreach ($categories as $cat): ?>
                <tr>
                    <td><?= htmlspecialchars($cat['CategoryName']) ?></td>
                    <td><?= $cat['Price'] == 0 ? 'Free' : '$' . number_format($cat['Price'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </table>

            <?php if ($error): ?>
                <p class="msg-error"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>
            <?php if ($success): ?>
                <p class="msg-success"><?= htmlspecialchars($success) ?></p>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>Ticket Type</label>
                    <select name="category_id" required onchange="updatePrice()">
                        <option value="">-- Select --</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['OrderCategoryID'] ?>" 
                                data-price="<?= $cat['Price'] ?>">
                            <?= htmlspecialchars($cat['CategoryName']) ?> 
                            — <?= $cat['Price'] == 0 ? 'Free' : '$' . number_format($cat['Price'], 2) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Quantity</label>
                    <input type="number" name="quantity" min="1" max="20" value="1" required onchange="updatePrice()">
                </div>
                <div class="form-group">
                    <label>Visit Date</label>
                    <input type="date" name="visit_date" min="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="form-group">
                    <label>Payment Type</label>
                    <select name="payment_type" required>
                        <option value="">-- Select --</option>
                        <option value="Credit Card">Credit Card</option>
                        <option value="Debit Card">Debit Card</option>
                        <option value="Cash">Cash</option>
                        <option value="PayPal">PayPal</option>
                    </select>
                </div>

                <div class="price-display" id="price-display">
                    Total: Select a ticket type
                </div>

                <button type="submit" class="buy-btn">Purchase Tickets</button>
            </form>
        </div>
    </div>

    <script>
        function updatePrice() {
            const select = document.querySelector('[name="category_id"]');
            const qty = parseInt(document.querySelector('[name="quantity"]').value) || 1;
            const selected = select.options[select.selectedIndex];
            const price = parseFloat(selected.dataset.price) || 0;
            const total = (price * qty).toFixed(2);
            const display = document.getElementById('price-display');

            if (select.value) {
                display.textContent = `Total: $${total} (${qty} x $${price.toFixed(2)})`;
            } else {
                display.textContent = 'Total: Select a ticket type';
            }
        }
    </script>
</body>
</html>