<?php require_once __DIR__ . '/../session_bootstrap.php';
require_once __DIR__ . '/../db.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Anaconda – Greenwood Zoo</title>
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
        <img src="https://images.unsplash.com/photo-1600682322637-95c40966e79f?q=80&w=2100&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D" alt="Anaconda at Greenwood Zoo">
        <div class="animal-hero-text">
            <h1>Anaconda</h1>
            <p>Green Anaconda · <em>Eunectes murinus</em></p>
        </div>
    </div>

    <main class="animal-detail">
        <a class="back-link" href="../animals.php">← Back to all animals</a>

        <h2>About our Anaconda</h2>
        <p>
            The green anaconda is the world's largest snake by weight and one of the longest, capable of
            reaching over 8 metres in length. Our resident anaconda, Craig, lives in the River Basin Enclosure —
            a lush, humid environment that mirrors the tropical wetlands of South America where anacondas thrive.
            As a non-venomous constrictor, Craig hunts by coiling around prey and applying pressure.
            He is a master of patience, often remaining perfectly still for hours before striking.
        </p>

        <div class="fact-grid">
            <div class="fact-card"><strong>Up to 250 kg</strong><span>Average weight</span></div>
            <div class="fact-card"><strong>10–30 yrs</strong><span>Lifespan</span></div>
            <div class="fact-card"><strong>Least Concern</strong><span>Conservation status</span></div>
            <div class="fact-card"><strong>South America</strong><span>Native habitat</span></div>
        </div>

        <?php
require_once __DIR__ . '/../db.php';
$animalSpecies = 'Green Anaconda';
$animalLabel   = 'Anaconda';
require __DIR__ . '/_animal_residents.php';
?>

        <h2>Conservation</h2>
        <p>
            Anacondas play a vital role in maintaining the balance of Amazonian ecosystems by regulating prey
            populations. Though currently not endangered, habitat destruction from deforestation poses a growing
            threat. Greenwood Zoo supports South American wetland conservation programs to protect the
            biodiversity of anaconda habitats.
        </p>

        <h2>Feeding & Care</h2>
        <p>
            Anacondas are ambush carnivores with slow metabolisms — Craig is fed whole prey items on a carefully
            scheduled basis by our reptile care team. The River Basin Enclosure maintains precise humidity and
            temperature controls to keep him healthy year-round. Guests may occasionally observe feeding days
            — check the daily schedule at the visitor centre for details.
        </p>
    </main>

    <footer class="site-footer">
        <p>&copy; 2026 Team 9 COSC 3380 Zoo Database Systems Project.</p>
        <p><a href="../login.html">Login</a> · <a href="../signup.html">Sign up</a></p>
    </footer>
</body>
</html>