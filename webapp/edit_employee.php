<?php
require_once __DIR__ . '/session_bootstrap.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit;
}
if (!in_array(strtolower(trim((string) ($_SESSION['role'] ?? ''))), ['admin'], true)) {
    die('Access denied');
}
require_once 'db.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: employees_report.php'); exit; }

$stmt = $pdo->prepare('SELECT * FROM employees WHERE EmployeeID = ?');
$stmt->execute([$id]);
$emp = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$emp) { header('Location: employees_report.php'); exit; }

$empRole = strtolower(trim($emp['Role'] ?? ''));
$isCaretaker = in_array($empRole, ['caretaker', 'keeper']);
$isVet       = in_array($empRole, ['vet', 'veterinarian']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first  = trim($_POST['firstname']  ?? '');
    $last   = trim($_POST['lastname']   ?? '');
    $role   = trim($_POST['role']       ?? '');
    $dept   = trim($_POST['department'] ?? '');
    $salary = $_POST['salary'] ?? '';
    $status = $_POST['status'] ?? 'Active';

    $deptMap = [
        'admin' => 'Administration', 'caretaker' => 'Animal Care',
        'keeper' => 'Animal Care', 'vet' => 'Veterinary',
        'veterinarian' => 'Veterinary', 'cashier' => 'Retail', 'shop' => 'Retail',
    ];
    if (empty($dept)) $dept = $deptMap[strtolower($role)] ?? $dept;

    if ($first && $last && $role) {
        $params = [$first, $last, $role, $dept ?: null, $status, $id];
        $salSql = '';
        if ($salary !== '' && (float)$salary > 0) {
            $salSql = ', Salary = ?';
            array_splice($params, 5, 0, [(float)$salary]);
        }
        $pdo->prepare("UPDATE employees SET FirstName=?, LastName=?, Role=?, Department=?, Status=? $salSql WHERE EmployeeID=?")
            ->execute($params);
    }

    // Update animal assignments for caretakers
    $newRole = strtolower($role);
    if (in_array($newRole, ['caretaker', 'keeper']) && isset($_POST['assigned_animals_caretaker'])) {
        // First unassign all animals from this caretaker
        $pdo->prepare("UPDATE animal SET Caretaker_EmployeeID = NULL WHERE Caretaker_EmployeeID = ?")
            ->execute([$id]);
        // Then assign selected ones
        $selected = array_filter(array_map('intval', $_POST['assigned_animals_caretaker']));
        if (!empty($selected)) {
            $placeholders = implode(',', array_fill(0, count($selected), '?'));
            $params2 = array_merge($selected, [$id]);
            $pdo->prepare("UPDATE animal SET Caretaker_EmployeeID = ? WHERE Animal_ID IN ($placeholders)")
                ->execute(array_merge([$id], $selected));
        }
    }

    // Update animal assignments for vets
    if (in_array($newRole, ['vet', 'veterinarian']) && isset($_POST['assigned_animals_vet'])) {
        // First unassign all animals from this vet
        $pdo->prepare("UPDATE animal SET Vet_EmployeeID = NULL WHERE Vet_EmployeeID = ?")
            ->execute([$id]);
        // Then assign selected ones
        $selected = array_filter(array_map('intval', $_POST['assigned_animals_vet']));
        if (!empty($selected)) {
            $pdo->prepare("UPDATE animal SET Vet_EmployeeID = ? WHERE Animal_ID IN (" . implode(',', array_fill(0, count($selected), '?')) . ")")
                ->execute(array_merge([$id], $selected));
        }
    }

    header('Location: employees_report.php');
    exit;
}

