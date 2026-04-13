<?php
session_start();
if (!isset($_SESSION['customer_id'])) {
    header('Location: login.html');
    exit;
}
require 'db.php';

$error = '';
$success = '';

// Ticket prices based on your ordercategories table
$ticketTypes = [
    'Child'  => 12.99,
    'Adult'  => 24.99,
    'Senior' => 18.99,
    'Infant' => 0.00,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type        = $_POST['ticket_type'];
    $payment     = $_POST['payment_type'];
    $visit_date  = $_POST['visit_date'];
    $quantity    = (int)$_POST['quantity'];
    $customerID  = $_SESSION['customer_id'];

    if (empty($type) || empty($payment) || empty($visit_date) || $quantity < 1) {
        $error = 'Please fill in all fields.';
    } elseif (!isset($ticketTypes[$type])) {
        $error = 'Invalid ticket type.';
    } else {
        $price = $ticketTypes[$type];

        try {
            $stmt = $pdo->prepare("INSERT INTO tickets 
                (Ticket_type, Price, Payment_type, Visit_date, CustomerID) 
                VALUES (?, ?, ?, ?, ?)");

            for ($i = 0; $i < $quantity; $i++) {
                $stmt->execute([$type, $price, $payment, $visit_date, $customerID]);
            }

            $success = "Successfully purchased $quantity $type ticket(s) for $visit_date!";
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
        .tickets-wrapper { 
            max-width: 700px; 
            margin: 2rem auto; 
            padding: 0 1rem; 
        }
        .ticket-card { 
            background: white; 
            border-radius: 20px; 
            padding: 2rem; 
            box-shadow: var(--shadow);
         }
        .ticket-card h2 { 
            font-size: 1.4rem; 
            font-weight: 800; 
            margin-bottom: 0.5rem; 
        }
        .ticket-card p { 
            margin-bottom: 1.5rem; 
            color: #555; 
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
        }
        .form-group input, .form-group select { 
            padding: 0.65rem 1rem; 
            border: 2px solid #ddd; 
            border-radius: 10px; 
            font: inherit; 
        }
        .form-group input:focus, .form-group select:focus { 
            outline: none; 
            border-color: var(--accent-color); 
        }
        .price-display { 
            background: var(--base-color); 
            border-radius: 10px; 
            padding: 1rem; 
            margin-bottom: 1rem; 
            font-weight: 600; 
        }
        .buy-btn { 
            width: 100%; 
            padding: 0.85rem; 
            background: var(--accent-color); 
            border: none; 
            border-radius: 1000px; 
            font: inherit; 
            font-weight: 700; 
            font-size: 1rem; 
            cursor: pointer; 
            color: white; 
            text-transform: uppercase; 
        }
        .buy-btn:hover { 
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
        .price-table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-bottom: 1.5rem; 
            font-size: 0.9rem; 
        }
        .price-table th { 
            background: var(--accent-color); 
            color: white; 
            padding: 8px 12px; 
            text-align: left; 
        }
        .price-table td { 
            padding: 8px 12px; 
            border-bottom: 1px solid #eee; 
        }
        .back-link { 
            display: inline-block; 
            margin-bottom: 1rem; 
            color: var(--text-color); 
            font-weight: 600; 
        }
    </style>
</head>
<body>
    <header class="site-header">
        <a class="logo" href="index.php">Greenwood Zoo</a>
        <nav aria-label="Main">
            <ul class="nav-links">
                <li><a href="index.php">Home</a></li>
                <li><a href="profile.php">My Profile</a></li>
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

            <table class="price-table">
                <tr>
                    <th>Ticket Type</th>
                    <th>Price</th>
                    <th>Age Group</th>
                </tr>
                <tr><td>Infant</td><td>Free</td><td>2 & under</td></tr>
                <tr><td>Child</td><td>$12.99</td><td>Ages 3–12</td></tr>
                <tr><td>Adult</td><td>$24.99</td><td>Ages 13–64</td></tr>
                <tr><td>Senior</td><td>$18.99</td><td>Ages 65+</td></tr>
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
                    <select name="ticket_type" required onchange="updatePrice(this.value)">
                        <option value="">-- Select --</option>
                        <option value="Infant">Infant (Free)</option>
                        <option value="Child">Child ($12.99)</option>
                        <option value="Adult">Adult ($24.99)</option>
                        <option value="Senior">Senior ($18.99)</option>
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
                        <option value="Credit">Credit Card</option>
                        <option value="Debit">Debit Card</option>
                        <option value="Cash">Cash</option>
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
        const prices = { Infant: 0, Child: 12.99, Adult: 24.99, Senior: 18.99 };

        function updatePrice() {
            const type = document.querySelector('[name="ticket_type"]').value;
            const qty  = parseInt(document.querySelector('[name="quantity"]').value) || 1;
            const display = document.getElementById('price-display');

            if (type && prices[type] !== undefined) {
                const total = (prices[type] * qty).toFixed(2);
                display.textContent = `Total: $${total} (${qty} x $${prices[type].toFixed(2)})`;
            } else {
                display.textContent = 'Total: Select a ticket type';
            }
        }

        document.querySelector('[name="quantity"]').addEventListener('change', updatePrice);
    </script>
</body>
</html>