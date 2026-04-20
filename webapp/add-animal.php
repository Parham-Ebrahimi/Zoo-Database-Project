<?php
require_once __DIR__ . '/session_bootstrap.php';

require_once __DIR__ . '/generate_animal_page.php';

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

        $pageGenerated = false;
        $pagePath      = '';
        $photoRelPath  = '';
        $photoError    = ''; 

        $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($name));
        $slug = trim($slug, '-');

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
                $photoError = 'Photo upload failed: images folder could not be created.';
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
                    $photoError = 'Photo upload failed: could not move file.';
                }
            }
        }

        $pagePath = __DIR__ . '/animals/' . $slug . '.php';
        $pi = 1;
        while (file_exists($pagePath)) {
            $pagePath = __DIR__ . '/animals/' . $slug . '-' . $pi . '.php';
            $pi++;
        }
        
        // This call is now safe because the file was required at the top
        $pageContent = generate_animal_page($name, $species, $category, $photoRelPath);
        $fileWritten = @file_put_contents($pagePath, $pageContent);

        if ($fileWritten !== false) {
            $pageGenerated = true;
        } else {
            $photoError .= ($photoError ? ' Also: ' : '') .
                'Detail page file could not be written to animals/ — Check permissions.';
        }

        try {
            if (animal_table_has_column($pdo, 'Page_Slug')) {
                $pdo->prepare("UPDATE animal SET Page_Slug=? WHERE Animal_ID=?")
                    ->execute([basename($pagePath, '.php'), $newAnimalId]);
            }
        } catch (Throwable $ignored) {}

        if (empty($error)) {
            $success = 'Animal added successfully!';
            if ($pageGenerated) {
                $success .= ' Detail page created: animals/' . basename($pagePath);
            }
            if (!empty($photoError)) {
                $error = $photoError;
            }
        }
    }
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
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 9px 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font: inherit;
            font-size: 0.95rem;
        }
        .submit-btn, .logout-btn {
            padding: 10px 28px;
            background-color: var(--accent-color);
            border: none;
            border-radius: 1000px;
            font-weight: 600;
            cursor: pointer;
            color: var(--text-color);
            text-decoration: none;
        }
        .submit-btn:hover, .logout-btn:hover { background-color: var(--text-color); color: white; }
        .back-btn {
            display: inline-block;
            padding: 8px 18px;
            background-color: var(--base-color);
            border-radius: 8px;
            color: var(--text-color);
            font-weight: 600;
            text-decoration: none;
            border: 2px solid var(--accent-color);
        }
        .msg-error { color: #e74c3c; font-weight: 600; margin-bottom: 12px; }
        .msg-success { color: #27ae60; font-weight: 600; margin-bottom: 12px; }
        .section-label {
            grid-column: 1 / -1;
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            color: #888;
            margin: 6px 0 2px;
            padding-bottom: 4px;
            border-bottom: 1px solid #eee;
        }
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
        <a href="<?= htmlspecialchars($staffHome) ?>" class="back-btn">← Back to dashboard</a>
        <a href="animals_report.php" class="back-btn">Animals report</a>
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
                    <input type="text" name="new_enclosure_name" id="new_enclosure_name">
                </div>
                <div class="form-group" id="new-climate-group" style="display:none">
                    <label>Climate Type *</label>
                    <select name="new_climate_id" id="new_climate_id">
                        <option value="">-- Select climate --</option>
                        <?php foreach ($climateTypes as $ct): ?>
                            <option value="<?= (int) $ct['ClimateType_ID'] ?>"><?= htmlspecialchars($ct['ClimateType_Name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" id="new-capacity-group" style="display:none">
                    <label>Max Capacity *</label>
                    <input type="number" name="new_max_capacity" id="new_max_capacity" min="1">
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
                    <input type="file" name="photo" accept="image/*">
                </div>
            </div>
            <button type="submit" class="submit-btn">Add Animal</button>
        </form>
    </div>
</div>
<script>
function toggleNewEnclosure(val) {
    var grps = ['new-enclosure-group', 'new-climate-group', 'new-capacity-group'];
    var inputs = ['new_enclosure_name', 'new_climate_id', 'new_max_capacity'];
    var isNew = (val === '__new__');
    
    grps.forEach(id => document.getElementById(id).style.display = isNew ? 'flex' : 'none');
    inputs.forEach(id => document.getElementById(id).required = isNew);
}
</script>
</body>
</html>
