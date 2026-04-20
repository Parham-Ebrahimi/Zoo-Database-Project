<?php
require_once __DIR__ . '/session_bootstrap.php';
if (!isset($_SESSION['customer_id'])) {
    if (!empty($_SESSION['user_id'])) {
        header('Location: dashboard.php');
        exit;
    }
    header('Location: login.html');
    exit;
}
require_once 'db.php';

$customerID = (int) $_SESSION['customer_id'];

// Count upcoming visits
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM orders
    WHERE CustomerID = ? AND ScheduledDate >= CURDATE()
    AND OrderCategoryID BETWEEN 1 AND 4
");
$stmt->execute([$customerID]);
$upcomingVisits = $stmt->fetchColumn();

$featuredAnimals = [];
try {
    $feat = $pdo->query("
        SELECT a.Name, a.Species, a.Category, e.Enclosure_Name
        FROM animal a
        LEFT JOIN enclosure e ON a.Enclosure_ID = e.Enclosure_ID
        ORDER BY RAND()
        LIMIT 6
    ");
    $featuredAnimals = $feat->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $featuredAnimals = [];
}

// Species-specific images and page slugs for known animals
$animalImageMap = [
    // keyword (lowercase) => [image_url, slug]
    'elephant'   => ['https://images.unsplash.com/photo-1474511320723-9a56873867b5?w=800&q=80',  'elephants'],
    'giraffe'    => ['https://images.unsplash.com/photo-1547721064-da6cfb341d50?w=800&q=80',      'giraffes'],
    'penguin'    => ['https://images.unsplash.com/photo-1551986782-d0169b3f8fa7?w=800&q=80',      'penguins'],
    'red panda'  => ['https://images.unsplash.com/photo-1525382455947-f319bc5f446a?w=800&q=80',   'red-pandas'],
    'lion'       => ['https://images.unsplash.com/photo-1546182990-dffeafbe841d?w=800&q=80',      'lion'],
    'polar bear' => ['https://images.unsplash.com/photo-1589656966895-2f33e7653819?w=800&q=80',   'polar-bear'],
    'jaguar'     => ['https://images.unsplash.com/photo-1616128417743-c3a6992a65e7?w=800&q=80',   'jaguar'],
    'seal'       => ['https://images.unsplash.com/photo-1572880393162-0518ac760495?w=800&q=80',   'seal'],
    'anaconda'   => ['https://images.unsplash.com/photo-1600682322637-95c40966e79f?w=800&q=80',   'anaconda'],
    'crocodile'  => ['https://images.unsplash.com/photo-1611069648374-733e7bb73e5c?w=800&q=80',   'crocodile'],
    'caiman'     => ['https://images.unsplash.com/photo-1557868363-8d9d44d5b9e4?w=800&q=80',      'caiman'],
    'shark'      => ['https://images.unsplash.com/photo-1586115457457-b3753fe50cf1?w=800&q=80',   'shark'],
    'otter'      => ['https://images.unsplash.com/photo-1633967920376-33b2d94f091f?w=800&q=80',   'otter'],
    'macaw'      => ['https://images.unsplash.com/photo-1548767797-d8c844163c4a?w=800&q=80',      'macaw'],
    'monkey'     => ['https://images.unsplash.com/photo-1540573133985-87b6da6d54a9?w=800&q=80',   'monkey'],
    'capybara'   => ['https://images.unsplash.com/photo-1701772164869-dfb2cac483dc?w=800&q=80',   'capybara'],
    'tapir'      => ['https://images.unsplash.com/photo-1712938548647-8f92b804eb82?w=800&q=80',   'tapir'],
    'camel'      => ['https://images.unsplash.com/photo-1598113972215-96c018fb1a0b?w=800&q=80',   'camel'],
];
$animalFallbackImage = 'https://images.unsplash.com/photo-1564349683136-77e08dba1ef7?w=800&q=80';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — Greenwood Zoo</title>
    <link rel="stylesheet" href="index.css">
    <style>
        .cr-shell--dash {
            max-width: 1200px;
        }
        .welcome-banner {
            background: var(--cr-surface);
            border: 1px solid var(--cr-border);
            border-radius: var(--cr-radius);
            padding: 1.5rem 2rem;
            margin-bottom: 1.75rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .welcome-banner h1 {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0 0 0.25rem;
        }
        .welcome-banner p { margin: 0; color: var(--cr-muted); }
        .stat-pill {
            background: #eef6ea;
            color: var(--cr-accent);
            font-weight: 700;
            padding: 0.5rem 1.25rem;
            border-radius: 999px;
            font-size: 0.9rem;
            white-space: nowrap;
        }

        .dash-layout {
            display: grid;
            grid-template-columns: minmax(220px, 260px) minmax(0, 1fr);
            gap: 1.5rem;
            align-items: start;
        }
        @media (max-width: 900px) {
            .dash-layout {
                grid-template-columns: 1fr;
            }
        }

        .dash-sidebar {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .dash-card {
            background: var(--cr-surface);
            border: 1px solid var(--cr-border);
            border-radius: var(--cr-radius);
            padding: 1.5rem;
            box-shadow: var(--cr-shadow);
        }
        .dash-card h2 {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--cr-muted);
            margin: 0 0 1rem;
            font-weight: 600;
        }
        .dash-card a {
            display: block;
            padding: 0.65rem 0.9rem;
            margin-bottom: 0.5rem;
            background: #f4f7f2;
            border-radius: 8px;
            color: var(--cr-accent);
            font-weight: 600;
            font-size: 0.92rem;
            text-decoration: none;
            transition: background 150ms;
        }
        .dash-card a:last-child { margin-bottom: 0; }
        .dash-card a:hover { background: #ddefd5; text-decoration: none; }
        .dash-card a.primary {
            background: var(--cr-accent);
            color: white;
        }
        .dash-card a.primary:hover { background: #1a5c2b; }

        .dash-main {
            background: var(--cr-surface);
            border: 1px solid var(--cr-border);
            border-radius: var(--cr-radius);
            padding: 1.75rem 1.85rem 2.25rem;
            box-shadow: var(--cr-shadow);
        }
        .dash-main > h2 {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--cr-accent);
            margin: 0 0 0.5rem;
        }
        .dash-main .dash-lead {
            margin: 0 0 0.75rem;
            color: var(--cr-muted);
            font-size: 0.95rem;
            line-height: 1.55;
        }
        .dash-main .dash-lead-secondary {
            margin: 0 0 1.75rem;
            color: var(--cr-muted);
            font-size: 0.88rem;
            line-height: 1.6;
        }

        .attr-section {
            margin-bottom: 2.25rem;
        }
        .attr-section:last-child { margin-bottom: 0; }
        .attr-section h3 {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--cr-muted);
            margin: 0 0 0.35rem;
            font-weight: 600;
        }
        .attr-section .attr-sub {
            font-size: 0.86rem;
            color: var(--cr-muted);
            margin: 0 0 1.1rem;
            line-height: 1.5;
        }

        .animal-card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 1.25rem;
        }
        .animal-card {
            display: flex;
            flex-direction: column;
            background: #f8faf6;
            border: 1px solid var(--cr-border);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(26, 46, 22, 0.06);
        }
        .animal-card img {
            width: 100%;
            aspect-ratio: 4 / 3;
            object-fit: cover;
            display: block;
            background: #e2e8dc;
            transition: transform 0.25s ease;
        }
        .animal-card--link {
            text-decoration: none;
            color: inherit;
            cursor: pointer;
            transition: box-shadow 0.2s ease, transform 0.2s ease;
        }
        .animal-card--link:hover {
            box-shadow: 0 6px 24px rgba(26, 92, 43, 0.18);
            transform: translateY(-3px);
            text-decoration: none;
        }
        .animal-card--link:hover img {
            transform: scale(1.04);
        }
        .animal-card-cta {
            display: inline-block;
            margin-top: 0.6rem;
            font-size: 0.82rem;
            font-weight: 700;
            color: var(--cr-accent);
        }
        .animal-card-body {
            padding: 1rem 1.1rem 1.15rem;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        .animal-card-body strong {
            display: block;
            color: var(--cr-text);
            font-size: 1.05rem;
            margin-bottom: 0.35rem;
        }
        .animal-card-meta {
            font-size: 0.8rem;
            color: var(--cr-accent);
            font-weight: 600;
            margin-bottom: 0.65rem;
        }
        .animal-card-desc {
            font-size: 0.86rem;
            color: var(--cr-muted);
            line-height: 1.55;
            margin: 0;
            flex: 1;
        }

        .show-grid {
            display: flex;
            flex-direction: column;
            gap: 1.35rem;
        }
        .show-card {
            display: grid;
            grid-template-columns: minmax(140px, 200px) 1fr;
            gap: 1.1rem;
            align-items: start;
            padding: 1rem 1.1rem;
            background: linear-gradient(135deg, #fafcf8 0%, #f4f7f2 100%);
            border: 1px solid var(--cr-border);
            border-radius: 12px;
        }
        @media (max-width: 640px) {
            .show-card {
                grid-template-columns: 1fr;
            }
        }
        .show-card img {
            width: 100%;
            aspect-ratio: 16 / 11;
            object-fit: cover;
            border-radius: 8px;
            display: block;
        }
        .show-card h4 {
            margin: 0 0 0.4rem;
            font-size: 1rem;
            color: var(--cr-text);
        }
        .show-card .show-when {
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--cr-accent);
            margin-bottom: 0.5rem;
        }
        .show-card p {
            margin: 0;
            font-size: 0.88rem;
            color: var(--cr-muted);
            line-height: 1.55;
        }
        .show-card .show-where {
            margin-top: 0.5rem;
            font-size: 0.82rem;
            color: var(--cr-text);
        }
        .show-card .show-where strong { color: var(--cr-accent); }

        .day-tips {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        .day-tip {
            padding: 1rem 1.1rem;
            border-radius: 10px;
            border: 1px dashed var(--cr-border);
            background: #fbfcfa;
        }
        .day-tip h4 {
            margin: 0 0 0.45rem;
            font-size: 0.88rem;
            color: var(--cr-accent);
        }
        .day-tip p {
            margin: 0;
            font-size: 0.84rem;
            color: var(--cr-muted);
            line-height: 1.5;
        }

        .attr-empty {
            color: var(--cr-muted);
            font-size: 0.9rem;
            margin: 0;
        }

        .featured-more {
            margin-top: 1.35rem;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.75rem 1.25rem;
        }
        .featured-more a.more-animals {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.65rem 1.35rem;
            background: var(--cr-accent);
            color: #fff;
            font-weight: 700;
            font-size: 0.92rem;
            border-radius: 999px;
            text-decoration: none;
            border: 2px solid transparent;
            transition: background 150ms, border-color 150ms, color 150ms;
        }
        .featured-more a.more-animals:hover {
            background: #1a5c2b;
            color: #fff;
            text-decoration: none;
        }
        .featured-more a.more-animals-secondary {
            font-size: 0.88rem;
            font-weight: 600;
            color: var(--cr-accent);
            text-decoration: none;
        }
        .featured-more a.more-animals-secondary:hover {
            text-decoration: underline;
        }

        .logout-area { margin-top: 1.75rem; }
        .logout-area a {
            color: var(--cr-muted);
            font-size: 0.9rem;
            text-decoration: none;
        }
        .logout-area a:hover { color: var(--cr-accent); text-decoration: underline; }
    </style>
</head>
<body>
    <div class="profile-wrapper">
        <header class="site-header">
            <a class="logo" href="index.php">Greenwood Zoo</a>
            <?php require __DIR__ . '/customer_nav.php'; ?>
        </header>

        <div class="profile-card">
            <div class="welcome-banner">
                <div>
                    <h1>Welcome back, <?= htmlspecialchars($_SESSION['firstname']) ?>!</h1>
                    <p>Manage your visits and explore our animals.</p>
                </div>
                <?php if ($upcomingVisits > 0): ?>
                    <span class="stat-pill"><?= $upcomingVisits ?> upcoming visit<?= $upcomingVisits > 1 ? 's' : '' ?></span>
                <?php endif; ?>
            </div>

            <div class="dash-layout">
                <aside class="dash-sidebar" aria-label="Quick links">
                    <div class="dash-card">
                        <h2>Tickets</h2>
                        <a href="buy_tickets.php" class="primary">Buy tickets</a>
                        <a href="customer_tickets_report.php">Purchase history</a>
                    </div>

                    <div class="dash-card">
                        <h2>Dining</h2>
                        <a href="restaurant.php" class="primary">Restaurant</a>
                        
                    </div>

                    <div class="dash-card">
                        <h2>Gift Shop</h2>
                        <a href="giftshop.php" class="primary">Gift shop</a>
                    </div>
                </aside>

                <section class="dash-main" aria-labelledby="attractions-heading">
                    <h2 id="attractions-heading">Attractions &amp; schedule</h2>
                    <p class="dash-lead">
                        From ambassador animals to live programs, there is plenty to see in one day. Use this page to spot highlights before you arrive—then follow the map to each habitat and show entrance.
                    </p>
                    <p class="dash-lead-secondary">
                        Tip: arrive within an hour of opening for quieter viewing at popular exhibits. Last entry is 90 minutes before closing unless noted at the gate.
                    </p>

                    <div class="attr-section">
                        <h3>Featured animals</h3>
                        <p class="attr-sub">A rotating selection from our collection. Photos are representative of the experience; visit the habitat to see who is out today.</p>
                        <?php if (count($featuredAnimals) > 0): ?>
                            <div class="animal-card-grid">
                                <?php foreach ($featuredAnimals as $a):
                                    $category = trim((string) ($a['Category'] ?? ''));
                                    $enclosure = trim((string) ($a['Enclosure_Name'] ?? ''));
                                    $species   = trim((string) ($a['Species'] ?? ''));
                                    $nameLower = strtolower(trim((string) $a['Name']));
                                    $speciesLower = strtolower($species);

                                    // Match species or name against the image map
                                    $imgUrl = $animalFallbackImage;
                                    $slug   = null;
                                    foreach ($animalImageMap as $keyword => [$mapImg, $mapSlug]) {
                                        if (str_contains($speciesLower, $keyword) || str_contains($nameLower, $keyword)) {
                                            $imgUrl = $mapImg;
                                            $slug   = $mapSlug;
                                            break;
                                        }
                                    }

                                    $desc = 'Meet ' . htmlspecialchars($a['Name']) . ', one of our ';
                                    $desc .= $category !== '' ? htmlspecialchars($category) . ' ambassadors' : 'animal ambassadors';
                                    $desc .= '. This ' . htmlspecialchars($species) . ' is part of our conservation storytelling along the main loop';
                                    $desc .= $enclosure !== '' ? '—find them at ' . htmlspecialchars($enclosure) . '.' : '.';

                                    $cardTag  = $slug ? 'a' : 'article';
                                    $cardHref = $slug ? ' href="animals/' . htmlspecialchars($slug) . '.php"' : '';
                                    ?>
                                <<?= $cardTag ?> class="animal-card<?= $slug ? ' animal-card--link' : '' ?>"<?= $cardHref ?>>
                                    <img src="<?= htmlspecialchars($imgUrl) ?>" alt="<?= htmlspecialchars($a['Name']) ?> at Greenwood Zoo" width="800" height="600" loading="lazy" decoding="async">
                                    <div class="animal-card-body">
                                        <strong><?= htmlspecialchars($a['Name']) ?></strong>
                                        <span class="animal-card-meta"><?= htmlspecialchars($species) ?><?= $category !== '' ? ' · ' . htmlspecialchars($category) : '' ?></span>
                                        <p class="animal-card-desc"><?= $desc ?></p>
                                        <?php if ($slug): ?><span class="animal-card-cta">View profile →</span><?php endif; ?>
                                    </div>
                                </<?= $cardTag ?>>
                                <?php endforeach; ?>
                            </div>
                            <div class="featured-more">
                                <a class="more-animals" href="animals.php">See more animals</a>
                            </div>
                        <?php else: ?>
                            <p class="attr-empty">Animal highlights will appear here when connected to the collection.
                                <a href="animals.php">See species highlights</a>
                        <?php endif; ?>
                    </div>

                    <div class="attr-section">
                        <h3>Shows &amp; keeper talks</h3>
                        <p class="attr-sub">Narrated programs run rain or shine unless weather warnings apply. Strollers welcome at outdoor venues; seating is first-come at the amphitheater.</p>
                        <div class="show-grid">
                            <article class="show-card">
                                <img src="https://images.unsplash.com/photo-1505142468610-359e7d316be0?w=600&q=80" alt="Outdoor amphitheater setting" loading="lazy" decoding="async">
                                <div>
                                    <h4>Wildlife Discovery Show</h4>
                                    <div class="show-when">Sat &amp; Sun · 11:00 a.m. &amp; 2:00 p.m.</div>
                                    <p>Our naturalists introduce several species in one action-packed program—flight, foraging, and training demos that show how we care for each animal behind the scenes.</p>
                                    <p class="show-where"><strong>Location:</strong> Amphitheater (north meadow)</p>
                                </div>
                            </article>
                           
                            <article class="show-card">
                                <img src="https://images.unsplash.com/photo-1561731216-c3a4d99437d5?w=600&q=80" alt="Big cat resting" loading="lazy" decoding="async">
                                <div>
                                    <h4>Big cats talk</h4>
                                    <div class="show-when">Mon–Fri · 10:30 a.m. · Weekends · 3:00 p.m.</div>
                                    <p>A focused talk at the viewing glass: enrichment, feeding strategy, and how our team supports species survival partnerships around the world.</p>
                                    <p class="show-where"><strong>Location:</strong> Feline overlook</p>
                                </div>
                            </article>
                            <article class="show-card">
                                <img src="https://images.unsplash.com/photo-1503454537195-1dcabb73ffb9?w=600&q=80" alt="Child-friendly animal encounter" loading="lazy" decoding="async">
                                <div>
                                    <h4>Children’s story &amp; meet a small animal</h4>
                                    <div class="show-when">Daily · 10:00 a.m. &amp; 4:00 p.m.</div>
                                    <p>Short storytime followed by a calm meet-and-greet with a rabbit, reptile, or small mammal—perfect for younger guests and first-time zoo visitors.</p>
                                    <p class="show-where"><strong>Location:</strong> Discovery barn</p>
                                </div>
                            </article>
                        </div>
                    </div>

                    <div class="attr-section">
                        <h3>Make the most of your visit</h3>
                        <p class="attr-sub">Quick ideas to stretch your ticket and avoid crowds.</p>
                        <div class="day-tips">
                            <div class="day-tip">
                                <h4>Trails &amp; pacing</h4>
                                <p>The outer loop is about 1.2 miles. Allow two to three hours if you plan to catch talks and grab a snack.</p>
                            </div>
                            <div class="day-tip">
                                <h4>Food &amp; shade</h4>
                                <p>The Grove Café opens at 10:00 a.m.; picnic tables sit beside the lake path if you bring your own water bottle.</p>
                            </div>
                            <div class="day-tip">
                                <h4>Quiet windows</h4>
                                <p>Weekday mornings after opening are calmest at popular habitats. School groups peak on spring weekdays after 11:00 a.m.</p>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
       </div>
    </div>
</body>
</html>