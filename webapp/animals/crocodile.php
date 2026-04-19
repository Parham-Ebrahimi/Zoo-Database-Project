<?php require_once __DIR__ . '/../session_bootstrap.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crocodile – Greenwood Zoo</title>
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
        <img src="https://images.unsplash.com/photo-1611069648374-733e7bb73e5c?q=80&w=2070&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D" alt="Crocodile at Greenwood Zoo">
        <div class="animal-hero-text">
            <h1>Crocodile</h1>
            <p>Nile Crocodile · <em>Crocodylus niloticus</em></p>
        </div>
    </div>

    <main class="animal-detail">
        <a class="back-link" href="../animals.php">← Back to all animals</a>

        <h2>About our Crocodile</h2>
        <p>
            Crocodiles are among the most ancient reptiles on Earth — their lineage stretches back over
            200 million years, predating the dinosaurs. Our resident crocodile, King, is a formidable
            Nile crocodile housed in the Giraffe Exhibit's adjacent waterway habitat. Nile crocodiles
            are among the largest reptiles in the world, renowned for their incredible bite force, ambush
            hunting technique, and surprising parental care behaviours. Despite their fearsome reputation,
            crocodiles are intelligent animals with complex social structures.
        </p>

        <div class="fact-grid">
            <div class="fact-card"><strong>Up to 750 kg</strong><span>Average weight</span></div>
            <div class="fact-card"><strong>70–100 yrs</strong><span>Lifespan</span></div>
            <div class="fact-card"><strong>Least Concern</strong><span>Conservation status</span></div>
            <div class="fact-card"><strong>Sub-Saharan Africa</strong><span>Native habitat</span></div>
        </div>

        <h2>Meet Our Crocodile</h2>
        <table class="residents-table">
            <thead><tr><th>Name</th><th>Age</th><th>Sex</th><th>Diet</th><th>Enclosure</th></tr></thead>
            <tbody>
                <tr><td>King</td><td>10 yr</td><td>Male</td><td>Carnivore</td><td>Giraffe Exhibit</td></tr>
            </tbody>
        </table>

        <h2>Conservation</h2>
        <p>
            While Nile crocodiles are currently classified as Least Concern, many crocodilian species worldwide
            remain threatened. Greenwood Zoo supports the IUCN Crocodile Specialist Group and advocates for
            the protection of wetland habitats across Africa, which are vital not just for crocodiles but for
            the entire ecosystem they anchor.
        </p>

        <h2>Feeding & Care</h2>
        <p>
            King is fed a carnivorous diet of fish and whole prey on a carefully managed schedule by our
            reptile care team. His habitat includes deep water areas for swimming, dry basking platforms,
            and naturalistic landscaping. Visitors can view King safely through reinforced viewing areas —
            his sheer size alone makes for an unforgettable encounter.
        </p>
    </main>

    <footer class="site-footer">
        <p>&copy; 2026 Team 9 COSC 3380 Zoo Database Systems Project.</p>
        <p><a href="../login.html">Login</a> · <a href="../signup.html">Sign up</a></p>
    </footer>
</body>
</html>