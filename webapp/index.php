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
                    <li><a href="customer-dashboard.php">Dashboard</a></li>
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

                <a href="animals/elephants.php" style="text-decoration:none;">
                    <figure class="animal-card">
                        <img src="https://images.unsplash.com/photo-1771341398737-b2467b6776a7?auto=format&fit=crop&w=800&q=80" alt="Baby elephant in a grassy field" width="800" height="600" loading="lazy">
                        <figcaption>Elephants</figcaption>
                    </figure>
                </a>

                <a href="animals/giraffes.php" style="text-decoration:none;">
                    <figure class="animal-card">
                        <img src="https://images.unsplash.com/photo-1737738736083-838af5116f95?auto=format&fit=crop&w=800&q=80" alt="Giraffe standing in a field" width="800" height="600" loading="lazy">
                        <figcaption>Giraffes</figcaption>
                    </figure>
                </a>

                <a href="animals/penguins.php" style="text-decoration:none;">
                    <figure class="animal-card">
                        <img src="https://images.unsplash.com/photo-1737498352674-aadc9f986eea?auto=format&fit=crop&w=800&q=80" alt="Penguin on a rocky beach" width="800" height="600" loading="lazy">
                        <figcaption>Penguins</figcaption>
                    </figure>
                </a>

                <a href="animals/red-pandas.php" style="text-decoration:none;">
                    <figure class="animal-card">
                        <img src="https://images.unsplash.com/photo-1656899367542-3fc106faa104?auto=format&fit=crop&w=800&q=80" alt="Red panda in a tree" width="800" height="600" loading="lazy">
                        <figcaption>Red pandas</figcaption>
                    </figure>
                </a>

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
                    <a class="btn btn-primary" href="buy_tickets.php">Buy Tickets</a>
                <?php else: ?>
                    <button type="button" class="btn btn-primary" id="open-guest-buy-modal" aria-haspopup="dialog" aria-controls="guest-buy-modal">
                        Buy Tickets
                    </button>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <footer class="site-footer">
        <p>&copy; 2026 Team 9 COSC 3380 Zoo Database Systems Project.</p>
        <p>
            <a href="login.html">Login</a>
            ·
            <a href="signup.html">Sign up</a>
        </p>
    </footer>

    <div id="guest-buy-modal" class="site-modal" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="guest-buy-modal-title">
        <div class="site-modal__backdrop" data-close-modal></div>
        <div class="site-modal__panel">
            <h2 id="guest-buy-modal-title" class="site-modal__title">Log in to buy tickets</h2>
            <p class="site-modal__text">You need an account to purchase zoo tickets. Log in if you already have one, or create a new account to get started.</p>
            <div class="site-modal__actions">
                <a class="btn btn-primary" href="login.html">Log in</a>
                <a class="btn btn-outline" href="signup.html">Create account</a>
                <button type="button" class="btn btn-outline" data-close-modal>Not now</button>
            </div>
        </div>
    </div>
    <script>
    (function () {
        var openBtn = document.getElementById('open-guest-buy-modal');
        var modal = document.getElementById('guest-buy-modal');
        if (!openBtn || !modal) return;
        var closers = modal.querySelectorAll('[data-close-modal]');
        function isOpen() {
            return modal.classList.contains('site-modal--open');
        }
        function openModal() {
            modal.classList.add('site-modal--open');
            modal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
            var first = modal.querySelector('.site-modal__panel a[href], .site-modal__panel button');
            if (first) first.focus();
        }
        function closeModal() {
            modal.classList.remove('site-modal--open');
            modal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
            openBtn.focus();
        }
        openBtn.addEventListener('click', function (e) {
            e.preventDefault();
            openModal();
        });
        closers.forEach(function (el) {
            el.addEventListener('click', closeModal);
        });
        modal.addEventListener('click', function (e) {
            if (e.target === modal) closeModal();
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && isOpen()) closeModal();
        });
    })();
    </script>
</body>
</html>