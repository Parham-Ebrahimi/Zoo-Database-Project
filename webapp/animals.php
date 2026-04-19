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
            object-position: center;
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
            "img" => "https://images.unsplash.com/photo-1564760055775-d63b17a55c44?auto=format&fit=crop&crop=center&w=800&h=534&q=80",
            "alt" => "Elephant in a grassy field"
        ],
        [
            "name" => "Giraffes",
            "slug" => "giraffes",
            "blurb" => "The tallest living terrestrial animals, with distinctive long necks.",
            "img" => "https://images.unsplash.com/photo-1547721064-da6cfb341d50?auto=format&fit=crop&crop=center&w=800&h=534&q=80",
            "alt" => "Giraffe standing in the savanna"
        ],
        [
            "name" => "Penguins",
            "slug" => "penguins",
            "blurb" => "Flightless seabirds perfectly adapted to life in and around cold water.",
            "img" => "https://images.unsplash.com/photo-1590418606746-018840f9ced0?auto=format&fit=crop&crop=center&w=800&h=534&q=80",
            "alt" => "Penguins on a snowy beach"
        ],
        [
            "name" => "Red Pandas",
            "slug" => "red-pandas",
            "blurb" => "Adorable, tree-dwelling mammals native to the eastern Himalayas.",
            "img" => "https://images.unsplash.com/photo-1592194996308-7b43878e84a6?auto=format&fit=crop&crop=center&w=800&h=534&q=80",
            "alt" => "Red panda closeup"
        ],
        [
            "name" => "Lion",
            "slug" => "lion",
            "blurb" => "The iconic apex predator of the African savanna, known for its powerful roar.",
            "img" => "https://images.unsplash.com/photo-1552410260-0fd9b577afa6?auto=format&fit=crop&crop=center&w=800&h=534&q=80",
            "alt" => "Male lion with a full mane"
        ],
        [
            "name" => "Polar Bear",
            "slug" => "polar-bear",
            "blurb" => "The world's largest land carnivore, perfectly adapted to Arctic life.",
            "img" => "https://images.unsplash.com/photo-1504618223053-559bdef9ad5f?auto=format&fit=crop&crop=center&w=800&h=534&q=80",
            "alt" => "Polar bear on ice"
        ],
        [
            "name" => "Jaguar",
            "slug" => "jaguar",
            "blurb" => "The Americas' largest cat, built for power with a beautifully spotted coat.",
            "img" => "https://images.unsplash.com/photo-1474511320723-9a56873867b5?auto=format&fit=crop&crop=center&w=800&h=534&q=80",
            "alt" => "Jaguar with spotted coat"
        ],
        [
            "name" => "Seals",
            "slug" => "seal",
            "blurb" => "Playful and graceful marine mammals at home in both water and on land.",
            "img" => "https://images.unsplash.com/photo-1535591273668-578e31182c4f?auto=format&fit=crop&crop=center&w=800&h=534&q=80",
            "alt" => "Seal resting on a rock"
        ],
        [
            "name" => "Anaconda",
            "slug" => "anaconda",
            "blurb" => "The world's heaviest snake, a stealthy constrictor of South American rivers.",
            "img" => "https://images.unsplash.com/photo-1516728778615-2d590ea1855e?auto=format&fit=crop&crop=center&w=800&h=534&q=80",
            "alt" => "Large green anaconda"
        ],
        [
            "name" => "Crocodile",
            "slug" => "crocodile",
            "blurb" => "Ancient reptiles that have outlasted the dinosaurs, unchanged for millions of years.",
            "img" => "https://images.unsplash.com/photo-1610058494806-52a5b5d3c3fe?auto=format&fit=crop&crop=center&w=800&h=534&q=80",
            "alt" => "Crocodile with open jaws"
        ],
        [
            "name" => "Caiman",
            "slug" => "caiman",
            "blurb" => "A formidable crocodilian and apex predator of South American river systems.",
            "img" => "https://images.unsplash.com/photo-1567863788954-0948a9dd8571?auto=format&fit=crop&crop=center&w=800&h=534&q=80",
            "alt" => "Caiman on a riverbank"
        ],
        [
            "name" => "Shark",
            "slug" => "shark",
            "blurb" => "Ocean's ultimate predator, evolving virtually unchanged for over 450 million years.",
            "img" => "https://images.unsplash.com/photo-1596568990924-2be8b3d2c8c4?auto=format&fit=crop&crop=center&w=800&h=534&q=80",
            "alt" => "Shark swimming underwater"
        ],
        [
            "name" => "Otter",
            "slug" => "otter",
            "blurb" => "Playful and intelligent river mammals with a love for water and fish.",
            "img" => "https://images.unsplash.com/photo-1584553421349-3557471bed79?auto=format&fit=crop&crop=center&w=800&h=534&q=80",
            "alt" => "Otter floating in water"
        ],
        [
            "name" => "Macaw",
            "slug" => "macaw",
            "blurb" => "Brilliantly coloured parrots renowned for their intelligence and long lifespans.",
            "img" => "https://images.unsplash.com/photo-1544736779-8c9e5a0a1f90?auto=format&fit=crop&crop=center&w=800&h=534&q=80",
            "alt" => "Colourful macaw closeup"
        ],
        [
            "name" => "Monkey",
            "slug" => "monkey",
            "blurb" => "Curious and acrobatic primates that thrive high in the jungle canopy.",
            "img" => "https://images.unsplash.com/photo-1540573133985-87b6da6d54a9?auto=format&fit=crop&crop=center&w=800&h=534&q=80",
            "alt" => "Monkey in a tree"
        ],
        [
            "name" => "Capybara",
            "slug" => "capybara",
            "blurb" => "The world's largest rodent — gentle, sociable, and surprisingly good swimmers.",
            "img" => "https://images.unsplash.com/photo-1598439210625-5067c578f3f6?auto=format&fit=crop&crop=center&w=800&h=534&q=80",
            "alt" => "Capybara resting near water"
        ],
        [
            "name" => "Tapir",
            "slug" => "tapir",
            "blurb" => "A living fossil with a prehensile snout, unchanged for tens of millions of years.",
            "img" => "https://images.unsplash.com/photo-1591100381189-338e1f5f3fe7?auto=format&fit=crop&crop=center&w=800&h=534&q=80",
            "alt" => "Tapir in a jungle setting"
        ],
        [
            "name" => "Camel",
            "slug" => "camel",
            "blurb" => "Desert survivors built to endure extreme heat and long stretches without water.",
            "img" => "https://images.unsplash.com/photo-1518984697134-bbbca00e6bc1?auto=format&fit=crop&crop=center&w=800&h=534&q=80",
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