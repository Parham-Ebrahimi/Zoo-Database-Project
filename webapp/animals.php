<?php
require_once __DIR__ . '/session_bootstrap.php';
$isCustomer = isset($_SESSION['customer_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Animals – Greenwood Zoo</title>
    <link rel="stylesheet" href="index.css">
    <style>
        .animals-page-inner {
            max-width: 1100px;
            margin: 0 auto;
            padding: 0 1rem 2.5rem;
        }
        .animals-back {
            margin: 0 0 0.75rem;
        }
        .animals-back a {
            display: inline-block;
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--cr-accent, #2d6a2d);
            text-decoration: none;
        }
        .animals-back a:hover {
            text-decoration: underline;
        }
        .animals-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 24px;
            padding: 24px 0 40px;
        }
        .animal-tile {
            border-radius: 12px;
            overflow: hidden;
            background: white;
            box-shadow: 0 2px 12px rgba(0,0,0,0.1);
            transition: transform 0.25s, box-shadow 0.25s;
            text-decoration: none;
            color: inherit;
            display: block;
        }
        .animal-tile:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.18);
        }
        .animal-tile img {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
        .animal-tile-caption {
            padding: 14px;
        }
        .animal-tile-caption h3 {
            margin: 0 0 4px;
            color: #2d6a2d;
            font-size: 1.1rem;
        }
        .animal-tile-caption p {
            margin: 0;
            font-size: 0.85rem;
            color: #555;
        }
        .page-hero {
            background: #e8f5e9;
            text-align: center;
            padding: 48px 5% 28px;
            border-radius: 0 0 var(--cr-radius, 12px) var(--cr-radius, 12px);
        }
        .page-hero h1 { color: #1a4a1a; margin-bottom: 8px; }
        .page-hero p { margin: 0; color: #3d5c3d; font-size: 1rem; }
        .animals-customer-foot {
            font-size: 0.85rem;
            color: var(--cr-muted, #666);
            margin-top: 0.5rem;
        }
        .animals-customer-foot a {
            color: var(--cr-accent, #2d6a2d);
            font-weight: 600;
        }
    </style>
</head>
<body class="<?= $isCustomer ? 'cr-body' : '' ?>">
<?php if ($isCustomer): ?>
    <div class="profile-wrapper">
        <header class="site-header">
            <a class="logo" href="index.php">Greenwood Zoo</a>
            <?php require __DIR__ . '/customer_nav.php'; ?>
        </header>
        <div class="profile-card animals-page-inner">
            <p class="animals-back"><a href="customer-dashboard.php">← Back to dashboard</a></p>
<?php else: ?>
    <header class="site-header">
        <a class="logo" href="index.php">Greenwood Zoo</a>
        <nav aria-label="Main">
            <ul class="nav-links">
                <li><a href="login.html">Login</a></li>
                <li><a href="signup.html">Sign Up</a></li>
                <li><a href="index.php#about">About</a></li>
                <li><a href="index.php#hours">Hours</a></li>
                <li><a href="animals.php" aria-current="page">Animals</a></li>
                <li><a href="index.php#visit">Visit</a></li>
            </ul>
        </nav>
    </header>
    <div class="animals-page-inner">
<?php endif; ?>

    <div class="page-hero">
        <h1>Our Animals</h1>
        <p>Discover the incredible wildlife that calls Greenwood home.</p>
    </div>

    <?php
    $animals = [
        [
            "name" => "Elephants",
            "slug" => "elephants",
            "blurb" => "The largest land animals on Earth, known for their memory and intelligence.",
            "img" => "https://images.unsplash.com/photo-1771341398737-b2467b6776a7?auto=format&fit=crop&w=800&q=80",
            "alt" => "Baby elephant in a grassy field"
        ],
        [
            "name" => "Giraffes",
            "slug" => "giraffes",
            "blurb" => "The tallest living terrestrial animals, with distinctive long necks.",
            "img" => "https://images.unsplash.com/photo-1737738736083-838af5116f95?auto=format&fit=crop&w=800&q=80",
            "alt" => "Giraffe silhouette at sunset"
        ],
        [
            "name" => "Penguins",
            "slug" => "penguins",
            "blurb" => "Flightless seabirds perfectly adapted to life in and around cold water.",
            "img" => "https://images.unsplash.com/photo-1737498352674-aadc9f986eea?auto=format&fit=crop&w=800&q=80",
            "alt" => "Penguin on a rocky beach"
        ],
        [
            "name" => "Red Pandas",
            "slug" => "red-pandas",
            "blurb" => "Adorable, tree-dwelling mammals native to the eastern Himalayas.",
            "img" => "https://images.unsplash.com/photo-1656899367542-3fc106faa104?auto=format&fit=crop&w=800&q=80",
            "alt" => "Red panda in a tree"
        ],
    ];
    ?>

    <main>
        <div class="animals-grid">
            <?php foreach ($animals as $a): ?>
            <a class="animal-tile" href="animals/<?= htmlspecialchars($a['slug']) ?>.php">
                <img src="<?= htmlspecialchars($a['img']) ?>" alt="<?= htmlspecialchars($a['alt']) ?>" loading="lazy">
                <div class="animal-tile-caption">
                    <h3><?= htmlspecialchars($a['name']) ?></h3>
                    <p><?= htmlspecialchars($a['blurb']) ?></p>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php if ($isCustomer): ?>
            <p class="animals-customer-foot">
                Looking for every animal in our collection?
            </p>
        <?php endif; ?>
    </main>

<?php if ($isCustomer): ?>
        </div>
    </div>
<?php else: ?>
    </div>
    <footer class="site-footer">
        <p>&copy; 2026 Team 9 COSC 3380 Zoo Database Systems Project.</p>
        <p><a href="login.html">Login</a> · <a href="signup.html">Sign up</a></p>
    </footer>
<?php endif; ?>
</body>
</html>
