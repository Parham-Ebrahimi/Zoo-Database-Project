<?php require_once __DIR__ . '/../session_bootstrap.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Penguins – Greenwood Zoo</title>
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
        <img src="https://images.unsplash.com/photo-1737498352674-aadc9f986eea?auto=format&fit=crop&w=1600&q=80" alt="Penguins at Greenwood Zoo">
        <div class="animal-hero-text">
            <h1>Penguins</h1>
            <p>African & Gentoo Penguin · <em>Spheniscus demersus / Pygoscelis papua</em></p>
        </div>
    </div>

    <main class="animal-detail">
        <a class="back-link" href="../animals.php">← Back to all animals</a>

        <h2>About our Penguins</h2>
        <p>
            Greenwood Zoo is home to a lively colony of penguins, one of our most popular exhibits.
            Despite their tuxedo-like appearance, penguins are remarkable athletes — they can swim
            at speeds up to 25 mph and hold their breath for several minutes while hunting fish beneath
            the surface. Highly social birds, our penguins live in a close-knit group and are known
            for their entertaining antics both in and out of the water.
        </p>

        <div class="fact-grid">
            <div class="fact-card"><strong>2–5 kg</strong><span>Average weight</span></div>
            <div class="fact-card"><strong>15–20 yrs</strong><span>Lifespan</span></div>
            <div class="fact-card"><strong>Vulnerable</strong><span>Conservation status</span></div>
            <div class="fact-card"><strong>Southern Hemisphere</strong><span>Native habitat</span></div>
        </div>

        <h2>Meet Our Penguins</h2>
        <table class="residents-table">
            <thead><tr><th>Name</th><th>Age</th><th>Sex</th><th>Diet</th></tr></thead>
            <tbody>
                <tr><td>Kowalski</td><td>3 yr</td><td>Male</td><td>Piscivore</td></tr>
                <tr><td>Rico</td><td>3 yr</td><td>Male</td><td>Piscivore</td></tr>
                <tr><td>Skipper</td><td>4 yr</td><td>Male</td><td>Piscivore</td></tr>
                <tr><td>Private</td><td>2 yr</td><td>Male</td><td>Piscivore</td></tr>
                <tr><td>Pingu</td><td>1 yr</td><td>Male</td><td>Piscivore</td></tr>
                <tr><td>Cabo</td><td>1 yr</td><td>Male</td><td>Piscivore</td></tr>
            </tbody>
        </table>

        <h2>Conservation</h2>
        <p>
            Several penguin species face significant threats from climate change, overfishing, and habitat loss.
            Greenwood Zoo actively supports ocean conservation programs and sustainable fisheries initiatives
            to protect wild penguin populations along the southern coasts of Africa and South America.
        </p>

        <h2>Feeding & Care</h2>
        <p>
            Our penguins are fed a carefully managed diet of fresh fish, including herring and capelin,
            tailored to each individual's age and health. The Penguin Enclosure features both an indoor
            climate-controlled habitat and an outdoor splash pool, giving visitors a chance to watch
            these incredible swimmers in action year-round.
        </p>
    </main>

    <footer class="site-footer">
        <p>&copy; 2026 Team 9 COSC 3380 Zoo Database Systems Project.</p>
        <p><a href="../login.html">Login</a> · <a href="../signup.html">Sign up</a></p>
    </footer>
</body>
</html>