<?php
session_start();
if (!isset($_SESSION['customer_id'])) {
    header('Location: sign-in.html');
    exit;
}
require_once 'db.php';

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = ['food' => [], 'shop' => [], 'ticket' => []];
}

// Load shop items grouped by shop
$items = $pdo->query("
    SELECT si.ShopItemID, si.ItemName, si.Price,
           s.ShopName, s.ShopID, b.Building_Name
    FROM shop_items si
    JOIN shops s  ON si.ShopID = s.ShopID
    JOIN building b ON s.Building_ID = b.Building_ID
    ORDER BY s.ShopID, si.ItemName
")->fetchAll(PDO::FETCH_ASSOC);

// Images keyed by substrings matched against shop_items.ItemName (first match wins).
// Local files live in /images (repo root); path is relative to webapp/ pages.
$shopImages = [
    'map'       => 'https://images.unsplash.com/photo-1524661135-423995f22d0b?w=600&q=80',
    'hat'       => 'https://images.unsplash.com/photo-1521369909029-2afed882baee?w=600&q=80',
    'elephant'  => '../images/elephant plush.jpg',
    'lion'      => '../images/lion plush.jpg',
    'stuffed'   => '../images/lion plush.jpg',
    'plush'     => '../images/lion plush.jpg',
    'mug'       => 'https://images.unsplash.com/photo-1514228742587-6b1558fcca3d?w=600&q=80',
    'post card' => '../images/post card.webp',
    'postcard'  => '../images/post card.webp',
    'bracelet'  => 'https://images.unsplash.com/photo-1611591437281-460bfbe1220a?w=600&q=80',
    'keychain'  => 'https://images.unsplash.com/photo-1601924994987-69e26d50dc26?w=600&q=80',
    't-shirt'   => 'https://images.unsplash.com/photo-1576566588028-4147f3842f27?w=600&q=80',
    'earring'   => 'https://images.unsplash.com/photo-1633810543613-4c96e0c8a3a8?w=600&q=80',
    'snow globe'=> 'https://images.unsplash.com/photo-1512389142860-9c449e58a543?w=600&q=80',
    'bottle'    => 'https://images.unsplash.com/photo-1602143407151-7111542de6e8?w=600&q=80',
    'default'   => 'https://images.unsplash.com/photo-1607082348824-0a96f2a4b9da?w=600&q=80',
];

function getShopImage(string $name, array $images): string {
    $lower = strtolower($name);
    foreach ($images as $keyword => $url) {
        if ($keyword !== 'default' && str_contains($lower, $keyword)) return $url;
    }
    return $images['default'];
}

/** Safe src for local paths with spaces; passes through http(s) URLs. */
function giftImageSrc(string $src): string {
    if (preg_match('#^https?://#i', $src)) {
        return $src;
    }
    $src = str_replace('\\', '/', $src);
    $segments = array_values(array_filter(explode('/', $src), function ($s) {
        return $s !== '';
    }));
    return implode('/', array_map('rawurlencode', $segments));
}

$cartCount = array_sum($_SESSION['cart']['food'])
           + array_sum($_SESSION['cart']['shop'])
           + array_sum(array_column($_SESSION['cart']['ticket'], 'qty'));

// Group by shop
$byShop = [];
foreach ($items as $item) {
    $byShop[$item['ShopName']][] = $item;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gift Shop — Greenwood Zoo</title>
    <link rel="stylesheet" href="customer-reports.css">
    <style>
        .zoo-nav { display:flex; flex-wrap:wrap; align-items:center; gap:.75rem 1.25rem; }
        .zoo-nav a { color:var(--cr-muted); text-decoration:none; font-weight:600; font-size:.9rem; }
        .zoo-nav a:hover { color:var(--cr-accent); }
        .zoo-nav .cr-btn-outline { padding:.45rem 1rem; border:1px solid var(--cr-border); border-radius:999px; background:var(--cr-surface); }

        .cart-badge { position:relative; display:inline-flex; align-items:center; gap:.4rem; }
        .cart-badge .badge { position:absolute; top:-8px; right:-10px; background:var(--cr-accent); color:white; font-size:.65rem; font-weight:700; padding:1px 6px; border-radius:999px; min-width:18px; text-align:center; }

        .shop-banner {
            background: linear-gradient(135deg, #2c1a4a 0%, #4a3a6a 100%);
            border-radius: 16px;
            padding: 1.25rem 1.75rem;
            margin-bottom: 1.75rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            color: white;
        }
        .shop-banner .icon { font-size:2rem; }
        .shop-banner h2 { margin:0 0 .2rem; font-size:1.15rem; font-weight:700; }
        .shop-banner p  { margin:0; font-size:.875rem; opacity:.8; }

        .shop-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 1.25rem;
            margin-bottom: 2.5rem;
        }

        .shop-card {
            background: var(--cr-surface);
            border: 1px solid var(--cr-border);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(26,46,22,.07);
            display: flex;
            flex-direction: column;
            transition: transform .2s, box-shadow .2s;
        }
        .shop-card:hover { transform:translateY(-3px); box-shadow:0 8px 24px rgba(26,46,22,.13); }

        .shop-card-img { width:100%; height:180px; object-fit:cover; display:block; background:#f0ecf8; }

        .shop-card-body { padding:1rem 1.1rem 1.25rem; flex:1; display:flex; flex-direction:column; }
        .shop-card-body h3 { margin:0 0 .3rem; font-size:.95rem; font-weight:700; color:var(--cr-text); }
        .shop-card-price { font-size:1.1rem; font-weight:700; color:#4a3a6a; margin-bottom:.85rem; }

        .add-form { display:flex; gap:.5rem; align-items:center; margin-top:auto; }
        .qty-input { width:54px; padding:.45rem .6rem; border:1px solid var(--cr-border); border-radius:8px; font:inherit; font-size:.9rem; text-align:center; }
        .qty-input:focus { outline:none; border-color:#9b7fd4; }
        .add-btn { flex:1; padding:.55rem 1rem; background:#4a3a6a; color:white; border:none; border-radius:8px; font:inherit; font-weight:600; font-size:.88rem; cursor:pointer; transition:background .15s; }
        .add-btn:hover { background:#2c1a4a; }

        .toast { position:fixed; bottom:1.5rem; right:1.5rem; background:#2c1a4a; color:white; padding:.85rem 1.5rem; border-radius:12px; font-weight:600; font-size:.9rem; box-shadow:0 4px 20px rgba(0,0,0,.25); z-index:999; opacity:0; transform:translateY(12px); transition:opacity .25s, transform .25s; pointer-events:none; }
        .toast.show { opacity:1; transform:translateY(0); }
    </style>
</head>
<body class="cr-body">
<div class="cr-shell">
    <header class="cr-topbar">
        <span class="cr-brand">Greenwood Zoo</span>
        <nav class="zoo-nav">
            <a href="customer-dashboard.php">Dashboard</a>
            <a href="restaurant.php">Restaurant</a>
            <a href="buy_tickets.php">Tickets</a>
            <a href="cart.php" class="cr-btn-outline cart-badge">
                🛒 Cart
                <?php if ($cartCount > 0): ?>
                    <span class="badge" id="cart-count"><?= $cartCount ?></span>
                <?php endif; ?>
            </a>
            <a href="logout.php" class="cr-btn-outline">Sign out</a>
        </nav>
    </header>

    <main>
        <div style="margin-bottom:2rem">
            <h1 style="font-size:clamp(1.6rem,3vw,2rem);font-weight:700;margin:0 0 .35rem">🛍️ Gift Shop</h1>
            <p style="color:var(--cr-muted);margin:0">Take a piece of Greenwood home. Browse our shops across the zoo.</p>
        </div>

        <?php foreach ($byShop as $shopName => $shopItems):
            $building = $shopItems[0]['Building_Name'];
        ?>
        <div class="shop-banner">
            <span class="icon">🏪</span>
            <div>
                <h2><?= htmlspecialchars($shopName) ?></h2>
                <p>📍 <?= htmlspecialchars($building) ?></p>
            </div>
        </div>

        <div class="shop-grid">
            <?php foreach ($shopItems as $item):
                $img = getShopImage($item['ItemName'], $shopImages);
            ?>
            <div class="shop-card">
                <img class="shop-card-img"
                     src="<?= htmlspecialchars(giftImageSrc($img), ENT_QUOTES) ?>"
                     alt="<?= htmlspecialchars($item['ItemName']) ?>"
                     loading="lazy">
                <div class="shop-card-body">
                    <h3><?= htmlspecialchars($item['ItemName']) ?></h3>
                    <div class="shop-card-price">$<?= number_format($item['Price'], 2) ?></div>
                    <form class="add-form" method="POST" action="cart_action.php">
                        <input type="hidden" name="action"   value="add">
                        <input type="hidden" name="type"     value="shop">
                        <input type="hidden" name="id"       value="<?= $item['ShopItemID'] ?>">
                        <input type="hidden" name="redirect" value="giftshop.php">
                        <input type="hidden" name="item_name" value="<?= htmlspecialchars($item['ItemName'], ENT_QUOTES) ?>">
                        <input class="qty-input" type="number" name="qty" value="1" min="1" max="20">
                        <button class="add-btn" type="submit">Add to cart</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    </main>
</div>

<div class="toast" id="toast"></div>

<script>
function showToast(msg) {
    const t = document.getElementById('toast');
    t.textContent = '✓ ' + msg + ' added!';
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 2200);
}

document.querySelectorAll('.add-form').forEach(form => {
    form.addEventListener('submit', e => {
        e.preventDefault();
        const name = form.querySelector('[name="item_name"]')?.value || 'Item';
        const data = new FormData(form);
        fetch('cart_action.php', { method:'POST', body:data,
            headers:{'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.json())
        .then(d => {
            showToast(name);
            const badge = document.getElementById('cart-count');
            if (badge) badge.textContent = d.count;
            else {
                const link = document.querySelector('a[href="cart.php"]');
                if (link) {
                    const b = document.createElement('span');
                    b.id='cart-count'; b.className='badge'; b.textContent=d.count;
                    link.appendChild(b);
                }
            }
        });
    });
});
</script>
</body>
</html>
