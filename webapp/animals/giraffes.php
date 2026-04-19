<?php require_once __DIR__ . '/../session_bootstrap.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giraffes – Greenwood Zoo</title>
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
        <img src="https://images.unsplash.com/photo-1737738736083-838af5116f95?auto=format&fit=crop&w=1600&q=80" alt="Giraffe at Greenwood Zoo">
        <div class="animal-hero-text">
            <h1>Giraffes</h1>
            <p>Reticulated Giraffe · <em>Giraffa reticulata</em></p>
        </div>
    </div>

    <main class="animal-detail">
        <a class="back-link" href="../animals.php">← Back to all animals</a>

        <h2>About our Giraffes</h2>
        <p>
            The giraffe is the tallest living terrestrial animal on Earth, with adults reaching up to 5.5 metres
            in height. Their iconic long necks — containing the same number of vertebrae as a human neck, just
            much larger — allow them to browse the treetops that other herbivores cannot reach. Our resident
            giraffe, Melman, lives in the spacious Sahara Dunes Exhibit, where his towering silhouette is
            one of the most photographed sights at Greenwood Zoo. Giraffes also possess the largest heart of
            any land animal, needed to pump blood all the way up to their heads.
        </p>

        <div class="fact-grid">
            <div class="fact-card"><strong>750–1,270 kg</strong><span>Average weight</span></div>
            <div class="fact-card"><strong>25 yrs</strong><span>Lifespan</span></div>
            <div class="fact-card"><strong>Vulnerable</strong><span>Conservation status</span></div>
            <div class="fact-card"><strong>Sub-Saharan Africa</strong><span>Native habitat</span></div>
        </div>

        <h2>Meet Our Giraffes</h2>
        <table class="residents-table">
            <thead><tr><th>Name</th><th>Age</th><th>Sex</th><th>Diet</th><th>Enclosure</th></tr></thead>
            <tbody>
                <tr><td>Melman</td><td>10 yr</td><td>Male</td><td>Herbivore</td><td>Sahara Dunes Exhibit</td></tr>
            </tbody>
        </table>

        <h2>Conservation</h2>
        <p>
            Giraffe populations have declined by up to 40% over the past three decades due to habitat loss,
            civil unrest, and poaching. Greenwood Zoo supports the Giraffe Conservation Foundation's efforts
            to monitor and protect wild giraffe populations across Africa, and raises awareness that giraffes
            are now classified as Vulnerable on the IUCN Red List.
        </p>

        <h2>Feeding & Care</h2>
        <p>
            Melman enjoys a herbivorous diet of acacia leaves, hay, and fresh browse, consuming up to
            34 kg of vegetation daily. His care team conducts regular health checks and provides enrichment
            through elevated feeding stations that encourage natural foraging postures. Visitors can get
            remarkably close to Melman at the Sahara Dunes Exhibit's raised viewing and feeding platform.
        </p>
    </main>

    <footer class="site-footer">
        <p>&copy; 2026 Team 9 COSC 3380 Zoo Database Systems Project.</p>
        <p><a href="../login.html">Login</a> · <a href="../signup.html">Sign up</a></p>
    </footer>
</body>
</html>
