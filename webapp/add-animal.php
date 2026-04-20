<?php
require_once __DIR__ . '/session_bootstrap.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit;
}

require_once 'staff_home.php';
$roleGate = strtolower(trim((string) ($_SESSION['role'] ?? '')));
if (!in_array($roleGate, ['admin', 'caretaker', 'vet', 'keeper'], true) && !staff_is_vet_role()) {
    die('Access denied');
}

require_once 'db.php';

$staffHome = staff_home_href();

function animal_table_has_column(PDO $pdo, string $col): bool
{
    static $cache = [];
    if (isset($cache[$col])) return $cache[$col];
    try {
        $stmt = $pdo->prepare("
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'animal'
              AND COLUMN_NAME = ?
            LIMIT 1
        ");
        $stmt->execute([$col]);
        $cache[$col] = (bool) $stmt->fetchColumn();
    } catch (Throwable $e) {
        $cache[$col] = false;
    }
    return $cache[$col];
}

$hasCaretakerCol = animal_table_has_column($pdo, 'Caretaker_EmployeeID');
$hasVetCol       = animal_table_has_column($pdo, 'Vet_EmployeeID');
$hasDietCol      = animal_table_has_column($pdo, 'Diet_ID');
$isAdmin         = strtolower((string) ($_SESSION['role'] ?? '')) === 'admin';

$error   = '';
$success = '';

$categoryOptions = [
    'Amphibian', 'Arachnid', 'Bird', 'Fish', 'Insect',
    'Mammal', 'Reptile', 'Other',
];

$dietRows = [];
if ($hasDietCol) {
    try {
        $dietRows = $pdo->query("SELECT Diet_ID, Diet_Type FROM diet ORDER BY Diet_Type")->fetchAll();
    } catch (Throwable $e) {
        $hasDietCol = false;
    }
}

$enclosures = $pdo->query("SELECT Enclosure_ID, Enclosure_Name FROM enclosure")->fetchAll();

$climateTypes = [];
try {
    $climateTypes = $pdo->query("SELECT ClimateType_ID, ClimateType_Name FROM climatetype ORDER BY ClimateType_Name")->fetchAll();
} catch (Throwable $e) {
    // Try alternate column name
    try {
        $climateTypes = $pdo->query("SELECT ClimateType_ID, Climate_Name FROM climatetype ORDER BY Climate_Name")->fetchAll();
        $climateTypes = array_map(fn($r) => [
            'ClimateType_ID'   => $r['ClimateType_ID'],
            'ClimateType_Name' => $r['Climate_Name'],
        ], $climateTypes);
    } catch (Throwable $e2) {
        $climateTypes = [];
    }
}

$assignableCaretakers = [];
if ($hasCaretakerCol) {
    $assignableCaretakers = $pdo->query("
        SELECT EmployeeID, CONCAT(FirstName, ' ', LastName) AS FullName
        FROM employees
        WHERE LOWER(TRIM(Role)) IN ('caretaker', 'keeper')
        ORDER BY FirstName, LastName
    ")->fetchAll();
}

$vets = $pdo->query("
    SELECT EmployeeID, CONCAT(FirstName, ' ', LastName) AS FullName
    FROM employees
    WHERE LOWER(TRIM(Role)) IN ('vet', 'veterinarian')
    ORDER BY FirstName, LastName
")->fetchAll();

/* ══ POST handler ══ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name      = trim($_POST['name']      ?? '');
    $species   = trim($_POST['species']   ?? '');
    $category  = trim($_POST['category']  ?? '');
    $age       = $_POST['age']            ?? '';
    $sex       = $_POST['sex']            ?? '';
    $enclosure = $_POST['enclosure_id']   ?? '';
    $dietId    = $_POST['diet_id']        ?? '';
    $vetId     = $_POST['vet_id']         ?? '';

    // Handle "Add New Enclosure" option
    if ($enclosure === '__new__') {
        $newEncName    = trim($_POST['new_enclosure_name'] ?? '');
        $newClimateId  = $_POST['new_climate_id'] ?? '';
        $newMaxCap     = $_POST['new_max_capacity'] ?? '';
        if (!empty($newEncName) && $newClimateId !== '' && $newMaxCap !== '') {
            $insEnc = $pdo->prepare("INSERT INTO enclosure (Enclosure_Name, ClimateType_ID, Max_Capacity) VALUES (?, ?, ?)");
            $insEnc->execute([$newEncName, (int) $newClimateId, (int) $newMaxCap]);
            $enclosure  = (string) $pdo->lastInsertId();
            $enclosures = $pdo->query("SELECT Enclosure_ID, Enclosure_Name FROM enclosure")->fetchAll();
        } elseif (!empty($newEncName) && $newClimateId === '') {
            $error = 'Please select a Climate Type for the new enclosure.';
        } elseif (!empty($newEncName) && $newMaxCap === '') {
            $error = 'Please enter a Max Capacity for the new enclosure.';
        } else {
            $enclosure = '';
        }
    }

    $caretakerId = null;
    if ($hasCaretakerCol && $isAdmin) {
        $cr = $_POST['caretaker_id'] ?? '';
        $caretakerId = ($cr === '' || $cr === '0') ? null : (int) $cr;
    }

    if (empty($name) || empty($species) || empty($category) || empty($sex)) {
        $error = 'Please fill in all required fields.';
    } else {
        $cols = ['Name', 'Species', 'Category', 'Age', 'Sex', 'Enclosure_ID'];
        $vals = [$name, $species, $category, $age ?: null, $sex, $enclosure ?: null];

        if ($hasDietCol && $dietId !== '') {
            $cols[] = 'Diet_ID';
            $vals[] = (int) $dietId;
        }
        if ($hasCaretakerCol && $isAdmin) {
            $cols[] = 'Caretaker_EmployeeID';
            $vals[] = $caretakerId;
        }
        if ($hasVetCol && $vetId !== '' && $vetId !== '0') {
            $cols[] = 'Vet_EmployeeID';
            $vals[] = (int) $vetId;
        }

        $ph     = implode(', ', array_fill(0, count($cols), '?'));
        $colStr = implode(', ', $cols);
        $stmt   = $pdo->prepare("INSERT INTO animal ($colStr) VALUES ($ph)");
        $stmt->execute($vals);
        $newAnimalId = (int) $pdo->lastInsertId();

        /* ── Photo upload & page generation ── */
        $pageGenerated = false;
        $pagePath      = '';
        $photoRelPath  = '';
        $photoError    = ''; // separate from $error so page still generates

        $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($name));
        $slug = trim($slug, '-');

        // Handle optional photo upload
        if (!empty($_FILES['photo']['tmp_name'])) {
            $uploadDir = __DIR__ . '/animals/images/';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0755, true);
            }

            $ext     = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            if (!in_array($ext, $allowed, true)) {
                $photoError = 'Photo upload failed: unsupported file type.';
            } elseif ($_FILES['photo']['size'] > 8 * 1024 * 1024) {
                $photoError = 'Photo upload failed: file too large (max 8 MB).';
            } elseif (!is_dir($uploadDir)) {
                $photoError = 'Photo upload failed: images folder could not be created (check folder permissions).';
            } else {
                $filename = $slug . '.' . $ext;
                $destPath = $uploadDir . $filename;
                $i = 1;
                while (file_exists($destPath)) {
                    $filename = $slug . '-' . $i . '.' . $ext;
                    $destPath = $uploadDir . $filename;
                    $i++;
                }
                if (@move_uploaded_file($_FILES['photo']['tmp_name'], $destPath)) {
                    $photoRelPath = 'images/' . $filename;
                    try {
                        if (animal_table_has_column($pdo, 'Photo_Path')) {
                            $pdo->prepare("UPDATE animal SET Photo_Path=? WHERE Animal_ID=?")
                                ->execute([$photoRelPath, $newAnimalId]);
                        }
                    } catch (Throwable $ignored) {}
                } else {
                    $photoError = 'Photo upload failed: could not move file (check folder permissions on animals/images/).';
                }
            }
        }

        // Always generate the detail page regardless of photo outcome
        $pagePath = __DIR__ . '/animals/' . $slug . '.php';
        $pi = 1;
        while (file_exists($pagePath)) {
            $pagePath = __DIR__ . '/animals/' . $slug . '-' . $pi . '.php';
            $pi++;
        }
        if (@file_put_contents($pagePath, generate_animal_page($name, $species, $category, $photoRelPath)) !== false) {
            $pageGenerated = true;
            try {
                if (animal_table_has_column($pdo, 'Page_Slug')) {
                    $pdo->prepare("UPDATE animal SET Page_Slug=? WHERE Animal_ID=?")
                        ->execute([basename($pagePath, '.php'), $newAnimalId]);
                }
            } catch (Throwable $ignored) {}
        }

        if (empty($error)) {
            $success = 'Animal added successfully!';
            if ($pageGenerated) {
                $success .= ' Detail page created: animals/' . basename($pagePath);
            }
            if (!empty($photoError)) {
                // Animal and page were still created — surface photo issue as a soft warning
                $error = $photoError . ' The animal page was still created using a placeholder image.';
            }
        }
    }
}

