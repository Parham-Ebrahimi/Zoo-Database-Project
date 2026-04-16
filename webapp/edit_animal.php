<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit;
}
if (!in_array($_SESSION['role'], ['admin', 'caretaker', 'vet'], true)) {
    die('Access denied');
}
require_once 'db.php';

$id = (int)($_GET["id"] ?? 0);
if ($id <= 0) {
    header('Location: animals_report.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM animal WHERE Animal_ID = ?");
$stmt->execute([$id]);
$animal = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$animal) {
    header('Location: animals_report.php');
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name     = $_POST["name"];
    $species  = $_POST["species"];
    $category = $_POST["category"];
    $age      = $_POST["age"];
    $sex      = $_POST["sex"];
    $enclosure = $_POST["enclosure"];

    $stmt = $pdo->prepare("UPDATE animal SET Name=?, Species=?, Category=?, Age=?, Sex=?, Enclosure_ID=? WHERE Animal_ID=?");
    $stmt->execute([$name, $species, $category, $age ?: null, $sex, $enclosure ?: null, $id]);

    header("Location: animals_report.php");
    exit();
}

$enclosures = $pdo->query("SELECT Enclosure_ID, Enclosure_Name FROM enclosure")->fetchAll();
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

        <a href="animals_report.php" class="back-btn">← Back to Animals</a>

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
                </div>
                <button type="submit" class="submit-btn">Save Changes</button>
            </form>
        </div>
    </div>
</body>
</html>
