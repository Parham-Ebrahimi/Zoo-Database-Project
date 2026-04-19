<?php require_once __DIR__ . '/../session_bootstrap.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jaguar – Greenwood Zoo</title>
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
        <img src="https://images.unsplash.com/photo-1605559424843-9e4c228bf1c2?auto=format&fit=crop&w=1600&q=80" alt="Jaguar at Greenwood Zoo">
        <div class="animal-hero-text">
            <h1>Jaguar</h1>
            <p>Jaguar · <em>Panthera onca</em></p>
        </div>
    </div>

    <main class="animal-detail">
        <a class="back-link" href="../animals.php">← Back to all animals</a>

        <h2>About our Jaguar</h2>
        <p>
            The jaguar is the largest cat in the Americas and the third largest in the world. Unlike most
            big cats, jaguars are powerful swimmers and actively seek out water. Our resident jaguar, Jackson,
            lives in the Jungle Floor Enclosure — a richly planted habitat designed to mirror the dense
            forests and riverbanks of the Amazon basin. Jackson is a magnificent carnivore with a distinctive
            rosette-patterned coat, known for the most powerful bite of any big cat relative to its size.
        </p>

        <div class="fact-grid">
            <div class="fact-card"><strong>56–96 kg</strong><span>Average weight</span></div>
            <div class="fact-card"><strong>12–15 yrs</strong><span>Lifespan</span></div>
            <div class="fact-card"><strong>Near Threatened</strong><span>Conservation status</span></div>
            <div class="fact-card"><strong>Central &amp; South America</strong><span>Native habitat</span></div>
        </div>

        <h2>Meet Our Jaguar</h2>
        <table class="residents-table">
            <thead><tr><th>Name</th><th>Age</th><th>Sex</th><th>Diet</th><th>Enclosure</th></tr></thead>
            <tbody>
                <tr><td>Jackson</td><td>7 yr</td><td>Male</td><td>Carnivore</td><td>Jungle Floor Enclosure</td></tr>
            </tbody>
        </table>

        <h2>Conservation</h2>
        <p>
            Jaguars face significant threats from habitat loss, fragmentation, and conflict with ranchers and farmers.
            Greenwood Zoo supports jaguar corridor programs that connect fragmented forest habitats across Central
            and South America, giving wild jaguar populations the space they need to survive and thrive.
        </p>

        <h2>Feeding & Care</h2>
        <p>
            Jackson is fed a carnivorous diet including whole prey and prepared meat, managed by our big cat
            veterinary team. His enclosure features climbing structures, water features, and scent enrichment
            to stimulate natural hunting instincts. Jackson's last checkup was on April 15, 2026, confirming
            he is in excellent health.
        </p>
    </main>

    <footer class="site-footer">
        <p>&copy; 2026 Team 9 COSC 3380 Zoo Database Systems Project.</p>
        <p><a href="../login.html">Login</a> · <a href="../signup.html">Sign up</a></p>
    </footer>
</body>
</html>