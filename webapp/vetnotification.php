<?php
require_once __DIR__ . '/session_bootstrap.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit;
}
require_once 'staff_home.php';

$roleRaw = strtolower(trim((string) ($_SESSION['role'] ?? '')));
$isAdmin = ($roleRaw === 'admin');
if (!$isAdmin && !staff_is_vet_role()) {
    header('Location: dashboard.php');
    exit;
}

require_once 'db.php';

$empStmt = $pdo->prepare("SELECT EmployeeID FROM systemuser WHERE UserID = ?");
$empStmt->execute([(int)$_SESSION['user_id']]);
$empRow = $empStmt->fetch();
$empID  = $empRow ? (int)$empRow['EmployeeID'] : 0;
if ($empID === 0) {
    $fb = $pdo->prepare("SELECT e.EmployeeID FROM employees e JOIN systemuser s ON s.EmployeeID = e.EmployeeID WHERE s.UserID = ?");
    $fb->execute([(int)$_SESSION['user_id']]);
    $row = $fb->fetch();
    $empID = $row ? (int)$row['EmployeeID'] : 1;
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: vet_dashboard.php');
    exit;
}

// Load animal with latest health record
$stmt = $pdo->prepare("
    SELECT a.Animal_ID, a.Name, a.Species, a.Category, a.Age, a.Sex,
           COALESCE(a.Health_Status, 'Pending') AS Health_Status,
           e.Enclosure_Name,
           hr.HealthRecord_ID,
           hr.Diagnosis,
           hr.Treatment,
           hr.Record_Date,
           hr.Notes,
           hr.Cured_Date
    FROM animal a
    LEFT JOIN enclosure e ON a.Enclosure_ID = e.Enclosure_ID
    LEFT JOIN (
        SELECT hr1.*
        FROM health_record hr1
        INNER JOIN (
            SELECT Animal_ID, MAX(Record_Date) AS MaxDate
            FROM health_record
            GROUP BY Animal_ID
        ) latest ON hr1.Animal_ID = latest.Animal_ID AND hr1.Record_Date = latest.MaxDate
    ) hr ON hr.Animal_ID = a.Animal_ID
    WHERE a.Animal_ID = ?
");
$stmt->execute([$id]);
$animal = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$animal) {
    header('Location: vet_dashboard.php');
    exit;
}

$successMsg = '';
$errorMsg   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newName       = trim($_POST['name']      ?? '');
    $newSpecies    = trim($_POST['species']   ?? '');
    $newCategory   = trim($_POST['category'] ?? '');
    $newAge        = $_POST['age'] !== '' ? (int)$_POST['age'] : null;
    $newSex        = $_POST['sex'] ?? $animal['Sex'];
    $newEnclosure  = (int)($_POST['enclosure'] ?? 0);
    $newDiagnosis  = trim($_POST['diagnosis'] ?? '');
    $newTreatment  = trim($_POST['treatment'] ?? '');
    $newStatus     = $_POST['vet_status'] ?? 'None';
    $today         = date('Y-m-d');

    $healthStatusMap = [
        'None'                 => 'Pending',
        'Undergoing Treatment' => 'Sick',
        'Healthy'              => 'Healthy',
    ];
    $animalHealthStatus = $healthStatusMap[$newStatus] ?? 'Pending';

    if ($animalHealthStatus === 'Healthy' && stripos($newDiagnosis, 'cured') === false) {
        $errorMsg = 'The animal cannot be marked Healthy until the diagnosis states it is cured.';
    } else {
        try {
            $pdo->beginTransaction();

            $enclosureVal = $newEnclosure > 0 ? $newEnclosure : null;
            $pdo->prepare("
                UPDATE animal
                SET Name=?, Species=?, Category=?, Age=?, Sex=?, Enclosure_ID=?, Health_Status=?
                WHERE Animal_ID=?
            ")->execute([$newName, $newSpecies, $newCategory, $newAge, $newSex,
                         $enclosureVal, $animalHealthStatus, $id]);

            $curedDate = ($animalHealthStatus === 'Healthy') ? $today : null;

            if (!empty($animal['HealthRecord_ID'])) {
                $pdo->prepare("
                    UPDATE health_record
                    SET Diagnosis=?, Treatment=?, Record_Date=?, Health_Status=?, Cured_Date=?
                    WHERE HealthRecord_ID=?
                ")->execute([
                    $newDiagnosis ?: null,
                    $newTreatment ?: null,
                    $today,
                    $animalHealthStatus,
                    $curedDate,
                    (int)$animal['HealthRecord_ID']
                ]);
            } else {
                try {
                    $pdo->prepare("
                        INSERT INTO health_record
                            (Animal_ID, Veterinarian_ID, Diagnosis, Treatment, Record_Date, Health_Status, Cured_Date)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ")->execute([
                        $id, $empID,
                        $newDiagnosis ?: null,
                        $newTreatment ?: null,
                        $today,
                        $animalHealthStatus,
                        $curedDate
                    ]);
                } catch (PDOException $colErr) {
                    $pdo->prepare("
                        INSERT INTO health_record
                            (Animal_ID, Veterinarian_ID, Diagnosis, Treatment, Record_Date, Health_Status)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ")->execute([
                        $id, $empID,
                        $newDiagnosis ?: null,
                        $newTreatment ?: null,
                        $today,
                        $animalHealthStatus
                    ]);
                }
            }

            $pdo->commit();

            $stmt->execute([$id]);
            $animal = $stmt->fetch(PDO::FETCH_ASSOC);

            $successMsg = 'Animal report updated. Last check-up date set to ' . date('F j, Y') . '.';

        } catch (Exception $e) {
            $pdo->rollBack();
            $errorMsg = 'Failed to save: ' . $e->getMessage();
        }
    }
}

