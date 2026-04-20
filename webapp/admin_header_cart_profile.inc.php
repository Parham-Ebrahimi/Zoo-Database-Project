<?php
/**
 * Inline fragment: Cart + Profile for admin (session shopping + staff profile).
 * Place immediately before the Logout control so order is Cart, Profile, Logout.
 */
if (($_SESSION['role'] ?? '') !== 'admin' || empty($_SESSION['user_id'])) {
    return;
}
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = ['food' => [], 'ticket' => [], 'shop' => []];
}
$adminNavCartCount = 0;
$food = $_SESSION['cart']['food'] ?? [];
$shop = $_SESSION['cart']['shop'] ?? [];
$ticket = $_SESSION['cart']['ticket'] ?? [];
if (is_array($food)) {
    $adminNavCartCount += array_sum($food);
}
if (is_array($shop)) {
    $adminNavCartCount += array_sum($shop);
}
if (is_array($ticket)) {
    foreach ($ticket as $t) {
        $adminNavCartCount += (int) ($t['qty'] ?? 0);
    }
}
?>
<a href="cart.php" class="admin-nav-link">Cart<?php if ($adminNavCartCount > 0): ?> <span class="admin-nav-badge"><?= $adminNavCartCount ?></span><?php endif; ?></a>
<a href="staff_account.php" class="admin-nav-link">Profile</a>
