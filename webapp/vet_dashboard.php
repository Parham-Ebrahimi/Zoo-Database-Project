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

// ── Handle POST actions ───────────────────────────────────────────────────────
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

// ── Initial alert load (open only — resolved tab loads on demand) ─────────────
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

        /* ── Header ──────────────────────────────────────────── */
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

        /* ── Bell button ─────────────────────────────────────── */
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
        /* pulse animation when a NEW alert arrives */
        .bell-badge.pulse { animation: badgePop 400ms cubic-bezier(.34,1.56,.64,1); }
        @keyframes badgePop { 0%{transform:scale(1)} 50%{transform:scale(1.5)} 100%{transform:scale(1)} }

        /* ── Live indicator dot ──────────────────────────────── */
        .live-dot {
            display: inline-block; width: 8px; height: 8px; border-radius: 50%;
            background: #2ecc71; margin-left: 2px;
            animation: livePulse 2s ease-in-out infinite;
        }
        @keyframes livePulse {
            0%, 100% { opacity: 1;   transform: scale(1); }
            50%       { opacity: 0.4; transform: scale(0.8); }
        }

        /* ── Toast notification ──────────────────────────────── */
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

        /* ── Slide-over panel ────────────────────────────────── */
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
        /* slide-in animation for newly polled alerts */
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

            <!-- Bell button — badge and count updated by JS poller -->
            <button class="bell-btn" id="bellBtn" onclick="openNotifPanel()">
                🔔 Alerts
                <span class="bell-badge" id="bellCount"
                      style="<?= $openCount === 0 ? 'display:none' : '' ?>">
                    <?= $openCount ?>
                </span>
                <span class="live-dot" title="Live updates active"></span>
            </button>

            <?php if ($isAdmin): ?>
                <a href="dashboard.php" class="secondary-nav-btn">← Staff dashboard</a>
            <?php endif; ?>
            <a href="change-password.php" class="secondary-nav-btn">🔒 Change Password</a>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </div>

    <!-- ── Stats (open alert count updated live by JS) ───────── -->
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

<!-- ── Toast container ───────────────────────────────────────── -->
<div id="toastContainer"></div>

<!-- ── Slide-over alert panel ────────────────────────────────── -->
<div class="notif-panel-backdrop" id="notifBackdrop" onclick="closeNotifPanel()"></div>

<div class="notif-panel" id="notifPanel">
    <div class="notif-panel-header">
        <h2>🔔 Vet Alerts</h2>
        <div class="notif-panel-actions">
            <button class="btn-resolve-all" id="resolveAllBtn"
                    style="<?= $openCount === 0 ? 'display:none' : '' ?>"
                    onclick="resolveAll()">Resolve all</button>
            <button class="notif-close" onclick="closeNotifPanel()">✕</button>
        </div>
    </div>

    <!-- Tabs -->
    <div class="panel-tabs">
        <button class="panel-tab active" id="tabBtnOpen"     onclick="switchTab('open', this)">
            Open (<span id="openTabCount"><?= $openCount ?></span>)
        </button>
        <button class="panel-tab"        id="tabBtnResolved" onclick="switchTab('resolved', this)">
            Resolved (<?= count($resolvedAlerts) ?>)
        </button>
    </div>

    <!-- Open alerts -->
    <div class="panel-tab-content active" id="tab-open">
        <?php if (empty($openAlerts)): ?>
            <div class="notif-empty" id="openEmptyState">✅ No open alerts.<br>All animals are healthy!</div>
        <?php else: ?>
            <?php foreach ($openAlerts as $alert): ?>
                <?= renderAlertItem($alert) ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Resolved alerts -->
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
// ── State tracked by the poller ───────────────────────────────
// Store alert IDs already visible so we can detect truly new ones
const knownAlertIds = new Set([
    <?php echo implode(',', array_column($openAlerts, 'AlertID')); ?>
]);

let pollInterval = null;
const POLL_EVERY_MS = 7000; // poll every 7 seconds

// ── Panel open/close ──────────────────────────────────────────
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
        ? `<div class="notif-enclosure">📍 ${escHtml(alert.enclosure)}</div>` : '';
    return `
        <div class="notif-item open-alert${isNew ? ' new-alert' : ''}" id="alert-${alert.alertId}">
            <div class="notif-animal danger">
                🔴 ${escHtml(alert.animalName)}
                <span style="font-weight:500;color:#555;">(${escHtml(alert.animalSpecies)})</span>
            </div>
            ${enclosure}
            <div class="notif-msg">${escHtml(alert.message)}</div>
            <div class="notif-footer">
                <span class="notif-time" data-created="${escHtml(alert.createdAt)}">⏱ ${escHtml(alert.timeAgo)}</span>
                <button class="btn-resolve" onclick="resolveAlert(${alert.alertId}, this)">✓ Resolve</button>
            </div>
        </div>`;
}

