<?php
require_once __DIR__ . '/../session_bootstrap.php';

// Guard: must be logged in as a customer
if (!isset($_SESSION['customer_id'])) {
    if (!empty($_SESSION['user_id'])) {
        header('Location: ../dashboard.php');
        exit;
    }
    header('Location: ../login.html');
    exit;
}

require_once __DIR__ . '/../db.php';

// Read slug from query string
$slug = trim((string) ($_GET['slug'] ?? ''));
if ($slug === '') {
    header('Location: ../animals.php');
    exit;
}

// Look up the animal by slug (or fall back to matching by name-derived slug)
$animal = null;
try {
    $hasSlugCol = (bool) $pdo->query("
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'animal'
          AND COLUMN_NAME  = 'Page_Slug'
        LIMIT 1
    ")->fetchColumn();

    if ($hasSlugCol) {
        $stmt = $pdo->prepare("
            SELECT a.Name, a.Species, a.Category, a.Age, a.Sex,
                   COALESCE(a.Photo_Path, '') AS Photo_Path,
                   d.Diet_Type,
                   e.Enclosure_Name
            FROM animal a
            LEFT JOIN enclosure e ON a.Enclosure_ID = e.Enclosure_ID
            LEFT JOIN diet     d  ON a.Diet_ID       = d.Diet_ID
            WHERE a.Page_Slug = ?
            LIMIT 1
        ");
        $stmt->execute([$slug]);
        $animal = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    // Fallback: derive slug from Name and match
    if (!$animal) {
        $all = $pdo->query("
            SELECT a.Name, a.Species, a.Category, a.Age, a.Sex,
                   COALESCE(a.Photo_Path, '') AS Photo_Path,
                   d.Diet_Type,
                   e.Enclosure_Name
            FROM animal a
            LEFT JOIN enclosure e ON a.Enclosure_ID = e.Enclosure_ID
            LEFT JOIN diet     d  ON a.Diet_ID       = d.Diet_ID
        ")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($all as $row) {
            $derived = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower((string) $row['Name'])), '-');
            if ($derived === $slug) {
                $animal = $row;
                break;
            }
        }
    }
} catch (Throwable $e) {
    $animal = null;
}

if (!$animal) {
    http_response_code(404);
    echo '<!DOCTYPE html><html><body><h1>Animal not found</h1><p><a href="../animals.php">← Back to all animals</a></p></body></html>';
    exit;
}

$name        = $animal['Name'];
$species     = $animal['Species'];
$category    = $animal['Category'];
$age         = $animal['Age'];
$sex         = $animal['Sex'];
$diet        = $animal['Diet_Type'] ?? null;
$enclosure   = $animal['Enclosure_Name'] ?? null;
$photoPath   = $animal['Photo_Path'];

$isCustomer  = isset($_SESSION['customer_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($name) ?> – Greenwood Zoo</title>
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
        .coming-soon-banner {
            background: #e8f5e9;
            border-left: 4px solid #4caf50;
            border-radius: 8px;
            padding: 18px 22px;
            margin: 24px 0;
            color: #1a4a1a;
        }
        .coming-soon-banner h2 { margin: 0 0 6px; font-size: 1.15rem; }
        .coming-soon-banner p { margin: 0; font-size: 0.92rem; color: #3d5c3d; }
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
                <?php if ($isCustomer): ?>
                    <li><span>Welcome, <?= htmlspecialchars($_SESSION['firstname'] ?? '') ?></span></li>
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
        <?php if (!empty($photoPath)): ?>
            <img src="../animals/<?= htmlspecialchars($photoPath) ?>" alt="<?= htmlspecialchars($name) ?> at Greenwood Zoo">
        <?php else: ?>
            <img src="https://placehold.co/1600x420/c8e6c9/2d6a2d?text=<?= rawurlencode($name) ?>"
                 alt="<?= htmlspecialchars($name) ?> at Greenwood Zoo"
                 style="object-fit:contain;background:#c8e6c9">
        <?php endif; ?>
        <div class="animal-hero-text">
            <h1><?= htmlspecialchars($name) ?></h1>
            <p><?= htmlspecialchars($category) ?> · <em><?= htmlspecialchars($species) ?></em></p>
        </div>
    </div>

    <main class="animal-detail">
        <a class="back-link" href="../animals.php">← Back to all animals</a>

        <div class="fact-grid">
            <div class="fact-card">
                <strong><?= $age !== null ? htmlspecialchars((string)$age) . ' yr' : '—' ?></strong>
                <span>Age</span>
            </div>
            <div class="fact-card">
                <strong><?= htmlspecialchars($sex ?? '—') ?></strong>
                <span>Sex</span>
            </div>
            <div class="fact-card">
                <strong><?= htmlspecialchars($diet ?? '—') ?></strong>
                <span>Diet</span>
            </div>
            <div class="fact-card">
                <strong><?= htmlspecialchars($enclosure ?? '—') ?></strong>
                <span>Enclosure</span>
            </div>
        </div>

        <div class="coming-soon-banner">
            <h2>🌿 More info to come!</h2>
            <p>
                We're still gathering details about <?= htmlspecialchars($name) ?>.
                Check back soon for conservation info, feeding schedules, and fun facts.
            </p>
        </div>

        <?php
        $animalSpecies  = $species;
        $animalKeywords = $name;
        $animalLabel    = $name;
        require __DIR__ . '/_animal_residents.php';
        ?>
    </main>

    <footer class="site-footer">
        <p>&copy; <?= date('Y') ?> Team 9 COSC 3380 Zoo Database Systems Project.</p>
        <p><a href="../login.html">Login</a> · <a href="../signup.html">Sign up</a></p>
    </footer>
</body>
</html>