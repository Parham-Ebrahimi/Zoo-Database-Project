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
        [
            "name" => "Lion",
            "slug" => "lion",
            "blurb" => "The iconic apex predator of the African savanna, known for its powerful roar.",
            "img" => "https://images.unsplash.com/photo-1546182990-dffeafbe841d?auto=format&fit=crop&w=800&q=80",
            "alt" => "Lion resting in the grass"
        ],
        [
            "name" => "Polar Bear",
            "slug" => "polar-bear",
            "blurb" => "The world's largest land carnivore, perfectly adapted to Arctic life.",
            "img" => "https://images.unsplash.com/photo-1589656966895-2f33e7653819?auto=format&fit=crop&w=1600&q=80",
            "alt" => "Polar bear in a snowy landscape"
        ],
        [
            "name" => "Jaguar",
            "slug" => "jaguar",
            "blurb" => "The Americas' largest cat, built for power with a beautifully spotted coat.",
            "img" => "https://images.unsplash.com/photo-1616128417743-c3a6992a65e7?q=80&w=2072&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D",
            "alt" => "Jaguar with spotted coat"
        ],
        [
            "name" => "Seals",
            "slug" => "seal",
            "blurb" => "Playful and graceful marine mammals at home in both water and on land.",
            "img" => "https://images.unsplash.com/photo-1572880393162-0518ac760495?q=80&w=1674&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D",
            "alt" => "Seal resting on a rock"
        ],
        [
            "name" => "Anaconda",
            "slug" => "anaconda",
            "blurb" => "The world's heaviest snake, a stealthy constrictor of South American rivers.",
            "img" => "https://images.unsplash.com/photo-1600682322637-95c40966e79f?q=80&w=2100&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D",
            "alt" => "Large green anaconda"
        ],
        [
            "name" => "Crocodile",
            "slug" => "crocodile",
            "blurb" => "Ancient reptiles that have outlasted the dinosaurs, unchanged for millions of years.",
            "img" => "https://images.unsplash.com/photo-1611069648374-733e7bb73e5c?q=80&w=2070&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D",
            "alt" => "Crocodile with open jaws"
        ],
        [
            "name" => "Caiman",
            "slug" => "caiman",
            "blurb" => "A formidable crocodilian and apex predator of South American river systems.",
            "img" => "https://images.unsplash.com/photo-1557868363-8d9d44d5b9e4?q=80&w=2070&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D",
            "alt" => "Caiman on a riverbank"
        ],
        [
            "name" => "Shark",
            "slug" => "shark",
            "blurb" => "Ocean's ultimate predator, evolving virtually unchanged for over 450 million years.",
            "img" => "https://images.unsplash.com/photo-1586115457457-b3753fe50cf1?q=80&w=1688&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D",
            "alt" => "Shark swimming underwater"
        ],
        [
            "name" => "Otter",
            "slug" => "otter",
            "blurb" => "Playful and intelligent river mammals with a love for water and fish.",
            "img" => "https://images.unsplash.com/photo-1633967920376-33b2d94f091f?q=80&w=2070&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D",
            "alt" => "Otter floating in water"
        ],
        [
            "name" => "Macaw",
            "slug" => "macaw",
            "blurb" => "Brilliantly colored parrots renowned for their intelligence and long lifespans.",
            "img" => "https://images.unsplash.com/photo-1664545141018-c70ca9e78a76?q=80&w=1625&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D",
            "alt" => "Colorful macaw perched on a branch"
        ],
        [
            "name" => "Monkey",
            "slug" => "monkey",
            "blurb" => "Curious and acrobatic primates that thrive high in the jungle canopy.",
            "img" => "https://images.unsplash.com/photo-1540573133985-87b6da6d54a9?auto=format&fit=crop&w=800&q=80",
            "alt" => "Monkey in a tree"
        ],
        [
            "name" => "Capybara",
            "slug" => "capybara",
            "blurb" => "The world's largest rodent — gentle, sociable, and surprisingly good swimmers.",
            "img" => "https://images.unsplash.com/photo-1701772164869-dfb2cac483dc?q=80&w=2070&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D",
            "alt" => "Capybara resting near water"
        ],
        [
            "name" => "Tapir",
            "slug" => "tapir",
            "blurb" => "A living fossil with a prehensile snout, unchanged for tens of millions of years.",
            "img" => "https://images.unsplash.com/photo-1712938548647-8f92b804eb82?q=80&w=2070&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D",
            "alt" => "Tapir in a jungle setting"
        ],
        [
            "name" => "Camel",
            "slug" => "camel",
            "blurb" => "Desert survivors built to endure extreme heat and long stretches without water.",
            "img" => "https://images.unsplash.com/photo-1598113972215-96c018fb1a0b?q=80&w=2070&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D",
            "alt" => "Camel in a desert landscape"
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
