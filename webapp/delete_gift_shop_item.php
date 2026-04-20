<?php
require_once __DIR__ . '/session_bootstrap.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit;
}
$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['admin', 'Gift Shop Employee'], true)) {
    header('Location: dashboard.php');
    exit;
}
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: manage_gift_shop_items.php');
    exit;
}

$id = (int) ($_POST['shop_item_id'] ?? 0);
if ($id <= 0) {
    header('Location: manage_gift_shop_items.php');
    exit;
}

$lineStmt = $pdo->prepare('SELECT COUNT(*) FROM order_shop_items WHERE ShopItemID = ?');
$lineStmt->execute([$id]);
$salesLines = (int) $lineStmt->fetchColumn();

if ($salesLines > 0) {
    header('Location: manage_gift_shop_items.php?error=' . rawurlencode(
        'This item cannot be removed because it appears on past orders. Set stock to 0 to take it off sale instead.'
    ));
    exit;
}

try {
    $pdo->beginTransaction();
    $pdo->prepare('DELETE FROM restock_alerts WHERE ShopItemID = ?')->execute([$id]);
    $del = $pdo->prepare('DELETE FROM shop_items WHERE ShopItemID = ?');
    $del->execute([$id]);
    if ($del->rowCount() === 0) {
        $pdo->rollBack();
        header('Location: manage_gift_shop_items.php?error=' . rawurlencode('Item not found.'));
        exit;
    }
    $pdo->commit();

    $uploadDir = __DIR__ . '/images/gift-shop/uploads';
    foreach (glob($uploadDir . DIRECTORY_SEPARATOR . 'item-' . $id . '.*') ?: [] as $f) {
        if (is_file($f)) {
            @unlink($f);
        }
    }

    header('Location: manage_gift_shop_items.php?deleted=1');
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header('Location: manage_gift_shop_items.php?error=' . rawurlencode(
        'Could not delete this item. It may still be referenced elsewhere.'
    ));
}
exit;
