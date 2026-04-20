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
require_once __DIR__ . '/vet_alerts_helpers.php';

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

function fetchAnimal(PDO $pdo, int $id): ?array {
    $stmt = $pdo->prepare("
        SELECT a.Animal_ID, a.Name, a.Species, a.Category, a.Age, a.Sex,
               COALESCE(a.Health_Status, 'Pending') AS Health_Status,
               e.Enclosure_Name,
               hr.HealthRecord_ID,
               COALESCE(hr.Diagnosis, '')  AS Diagnosis,
               COALESCE(hr.Treatment, '')  AS Treatment,
               hr.Record_Date,
               hr.Cured_Date
        FROM animal a
        LEFT JOIN enclosure e ON a.Enclosure_ID = e.Enclosure_ID
        LEFT JOIN (
            SELECT hr1.*
            FROM health_record hr1
            INNER JOIN (
                SELECT Animal_ID, MAX(Record_Date) AS MaxDate
                FROM health_record GROUP BY Animal_ID
            ) latest ON hr1.Animal_ID = latest.Animal_ID AND hr1.Record_Date = latest.MaxDate
        ) hr ON hr.Animal_ID = a.Animal_ID
        WHERE a.Animal_ID = ?
    ");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$animal = fetchAnimal($pdo, $id);
if (!$animal) {
    header('Location: vet_dashboard.php');
    exit;
}

$successMsg = '';
$errorMsg   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newDiagnosis = trim($_POST['diagnosis'] ?? '');
    $newTreatment = trim($_POST['treatment'] ?? '');
    $vetStatus    = $_POST['vet_status'] ?? 'Pending';
    $today        = date('Y-m-d');

    $allowedStatuses = ['Healthy', 'Sick', 'Pending', 'Under Treatment'];
    if (!in_array($vetStatus, $allowedStatuses, true)) {
        $vetStatus = 'Pending';
    }
    // "Under Treatment" is a friendly label — maps to Sick in the database
    $animalHealthStatus = ($vetStatus === 'Under Treatment') ? 'Sick' : $vetStatus;

    if ($animalHealthStatus === 'Healthy' && stripos($newDiagnosis, 'cured') === false) {
        $errorMsg = 'Cannot mark as Healthy: the diagnosis must say "cured" first.';
    } else {
        try {
            $pdo->beginTransaction();

            if ($animal['Health_Status'] === 'Sick' && $animalHealthStatus !== 'Sick') {
                vet_alerts_clear_stale_resolved_sick($pdo, $id);
            }

            $pdo->prepare("UPDATE animal SET Health_Status=? WHERE Animal_ID=?")
                ->execute([$animalHealthStatus, $id]);

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
                    ")->execute([$id, $empID, $newDiagnosis ?: null, $newTreatment ?: null,
                                 $today, $animalHealthStatus, $curedDate]);
                } catch (PDOException $colErr) {
                    $pdo->prepare("
                        INSERT INTO health_record
                            (Animal_ID, Veterinarian_ID, Diagnosis, Treatment, Record_Date, Health_Status)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ")->execute([$id, $empID, $newDiagnosis ?: null, $newTreatment ?: null,
                                 $today, $animalHealthStatus]);
                }
            }

            $pdo->commit();
            $animal = fetchAnimal($pdo, $id);
            $successMsg = 'Report saved. Last check-up date updated to ' . date('F j, Y') . '.';

        } catch (Exception $e) {
            $pdo->rollBack();
            $errorMsg = 'Save failed: ' . $e->getMessage();
        }
    }
}

$isCuredAndRecorded = !empty($animal['Cured_Date']) && $animal['Health_Status'] === 'Healthy';

