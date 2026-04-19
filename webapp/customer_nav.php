<?php
/**
 * Shared customer top nav: Home, Dashboard, Buy Tickets, Gift Shop, Restaurant, Animals, Profile, Cart, Logout.
 * Hides the link for the current page (basename of script).
 * Expects $_SESSION['customer_id']. Optional: $cartCount or $navCartCount; otherwise totals session cart.
 */
if (!isset($_SESSION['customer_id'])) {
    return;
}

$self = basename($_SERVER['SCRIPT_NAME'] ?? '');

if (isset($cartCount)) {
    $cartCount = (int) $cartCount;
} elseif (isset($navCartCount)) {
    $cartCount = (int) $navCartCount;
} else {
    $cartCount = 0;
    if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
        $food   = $_SESSION['cart']['food'] ?? [];
        $shop   = $_SESSION['cart']['shop'] ?? [];
        $ticket = $_SESSION['cart']['ticket'] ?? [];
        $cartCount = array_sum($food) + array_sum($shop);
        foreach ($ticket as $t) {
            $cartCount += (int) ($t['qty'] ?? 0);
        }
    }
}

$show = static function (string $file) use ($self): bool {
    return $self !== $file;
};
?>
<nav aria-label="Main">
    <ul class="nav-links">
        <?php if ($show('index.php')): ?>
            <li><a href="index.php">Home</a></li>
        <?php endif; ?>
        <?php if ($show('customer-dashboard.php')): ?>
            <li><a href="customer-dashboard.php">Dashboard</a></li>
        <?php endif; ?>
        <?php if ($show('buy_tickets.php')): ?>
            <li><a href="buy_tickets.php">Buy Tickets</a></li>
        <?php endif; ?>
        <?php if ($show('giftshop.php')): ?>
            <li><a href="giftshop.php">Gift Shop</a></li>
        <?php endif; ?>
        <?php if ($show('restaurant.php')): ?>
            <li><a href="restaurant.php">Restaurant</a></li>
        <?php endif; ?>
        <?php if ($show('animals.php')): ?>
            <li><a href="animals.php">Animals</a></li>
        <?php endif; ?>
        <?php if ($show('customer_profile.php')): ?>
            <li><a href="customer_profile.php">Profile</a></li>
        <?php endif; ?>
        <?php if ($show('cart.php')): ?>
            <li>
                <a href="cart.php" class="nav-cart-link">Cart<?php if ($cartCount > 0): ?><span class="nav-cart-badge" id="cart-count"><?= $cartCount ?></span><?php endif; ?></a>
            </li>
        <?php endif; ?>
        <li><a href="logout.php">Logout</a></li>
    </ul>
</nav>
