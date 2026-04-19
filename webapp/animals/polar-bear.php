<?php require_once __DIR__ . '/../session_bootstrap.php';
require_once __DIR__ . '/../db.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Polar Bear – Greenwood Zoo</title>
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
        <img src="https://images.unsplash.com/photo-1589656966895-2f33e7653819?auto=format&fit=crop&w=1600&q=80" alt="Polar Bear at Greenwood Zoo">
        <div class="animal-hero-text">
            <h1>Polar Bear</h1>
            <p>Polar Bear · <em>Ursus maritimus</em></p>
        </div>
    </div>

    <main class="animal-detail">
        <a class="back-link" href="../animals.php">← Back to all animals</a>

        <h2>About our Polar Bear</h2>
        <p>
            The polar bear is the world's largest land carnivore and the Arctic's apex predator. Uniquely
            adapted to life on sea ice, polar bears have water-repellent fur, a thick layer of insulating
            blubber, and large, partially webbed paws that make them powerful swimmers capable of crossing
            vast stretches of open ocean. Our resident polar bear, Wojtek, lives in the Polar Bear Exhibit —
            a climate-controlled habitat with chilled pools, ice features, and naturalistic terrain that
            supports his physical and psychological wellbeing year-round.
        </p>

        <div class="fact-grid">
            <div class="fact-card"><strong>350–700 kg</strong><span>Average weight</span></div>
            <div class="fact-card"><strong>20–30 yrs</strong><span>Lifespan</span></div>
            <div class="fact-card"><strong>Vulnerable</strong><span>Conservation status</span></div>
            <div class="fact-card"><strong>Arctic Circle</strong><span>Native habitat</span></div>
        </div>

        <?php
require_once __DIR__ . '/../db.php';
$animalSpecies = "Polar Bear";
$animalLabel   = "Polar Bear";
require __DIR__ . '/_animal_residents.php';
?>

        <h2>Conservation</h2>
        <p>
            Polar bears are listed as Vulnerable, with climate change posing the most severe threat to their
            survival. As Arctic sea ice shrinks, polar bears lose both their hunting grounds and their travel
            routes. Greenwood Zoo actively supports Arctic conservation research and advocates for ambitious
            climate action as the single most important factor in securing the polar bear's future.
        </p>

        <h2>Feeding & Care</h2>
        <p>
            Wojtek receives a carnivorous diet managed by our specialist bear care team, including fish,
            meat, and enrichment food items frozen into ice blocks to encourage natural foraging behaviour.
            His most recent health checkup on April 2, 2026 confirmed he is in excellent health. The Polar
            Bear Exhibit's refrigerated pool and snow-making capabilities ensure Wojtek stays comfortable
            and active regardless of the season.
        </p>
    </main>

    <footer class="site-footer">
        <p>&copy; 2026 Team 9 COSC 3380 Zoo Database Systems Project.</p>
        <p><a href="../login.html">Login</a> · <a href="../signup.html">Sign up</a></p>
    </footer>
</body>
</html>