$justHealed = ($animal['Health_Status'] === 'Healthy' && !empty($animal['Cured_Date']));

$vetStatusMap = [
    'Sick'    => 'Undergoing Treatment',
    'Pending' => 'None',
    'Healthy' => 'Healthy',
];
$currentVetStatus = $vetStatusMap[$animal['Health_Status']] ?? 'None';

$enclosures = $pdo->query("SELECT Enclosure_ID, Enclosure_Name FROM enclosure ORDER BY Enclosure_Name")->fetchAll();

$rawAnimal = $pdo->prepare("SELECT Enclosure_ID FROM animal WHERE Animal_ID=?");
$rawAnimal->execute([$id]);
$rawRow = $rawAnimal->fetch();
$currentEnclosureId = $rawRow ? $rawRow['Enclosure_ID'] : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Animal Report — Vet</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body { overflow: auto; }
        .page-wrap {
            box-sizing: border-box; min-height: 100vh;
            padding: 28px clamp(16px, 3vw, 40px);
            background-color: rgba(187, 223, 158, 0.95);
        }
        .page-header {
            display: flex; justify-content: space-between; align-items: flex-start;
            gap: 16px; margin-bottom: 20px;
            border-bottom: 3px solid var(--accent-color); padding-bottom: 14px;
            flex-wrap: wrap;
        }
        .page-header h1 { margin: 0; font-size: clamp(1.2rem, 2.2vw, 1.6rem); font-weight: 800; color: var(--text-color); }
        .page-header .sub { margin: 4px 0 0; font-size: 0.85rem; color: #555; font-weight: 500; }
        .header-actions { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }

        .logout-btn {
            padding: 9px 20px; background-color: var(--accent-color); border: none;
            border-radius: 1000px; font: inherit; font-weight: 600; cursor: pointer;
            color: var(--text-color); text-decoration: none; display: inline-block;
        }
        .logout-btn:hover { background-color: var(--text-color); color: white; }
        .back-btn {
            display: inline-block; padding: 8px 16px;
            background-color: white; border: 2px solid var(--accent-color);
            border-radius: 8px; color: var(--text-color);
            font-weight: 600; text-decoration: none; font-size: 0.88rem;
        }
        .back-btn:hover { background-color: var(--accent-color); }

        .info-strip {
            background: white; border-radius: 12px; padding: 14px 20px;
            margin-bottom: 18px; display: flex; flex-wrap: wrap; gap: 18px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05); border-left: 4px solid var(--accent-color);
            align-items: center;
        }
        .info-strip .animal-name { font-size: 1.15rem; font-weight: 800; color: var(--text-color); }
        .info-strip .animal-sub  { font-size: 0.82rem; color: #666; margin-top: 2px; }
        .status-badge {
            display: inline-block; padding: 4px 12px; border-radius: 999px;
            font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em;
        }
        .badge-healthy { background: #d4edda; color: #155724; }
        .badge-sick    { background: #f8d7da; color: #721c24; }
        .badge-pending { background: #fff3cd; color: #856505; }

        /* ── Notification popup ── */
        #notifPopup {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,0.35); z-index: 1000;
            justify-content: center; align-items: center;
        }
        #notifPopup.show { display: flex; }
        .popup-card {
            background: white; border-radius: 16px; padding: 28px 30px;
            max-width: 420px; width: 90%; box-shadow: 0 12px 40px rgba(0,0,0,0.18);
            animation: popIn 280ms cubic-bezier(.34,1.56,.64,1);
            position: relative;
        }
        @keyframes popIn {
            from { opacity: 0; transform: scale(0.88) translateY(16px); }
            to   { opacity: 1; transform: scale(1) translateY(0); }
        }
        .popup-type {
            font-size: 0.75rem; font-weight: 800; letter-spacing: 0.08em;
            text-transform: uppercase; color: #e74c3c; margin-bottom: 6px;
        }
        .popup-title {
            font-size: 1.15rem; font-weight: 800; color: var(--text-color);
            margin-bottom: 8px; line-height: 1.3;
        }
        .popup-body {
            font-size: 0.88rem; color: #555; line-height: 1.6; margin-bottom: 20px;
        }
        .popup-body strong { color: var(--text-color); }
        .popup-actions { display: flex; gap: 10px; flex-wrap: wrap; }
        .popup-btn-primary {
            padding: 10px 22px; background: var(--accent-color); border: none;
            border-radius: 8px; font: inherit; font-weight: 700; cursor: pointer;
            color: var(--text-color); text-decoration: none; display: inline-block;
            flex: 1; text-align: center; font-size: 0.88rem;
        }
        .popup-btn-primary:hover { background: var(--text-color); color: white; text-decoration: none; }
        .popup-btn-secondary {
            padding: 10px 18px; background: white; border: 2px solid #ddd;
            border-radius: 8px; font: inherit; font-weight: 600; cursor: pointer;
            color: #555; font-size: 0.88rem;
        }
        .popup-btn-secondary:hover { border-color: var(--accent-color); color: var(--text-color); }
        .popup-close {
            position: absolute; top: 14px; right: 16px;
            background: none; border: none; font-size: 1.3rem;
            cursor: pointer; color: #aaa; line-height: 1;
        }
        .popup-close:hover { color: var(--text-color); }
        .popup-stripe {
            position: absolute; top: 0; left: 0; right: 0; height: 4px;
            background: #e74c3c; border-radius: 16px 16px 0 0;
        }

        /* ── Form card ── */
        .form-card {
            background: white; border-radius: 14px; padding: 24px 28px;
            max-width: 820px; box-shadow: 0 3px 10px rgba(0,0,0,0.06);
        }
        .section-label {
            font-size: 0.78rem; font-weight: 800; letter-spacing: 0.08em;
            text-transform: uppercase; color: #999; margin: 20px 0 12px;
            padding-bottom: 6px; border-bottom: 1px solid #f0f0f0;
        }
        .section-label:first-child { margin-top: 0; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .form-group { display: flex; flex-direction: column; gap: 4px; }
        .form-group.full { grid-column: 1 / -1; }
        .form-group label {
            font-size: 0.85rem; font-weight: 600; color: var(--text-color);
            width: auto; height: auto; background: none; border-radius: 0; text-align: left; padding: 0;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 9px 12px; border: 2px solid #e0e0e0; border-radius: 8px;
            font: inherit; font-size: 0.92rem; background: white;
            box-sizing: border-box; transition: border-color 150ms;
            height: auto; flex-grow: 0; width: 100%;
        }
        .form-group textarea { resize: vertical; min-height: 80px; }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus { outline: none; border-color: var(--accent-color); }
        .form-group input:disabled,
        .form-group select:disabled { background: #f7f7f7; color: #999; cursor: not-allowed; }

        /* Status section */
        .status-group { display: flex; flex-direction: column; gap: 4px; }
        .status-group label {
            font-size: 0.85rem; font-weight: 600; color: var(--text-color);
            width: auto; height: auto; background: none; border-radius: 0; text-align: left; padding: 0;
        }
        .status-hint {
            font-size: 0.78rem; color: #888; margin-top: 4px; line-height: 1.5;
        }
        .status-hint.warn { color: #c0392b; font-weight: 600; }

        .record-date-box {
            background: #f7fbf4; border: 2px solid #e0f0d8; border-radius: 8px;
            padding: 9px 14px; font-size: 0.88rem; color: var(--text-color);
            font-weight: 600;
        }
        .record-date-box span { color: #888; font-weight: 500; font-size: 0.8rem; }

        .submit-row { margin-top: 22px; display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
        .save-btn {
            padding: 11px 30px; background: var(--accent-color); border: none;
            border-radius: 1000px; font: inherit; font-weight: 700; cursor: pointer;
            color: var(--text-color); font-size: 0.95rem;
        }
        .save-btn:hover { background: var(--text-color); color: white; }

        .alert {
            border-radius: 10px; padding: 12px 16px; margin-bottom: 16px;
            font-size: 0.88rem; font-weight: 600; display: flex; align-items: center; gap: 10px;
        }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error   { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        @media (max-width: 580px) {
            .form-grid { grid-template-columns: 1fr; }
            .form-group.full { grid-column: 1; }
        }
    </style>
</head>
<body>
<div class="page-wrap">

    <div class="page-header">
        <div>
            <h1>Update Animal Report</h1>
            <p class="sub"><?= date('l, F j, Y') ?></p>
        </div>
        <div class="header-actions">
            <span style="font-size:0.88rem;font-weight:600;color:var(--text-color)">
                <?= htmlspecialchars($_SESSION['firstname'] ?? '') ?>
            </span>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </div>

    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:18px">
        <a href="vet_dashboard.php" class="back-btn">← Vet Dashboard</a>
        <a href="health-reports.php" class="back-btn">Health Records</a>
    </div>

    <?php
        $badgeClass = strtolower($animal['Health_Status'] ?? 'pending');
    ?>
    <div class="info-strip">
        <div>
            <div class="animal-name"><?= htmlspecialchars($animal['Name']) ?></div>
            <div class="animal-sub">
                <?= htmlspecialchars($animal['Species']) ?>
                <?= $animal['Category'] ? ' · ' . htmlspecialchars($animal['Category']) : '' ?>
                <?= $animal['Age'] ? ' · Age ' . (int)$animal['Age'] : '' ?>
                <?= $animal['Sex'] ? ' · ' . htmlspecialchars($animal['Sex']) : '' ?>
            </div>
        </div>
        <span class="status-badge badge-<?= $badgeClass ?>">
            <?= htmlspecialchars($animal['Health_Status']) ?>
        </span>
        <?php if (!empty($animal['Enclosure_Name'])): ?>
            <span style="font-size:0.82rem;color:#777">
                Enclosure: <?= htmlspecialchars($animal['Enclosure_Name']) ?>
            </span>
        <?php endif; ?>
        <?php if (!empty($animal['Record_Date'])): ?>
            <span style="font-size:0.82rem;color:#777">
                Last check-up: <strong><?= htmlspecialchars($animal['Record_Date']) ?></strong>
            </span>
        <?php endif; ?>
    </div>

    <?php if ($successMsg): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($successMsg) ?>
        </div>
    <?php endif; ?>
    <?php if ($errorMsg): ?>
        <div class="alert alert-error">
            <?= htmlspecialchars($errorMsg) ?>
        </div>
    <?php endif; ?>

    <div class="form-card">
        <form method="POST" id="updateForm">

            <div class="section-label">Animal Information</div>
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
                    <input type="text" name="category" value="<?= htmlspecialchars($animal['Category'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Age</label>
                    <input type="number" name="age" value="<?= $animal['Age'] !== null ? (int)$animal['Age'] : '' ?>" min="0">
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
                            <option value="<?= (int)$enc['Enclosure_ID'] ?>"
                                <?= (string)$currentEnclosureId === (string)$enc['Enclosure_ID'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($enc['Enclosure_Name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="section-label" style="margin-top:24px">Medical Record</div>
            <div class="form-grid">

                <div class="form-group full">
                    <label>Diagnosis</label>
                    <textarea name="diagnosis" id="diagnosisField" placeholder="Describe the diagnosis, or type 'Cured' once the animal has recovered..."
                    ><?= htmlspecialchars($animal['Diagnosis'] ?? '') ?></textarea>
                </div>

                <div class="form-group full">
                    <label>Treatment</label>
                    <textarea name="treatment" id="treatmentField" placeholder="Describe the treatment plan or medication..."
                    ><?= htmlspecialchars($animal['Treatment'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <div class="status-group">
                        <label>Health Status</label>
                        <select name="vet_status" id="vetStatusField" onchange="onStatusChange(this.value)">
                            <option value="None"                 <?= $currentVetStatus === 'None'                 ? 'selected' : '' ?>>None</option>
                            <option value="Undergoing Treatment" <?= $currentVetStatus === 'Undergoing Treatment' ? 'selected' : '' ?>>Undergoing Treatment</option>
                            <option value="Healthy"              <?= $currentVetStatus === 'Healthy'              ? 'selected' : '' ?>>Healthy</option>
                        </select>
                        <div class="status-hint" id="statusHint">
                            <?php if ($currentVetStatus !== 'Healthy'): ?>
                                To mark as Healthy, the diagnosis must state the animal is cured.
                            <?php else: ?>
                                Animal is recorded as healthy.
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Last Check-up Date</label>
                    <div class="record-date-box">
                        <?php if (!empty($animal['Record_Date'])): ?>
                            <?= htmlspecialchars($animal['Record_Date']) ?>
                            <br><span>Will update to today (<?= date('Y-m-d') ?>) when you save</span>
                        <?php else: ?>
                            No record yet
                            <br><span>Will be set to today (<?= date('Y-m-d') ?>) when you save</span>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

            <div class="submit-row">
                <button type="submit" class="save-btn">Save Report</button>
                <a href="vet_dashboard.php" class="back-btn" style="margin-bottom:0">Cancel</a>
            </div>

        </form>
    </div>

</div>


<?php if ($animal['Health_Status'] === 'Sick' || $animal['Health_Status'] === 'Pending'): ?>
<div id="notifPopup" class="show">
    <div class="popup-card">
        <div class="popup-stripe"></div>
        <button class="popup-close" onclick="closePopup()" title="Dismiss">&#x2715;</button>
        <div class="popup-type">Animal Alert</div>
        <div class="popup-title">
            <?= $animal['Health_Status'] === 'Sick' ? 'Sick Animal Requires Attention' : 'Animal Pending Review' ?>
        </div>
        <div class="popup-body">
            <strong><?= htmlspecialchars($animal['Name']) ?></strong>
            (<?= htmlspecialchars($animal['Species']) ?>)
            <strong><?= htmlspecialchars($animal['Health_Status']) ?></strong>.
            <?php if (!empty($animal['Diagnosis'])): ?>
                <br><br>Current diagnosis: <strong><?= htmlspecialchars($animal['Diagnosis']) ?></strong>
            <?php endif; ?>
            <br><br>Please review and update the medical report below.
        </div>
        <div class="popup-actions">
            <button class="popup-btn-secondary" onclick="closePopup()">Review Report</button>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function closePopup() {
    document.getElementById('notifPopup').classList.remove('show');
}

document.addEventListener('click', function(e) {
    const popup = document.getElementById('notifPopup');
    if (popup && e.target === popup) closePopup();
});

function onStatusChange(val) {
    const hint = document.getElementById('statusHint');
    const diag = document.getElementById('diagnosisField').value.toLowerCase();

    if (val === 'Healthy') {
        if (diag.indexOf('cured') === -1) {
            hint.textContent = 'Cannot mark as Healthy: the diagnosis must contain "cured" first.';
            hint.className = 'status-hint warn';
        } else {
            hint.textContent = 'Diagnosis confirms cured. You may save as Healthy.';
            hint.className = 'status-hint';
            hint.style.color = '#155724';
        }
    } else if (val === 'Undergoing Treatment') {
        hint.textContent = 'Animal will be marked as Sick and under active treatment.';
        hint.className = 'status-hint';
        hint.style.color = '';
    } else {
        hint.textContent = 'Status is None — animal will be shown as Pending review.';
        hint.className = 'status-hint';
        hint.style.color = '';
    }
}

document.getElementById('updateForm').addEventListener('submit', function(e) {
    const status = document.getElementById('vetStatusField').value;
    const diag   = document.getElementById('diagnosisField').value.toLowerCase();
    if (status === 'Healthy' && diag.indexOf('cured') === -1) {
        e.preventDefault();
        alert('The animal cannot be marked Healthy until the diagnosis states it is cured.');
    }
});

document.getElementById('diagnosisField').addEventListener('input', function() {
    const status = document.getElementById('vetStatusField').value;
    if (status === 'Healthy') onStatusChange('Healthy');
});
</script>
</body>
</html>