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
body{overflow:auto}.page-wrap{box-sizing:border-box;min-height:100vh;padding:30px 40px;background-color:rgba(187,223,158,.95)}
.page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:22px;border-bottom:3px solid var(--accent-color);padding-bottom:16px;flex-wrap:wrap;gap:12px}
.page-header h1{font-size:1.75rem;margin:0}
.btn-nav{padding:8px 20px;background:var(--base-color);border:2px solid var(--accent-color);border-radius:1000px;font:inherit;font-weight:600;font-size:.88rem;cursor:pointer;color:var(--text-color);text-decoration:none}
.btn-nav:hover{background:var(--accent-color);text-decoration:none}
.btn-logout{padding:8px 20px;background:var(--accent-color);border:none;border-radius:1000px;font:inherit;font-weight:600;cursor:pointer;color:var(--text-color);text-decoration:none}
.btn-logout:hover{background:var(--text-color);color:white}
.stats-bar{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:18px}
.stat{background:white;border-radius:12px;padding:14px 16px;text-align:center;box-shadow:0 2px 8px rgba(0,0,0,.06)}
.stat .num{font-size:1.8rem;font-weight:900;color:var(--text-color);line-height:1}
.stat .lbl{font-size:.72rem;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:#888;margin-top:4px}
.stat.money .num{color:#27ae60;font-size:1.4rem}.stat.blue .num{color:#2980b9}
.type-bar-card{background:white;border-radius:12px;padding:16px 20px;box-shadow:0 2px 8px rgba(0,0,0,.06);margin-bottom:18px}
.type-bar-card h3{font-size:.8rem;text-transform:uppercase;letter-spacing:.05em;color:#888;margin:0 0 12px;font-weight:600}
.type-row{display:flex;align-items:center;gap:10px;margin-bottom:8px;font-size:.85rem}
.type-label{min-width:140px;font-weight:600;color:var(--text-color)}
.type-bar-outer{flex:1;background:#eee;border-radius:4px;height:10px;overflow:hidden}
.type-bar-inner{height:100%;border-radius:4px;background:var(--accent-color)}
.type-amount{min-width:80px;text-align:right;font-weight:700;color:#27ae60}
.filter-panel{background:white;border-radius:14px;padding:16px 20px;margin-bottom:18px;box-shadow:0 2px 8px rgba(0,0,0,.06)}
.filter-panel summary{font-weight:700;font-size:.95rem;cursor:pointer;color:var(--text-color);list-style:none;display:flex;align-items:center;gap:.5rem}
.filter-panel summary::before{content:"🔍"}
.filter-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(175px,1fr));gap:12px;margin-top:16px}
.filter-group{display:flex;flex-direction:column;gap:4px}
.filter-group label{font-size:.78rem;font-weight:600;color:var(--text-color);text-transform:uppercase;letter-spacing:.04em}
.filter-group input,.filter-group select{padding:7px 10px;border:2px solid #ddd;border-radius:8px;font:inherit;font-size:.88rem;background:white}
.filter-group input:focus,.filter-group select:focus{outline:none;border-color:var(--accent-color)}
.filter-actions{display:flex;gap:10px;margin-top:14px;flex-wrap:wrap}
.btn-filter{padding:8px 22px;background:var(--accent-color);border:none;border-radius:8px;font:inherit;font-weight:600;cursor:pointer;color:white}
.btn-filter:hover{background:var(--text-color)}
.btn-reset{padding:8px 22px;background:#eee;border:none;border-radius:8px;font:inherit;font-weight:600;cursor:pointer;color:#555;text-decoration:none;display:inline-block}
.btn-reset:hover{background:#ddd}
.result-count{font-size:.85rem;color:#666;margin-bottom:10px;font-weight:600}
.table-wrap{background:white;border-radius:14px;overflow:hidden;box-shadow:0 4px 14px rgba(0,0,0,.08);overflow-x:auto}
table{width:100%;border-collapse:collapse;min-width:1000px}
th{background:var(--accent-color);color:white;padding:11px 13px;text-align:left;font-size:.8rem;text-transform:uppercase;letter-spacing:.04em;white-space:nowrap}
td{padding:10px 13px;border-bottom:1px solid #f0f0f0;font-size:.88rem;vertical-align:middle}
tr:last-child td{border-bottom:none}
tbody tr:hover td{background:rgba(187,223,158,.2)}
tfoot td{background:var(--base-color);font-weight:700;padding:11px 13px;border-top:2px solid var(--accent-color);font-size:.9rem}
.badge-ticket{display:inline-block;padding:2px 10px;border-radius:1000px;font-size:.75rem;font-weight:700;background:#d4edda;color:#155724;white-space:nowrap}
.badge-payment{display:inline-block;padding:2px 10px;border-radius:1000px;font-size:.75rem;font-weight:600;background:#e8f4fd;color:#1a5276;white-space:nowrap}
.visit-upcoming{color:#27ae60;font-weight:700}.visit-past{color:#aaa}
.amount-cell{font-weight:700;color:#27ae60}
.expand-btn{background:var(--base-color);border:none;cursor:pointer;font-size:.8rem;font-weight:600;color:var(--text-color);padding:3px 8px;border-radius:6px}
.expand-btn:hover{background:var(--accent-color);color:white}
.detail-row{display:none}.detail-row.open{display:table-row}
.detail-cell{background:#f8fff8!important;padding:14px 18px!important;border-bottom:2px solid var(--accent-color)!important}
.detail-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:10px}
.detail-item{font-size:.85rem}
.detail-item strong{display:block;font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;color:#888;margin-bottom:2px}
.no-results{padding:40px;text-align:center;color:#888;font-style:italic}
</style>
</head>
<body>
<div class="page-wrap">
<div class="page-header">
    <div>
        <h1>Tickets Report</h1>
        <p style="margin:4px 0 0;font-size:.88rem;color:#555">Welcome, <?php echo htmlspecialchars($_SESSION['firstname']); ?> &middot; Role: <?php echo $_SESSION['role']; ?></p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        <a href="dashboard.php" class="btn-nav">← Dashboard</a>
        <a href="logout.php" class="btn-logout">Logout</a>
    </div>
</div>

<div class="stats-bar">
    <div class="stat"><div class="num"><?php echo $totalOrders; ?></div><div class="lbl">Total orders</div></div>
    <div class="stat"><div class="num"><?php echo number_format($totalTickets); ?></div><div class="lbl">Tickets sold</div></div>
    <div class="stat money"><div class="num">$<?php echo number_format($totalRevenue,2); ?></div><div class="lbl">Total revenue</div></div>
    <div class="stat blue"><div class="num"><?php echo $upcomingCount; ?></div><div class="lbl">Upcoming visits</div></div>
    <div class="stat"><div class="num">$<?php echo $totalOrders>0?number_format($avgOrder,2):'—'; ?></div><div class="lbl">Avg order value</div></div>
</div>

<?php if (!empty($byType) && $totalRevenue > 0): ?>
<div class="type-bar-card">
    <h3>Revenue by ticket type</h3>
    <?php foreach ($byType as $typeName => $typeRev): ?>
    <div class="type-row">
        <span class="type-label"><?php echo htmlspecialchars($typeName); ?></span>
        <div class="type-bar-outer"><div class="type-bar-inner" style="width:<?php echo round(($typeRev/$totalRevenue)*100); ?>%"></div></div>
        <span class="type-amount">$<?php echo number_format($typeRev,2); ?></span>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<details class="filter-panel" <?php echo $hasFilters?'open':''; ?>>
    <summary>Filters &amp; Search</summary>
    <form method="GET">
        <div class="filter-grid">
            <div class="filter-group" style="grid-column:span 2">
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
        </div>
        <div class="filter-actions">
            <button type="submit" class="btn-filter">Apply filters</button>
            <a href="tickets_report.php" class="btn-reset">Reset all</a>
        </div>
    </form>
</details>

<p class="result-count">
    Showing <?php echo $totalOrders; ?> order<?php echo $totalOrders!==1?'s':''; ?>
    &middot; <?php echo number_format($totalTickets); ?> ticket<?php echo $totalTickets!==1?'s':''; ?>
    &middot; $<?php echo number_format($totalRevenue,2); ?> revenue
    <?php if ($hasFilters): ?><span style="color:var(--accent-color)">(filtered)</span><?php endif; ?>
</p>

<div class="table-wrap">
    <?php if (empty($tickets)): ?>
        <p class="no-results">No ticket orders match the selected filters.</p>
    <?php else: ?>
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
    <?php endif; ?>
</div>
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