function escHtml(str) {
    return String(str)
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}


async function pollAlerts() {
    try {
        const res  = await fetch('vet_alerts_real_time.php', { cache: 'no-store' });
        if (!res.ok) return; // silently ignore network hiccups
        const data = await res.json();

        const freshIds   = new Set(data.alerts.map(a => a.alertId));
        const newAlerts  = data.alerts.filter(a => !knownAlertIds.has(a.alertId));
        const goneIds    = [...knownAlertIds].filter(id => !freshIds.has(id));

        // ── Remove alerts that were resolved elsewhere (e.g. another vet) ──
        goneIds.forEach(id => {
            knownAlertIds.delete(id);
            const el = document.getElementById(`alert-${id}`);
            if (el) el.remove();
        });

        // ── Inject new alerts at the top of the open tab ─────────────────
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

        // ── Sync open count everywhere ────────────────────────────────────
        updateOpenCount(data.openCount);

    } catch (e) {
        // Network error — just wait for the next poll
    }
}

function updateOpenCount(count) {
    // Bell badge
    const badge = document.getElementById('bellCount');
    const prev  = parseInt(badge.textContent || '0', 10);
    badge.textContent = count;
    badge.style.display = count > 0 ? 'inline-flex' : 'none';
    if (count > prev) {
        badge.classList.remove('pulse');
        void badge.offsetWidth; // reflow to restart animation
        badge.classList.add('pulse');
    }

    // Stat card
    document.getElementById('openAlertCount').textContent = count;
    const card = document.getElementById('openAlertCard');
    card.classList.toggle('danger', count > 0);

    // Tab label
    document.getElementById('openTabCount').textContent = count;

    // "Resolve all" button
    document.getElementById('resolveAllBtn').style.display = count > 0 ? '' : 'none';

    // If open tab is now empty, show the empty state
    const tab = document.getElementById('tab-open');
    if (count === 0 && !tab.querySelector('.notif-item')) {
        if (!document.getElementById('openEmptyState')) {
            tab.innerHTML = '<div class="notif-empty" id="openEmptyState">✅ No open alerts.<br>All animals are healthy!</div>';
        }
    }
}

// ── Toast pop-up for new alerts ───────────────────────────────
function showToast(alert) {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = 'toast danger';
    toast.innerHTML = `
        <span class="toast-icon">🔴</span>
        <div class="toast-body">
            <div class="toast-title">New sick animal alert</div>
            <strong>${escHtml(alert.animalName)}</strong> (${escHtml(alert.animalSpecies)})
            ${alert.enclosure ? ' · ' + escHtml(alert.enclosure) : ''}
        </div>`;
    container.appendChild(toast);

    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        toast.style.animation = 'toastOut 300ms ease forwards';
        toast.addEventListener('animationend', () => toast.remove());
    }, 5000);
}

// ── Resolve a single alert ────────────────────────────────────
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

// ── Resolve all open alerts ───────────────────────────────────
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

// ── Refresh relative timestamps every minute ──────────────────
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

// ── Start polling on page load ────────────────────────────────
pollAlerts(); // immediate first check
pollInterval = setInterval(pollAlerts, POLL_EVERY_MS);
setInterval(refreshTimestamps, 60000); // refresh "X ago" labels every minute
</script>

</body>
</html>

<?php

function renderAlertItem(array $alert): string {
    $id        = (int)$alert['AlertID'];
    $name      = htmlspecialchars($alert['AnimalName']);
    $species   = htmlspecialchars($alert['AnimalSpecies']);
    $enclosure = !empty($alert['Enclosure_Name'])
        ? '<div class="notif-enclosure">📍 ' . htmlspecialchars($alert['Enclosure_Name']) . '</div>'
        : '';
    $msg  = htmlspecialchars($alert['Message']);
    $time = human_time_diff($alert['CreatedAt']);
    $created = htmlspecialchars($alert['CreatedAt']);
    return "
        <div class=\"notif-item open-alert\" id=\"alert-{$id}\">
            <div class=\"notif-animal danger\">
                🔴 {$name}
                <span style=\"font-weight:500;color:#555;\">({$species})</span>
            </div>
            {$enclosure}
            <div class=\"notif-msg\">{$msg}</div>
            <div class=\"notif-footer\">
                <span class=\"notif-time\" data-created=\"{$created}\">⏱ {$time}</span>
                <button class=\"btn-resolve\" onclick=\"resolveAlert({$id}, this)\">✓ Resolve</button>
            </div>
        </div>";
}
?>