<?php
/**
 * JSON: top gift shop line items by quantity for the current calendar month.
 * Used by the gift shop dashboard chart (polls so totals update after sales).
 */
require_once __DIR__ . '/session_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['admin', 'Gift Shop Employee'], true)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden']);
    exit;
}

require_once 'db.php';

$monthStart = date('Y-m-01');
$nextMonth  = date('Y-m-01', strtotime('first day of next month'));

$stmt = $pdo->prepare("
    SELECT si.ItemName AS name, SUM(osi.Quantity) AS qty
    FROM order_shop_items osi
    INNER JOIN orders o ON o.OrderID = osi.OrderID AND o.OrderCategoryID = 6
    INNER JOIN shop_items si ON si.ShopItemID = osi.ShopItemID
    WHERE o.OrderDate >= ? AND o.OrderDate < ?
    GROUP BY si.ShopItemID, si.ItemName
    ORDER BY qty DESC, si.ItemName ASC
    LIMIT 15
");
$stmt->execute([$monthStart, $nextMonth]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$items = [];
foreach ($rows as $r) {
    $items[] = [
        'name' => (string) $r['name'],
        'qty'  => (int) $r['qty'],
    ];
}

echo json_encode([
    'ok'         => true,
    'monthLabel' => date('F Y'),
    'monthStart' => $monthStart,
    'items'      => $items,
], JSON_UNESCAPED_UNICODE);
