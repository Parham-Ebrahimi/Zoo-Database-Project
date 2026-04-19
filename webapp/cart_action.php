<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = ['food' => [], 'ticket' => [], 'shop' => []];
} else {
    if (!isset($_SESSION['cart']['food']) || !is_array($_SESSION['cart']['food'])) {
        $_SESSION['cart']['food'] = [];
    }
    if (!isset($_SESSION['cart']['ticket']) || !is_array($_SESSION['cart']['ticket'])) {
        $_SESSION['cart']['ticket'] = [];
    }
    if (!isset($_SESSION['cart']['shop']) || !is_array($_SESSION['cart']['shop'])) {
        $_SESSION['cart']['shop'] = [];
    }
}

$action   = $_POST['action']   ?? $_GET['action']   ?? '';
$type     = $_POST['type']     ?? $_GET['type']     ?? '';
$id       = (int) ($_POST['id'] ?? $_GET['id']       ?? 0);
$qty      = max(1, (int) ($_POST['qty'] ?? 1));
$redirect = $_POST['redirect'] ?? $_GET['redirect']  ?? 'cart.php';

switch ($action) {
    case 'add':
        if ($type === 'food') {
            $stmt = $pdo->prepare('SELECT FoodID FROM fooditem WHERE FoodID = ?');
            $stmt->execute([$id]);
            if ($stmt->fetch()) {
                $_SESSION['cart']['food'][$id] = ($_SESSION['cart']['food'][$id] ?? 0) + $qty;
            }
        } elseif ($type === 'ticket') {
            $visit_date  = $_POST['visit_date'] ?? '';
            $category_id = (int) ($_POST['category_id'] ?? $id);
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
        } elseif ($type === 'shop') {
            $stmt = $pdo->prepare('SELECT StockQty FROM shop_items WHERE ShopItemID = ?');
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $max = (int) $row['StockQty'];
                if ($max > 0) {
                    $had = (int) ($_SESSION['cart']['shop'][$id] ?? 0);
                    $newTotal = min($max, $had + $qty);
                    if ($newTotal > 0) {
                        $_SESSION['cart']['shop'][$id] = $newTotal;
                    }
                }
            }
        }
        break;

    case 'update':
        if ($type === 'food') {
            if ($qty <= 0) {
                unset($_SESSION['cart']['food'][$id]);
            } else {
                $_SESSION['cart']['food'][$id] = $qty;
            }
        } elseif ($type === 'ticket') {
            $key = $_POST['key'] ?? '';
            if ($key && isset($_SESSION['cart']['ticket'][$key])) {
                if ($qty <= 0) {
                    unset($_SESSION['cart']['ticket'][$key]);
                } else {
                    $_SESSION['cart']['ticket'][$key]['qty'] = $qty;
                }
            }
        } elseif ($type === 'shop') {
            $stmt = $pdo->prepare('SELECT StockQty FROM shop_items WHERE ShopItemID = ?');
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $max = (int) $row['StockQty'];
                if ($qty <= 0) {
                    unset($_SESSION['cart']['shop'][$id]);
                } else {
                    $_SESSION['cart']['shop'][$id] = min($qty, $max);
                }
            }
        }
        break;

    case 'remove':
        if ($type === 'food') {
            unset($_SESSION['cart']['food'][$id]);
        }
        if ($type === 'ticket') {
            $key = $_POST['key'] ?? $_GET['key'] ?? '';
            if ($key) {
                unset($_SESSION['cart']['ticket'][$key]);
            }
        }
        if ($type === 'shop') {
            unset($_SESSION['cart']['shop'][$id]);
        }
        break;

    case 'clear':
        $_SESSION['cart'] = ['food' => [], 'ticket' => [], 'shop' => []];
        break;
}

// AJAX response
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    $shopSum = array_sum($_SESSION['cart']['shop']);
    $count = array_sum($_SESSION['cart']['food'])
        + array_sum(array_column($_SESSION['cart']['ticket'], 'qty'))
        + $shopSum;
    header('Content-Type: application/json');
    echo json_encode(['count' => $count, 'status' => 'ok']);
    exit;
}

header('Location: ' . $redirect);
exit;
