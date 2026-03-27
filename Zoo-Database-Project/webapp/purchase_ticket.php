<?php
session_start();

if (!isset($_SESSION['customer_id'])) {
    header('Location: customer-login.html');
    exit;
}

require 'db.php';

/** @var array<int, array{id:int, name:string, price:float}> */
$categories = [];
$categoriesError = null;

try {
    $stmt = $pdo->query('SELECT OrderCategoryID, CategoryName, Price FROM ordercategories ORDER BY OrderCategoryID');
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        $id = (int) ($row['OrderCategoryID'] ?? 0);
        if ($id < 1) {
            continue;
        }
        $categories[$id] = [
            'id' => $id,
            'name' => (string) ($row['CategoryName'] ?? 'Ticket'),
            'price' => (float) ($row['Price'] ?? 0),
        ];
    }
} catch (PDOException $e) {
    $categoriesError = 'Ticket categories could not be loaded. Please try again later.';
}

const STUDENT_CATEGORY_ID = 4;

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $categoriesError === null && count($categories) > 0) {
    $categoryId = (int) ($_POST['order_category_id'] ?? 0);
    $visitDate = trim($_POST['visit_date'] ?? '');
    $paymentType = trim($_POST['payment_type'] ?? '');

    if ($categoryId < 1 || !isset($categories[$categoryId])) {
        $error = 'Please choose a valid ticket category.';
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
        $cat = $categories[$categoryId];
        $ticketType = $cat['name'];
        $price = $cat['price'];
        $purchaseDate = date('Y-m-d');
        $cid = (int) $_SESSION['customer_id'];

        $inserted = false;
        $lastDbError = null;

        $visitDateTime = $visitDate . ' 00:00:00';
        $purchaseDateTime = $purchaseDate . ' 00:00:00';

        $insertAttempts = [
            ['(OrderCategoryID, Ticket_type, Price, Payment_type, Visit_date, Purchase_date, CustomerID) VALUES (?, ?, ?, ?, ?, ?, ?)', [$categoryId, $ticketType, $price, $paymentType, $visitDate, $purchaseDate, $cid]],
            ['(OrderCategoryID, Ticket_type, Price, Payment_type, Visit_date, Purchase_date, Customer_ID) VALUES (?, ?, ?, ?, ?, ?, ?)', [$categoryId, $ticketType, $price, $paymentType, $visitDate, $purchaseDate, $cid]],
            ['(OrderCategory_ID, Ticket_type, Price, Payment_type, Visit_date, Purchase_date, CustomerID) VALUES (?, ?, ?, ?, ?, ?, ?)', [$categoryId, $ticketType, $price, $paymentType, $visitDate, $purchaseDate, $cid]],
            ['(OrderCategory_ID, Ticket_type, Price, Payment_type, Visit_date, Purchase_date, Customer_ID) VALUES (?, ?, ?, ?, ?, ?, ?)', [$categoryId, $ticketType, $price, $paymentType, $visitDate, $purchaseDate, $cid]],
            ['(OrderCategoryID, Ticket_type, Price, Payment_type, Visit_date, Purchase_date, CustomerID) VALUES (?, ?, ?, ?, ?, ?, ?)', [$categoryId, $ticketType, $price, $paymentType, $visitDateTime, $purchaseDateTime, $cid]],
            ['(OrderCategoryID, Ticket_type, Price, Payment_type, Visit_date, Purchase_date, Customer_ID) VALUES (?, ?, ?, ?, ?, ?, ?)', [$categoryId, $ticketType, $price, $paymentType, $visitDateTime, $purchaseDateTime, $cid]],
            ['(Ticket_type, Price, Payment_type, Visit_date, Purchase_date, CustomerID) VALUES (?, ?, ?, ?, ?, ?)', [$ticketType, $price, $paymentType, $visitDate, $purchaseDate, $cid]],
            ['(Ticket_type, Price, Payment_type, Visit_date, Purchase_date, Customer_ID) VALUES (?, ?, ?, ?, ?, ?)', [$ticketType, $price, $paymentType, $visitDate, $purchaseDate, $cid]],
            ['(Ticket_type, Price, Payment_type, Visit_date, Purchase_date, CustomerID) VALUES (?, ?, ?, ?, ?, ?)', [$ticketType, $price, $paymentType, $visitDateTime, $purchaseDateTime, $cid]],
            ['(Ticket_type, Price, Payment_type, Visit_date, Purchase_date, Customer_ID) VALUES (?, ?, ?, ?, ?, ?)', [$ticketType, $price, $paymentType, $visitDateTime, $purchaseDateTime, $cid]],
        ];

        foreach ($insertAttempts as $attempt) {
            [$suffix, $params] = $attempt;
            try {
                $sql = 'INSERT INTO tickets ' . $suffix;
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $inserted = true;
                $lastDbError = null;
                break;
            } catch (PDOException $e) {
                $lastDbError = $e->getMessage();
                continue;
            }
        }

        if (!$inserted) {
            try {
                $meta = $pdo->query('SHOW COLUMNS FROM tickets')->fetchAll(PDO::FETCH_ASSOC);
                $fields = [];
                foreach ($meta as $col) {
                    $fields[$col['Field']] = $col;
                }

                $valueMap = [
                    'OrderCategoryID' => $categoryId,
                    'OrderCategory_ID' => $categoryId,
                    'ordercategoryid' => $categoryId,
                    'Ticket_type' => $ticketType,
                    'Ticket_Type' => $ticketType,
                    'TicketType' => $ticketType,
                    'ticket_type' => $ticketType,
                    'Price' => $price,
                    'price' => $price,
                    'Payment_type' => $paymentType,
                    'Payment_Type' => $paymentType,
                    'Visit_date' => $visitDate,
                    'Visit_Date' => $visitDate,
                    'visit_date' => $visitDate,
                    'Purchase_date' => $purchaseDate,
                    'Purchase_Date' => $purchaseDate,
                    'purchase_date' => $purchaseDate,
                    'CustomerID' => $cid,
                    'Customer_ID' => $cid,
                    'customer_id' => $cid,
                ];

                $insertCols = [];
                $insertVals = [];
                foreach ($fields as $name => $info) {
                    $extra = strtolower((string) ($info['Extra'] ?? ''));
                    if (strpos($extra, 'auto_increment') !== false) {
                        continue;
                    }
                    if (!isset($valueMap[$name])) {
                        continue;
                    }
                    $val = $valueMap[$name];
                    $typeLower = strtolower((string) ($info['Type'] ?? ''));
                    $nameLower = strtolower($name);
                    if (strpos($nameLower, 'visit') !== false && strpos($typeLower, 'date') !== false && strpos($typeLower, 'datetime') !== false) {
                        $val = $visitDateTime;
                    } elseif (strpos($nameLower, 'visit') !== false && strpos($typeLower, 'timestamp') !== false) {
                        $val = $visitDateTime;
                    } elseif (strpos($nameLower, 'purchase') !== false && strpos($typeLower, 'date') !== false && strpos($typeLower, 'datetime') !== false) {
                        $val = $purchaseDateTime;
                    } elseif (strpos($nameLower, 'purchase') !== false && strpos($typeLower, 'timestamp') !== false) {
                        $val = $purchaseDateTime;
                    } elseif ((strpos($typeLower, 'datetime') !== false || strpos($typeLower, 'timestamp') !== false)
                        && is_string($val) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) {
                        $val .= ' 00:00:00';
                    }
                    $insertCols[] = '`' . str_replace('`', '``', $name) . '`';
                    $insertVals[] = $val;
                }

                if (count($insertCols) >= 3) {
                    $placeholders = implode(', ', array_fill(0, count($insertVals), '?'));
                    $sqlDyn = 'INSERT INTO tickets (' . implode(', ', $insertCols) . ') VALUES (' . $placeholders . ')';
                    $st = $pdo->prepare($sqlDyn);
                    $st->execute($insertVals);
                    $inserted = true;
                    $lastDbError = null;
                }
            } catch (PDOException $e) {
                $lastDbError = $e->getMessage();
            }
        }

        if ($lastDbError !== null) {
            error_log('purchase_ticket INSERT failed (last error): ' . $lastDbError);
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
        .cr-form { max-width: 480px; }
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
        .cr-submit:disabled { opacity: 0.55; cursor: not-allowed; filter: none; }
        .cr-note { font-size: 0.8rem; color: var(--cr-muted); margin-top: 1.25rem; line-height: 1.45; }
        .cr-callout {
            display: none;
            margin-top: 0.75rem;
            padding: 0.9rem 1rem;
            border-radius: 10px;
            border: 1px solid #c4d9f0;
            background: linear-gradient(180deg, #f0f7ff 0%, #e8f2fc 100%);
            color: #1e3a5f;
            font-size: 0.88rem;
            line-height: 1.5;
        }
        .cr-callout.is-visible { display: block; }
        .cr-callout strong { display: block; margin-bottom: 0.25rem; font-size: 0.82rem; text-transform: uppercase; letter-spacing: 0.04em; color: #0f3460; }
        .cr-db-fallback { background: #fff8e6; border: 1px solid #f0d78c; color: #6b4f00; padding: 1rem; border-radius: 10px; font-size: 0.9rem; }
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
                <p>Select a category, date, and payment method. Prices match our current ticket catalog.</p>
            </div>

            <div class="cr-card cr-form-card">
                <?php if ($categoriesError !== null): ?>
                    <p class="cr-db-fallback"><?= htmlspecialchars($categoriesError) ?></p>
                <?php elseif (count($categories) === 0): ?>
                    <p class="cr-db-fallback">No ticket categories are available right now. Please check back later.</p>
                <?php else: ?>
                <?php if ($error !== ''): ?>
                    <p class="cr-error"><?= htmlspecialchars($error) ?></p>
                <?php endif; ?>

                <form class="cr-form" method="post" action="purchase_ticket.php" novalidate id="purchase-form">
                    <div class="cr-field">
                        <label for="order_category_id">Ticket category</label>
                        <select id="order_category_id" name="order_category_id" required>
                            <option value="">— Select a category —</option>
                            <?php foreach ($categories as $c): ?>
                                <option
                                    value="<?= (int) $c['id'] ?>"
                                    data-student="<?= $c['id'] === STUDENT_CATEGORY_ID ? '1' : '0' ?>"
                                    <?= (isset($_POST['order_category_id']) && (int) $_POST['order_category_id'] === $c['id']) ? 'selected' : '' ?>
                                >
                                    <?= htmlspecialchars($c['name']) ?> — $<?= number_format($c['price'], 2) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="cr-price-hint">Prices are set by the zoo and pulled from our catalog.</p>
                        <div id="student-callout" class="cr-callout" role="note">
                            <strong>Student verification</strong>
                            Please bring valid student identification (ID card or enrollment) on the day of your visit. Staff may verify your ticket type at the entrance.
                        </div>
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
                            <option value="Credit card" <?= (($_POST['payment_type'] ?? '') === 'Credit card') ? 'selected' : '' ?>>Credit card</option>
                            <option value="Debit card" <?= (($_POST['payment_type'] ?? '') === 'Debit card') ? 'selected' : '' ?>>Debit card</option>
                            <option value="PayPal" <?= (($_POST['payment_type'] ?? '') === 'PayPal') ? 'selected' : '' ?>>PayPal</option>
                        </select>
                    </div>

                    <button type="submit" class="cr-submit">Complete purchase</button>
                </form>

                <p class="cr-note">This is a demo checkout: no real payment is processed. Your ticket is stored in the zoo database under your account.</p>
                <?php endif; ?>
            </div>
        </main>
    </div>
    <script>
(function () {
    var sel = document.getElementById('order_category_id');
    var note = document.getElementById('student-callout');
    if (!sel || !note) return;
    function sync() {
        var opt = sel.options[sel.selectedIndex];
        var isStudent = opt && opt.getAttribute('data-student') === '1';
        note.classList.toggle('is-visible', isStudent);
    }
    sel.addEventListener('change', sync);
    sync();
})();
    </script>
</body>
</html>
