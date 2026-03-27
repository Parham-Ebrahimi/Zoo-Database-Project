<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit;
}
require_once 'db.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name       = trim($_POST['name']);
    $species    = trim($_POST['species']);
    $category   = trim($_POST['category']);
    $age        = $_POST['age'];
    $sex        = $_POST['sex'];
    $enclosure  = $_POST['enclosure_id'];

    if (empty($name) || empty($species) || empty($category) || empty($sex)) {
        $error = 'Please fill in all required fields.';
    } else {
        $stmt = $pdo->prepare("INSERT INTO animal (Name, Species, Category, Age, Sex, Enclosure_ID) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $species, $category, $age ?: null, $sex, $enclosure ?: null]);
        $success = 'Animal added successfully!';
    }
}

$enclosures = $pdo->query("SELECT Enclosure_ID, Enclosure_Name FROM enclosure")->fetchAll();
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
        .dashboard-wrapper { box-sizing: border-box; min-height: 100vh; padding: 30px 40px; background-color: var(--base-color); }
        .dashboard-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 3px solid var(--accent-color); padding-bottom: 15px; }
        .form-card { background: white; border-radius: 15px; padding: 25px 30px; max-width: 700px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .form-group { display: flex; flex-direction: column; }
        .form-group.full { grid-column: 1 / -1; }
        .form-group label { font-weight: 600; margin-bottom: 4px; color: var(--text-color); font-size: 0.9rem; width: auto; height: auto; background: none; border-radius: 0; text-align: left; }
        .form-group input, .form-group select { width: 100%; padding: 9px 12px; border: 2px solid #ddd; border-radius: 8px; font: inherit; font-size: 0.95rem; box-sizing: border-box; background-color: white; height: auto; flex-grow: 0; border-radius: 8px; }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: var(--accent-color); }
        form > div { width: auto; display: block; justify-content: unset; }
        .submit-btn { margin-top: 16px; padding: 10px 28px; background-color: var(--accent-color); border: none; border-radius: 1000px; font: inherit; font-weight: 600; cursor: pointer; color: var(--text-color); }
        .submit-btn:hover { background-color: var(--text-color); color: white; }
        .logout-btn { padding: 9px 22px; background-color: var(--accent-color); border: none; border-radius: 1000px; font: inherit; font-weight: 600; cursor: pointer; color: var(--text-color); text-decoration: none; }
        .logout-btn:hover { background-color: var(--text-color); color: white; }
        .back-btn { display: inline-block; margin-bottom: 15px; padding: 8px 18px; background-color: var(--base-color); border-radius: 8px; color: var(--text-color); font-weight: 600; text-decoration: none; border: 2px solid var(--accent-color); font-size: 0.9rem; }
        .back-btn:hover { background-color: var(--accent-color); }
        .msg-error { color: #e74c3c; font-weight: 600; margin-bottom: 12px; }
        .msg-success { color: #27ae60; font-weight: 600; margin-bottom: 12px; }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <div class="dashboard-header">
            <h1>Add Animal</h1>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>

        <a href="admin-dashboard.php" class="back-btn">← Back to Dashboard</a>

        <div class="form-card">
            <?php if ($error): ?><p class="msg-error"><?= $error ?></p><?php endif; ?>
            <?php if ($success): ?><p class="msg-success"><?= $success ?></p><?php endif; ?>

            <form method="POST">
                <div class="form-grid">
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
                        <input type="text" name="category" placeholder="e.g. Mammal, Bird, Reptile" required>
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
                        <select name="enclosure_id">
                            <option value="">-- None --</option>
                            <?php foreach ($enclosures as $enc): ?>
                                <option value="<?= $enc['Enclosure_ID'] ?>"><?= htmlspecialchars($enc['Enclosure_Name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <button type="submit" class="submit-btn">Add Animal</button>
            </form>
        </div>
    </div>
</body>
</html>
