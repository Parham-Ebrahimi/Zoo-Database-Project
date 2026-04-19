<?php require_once __DIR__ . '/../session_bootstrap.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Caiman – Greenwood Zoo</title>
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
        <img src="https://images.unsplash.com/photo-1557868363-8d9d44d5b9e4?q=80&w=2070&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D" alt="Caiman at Greenwood Zoo">
        <div class="animal-hero-text">
            <h1>Caiman</h1>
            <p>Black Caiman · <em>Melanosuchus niger</em></p>
        </div>
    </div>

    <main class="animal-detail">
        <a class="back-link" href="../animals.php">← Back to all animals</a>

        <h2>About our Caiman</h2>
        <p>
            The caiman is a crocodilian native to the freshwater habitats of Central and South America.
            Our resident caiman, King, lives in the River Basin Enclosure — a carefully controlled aquatic
            habitat that mimics the slow-moving rivers and flooded forests of the Amazon. Caimans are
            stealthy, patient hunters that rely on camouflage and stillness to ambush prey. King is
            particularly impressive specimen — dark-scaled, powerfully built, and a master of the motionless wait.
        </p>

        <div class="fact-grid">
            <div class="fact-card"><strong>Up to 300 kg</strong><span>Average weight</span></div>
            <div class="fact-card"><strong>30–40 yrs</strong><span>Lifespan</span></div>
            <div class="fact-card"><strong>Conservation Dependent</strong><span>Conservation status</span></div>
            <div class="fact-card"><strong>South America</strong><span>Native habitat</span></div>
        </div>

        <h2>Meet Our Caiman</h2>
        <table class="residents-table">
            <thead><tr><th>Name</th><th>Age</th><th>Sex</th><th>Diet</th><th>Enclosure</th></tr></thead>
            <tbody>
                <tr><td>King</td><td>10 yr</td><td>Male</td><td>Carnivore</td><td>River Basin Enclosure</td></tr>
            </tbody>
        </table>

        <h2>Conservation</h2>
        <p>
            Black caimans were once hunted nearly to extinction for their skins but have recovered significantly
            due to conservation protections. Greenwood Zoo supports Amazonian wetland conservation and works
            with South American wildlife agencies to monitor wild caiman populations and protect critical nesting sites.
        </p>

        <h2>Feeding & Care</h2>
        <p>
            King is fed a carnivorous diet including fish and whole prey, on a schedule calibrated by our
            reptile care team. The River Basin Enclosure maintains the warm, humid conditions caimans need
            and includes both deep water swimming areas and dry basking platforms. King's powerful presence
            makes him one of the most awe-inspiring residents in the zoo.
        </p>
    </main>

    <footer class="site-footer">
        <p>&copy; 2026 Team 9 COSC 3380 Zoo Database Systems Project.</p>
        <p><a href="../login.html">Login</a> · <a href="../signup.html">Sign up</a></p>
    </footer>
</body>
</html>
