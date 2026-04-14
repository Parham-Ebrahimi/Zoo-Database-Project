<?php
session_start();
if (!isset($_SESSION['customer_id'])) {
    header('Location: login.html');
    exit;
}
require 'db.php';

$id = $_SESSION['customer_id'];

// Fetch customer info
$stmt = $pdo->prepare("SELECT * FROM customers WHERE CustomerID = ?");
$stmt->execute([$id]);
$customer = $stmt->fetch();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $firstname = trim($_POST['firstname']);
    $lastname  = trim($_POST['lastname']);
    $phone     = trim($_POST['phone']);
    $country   = trim($_POST['countrycode']);

    $stmt = $pdo->prepare("UPDATE customers SET FirstName=?, LastName=?, PhoneNumber=?, CountryCode=? WHERE CustomerID=?");
    $stmt->execute([$firstname, $lastname, $phone, $country, $id]);
    $success = 'Profile updated successfully!';

    // Refresh customer data
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE CustomerID = ?");
    $stmt->execute([$id]);
    $customer = $stmt->fetch();
}

// Fetch their tickets
$tickets = $pdo->prepare("
    SELECT o.OrderID, oc.CategoryName, o.TransactionAmount, 
           o.PaymentMode, o.ScheduledDate, o.OrderDate,
           ot.Quantity
    FROM orders o
    JOIN ordercategories oc ON o.OrderCategoryID = oc.OrderCategoryID
    JOIN order_tickets ot ON ot.OrderID = o.OrderID
    WHERE o.CustomerID = ?
    ORDER BY o.OrderDate DESC
");
$tickets->execute([$id]);
$myTickets = $tickets->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile — Greenwood Zoo</title>
    <link rel="stylesheet" href="index.css">
    <style>
        .profile-wrapper { 
            max-width: 900px; 
            margin: 2rem auto; 
            padding: 0 1rem; 
        }
        .profile-card { 
            background: white; 
            border-radius: 20px; 
            padding: 2rem; 
            box-shadow: var(--shadow); 
            margin-bottom: 2rem; 
        }
        .profile-card h2 { 
            font-size: 1.4rem; 
            font-weight: 800; 
            margin-bottom: 1.5rem; 
            padding-bottom: 0.75rem; 
            border-bottom: 2px solid var(--accent-color); 
        }
        .form-grid { 
            display: grid; 
            grid-template-columns: 1fr 1fr; 
            gap: 1rem; 
        }
        .form-group { 
            display: flex; 
            flex-direction: column; 
            gap: 0.4rem; 
        }
        .form-group label { 
            font-weight: 600; 
            font-size: 0.9rem; 
        }
        .form-group input { 
            padding: 0.65rem 1rem; 
            border: 2px solid #ddd; 
            border-radius: 10px; 
            font: inherit; 
        }
        .form-group input:focus { 
            outline: none; 
            border-color: var(--accent-color); 
        }
        .form-group input[readonly] { 
            background: #f5f5f5; 
            cursor: not-allowed;
         }
        .save-btn { 
            margin-top: 1rem; 
            padding: 0.75rem 2rem; 
            background: var(--accent-color); 
            border: none; 
            border-radius: 1000px; 
            font: inherit; 
            font-weight: 600; 
            cursor: pointer; 
            color: white; 
        }
        .save-btn:hover { 
            background: var(--text-color); 
        }
        .msg-success { 
            color: #27ae60; 
            font-weight: 600; 
            margin-bottom: 1rem; 
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            font-size: 0.9rem; 
        }
        th { 
            background: var(--accent-color); 
            color: white; 
            padding: 10px 14px; 
            text-align: left; 
        }
        td { 
            padding: 9px 14px; 
            border-bottom: 1px solid #eee; 
        }
        tr:hover td { 
            background: #f9fff9; 
        }
        .badge { 
            display: inline-block; 
            padding: 3px 10px; 
            border-radius: 1000px; 
            font-size: 0.8rem; 
            font-weight: 600; 
            background: var(--base-color); 
            color: var(--text-color); 
        }
        .back-link { 
            display: inline-block; 
            margin-bottom: 1rem; 
            color: var(--text-color); 
            font-weight: 600; 
        }
        .back-link:hover { 
            text-decoration: underline; 
        }
    </style>
</head>
<body>
    <header class="site-header">
        <a class="logo" href="index.php">Greenwood Zoo</a>
        <nav aria-label="Main">
            <ul class="nav-links">
                <li><a href="index.php">Home</a></li>
                <li><a href="buy-tickets.php">Buy Tickets</a></li>
                <li><a href="customer_animals_report.php">Animals</a></li>
                <li><span>Welcome, <?= htmlspecialchars($_SESSION['firstname']) ?></span></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </nav>
    </header>

    <div class="profile-wrapper">
        <a class="back-link" href="customer-dashboard.php">← Back to Dashboard</a>

        <div class="profile-card">
            <h2>My Profile</h2>
            <?php if (!empty($success)): ?>
                <p class="msg-success"><?= $success ?></p>
            <?php endif; ?>
            <form method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label>First Name</label>
                        <input type="text" name="firstname" value="<?= htmlspecialchars($customer['FirstName']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Last Name</label>
                        <input type="text" name="lastname" value="<?= htmlspecialchars($customer['LastName']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Email (cannot change)</label>
                        <input type="email" value="<?= htmlspecialchars($customer['Email']) ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="text" name="phone" value="<?= htmlspecialchars($customer['PhoneNumber']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Country Code</label>
                        <input type="text" name="countrycode" value="<?= htmlspecialchars($customer['CountryCode']) ?>" maxlength="5">
                    </div>
                    <div class="form-group">
                        <label>Member Since</label>
                        <input type="text" value="<?= $customer['RegistrationDate'] ?>" readonly>
                    </div>
                </div>
                <button type="submit" name="update_profile" class="save-btn">Save Changes</button>
            </form>
        </div>

        <div class="profile-card">
            <h2>My Tickets</h2>
            <?php if (count($myTickets) === 0): ?>
                <p>You haven't purchased any tickets yet. <a href="buy-tickets.php">Buy tickets here!</a></p>
            <?php else: ?>
            <table>
                <tr>
                    <th>Order #</th>
                    <th>Type</th>
                    <th>Quantity</th>
                    <th>Total Paid</th>
                    <th>Payment</th>
                    <th>Visit Date</th>
                    <th>Ordered</th>
                </tr>
                <?php foreach ($myTickets as $t): ?>
                <tr>
                    <td>#<?= $t['OrderID'] ?></td>
                    <td><span class="badge"><?= htmlspecialchars($t['CategoryName']) ?></span></td>
                    <td><?= $t['Quantity'] ?></td>
                    <td>$<?= number_format($t['TransactionAmount'], 2) ?></td>
                    <td><?= htmlspecialchars($t['PaymentMode']) ?></td>
                    <td><?= $t['ScheduledDate'] ?></td>
                    <td><?= $t['OrderDate'] ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>