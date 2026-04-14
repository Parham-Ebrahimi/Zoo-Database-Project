<?php
session_start();
if (!isset($_SESSION['customer_id'])) {
    header('Location: sign-in.html');
    exit;
}
require_once 'db.php';

$error   = '';
$success = '';

// Zoo visit ticket types only (must match order_tickets CHECK: categories 1–4; not Food/Shop)
$categories = $pdo->query("
    SELECT OrderCategoryID, CategoryName, Price
    FROM ordercategories
    WHERE OrderCategoryID BETWEEN 1 AND 4
    ORDER BY OrderCategoryID
")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customerID  = (int) $_SESSION['customer_id'];
    $categoryID  = (int) $_POST['category_id'];
    $quantity    = (int) $_POST['quantity'];
    $paymentMode = trim($_POST['payment_mode']);
    $visitDate   = $_POST['visit_date'];

    // Validate
    if (!$categoryID || !$quantity || !$paymentMode || !$visitDate) {
        $error = 'Please fill in all fields.';
    } elseif ($categoryID < 1 || $categoryID > 4) {
        $error = 'Please select a valid zoo ticket type (Adult, Child, Senior, or Student).';
    } elseif ($quantity < 1 || $quantity > 20) {
        $error = 'Quantity must be between 1 and 20.';
    } elseif (strtotime($visitDate) < strtotime('today')) {
        $error = 'Visit date cannot be in the past.';
    } else {
        // Get price for selected category
        $stmt = $pdo->prepare("SELECT Price FROM ordercategories WHERE OrderCategoryID = ?");
        $stmt->execute([$categoryID]);
        $price = $stmt->fetchColumn();

        if (!$price) {
            $error = 'Invalid ticket type selected.';
        } else {
            $total = $price * $quantity;

            try {
                $pdo->beginTransaction();

                // Insert into orders
                $stmt = $pdo->prepare("
                    INSERT INTO orders (OrderDate, CustomerID, OrderCategoryID, PaymentMode, TransactionAmount, ScheduledDate)
                    VALUES (CURDATE(), ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$customerID, $categoryID, $paymentMode, $total, $visitDate]);
                $orderID = $pdo->lastInsertId();

                // Insert into order_tickets
                $stmt = $pdo->prepare("
                    INSERT INTO order_tickets (OrderID, OrderCategoryID, Quantity)
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$orderID, $categoryID, $quantity]);

                $pdo->commit();
                $success = 'Order placed! ' . (int) $quantity . ' × ' . htmlspecialchars($_POST['category_name'] ?? 'ticket')
                    . ' for ' . date('M j, Y', strtotime($visitDate)) . '. Total: $' . number_format($total, 2);

            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Order failed: ' . $e->getMessage();
            }
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
    .form-card {
        background: white;
        border-radius: 20px;
        padding: 2rem;
        max-width: 560px;
        box-shadow: var(--shadow);
        border: none;
    }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: 0.4rem;
        margin-bottom: 1rem;
    }

    .form-group label {
        font-weight: 600;
        font-size: 0.9rem;
        color: var(--text-color);
    }

    .form-group select,
    .form-group input {
        padding: 0.65rem 1rem;
        border: 2px solid #ddd;
        border-radius: 10px;
        font: inherit;
        font-size: 0.95rem;
        background: white;
    }

    .form-group select:focus,
    .form-group input:focus {
        outline: none;
        border-color: var(--accent-color);
    }

    .ticket-price {
        font-size: 0.85rem;
        color: #777;
    }

    .total-row {
        margin: 1.2rem 0;
        padding: 0.85rem 1rem;
        background: #f5f5f5;
        border-radius: 10px;
        font-weight: 600;
        color: var(--text-color);
        font-size: 1rem;
    }

    .submit-btn {
        margin-top: 0.5rem;
        padding: 0.75rem 2rem;
        background: var(--accent-color);
        border: none;
        border-radius: 1000px;
        font: inherit;
        font-weight: 600;
        cursor: pointer;
        color: white;
    }

    .submit-btn:hover {
        background: var(--text-color);
    }

    .msg-error {
        color: #e74c3c;
        font-weight: 600;
        margin-bottom: 1rem;
    }

    .msg-success {
        color: #27ae60;
        font-weight: 600;
        margin-bottom: 1rem;
    }

    .back-link {
        display: inline-block;
        margin-bottom: 1rem;
        color: var(--text-color);
        font-weight: 600;
        text-decoration: none;
    }

    .back-link:hover {
        text-decoration: underline;
    }
</style>
</head>
<body>
    <div class="profile-wrapper">
        <header class="site-header">
            <a class="logo" href="index.php">Greenwood Zoo</a>
            <nav aria-label="Main">
                <ul class="nav-links">
                    <li><a href="index.php">Home</a></li>
                    <li><a href="buy_tickets.php">Buy Tickets</a></li>
                    <li><a href="customer_animals_report.php">Animals</a></li>
                    <li><a href="customer_profile.php">Profile</a></li>
                    <li><span>Welcome, <?= htmlspecialchars($_SESSION['firstname']) ?></span></li>
                    <li><a href="logout.php">Logout</a></li>
                </ul>
            </nav>
        </header>

        <div class="profile-card">
             <a class="back-link" href="customer-dashboard.php">← Back to Dashboard</a>
             <h1>Buy tickets</h1>
             <p>Select your ticket type, visit date, and quantity to complete your purchase.</p>
            </div>

            <div class="form-card">
                <?php if ($error):   ?><p class="msg-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
                <?php if ($success): ?><p class="msg-success"><?= $success ?></p><?php endif; ?>

                <form method="POST" id="ticketForm">
                    <div class="form-group">
                        <label for="category_id">Ticket type *</label>
                        <select name="category_id" id="category_id" required onchange="updatePrice()">
                            <option value="">-- Select a ticket type --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['OrderCategoryID'] ?>"
                                        data-price="<?= htmlspecialchars((string) $cat['Price']) ?>"
                                        data-name="<?= htmlspecialchars($cat['CategoryName']) ?>">
                                    <?= htmlspecialchars($cat['CategoryName']) ?> — $<?= number_format((float) $cat['Price'], 2) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="ticket-price" id="priceHint"></span>
                    </div>

                    <!-- Hidden field to pass category name for success message -->
                    <input type="hidden" name="category_name" id="category_name">

                    <div class="form-group">
                        <label for="quantity">Quantity *</label>
                        <input type="number" name="quantity" id="quantity" min="1" max="20" value="1" required onchange="updatePrice()">
                    </div>

                    <div class="form-group">
                        <label for="visit_date">Visit date *</label>
                        <input type="date" name="visit_date" id="visit_date" required
                               min="<?= date('Y-m-d') ?>">
                    </div>

                    <div class="form-group">
                        <label for="payment_mode">Payment method *</label>
                        <select name="payment_mode" id="payment_mode" required>
                            <option value="">-- Select --</option>
                            <option value="Credit Card">Credit Card</option>
                            <option value="Debit Card">Debit Card</option>
                            <option value="Cash">Cash</option>
                            <option value="PayPal">PayPal</option>
                        </select>
                    </div>

                    <div class="total-row" id="totalRow" style="display:none">
                        Total: <span id="totalAmount">$0.00</span>
                    </div>

                    <button type="submit" class="submit-btn">Purchase tickets</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function updatePrice() {
            const select   = document.getElementById('category_id');
            const qty      = parseInt(document.getElementById('quantity').value) || 0;
            const option   = select.options[select.selectedIndex];
            const price    = parseFloat(option.dataset.price) || 0;
            const name     = option.dataset.name || '';
            const total    = price * qty;
            const hint     = document.getElementById('priceHint');
            const totalRow = document.getElementById('totalRow');

            document.getElementById('category_name').value = name;

            if (price > 0) {
                hint.textContent = '$' + price.toFixed(2) + ' per ticket';
                totalRow.style.display = 'block';
                document.getElementById('totalAmount').textContent = '$' + total.toFixed(2);
            } else {
                hint.textContent = '';
                totalRow.style.display = 'none';
            }
        }
    </script>
</body>
</html>