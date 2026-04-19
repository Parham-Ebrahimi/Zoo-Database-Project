<?php require_once __DIR__ . '/../session_bootstrap.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Capybara – Greenwood Zoo</title>
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
        <img src="https://images.unsplash.com/photo-1619368100791-5ef5b3600e6c?auto=format&fit=crop&w=1600&q=80" alt="Capybara at Greenwood Zoo">
        <div class="animal-hero-text">
            <h1>Capybara</h1>
            <p>Capybara · <em>Hydrochoerus hydrochaeris</em></p>
        </div>
    </div>

    <main class="animal-detail">
        <a class="back-link" href="../animals.php">← Back to all animals</a>

        <h2>About our Capybara</h2>
        <p>
            The capybara holds the title of the world's largest rodent, and it's easy to see why they've
            become an internet favourite — these gentle, sociable animals get along with almost every other
            species they meet. Our resident capybara, Don, lives in the Jungle Floor Enclosure where he
            enjoys lounging near the water and grazing on grasses throughout the day. Capybaras are semi-aquatic
            and excellent swimmers, capable of staying submerged for several minutes to evade predators in the wild.
        </p>

        <div class="fact-grid">
            <div class="fact-card"><strong>35–65 kg</strong><span>Average weight</span></div>
            <div class="fact-card"><strong>8–10 yrs</strong><span>Lifespan</span></div>
            <div class="fact-card"><strong>Least Concern</strong><span>Conservation status</span></div>
            <div class="fact-card"><strong>South America</strong><span>Native habitat</span></div>
        </div>

        <h2>Meet Our Capybara</h2>
        <table class="residents-table">
            <thead><tr><th>Name</th><th>Age</th><th>Sex</th><th>Diet</th><th>Enclosure</th></tr></thead>
            <tbody>
                <tr><td>Don</td><td>3 yr</td><td>Male</td><td>Herbivore</td><td>Jungle Floor Enclosure</td></tr>
            </tbody>
        </table>

        <h2>Conservation</h2>
        <p>
            Capybaras are currently not threatened, but they depend heavily on healthy river ecosystems in
            South America. Greenwood Zoo supports wetland and river conservation efforts across the Amazon
            basin to help preserve the habitats these animals and countless other species rely on.
        </p>

        <h2>Feeding & Care</h2>
        <p>
            Don enjoys a herbivorous diet rich in grasses, aquatic plants, fruit, and bark. Our keepers
            provide daily enrichment — including browse items and puzzle feeders — to keep him engaged and active.
            The Jungle Floor Enclosure features a wading pool where Don can cool off and exhibit natural swimming behaviour.
        </p>
    </main>

    <footer class="site-footer">
        <p>&copy; 2026 Team 9 COSC 3380 Zoo Database Systems Project.</p>
        <p><a href="../login.html">Login</a> · <a href="../signup.html">Sign up</a></p>
    </footer>
</body>
</html>