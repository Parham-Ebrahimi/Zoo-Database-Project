<?php require_once __DIR__ . '/../session_bootstrap.php';
require_once __DIR__ . '/../db.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tapir – Greenwood Zoo</title>
    <link rel="stylesheet" href="../index.css">
    <style>
        .animal-hero { position: relative; height: 420px; overflow: hidden; }
        .animal-hero img { width: 100%; height: 100%; object-fit: cover; filter: brightness(0.65); }
        .animal-hero-text { position: absolute; bottom: 40px; left: 5%; color: white; }
        .animal-hero-text h1 { font-size: 3rem; margin: 0; }
        .animal-hero-text p { font-size: 1.1rem; opacity: 0.9; }
        .animal-detail { max-width: 860px; margin: 40px auto; padding: 0 5%; }
        .fact-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 16px; margin: 24px 0; }
        .fact-card { background: #e8f5e9; border-radius: 10px; padding: 16px; text-align: center; }
        .fact-card strong { display: block; color: #2d6a2d; font-size: 1.3rem; }
        .fact-card span { font-size: 0.85rem; color: #555; }
        .back-link { display: inline-block; margin-bottom: 20px; color: #2d6a2d; text-decoration: none; font-weight: 600; }
        .back-link:hover { text-decoration: underline; }
        .residents-table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        .residents-table th, .residents-table td { text-align: left; padding: 10px 14px; border-bottom: 1px solid #d4ebd4; font-size: 0.9rem; }
        .residents-table th { background: #e8f5e9; color: #2d6a2d; font-weight: 600; }
    </style>
</head>
<body>
    <header class="site-header">
        <a class="logo" href="../index.php">Greenwood Zoo</a>
        <nav aria-label="Main">
            <ul class="nav-links">
                <?php if (isset($_SESSION['customer_id'])): ?>
                    <li><span>Welcome, <?= $_SESSION['firstname'] ?></span></li>
                    <li><a href="../customer_profile.php">Profile</a></li>
                    <li><a href="../logout.php">Logout</a></li>
                <?php else: ?>
                    <li><a href="../login.html">Login</a></li>
                    <li><a href="../signup.html">Sign Up</a></li>
                <?php endif; ?>
                <li><a href="../index.php#about">About</a></li>
                <li><a href="../index.php#hours">Hours</a></li>
                <li><a href="../animals.php">Animals</a></li>
                <li><a href="../index.php#visit">Visit</a></li>
            </ul>
        </nav>
    </header>

    <div class="animal-hero">
        <img src="https://images.unsplash.com/photo-1712938548647-8f92b804eb82?q=80&w=2070&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D" alt="Tapir at Greenwood Zoo">
        <div class="animal-hero-text">
            <h1>Tapir</h1>
            <p>Baird's Tapir · <em>Tapirus bairdii</em></p>
        </div>
    </div>

    <main class="animal-detail">
        <a class="back-link" href="../animals.php">← Back to all animals</a>

        <h2>About our Tapir</h2>
        <p>
            Tapirs are ancient animals — they've changed little in the past 35 million years, making them
            true living fossils. Distantly related to horses and rhinoceroses, tapirs are most recognisable
            for their short, flexible prehensile snout, which they use to grasp leaves and fruit. Our resident
            tapir, Juana, lives in the Jungle Floor Enclosure and is one of the zoo's most intriguing yet
            lesser-known residents. Despite their stocky build, tapirs are excellent swimmers and often
            submerge themselves to cool down or escape predators in the wild.
        </p>

        <div class="fact-grid">
            <div class="fact-card"><strong>150–300 kg</strong><span>Average weight</span></div>
            <div class="fact-card"><strong>25–30 yrs</strong><span>Lifespan</span></div>
            <div class="fact-card"><strong>Endangered</strong><span>Conservation status</span></div>
            <div class="fact-card"><strong>Central America</strong><span>Native habitat</span></div>
        </div>

        <?php
require_once __DIR__ . '/../db.php';
$animalSpecies = "Baird's Tapir";
$animalLabel   = "Tapir";
require __DIR__ . '/_animal_residents.php';
?>

        <h2>Conservation</h2>
        <p>
            All four tapir species are classified as threatened or endangered due to habitat loss and hunting.
            Tapirs are vital seed dispersers in their ecosystems — sometimes called "gardeners of the forest."
            Greenwood Zoo supports tapir conservation programs in Central America and advocates for the protection
            of lowland tropical forest habitats.
        </p>

        <h2>Feeding & Care</h2>
        <p>
            Juana enjoys a herbivorous diet of leaves, browse, aquatic plants, soft twigs, and fruit.
            Her keepers provide enrichment through food hides, novel browse, and access to a shallow water
            pool in the Jungle Floor Enclosure. As a naturally shy species, Juana is given plenty of
            private space in her habitat to feel secure and comfortable.
        </p>
    </main>

    <footer class="site-footer">
        <p>&copy; 2026 Team 9 COSC 3380 Zoo Database Systems Project.</p>
        <p><a href="../login.html">Login</a> · <a href="../signup.html">Sign up</a></p>
    </footer>
</body>
</html>