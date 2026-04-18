<?php
session_start();
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

/** @see animals_report.php (same logic) */
function animal_table_has_caretaker_column(PDO $pdo): bool
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    try {
        $stmt = $pdo->query("
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'animal'
              AND COLUMN_NAME = 'Caretaker_EmployeeID'
            LIMIT 1
        ");
        $cache = (bool) $stmt->fetchColumn();
    } catch (Throwable $e) {
        $cache = false;
    }
    return $cache;
}

$hasCaretakerCol = animal_table_has_caretaker_column($pdo);

$id = (int)($_GET["id"] ?? 0);
if ($id <= 0) {
    header('Location: animals_report.php');
    exit;
}

if ($hasCaretakerCol) {
    $stmt = $pdo->prepare("
        SELECT a.*, CONCAT(ck.FirstName, ' ', ck.LastName) AS CaretakerFullName
        FROM animal a
        LEFT JOIN employees ck ON a.Caretaker_EmployeeID = ck.EmployeeID
        WHERE a.Animal_ID = ?
    ");
} else {
    $stmt = $pdo->prepare("SELECT * FROM animal WHERE Animal_ID = ?");
}
$stmt->execute([$id]);
$animal = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$animal) {
    header('Location: animals_report.php');
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name      = $_POST["name"];
    $species   = $_POST["species"];
    $category  = $_POST["category"];
    $age       = $_POST["age"];
    $sex       = $_POST["sex"];
    $enclosure = $_POST["enclosure"];

    if ($hasCaretakerCol && strtolower((string) ($_SESSION['role'] ?? '')) === 'admin') {
        $caretakerRaw = $_POST['caretaker_id'] ?? '';
        $caretakerId  = ($caretakerRaw === '' || $caretakerRaw === '0') ? null : (int) $caretakerRaw;
        $stmt = $pdo->prepare("
            UPDATE animal
            SET Name=?, Species=?, Category=?, Age=?, Sex=?, Enclosure_ID=?, Caretaker_EmployeeID=?
            WHERE Animal_ID=?
        ");
        $stmt->execute([$name, $species, $category, $age ?: null, $sex, $enclosure ?: null, $caretakerId, $id]);
    } else {
        $stmt = $pdo->prepare("UPDATE animal SET Name=?, Species=?, Category=?, Age=?, Sex=?, Enclosure_ID=? WHERE Animal_ID=?");
        $stmt->execute([$name, $species, $category, $age ?: null, $sex, $enclosure ?: null, $id]);
    }

    header("Location: animals_report.php");
    exit();
}

$enclosures = $pdo->query("SELECT Enclosure_ID, Enclosure_Name FROM enclosure")->fetchAll();
$assignableCaretakers = [];
if ($hasCaretakerCol) {
    $assignableCaretakers = $pdo->query("
        SELECT EmployeeID, CONCAT(FirstName, ' ', LastName) AS FullName
        FROM employees
        WHERE LOWER(TRIM(Role)) IN ('caretaker', 'keeper')
        ORDER BY FirstName, LastName
    ")->fetchAll();
}
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
        .form-group label { font-weight: 600; margin-bottom: 4px; color: var(--text-color); font-size: 0.9rem; width: auto; height: auto; background: none; border-radius: 0; text-align: left; }
        .form-group input, .form-group select { width: 100%; padding: 9px 12px; border: 2px solid #ddd; border-radius: 8px; font: inherit; font-size: 0.95rem; box-sizing: border-box; background-color: white; height: auto; flex-grow: 0; }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: var(--accent-color); }
        form > div { width: auto; display: block; }
        .submit-btn { margin-top: 16px; padding: 10px 28px; background-color: var(--accent-color); border: none; border-radius: 1000px; font: inherit; font-weight: 600; cursor: pointer; color: var(--text-color); }
        .submit-btn:hover { background-color: var(--text-color); color: white; }
        .logout-btn { padding: 9px 22px; background-color: var(--accent-color); border: none; border-radius: 1000px; font: inherit; font-weight: 600; cursor: pointer; color: var(--text-color); text-decoration: none; }
        .logout-btn:hover { background-color: var(--text-color); color: white; }
        .back-btn { display: inline-block; margin-bottom: 15px; padding: 8px 18px; background-color: var(--base-color); border-radius: 8px; color: var(--text-color); font-weight: 600; text-decoration: none; border: 2px solid var(--accent-color); font-size: 0.9rem; }
        .back-btn:hover { background-color: var(--accent-color); }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <div class="dashboard-header">
            <h1>Edit Animal</h1>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>

        <div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:15px">
            <a href="<?= htmlspecialchars($staffHome) ?>" class="back-btn" style="margin-bottom:0">← Back to dashboard</a>
            <a href="animals_report.php" class="back-btn" style="margin-bottom:0">← Animals report</a>
        </div>

        <div class="form-card">
            <form method="POST">
                <div class="form-grid">
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
                        <input type="text" name="category" value="<?= htmlspecialchars($animal['Category']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Age</label>
                        <input type="number" name="age" value="<?= $animal['Age'] ?>" min="0">
                    </div>
                    <div class="form-group">
                        <label>Sex</label>
                        <select name="sex">
                            <option value="Male" <?= $animal['Sex'] === 'Male' ? 'selected' : '' ?>>Male</option>
                            <option value="Female" <?= $animal['Sex'] === 'Female' ? 'selected' : '' ?>>Female</option>
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
                    <?php if ($hasCaretakerCol): ?>
                    <div class="form-group" style="grid-column: 1 / -1">
                        <label>Assigned caretaker (admin only)</label>
                        <?php if (strtolower((string) ($_SESSION['role'] ?? '')) === 'admin'): ?>
                            <select name="caretaker_id">
                                <option value="">— Not assigned —</option>
                                <?php
                                $currentCid = isset($animal['Caretaker_EmployeeID']) ? (int) $animal['Caretaker_EmployeeID'] : 0;
                                foreach ($assignableCaretakers as $c):
                                ?>
                                    <option value="<?= (int) $c['EmployeeID'] ?>" <?= $currentCid === (int) $c['EmployeeID'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($c['FullName']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <p style="margin:0;padding:9px 12px;border:2px solid #eee;border-radius:8px;background:#fafafa">
                                <?= htmlspecialchars($animal['CaretakerFullName'] ?? '') !== ''
                                    ? htmlspecialchars((string) $animal['CaretakerFullName'])
                                    : 'Not assigned' ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <button type="submit" class="submit-btn">Save Changes</button>
            </form>
        </div>
    </div>
</body>
</html>