// Map DB status to vet dropdown value
// Sick shows as "Under Treatment" in the dropdown
$dbToVetStatus = [
    'Sick'    => 'Under Treatment',
    'Pending' => 'Pending',
    'Healthy' => 'Healthy',
];
$currentVetStatus = $dbToVetStatus[$animal['Health_Status']] ?? 'Pending';
$badgeClass = strtolower($animal['Health_Status']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Animal Report</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body { overflow: auto; }
        .page-wrap { box-sizing: border-box; min-height: 100vh; padding: 28px clamp(16px, 3vw, 40px); background-color: rgba(187, 223, 158, 0.95); }
        .page-inner { max-width: 920px; margin: 0 auto; width: 100%; }
        .page-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; margin-bottom: 20px; border-bottom: 3px solid var(--accent-color); padding-bottom: 14px; flex-wrap: wrap; }
        .page-header h1 { margin: 0; font-size: clamp(1.2rem, 2.2vw, 1.6rem); font-weight: 800; color: var(--text-color); }
        .page-header .sub { margin: 4px 0 0; font-size: 0.85rem; color: #555; }
        .header-right { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .logout-btn { padding: 9px 20px; background: var(--accent-color); border: none; border-radius: 1000px; font: inherit; font-weight: 600; cursor: pointer; color: var(--text-color); text-decoration: none; display: inline-block; }
        .logout-btn:hover { background: var(--text-color); color: white; }
        .back-btn { display: inline-block; padding: 8px 16px; background: white; border: 2px solid var(--accent-color); border-radius: 8px; color: var(--text-color); font-weight: 600; text-decoration: none; font-size: 0.88rem; }
        .back-btn:hover { background: var(--accent-color); text-decoration: none; }
        .info-strip { background: white; border-radius: 12px; padding: 16px 20px; margin-bottom: 20px; display: flex; flex-wrap: wrap; gap: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); border-left: 4px solid var(--accent-color); align-items: center; }
        .info-strip.sick    { border-left-color: #e74c3c; }
        .info-strip.pending { border-left-color: #f39c12; }
        .animal-name { font-size: 1.05rem; font-weight: 800; color: var(--text-color); }
        .animal-sub  { font-size: 0.8rem; color: #666; margin-top: 3px; }
        .status-badge { display: inline-block; padding: 4px 12px; border-radius: 999px; font-size: 0.74rem; font-weight: 700; text-transform: uppercase; }
        .badge-healthy { background: #d4edda; color: #155724; }
        .badge-sick    { background: #f8d7da; color: #721c24; }
        .badge-pending { background: #fff3cd; color: #856505; }
        .last-checkup  { font-size: 0.82rem; color: #777; }
        .last-checkup strong { color: var(--text-color); }
        .alert { border-radius: 10px; padding: 12px 16px; margin-bottom: 18px; font-size: 0.88rem; font-weight: 600; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error   { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .cured-notice { background: #d4edda; border: 1px solid #c3e6cb; border-radius: 10px; padding: 12px 16px; font-size: 0.85rem; color: #155724; font-weight: 600; margin-bottom: 18px; line-height: 1.5; }
        .form-card { background: white; border-radius: 14px; padding: 26px 28px; max-width: 100%; box-shadow: 0 3px 10px rgba(0,0,0,0.06); }
        #updateForm { width: 100%; max-width: none; margin-top: 0; margin-bottom: 0; align-items: stretch; }
        #updateForm > div { justify-content: flex-start; width: 100%; }
        .section-label { font-size: 0.72rem; font-weight: 800; letter-spacing: 0.09em; text-transform: uppercase; color: #aaa; margin-bottom: 16px; padding-bottom: 6px; border-bottom: 1px solid #f0f0f0; }
        .field-group { display: flex; flex-direction: column; gap: 5px; margin-bottom: 18px; }
        .field-group label { font-size: 0.88rem; font-weight: 700; color: var(--text-color); width: auto; height: auto; background: none; border-radius: 0; text-align: left; padding: 0; }
        .field-group textarea, .field-group select { padding: 10px 12px; border: 2px solid #e0e0e0; border-radius: 8px; font: inherit; font-size: 0.92rem; background: white; box-sizing: border-box; transition: border-color 150ms; width: 100%; }
        .field-group textarea { resize: vertical; min-height: 90px; height: auto; }
        .field-group textarea:focus, .field-group select:focus { outline: none; border-color: var(--accent-color); }
        .status-hint      { font-size: 0.78rem; color: #888; margin-top: 5px; line-height: 1.5; }
        .status-hint.warn { color: #c0392b; font-weight: 600; }
        .status-hint.ok   { color: #155724; font-weight: 600; }
        .checkup-box { background: #f7fbf4; border: 2px solid #e0f0d8; border-radius: 8px; padding: 10px 14px; font-size: 0.88rem; color: var(--text-color); font-weight: 600; margin-bottom: 20px; }
        .checkup-box .note { color: #888; font-weight: 500; font-size: 0.79rem; display: block; margin-top: 3px; }
        .submit-row { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
        .save-btn { padding: 11px 30px; background: var(--accent-color); border: none; border-radius: 1000px; font: inherit; font-weight: 700; cursor: pointer; color: var(--text-color); font-size: 0.95rem; }
        .save-btn:hover { background: var(--text-color); color: white; }
        #notifPopup { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.4); z-index: 1000; justify-content: center; align-items: center; }
        #notifPopup.show { display: flex; }
        .popup-card { background: white; border-radius: 16px; max-width: 400px; width: 90%; box-shadow: 0 14px 44px rgba(0,0,0,0.2); animation: popIn 280ms cubic-bezier(.34,1.56,.64,1); overflow: hidden; position: relative; }
        @keyframes popIn { from { opacity: 0; transform: scale(0.88) translateY(16px); } to { opacity: 1; transform: scale(1) translateY(0); } }
        .popup-top { background: #c0392b; padding: 16px 20px; position: relative; }
        .popup-top .popup-type { font-size: 0.7rem; font-weight: 800; letter-spacing: 0.1em; text-transform: uppercase; color: rgba(255,255,255,0.8); margin-bottom: 4px; }
        .popup-top h2 { margin: 0; font-size: 1rem; font-weight: 800; color: white; }
        .popup-x { position: absolute; top: 12px; right: 14px; background: rgba(255,255,255,0.2); border: none; border-radius: 6px; color: white; font-size: 0.88rem; font-weight: 700; cursor: pointer; padding: 3px 8px; line-height: 1; }
        .popup-x:hover { background: rgba(255,255,255,0.35); }
        .popup-body { padding: 18px 20px 20px; }
        .popup-body p { font-size: 0.88rem; color: #444; line-height: 1.6; margin: 0 0 16px; }
        .popup-body p strong { color: var(--text-color); }
        .popup-dismiss { width: 100%; padding: 10px; background: white; border: 2px solid var(--accent-color); border-radius: 8px; font: inherit; font-weight: 700; cursor: pointer; color: var(--text-color); font-size: 0.88rem; }
        .popup-dismiss:hover { background: var(--accent-color); }
    </style>
</head>
<body>
<div class="page-wrap">
<div class="page-inner">

    <div class="page-header">
        <div>
            <h1>Update Animal Report</h1>
            <p class="sub"><?= date('l, F j, Y') ?></p>
        </div>
        <div class="header-right">
            <span style="font-size:0.88rem;font-weight:600;color:var(--text-color)">
                <?= htmlspecialchars($_SESSION['firstname'] ?? '') ?>
            </span>
            <?php if ($isAdmin): ?>
            <?php include __DIR__ . '/admin_header_cart_profile.inc.php'; ?>
            <?php endif; ?>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </div>

    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px">
        <a href="vet_dashboard.php" class="back-btn">← Vet Dashboard</a>
        <a href="health-reports.php" class="back-btn">Health Records</a>
    </div>

    <div class="info-strip <?= $badgeClass ?>">
        <div>
            <div class="animal-name"><?= htmlspecialchars($animal['Name']) ?></div>
            <div class="animal-sub">
                <?= htmlspecialchars($animal['Species']) ?>
                <?= $animal['Category']       ? ' &middot; ' . htmlspecialchars($animal['Category']) : '' ?>
                <?= $animal['Age'] !== null   ? ' &middot; Age ' . (int)$animal['Age'] : '' ?>
                <?= $animal['Sex']            ? ' &middot; ' . htmlspecialchars($animal['Sex']) : '' ?>
                <?= $animal['Enclosure_Name'] ? ' &middot; ' . htmlspecialchars($animal['Enclosure_Name']) : '' ?>
            </div>
        </div>
        <span class="status-badge badge-<?= $badgeClass ?>"><?= htmlspecialchars($animal['Health_Status']) ?></span>
        <div class="last-checkup">
            Last check-up:
            <strong><?= !empty($animal['Record_Date']) ? htmlspecialchars($animal['Record_Date']) : 'No record yet' ?></strong>
        </div>
    </div>

    <?php if ($successMsg): ?>
        <div class="alert alert-success"><?= htmlspecialchars($successMsg) ?></div>
    <?php endif; ?>
    <?php if ($errorMsg): ?>
        <div class="alert alert-error"><?= htmlspecialchars($errorMsg) ?></div>
    <?php endif; ?>

    <?php if ($isCuredAndRecorded): ?>
    <div class="cured-notice">
        This animal was recorded as cured on <?= htmlspecialchars($animal['Cured_Date']) ?>.
        You can now reset the diagnosis, treatment, and status back to Pending.
    </div>
    <?php endif; ?>

    <div class="form-card">
        <div class="section-label">Medical Report</div>
        <form method="POST" id="updateForm">

            <div class="field-group">
                <label for="diagnosisField">Diagnosis</label>
                <textarea id="diagnosisField" name="diagnosis"
                    placeholder="Describe the diagnosis. Type 'Cured' once the animal has fully recovered."
                ><?= htmlspecialchars($animal['Diagnosis']) ?></textarea>
            </div>

            <div class="field-group">
                <label for="treatmentField">Treatment</label>
                <textarea id="treatmentField" name="treatment"
                    placeholder="Describe the treatment or medication being given."
                ><?= htmlspecialchars($animal['Treatment']) ?></textarea>
            </div>

            <div class="field-group">
                <label for="vetStatusField">Status</label>
                <select id="vetStatusField" name="vet_status" onchange="onStatusChange(this.value)">
                    <option value="Pending"         <?= $currentVetStatus === 'Pending'         ? 'selected' : '' ?>>Pending</option>
                    <option value="Sick"            <?= $currentVetStatus === 'Sick'            ? 'selected' : '' ?>>Sick</option>
                    <option value="Under Treatment" <?= $currentVetStatus === 'Under Treatment' ? 'selected' : '' ?>>Under Treatment</option>
                    <option value="Healthy"         <?= $currentVetStatus === 'Healthy'         ? 'selected' : '' ?>>Healthy</option>
                </select>
                <div class="status-hint" id="statusHint">
                    <?php if ($currentVetStatus === 'Healthy'): ?>
                        Animal is recorded as healthy.
                    <?php elseif ($currentVetStatus === 'Under Treatment'): ?>
                        Animal is actively under treatment — saved as Sick in the database.
                    <?php elseif ($currentVetStatus === 'Sick'): ?>
                        Animal is marked Sick — requires veterinary attention.
                    <?php else: ?>
                        To mark as Healthy, type "Cured" in the diagnosis field first.
                    <?php endif; ?>
                </div>
            </div>

            <div class="checkup-box">
                Last Check-up Date:
                <strong>
                    <?= !empty($animal['Record_Date']) ? htmlspecialchars($animal['Record_Date']) : 'No record yet' ?>
                </strong>
                <span class="note">Automatically updates to today (<?= date('Y-m-d') ?>) each time you save.</span>
            </div>

            <div class="submit-row">
                <button type="submit" class="save-btn">Save Report</button>
                <a href="vet_dashboard.php" class="back-btn" style="margin-bottom:0">Cancel</a>
            </div>

        </form>
    </div>

</div>
</div>

<?php if ($animal['Health_Status'] === 'Sick' || $animal['Health_Status'] === 'Pending'): ?>
<div id="notifPopup" class="show">
    <div class="popup-card">
        <div class="popup-top">
            <div class="popup-type">Animal Alert</div>
            <h2><?= $animal['Health_Status'] === 'Sick' ? 'Sick Animal Requires Attention' : 'Animal Pending Review' ?></h2>
            <button class="popup-x" onclick="closePopup()">X</button>
        </div>
        <div class="popup-body">
            <p>
                <strong><?= htmlspecialchars($animal['Name']) ?></strong>
                (<?= htmlspecialchars($animal['Species']) ?>)
                is currently marked as <strong><?= htmlspecialchars($animal['Health_Status']) ?></strong>.
                <?php if (!empty($animal['Diagnosis'])): ?>
                    <br><br>Current diagnosis: <strong><?= htmlspecialchars($animal['Diagnosis']) ?></strong>
                <?php endif; ?>
                <br><br>Please update the medical report below.
            </p>
            <button class="popup-dismiss" onclick="closePopup()">View Report</button>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function closePopup() {
    const p = document.getElementById('notifPopup');
    if (p) p.classList.remove('show');
}
document.addEventListener('click', function(e) {
    const p = document.getElementById('notifPopup');
    if (p && e.target === p) closePopup();
});

function onStatusChange(val) {
    const hint = document.getElementById('statusHint');
    const diag = document.getElementById('diagnosisField').value.toLowerCase();
    hint.className = 'status-hint';

    if (val === 'Healthy') {
        if (diag.indexOf('cured') === -1) {
            hint.textContent = 'Cannot mark as Healthy — type "Cured" in the diagnosis field first.';
            hint.classList.add('warn');
        } else {
            hint.textContent = 'Diagnosis confirms cured. You can now save as Healthy.';
            hint.classList.add('ok');
        }
    } else if (val === 'Under Treatment') {
        hint.textContent = 'Animal is actively under treatment — will be saved as Sick in the database.';
    } else if (val === 'Sick') {
        hint.textContent = 'Animal will be marked Sick — requires veterinary attention.';
    } else {
        hint.textContent = 'Animal will be marked Pending — awaiting review.';
    }
}

document.getElementById('diagnosisField').addEventListener('input', function() {
    onStatusChange(document.getElementById('vetStatusField').value);
});

document.getElementById('updateForm').addEventListener('submit', function(e) {
    const status = document.getElementById('vetStatusField').value;
    const diag   = document.getElementById('diagnosisField').value.toLowerCase();
    if (status === 'Healthy' && diag.indexOf('cured') === -1) {
        e.preventDefault();
        const hint = document.getElementById('statusHint');
        hint.textContent = 'Cannot save as Healthy — type "Cured" in the diagnosis field first.';
        hint.className = 'status-hint warn';
        document.getElementById('vetStatusField').focus();
    }
});
</script>
</body>
</html>
