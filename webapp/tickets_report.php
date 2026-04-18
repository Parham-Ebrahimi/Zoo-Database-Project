<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit;
}
if (!in_array($_SESSION['role'], ['admin'])) {
    header('Location: dashboard.php');
    exit;
}
require 'db.php';

$ticketTypes  = $pdo->query("SELECT OrderCategoryID, CategoryName, Price FROM ordercategories WHERE OrderCategoryID BETWEEN 1 AND 5 ORDER BY OrderCategoryID")->fetchAll();
$paymentModes = ['Credit Card', 'Debit Card', 'Cash', 'PayPal'];

$f_category   = $_GET['category']   ?? '';
$f_payment    = $_GET['payment']    ?? '';
$f_customer   = trim($_GET['customer'] ?? '');
$f_order_from = $_GET['order_from'] ?? '';
$f_order_to   = $_GET['order_to']   ?? '';
$f_visit_from = $_GET['visit_from'] ?? '';
$f_visit_to   = $_GET['visit_to']   ?? '';
$f_amt_min    = $_GET['amt_min']    ?? '';
$f_amt_max    = $_GET['amt_max']    ?? '';
$f_qty_min    = $_GET['qty_min']    ?? '';
$f_sort       = $_GET['sort']       ?? 'o.OrderDate';
$f_dir        = $_GET['dir']        ?? 'DESC';

$allowedSorts = ['o.OrderID','o.OrderDate','o.ScheduledDate','o.TransactionAmount','oc.CategoryName','o.PaymentMode','customer_name','ot.Quantity'];
if (!in_array($f_sort, $allowedSorts)) $f_sort = 'o.OrderDate';
$f_dir = ($f_dir === 'ASC') ? 'ASC' : 'DESC';

$where  = ['o.OrderCategoryID BETWEEN 1 AND 5'];
$params = [];

if ($f_category)   { $where[] = 'o.OrderCategoryID = ?'; $params[] = (int)$f_category; }
if ($f_payment)    { $where[] = 'o.PaymentMode = ?';     $params[] = $f_payment; }
if ($f_order_from) { $where[] = 'o.OrderDate >= ?';      $params[] = $f_order_from; }
if ($f_order_to)   { $where[] = 'o.OrderDate <= ?';      $params[] = $f_order_to; }
if ($f_visit_from) { $where[] = 'o.ScheduledDate >= ?';  $params[] = $f_visit_from; }
if ($f_visit_to)   { $where[] = 'o.ScheduledDate <= ?';  $params[] = $f_visit_to; }
if ($f_amt_min !== '') { $where[] = 'o.TransactionAmount >= ?'; $params[] = (float)$f_amt_min; }
if ($f_amt_max !== '') { $where[] = 'o.TransactionAmount <= ?'; $params[] = (float)$f_amt_max; }
if ($f_qty_min !== '') { $where[] = 'ot.Quantity >= ?';         $params[] = (int)$f_qty_min; }
if ($f_customer) {
    $where[]  = "(c.FirstName LIKE ? OR c.LastName LIKE ? OR CONCAT(c.FirstName,\' \',c.LastName) LIKE ? OR c.Email LIKE ?)";
    $params[] = "%$f_customer%"; $params[] = "%$f_customer%";
    $params[] = "%$f_customer%"; $params[] = "%$f_customer%";
}

$orderBy = ($f_sort === 'customer_name') ? "CONCAT(c.FirstName,\' \',c.LastName) $f_dir" : "$f_sort $f_dir";

$sql = "SELECT o.OrderID, o.OrderDate AS PurchaseDate, o.ScheduledDate AS VisitDate,
        o.TransactionAmount, o.PaymentMode, oc.CategoryName AS TicketType, oc.Price AS UnitPrice,
        ot.Quantity, c.CustomerID, c.FirstName, c.LastName, c.Email, c.PhoneNumber,
        c.CountryCode, c.RegistrationDate
        FROM orders o
        JOIN ordercategories oc ON o.OrderCategoryID = oc.OrderCategoryID
        JOIN order_tickets ot   ON o.OrderID = ot.OrderID
        JOIN customers c        ON o.CustomerID = c.CustomerID
        WHERE " . implode(' AND ', $where) . " ORDER BY $orderBy";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalOrders   = count($tickets);