/* ══ Page generator ══ */
function generate_animal_page(string $name, string $species, string $category, string $photoRelPath): string
{
    $eName     = addslashes($name);
    $eSpecies  = addslashes($species);
    $eCategory = addslashes($category);
    $ePhoto    = addslashes($photoRelPath);
    // If no photo was uploaded, use a neutral placeholder via placehold.co
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Animal</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body { overflow: auto; }
        .dashboard-wrapper {
            box-sizing: border-box;
            min-height: 100vh;
            padding: 30px 40px;
            background-color: var(--base-color);
        }
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 3px solid var(--accent-color);
            padding-bottom: 15px;
        }
        .form-card {
            background: white;
            border-radius: 15px;
            padding: 25px 30px;
            max-width: 700px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
        }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .form-group { display: flex; flex-direction: column; }
        .form-group.full { grid-column: 1 / -1; }
        .form-group label {
            font-weight: 600;
            margin-bottom: 4px;
            color: var(--text-color);
            font-size: 0.9rem;
            width: auto; height: auto;
            background: none;
            border-radius: 0;
            text-align: left;
        }
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 9px 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font: inherit;
            font-size: 0.95rem;
            box-sizing: border-box;
            background-color: white;
            height: auto;
            flex-grow: 0;
        }
        .form-group input[type="file"] {
            padding: 6px 10px;
            background-color: #fafafa;
        }
        .form-group input:focus,
        .form-group select:focus { outline: none; border-color: var(--accent-color); }
        form > div { width: auto; display: block; justify-content: unset; }
        .submit-btn {
            margin-top: 16px;
            padding: 10px 28px;
            background-color: var(--accent-color);
            border: none;
            border-radius: 1000px;
            font: inherit;
            font-weight: 600;
            cursor: pointer;
            color: var(--text-color);
        }
        .submit-btn:hover { background-color: var(--text-color); color: white; }
        .logout-btn {
            padding: 9px 22px;
            background-color: var(--accent-color);
            border: none;
            border-radius: 1000px;
            font: inherit;
            font-weight: 600;
            cursor: pointer;
            color: var(--text-color);
            text-decoration: none;
        }
        .logout-btn:hover { background-color: var(--text-color); color: white; }
        .back-btn {
            display: inline-block;
            margin-bottom: 15px;
            padding: 8px 18px;
            background-color: var(--base-color);
            border-radius: 8px;
            color: var(--text-color);
            font-weight: 600;
            text-decoration: none;
            border: 2px solid var(--accent-color);
            font-size: 0.9rem;
        }
        .back-btn:hover { background-color: var(--accent-color); }
        .msg-error   { color: #e74c3c; font-weight: 600; margin-bottom: 12px; }
        .msg-success { color: #27ae60; font-weight: 600; margin-bottom: 12px; }
        .section-label {
            grid-column: 1 / -1;
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #888;
            margin: 6px 0 2px;
            padding-bottom: 4px;
            border-bottom: 1px solid #eee;
        }
        .photo-hint { font-size: 0.78rem; color: #888; margin-top: 3px; }
    </style>
</head>
<body>
<div class="dashboard-wrapper">
    <div class="dashboard-header">
        <h1>Add Animal</h1>
        <div class="admin-header-actions-inline">
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </div>

    <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:12px">
        <a href="<?= htmlspecialchars($staffHome) ?>" class="back-btn" style="margin-bottom:0">← Back to dashboard</a>
        <a href="animals_report.php" class="back-btn" style="margin-bottom:0">Animals report</a>
    </div>

    <div class="form-card">
        <?php if ($error):   ?><p class="msg-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
        <?php if ($success): ?><p class="msg-success"><?= htmlspecialchars($success) ?></p><?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="form-grid">

                <div class="section-label">Basic Information</div>

                <div class="form-group">
                    <label>Name *</label>
                    <input type="text" name="name" required>
                </div>
                <div class="form-group">
                    <label>Species *</label>
                    <input type="text" name="species" required>
                </div>

                <div class="form-group">
                    <label>Category *</label>
                    <select name="category" required>
                        <option value="">-- Select category --</option>
                        <?php foreach ($categoryOptions as $cat): ?>
                            <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Age</label>
                    <input type="number" name="age" min="0">
                </div>

                <div class="form-group">
                    <label>Sex *</label>
                    <select name="sex" required>
                        <option value="">-- Select --</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Enclosure</label>
                    <select name="enclosure_id" id="enclosure_select" onchange="toggleNewEnclosure(this.value)">
                        <option value="">-- None --</option>
                        <?php foreach ($enclosures as $enc): ?>
                            <option value="<?= $enc['Enclosure_ID'] ?>"><?= htmlspecialchars($enc['Enclosure_Name']) ?></option>
                        <?php endforeach; ?>
                        <option value="__new__">＋ Add new enclosure…</option>
                    </select>
                </div>

                <div class="form-group" id="new-enclosure-group" style="display:none">
                    <label>New Enclosure Name *</label>
                    <input type="text" name="new_enclosure_name" id="new_enclosure_name" placeholder="e.g. Bat Cave Enclosure">
                </div>

                <div class="form-group" id="new-climate-group" style="display:none">
                    <label>Climate Type *</label>
                    <select name="new_climate_id" id="new_climate_id">
                        <option value="">-- Select climate --</option>
                        <?php foreach ($climateTypes as $ct): ?>
                            <option value="<?= (int) $ct['ClimateType_ID'] ?>"><?= htmlspecialchars($ct['ClimateType_Name']) ?></option>
                        <?php endforeach; ?>
                        <?php if (empty($climateTypes)): ?>
                            <option value="" disabled>No climate types found in database</option>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="form-group" id="new-capacity-group" style="display:none">
                    <label>Max Capacity *</label>
                    <input type="number" name="new_max_capacity" id="new_max_capacity" min="1" placeholder="e.g. 10">
                </div>

                <?php if ($hasDietCol && !empty($dietRows)): ?>
                <div class="section-label">Diet</div>
                <div class="form-group full">
                    <label>Diet Type</label>
                    <select name="diet_id">
                        <option value="">-- Not specified --</option>
                        <?php foreach ($dietRows as $d): ?>
                            <option value="<?= (int) $d['Diet_ID'] ?>"><?= htmlspecialchars($d['Diet_Type']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <div class="section-label">Staff Assignment</div>

                <div class="form-group full">
                    <label>Assigned Vet</label>
                    <select name="vet_id">
                        <option value="">— Not assigned —</option>
                        <?php foreach ($vets as $v): ?>
                            <option value="<?= (int) $v['EmployeeID'] ?>"><?= htmlspecialchars($v['FullName']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if ($hasCaretakerCol && $isAdmin): ?>
                <div class="form-group full">
                    <label>Assigned Caretaker</label>
                    <select name="caretaker_id">
                        <option value="">— Not assigned —</option>
                        <?php foreach ($assignableCaretakers as $c): ?>
                            <option value="<?= (int) $c['EmployeeID'] ?>"><?= htmlspecialchars($c['FullName']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <div class="section-label">Photo &amp; Page Generation</div>

                <div class="form-group full">
                    <label>Animal Photo</label>
                    <input type="file" name="photo" accept="image/jpeg,image/png,image/gif,image/webp">
                    <span class="photo-hint">
                        Optional. A detail page is always created under <code>animals/</code> — uploading a photo
                        replaces the placeholder image on that page. Supported: JPG, PNG, GIF, WEBP · Max 8 MB.
                    </span>
                </div>

            </div>
            <button type="submit" class="submit-btn">Add Animal</button>
        </form>
    </div>
</div>
<script>
function toggleNewEnclosure(val) {
    var encGrp    = document.getElementById('new-enclosure-group');
    var climGrp   = document.getElementById('new-climate-group');
    var capGrp    = document.getElementById('new-capacity-group');
    var nameInput = document.getElementById('new_enclosure_name');
    var climSel   = document.getElementById('new_climate_id');
    var capInput  = document.getElementById('new_max_capacity');
    if (val === '__new__') {
        encGrp.style.display  = 'flex';
        climGrp.style.display = 'flex';
        capGrp.style.display  = 'flex';
        nameInput.required    = true;
        climSel.required      = true;
        capInput.required     = true;
    } else {
        encGrp.style.display  = 'none';
        climGrp.style.display = 'none';
        capGrp.style.display  = 'none';
        nameInput.required    = false;
        climSel.required      = false;
        capInput.required     = false;
        nameInput.value       = '';
        climSel.value         = '';
        capInput.value        = '';
    }
}
</script>
</body>
</html>
