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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
              && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    if ($_POST['action'] === 'resolve_alert') {
        $alertId = (int)($_POST['alert_id'] ?? 0);
        if ($alertId > 0) {
            $pdo->prepare("
                UPDATE vet_alerts
                SET IsResolved = 1, ResolvedAt = NOW()
                WHERE AlertID = ? AND IsResolved = 0
            ")->execute([$alertId]);
        }
        if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success' => true]); exit; }
        header('Location: vet_dashboard.php'); exit;
    }

    if ($_POST['action'] === 'resolve_all_alerts') {
        $pdo->prepare("
            UPDATE vet_alerts SET IsResolved = 1, ResolvedAt = NOW() WHERE IsResolved = 0
        ")->execute();
        if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success' => true]); exit; }
        header('Location: vet_dashboard.php'); exit;
    }
}

$summary = $pdo->query("
    SELECT
        COUNT(*) AS TotalAnimals,
        SUM(CASE WHEN COALESCE(Health_Status,'Pending') = 'Sick'    THEN 1 ELSE 0 END) AS SickAnimals,
        SUM(CASE WHEN COALESCE(Health_Status,'Pending') = 'Pending' THEN 1 ELSE 0 END) AS PendingAnimals
    FROM animal
")->fetch(PDO::FETCH_ASSOC);

$totalAnimals   = (int)($summary['TotalAnimals']   ?? 0);
$sickAnimals    = (int)($summary['SickAnimals']    ?? 0);
$pendingAnimals = (int)($summary['PendingAnimals'] ?? 0);

$openAlerts = $pdo->query("
    SELECT
        va.AlertID,
        va.Animal_ID,
        va.AlertType,
        va.Message,
        va.CreatedAt,
        a.Name          AS AnimalName,
        a.Species       AS AnimalSpecies,
        e.Enclosure_Name
    FROM vet_alerts va
    JOIN  animal a    ON va.Animal_ID    = a.Animal_ID
    LEFT JOIN enclosure e ON a.Enclosure_ID = e.Enclosure_ID
    WHERE va.IsResolved = 0
    ORDER BY va.CreatedAt DESC
")->fetchAll(PDO::FETCH_ASSOC);

$openCount = count($openAlerts);

$sickList = $pdo->query("
    SELECT a.Animal_ID, a.Name, a.Species,
           hr.Diagnosis,
           hr.Cured_Date
    FROM animal a
    LEFT JOIN (
        SELECT hr1.Animal_ID, hr1.Diagnosis, hr1.Cured_Date
        FROM health_record hr1
        INNER JOIN (
            SELECT Animal_ID, MAX(HealthRecord_ID) AS MaxID
            FROM health_record
            GROUP BY Animal_ID
        ) latest ON hr1.Animal_ID = latest.Animal_ID AND hr1.HealthRecord_ID = latest.MaxID
    ) hr ON hr.Animal_ID = a.Animal_ID
    WHERE COALESCE(a.Health_Status,'Pending') = 'Sick'
    ORDER BY a.Name
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

$defaultUpdateAnimalHref = !empty($sickList)
    ? ('vetanimalupdate.php?id=' . (int) $sickList[0]['Animal_ID'])
    : 'health-reports.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vet Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <style>
        html, body { min-height: 100%; }
        body { overflow: auto; margin: 0; }

        .dashboard-wrapper {
            box-sizing: border-box; width: 100%;
            min-height: 100vh; min-height: 100dvh;
            background-color: rgba(187, 223, 158, 0.95);
            text-align: left;
        }
        .dashboard-inner {
            box-sizing: border-box; max-width: 1200px;
            margin: 0 auto;
            padding: 20px clamp(12px, 2.4vw, 18px);
        }

        .dashboard-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; margin-bottom: 20px; border-bottom: 3px solid var(--accent-color); padding-bottom: 14px; flex-wrap: wrap; }
        .dashboard-header h1 { margin: 0; font-size: clamp(1.35rem, 2.5vw, 1.75rem); font-weight: 800; color: var(--text-color); }
        .dashboard-header .dash-meta { margin: 6px 0 0; font-size: 0.9rem; color: #666; font-weight: 500; }
        .dashboard-header-actions { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
        .dashboard-header-actions .user-name { font-size: 0.9rem; font-weight: 600; color: var(--text-color); }
        .role-badge { background: var(--accent-color); color: var(--text-color); padding: 4px 14px; border-radius: 1000px; font-size: 0.8rem; font-weight: 700; text-transform: capitalize; }
        .secondary-nav-btn { padding: 9px 18px; background-color: var(--base-color); border: 2px solid var(--accent-color); border-radius: 1000px; font: inherit; font-weight: 600; font-size: 0.88rem; color: var(--text-color); text-decoration: none; }
        .secondary-nav-btn:hover { background-color: var(--accent-color); text-decoration: none; }
        .logout-btn { padding: 10px 22px; background-color: var(--accent-color); border: none; border-radius: 1000px; font: inherit; font-weight: 600; cursor: pointer; color: var(--text-color); text-decoration: none; display: inline-block; }
        .logout-btn:hover { background-color: var(--text-color); color: white; text-decoration: none; }

        .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; margin-bottom: 22px; }
        .stat-card { background: white; border-radius: 12px; padding: 18px; box-shadow: 0 3px 8px rgba(0,0,0,0.05); border-left: 4px solid var(--accent-color); }
        .stat-card.danger  { border-left-color: #e74c3c; }
        .stat-card.warning { border-left-color: #f39c12; }
        .stat-card .stat-label { font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.06em; color: #666; font-weight: 600; margin-bottom: 8px; }
        .stat-card .stat-value { font-size: 1.85rem; font-weight: 800; color: var(--text-color); line-height: 1.1; }
        .stat-card.danger  .stat-value { color: #e74c3c; }
        .stat-card.warning .stat-value { color: #f39c12; }

        .section-title { font-size: 1rem; margin: 22px 0 10px; border-bottom: 1px solid #e0e0e0; padding-bottom: 6px; color: var(--text-color); font-weight: 700; }
        .section-title:first-of-type { margin-top: 0; }
        .tiles-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px; margin-bottom: 22px; }
        .tile { background: white; border-radius: 12px; padding: 16px 18px; text-decoration: none; color: var(--text-color); box-shadow: 0 3px 8px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 14px; transition: transform 120ms ease, box-shadow 120ms ease; border: 2px solid transparent; }
        .tile:hover { transform: translateY(-2px); box-shadow: 0 6px 14px rgba(23,103,7,0.12); border-color: var(--accent-color); text-decoration: none; }
        .tile-text strong { display: block; font-size: 0.9rem; font-weight: 700; margin-bottom: 3px; }
        .tile-text span   { font-size: 0.8rem; color: #666; }

        #sickPopup {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,0.35); z-index: 1100;
            justify-content: center; align-items: center;
        }
        #sickPopup.show { display: flex; }
        .sick-popup-card {
            background: white; border-radius: 16px; padding: 0;
            max-width: 460px; width: 92%; box-shadow: 0 14px 44px rgba(0,0,0,0.2);
            animation: sickPopIn 300ms cubic-bezier(.34,1.56,.64,1);
            position: relative; overflow: hidden;
        }
        @keyframes sickPopIn {
            from { opacity: 0; transform: scale(0.88) translateY(18px); }
            to   { opacity: 1; transform: scale(1) translateY(0); }
        }
        .sick-popup-header {
            background: #c0392b; padding: 18px 22px 14px;
            color: white; position: relative;
        }
        .sick-popup-header .popup-type {
            font-size: 0.72rem; font-weight: 800; letter-spacing: 0.1em;
            text-transform: uppercase; opacity: 0.85; margin-bottom: 4px;
        }
        .sick-popup-header h2 {
            margin: 0; font-size: 1.1rem; font-weight: 800; color: white; line-height: 1.3;
        }
        .sick-popup-close {
            position: absolute; top: 14px; right: 16px;
            background: rgba(255,255,255,0.2); border: none; border-radius: 6px;
            font-size: 1rem; cursor: pointer; color: white; line-height: 1;
            padding: 4px 8px; font-weight: 700;
        }
        .sick-popup-close:hover { background: rgba(255,255,255,0.35); }
        .sick-popup-body { padding: 18px 22px 20px; }
        .sick-popup-sub {
            font-size: 0.85rem; color: #555; margin-bottom: 14px; line-height: 1.5;
        }
        .sick-animal-list { display: flex; flex-direction: column; gap: 8px; margin-bottom: 18px; }
        .sick-animal-row {
            display: flex; align-items: center; justify-content: space-between;
            padding: 10px 14px; background: #fff8f8; border: 1.5px solid #f5c6cb;
            border-radius: 10px; gap: 12px;
        }
        .sick-animal-row .san-name { font-weight: 700; font-size: 0.88rem; color: var(--text-color); }
        .sick-animal-row .san-species { font-size: 0.78rem; color: #888; margin-top: 2px; }
        .sick-animal-row .san-diag {
            font-size: 0.76rem; color: #c0392b; max-width: 160px;
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }
        .btn-go-animal {
            padding: 6px 14px; background: var(--accent-color); border: none;
            border-radius: 6px; font: inherit; font-size: 0.78rem; font-weight: 700;
            cursor: pointer; color: var(--text-color); text-decoration: none;
            white-space: nowrap; display: inline-block;
        }
        .btn-go-animal:hover { background: var(--text-color); color: white; text-decoration: none; }
        .sick-popup-actions { display: flex; gap: 10px; }
        .popup-btn-dismiss {
            flex: 1; padding: 10px; background: white; border: 2px solid #ddd;
            border-radius: 8px; font: inherit; font-weight: 600; cursor: pointer;
            color: #555; font-size: 0.88rem; text-align: center;
        }
        .popup-btn-dismiss:hover { border-color: var(--accent-color); color: var(--text-color); }

        .sick-quick-list { display: flex; flex-direction: column; gap: 8px; margin-bottom: 22px; }
        .sick-quick-row {
            display: flex; align-items: center; justify-content: space-between;
            background: white; border-radius: 10px; padding: 12px 16px;
            border: 2px solid #f5c6cb; gap: 14px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.04);
        }
        .sqr-left { flex: 1; min-width: 0; }
        .sqr-name { font-size: 0.92rem; font-weight: 800; color: var(--text-color); }
        .sqr-sub  { font-size: 0.78rem; color: #888; margin-top: 2px; }
        .sqr-diag { font-size: 0.76rem; color: #c0392b; margin-top: 4px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 340px; }
        .sqr-btn { padding: 8px 18px; background: var(--accent-color); border: none; border-radius: 8px; font: inherit; font-size: 0.82rem; font-weight: 700; cursor: pointer; color: var(--text-color); text-decoration: none; white-space: nowrap; display: inline-block; flex-shrink: 0; }
        .sqr-btn:hover { background: var(--text-color); color: white; text-decoration: none; }
    </style>
</head>
<body>
<div class="dashboard-wrapper">
<div class="dashboard-inner">

    <div class="dashboard-header">
        <div>
            <h1>Veterinarian dashboard</h1>
            <p class="dash-meta"><?= date('l, F j, Y') ?></p>
        </div>
        <div class="dashboard-header-actions">
            <span class="user-name"><?= htmlspecialchars($_SESSION['firstname']) ?></span>
            <span class="role-badge"><?= htmlspecialchars($_SESSION['role']) ?></span>

            <?php if ($isAdmin): ?>
                <a href="dashboard.php" class="secondary-nav-btn">← Staff dashboard</a>
            <?php endif; ?>
            <a href="change-password.php" class="secondary-nav-btn">Change Password</a>
            <?php if ($isAdmin): ?>
            <?php include __DIR__ . '/admin_header_cart_profile.inc.php'; ?>
            <?php endif; ?>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </div>

    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-label">Animals in view</div>
            <div class="stat-value"><?= $totalAnimals ?></div>
        </div>
        <div class="stat-card <?= $sickAnimals > 0 ? 'danger' : '' ?>">
            <div class="stat-label">Sick animals</div>
            <div class="stat-value"><?= $sickAnimals ?></div>
        </div>
        <div class="stat-card <?= $pendingAnimals > 0 ? 'warning' : '' ?>">
            <div class="stat-label">Pending review</div>
            <div class="stat-value"><?= $pendingAnimals ?></div>
        </div>
        <div class="stat-card" id="openAlertCard" class="<?= $openCount > 0 ? 'danger' : '' ?>">
            <div class="stat-label">Open alerts</div>
            <div class="stat-value" id="openAlertCount"><?= $openCount ?></div>
        </div>
    </div>

    <div class="section-title">Animals &amp; enclosures</div>
    <div class="tiles-grid">
        <a href="animals_report.php" class="tile">
            <div class="tile-text"><strong>Animals report</strong><span>Search and filter animals</span></div>
        </a>
        <a href="health-reports.php" class="tile">
            <div class="tile-text"><strong>Health records</strong><span>Medical history and status</span></div>
        </a>
        <a href="caretaker_dashboard.php#care-table" class="tile">
            <div class="tile-text"><strong>Health status updates</strong><span>Open the care board to set Healthy, Sick, or Pending.</span></div>
        </a>
        <a href="<?= htmlspecialchars($defaultUpdateAnimalHref) ?>" class="tile">
            <div class="tile-text"><strong>Update animal report</strong><span>Edit diagnosis, treatment and status</span></div>
        </a>
    </div>

    <?php if (!empty($sickList)): ?>
    <div class="section-title" style="margin-top:24px">Sick animals requiring attention</div>
    <div class="sick-quick-list">
        <?php foreach ($sickList as $s): ?>
        <div class="sick-quick-row">
            <div class="sqr-left">
                <div class="sqr-name"><?= htmlspecialchars($s['Name']) ?></div>
                <div class="sqr-sub"><?= htmlspecialchars($s['Species']) ?></div>
                <?php if (!empty($s['Diagnosis']) && empty($s['Cured_Date'])): ?>
                    <div class="sqr-diag"><?= htmlspecialchars($s['Diagnosis']) ?></div>
                <?php endif; ?>
            </div>
            <a href="vetanimalupdate.php?id=<?= (int)$s['Animal_ID'] ?>" class="sqr-btn">Update Report</a>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</div>
</div>

<?php if (!empty($sickList)): ?>
<div id="sickPopup" class="show">
    <div class="sick-popup-card">
        <div class="sick-popup-header">
            <div class="popup-type">Animal Alert</div>
            <h2><?= count($sickList) === 1 ? '1 sick animal requires attention' : count($sickList) . ' sick animals require attention' ?></h2>
            <button class="sick-popup-close" onclick="closeSickPopup()" title="Dismiss">X</button>
        </div>
        <div class="sick-popup-body">
            <p class="sick-popup-sub">The following animals are currently marked as sick and need a medical report update.</p>
            <div class="sick-animal-list">
                <?php foreach ($sickList as $s): ?>
                <div class="sick-animal-row">
                    <div>
                        <div class="san-name"><?= htmlspecialchars($s['Name']) ?></div>
                        <div class="san-species"><?= htmlspecialchars($s['Species']) ?></div>
                        <?php if (!empty($s['Diagnosis']) && empty($s['Cured_Date'])): ?>
                            <div class="san-diag"><?= htmlspecialchars($s['Diagnosis']) ?></div>
                        <?php endif; ?>
                    </div>
                    <a href="vetanimalupdate.php?id=<?= (int)$s['Animal_ID'] ?>" class="btn-go-animal">Update Report</a>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="sick-popup-actions">
                <button class="popup-btn-dismiss" onclick="closeSickPopup()">Dismiss</button>
            </div>
        </div>
    </div>
</div>
<script>
function closeSickPopup() {
    document.getElementById('sickPopup').classList.remove('show');
}
document.addEventListener('click', function(e) {
    const p = document.getElementById('sickPopup');
    if (p && e.target === p) closeSickPopup();
});
</script>
<?php endif; ?>

</body>
</html>