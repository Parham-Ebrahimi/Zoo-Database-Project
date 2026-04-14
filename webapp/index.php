<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Greenwood Wildlife Zoo</title>
    <link rel="stylesheet" href="index.css">
</head>
<body>
    <header class="site-header">
        <a class="logo" href="index.php">Greenwood Zoo</a>

        <nav aria-label="Main">
            <ul class="nav-links">

                <?php if (isset($_SESSION['customer_id'])): ?>
                    <li><span>Welcome, <?= $_SESSION['firstname'] ?></span></li>
                    <li><a href="customer_profile.php">Profile</a></li>
                    <li><a href="logout.php">Logout</a></li>
                <?php else: ?>
                    <li><a href="login.html">Login</a></li>
                    <li><a href="signup.html">Sign Up</a></li>
                <?php endif; ?>

                <li><a href="#about">About</a></li>
                <li><a href="#hours">Hours</a></li>
                <li><a href="animals.php">Animals</a></li>
                <li><a href="#visit">Visit</a></li>
            </ul>
        </nav>
    </header>

    <section class="hero" aria-labelledby="hero-title">
        <div class="hero-inner">
            <h1 id="hero-title">Wildlife. Wonder. Conservation.</h1>
            <p>
                Greenwood Wildlife Zoo is home to animals from around the world. Explore naturalistic habitats,
                learn from our educators, and support species protection—all in one visit.
            </p>
            <div class="hero-actions">
                <a class="btn btn-primary" href="#hours">Plan your visit</a>
                <a class="btn btn-outline" href="signup.html">Create an account</a>
            </div>
        </div>
    </section>

    <main>
        <section id="about" aria-labelledby="about-heading">
            <h2 id="about-heading">About our zoo</h2>
            <p class="lead">
                Founded to connect people with nature, Greenwood combines accredited animal care with research and
                community programs. Every ticket helps fund habitat restoration and breeding programs for threatened species.
            </p>
        </section>

        <section id="hours" aria-labelledby="hours-heading">
            <h2 id="hours-heading">Hours &amp; seasons</h2>
            <div class="card" style="max-width: 560px; margin: 0 auto;">
                <table class="hours-table">
                    <thead>
                        <tr>
                            <th scope="col">Day</th>
                            <th scope="col">Open</th>
                            <th scope="col">Last entry</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Monday – Friday</td>
                            <td>9:00 a.m.</td>
                            <td>4:30 p.m.</td>
                        </tr>
                        <tr>
                            <td>Saturday &amp; Sunday</td>
                            <td>8:00 a.m.</td>
                            <td>5:00 p.m.</td>
                        </tr>
                        <tr>
                            <td>Holidays</td>
                            <td>8:00 a.m.</td>
                            <td>4:00 p.m.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section id="gallery" aria-labelledby="gallery-heading">
            <h2 id="gallery-heading">Meet some of our residents</h2>
            <p class="lead">A few highlights from our habitats, see them in person on your next visit!</p>
            <div class="gallery">
                <figure class="animal-card">
                    <img src="https://images.unsplash.com/photo-1771341398737-b2467b6776a7?auto=format&fit=crop&w=800&q=80" alt="Baby elephant in a grassy field" width="800" height="600" loading="lazy">
                    <figcaption>
                        Elephants
                        <a class="animal-link" href="animals/elephants.php">Learn More →</a>
                    </figcaption>
                </figure>
                <figure class="animal-card">
                    <img src="https://images.unsplash.com/photo-1737738736083-838af5116f95?auto=format&fit=crop&w=800&q=80" alt="Giraffe standing in a field" width="800" height="600" loading="lazy">
                    <figcaption>
                        Giraffes
                        <a class="animal-link" href="animals/giraffes.php">Learn More →</a>
                    </figcaption>
                </figure>
                <figure class="animal-card">
                    <img src="https://images.unsplash.com/photo-1737498352674-aadc9f986eea?auto=format&fit=crop&w=800&q=80" alt="Penguin on a rocky beach" width="800" height="600" loading="lazy">
                    <figcaption>
                        Penguins
                        <a class="animal-link" href="animals/penguins.php">Learn More →</a>
                    </figcaption>
                </figure>
                <figure class="animal-card">
                    <img src="https://images.unsplash.com/photo-1656899367542-3fc106faa104?auto=format&fit=crop&w=800&q=80" alt="Red panda in a tree" width="800" height="600" loading="lazy">
                    <figcaption>
                        Red pandas
                        <a class="animal-link" href="animals/red-pandas.php">Learn More →</a>
                    </figcaption>
                </figure>
            </div>
        </section>

        <section id="visit" aria-labelledby="visit-heading">
            <h2 id="visit-heading">Plan your visit</h2>

            <div class="visit-info">
                <div class="card">
                    <strong>Address</strong>
                    <span>1234 Greenwood Street, 00000</span>
                </div>
                <div class="card">
                    <strong>Phone</strong>
                    <span>(123) 456-ZOOO</span>
                </div>
                <div class="card">
                    <strong>Parking</strong>
                    <span>Free general parking; EV spots in Lot B</span>
                </div>
            </div>

            <div style="margin-top:20px; text-align:center;">
                <?php if (isset($_SESSION['customer_id'])): ?>
                    <a class="btn btn-primary" href="buy-tickets.php">Buy Tickets</a>
                <?php else: ?>
                    <a class="btn btn-primary" href="login.html"
                       onclick="alert('You must login or create an account first')">
                       Buy Tickets
                    </a>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <footer class="site-footer">
        <p>&copy; 2026 Team 8 COSC 3380 Zoo Database Systems Project.</p>
        <p>
            <a href="login.html">Login</a>
            ·
            <a href="signup.html">Sign up</a>
        </p>
    </footer>
</body>
</html>