// Get all animals with their current assignments
$allAnimals = $pdo->query("
    SELECT a.Animal_ID, a.Name, a.Species, a.Category, e.Enclosure_Name,
           a.Caretaker_EmployeeID, a.Vet_EmployeeID
    FROM animal a
    LEFT JOIN enclosure e ON a.Enclosure_ID = e.Enclosure_ID
    ORDER BY e.Enclosure_Name, a.Name
")->fetchAll(PDO::FETCH_ASSOC);

// Currently assigned animals
$assignedAsCaretaker = array_column(
    array_filter($allAnimals, fn($a) => (int)$a['Caretaker_EmployeeID'] === $id),
    'Animal_ID'
);
$assignedAsVet = array_column(
    array_filter($allAnimals, fn($a) => (int)$a['Vet_EmployeeID'] === $id),
    'Animal_ID'
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Employee</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body { overflow: auto; }
        .dashboard-wrapper { box-sizing:border-box; min-height:100vh; padding:30px 40px; background-color:var(--base-color); }
        .dashboard-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:3px solid var(--accent-color); padding-bottom:15px; }
        .form-card { background:white; border-radius:15px; padding:25px 30px; max-width:780px; box-shadow:0 4px 10px rgba(0,0,0,0.05); }
        .form-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
        .form-group { display:flex; flex-direction:column; gap:4px; }
        .form-group.full { grid-column:1/-1; }
        .form-group label { font-weight:600; font-size:0.88rem; color:var(--text-color); text-align:left; width:auto; height:auto; background:none; border-radius:0; }
        .form-group input, .form-group select {
            width:100%; padding:9px 12px; border:2px solid #ddd; border-radius:8px;
            font:inherit; font-size:0.92rem; box-sizing:border-box; background:white; height:auto;
        }
        .form-group input:focus, .form-group select:focus { outline:none; border-color:var(--accent-color); }
        form > div { width:auto; display:block; }
        .form-section { grid-column:1/-1; margin:10px 0 2px; padding-bottom:6px; border-bottom:2px solid var(--base-color); }
        .form-section h3 { font-size:0.85rem; font-weight:700; text-transform:uppercase; letter-spacing:0.05em; color:#888; margin:0; }

        /* Animal checklist */
        .animal-checklist { display:grid; grid-template-columns:repeat(auto-fill, minmax(220px,1fr)); gap:8px; max-height:320px; overflow-y:auto; padding:10px; border:2px solid #ddd; border-radius:8px; background:white; }
        .animal-check-item { display:flex; align-items:center; gap:8px; padding:7px 10px; border-radius:6px; background:#f8faf5; border:1px solid #e8f0e0; cursor:pointer; transition:background .12s; }
        .animal-check-item:hover { background:#eef5e8; }
        .animal-check-item input[type="checkbox"] { width:16px; height:16px; accent-color:var(--accent-color); flex-shrink:0; cursor:pointer; }
        .animal-check-item label { cursor:pointer; font-size:0.85rem; font-weight:600; color:var(--text-color); margin:0; background:none; height:auto; width:auto; border-radius:0; line-height:1.3; }
        .animal-check-item .animal-meta { font-size:0.73rem; color:#888; display:block; }
        .checklist-actions { display:flex; gap:8px; margin-bottom:8px; }
        .checklist-btn { padding:4px 12px; border:1px solid var(--accent-color); border-radius:6px; background:white; font:inherit; font-size:0.78rem; font-weight:600; cursor:pointer; color:var(--text-color); }
        .checklist-btn:hover { background:var(--accent-color); }
        .assigned-count { font-size:0.8rem; color:#888; font-weight:600; margin-top:4px; }

        .submit-btn { margin-top:18px; padding:11px 32px; background:var(--accent-color); border:none; border-radius:1000px; font:inherit; font-weight:600; cursor:pointer; color:var(--text-color); font-size:1rem; }
        .submit-btn:hover { background:var(--text-color); color:white; }
        .logout-btn { padding:9px 22px; background:var(--accent-color); border:none; border-radius:1000px; font:inherit; font-weight:600; cursor:pointer; color:var(--text-color); text-decoration:none; }
        .logout-btn:hover { background:var(--text-color); color:white; }
        .back-btn { display:inline-block; margin-bottom:15px; padding:8px 18px; background:var(--base-color); border-radius:8px; color:var(--text-color); font-weight:600; text-decoration:none; border:2px solid var(--accent-color); font-size:0.9rem; }
        .back-btn:hover { background:var(--accent-color); }
        .hint-text { font-size:0.74rem; color:#aaa; margin-top:1px; }
        .role-note { font-size:0.78rem; color:#888; margin-top:4px; font-style:italic; }
    </style>
</head>
<body>
<div class="dashboard-wrapper">
    <div class="dashboard-header">
        <h1>Edit Employee — <?= htmlspecialchars(($emp['FirstName'] ?? '').' '.($emp['LastName'] ?? '')) ?></h1>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>

    <a href="employees_report.php" class="back-btn">← Back to Employees</a>

    <div class="form-card">
        <form method="POST" id="editForm">
            <div class="form-grid">

                <div class="form-section"><h3>Personal & employment details</h3></div>

                <div class="form-group">
                    <label>First name</label>
                    <input type="text" name="firstname" value="<?= htmlspecialchars($emp['FirstName'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>Last name</label>
                    <input type="text" name="lastname" value="<?= htmlspecialchars($emp['LastName'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>Role</label>
                    <select name="role" id="roleSelect" onchange="syncDept(); updateAnimalSection()">
                        <option value="Admin"      <?= ($emp['Role']??'')==='Admin'     ?'selected':'' ?>>Admin</option>
                        <option value="Caretaker"  <?= strtolower($emp['Role']??'')==='caretaker'?'selected':'' ?>>Caretaker</option>
                        <option value="Vet"        <?= strtolower($emp['Role']??'')==='vet'?'selected':'' ?>>Vet</option>
                        <option value="Cashier"    <?= ($emp['Role']??'')==='Cashier'   ?'selected':'' ?>>Cashier</option>
                        <option value="Shop"       <?= ($emp['Role']??'')==='Shop'      ?'selected':'' ?>>Shop</option>
                    </select>
                    <span class="hint-text">Changing role auto-updates department</span>
                </div>
                <div class="form-group">
                    <label>Department</label>
                    <select name="department" id="deptSelect">
                        <option value="">— Auto from role —</option>
                        <option value="Administration" <?= ($emp['Department']??'')==='Administration'?'selected':'' ?>>Administration</option>
                        <option value="Animal Care"    <?= ($emp['Department']??'')==='Animal Care'   ?'selected':'' ?>>Animal Care</option>
                        <option value="Veterinary"     <?= ($emp['Department']??'')==='Veterinary'    ?'selected':'' ?>>Veterinary</option>
                        <option value="Retail"         <?= ($emp['Department']??'')==='Retail'        ?'selected':'' ?>>Retail</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Salary ($/year)</label>
                    <input type="number" name="salary" step="0.01" min="1" value="<?= htmlspecialchars($emp['Salary'] ?? '') ?>" placeholder="e.g. 40000">
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="Active"   <?= ($emp['Status']??'Active')==='Active'  ?'selected':'' ?>>Active</option>
                        <option value="Inactive" <?= ($emp['Status']??'')==='Inactive'?'selected':'' ?>>Inactive</option>
                    </select>
                </div>

                <!-- Caretaker animal assignments -->
                <div class="form-section" id="caretaker-section" style="<?= $isCaretaker ? '' : 'display:none' ?>">
                    <h3>Assigned animals (caretaker)</h3>
                </div>
                <div class="form-group full" id="caretaker-animals" style="<?= $isCaretaker ? '' : 'display:none' ?>">
                    <div class="checklist-actions">
                        <button type="button" class="checklist-btn" onclick="toggleAll('caretaker', true)">Select all</button>
                        <button type="button" class="checklist-btn" onclick="toggleAll('caretaker', false)">Deselect all</button>
                    </div>
                    <div class="animal-checklist" id="caretaker-checklist">
                        <?php foreach ($allAnimals as $a): ?>
                        <label class="animal-check-item">
                            <input type="checkbox" name="assigned_animals_caretaker[]"
                                   value="<?= (int)$a['Animal_ID'] ?>"
                                   <?= in_array((int)$a['Animal_ID'], $assignedAsCaretaker) ? 'checked' : '' ?>>
                            <span>
                                <span style="font-weight:600"><?= htmlspecialchars($a['Name']) ?></span>
                                <span class="animal-meta"><?= htmlspecialchars($a['Species']) ?> · <?= htmlspecialchars($a['Enclosure_Name'] ?? 'No enclosure') ?></span>
                            </span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <p class="assigned-count" id="caretaker-count"><?= count($assignedAsCaretaker) ?> animal(s) currently assigned</p>
                </div>

                <!-- Vet animal assignments -->
                <div class="form-section" id="vet-section" style="<?= $isVet ? '' : 'display:none' ?>">
                    <h3>Assigned animals (vet)</h3>
                </div>
                <div class="form-group full" id="vet-animals" style="<?= $isVet ? '' : 'display:none' ?>">
                    <div class="checklist-actions">
                        <button type="button" class="checklist-btn" onclick="toggleAll('vet', true)">Select all</button>
                        <button type="button" class="checklist-btn" onclick="toggleAll('vet', false)">Deselect all</button>
                    </div>
                    <div class="animal-checklist" id="vet-checklist">
                        <?php foreach ($allAnimals as $a): ?>
                        <label class="animal-check-item">
                            <input type="checkbox" name="assigned_animals_vet[]"
                                   value="<?= (int)$a['Animal_ID'] ?>"
                                   <?= in_array((int)$a['Animal_ID'], $assignedAsVet) ? 'checked' : '' ?>>
                            <span>
                                <span style="font-weight:600"><?= htmlspecialchars($a['Name']) ?></span>
                                <span class="animal-meta"><?= htmlspecialchars($a['Species']) ?> · <?= htmlspecialchars($a['Enclosure_Name'] ?? 'No enclosure') ?></span>
                            </span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <p class="assigned-count" id="vet-count"><?= count($assignedAsVet) ?> animal(s) currently assigned</p>
                </div>

            </div>
            <button type="submit" class="submit-btn">Save changes</button>
        </form>
    </div>
</div>

<script>
const roleMap = {
    'Admin':'Administration','Caretaker':'Animal Care','Keeper':'Animal Care',
    'Vet':'Veterinary','Cashier':'Retail','Shop':'Retail'
};
function syncDept() {
    const role = document.getElementById('roleSelect').value;
    const dept = document.getElementById('deptSelect');
    if (roleMap[role]) {
        for (let i = 0; i < dept.options.length; i++) {
            if (dept.options[i].value === roleMap[role]) { dept.selectedIndex = i; break; }
        }
    }
}
function updateAnimalSection() {
    const role = document.getElementById('roleSelect').value.toLowerCase();
    const isCaretaker = role === 'caretaker' || role === 'keeper';
    const isVet = role === 'vet' || role === 'veterinarian';
    document.getElementById('caretaker-section').style.display = isCaretaker ? '' : 'none';
    document.getElementById('caretaker-animals').style.display = isCaretaker ? '' : 'none';
    document.getElementById('vet-section').style.display = isVet ? '' : 'none';
    document.getElementById('vet-animals').style.display = isVet ? '' : 'none';
}
function toggleAll(type, checked) {
    document.querySelectorAll(`#${type}-checklist input[type="checkbox"]`)
        .forEach(cb => cb.checked = checked);
    updateCount(type);
}
function updateCount(type) {
    const checked = document.querySelectorAll(`#${type}-checklist input:checked`).length;
    document.getElementById(`${type}-count`).textContent = checked + ' animal(s) selected';
}
// Live count update
document.querySelectorAll('#caretaker-checklist input, #vet-checklist input').forEach(cb => {
    cb.addEventListener('change', () => {
        const type = cb.closest('.animal-checklist').id.replace('-checklist', '');
        updateCount(type);
    });
});
</script>
</body>
</html>
