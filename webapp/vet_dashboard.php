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

$resolvedAlerts = $pdo->query("
    SELECT
        va.AlertID,
        va.Animal_ID,
        va.Message,
        va.CreatedAt,
        va.ResolvedAt,
        a.Name          AS AnimalName,
        a.Species       AS AnimalSpecies,
        e.Enclosure_Name
    FROM vet_alerts va
    JOIN  animal a    ON va.Animal_ID    = a.Animal_ID
    LEFT JOIN enclosure e ON a.Enclosure_ID = e.Enclosure_ID
    WHERE va.IsResolved = 1
    ORDER BY va.ResolvedAt DESC
    LIMIT 40
")->fetchAll(PDO::FETCH_ASSOC);

$openCount = count($openAlerts);

$sickList = $pdo->query("
    SELECT a.Animal_ID, a.Name, a.Species,
           hr.Diagnosis
    FROM animal a
    LEFT JOIN (
        SELECT hr1.Animal_ID, hr1.Diagnosis
        FROM health_record hr1
        INNER JOIN (
            SELECT Animal_ID, MAX(Record_Date) AS MaxDate FROM health_record GROUP BY Animal_ID
        ) latest ON hr1.Animal_ID = latest.Animal_ID AND hr1.Record_Date = latest.MaxDate
    ) hr ON hr.Animal_ID = a.Animal_ID
    WHERE COALESCE(a.Health_Status,'Pending') = 'Sick'
    ORDER BY a.Name
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

function human_time_diff(string $dateStr): string {
    $diff = time() - strtotime($dateStr);
    if ($diff < 60)     return 'Just now';
    if ($diff < 3600)   return floor($diff / 60) . 'm ago';
    if ($diff < 86400)  return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M j, Y', strtotime($dateStr));
}
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

        .bell-btn {
            position: relative; background: white; border: 2px solid var(--accent-color);
            border-radius: 1000px; padding: 8px 16px 8px 14px; font: inherit;
            font-size: 0.88rem; font-weight: 600; cursor: pointer; color: var(--text-color);
            display: flex; align-items: center; gap: 6px; transition: 150ms ease;
        }
        .bell-btn:hover { background: var(--accent-color); }
        .bell-badge {
            background: #e74c3c; color: white; font-size: 0.7rem; font-weight: 800;
            min-width: 18px; height: 18px; border-radius: 1000px; display: inline-flex;
            align-items: center; justify-content: center; padding: 0 4px; line-height: 1;
            transition: transform 300ms cubic-bezier(.34,1.56,.64,1);
        }
        .bell-badge.pulse { animation: badgePop 400ms cubic-bezier(.34,1.56,.64,1); }
        @keyframes badgePop { 0%{transform:scale(1)} 50%{transform:scale(1.5)} 100%{transform:scale(1)} }

        .live-dot {
            display: inline-block; width: 8px; height: 8px; border-radius: 50%;
            background: #2ecc71; margin-left: 2px;
            animation: livePulse 2s ease-in-out infinite;
        }
        @keyframes livePulse {
            0%, 100% { opacity: 1;   transform: scale(1); }
            50%       { opacity: 0.4; transform: scale(0.8); }
        }

        #toastContainer {
            position: fixed; bottom: 24px; right: 24px;
            z-index: 2000; display: flex; flex-direction: column; gap: 10px;
            pointer-events: none;
        }
        .toast {
            background: #2c3e50; color: white; border-radius: 12px;
            padding: 14px 20px; font-size: 0.88rem; font-weight: 600;
            box-shadow: 0 6px 24px rgba(0,0,0,0.2); max-width: 340px;
            display: flex; align-items: flex-start; gap: 10px;
            animation: toastIn 350ms cubic-bezier(.34,1.56,.64,1);
            pointer-events: all;
        }
        .toast.danger { background: #c0392b; border-left: 4px solid #e74c3c; }
        .toast-icon { font-size: 1.1rem; flex-shrink: 0; margin-top: 1px; }
        .toast-body { flex: 1; line-height: 1.4; }
        .toast-title { font-size: 0.8rem; opacity: 0.8; margin-bottom: 2px; }
        @keyframes toastIn {
            from { opacity: 0; transform: translateY(20px) scale(0.95); }
            to   { opacity: 1; transform: translateY(0)    scale(1); }
        }
        @keyframes toastOut {
            from { opacity: 1; transform: translateY(0)    scale(1); }
            to   { opacity: 0; transform: translateY(10px) scale(0.95); }
        }

        .notif-panel-backdrop { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.25); z-index: 999; }
        .notif-panel-backdrop.open { display: block; }
        .notif-panel {
            position: fixed; top: 0; right: -460px;
            width: min(460px, 100vw); height: 100vh; background: white;
            box-shadow: -6px 0 32px rgba(0,0,0,0.14); z-index: 1000;
            display: flex; flex-direction: column;
            transition: right 280ms cubic-bezier(.4,0,.2,1); overflow: hidden;
        }
        .notif-panel.open { right: 0; }
        .notif-panel-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 18px 20px; border-bottom: 2px solid #f0f0f0;
            background: var(--accent-color); flex-shrink: 0;
        }
        .notif-panel-header h2 { margin: 0; font-size: 1.05rem; font-weight: 800; color: var(--text-color); }
        .notif-panel-actions { display: flex; gap: 8px; align-items: center; }
        .btn-resolve-all { background: white; border: 2px solid var(--text-color); border-radius: 8px; padding: 5px 12px; font: inherit; font-size: 0.78rem; font-weight: 700; cursor: pointer; color: var(--text-color); }
        .btn-resolve-all:hover { background: var(--text-color); color: white; }
        .notif-close { background: none; border: none; font-size: 1.4rem; cursor: pointer; color: var(--text-color); line-height: 1; padding: 2px 6px; }

        .panel-tabs { display: flex; border-bottom: 2px solid #f0f0f0; flex-shrink: 0; }
        .panel-tab { flex: 1; padding: 11px 8px; background: none; border: none; font: inherit; font-size: 0.85rem; font-weight: 700; cursor: pointer; color: #888; border-bottom: 3px solid transparent; margin-bottom: -2px; transition: 150ms; }
        .panel-tab.active { color: var(--text-color); border-bottom-color: var(--accent-color); }
        .panel-tab-content { display: none; flex: 1; overflow-y: auto; padding: 12px; flex-direction: column; gap: 10px; }
        .panel-tab-content.active { display: flex; }

        .notif-empty { text-align: center; color: #aaa; padding: 60px 20px; font-size: 0.95rem; }
        .notif-item { border-radius: 10px; padding: 14px 16px; border: 2px solid #f0f0f0; background: #fff; position: relative; }
        .notif-item.open-alert { background: #fff8f8; border-color: #f5c6cb; }
        .notif-item.open-alert::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 4px; background: #e74c3c; border-radius: 10px 0 0 10px; }

        .notif-item.new-alert {
            animation: slideIn 400ms cubic-bezier(.34,1.56,.64,1);
        }
        @keyframes slideIn {
            from { opacity: 0; transform: translateX(20px); }
            to   { opacity: 1; transform: translateX(0); }
        }
        .notif-item.resolved-alert { opacity: 0.7; }
        .notif-animal { font-size: 0.88rem; font-weight: 800; margin-bottom: 4px; display: flex; align-items: center; gap: 6px; }
        .notif-animal.danger { color: #e74c3c; }
        .notif-animal.muted  { color: #666; }
        .notif-enclosure { font-size: 0.78rem; color: #888; margin-bottom: 6px; }
        .notif-msg { font-size: 0.83rem; color: #444; line-height: 1.5; margin-bottom: 8px; }
        .notif-footer { display: flex; justify-content: space-between; align-items: center; gap: 8px; flex-wrap: wrap; }
        .notif-time { font-size: 0.75rem; color: #aaa; }
        .notif-resolved-time { font-size: 0.75rem; color: #2ecc71; font-weight: 600; }
        .btn-resolve { background: none; border: 2px solid #e74c3c; border-radius: 6px; padding: 4px 12px; font: inherit; font-size: 0.75rem; font-weight: 700; cursor: pointer; color: #e74c3c; transition: 150ms; }
        .btn-resolve:hover { background: #e74c3c; color: white; }

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

            <button class="bell-btn" id="bellBtn" onclick="openNotifPanel()">
                Alerts
                <span class="bell-badge" id="bellCount"
                      style="<?= $openCount === 0 ? 'display:none' : '' ?>">
                    <?= $openCount ?>
                </span>
                <span class="live-dot" title="Live updates active"></span>
            </button>

            <?php if ($isAdmin): ?>
                <a href="dashboard.php" class="secondary-nav-btn">← Staff dashboard</a>
            <?php endif; ?>
            <a href="change-password.php" class="secondary-nav-btn">Change Password</a>
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
        <a href="vetanimalupdate.php" class="tile">
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
                <?php if (!empty($s['Diagnosis'])): ?>
                    <div class="sqr-diag"><?= htmlspecialchars($s['Diagnosis']) ?></div>
                <?php endif; ?>
            </div>
            <a href="vet_update_animal.php?id=<?= (int)$s['Animal_ID'] ?>" class="sqr-btn">Update Report</a>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</div>
</div>

<div id="toastContainer"></div>

<div class="notif-panel-backdrop" id="notifBackdrop" onclick="closeNotifPanel()"></div>

<div class="notif-panel" id="notifPanel">
    <div class="notif-panel-header">
        <h2>Vet Alerts</h2>
        <div class="notif-panel-actions">
            <button class="btn-resolve-all" id="resolveAllBtn"
                    style="<?= $openCount === 0 ? 'display:none' : '' ?>"
                    onclick="resolveAll()">Resolve all</button>
            <button class="notif-close" onclick="closeNotifPanel()">✕</button>
        </div>
    </div>

    <div class="panel-tabs">
        <button class="panel-tab active" id="tabBtnOpen"     onclick="switchTab('open', this)">
            Open (<span id="openTabCount"><?= $openCount ?></span>)
        </button>
        <button class="panel-tab"        id="tabBtnResolved" onclick="switchTab('resolved', this)">
            Resolved (<?= count($resolvedAlerts) ?>)
        </button>
    </div>

    <div class="panel-tab-content active" id="tab-open">
        <?php if (empty($openAlerts)): ?>
            <div class="notif-empty" id="openEmptyState"> No open alerts.<br>All animals are healthy!</div>
        <?php else: ?>
            <?php foreach ($openAlerts as $alert): ?>
                <?= renderAlertItem($alert) ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="panel-tab-content" id="tab-resolved">
        <?php if (empty($resolvedAlerts)): ?>
            <div class="notif-empty">No resolved alerts yet.</div>
        <?php else: ?>
            <?php foreach ($resolvedAlerts as $alert): ?>
                <div class="notif-item resolved-alert">
                    <div class="notif-animal muted">
                        <?= htmlspecialchars($alert['AnimalName']) ?>
                        <span style="font-weight:500;">(<?= htmlspecialchars($alert['AnimalSpecies']) ?>)</span>
                    </div>
                    <?php if (!empty($alert['Enclosure_Name'])): ?>
                        <div class="notif-enclosure"> <?= htmlspecialchars($alert['Enclosure_Name']) ?></div>
                    <?php endif; ?>
                    <div class="notif-msg"><?= htmlspecialchars($alert['Message']) ?></div>
                    <div class="notif-footer">
                        <span class="notif-time">Alerted: <?= human_time_diff($alert['CreatedAt']) ?></span>
                        <?php if ($alert['ResolvedAt']): ?>
                            <span class="notif-resolved-time">Resolved: <?= human_time_diff($alert['ResolvedAt']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div><!-- /.notif-panel -->


<script>

const knownAlertIds = new Set([
    <?php echo implode(',', array_column($openAlerts, 'AlertID')); ?>
]);

let pollInterval = null;
const POLL_EVERY_MS = 7000; // poll every 7 seconds

function openNotifPanel()  {
    document.getElementById('notifPanel').classList.add('open');
    document.getElementById('notifBackdrop').classList.add('open');
}
function closeNotifPanel() {
    document.getElementById('notifPanel').classList.remove('open');
    document.getElementById('notifBackdrop').classList.remove('open');
}

function switchTab(name, btn) {
    document.querySelectorAll('.panel-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.panel-tab-content').forEach(t => t.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('tab-' + name).classList.add('active');
}

// ── Build an alert card HTML string (mirrors PHP renderAlertItem) ─
function buildAlertHTML(alert, isNew = false) {
    const enclosure = alert.enclosure
        ? `<div class="notif-enclosure"> ${escHtml(alert.enclosure)}</div>` : '';
    return `
        <div class="notif-item open-alert${isNew ? ' new-alert' : ''}" id="alert-${alert.alertId}"
             onclick="window.location='vet_update_animal.php?id=${alert.animalId}'"
             style="cursor:pointer;" title="Click to update animal report">
            <div class="notif-animal danger">
                ${escHtml(alert.animalName)}
                <span style="font-weight:500;color:#555;">(${escHtml(alert.animalSpecies)})</span>
            </div>
            ${enclosure}
            <div class="notif-msg">${escHtml(alert.message)}</div>
            <div class="notif-footer">
                <span class="notif-time" data-created="${escHtml(alert.createdAt)}">⏱ ${escHtml(alert.timeAgo)}</span>
                <span style="font-size:0.75rem;color:#888;font-style:italic;">Click to update report</span>
                <button class="btn-resolve" onclick="event.stopPropagation();resolveAlert(${alert.alertId}, this)">✓ Resolve</button>
            </div>
        </div>`;
}
}

function escHtml(str) {
    return String(str)
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}


async function pollAlerts() {
    try {
        const res  = await fetch('vet_alerts_real_time.php', { cache: 'no-store' });
        if (!res.ok) return;
        const data = await res.json();

        const freshIds   = new Set(data.alerts.map(a => a.alertId));
        const newAlerts  = data.alerts.filter(a => !knownAlertIds.has(a.alertId));
        const goneIds    = [...knownAlertIds].filter(id => !freshIds.has(id));

        goneIds.forEach(id => {
            knownAlertIds.delete(id);
            const el = document.getElementById(`alert-${id}`);
            if (el) el.remove();
        });

        if (newAlerts.length > 0) {
            const tab = document.getElementById('tab-open');
            const emptyState = document.getElementById('openEmptyState');
            if (emptyState) emptyState.remove();

            newAlerts.forEach(alert => {
                knownAlertIds.add(alert.alertId);
                tab.insertAdjacentHTML('afterbegin', buildAlertHTML(alert, true));
                showToast(alert);
            });
        }

        updateOpenCount(data.openCount);

    } catch (e) {
    }
}

function updateOpenCount(count) {

    const badge = document.getElementById('bellCount');
    const prev  = parseInt(badge.textContent || '0', 10);
    badge.textContent = count;
    badge.style.display = count > 0 ? 'inline-flex' : 'none';
    if (count > prev) {
        badge.classList.remove('pulse');
        void badge.offsetWidth;
        badge.classList.add('pulse');
    }

    document.getElementById('openAlertCount').textContent = count;
    const card = document.getElementById('openAlertCard');
    card.classList.toggle('danger', count > 0);

    document.getElementById('openTabCount').textContent = count;

    document.getElementById('resolveAllBtn').style.display = count > 0 ? '' : 'none';

    const tab = document.getElementById('tab-open');
    if (count === 0 && !tab.querySelector('.notif-item')) {
        if (!document.getElementById('openEmptyState')) {
            tab.innerHTML = '<div class="notif-empty" id="openEmptyState"> No open alerts.<br>All animals are healthy!</div>';
        }
    }
}

function showToast(alert) {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = 'toast danger';
    toast.innerHTML = `
        <span class="toast-icon"></span>
        <div class="toast-body">
            <div class="toast-title">New sick animal alert</div>
            <strong>${escHtml(alert.animalName)}</strong> (${escHtml(alert.animalSpecies)})
            ${alert.enclosure ? ' · ' + escHtml(alert.enclosure) : ''}
        </div>`;
    container.appendChild(toast);

    setTimeout(() => {
        toast.style.animation = 'toastOut 300ms ease forwards';
        toast.addEventListener('animationend', () => toast.remove());
    }, 5000);
}

function resolveAlert(alertId, btn) {
    fetch('vet_dashboard.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
        body: `action=resolve_alert&alert_id=${alertId}`
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) return;
        knownAlertIds.delete(alertId);
        const item = document.getElementById(`alert-${alertId}`);
        if (item) item.remove();
        updateOpenCount(knownAlertIds.size);
    });
}

function resolveAll() {
    fetch('vet_dashboard.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
        body: 'action=resolve_all_alerts'
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) return;
        knownAlertIds.clear();
        document.querySelectorAll('#tab-open .notif-item').forEach(el => el.remove());
        updateOpenCount(0);
    });
}

function refreshTimestamps() {
    document.querySelectorAll('.notif-time[data-created]').forEach(el => {
        const created = el.getAttribute('data-created');
        if (!created) return;
        const diff = Math.floor((Date.now() - new Date(created).getTime()) / 1000);
        let label;
        if (diff < 60)     label = 'Just now';
        else if (diff < 3600)  label = Math.floor(diff/60)   + 'm ago';
        else if (diff < 86400) label = Math.floor(diff/3600)  + 'h ago';
        else                   label = Math.floor(diff/86400) + 'd ago';
        el.textContent = '⏱ ' + label;
    });
}

pollAlerts();
pollInterval = setInterval(pollAlerts, POLL_EVERY_MS);
setInterval(refreshTimestamps, 60000);
</script>

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
                        <?php if (!empty($s['Diagnosis'])): ?>
                            <div class="san-diag"><?= htmlspecialchars($s['Diagnosis']) ?></div>
                        <?php endif; ?>
                    </div>
                    <a href="vet_update_animal.php?id=<?= (int)$s['Animal_ID'] ?>" class="btn-go-animal">Update Report</a>
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

<?php

function renderAlertItem(array $alert): string {
    $id        = (int)$alert['AlertID'];
    $name      = htmlspecialchars($alert['AnimalName']);
    $species   = htmlspecialchars($alert['AnimalSpecies']);
    $enclosure = !empty($alert['Enclosure_Name'])
        ? '<div class="notif-enclosure"> ' . htmlspecialchars($alert['Enclosure_Name']) . '</div>'
        : '';
    $msg      = htmlspecialchars($alert['Message']);
    $time     = human_time_diff($alert['CreatedAt']);
    $created  = htmlspecialchars($alert['CreatedAt']);
    $animalId = (int)$alert['Animal_ID'];
    return "
        <div class=\"notif-item open-alert\" id=\"alert-{$id}\"
             onclick=\"window.location='vet_update_animal.php?id={$animalId}'\"
             style=\"cursor:pointer;\" title=\"Click to update animal report\">
            <div class=\"notif-animal danger\">
                {$name}
                <span style=\"font-weight:500;color:#555;\">({$species})</span>
            </div>
            {$enclosure}
            <div class=\"notif-msg\">{$msg}</div>
            <div class=\"notif-footer\">
                <span class=\"notif-time\" data-created=\"{$created}\">⏱ {$time}</span>
                <span style=\"font-size:0.75rem;color:#888;font-style:italic;\">Click to update report</span>
                <button class=\"btn-resolve\" onclick=\"event.stopPropagation();resolveAlert({$id}, this)\">✓ Resolve</button>
            </div>
        </div>";
}
?>