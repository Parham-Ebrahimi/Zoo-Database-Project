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

function animal_table_has_caretaker_column(PDO $pdo): bool {
    static $cache = null;
    if ($cache !== null) return $cache;
    try {
        $stmt = $pdo->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'animal' AND COLUMN_NAME = 'Caretaker_EmployeeID' LIMIT 1");
        $cache = (bool) $stmt->fetchColumn();
    } catch (Throwable $e) { $cache = false; }
    return $cache;
}

function animal_table_has_vet_column(PDO $pdo): bool {
    static $cache = null;
    if ($cache !== null) return $cache;
    try {
        $stmt = $pdo->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'animal' AND COLUMN_NAME = 'Vet_EmployeeID' LIMIT 1");
        $cache = (bool) $stmt->fetchColumn();
    } catch (Throwable $e) { $cache = false; }
    return $cache;
}

$hasCaretakerCol = animal_table_has_caretaker_column($pdo);
$hasVetCol       = animal_table_has_vet_column($pdo);

$id = (int)($_GET["id"] ?? 0);
if ($id <= 0) { header('Location: animals_report.php'); exit; }

// Build select based on available columns
$selectExtra = '';
if ($hasCaretakerCol) $selectExtra .= ", CONCAT(ck.FirstName,' ',ck.LastName) AS CaretakerFullName";
if ($hasVetCol)       $selectExtra .= ", CONCAT(v.FirstName,' ',v.LastName) AS VetFullName";

$joinExtra = '';
if ($hasCaretakerCol) $joinExtra .= " LEFT JOIN employees ck ON a.Caretaker_EmployeeID = ck.EmployeeID";
if ($hasVetCol)       $joinExtra .= " LEFT JOIN employees v  ON a.Vet_EmployeeID = v.EmployeeID";

$stmt = $pdo->prepare("SELECT a.* $selectExtra FROM animal a $joinExtra WHERE a.Animal_ID = ?");
$stmt->execute([$id]);
$animal = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$animal) { header('Location: animals_report.php'); exit; }

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name      = $_POST["name"];
    $species   = $_POST["species"];
    $category  = $_POST["category"];
    $age       = $_POST["age"];
    $sex       = $_POST["sex"];
    $enclosure = $_POST["enclosure"];
    $isAdmin   = strtolower((string)($_SESSION['role'] ?? '')) === 'admin';

    $setCols = "Name=?, Species=?, Category=?, Age=?, Sex=?, Enclosure_ID=?";
    $params  = [$name, $species, $category, $age ?: null, $sex, $enclosure ?: null];

    if ($hasCaretakerCol && $isAdmin) {
        $raw = $_POST['caretaker_id'] ?? '';
        $params[] = ($raw === '' || $raw === '0') ? null : (int)$raw;
        $setCols .= ", Caretaker_EmployeeID=?";
    }
    if ($hasVetCol && $isAdmin) {
        $raw = $_POST['vet_id'] ?? '';
        $params[] = ($raw === '' || $raw === '0') ? null : (int)$raw;
        $setCols .= ", Vet_EmployeeID=?";
    }

    $params[] = $id;
    $pdo->prepare("UPDATE animal SET $setCols WHERE Animal_ID=?")->execute($params);
    header("Location: animals_report.php");
    exit();
}

$enclosures = $pdo->query("SELECT Enclosure_ID, Enclosure_Name FROM enclosure ORDER BY Enclosure_Name")->fetchAll();

