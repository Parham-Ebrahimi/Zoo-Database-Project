<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = ['food' => [], 'shop' => [], 'ticket' => []];
}

$action   = $_POST['action']   ?? $_GET['action']   ?? '';
$type     = $_POST['type']     ?? $_GET['type']     ?? '';
$id       = (int)($_POST['id'] ?? $_GET['id']       ?? 0);
$qty      = max(1, (int)($_POST['qty'] ?? 1));
$redirect = $_POST['redirect'] ?? $_GET['redirect']  ?? 'cart.php';

switch ($action) {
    case 'add':
        if ($type === 'food') {
            $stmt = $pdo->prepare("SELECT FoodID FROM fooditem WHERE FoodID = ?");
            $stmt->execute([$id]);
            if ($stmt->fetch()) {
                $_SESSION['cart']['food'][$id] = ($_SESSION['cart']['food'][$id] ?? 0) + $qty;
            }
        } elseif ($type === 'shop') {
            $stmt = $pdo->prepare("SELECT ShopItemID FROM shop_items WHERE ShopItemID = ?");
            $stmt->execute([$id]);
            if ($stmt->fetch()) {
                $_SESSION['cart']['shop'][$id] = ($_SESSION['cart']['shop'][$id] ?? 0) + $qty;
            }
        } elseif ($type === 'ticket') {
            $visit_date  = $_POST['visit_date'] ?? '';
            $category_id = (int)($_POST['category_id'] ?? $id);
            if ($category_id >= 1 && $category_id <= 4 && $visit_date) {
                $key = $category_id . '_' . $visit_date;
                if (isset($_SESSION['cart']['ticket'][$key])) {
                    $_SESSION['cart']['ticket'][$key]['qty'] += $qty;
                } else {
                    $_SESSION['cart']['ticket'][$key] = [
                        'category_id' => $category_id,
                        'visit_date'  => $visit_date,
                        'qty'         => $qty,
                    ];
                }
            }
        }
        break;

    case 'update':
        if ($type === 'food') {
            if ($qty <= 0) unset($_SESSION['cart']['food'][$id]);
            else $_SESSION['cart']['food'][$id] = $qty;
        } elseif ($type === 'shop') {
            if ($qty <= 0) unset($_SESSION['cart']['shop'][$id]);
            else $_SESSION['cart']['shop'][$id] = $qty;
        } elseif ($type === 'ticket') {
            $key = $_POST['key'] ?? '';
            if ($key && isset($_SESSION['cart']['ticket'][$key])) {
                if ($qty <= 0) unset($_SESSION['cart']['ticket'][$key]);
                else $_SESSION['cart']['ticket'][$key]['qty'] = $qty;
            }
        }
        break;

    case 'remove':
        if ($type === 'food')  unset($_SESSION['cart']['food'][$id]);
        if ($type === 'shop')  unset($_SESSION['cart']['shop'][$id]);
        if ($type === 'ticket') {
            $key = $_POST['key'] ?? $_GET['key'] ?? '';
            if ($key) unset($_SESSION['cart']['ticket'][$key]);
        }
        break;

    case 'clear':
        $_SESSION['cart'] = ['food' => [], 'shop' => [], 'ticket' => []];
        break;
}

// AJAX response
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    $count = array_sum($_SESSION['cart']['food'])
           + array_sum($_SESSION['cart']['shop'])
           + array_sum(array_column($_SESSION['cart']['ticket'], 'qty'));
    header('Content-Type: application/json');
    echo json_encode(['count' => $count, 'status' => 'ok']);
    exit;
}

header('Location: ' . $redirect);
exit;
