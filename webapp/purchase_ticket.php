<?php
session_start();

if (!isset($_SESSION['customer_id'])) {
    header('Location: customer-login.html');
    exit;
}

require 'db.php';

$prices = [
    'Adult' => 29.99,
    'Child' => 18.99,
    'Senior' => 22.99,
    'Student' => 21.99,
];

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ticketType = trim($_POST['ticket_type'] ?? '');
    $visitDate = trim($_POST['visit_date'] ?? '');
    $paymentType = trim($_POST['payment_type'] ?? '');

    if ($ticketType === '' || !isset($prices[$ticketType])) {
        $error = 'Please choose a valid ticket type.';
    } elseif ($visitDate === '') {
        $error = 'Please choose a visit date.';
    } elseif ($paymentType === '') {
        $error = 'Please choose a payment method.';
    } else {
        $vd = DateTime::createFromFormat('Y-m-d', $visitDate);
        if (!$vd || $vd->format('Y-m-d') !== $visitDate) {
            $error = 'Visit date is invalid.';
        } elseif ($vd < new DateTime('today')) {
            $error = 'Visit date must be today or in the future.';
        }
    }

    if ($error === '') {
        $price = $prices[$ticketType];
        $purchaseDate = date('Y-m-d');
        $cid = (int) $_SESSION['customer_id'];

        $inserted = false;

        $attempts = [
            '(Ticket_type, Price, Payment_type, Visit_date, Purchase_date, CustomerID) VALUES (?, ?, ?, ?, ?, ?)',
            '(Ticket_type, Price, Payment_type, Visit_date, Purchase_date, Customer_ID) VALUES (?, ?, ?, ?, ?, ?)',
        ];

        foreach ($attempts as $suffix) {
            try {
                $sql = 'INSERT INTO tickets ' . $suffix;
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$ticketType, $price, $paymentType, $visitDate, $purchaseDate, $cid]);
                $inserted = true;
                break;
            } catch (PDOException $e) {
                continue;
            }
        }

        if ($inserted) {
            header('Location: customer_tickets_report.php?purchased=1');
            exit;
        }

        $error = 'We could not save your ticket. Please try again or contact guest services.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase tickets — Greenwood Zoo</title>
    <link rel="stylesheet" href="customer-reports.css">
    <style>
        .cr-form-card { padding: 1.5rem clamp(1rem, 3vw, 2rem) 2rem; }
        .cr-form { max-width: 420px; }
        .cr-field { margin-bottom: 1.15rem; }
        .cr-field label { display: block; font-weight: 600; font-size: 0.88rem; margin-bottom: 0.35rem; color: var(--cr-text); }
        .cr-field select, .cr-field input[type="date"] {
            width: 100%; max-width: 100%; padding: 0.65rem 0.75rem; border: 1px solid var(--cr-border);
            border-radius: 8px; font: inherit; box-sizing: border-box;
        }
        .cr-price-hint { font-size: 0.85rem; color: var(--cr-muted); margin-top: 0.35rem; }
        .cr-error { background: #fdecea; color: #7f1d1d; padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 1rem; font-size: 0.9rem; }
        .cr-submit {
            margin-top: 0.5rem; padding: 0.75rem 1.5rem; border: none; border-radius: 999px;
            background: var(--cr-accent); color: #fff; font: inherit; font-weight: 700; cursor: pointer;
        }
        .cr-submit:hover { filter: brightness(1.08); }
        .cr-note { font-size: 0.8rem; color: var(--cr-muted); margin-top: 1.25rem; line-height: 1.45; }
    </style>
</head>
<body class="cr-body">
    <div class="cr-shell">
        <header class="cr-topbar">
            <span class="cr-brand">Greenwood Zoo</span>
            <nav class="cr-nav" aria-label="Navigation">
                <a href="customer-dashboard.php">Dashboard</a>
                <a href="customer_animals_report.php">Animals</a>
                <a href="customer_tickets_report.php">My tickets</a>
                <a class="cr-btn-outline" href="logout.php">Sign out</a>
            </nav>
        </header>

        <main>
            <div class="cr-hero">
                <h1>Purchase tickets</h1>
                <p>Choose a ticket type and visit date. Your purchase is saved to your account.</p>
            </div>

            <div class="cr-card cr-form-card">
                <?php if ($error !== ''): ?>
                    <p class="cr-error"><?= htmlspecialchars($error) ?></p>
                <?php endif; ?>

                <form class="cr-form" method="post" action="purchase_ticket.php" novalidate>
                    <div class="cr-field">
                        <label for="ticket_type">Ticket type</label>
                        <select id="ticket_type" name="ticket_type" required>
                            <option value="">— Select —</option>
                            <?php foreach ($prices as $label => $amt): ?>
                                <option value="<?= htmlspecialchars($label) ?>"><?= htmlspecialchars($label) ?> — $<?= number_format($amt, 2) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="cr-price-hint">Prices include general zoo admission for one day.</p>
                    </div>

                    <div class="cr-field">
                        <label for="visit_date">Visit date</label>
                        <input type="date" id="visit_date" name="visit_date" required
                               min="<?= htmlspecialchars(date('Y-m-d')) ?>"
                               value="<?= htmlspecialchars($_POST['visit_date'] ?? '') ?>">
                    </div>

                    <div class="cr-field">
                        <label for="payment_type">Payment method</label>
                        <select id="payment_type" name="payment_type" required>
                            <option value="">— Select —</option>
                            <option value="Credit card">Credit card</option>
                            <option value="Debit card">Debit card</option>
                            <option value="PayPal">PayPal</option>
                        </select>
                    </div>

                    <button type="submit" class="cr-submit">Complete purchase</button>
                </form>

                <p class="cr-note">This is a demo checkout. No real payment is processed. The ticket is stored in the zoo database for your account.</p>
            </div>
        </main>
    </div>
</body>
</html>
