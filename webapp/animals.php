<?php require_once __DIR__ . '/session_bootstrap.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Animals – Greenwood Zoo</title>
    <link rel="stylesheet" href="index.css">
    <style>
        .animals-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 24px;
            padding: 40px 5%;
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
            padding: 60px 5% 30px;
        }
        .page-hero h1 { color: #1a4a1a; margin-bottom: 8px; }
    </style>
</head>
<body>
    <header class="site-header">
        <a class="logo" href="index.php">Greenwood Zoo</a>
        <nav aria-label="Main">
            <ul class="nav-links">
                <?php if (isset($_SESSION['customer_id'])): ?>
                    <li><span>Welcome, <?= $_SESSION['firstname'] ?></span></li>
                    <li><a href="customer_profile.php">Profile</a></li>
                    <li><a href="logout.php">Logout</a></li>
                <?php else: ?>
                    <li><a href="login.html">Login</a></li>
                    <li><a href="signup.html">Sign Up</a></li>
                <?php endif; ?>
                <li><a href="index.php#about">About</a></li>
                <li><a href="index.php#hours">Hours</a></li>
                <li><a href="animals.php" aria-current="page">Animals</a></li>
                <li><a href="index.php#visit">Visit</a></li>
            </ul>
        </nav>
    </header>

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
            "img" => "https://images.unsplash.com/photo-1517840901100-8179e982acb7?auto=format&fit=crop&w=800&q=80",
            "alt" => "Polar bear in a snowy landscape"
        ],
        [
            "name" => "Jaguar",
            "slug" => "jaguar",
            "blurb" => "The Americas' largest cat, built for power with a beautifully spotted coat.",
            "img" => "https://images.unsplash.com/photo-1605559424843-9e4c228bf1c2?auto=format&fit=crop&w=800&q=80",
            "alt" => "Jaguar with spotted coat"
        ],
        [
            "name" => "Seals",
            "slug" => "seal",
            "blurb" => "Playful and graceful marine mammals at home in both water and on land.",
            "img" => "https://images.unsplash.com/photo-1564349683136-77e08dba1ef7?auto=format&fit=crop&w=800&q=80",
            "alt" => "Seal resting on a rock"
        ],
        [
            "name" => "Anaconda",
            "slug" => "anaconda",
            "blurb" => "The world's heaviest snake, a stealthy constrictor of South American rivers.",
            "img" => "https://images.unsplash.com/photo-1531386151447-fd76ad50012f?auto=format&fit=crop&w=800&q=80",
            "alt" => "Large green anaconda"
        ],
        [
            "name" => "Crocodile",
            "slug" => "crocodile",
            "blurb" => "Ancient reptiles that have outlasted the dinosaurs, unchanged for millions of years.",
            "img" => "https://images.unsplash.com/photo-1591389703635-e15a07b842d7?auto=format&fit=crop&w=800&q=80",
            "alt" => "Crocodile with open jaws"
        ],
        [
            "name" => "Caiman",
            "slug" => "caiman",
            "blurb" => "A formidable crocodilian and apex predator of South American river systems.",
            "img" => "https://images.unsplash.com/photo-1589656966895-2f33e7653819?auto=format&fit=crop&w=800&q=80",
            "alt" => "Caiman on a riverbank"
        ],
        [
            "name" => "Shark",
            "slug" => "shark",
            "blurb" => "Ocean's ultimate predator, evolving virtually unchanged for over 450 million years.",
            "img" => "https://images.unsplash.com/photo-1560275619-4cc5fa59d3ae?auto=format&fit=crop&w=800&q=80",
            "alt" => "Shark swimming underwater"
        ],
        [
            "name" => "Otter",
            "slug" => "otter",
            "blurb" => "Playful and intelligent river mammals with a love for water and fish.",
            "img" => "https://images.unsplash.com/photo-1558618666-fcd25c85cd64?auto=format&fit=crop&w=800&q=80",
            "alt" => "Otter floating in water"
        ],
        [
            "name" => "Macaw",
            "slug" => "macaw",
            "blurb" => "Brilliantly coloured parrots renowned for their intelligence and long lifespans.",
            "img" => "https://images.unsplash.com/photo-1552728089-57bdde30beb3?auto=format&fit=crop&w=800&q=80",
            "alt" => "Colourful macaw perched on a branch"
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
            "img" => "https://images.unsplash.com/photo-1619368100791-5ef5b3600e6c?auto=format&fit=crop&w=800&q=80",
            "alt" => "Capybara resting near water"
        ],
        [
            "name" => "Tapir",
            "slug" => "tapir",
            "blurb" => "A living fossil with a prehensile snout, unchanged for tens of millions of years.",
            "img" => "https://images.unsplash.com/photo-1611689342806-0863700ce1e4?auto=format&fit=crop&w=800&q=80",
            "alt" => "Tapir in a jungle setting"
        ],
        [
            "name" => "Camel",
            "slug" => "camel",
            "blurb" => "Desert survivors built to endure extreme heat and long stretches without water.",
            "img" => "https://images.unsplash.com/photo-1548767797-d8c844163c4a?auto=format&fit=crop&w=800&q=80",
            "alt" => "Camel in a desert landscape"
        ],
    ];
    ?>

    <main>
        <div class="animals-grid">
            <?php foreach ($animals as $a): ?>
            <a class="animal-tile" href="animals/<?= $a['slug'] ?>.php">
                <img src="<?= $a['img'] ?>" alt="<?= $a['alt'] ?>" loading="lazy">
                <div class="animal-tile-caption">
                    <h3><?= $a['name'] ?></h3>
                    <p><?= $a['blurb'] ?></p>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </main>

    <footer class="site-footer">
        <p>&copy; 2026 Team 9 COSC 3380 Zoo Database Systems Project.</p>
        <p><a href="login.html">Login</a> · <a href="signup.html">Sign up</a></p>
    </footer>
</body>
</html>