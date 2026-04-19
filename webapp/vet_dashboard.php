<?php
session_start();
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

// ── Handle POST actions (manual resolve / reopen) ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
              && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    // Manually resolve a single alert
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

    // Resolve all open alerts at once
    if ($_POST['action'] === 'resolve_all_alerts') {
        $pdo->prepare("
            UPDATE vet_alerts SET IsResolved = 1, ResolvedAt = NOW() WHERE IsResolved = 0
        ")->execute();
        if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success' => true]); exit; }
        header('Location: vet_dashboard.php'); exit;
    }
}

// ── Animal summary stats ──────────────────────────────────────────────────────
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

// ── Fetch alerts from vet_alerts ──────────────────────────────────────────────
// Open alerts (IsResolved = 0) first, then recently resolved, joined to animal for extra detail
$allAlerts = $pdo->query("
    SELECT
        va.AlertID,
        va.Animal_ID,
        va.AlertType,
        va.Message,
        va.CreatedAt,
        va.IsResolved,
        va.ResolvedAt,
        a.Name        AS AnimalName,
        a.Species     AS AnimalSpecies,
        a.Category    AS AnimalCategory,
        e.Enclosure_Name
    FROM vet_alerts va
    JOIN animal a ON va.Animal_ID = a.Animal_ID
    LEFT JOIN enclosure e ON a.Enclosure_ID = e.Enclosure_ID
    ORDER BY va.IsResolved ASC, va.CreatedAt DESC
    LIMIT 60
")->fetchAll(PDO::FETCH_ASSOC);

$openAlerts     = array_filter($allAlerts, fn($r) => !(int)$r['IsResolved']);
$openCount      = count($openAlerts);
$resolvedAlerts = array_filter($allAlerts, fn($r) =>  (int)$r['IsResolved']);
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
            box-sizing: border-box;
            width: 100%;
            min-height: 100vh;
            min-height: 100dvh;
            background-color: rgba(187, 223, 158, 0.95);
            text-align: left;
        }
        .dashboard-inner {
            box-sizing: border-box;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px clamp(12px, 2.4vw, 18px);
        }

        /* ── Header ──────────────────────────────────────────── */
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 20px;
            border-bottom: 3px solid var(--accent-color);
            padding-bottom: 14px;
            flex-wrap: wrap;
        }
        .dashboard-header h1 { margin: 0; font-size: clamp(1.35rem, 2.5vw, 1.75rem); font-weight: 800; color: var(--text-color); }
        .dashboard-header .dash-meta { margin: 6px 0 0; font-size: 0.9rem; color: #666; font-weight: 500; }
        .dashboard-header-actions { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
        .dashboard-header-actions .user-name { font-size: 0.9rem; font-weight: 600; color: var(--text-color); }
        .role-badge { background: var(--accent-color); color: var(--text-color); padding: 4px 14px; border-radius: 1000px; font-size: 0.8rem; font-weight: 700; text-transform: capitalize; }
        .secondary-nav-btn { padding: 9px 18px; background-color: var(--base-color); border: 2px solid var(--accent-color); border-radius: 1000px; font: inherit; font-weight: 600; font-size: 0.88rem; color: var(--text-color); text-decoration: none; }
        .secondary-nav-btn:hover { background-color: var(--accent-color); text-decoration: none; }
        .logout-btn { padding: 10px 22px; background-color: var(--accent-color); border: none; border-radius: 1000px; font: inherit; font-weight: 600; cursor: pointer; color: var(--text-color); text-decoration: none; display: inline-block; }
        .logout-btn:hover { background-color: var(--text-color); color: white; text-decoration: none; }

        /* ── Bell button ─────────────────────────────────────── */
        .bell-btn {
            position: relative;
            background: white;
            border: 2px solid var(--accent-color);
            border-radius: 1000px;
            padding: 8px 16px 8px 14px;
            font: inherit;
            font-size: 0.88rem;
            font-weight: 600;
            cursor: pointer;
            color: var(--text-color);
            display: flex;
            align-items: center;
            gap: 6px;
            transition: 150ms ease;
        }
        .bell-btn:hover { background: var(--accent-color); }
        .bell-badge { background: #e74c3c; color: white; font-size: 0.7rem; font-weight: 800; min-width: 18px; height: 18px; border-radius: 1000px; display: inline-flex; align-items: center; justify-content: center; padding: 0 4px; line-height: 1; }

        /* ── Slide-over panel ────────────────────────────────── */
        .notif-panel-backdrop { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.25); z-index: 999; }
        .notif-panel-backdrop.open { display: block; }
        .notif-panel {
            position: fixed; top: 0; right: -460px;
            width: min(460px, 100vw); height: 100vh;
            background: white;
            box-shadow: -6px 0 32px rgba(0,0,0,0.14);
            z-index: 1000;
            display: flex; flex-direction: column;
            transition: right 280ms cubic-bezier(.4,0,.2,1);
            overflow: hidden;
        }
        .notif-panel.open { right: 0; }
        .notif-panel-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 18px 20px;
            border-bottom: 2px solid #f0f0f0;
            background: var(--accent-color);
            flex-shrink: 0;
        }
        .notif-panel-header h2 { margin: 0; font-size: 1.05rem; font-weight: 800; color: var(--text-color); }
        .notif-panel-actions { display: flex; gap: 8px; align-items: center; }
        .btn-resolve-all { background: white; border: 2px solid var(--text-color); border-radius: 8px; padding: 5px 12px; font: inherit; font-size: 0.78rem; font-weight: 700; cursor: pointer; color: var(--text-color); }
        .btn-resolve-all:hover { background: var(--text-color); color: white; }
        .notif-close { background: none; border: none; font-size: 1.4rem; cursor: pointer; color: var(--text-color); line-height: 1; padding: 2px 6px; }

        /* panel tabs */
        .panel-tabs { display: flex; border-bottom: 2px solid #f0f0f0; flex-shrink: 0; }
        .panel-tab { flex: 1; padding: 11px 8px; background: none; border: none; font: inherit; font-size: 0.85rem; font-weight: 700; cursor: pointer; color: #888; border-bottom: 3px solid transparent; margin-bottom: -2px; transition: 150ms; }
        .panel-tab.active { color: var(--text-color); border-bottom-color: var(--accent-color); }
        .panel-tab-content { display: none; flex: 1; overflow-y: auto; padding: 12px; flex-direction: column; gap: 10px; }
        .panel-tab-content.active { display: flex; }

        /* alert items */
        .notif-empty { text-align: center; color: #aaa; padding: 60px 20px; font-size: 0.95rem; }
        .notif-item { border-radius: 10px; padding: 14px 16px; border: 2px solid #f0f0f0; background: #fff; position: relative; }
        .notif-item.open-alert { background: #fff8f8; border-color: #f5c6cb; }
        .notif-item.open-alert::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 4px; background: #e74c3c; border-radius: 10px 0 0 10px; }
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

        /* ── Stats ───────────────────────────────────────────── */
        .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; margin-bottom: 22px; }
        .stat-card { background: white; border-radius: 12px; padding: 18px; box-shadow: 0 3px 8px rgba(0,0,0,0.05); border-left: 4px solid var(--accent-color); }
        .stat-card.danger  { border-left-color: #e74c3c; }
        .stat-card.warning { border-left-color: #f39c12; }
        .stat-card .stat-label { font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.06em; color: #666; font-weight: 600; margin-bottom: 8px; }
        .stat-card .stat-value { font-size: 1.85rem; font-weight: 800; color: var(--text-color); line-height: 1.1; }
        .stat-card.danger  .stat-value { color: #e74c3c; }
        .stat-card.warning .stat-value { color: #f39c12; }

        /* ── Section & tiles ─────────────────────────────────── */
        .section-title { font-size: 1rem; margin: 22px 0 10px; border-bottom: 1px solid #e0e0e0; padding-bottom: 6px; color: var(--text-color); font-weight: 700; }
        .section-title:first-of-type { margin-top: 0; }
        .tiles-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px; margin-bottom: 22px; }
        .tile { background: white; border-radius: 12px; padding: 16px 18px; text-decoration: none; color: var(--text-color); box-shadow: 0 3px 8px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 14px; transition: transform 120ms ease, box-shadow 120ms ease; border: 2px solid transparent; }
        .tile:hover { transform: translateY(-2px); box-shadow: 0 6px 14px rgba(23,103,7,0.12); border-color: var(--accent-color); text-decoration: none; }
        .tile-text strong { display: block; font-size: 0.9rem; font-weight: 700; margin-bottom: 3px; }
        .tile-text span   { font-size: 0.8rem; color: #666; }
    </style>
</head>
<body>
<div class="dashboard-wrapper">
<div class="dashboard-inner">

    <!-- ── Header ────────────────────────────────────────────── -->
    <div class="dashboard-header">
        <div>
            <h1>Veterinarian dashboard</h1>
            <p class="dash-meta"><?= date('l, F j, Y') ?></p>
        </div>
        <div class="dashboard-header-actions">
            <span class="user-name"><?= htmlspecialchars($_SESSION['firstname']) ?></span>
            <span class="role-badge"><?= htmlspecialchars($_SESSION['role']) ?></span>

            <button class="bell-btn" onclick="openNotifPanel()">
                🔔 Alerts
                <?php if ($openCount > 0): ?>
                    <span class="bell-badge" id="bellCount"><?= $openCount ?></span>
                <?php endif; ?>
            </button>

            <?php if ($isAdmin): ?>
                <a href="dashboard.php" class="secondary-nav-btn">← Staff dashboard</a>
            <?php endif; ?>
            <a href="change-password.php" class="secondary-nav-btn">🔒 Change Password</a>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </div>

    <!-- ── Stats ─────────────────────────────────────────────── -->
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
        <div class="stat-card <?= $openCount > 0 ? 'danger' : '' ?>">
            <div class="stat-label">Open alerts</div>
            <div class="stat-value"><?= $openCount ?></div>
        </div>
    </div>

    <!-- ── Tiles ─────────────────────────────────────────────── -->
    <div class="section-title">Animals &amp; enclosures</div>
    <div class="tiles-grid">
        <a href="caretaker_dashboard.php#care-table" class="tile">
            <div class="tile-text"><strong>Health status updates</strong><span>Open the care board to set Healthy, Sick, or Pending.</span></div>
        </a>
        <a href="animals_report.php" class="tile">
            <div class="tile-text"><strong>Animals report</strong><span>Search and filter animals</span></div>
        </a>
        <a href="health-reports.php" class="tile">
            <div class="tile-text"><strong>Health records</strong><span>Medical history and status</span></div>
        </a>
    </div>

</div><!-- /.dashboard-inner -->
</div><!-- /.dashboard-wrapper -->


<!-- ── Slide-over alert panel ────────────────────────────────── -->
<div class="notif-panel-backdrop" id="notifBackdrop" onclick="closeNotifPanel()"></div>

<div class="notif-panel" id="notifPanel">
    <div class="notif-panel-header">
        <h2>🔔 Vet Alerts</h2>
        <div class="notif-panel-actions">
            <?php if ($openCount > 0): ?>
                <button class="btn-resolve-all" id="resolveAllBtn" onclick="resolveAll()">Resolve all</button>
            <?php endif; ?>
            <button class="notif-close" onclick="closeNotifPanel()">✕</button>
        </div>
    </div>

    <!-- Tabs: Open / Resolved -->
    <div class="panel-tabs">
        <button class="panel-tab active" onclick="switchTab('open', this)">
            Open <?php if ($openCount > 0): ?>(<?= $openCount ?>)<?php endif; ?>
        </button>
        <button class="panel-tab" onclick="switchTab('resolved', this)">
            Resolved (<?= count($resolvedAlerts) ?>)
        </button>
    </div>

    <!-- Open alerts tab -->
    <div class="panel-tab-content active" id="tab-open">
        <?php if (empty($openAlerts)): ?>
            <div class="notif-empty">✅ No open alerts.<br>All animals are healthy!</div>
        <?php else: ?>
            <?php foreach ($openAlerts as $alert): ?>
                <div class="notif-item open-alert" id="alert-<?= (int)$alert['AlertID'] ?>">
                    <div class="notif-animal danger">
                        🔴 <?= htmlspecialchars($alert['AnimalName']) ?>
                        <span style="font-weight:500;color:#555;">(<?= htmlspecialchars($alert['AnimalSpecies']) ?>)</span>
                    </div>
                    <?php if (!empty($alert['Enclosure_Name'])): ?>
                        <div class="notif-enclosure">📍 <?= htmlspecialchars($alert['Enclosure_Name']) ?></div>
                    <?php endif; ?>
                    <div class="notif-msg"><?= htmlspecialchars($alert['Message']) ?></div>
                    <div class="notif-footer">
                        <span class="notif-time">⏱ <?= human_time_diff($alert['CreatedAt']) ?></span>
                        <button class="btn-resolve" onclick="resolveAlert(<?= (int)$alert['AlertID'] ?>, this)">
                            ✓ Resolve
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Resolved alerts tab -->
    <div class="panel-tab-content" id="tab-resolved">
        <?php if (empty($resolvedAlerts)): ?>
            <div class="notif-empty">No resolved alerts yet.</div>
        <?php else: ?>
            <?php foreach ($resolvedAlerts as $alert): ?>
                <div class="notif-item resolved-alert">
                    <div class="notif-animal muted">
                        ✅ <?= htmlspecialchars($alert['AnimalName']) ?>
                        <span style="font-weight:500;">(<?= htmlspecialchars($alert['AnimalSpecies']) ?>)</span>
                    </div>
                    <?php if (!empty($alert['Enclosure_Name'])): ?>
                        <div class="notif-enclosure">📍 <?= htmlspecialchars($alert['Enclosure_Name']) ?></div>
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
function openNotifPanel()  { document.getElementById('notifPanel').classList.add('open');    document.getElementById('notifBackdrop').classList.add('open'); }
function closeNotifPanel() { document.getElementById('notifPanel').classList.remove('open'); document.getElementById('notifBackdrop').classList.remove('open'); }

function switchTab(name, btn) {
    document.querySelectorAll('.panel-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.panel-tab-content').forEach(t => t.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('tab-' + name).classList.add('active');
}

// Resolve a single alert via AJAX
function resolveAlert(alertId, btn) {
    fetch('vet_dashboard.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
        body: `action=resolve_alert&alert_id=${alertId}`
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) return;
        const item = document.getElementById(`alert-${alertId}`);
        if (item) item.remove();
        decrementBadge();
        // If no more open alerts, show empty state
        const openTab = document.getElementById('tab-open');
        if (!openTab.querySelector('.notif-item')) {
            openTab.innerHTML = '<div class="notif-empty">✅ No open alerts.<br>All animals are healthy!</div>';
            const resolveAllBtn = document.getElementById('resolveAllBtn');
            if (resolveAllBtn) resolveAllBtn.remove();
        }
    });
}

// Resolve all open alerts via AJAX
function resolveAll() {
    fetch('vet_dashboard.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
        body: 'action=resolve_all_alerts'
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) return;
        document.querySelectorAll('#tab-open .notif-item').forEach(el => el.remove());
        document.getElementById('tab-open').innerHTML =
            '<div class="notif-empty">✅ No open alerts.<br>All animals are healthy!</div>';
        const badge = document.getElementById('bellCount');
        if (badge) badge.remove();
        const resolveAllBtn = document.getElementById('resolveAllBtn');
        if (resolveAllBtn) resolveAllBtn.remove();
    });
}

function decrementBadge() {
    const badge = document.getElementById('bellCount');
    if (!badge) return;
    const current = parseInt(badge.textContent, 10);
    if (current <= 1) badge.remove();
    else badge.textContent = current - 1;
}
</script>

</body>
</html>

<?php
function human_time_diff(string $dateStr): string {
    $diff = time() - strtotime($dateStr);
    if ($diff < 60)     return 'Just now';
    if ($diff < 3600)   return floor($diff / 60) . 'm ago';
    if ($diff < 86400)  return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M j, Y', strtotime($dateStr));
}
?>
