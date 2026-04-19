<?php
require_once __DIR__ . '/session_bootstrap.php';

$role = $_SESSION['role'] ?? '';
/** Staff (admin / gift shop) can open customer storefront with ?preview=1 — otherwise they are sent to the staff dashboard. */
$staffPreview = isset($_GET['preview']) && (string) $_GET['preview'] === '1'
    && !empty($_SESSION['user_id'])
    && in_array($role, ['admin', 'Gift Shop Employee'], true);

if (!isset($_SESSION['customer_id'])) {
    if ($staffPreview) {
        // show read-only customer view for authorized staff
    } elseif (!empty($_SESSION['user_id'])) {
        header('Location: dashboard.php');
        exit;
    } else {
        header('Location: login.html');
        exit;
    }
}

$staffPreviewDashboardHref = $role === 'Gift Shop Employee'
    ? 'dashboard.php#gift-shop'
    : 'dashboard.php#gift-shop-admin';

require_once 'db.php';

$flash = '';
if (isset($_GET['added'])) {
    $flash = 'Item added to your cart.';
}

$itemsStmt = $pdo->query("
    SELECT si.ShopItemID, si.ItemName, si.Price, si.StockQty, s.ShopName
    FROM shop_items si
    JOIN shops s ON s.ShopID = si.ShopID
    ORDER BY s.ShopName, si.ItemName
");
$items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
$groupedItems = [];
foreach ($items as $item) {
    $groupName = (string) ($item['ShopName'] ?? 'Gift Shop');
    if (!isset($groupedItems[$groupName])) {
        $groupedItems[$groupName] = [];
    }
    $groupedItems[$groupName][] = $item;
}

$mostPopularShopItemID = null;
$mostPopularLabel = '';
try {
    $monthStart = date('Y-m-01');
    $nextMonth  = date('Y-m-01', strtotime('first day of next month'));
    $topStmt = $pdo->prepare("
        SELECT si.ShopItemID, si.ItemName, SUM(osi.Quantity) AS qty
        FROM order_shop_items osi
        INNER JOIN orders o ON o.OrderID = osi.OrderID AND o.OrderCategoryID = 6
        INNER JOIN shop_items si ON si.ShopItemID = osi.ShopItemID
        WHERE o.OrderDate >= ? AND o.OrderDate < ?
        GROUP BY si.ShopItemID, si.ItemName
        ORDER BY qty DESC, si.ItemName ASC
        LIMIT 1
    ");
    $topStmt->execute([$monthStart, $nextMonth]);
    $topRow = $topStmt->fetch(PDO::FETCH_ASSOC);
    if ($topRow) {
        $mostPopularShopItemID = (int) $topRow['ShopItemID'];
        $mostPopularLabel = (string) $topRow['ItemName'];
    }
} catch (Throwable $e) {
    $mostPopularShopItemID = null;
    $mostPopularLabel = '';
}

$cartShop = [];
if (isset($_SESSION['cart']['shop']) && is_array($_SESSION['cart']['shop'])) {
    $cartShop = $_SESSION['cart']['shop'];
}
$cartCount = array_sum($cartShop);

/** Prefer admin-uploaded image (images/gift-shop/uploads/item-{ShopItemID}.ext), else keyword stock art. */
function gift_shop_resolved_image_url(array $item): string
{
    $id = (int) ($item['ShopItemID'] ?? 0);
    $uploadDir = __DIR__ . '/images/gift-shop/uploads';
    if ($id > 0 && is_dir($uploadDir)) {
        $matches = glob($uploadDir . DIRECTORY_SEPARATOR . 'item-' . $id . '.*') ?: [];
        if ($matches !== [] && is_file($matches[0])) {
            return 'images/gift-shop/uploads/' . basename($matches[0]);
        }
    }

    return gift_shop_item_image_src((string) ($item['ItemName'] ?? ''), $id);
}

/**
 * Local product photos in webapp/images/gift-shop/ (see filenames below).
 * Matching is by substring on ItemName; add ShopItemID overrides if names don't contain these words.
 */
function gift_shop_item_image_src(string $itemName, int $shopItemId = 0): string
{
    $base = 'images/gift-shop/';
    static $byShopItemId = [
        // Optional: force image when DB ItemName doesn't match keywords, e.g. 12 => 'lion-plush.png',
    ];
    if ($shopItemId > 0 && isset($byShopItemId[$shopItemId])) {
        return $base . $byShopItemId[$shopItemId];
    }

    $n = strtolower($itemName);
    $rules = [
        [['snow globe', 'snowglobe', 'polar bear'], 'arctic-snow-globe.png'],
        [['earring', 'earings'], 'tropical-earrings.png'],
        [['safari hat', 'sun hat', 'fedora'], 'safari-hat.png'],
        [['keychain'], 'animal-keychain.png'],
        [['postcard'], 'zoo-postcard.png'],
        [['mug'], 'zoo-mug.png'],
        [['water bottle', 'waterbottle', 'bottle'], 'greenwood-zoo-water-bottle.png'],
        [['t-shirt', 'tshirt', 'tee shirt', 't shirt', 'shirt'], 'greenwood-zoo-tshirt.png'],
        [['map', 'brochure', 'visitor guide'], 'zoo-map.png'],
        [['bracelet', 'charm'], 'jungle-bracelet.png'],
        [['penguin'], 'penguin-plush.png'],
        [['elephant'], 'elephant-plush.png'],
        [['lion'], 'lion-plush.png'],
    ];
    foreach ($rules as [$keywords, $file]) {
        foreach ($keywords as $kw) {
            if (str_contains($n, $kw)) {
                return $base . $file;
            }
        }
    }

    return $base . 'zoo-map.png';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gift Shop - Greenwood Zoo</title>
    <link rel="stylesheet" href="index.css">
    <style>
        .shop-wrap {
            max-width: 1100px;
            margin: 1.5rem auto 2rem;
            padding: 0 1rem;
        }
        .shop-panel {
            background: var(--surface);
            border-radius: 18px;
            box-shadow: var(--shadow);
            padding: 1.35rem;
            margin-bottom: 1rem;
        }
        .shop-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1rem;
        }
        .shop-group {
            margin-bottom: 1.4rem;
        }
        .shop-group-header {
            margin: 0 0 0.85rem;
            border-radius: 14px;
            padding: 0.8rem 1rem;
            background: #1f8a33;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 0.8rem;
            box-shadow: 0 3px 10px rgba(23, 103, 7, 0.16);
        }
        .shop-group-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.18);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            line-height: 1;
            flex-shrink: 0;
        }
        .shop-group-title {
            margin: 0;
            font-size: 1.15rem;
            font-weight: 800;
            line-height: 1.1;
        }
        .shop-group-subtitle {
            margin-top: 0.15rem;
            font-size: 0.82rem;
            opacity: 0.92;
            font-weight: 600;
        }
        .shop-card {
            background: #fff;
            border-radius: 12px;
            border: 1px solid rgba(23, 103, 7, 0.12);
            padding: 1rem;
            text-align: left;
        }
        .shop-card img {
            width: 100%;
            aspect-ratio: 4 / 3;
            object-fit: cover;
            border-radius: 10px;
            margin-bottom: 0.75rem;
            background: #edf2eb;
            display: block;
        }
        .shop-name {
            font-size: 0.82rem;
            color: #2d7d23;
            font-weight: 600;
            margin-bottom: 0.2rem;
        }
        .item-name {
            font-size: 1.05rem;
            font-weight: 700;
            margin-bottom: 0.4rem;
        }
        .badge-popular {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            margin: 0 0 0.6rem;
            padding: 0.28rem 0.55rem;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 700;
            color: #1f5a1a;
            background: #ecf9e8;
            border: 1px solid rgba(31, 90, 26, 0.18);
        }
        .meta {
            font-size: 0.9rem;
            margin-bottom: 0.6rem;
        }
        .stock-ok { color: #1e7a16; }
        .stock-low { color: #9a6700; }
        .stock-out { color: #b50000; font-weight: 600; }
        .buy-form {
            display: grid;
            grid-template-columns: 1fr;
            gap: 0.45rem;
        }
        .buy-form input,
        .buy-form button {
            width: 100%;
            padding: 0.5rem 0.65rem;
            border-radius: 8px;
            border: 1px solid #c7d9bf;
            font: inherit;
        }
        .buy-form button {
            border: none;
            background: var(--accent-color);
            color: #fff;
            font-weight: 600;
            cursor: pointer;
        }
        .buy-form button:disabled {
            background: #b4b4b4;
            cursor: not-allowed;
        }
        .notice {
            margin-bottom: 0.8rem;
            padding: 0.7rem 0.9rem;
            border-radius: 8px;
            font-size: 0.92rem;
        }
        .ok { background: #ecf9e8; color: #205f18; }
        .staff-preview-banner {
            margin-bottom: 0.85rem;
            padding: 0.65rem 0.9rem;
            border-radius: 10px;
            font-size: 0.9rem;
            line-height: 1.45;
            background: #fff8e6;
            border: 1px solid #e6c86a;
            color: #5c4a12;
        }
        .staff-preview-banner a {
            color: #1f5a1a;
            font-weight: 700;
        }
        .site-header .staff-back {
            margin-left: auto;
            font-size: 0.88rem;
            font-weight: 700;
            color: #1f5a1a;
            text-decoration: underline;
            text-underline-offset: 2px;
        }
        .site-header .staff-back:hover { color: #143d12; }
        .site-header a.admin-nav-link {
            font-size: 0.88rem;
            font-weight: 700;
            color: #1f5a1a;
            text-decoration: underline;
            text-underline-offset: 2px;
        }
        .site-header a.admin-nav-link:hover { color: #143d12; }
        .site-header .admin-nav-badge {
            display: inline-block;
            min-width: 1.1em;
            padding: 0 5px;
            margin-left: 3px;
            border-radius: 999px;
            background: #1f5a1a;
            color: #fff;
            font-size: 0.72rem;
            font-weight: 800;
            text-decoration: none;
        }
        .shop-lead-muted {
            margin: 0 0 0.75rem;
            font-size: 0.92rem;
            color: #5a6b52;
        }
        .cart-link {
            font-weight: 700;
            color: var(--accent-color);
            text-decoration: none;
        }
        .cart-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <header class="site-header">
        <a class="logo" href="index.php">Greenwood Zoo</a>
        <?php if (!empty($staffPreview)): ?>
            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-left:auto">
                <?php if ($role === 'admin'): ?>
                    <?php include __DIR__ . '/admin_header_cart_profile.inc.php'; ?>
                    <a class="staff-back" href="logout.php">Logout</a>
                <?php endif; ?>
                <a class="staff-back" href="<?= htmlspecialchars($staffPreviewDashboardHref) ?>">← Back to dashboard</a>
            </div>
        <?php else: ?>
            <?php require __DIR__ . '/customer_nav.php'; ?>
        <?php endif; ?>
    </header>

    <main class="shop-wrap">
        <section class="shop-panel">
            <h1>Gift Shop</h1>
            <?php if (!empty($staffPreview)): ?>
                <div class="staff-preview-banner" role="status">
                    <strong>Staff preview</strong> — this is the customer-facing catalog. Cart and checkout require a
                    <a href="unified_login.php">customer login</a>. Use this page to check photos and copy after adding items.
                </div>
                <p class="shop-lead-muted">Add to cart is disabled in preview.</p>
            <?php else: ?>
                <p>Add souvenirs to your cart, then open <a class="cart-link" href="cart.php">your cart</a> to review and pay.</p>
            <?php endif; ?>
            <?php if ($flash !== '' && empty($staffPreview)): ?>
                <div class="notice ok"><?= htmlspecialchars($flash) ?></div>
            <?php endif; ?>
        </section>

        <?php foreach ($groupedItems as $groupName => $groupItems): ?>
            <section class="shop-group" aria-label="<?= htmlspecialchars($groupName) ?>">
                <?php
                    $groupLower = strtolower($groupName);
                    $groupIcon = '🛍️';
                    $groupSubtitle = 'Zoo Gift Collection';
                    if (str_contains($groupLower, 'arctic')) {
                        $groupIcon = '❄️';
                        $groupSubtitle = 'Arctic Collection';
                    } elseif (str_contains($groupLower, 'jungle')) {
                        $groupIcon = '🌿';
                        $groupSubtitle = 'Rainforest Collection';
                    } elseif (str_contains($groupLower, 'savanna') || str_contains($groupLower, 'safari')) {
                        $groupIcon = '🦁';
                        $groupSubtitle = 'African Plains';
                    }
                ?>
                <div class="shop-group-header">
                    <div class="shop-group-icon" aria-hidden="true"><?= $groupIcon ?></div>
                    <div>
                        <h2 class="shop-group-title"><?= htmlspecialchars($groupName) ?></h2>
                        <div class="shop-group-subtitle"><?= htmlspecialchars($groupSubtitle) ?></div>
                    </div>
                </div>
                <div class="shop-grid">
                    <?php foreach ($groupItems as $item): ?>
                        <?php
                            $stock = (int) $item['StockQty'];
                            $stockClass = $stock <= 0 ? 'stock-out' : ($stock <= 3 ? 'stock-low' : 'stock-ok');
                        ?>
                        <article class="shop-card">
                            <img src="<?= htmlspecialchars(gift_shop_resolved_image_url($item)) ?>" alt="<?= htmlspecialchars($item['ItemName']) ?>" loading="lazy" decoding="async">
                            <div class="shop-name"><?= htmlspecialchars($item['ShopName']) ?></div>
                            <div class="item-name"><?= htmlspecialchars($item['ItemName']) ?></div>
                            <?php if ($mostPopularShopItemID !== null && (int) $item['ShopItemID'] === $mostPopularShopItemID): ?>
                                <div class="badge-popular" title="<?= htmlspecialchars($mostPopularLabel !== '' ? ($mostPopularLabel . ' is the top seller this month.') : 'Top seller this month.') ?>">
                                    ★ Most popular this month
                                </div>
                            <?php endif; ?>
                            <div class="meta">$<?= number_format((float) $item['Price'], 2) ?></div>
                            <div class="meta <?= $stockClass ?>">
                                Stock: <?= $stock <= 0 ? 'Out of stock' : $stock ?>
                            </div>
                            <form class="buy-form" method="POST" action="cart_action.php">
                                <input type="hidden" name="action" value="add">
                                <input type="hidden" name="type" value="shop">
                                <input type="hidden" name="id" value="<?= (int) $item['ShopItemID'] ?>">
                                <input type="hidden" name="redirect" value="<?= !empty($staffPreview) ? 'giftshop.php?preview=1' : 'giftshop.php?added=1' ?>">
                                <label>
                                    Quantity
                                    <input type="number" name="qty" min="1" max="<?= max(1, $stock) ?>" value="1" <?= $stock <= 0 || !empty($staffPreview) ? 'disabled' : '' ?>>
                                </label>
                                <button type="submit" <?= $stock <= 0 || !empty($staffPreview) ? 'disabled' : '' ?>>Add to cart</button>
                            </form>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </main>
</body>
</html>
