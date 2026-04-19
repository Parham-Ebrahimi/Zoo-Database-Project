<?php require_once __DIR__ . '/../session_bootstrap.php';
require_once __DIR__ . '/../db.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seals – Greenwood Zoo</title>
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
        <img src="https://images.unsplash.com/photo-1572880393162-0518ac760495?q=80&w=1674&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D" alt="Seals at Greenwood Zoo">
        <div class="animal-hero-text">
            <h1>Seals</h1>
            <p>Harbour Seal · <em>Phoca vitulina</em></p>
        </div>
    </div>

    <main class="animal-detail">
        <a class="back-link" href="../animals.php">← Back to all animals</a>

        <h2>About our Seals</h2>
        <p>
            Seals are sleek, intelligent marine mammals perfectly evolved for life between the land and sea.
            Our two resident seals, Sally and Wally, live in the Seal Pool — a large aquatic habitat
            with both underwater viewing and a spacious haul-out area where they can bask and rest.
            Seals are extraordinary swimmers, using their rear flippers to propel themselves through water
            with effortless grace while their large, expressive eyes help them hunt in low-light conditions
            beneath the surface.
        </p>

        <div class="fact-grid">
            <div class="fact-card"><strong>55–170 kg</strong><span>Average weight</span></div>
            <div class="fact-card"><strong>25–35 yrs</strong><span>Lifespan</span></div>
            <div class="fact-card"><strong>Least Concern</strong><span>Conservation status</span></div>
            <div class="fact-card"><strong>Northern Atlantic &amp; Pacific</strong><span>Native habitat</span></div>
        </div>

        <?php
require_once __DIR__ . '/../db.php';
$animalSpecies  = ["Harbour Seal", "Harbor Seal"];
$animalKeywords = "Seal";
$animalLabel    = "Seals";
require __DIR__ . '/_animal_residents.php';
?>

        <h2>Conservation</h2>
        <p>
            While harbour seals are not currently threatened, many seal populations face pressures from
            ocean pollution, fishing net entanglement, and climate-driven changes to fish stocks.
            Greenwood Zoo advocates for ocean health initiatives and works with marine rescue organisations
            to rehabilitate stranded or injured seals found along our coastlines.
        </p>

        <h2>Feeding & Care</h2>
        <p>
            Sally and Wally are fed a piscivorous diet of fresh fish — including herring, mackerel, and
            capelin — individually tailored to their age, weight, and health. Daily feeding sessions at
            the Seal Pool are among the most popular events at the zoo, giving visitors an opportunity
            to watch these agile swimmers at their most energetic. Their last health checkups confirmed
            both animals are in excellent condition.
        </p>
    </main>

    <footer class="site-footer">
        <p>&copy; 2026 Team 9 COSC 3380 Zoo Database Systems Project.</p>
        <p><a href="../login.html">Login</a> · <a href="../signup.html">Sign up</a></p>
    </footer>
</body>
</html>