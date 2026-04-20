<?php
/**
 * Shared helper — generates the HTML content for a new auto-created animal detail page.
 * Included by both add-animal.php and animals.php (for self-healing missing files).
 */
if (!function_exists('generate_animal_page')):
function generate_animal_page(string $name, string $species, string $category, string $photoRelPath): string
{
    $eName     = addslashes($name);
    $eSpecies  = addslashes($species);
    $eCategory = addslashes($category);
    $ePhoto    = addslashes($photoRelPath);
    $heroImgTag = $ePhoto !== ''
        ? "<img src=\"{$ePhoto}\" alt=\"{$eName} at Greenwood Zoo\">"
        : "<img src=\"https://placehold.co/1200x420/c8e6c9/2d6a2d?text=" . rawurlencode($name) . "\" alt=\"{$eName} at Greenwood Zoo\" style=\"object-fit:contain;background:#c8e6c9\">";

    return <<<TEMPLATE
<?php require_once __DIR__ . '/../session_bootstrap.php';
require_once __DIR__ . '/../db.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$eName} – Greenwood Zoo</title>
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
        .placeholder-notice { background: #fff8e1; border-left: 4px solid #f9a825; padding: 12px 16px; border-radius: 6px; color: #5d4037; font-size: 0.92rem; margin: 16px 0; }
    </style>
</head>
<body>
    <header class="site-header">
        <a class="logo" href="../index.php">Greenwood Zoo</a>
        <nav aria-label="Main">
            <ul class="nav-links">
                <?php if (isset(\$_SESSION['customer_id'])): ?>
                    <li><span>Welcome, <?= \$_SESSION['firstname'] ?></span></li>
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
        {$heroImgTag}
        <div class="animal-hero-text">
            <h1>{$eName}</h1>
            <p>{$eCategory} · <em>{$eSpecies}</em></p>
        </div>
    </div>

    <main class="animal-detail">
        <a class="back-link" href="../animals.php">← Back to all animals</a>

        <div class="placeholder-notice">
            ℹ️ <strong>More details to be included.</strong>
            This page was auto-generated when the animal was added.
            An admin can update the description, facts, and conservation info here.
        </div>

        <h2>About our {$eName}</h2>
        <p>
            Meet <strong>{$eName}</strong>, a <strong>{$eSpecies}</strong> — a member of the
            <strong>{$eCategory}</strong> family at Greenwood Zoo.
            Full care information and fun facts are coming soon!
        </p>

        <div class="fact-grid">
            <?php
            try {
                \$stmt = \$pdo->prepare("
                    SELECT a.Age, a.Sex, d.Diet_Type, e.Enclosure_Name
                    FROM animal a
                    LEFT JOIN enclosure e ON a.Enclosure_ID = e.Enclosure_ID
                    LEFT JOIN diet d      ON a.Diet_ID = d.Diet_ID
                    WHERE LOWER(TRIM(a.Name)) = LOWER(TRIM(?))
                      AND LOWER(TRIM(a.Species)) = LOWER(TRIM(?))
                    LIMIT 1
                ");
                \$stmt->execute(['{$eName}', '{$eSpecies}']);
                \$row = \$stmt->fetch(PDO::FETCH_ASSOC);
            } catch (Throwable \$ex) { \$row = null; }
            ?>
            <div class="fact-card">
                <strong><?= \$row && \$row['Age'] !== null ? htmlspecialchars((string)\$row['Age']).' yr' : '—' ?></strong>
                <span>Age</span>
            </div>
            <div class="fact-card">
                <strong><?= \$row ? htmlspecialchars(\$row['Sex'] ?? '—') : '—' ?></strong>
                <span>Sex</span>
            </div>
            <div class="fact-card">
                <strong><?= \$row ? htmlspecialchars(\$row['Diet_Type'] ?? '—') : '—' ?></strong>
                <span>Diet</span>
            </div>
            <div class="fact-card">
                <strong><?= \$row ? htmlspecialchars(\$row['Enclosure_Name'] ?? '—') : '—' ?></strong>
                <span>Enclosure</span>
            </div>
        </div>

        <?php
\$animalSpecies  = '{$eSpecies}';
\$animalKeywords = '{$eName}';
\$animalLabel    = '{$eName}';
require __DIR__ . '/_animal_residents.php';
?>

        <h2>Conservation</h2>
        <p><em>More details to be included.</em> Information about this animal's conservation status,
        native habitat, and any programs Greenwood Zoo supports will be added here.</p>

        <h2>Feeding &amp; Care</h2>
        <p><em>More details to be included.</em> Diet information, enrichment activities, and daily
        care routines for {$eName} will be added here.</p>
    </main>

    <footer class="site-footer">
        <p>&copy; <?= date('Y') ?> Team 9 COSC 3380 Zoo Database Systems Project.</p>
        <p><a href="../login.html">Login</a> · <a href="../signup.html">Sign up</a></p>
    </footer>
</body>
</html>
TEMPLATE;
}
endif;