$totalRevenue  = array_sum(array_column($tickets, 'TransactionAmount'));
$totalTickets  = array_sum(array_column($tickets, 'Quantity'));
$upcomingCount = count(array_filter($tickets, fn($t) => !empty($t['VisitDate']) && $t['VisitDate'] >= date('Y-m-d')));
$avgOrder      = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;

$byType = [];
foreach ($tickets as $t) { $byType[$t['TicketType']] = ($byType[$t['TicketType']] ?? 0) + $t['TransactionAmount']; }
arsort($byType);

function sLink(string $col, string $label, string $cur, string $dir): string {
    $p = $_GET; $p['sort'] = $col; $p['dir'] = ($cur === $col && $dir === 'DESC') ? 'ASC' : 'DESC';
    $arrow = ($cur === $col) ? ($dir === 'DESC' ? ' ↓' : ' ↑') : '';
    return '<a href="?'.http_build_query($p).'" style="color:white;text-decoration:none">'.htmlspecialchars($label).$arrow.'</a>';
}

$hasFilters = array_filter([$f_category,$f_payment,$f_customer,$f_order_from,$f_order_to,$f_visit_from,$f_visit_to,$f_amt_min,$f_amt_max,$f_qty_min]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tickets Report</title>
<link rel="stylesheet" href="style.css">
<style>
        body { overflow: auto; }
        .dashboard-wrapper {
            box-sizing: border-box;
            min-height: 100vh;
            padding: 20px clamp(12px, 2.4vw, 18px);
            background-color: rgba(187, 223, 158, 0.95);
        }
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            border-bottom: 3px solid var(--accent-color);
            padding-bottom: 12px;
        }
        .filter-card {
            background: white;
            border-radius: 12px;
            padding: 12px 14px;
            margin-bottom: 14px;
            box-shadow: 0 3px 8px rgba(0,0,0,0.05);
        }
        .filter-card h2 {
            font-size: 0.95rem;
            font-weight: 700;
            margin-bottom: 10px;
            color: var(--text-color);
        }
        .filter-card label {
            background: none;
            color: var(--text-color);
            font-size: 0.85rem;
            font-weight: 600;
            height: auto;
            width: auto;
            border-radius: 0;
            display: block;
            text-align: left;
            padding: 0;
            fill: none;
            flex-shrink: unset;
        }
        .filter-card form {
            width: 100%;
            margin: 0;
            display: block;
        }
        .filter-card form > div {
            width: auto;
            display: block;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .filter-group label {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-color);
        }
        .filter-group input, .filter-group select {
            padding: 6px 10px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font: inherit;
            background: white;
        }
        .filter-group input:focus, .filter-group select:focus {
            outline: none;
            border-color: var(--accent-color);
        }
        .filter-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: end;
        }
        .btn {
            padding: 6px 14px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.85rem;
        }
        .btn-edit {
            background-color: var(--accent-color);
            color: white;
            border: none;
            cursor: pointer;
            font: inherit;
        }
        .btn-edit:hover { background-color: var(--text-color); }
        .logout-btn {
            padding: 10px 25px;
            background-color: var(--accent-color);
            border: none;
            border-radius: 1000px;
            font: inherit;
            font-weight: 600;
            cursor: pointer;
            color: var(--text-color);
            text-decoration: none;
        }
        .logout-btn:hover {
            background-color: var(--text-color);
            color: white;
        }
        .back-btn {
            display: inline-block;
            margin-bottom: 14px;
            padding: 7px 14px;
            background-color: var(--base-color);
            border-radius: 8px;
            color: var(--text-color);
            font-weight: 600;
            text-decoration: none;
            font-size: 0.88rem;
        }
        .back-btn:hover { background-color: var(--accent-color); }
        .badge-ticket {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 1000px;
            font-size: 0.75rem;
            font-weight: 700;
            background: #d4edda;
            color: #155724;
            white-space: nowrap;
        }
        .badge-payment {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 1000px;
            font-size: 0.75rem;
            font-weight: 600;
            background: #e8f4fd;
            color: #1a5276;
            white-space: nowrap;
        }
        .visit-upcoming { color: #27ae60; font-weight: 700; }
        .visit-past { color: #aaa; }
        .amount-cell { font-weight: 700; color: #27ae60; }
        .expand-btn {
            background: var(--base-color);
            border: none;
            cursor: pointer;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-color);
            padding: 3px 8px;
            border-radius: 6px;
        }
        .expand-btn:hover {
            background: var(--accent-color);
            color: white;
        }
        .detail-row { display: none; }
        .detail-row.open { display: table-row; }
        .detail-cell {
            background: #f8fff8 !important;
            padding: 14px 18px !important;
            border-bottom: 2px solid var(--accent-color) !important;
        }
        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 10px;
        }
        .detail-item { font-size: 0.85rem; }
        .detail-item strong {
            display: block;
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #888;
            margin-bottom: 2px;
        }
        .no-results {
            padding: 28px;
            text-align: center;
            color: #888;
            font-style: italic;
        }
</style>
</head>
<body>
<div class="dashboard-wrapper">
    <div class="dashboard-header">
        <h1>Tickets Report</h1>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>

    <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>

    <div class="filter-card">
        <h2>Filter ticket orders</h2>
    <form method="GET">
        <div class="filter-grid">
            <div class="filter-group filter-group--wide">
                <label>Customer name or email</label>
                <input type="text" name="customer" value="<?php echo htmlspecialchars($f_customer); ?>" placeholder="e.g. Jane, Smith, jane@email.com">
            </div>
            <div class="filter-group">
                <label>Ticket type</label>
                <select name="category">
                    <option value="">All types</option>
                    <?php foreach ($ticketTypes as $t): ?>
                    <option value="<?php echo $t['OrderCategoryID']; ?>" <?php echo (int)$f_category===$t['OrderCategoryID']?'selected':''; ?>>
                        <?php echo htmlspecialchars($t['CategoryName']); ?> ($<?php echo number_format($t['Price'],2); ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Payment method</label>
                <select name="payment">
                    <option value="">All methods</option>
                    <?php foreach ($paymentModes as $pm): ?>
                    <option value="<?php echo $pm; ?>" <?php echo $f_payment===$pm?'selected':''; ?>><?php echo $pm; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group"><label>Purchase date from</label><input type="date" name="order_from" value="<?php echo htmlspecialchars($f_order_from); ?>"></div>
            <div class="filter-group"><label>Purchase date to</label><input type="date" name="order_to" value="<?php echo htmlspecialchars($f_order_to); ?>"></div>
            <div class="filter-group"><label>Visit date from</label><input type="date" name="visit_from" value="<?php echo htmlspecialchars($f_visit_from); ?>"></div>
            <div class="filter-group"><label>Visit date to</label><input type="date" name="visit_to" value="<?php echo htmlspecialchars($f_visit_to); ?>"></div>
            <div class="filter-group"><label>Min amount ($)</label><input type="number" step="0.01" name="amt_min" value="<?php echo htmlspecialchars($f_amt_min); ?>" placeholder="e.g. 20"></div>
            <div class="filter-group"><label>Max amount ($)</label><input type="number" step="0.01" name="amt_max" value="<?php echo htmlspecialchars($f_amt_max); ?>" placeholder="e.g. 100"></div>
            <div class="filter-group"><label>Min quantity</label><input type="number" name="qty_min" value="<?php echo htmlspecialchars($f_qty_min); ?>" min="1" placeholder="e.g. 2"></div>
            <div class="filter-group">
                <label>Sort by</label>
                <select name="sort">
                    <option value="o.OrderDate" <?php echo $f_sort==='o.OrderDate'?'selected':''; ?>>Purchase date</option>
                    <option value="o.ScheduledDate" <?php echo $f_sort==='o.ScheduledDate'?'selected':''; ?>>Visit date</option>
                    <option value="o.OrderID" <?php echo $f_sort==='o.OrderID'?'selected':''; ?>>Order ID</option>
                    <option value="o.TransactionAmount" <?php echo $f_sort==='o.TransactionAmount'?'selected':''; ?>>Amount</option>
                    <option value="oc.CategoryName" <?php echo $f_sort==='oc.CategoryName'?'selected':''; ?>>Ticket type</option>
                    <option value="o.PaymentMode" <?php echo $f_sort==='o.PaymentMode'?'selected':''; ?>>Payment</option>
                    <option value="customer_name" <?php echo $f_sort==='customer_name'?'selected':''; ?>>Customer name</option>
                    <option value="ot.Quantity" <?php echo $f_sort==='ot.Quantity'?'selected':''; ?>>Quantity</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Direction</label>
                <select name="dir">
                    <option value="DESC" <?php echo $f_dir==='DESC'?'selected':''; ?>>Newest / Highest first</option>
                    <option value="ASC" <?php echo $f_dir==='ASC'?'selected':''; ?>>Oldest / Lowest first</option>
                </select>
            </div>
            <div class="filter-actions">
                <button type="submit" class="btn btn-edit">Search</button>
                <a href="tickets_report.php" class="btn">Reset</a>
            </div>
        </div>
    </form>
    </div>

    <p><strong>Total orders:</strong> <?php echo $totalOrders; ?><?php if ($hasFilters): ?> <span style="color:var(--accent-color);font-weight:600">(filtered)</span><?php endif; ?></p>
    <p><strong>Tickets sold:</strong> <?php echo number_format($totalTickets); ?> &nbsp;|&nbsp; <strong>Revenue:</strong> $<?php echo number_format($totalRevenue, 2); ?> &nbsp;|&nbsp; <strong>Upcoming visits:</strong> <?php echo $upcomingCount; ?> &nbsp;|&nbsp; <strong>Avg order:</strong> $<?php echo $totalOrders > 0 ? number_format($avgOrder, 2) : '—'; ?></p>

    <?php if (empty($tickets)): ?>
        <p>No ticket orders match the selected filters.</p>
    <?php else: ?>
    <div class="report-table-scroll">
    <table>
        <thead>
            <tr>
                <th><?php echo sLink('o.OrderID','#',$f_sort,$f_dir); ?></th>
                <th><?php echo sLink('customer_name','Customer',$f_sort,$f_dir); ?></th>
                <th><?php echo sLink('oc.CategoryName','Ticket type',$f_sort,$f_dir); ?></th>
                <th><?php echo sLink('ot.Quantity','Qty',$f_sort,$f_dir); ?></th>
                <th>Unit price</th>
                <th><?php echo sLink('o.TransactionAmount','Total',$f_sort,$f_dir); ?></th>
                <th><?php echo sLink('o.PaymentMode','Payment',$f_sort,$f_dir); ?></th>
                <th><?php echo sLink('o.OrderDate','Purchased',$f_sort,$f_dir); ?></th>
                <th><?php echo sLink('o.ScheduledDate','Visit date',$f_sort,$f_dir); ?></th>
                <th>Details</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($tickets as $row):
            $isUpcoming = !empty($row['VisitDate']) && $row['VisitDate'] >= date('Y-m-d');
        ?>
        <tr>
            <td>#<?php echo $row['OrderID']; ?></td>
            <td>
                <strong><?php echo htmlspecialchars($row['FirstName'].' '.$row['LastName']); ?></strong>
                <div style="font-size:.75rem;color:#888"><?php echo htmlspecialchars($row['Email']); ?></div>
            </td>
            <td><span class="badge-ticket"><?php echo htmlspecialchars($row['TicketType']); ?></span></td>
            <td style="font-weight:700"><?php echo $row['Quantity']; ?></td>
            <td>$<?php echo number_format($row['UnitPrice'],2); ?></td>
            <td class="amount-cell">$<?php echo number_format($row['TransactionAmount'],2); ?></td>
            <td><span class="badge-payment"><?php echo htmlspecialchars($row['PaymentMode']); ?></span></td>
            <td><?php echo date('M j, Y',strtotime($row['PurchaseDate'])); ?></td>
            <td>
                <?php if ($row['VisitDate']): ?>
                    <span class="<?php echo $isUpcoming?'visit-upcoming':'visit-past'; ?>">
                        <?php echo date('M j, Y',strtotime($row['VisitDate'])); ?><?php echo $isUpcoming?' ✓':''; ?>
                    </span>
                <?php else: ?>
                    <span style="color:#aaa">—</span>
                <?php endif; ?>
            </td>
            <td><button class="expand-btn" onclick="toggleDetail(<?php echo $row['OrderID']; ?>)">Details ▾</button></td>
        </tr>
        <tr class="detail-row" id="detail-<?php echo $row['OrderID']; ?>">
            <td class="detail-cell" colspan="10">
                <div class="detail-grid">
                    <div class="detail-item"><strong>Order ID</strong>#<?php echo $row['OrderID']; ?></div>
                    <div class="detail-item"><strong>Customer ID</strong>#<?php echo $row['CustomerID']; ?></div>
                    <div class="detail-item"><strong>Full name</strong><?php echo htmlspecialchars($row['FirstName'].' '.$row['LastName']); ?></div>
                    <div class="detail-item"><strong>Email</strong><?php echo htmlspecialchars($row['Email']); ?></div>
                    <div class="detail-item"><strong>Phone</strong><?php echo htmlspecialchars($row['CountryCode'].' '.$row['PhoneNumber']); ?></div>
                    <div class="detail-item"><strong>Customer since</strong><?php echo date('M j, Y',strtotime($row['RegistrationDate'])); ?></div>
                    <div class="detail-item"><strong>Ticket type</strong><?php echo htmlspecialchars($row['TicketType']); ?></div>
                    <div class="detail-item"><strong>Quantity</strong><?php echo $row['Quantity'].' ticket'.($row['Quantity']!=1?'s':''); ?></div>
                    <div class="detail-item"><strong>Unit price</strong>$<?php echo number_format($row['UnitPrice'],2); ?></div>
                    <div class="detail-item"><strong>Total paid</strong>$<?php echo number_format($row['TransactionAmount'],2); ?></div>
                    <div class="detail-item"><strong>Payment method</strong><?php echo htmlspecialchars($row['PaymentMode']); ?></div>
                    <div class="detail-item"><strong>Purchase date</strong><?php echo date('F j, Y',strtotime($row['PurchaseDate'])); ?></div>
                    <div class="detail-item">
                        <strong>Scheduled visit</strong>
                        <?php echo $row['VisitDate'] ? date('F j, Y',strtotime($row['VisitDate'])).($isUpcoming?' (upcoming)':' (past)') : 'Not set'; ?>
                    </div>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3">TOTALS (<?php echo $totalOrders; ?> orders)</td>
                <td><?php echo number_format($totalTickets); ?></td>
                <td>—</td>
                <td>$<?php echo number_format($totalRevenue,2); ?></td>
                <td colspan="4"></td>
            </tr>
        </tfoot>
    </table>
    </div>
    <?php endif; ?>
</div>
<script>
function toggleDetail(id) {
    const row = document.getElementById('detail-' + id);
    row.classList.toggle('open');
    const btn = row.previousElementSibling.querySelector('.expand-btn');
    btn.textContent = row.classList.contains('open') ? 'Details ▴' : 'Details ▾';
}
</script>
</body>
</html>
