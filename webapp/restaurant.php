<?php
session_start();
if (!isset($_SESSION['customer_id'])) {
    header('Location: sign-in.html');
    exit;
}
require_once 'db.php';

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = ['food' => [], 'ticket' => []];
}
$_SESSION['cart']['shop'] = [];

// Load all food items with stall info
$items = $pdo->query("
    SELECT fi.FoodID, fi.FoodName, fi.Price, fs.Name AS StallName, fs.Location
    FROM fooditem fi
    JOIN foodstall fs ON fi.StallID = fs.StallID
    ORDER BY fi.FoodName
")->fetchAll(PDO::FETCH_ASSOC);

// Unsplash food images mapped by food name keywords
$foodImages = [
    'burger'   => 'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?w=600&q=80',
    'fries'    => 'https://images.unsplash.com/photo-1630384060421-cb20d0e0649d?w=600&q=80',
    'smoothie' => 'https://images.unsplash.com/photo-1553530666-ba11a7da3888?w=600&q=80',
    'pizza'    => 'https://images.unsplash.com/photo-1513104890138-7c749659a591?w=600&q=80',
    'pepperoni'=> 'https://images.unsplash.com/photo-1534308983496-4fabb1a015ee?w=600&q=80',
    'drink'    => 'https://images.unsplash.com/photo-1437418747212-8d9709afab22?w=600&q=80',
    'salad'    => 'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?w=600&q=80',
    'water'    => 'https://images.unsplash.com/photo-1548839140-29a749e1cf4d?w=600&q=80',
    'burrito'  => 'https://images.unsplash.com/photo-1626700051175-6818013e1d4f?w=600&q=80',
    'default'  => 'https://images.unsplash.com/photo-1504674900247-0877df9cc836?w=600&q=80',
];

function getFoodImage(string $name, array $images): string {
    $lower = strtolower($name);
    foreach ($images as $keyword => $url) {
        if ($keyword !== 'default' && str_contains($lower, $keyword)) return $url;
    }
    return $images['default'];
}

// Cart count for badge
$cartCount = array_sum($_SESSION['cart']['food'])
           + array_sum(array_column($_SESSION['cart']['ticket'], 'qty'));

$added = $_GET['added'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restaurant — Greenwood Zoo</title>
    <link rel="stylesheet" href="index.css">
    <style>
        .page-hero { margin-bottom:2rem; }
        .page-hero h1 { font-size:clamp(1.6rem,3vw,2rem); font-weight:800; margin:0 0 .35rem; color:var(--text-color); }
        .page-hero p  { color:var(--cr-muted); margin:0; }

        .stall-banner {
            background: linear-gradient(135deg, var(--text-color) 0%, #2a8a3a 100%);
            border-radius: 16px;
            padding: 1.25rem 1.75rem;
            margin-bottom: 1.75rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            color: white;
        }
        .stall-banner .stall-icon { font-size:2rem; }
        .stall-banner h2 { margin:0 0 .2rem; font-size:1.15rem; font-weight:700; }
        .stall-banner p  { margin:0; font-size:.875rem; opacity:.8; }

        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 1.25rem;
        }

        .menu-card {
            background: var(--cr-surface);
            border: 1px solid var(--cr-border);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(26,46,22,.07);
            display: flex;
            flex-direction: column;
            transition: transform .2s, box-shadow .2s;
        }
        .menu-card:hover { transform:translateY(-3px); box-shadow:0 8px 24px rgba(26,46,22,.13); }

        .menu-card-img {
            width:100%; height:180px; object-fit:cover; display:block; background:#e2e8dc;
        }

        .menu-card-body {
            padding: 1rem 1.1rem 1.25rem;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        .menu-card-body h3 { margin:0 0 .3rem; font-size:1rem; font-weight:700; color:var(--cr-text); }
        .menu-card-price { font-size:1.15rem; font-weight:700; color:var(--cr-accent); margin-bottom:.85rem; }

        .add-form { display:flex; gap:.5rem; align-items:center; margin-top:auto; }
        .qty-input {
            width:54px; padding:.45rem .6rem; border:1px solid var(--cr-border);
            border-radius:8px; font:inherit; font-size:.9rem; text-align:center;
        }
        .qty-input:focus { outline:none; border-color:var(--cr-accent-soft); }
        .add-btn {
            flex:1; padding:.55rem 1rem; background:var(--cr-accent); color:white;
            border:none; border-radius:8px; font:inherit; font-weight:600; font-size:.88rem;
            cursor:pointer; transition:background .15s;
        }
        .add-btn:hover { background:#1a5c2b; }

        .toast {
            position:fixed; bottom:1.5rem; right:1.5rem; background:#1a4a1a; color:white;
            padding:.85rem 1.5rem; border-radius:12px; font-weight:600; font-size:.9rem;
            box-shadow:0 4px 20px rgba(0,0,0,.25); z-index:999;
            opacity:0; transform:translateY(12px);
            transition:opacity .25s, transform .25s;
            pointer-events:none;
        }
        .toast.show { opacity:1; transform:translateY(0); }
    </style>
</head>
<body>
    <header class="site-header">
        <a class="logo" href="index.php">Greenwood Zoo</a>
        <nav aria-label="Main">
            <ul class="nav-links">
                <li><a href="index.php">Home</a></li>
                <li><a href="customer-dashboard.php">Dashboard</a></li>
                <li><a href="restaurant.php">Restaurant</a></li>
                <li><a href="buy_tickets.php">Buy tickets</a></li>
                <li><a href="giftshop.php">Gift shop</a></li>
                <li>
                    <a href="cart.php" class="nav-cart-link">🛒 Cart<?php if ($cartCount > 0): ?><span class="nav-cart-badge" id="cart-count"><?= (int) $cartCount ?></span><?php endif; ?></a>
                </li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </nav>
    </header>

    <main>
        <div class="page-hero">
            <h1>🍽️ Restaurant</h1>
            <p>Fresh food served daily at our on-site food stalls. Add items to your cart and pay at checkout.</p>
        </div>

        <?php
        // Group items by stall
        $byStall = [];
        foreach ($items as $item) {
            $byStall[$item['StallName']][] = $item;
        }
        foreach ($byStall as $stallName => $stallItems):
            $loc = $stallItems[0]['Location'];
        ?>
        <div class="stall-banner">
            <span class="stall-icon">🏕️</span>
            <div>
                <h2><?= htmlspecialchars($stallName) ?></h2>
                <p>📍 <?= htmlspecialchars($loc) ?></p>
            </div>
        </div>

        <div class="menu-grid" style="margin-bottom:2.5rem">
            <?php foreach ($stallItems as $item):
                $img = getFoodImage($item['FoodName'], $foodImages);
            ?>
            <div class="menu-card">
                <img class="menu-card-img"
                     src="<?= $img ?>"
                     alt="<?= htmlspecialchars($item['FoodName']) ?>"
                     loading="lazy">
                <div class="menu-card-body">
                    <h3><?= htmlspecialchars($item['FoodName']) ?></h3>
                    <div class="menu-card-price">$<?= number_format($item['Price'], 2) ?></div>
                    <form class="add-form" method="POST" action="cart_action.php"
                          onsubmit="showToast('<?= htmlspecialchars($item['FoodName'], ENT_QUOTES) ?> added!')">
                        <input type="hidden" name="action"   value="add">
                        <input type="hidden" name="type"     value="food">
                        <input type="hidden" name="id"       value="<?= $item['FoodID'] ?>">
                        <input type="hidden" name="redirect" value="restaurant.php">
                        <input class="qty-input" type="number" name="qty" value="1" min="1" max="20">
                        <button class="add-btn" type="submit">Add to cart</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    </main>

<div class="toast" id="toast"></div>

<script>
function showToast(msg) {
    const t = document.getElementById('toast');
    t.textContent = '✓ ' + msg;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 2200);
}

// Intercept all add-to-cart forms with AJAX so page doesn't reload
document.querySelectorAll('.add-form').forEach(form => {
    form.addEventListener('submit', e => {
        e.preventDefault();
        const msg = form.getAttribute('onsubmit').match(/'(.+)'/)[1];
        const data = new FormData(form);
        fetch('cart_action.php', { method:'POST', body:data,
            headers:{'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.json())
        .then(d => {
            showToast(msg);
            const badge = document.getElementById('cart-count');
            if (badge) { badge.textContent = d.count; }
            else {
                const link = document.querySelector('a.nav-cart-link');
                if (link) {
                    const b = document.createElement('span');
                    b.id = 'cart-count'; b.className = 'nav-cart-badge'; b.textContent = d.count;
                    link.appendChild(b);
                }
            }
        });
    });
});
</script>
</body>
</html>
