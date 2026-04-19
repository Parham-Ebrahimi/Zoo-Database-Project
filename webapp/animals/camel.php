<?php require_once __DIR__ . '/../session_bootstrap.php';
require_once __DIR__ . '/../db.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Camel – Greenwood Zoo</title>
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
        <img src="https://images.unsplash.com/photo-1598113972215-96c018fb1a0b?q=80&w=2070&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D" alt="Camel at Greenwood Zoo">
        <div class="animal-hero-text">
            <h1>Camel</h1>
            <p>Dromedary Camel · <em>Camelus dromedarius</em></p>
        </div>
    </div>
    <main class="animal-detail">
        <a class="back-link" href="../animals.php">← Back to all animals</a>
        <h2>About our Camel</h2>
        <p>
            Camels are legendary survivors — perfectly adapted to withstand the harshest desert conditions
            on Earth. Contrary to popular belief, a camel's hump stores fat, not water, which is metabolised
            into energy during long journeys without food. Our resident camel, Joe, lives in the Woodlands
            Enclosure and is a favourite among younger visitors for her calm, curious demeanour. With her
            thick double eyelashes, closeable nostrils, and broad padded feet, Joe carries all the hallmarks
            of a true desert specialist.
        </p>
        <div class="fact-grid">
            <div class="fact-card"><strong>400–600 kg</strong><span>Average weight</span></div>
            <div class="fact-card"><strong>40–50 yrs</strong><span>Lifespan</span></div>
            <div class="fact-card"><strong>Least Concern</strong><span>Conservation status</span></div>
            <div class="fact-card"><strong>Middle East &amp; Africa</strong><span>Native habitat</span></div>
        </div>
        <?php
require_once __DIR__ . '/../db.php';
$animalSpecies  = "Dromedary Camel";
$animalKeywords = "Camel";
$animalLabel    = "Camel";
require __DIR__ . '/_animal_residents.php';
?>
        <h2>Conservation</h2>
        <p>
            While domesticated dromedary camels are widespread, the wild Bactrian camel is critically endangered.
            Greenwood Zoo supports programmes working to protect wild camel populations in Central Asia and
            raises awareness about the importance of preserving desert ecosystems worldwide.
        </p>
        <h2>Feeding & Care</h2>
        <p>
            Joe enjoys a herbivorous diet of hay, grasses, grains, and occasional fresh produce.
            Her care team monitors her hump condition as an indicator of overall health and nutrition.
            The Woodlands Enclosure provides Joe with plenty of space to roam, sand to roll in,
            and shade structures to rest under during the warmest parts of the day.
        </p>
    </main>
    <footer class="site-footer">
        <p>&copy; 2026 Team 9 COSC 3380 Zoo Database Systems Project.</p>
        <p><a href="../login.html">Login</a> · <a href="../signup.html">Sign up</a></p>
    </footer>
</body>
</html>