$assignableCaretakers = [];
if ($hasCaretakerCol) {
    $assignableCaretakers = $pdo->query("
        SELECT EmployeeID, CONCAT(FirstName,' ',LastName) AS FullName
        FROM employees WHERE LOWER(TRIM(Role)) IN ('caretaker','keeper')
        ORDER BY FirstName, LastName
    ")->fetchAll();
}

$assignableVets = [];
if ($hasVetCol) {
    $assignableVets = $pdo->query("
        SELECT EmployeeID, CONCAT(FirstName,' ',LastName) AS FullName
        FROM employees WHERE LOWER(TRIM(Role)) IN ('vet','veterinarian')
        ORDER BY FirstName, LastName
    ")->fetchAll();
}

$isAdmin = strtolower((string)($_SESSION['role'] ?? '')) === 'admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Animal</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body { overflow: auto; }
        .dashboard-wrapper { box-sizing: border-box; min-height: 100vh; padding: 30px 40px; background-color: var(--base-color); }
        .dashboard-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 3px solid var(--accent-color); padding-bottom: 15px; }
        .form-card { background: white; border-radius: 15px; padding: 25px 30px; max-width: 700px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .form-group { display: flex; flex-direction: column; }
        .form-group.full { grid-column: 1 / -1; }
        .form-group label { font-weight: 600; margin-bottom: 4px; color: var(--text-color); font-size: 0.9rem; width: auto; height: auto; background: none; border-radius: 0; text-align: left; }
        .form-group input, .form-group select { width: 100%; padding: 9px 12px; border: 2px solid #ddd; border-radius: 8px; font: inherit; font-size: 0.95rem; box-sizing: border-box; background-color: white; height: auto; flex-grow: 0; }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: var(--accent-color); }
        .form-section { grid-column: 1 / -1; margin: 10px 0 2px; padding-bottom: 6px; border-bottom: 2px solid var(--base-color); }
        .form-section h3 { font-size: 0.85rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #888; margin: 0; }
        .readonly-field { margin: 0; padding: 9px 12px; border: 2px solid #eee; border-radius: 8px; background: #fafafa; font-size: 0.92rem; color: #555; }
        form > div { width: auto; display: block; }
        .submit-btn { margin-top: 16px; padding: 10px 28px; background-color: var(--accent-color); border: none; border-radius: 1000px; font: inherit; font-weight: 600; cursor: pointer; color: var(--text-color); }
        .submit-btn:hover { background-color: var(--text-color); color: white; }
        .logout-btn { padding: 9px 22px; background-color: var(--accent-color); border: none; border-radius: 1000px; font: inherit; font-weight: 600; cursor: pointer; color: var(--text-color); text-decoration: none; }
        .logout-btn:hover { background-color: var(--text-color); color: white; }
        .back-btn { display: inline-block; margin-bottom: 15px; padding: 8px 18px; background-color: var(--base-color); border-radius: 8px; color: var(--text-color); font-weight: 600; text-decoration: none; border: 2px solid var(--accent-color); font-size: 0.9rem; }
        .back-btn:hover { background-color: var(--accent-color); }
        .admin-note { font-size: 0.75rem; color: #aaa; margin-top: 2px; }
    </style>
</head>
<body>
<div class="dashboard-wrapper">
    <div class="dashboard-header">
        <h1>Edit Animal — <?= htmlspecialchars($animal['Name']) ?></h1>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>

    <div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:15px">
        <a href="<?= htmlspecialchars($staffHome) ?>" class="back-btn" style="margin-bottom:0">← Back to dashboard</a>
        <a href="animals_report.php" class="back-btn" style="margin-bottom:0">← Animals report</a>
    </div>

    <div class="form-card">
        <form method="POST">
            <div class="form-grid">

                <!-- Basic info -->
                <div class="form-section"><h3>Animal details</h3></div>

                <div class="form-group">
                    <label>Name</label>
                    <input type="text" name="name" value="<?= htmlspecialchars($animal['Name']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Species</label>
                    <input type="text" name="species" value="<?= htmlspecialchars($animal['Species']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Category</label>
                    <input type="text" name="category" value="<?= htmlspecialchars($animal['Category'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Age</label>
                    <input type="number" name="age" value="<?= $animal['Age'] ?>" min="0">
                </div>
                <div class="form-group">
                    <label>Sex</label>
                    <select name="sex">
                        <option value="Male"   <?= ($animal['Sex'] ?? '') === 'Male'   ? 'selected' : '' ?>>Male</option>
                        <option value="Female" <?= ($animal['Sex'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Enclosure</label>
                    <select name="enclosure">
                        <option value="">-- None --</option>
                        <?php foreach ($enclosures as $enc): ?>
                        <option value="<?= $enc['Enclosure_ID'] ?>" <?= $animal['Enclosure_ID'] == $enc['Enclosure_ID'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($enc['Enclosure_Name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Staff assignments -->
                <div class="form-section"><h3>Staff assignments</h3></div>

                <?php if ($hasCaretakerCol): ?>
                <div class="form-group">
                    <label>Assigned caretaker</label>
                    <?php if ($isAdmin): ?>
                        <select name="caretaker_id">
                            <option value="">— Not assigned —</option>
                            <?php
                            $currentCid = isset($animal['Caretaker_EmployeeID']) ? (int)$animal['Caretaker_EmployeeID'] : 0;
                            foreach ($assignableCaretakers as $c):
                            ?>
                            <option value="<?= (int)$c['EmployeeID'] ?>" <?= $currentCid === (int)$c['EmployeeID'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['FullName']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <p class="readonly-field"><?= htmlspecialchars($animal['CaretakerFullName'] ?? 'Not assigned') ?></p>
                        <span class="admin-note">Only admins can change caretaker assignment</span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if ($hasVetCol): ?>
                <div class="form-group">
                    <label>Assigned vet</label>
                    <?php if ($isAdmin): ?>
                        <select name="vet_id">
                            <option value="">— Not assigned —</option>
                            <?php
                            $currentVid = isset($animal['Vet_EmployeeID']) ? (int)$animal['Vet_EmployeeID'] : 0;
                            foreach ($assignableVets as $v):
                            ?>
                            <option value="<?= (int)$v['EmployeeID'] ?>" <?= $currentVid === (int)$v['EmployeeID'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($v['FullName']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <p class="readonly-field"><?= htmlspecialchars($animal['VetFullName'] ?? 'Not assigned') ?></p>
                        <span class="admin-note">Only admins can change vet assignment</span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

            </div>
            <button type="submit" class="submit-btn">Save changes</button>
        </form>
    </div>
</div>
</body>
</html>
