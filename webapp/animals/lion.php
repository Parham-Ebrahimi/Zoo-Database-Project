<?php require_once __DIR__ . '/../session_bootstrap.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lion – Greenwood Zoo</title>
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
        <img src="https://images.unsplash.com/photo-1546182990-dffeafbe841d?auto=format&fit=crop&w=1600&q=80" alt="Lion at Greenwood Zoo">
        <div class="animal-hero-text">
            <h1>Lion</h1>
            <p>African Lion · <em>Panthera leo</em></p>
        </div>
    </div>

    <main class="animal-detail">
        <a class="back-link" href="../animals.php">← Back to all animals</a>

        <h2>About our Lion</h2>
        <p>
            The lion is the only truly social cat species in the world, living in cooperative family groups
            called prides. Known as the "King of the Jungle" — though lions actually prefer open savannas —
            they are the second largest cat on Earth after the tiger. Our resident lion, Simba, lives in
            the Elephant Exhibit's neighbouring pride territory, a sprawling savanna habitat complete with
            elevated rock outcrops for surveying his kingdom. His magnificent mane and resonant roar —
            audible up to 8 km away — make him one of Greenwood Zoo's most iconic residents.
        </p>

        <div class="fact-grid">
            <div class="fact-card"><strong>120–250 kg</strong><span>Average weight</span></div>
            <div class="fact-card"><strong>10–14 yrs</strong><span>Lifespan</span></div>
            <div class="fact-card"><strong>Vulnerable</strong><span>Conservation status</span></div>
            <div class="fact-card"><strong>Sub-Saharan Africa</strong><span>Native habitat</span></div>
        </div>

        <h2>Meet Our Lion</h2>
        <table class="residents-table">
            <thead><tr><th>Name</th><th>Age</th><th>Sex</th><th>Diet</th><th>Enclosure</th></tr></thead>
            <tbody>
                <tr><td>Simba</td><td>7 yr</td><td>Male</td><td>Carnivore</td><td>Elephant Exhibit</td></tr>
            </tbody>
        </table>

        <h2>Conservation</h2>
        <p>
            Lion populations have declined by more than 40% over the past two decades, with fewer than
            25,000 estimated to remain in the wild. Habitat loss, prey depletion, and conflict with
            livestock farmers are primary drivers of their decline. Greenwood Zoo supports African lion
            conservation programs and community-based coexistence initiatives to reduce human-wildlife conflict.
        </p>

        <h2>Feeding & Care</h2>
        <p>
            Simba receives a carnivorous diet of whole prey and prepared meat, carefully managed by our
            big cat care team. Enrichment activities — including scent trails, carcass hides, and novel
            objects — help him express natural behaviours. His large territory in the Elephant Exhibit
            gives him ample space to roam, rest, and mark his domain.
        </p>
    </main>

    <footer class="site-footer">
        <p>&copy; 2026 Team 9 COSC 3380 Zoo Database Systems Project.</p>
        <p><a href="../login.html">Login</a> · <a href="../signup.html">Sign up</a></p>
    </footer>
</body>
</html>