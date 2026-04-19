<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit;
}
require_once 'staff_home.php';
$roleGate = strtolower(trim((string) ($_SESSION['role'] ?? '')));
if (!in_array($roleGate, ['admin', 'vet', 'caretaker', 'keeper'], true) && !staff_is_vet_role()) {
    header('Location: dashboard.php');
    exit;
}

require_once 'db.php';

// Filters
$healthFilter = $_GET['health']     ?? 'all';
$search       = trim($_GET['search'] ?? '');
$animalId     = (int)($_GET['animal_id'] ?? 0);
$f_enclosure  = $_GET['enclosure']  ?? '';
$f_category   = $_GET['category']   ?? '';
$f_species    = $_GET['species']    ?? '';
$f_caretaker  = $_GET['caretaker']  ?? '';
$f_vet        = $_GET['vet']        ?? '';
$f_date_from  = $_GET['date_from']  ?? '';
$f_date_to    = $_GET['date_to']    ?? '';
$f_food_max   = $_GET['food_max']   ?? '';
$f_sort       = $_GET['sort']       ?? 'status';
$f_dir        = $_GET['dir']        ?? 'ASC';

$allowedHealth = ['all', 'Sick', 'Pending', 'Healthy'];
if (!in_array($healthFilter, $allowedHealth, true)) $healthFilter = 'all';

// Filter options
$enclosures = $pdo->query("SELECT Enclosure_ID, Enclosure_Name FROM enclosure ORDER BY Enclosure_Name")->fetchAll();
$categories = $pdo->query("SELECT DISTINCT Category FROM animal WHERE Category IS NOT NULL ORDER BY Category")->fetchAll(PDO::FETCH_COLUMN);
$species    = $pdo->query("SELECT DISTINCT Species FROM animal WHERE Species IS NOT NULL ORDER BY Species")->fetchAll(PDO::FETCH_COLUMN);
$caretakers = $pdo->query("
    SELECT e.EmployeeID, CONCAT(e.FirstName,' ',e.LastName) AS FullName
    FROM employees e WHERE LOWER(TRIM(e.Role)) IN ('caretaker','keeper') ORDER BY e.FirstName
")->fetchAll();
$vets = $pdo->query("
    SELECT e.EmployeeID, CONCAT(e.FirstName,' ',e.LastName) AS FullName
    FROM employees e WHERE LOWER(TRIM(e.Role)) IN ('vet','veterinarian') ORDER BY e.FirstName
")->fetchAll();

// Build query
$where  = ['1=1'];
$params = [];

if ($healthFilter !== 'all') { $where[] = "COALESCE(a.Health_Status,'Pending') = ?"; $params[] = $healthFilter; }
if ($search)       { $where[] = "(a.Name LIKE ? OR a.Species LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($animalId > 0) { $where[] = "a.Animal_ID = ?"; $params[] = $animalId; }
if ($f_enclosure)  { $where[] = "a.Enclosure_ID = ?"; $params[] = (int)$f_enclosure; }
if ($f_category)   { $where[] = "a.Category = ?";     $params[] = $f_category; }
if ($f_species)    { $where[] = "a.Species = ?";       $params[] = $f_species; }
if ($f_caretaker)  { $where[] = "a.Caretaker_EmployeeID = ?"; $params[] = (int)$f_caretaker; }
if ($f_vet)        { $where[] = "hr.Veterinarian_ID = ?";      $params[] = (int)$f_vet; }
if ($f_food_max !== '') { $where[] = "COALESCE(a.food_stock,50) <= ?"; $params[] = (int)$f_food_max; }
if ($f_date_from)  { $where[] = "hr.Record_Date >= ?"; $params[] = $f_date_from; }
if ($f_date_to)    { $where[] = "hr.Record_Date <= ?"; $params[] = $f_date_to; }

$sortMap = [
    'status'   => "FIELD(COALESCE(a.Health_Status,'Pending'),'Sick','Pending','Healthy')",
    'name'     => 'a.Name',
    'species'  => 'a.Species',
    'enclosure'=> 'e.Enclosure_Name',
    'checkup'  => 'hr.Record_Date',
    'food'     => 'a.food_stock',
];
$orderBy = ($sortMap[$f_sort] ?? $sortMap['status']) . ' ' . ($f_dir === 'DESC' ? 'DESC' : 'ASC');

$sql = "
    SELECT a.Animal_ID, a.Name, a.Species, a.Category, a.Age, a.Sex,
           COALESCE(a.Health_Status,'Pending') AS Health_Status,
           COALESCE(a.food_stock,50) AS food_stock,
           e.Enclosure_Name,
           CONCAT(ck.FirstName,' ',ck.LastName) AS CaretakerName,
           hr.Diagnosis, hr.Treatment, hr.Notes,
           hr.Record_Date AS LastCheckup,
           hr.Cured_Date,
           CONCAT(vet.FirstName,' ',vet.LastName) AS VetName,
           (SELECT COUNT(*) FROM health_record WHERE Animal_ID = a.Animal_ID) AS TotalRecords,
           (SELECT COUNT(*) FROM health_record WHERE Animal_ID = a.Animal_ID AND Health_Status = 'Sick') AS SickCount
    FROM animal a
    LEFT JOIN enclosure e ON a.Enclosure_ID = e.Enclosure_ID
    LEFT JOIN employees ck ON a.Caretaker_EmployeeID = ck.EmployeeID
    LEFT JOIN (
        SELECT hr1.* FROM health_record hr1
        INNER JOIN (
            SELECT Animal_ID, MAX(Record_Date) AS MaxDate
            FROM health_record GROUP BY Animal_ID
        ) latest ON hr1.Animal_ID = latest.Animal_ID AND hr1.Record_Date = latest.MaxDate
    ) hr ON hr.Animal_ID = a.Animal_ID
    LEFT JOIN employees vet ON hr.Veterinarian_ID = vet.EmployeeID
    WHERE " . implode(' AND ', $where) . "
    ORDER BY $orderBy
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$animals = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Stats
$total        = count($animals);
$sick         = count(array_filter($animals, fn($a) => $a['Health_Status'] === 'Sick'));
$pending      = count(array_filter($animals, fn($a) => $a['Health_Status'] === 'Pending'));
$healthy      = count(array_filter($animals, fn($a) => $a['Health_Status'] === 'Healthy'));
$lowFood      = count(array_filter($animals, fn($a) => (int)$a['food_stock'] <= 20));
$noCheckup    = count(array_filter($animals, fn($a) => empty($a['LastCheckup'])));
$recentlySick = count(array_filter($animals, fn($a) => (int)$a['SickCount'] > 0));

// Health by enclosure for chart
$byEnclosure = [];
foreach ($animals as $a) {
    $enc = $a['Enclosure_Name'] ?? 'Unassigned';
    if (!isset($byEnclosure[$enc])) $byEnclosure[$enc] = ['Healthy'=>0,'Sick'=>0,'Pending'=>0];
    $byEnclosure[$enc][$a['Health_Status']] = ($byEnclosure[$enc][$a['Health_Status']] ?? 0) + 1;
}

// Health by category
$byCategory = [];
foreach ($animals as $a) {
    $cat = $a['Category'] ?? 'Unknown';
    if (!isset($byCategory[$cat])) $byCategory[$cat] = ['Healthy'=>0,'Sick'=>0,'Pending'=>0];
    $byCategory[$cat][$a['Health_Status']] = ($byCategory[$cat][$a['Health_Status']] ?? 0) + 1;
}

// Cumulative animal health status over last 30 days
// For each day, count how many animals had each status as their most recent record on or before that day
$cumulativeStmt = $pdo->query("
    SELECT d.Day,
        SUM(CASE WHEN latest_status.Health_Status = 'Healthy' THEN 1 ELSE 0 END) AS Healthy,
        SUM(CASE WHEN latest_status.Health_Status = 'Sick'    THEN 1 ELSE 0 END) AS Sick,
        SUM(CASE WHEN latest_status.Health_Status = 'Pending' THEN 1 ELSE 0 END) AS Pending
    FROM (
        SELECT DATE(d2) AS Day FROM (
            SELECT CURDATE() - INTERVAL seq DAY AS d2
            FROM (
                SELECT 0 AS seq UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4
                UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9
                UNION SELECT 10 UNION SELECT 11 UNION SELECT 12 UNION SELECT 13 UNION SELECT 14
                UNION SELECT 15 UNION SELECT 16 UNION SELECT 17 UNION SELECT 18 UNION SELECT 19
                UNION SELECT 20 UNION SELECT 21 UNION SELECT 22 UNION SELECT 23 UNION SELECT 24
                UNION SELECT 25 UNION SELECT 26 UNION SELECT 27 UNION SELECT 28 UNION SELECT 29
            ) seq_tbl
        ) dates
    ) d
    LEFT JOIN (
        SELECT hr.Animal_ID, DATE(hr.Record_Date) AS RecordDay, hr.Health_Status,
               ROW_NUMBER() OVER (PARTITION BY hr.Animal_ID, DATE(hr.Record_Date) ORDER BY hr.Record_Date DESC) AS rn
        FROM health_record hr
    ) latest_status ON latest_status.RecordDay <= d.Day AND latest_status.rn = 1
    GROUP BY d.Day
    ORDER BY d.Day ASC
");
$cumulativeRows = $cumulativeStmt->fetchAll(PDO::FETCH_ASSOC);

$dashHref = staff_home_href();
$hasFilters = $healthFilter !== 'all' || $search || $animalId || $f_enclosure || $f_category || $f_species || $f_caretaker || $f_vet || $f_food_max !== '' || $f_date_from || $f_date_to;

function sLink(string $col, string $label, string $cur, string $dir): string {
    $p = $_GET; $p['sort'] = $col; $p['dir'] = ($cur === $col && $dir === 'ASC') ? 'DESC' : 'ASC';
    $arrow = ($cur === $col) ? ($dir === 'ASC' ? ' ↑' : ' ↓') : '';
    return '<a href="?'.http_build_query($p).'" style="color:white;text-decoration:none">'.htmlspecialchars($label).$arrow.'</a>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Health Reports — Greenwood Zoo</title>
<link rel="stylesheet" href="style.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<style>
body { overflow: auto; }
.dashboard-wrapper { box-sizing:border-box; min-height:100vh; padding:20px clamp(12px,2.4vw,18px); background-color:rgba(187,223,158,0.95); }
.dashboard-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; border-bottom:3px solid var(--accent-color); padding-bottom:12px; flex-wrap:wrap; gap:12px; }
.dashboard-header h1 { font-size:1.5rem; margin:0; font-weight:800; color:var(--text-color); }
.logout-btn { padding:10px 22px; background:var(--accent-color); border:none; border-radius:1000px; font:inherit; font-weight:600; cursor:pointer; color:var(--text-color); text-decoration:none; }
.logout-btn:hover { background:var(--text-color); color:white; }
.back-btn { display:inline-block; margin-bottom:14px; padding:7px 14px; background:var(--base-color); border-radius:8px; color:var(--text-color); font-weight:600; text-decoration:none; font-size:.88rem; }
.back-btn:hover { background:var(--accent-color); }

/* KPI cards */
.kpi-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(130px,1fr)); gap:12px; margin-bottom:18px; }
.kpi { background:white; border-radius:12px; padding:14px 16px; box-shadow:0 2px 8px rgba(0,0,0,.07); border-left:4px solid var(--accent-color); }
.kpi.red   { border-color:#e74c3c; }
.kpi.orange{ border-color:#f39c12; }
.kpi.blue  { border-color:#2980b9; }
.kpi.grey  { border-color:#aaa; }
.kpi .k-label { font-size:.7rem; font-weight:600; text-transform:uppercase; letter-spacing:.05em; color:#888; margin-bottom:5px; }
.kpi .k-val   { font-size:1.6rem; font-weight:900; color:var(--text-color); line-height:1; }
.kpi.red   .k-val { color:#e74c3c; }
.kpi.orange .k-val{ color:#f39c12; }
.kpi .k-sub   { font-size:.72rem; color:#aaa; margin-top:3px; }

/* Filter card */
.filter-card { background:white; border-radius:12px; padding:12px 14px; margin-bottom:14px; box-shadow:0 3px 8px rgba(0,0,0,.05); }
.filter-card h2 { font-size:.95rem; font-weight:700; margin-bottom:10px; color:var(--text-color); }
.filter-card label { background:none; color:var(--text-color); font-size:.85rem; font-weight:600; height:auto; width:auto; border-radius:0; display:block; text-align:left; padding:0; }
.filter-card form { width:100%; margin:0; display:block; }
.filter-card form > div { width:auto; display:block; }
.filter-group { display:flex; flex-direction:column; gap:4px; }
.filter-group label { font-size:.85rem; font-weight:600; color:var(--text-color); }
.filter-group input, .filter-group select { padding:6px 10px; border:2px solid #ddd; border-radius:8px; font:inherit; background:white; }
.filter-group input:focus, .filter-group select:focus { outline:none; border-color:var(--accent-color); }
.filter-actions { display:flex; flex-wrap:wrap; gap:8px; align-items:flex-end; }
.btn { padding:6px 14px; border-radius:8px; text-decoration:none; font-weight:600; font-size:.85rem; border:none; cursor:pointer; font:inherit; display:inline-block; }
.btn-edit { background:var(--accent-color); color:white; }
.btn-edit:hover { background:var(--text-color); }

/* Charts */
.chart-grid { display:grid; grid-template-columns:1fr 1fr 1fr; gap:14px; margin-bottom:18px; }
@media(max-width:900px) { .chart-grid { grid-template-columns:1fr; } }
.cc { background:white; border-radius:12px; padding:16px 18px; box-shadow:0 2px 8px rgba(0,0,0,.07); }
.cc h3 { font-size:.78rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:#888; margin:0 0 12px; }
.cw { position:relative; height:200px; }

/* Table */
.tw { background:white; border-radius:12px; overflow:hidden; box-shadow:0 4px 14px rgba(0,0,0,.08); overflow-x:auto; }
table { width:100%; border-collapse:collapse; min-width:700px; }
th { background:var(--accent-color); color:white; padding:10px 12px; text-align:left; font-size:.78rem; text-transform:uppercase; letter-spacing:.04em; white-space:nowrap; }
td { padding:9px 12px; border-bottom:1px solid #f0f0f0; font-size:.85rem; vertical-align:middle; }
tr:last-child td { border-bottom:none; }
tbody tr:hover td { background:rgba(187,223,158,.15); }

.badge { display:inline-block; padding:2px 10px; border-radius:999px; font-size:.72rem; font-weight:700; text-transform:uppercase; }
.badge-healthy { background:#d4edda; color:#155724; }
.badge-sick    { background:#f8d7da; color:#721c24; }
.badge-pending { background:#fff3cd; color:#856505; }

.food-bar-wrap { display:flex; align-items:center; gap:6px; min-width:80px; }
.food-bar { flex:1; height:7px; border-radius:3px; background:#eee; overflow:hidden; }
.food-fill { height:100%; border-radius:3px; }
.food-fill.high { background:#2ecc71; }
.food-fill.medium { background:#f39c12; }
.food-fill.low { background:#e74c3c; }

/* Expandable detail */
.expand-btn { background:var(--base-color); border:none; cursor:pointer; font-size:.78rem; font-weight:600; color:var(--text-color); padding:3px 8px; border-radius:6px; }
.expand-btn:hover { background:var(--accent-color); color:white; }
.detail-row { display:none; }
.detail-row.open { display:table-row; }
.detail-cell { background:#f8fff8 !important; padding:12px 14px !important; border-bottom:2px solid var(--accent-color) !important; }
.detail-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(170px,1fr)); gap:8px; }
.detail-item { font-size:.83rem; }
.detail-item strong { display:block; font-size:.7rem; text-transform:uppercase; letter-spacing:.05em; color:#888; margin-bottom:2px; }

.result-meta { font-size:.85rem; color:#666; font-weight:600; margin-bottom:12px; }
.no-data { padding:30px; text-align:center; color:#aaa; font-style:italic; }
.alert-banner { padding:12px 16px; border-radius:10px; margin-bottom:14px; font-weight:600; font-size:.9rem; }
.alert-red    { background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; }
.alert-orange { background:#fff3cd; color:#856505; border:1px solid #ffc107; }
</style>
</head>
<body>
<div class="dashboard-wrapper">

<div class="dashboard-header">
    <h1>Health Reports</h1>
    <a href="logout.php" class="logout-btn">Logout</a>
</div>

<a href="<?= htmlspecialchars($dashHref) ?>" class="back-btn">← Back to dashboard</a>

<?php if ($sick > 0): ?>
<div class="alert-banner alert-red">🚨 <?= $sick ?> animal<?= $sick>1?'s are':' is'?> currently marked <strong>Sick</strong> — immediate attention may be required.</div>
<?php endif; ?>
<?php if ($pending > 0): ?>
<div class="alert-banner alert-orange">⏳ <?= $pending ?> animal<?= $pending>1?'s':''?> <?= $pending>1?'are':'is'?> <strong>Pending</strong> review.</div>
<?php endif; ?>

<!-- KPI Cards -->
<div class="kpi-grid">
    <div class="kpi">
        <div class="k-label">Total animals</div>
        <div class="k-val"><?= $total ?></div>
        <div class="k-sub"><?= $hasFilters ? 'filtered' : 'all animals' ?></div>
    </div>
    <div class="kpi red">
        <div class="k-label">Sick</div>
        <div class="k-val"><?= $sick ?></div>
        <div class="k-sub"><?= $total > 0 ? round($sick/$total*100,1) : 0 ?>% of total</div>
    </div>
    <div class="kpi orange">
        <div class="k-label">Pending</div>
        <div class="k-val"><?= $pending ?></div>
        <div class="k-sub">awaiting review</div>
    </div>
    <div class="kpi">
        <div class="k-label">Healthy</div>
        <div class="k-val"><?= $healthy ?></div>
        <div class="k-sub"><?= $total > 0 ? round($healthy/$total*100,1) : 0 ?>% of total</div>
    </div>
    <div class="kpi orange">
        <div class="k-label">Low food (≤20%)</div>
        <div class="k-val"><?= $lowFood ?></div>
        <div class="k-sub">need restocking</div>
    </div>
    <div class="kpi grey">
        <div class="k-label">No checkup yet</div>
        <div class="k-val"><?= $noCheckup ?></div>
        <div class="k-sub">never examined</div>
    </div>
    <div class="kpi blue">
        <div class="k-label">Have sick history</div>
        <div class="k-val"><?= $recentlySick ?></div>
        <div class="k-sub">were sick at some point</div>
    </div>
</div>

<!-- Charts -->
<div class="chart-grid">
    <div class="cc">
        <h3>Overall health breakdown</h3>
        <div class="cw"><canvas id="overallChart"></canvas></div>
    </div>
    <div class="cc">
        <h3>Health by enclosure</h3>
        <div class="cw"><canvas id="enclosureChart"></canvas></div>
    </div>
    <div class="cc">
        <h3>Animal health status over time (30 days)</h3>
        <div class="cw"><canvas id="timelineChart"></canvas></div>
    </div>
</div>

<!-- Filters -->
<div class="filter-card">
    <h2>Filter animals</h2>
    <form method="GET">
        <div class="filter-grid">
            <div class="filter-group">
                <label>Health status</label>
                <select name="health">
                    <option value="all"     <?= $healthFilter==='all'    ?'selected':'' ?>>All statuses</option>
                    <option value="Sick"    <?= $healthFilter==='Sick'   ?'selected':'' ?>>Sick only</option>
                    <option value="Pending" <?= $healthFilter==='Pending'?'selected':'' ?>>Pending only</option>
                    <option value="Healthy" <?= $healthFilter==='Healthy'?'selected':'' ?>>Healthy only</option>
                </select>
            </div>
            <div class="filter-group filter-group--wide">
                <label>Search name / species</label>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="e.g. Kowalski, Penguin">
            </div>
            <div class="filter-group">
                <label>Enclosure</label>
                <select name="enclosure">
                    <option value="">All enclosures</option>
                    <?php foreach ($enclosures as $enc): ?>
                    <option value="<?= $enc['Enclosure_ID'] ?>" <?= (int)$f_enclosure===$enc['Enclosure_ID']?'selected':'' ?>><?= htmlspecialchars($enc['Enclosure_Name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Category</label>
                <select name="category">
                    <option value="">All categories</option>
                    <?php foreach ($categories as $c): ?>
                    <option value="<?= htmlspecialchars($c) ?>" <?= $f_category===$c?'selected':'' ?>><?= htmlspecialchars($c) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Species</label>
                <select name="species">
                    <option value="">All species</option>
                    <?php foreach ($species as $s): ?>
                    <option value="<?= htmlspecialchars($s) ?>" <?= $f_species===$s?'selected':'' ?>><?= htmlspecialchars($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Assigned caretaker</label>
                <select name="caretaker">
                    <option value="">Any caretaker</option>
                    <?php foreach ($caretakers as $c): ?>
                    <option value="<?= $c['EmployeeID'] ?>" <?= (int)$f_caretaker===$c['EmployeeID']?'selected':'' ?>><?= htmlspecialchars($c['FullName']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Vet</label>
                <select name="vet">
                    <option value="">Any vet</option>
                    <?php foreach ($vets as $v): ?>
                    <option value="<?= $v['EmployeeID'] ?>" <?= (int)$f_vet===$v['EmployeeID']?'selected':'' ?>><?= htmlspecialchars($v['FullName']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Last checkup from</label>
                <input type="date" name="date_from" value="<?= htmlspecialchars($f_date_from) ?>">
            </div>
            <div class="filter-group">
                <label>Last checkup to</label>
                <input type="date" name="date_to" value="<?= htmlspecialchars($f_date_to) ?>">
            </div>
            <div class="filter-group">
                <label>Food stock max (%)</label>
                <input type="number" name="food_max" min="0" max="100" value="<?= htmlspecialchars($f_food_max) ?>" placeholder="e.g. 20 for low stock">
            </div>
            <div class="filter-group">
                <label>Animal ID</label>
                <input type="number" name="animal_id" value="<?= $animalId > 0 ? $animalId : '' ?>" min="1" placeholder="Optional">
            </div>
            <div class="filter-group">
                <label>Sort by</label>
                <select name="sort">
                    <option value="status"   <?= $f_sort==='status'   ?'selected':'' ?>>Health status</option>
                    <option value="name"     <?= $f_sort==='name'     ?'selected':'' ?>>Name</option>
                    <option value="species"  <?= $f_sort==='species'  ?'selected':'' ?>>Species</option>
                    <option value="enclosure"<?= $f_sort==='enclosure'?'selected':'' ?>>Enclosure</option>
                    <option value="checkup"  <?= $f_sort==='checkup'  ?'selected':'' ?>>Last checkup</option>
                    <option value="food"     <?= $f_sort==='food'     ?'selected':'' ?>>Food stock</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Direction</label>
                <select name="dir">
                    <option value="ASC"  <?= $f_dir==='ASC' ?'selected':'' ?>>A→Z / Low→High</option>
                    <option value="DESC" <?= $f_dir==='DESC'?'selected':'' ?>>Z→A / High→Low</option>
                </select>
            </div>
            <div class="filter-actions">
                <button type="submit" class="btn btn-edit">Search</button>
                <a href="health-reports.php" class="btn">Reset</a>
            </div>
        </div>
    </form>
</div>

<p class="result-meta"><?= $total ?> animal<?= $total!==1?'s':''?> shown <?= $hasFilters ? '<span style="color:var(--accent-color)">(filtered)</span>' : '' ?> — <?= $sick ?> sick, <?= $pending ?> pending, <?= $healthy ?> healthy</p>

<!-- Table -->
<div class="tw">
<?php if (empty($animals)): ?>
    <p class="no-data">No animals match the selected filters.</p>
<?php else: ?>
<table>
    <thead>
        <tr>
            <th>ID</th>
            <th><?= sLink('name','Name',$f_sort,$f_dir) ?></th>
            <th><?= sLink('species','Species',$f_sort,$f_dir) ?></th>
            <th>Cat.</th>
            <th><?= sLink('status','Health',$f_sort,$f_dir) ?></th>
            <th><?= sLink('enclosure','Enclosure',$f_sort,$f_dir) ?></th>
            <th>Caretaker</th>
            <th>Vet</th>
            <th><?= sLink('food','Food',$f_sort,$f_dir) ?></th>
            <th><?= sLink('checkup','Last checkup',$f_sort,$f_dir) ?></th>
            <th>Records</th>
            <th>Detail</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($animals as $a):
        $stock = (int)$a['food_stock'];
        $bc = $stock > 40 ? 'high' : ($stock > 10 ? 'medium' : 'low');
        $hs = strtolower($a['Health_Status']);
    ?>
    <tr>
        <td style="color:#aaa"><?= (int)$a['Animal_ID'] ?></td>
        <td><strong><?= htmlspecialchars($a['Name'] ?? '') ?></strong></td>
        <td><?= htmlspecialchars($a['Species'] ?? '—') ?></td>
        <td style="font-size:.78rem;color:#888"><?= htmlspecialchars($a['Category'] ?? '—') ?></td>
        <td><span class="badge badge-<?= $hs ?>"><?= htmlspecialchars($a['Health_Status']) ?></span></td>
        <td><?= htmlspecialchars($a['Enclosure_Name'] ?? 'Unassigned') ?></td>
        <td style="font-size:.8rem"><?= !empty($a['CaretakerName']) ? htmlspecialchars($a['CaretakerName']) : '<span style="color:#aaa">—</span>' ?></td>
        <td style="font-size:.8rem"><?= !empty($a['VetName']) ? htmlspecialchars($a['VetName']) : '<span style="color:#aaa">—</span>' ?></td>
        <td>
            <div class="food-bar-wrap">
                <div class="food-bar"><div class="food-fill <?= $bc ?>" style="width:<?= $stock ?>%"></div></div>
                <span style="font-size:.73rem;font-weight:700;<?= $stock<=20?'color:#e74c3c':'' ?>"><?= $stock ?>%</span>
            </div>
        </td>
        <td style="font-size:.8rem">
            <?= $a['LastCheckup'] ? date('M j, Y', strtotime($a['LastCheckup'])) : '<span style="color:#aaa">None</span>' ?>
        </td>
        <td style="font-weight:700;color:<?= (int)$a['TotalRecords']>0?'#2980b9':'#aaa' ?>"><?= $a['TotalRecords'] ?></td>
        <td><button class="expand-btn" onclick="toggleDetail(<?= (int)$a['Animal_ID'] ?>)">▶ More</button></td>
    </tr>
    <tr class="detail-row" id="detail-<?= (int)$a['Animal_ID'] ?>">
        <td class="detail-cell" colspan="12">
            <div class="detail-grid">
                <div class="detail-item"><strong>Age</strong><?= $a['Age'] !== null ? $a['Age'].' yr' : '—' ?></div>
                <div class="detail-item"><strong>Sex</strong><?= htmlspecialchars($a['Sex'] ?? '—') ?></div>
                <div class="detail-item"><strong>Past sick episodes</strong><?= $a['SickCount'] ?></div>
                <div class="detail-item"><strong>Total health records</strong><?= $a['TotalRecords'] ?></div>
                <div class="detail-item"><strong>Cured date</strong><?= $a['Cured_Date'] ? '<span style="color:#27ae60;font-weight:600">'.date('M j, Y',strtotime($a['Cured_Date'])).'</span>' : '—' ?></div>
                <div class="detail-item"><strong>Last vet</strong><?= !empty($a['VetName']) ? htmlspecialchars($a['VetName']) : '—' ?></div>
                <div class="detail-item" style="grid-column:span 2"><strong>Diagnosis</strong><?= $a['Diagnosis'] ? htmlspecialchars($a['Diagnosis']) : '<span style="color:#aaa">None on record</span>' ?></div>
                <div class="detail-item" style="grid-column:span 2"><strong>Treatment</strong><?= $a['Treatment'] ? htmlspecialchars($a['Treatment']) : '<span style="color:#aaa">None on record</span>' ?></div>
                <div class="detail-item" style="grid-column:span 2"><strong>Notes</strong><?= $a['Notes'] ? htmlspecialchars($a['Notes']) : '<span style="color:#aaa">No notes</span>' ?></div>
            </div>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
</div>

</div><!-- end wrapper -->

<script>
function toggleDetail(id) {
    const row = document.getElementById('detail-' + id);
    row.classList.toggle('open');
    const btn = row.previousElementSibling.querySelector('.expand-btn');
    btn.textContent = row.classList.contains('open') ? '▼ Less' : '▶ More';
}

// Overall health donut
new Chart(document.getElementById('overallChart'), {
    type: 'doughnut',
    data: {
        labels: ['Healthy', 'Sick', 'Pending'],
        datasets: [{ data: [<?= $healthy ?>, <?= $sick ?>, <?= $pending ?>],
            backgroundColor: ['#2ecc71','#e74c3c','#f39c12'], borderWidth: 2, borderColor: '#fff' }]
    },
    options: { responsive:true, maintainAspectRatio:false, cutout:'58%',
        plugins:{ legend:{ position:'bottom', labels:{ font:{size:11} } } } }
});

// Health by enclosure stacked bar
const encLabels  = <?= json_encode(array_keys($byEnclosure)) ?>;
const encHealthy = <?= json_encode(array_map(fn($e) => $e['Healthy'], $byEnclosure)) ?>;
const encSick    = <?= json_encode(array_map(fn($e) => $e['Sick'],    $byEnclosure)) ?>;
const encPending = <?= json_encode(array_map(fn($e) => $e['Pending'], $byEnclosure)) ?>;
new Chart(document.getElementById('enclosureChart'), {
    type: 'bar',
    data: {
        labels: encLabels,
        datasets: [
            { label:'Healthy', data:encHealthy, backgroundColor:'#2ecc71cc', stack:'s' },
            { label:'Sick',    data:encSick,    backgroundColor:'#e74c3ccc', stack:'s' },
            { label:'Pending', data:encPending, backgroundColor:'#f39c12cc', stack:'s' },
        ]
    },
    options: { responsive:true, maintainAspectRatio:false,
        plugins:{ legend:{ labels:{font:{size:10}} } },
        scales:{ x:{ stacked:true, ticks:{font:{size:9}} }, y:{ stacked:true, ticks:{ stepSize:1, font:{size:10} } } } }
});

// Cumulative health status over time
const cumRaw     = <?= json_encode($cumulativeRows) ?>;
const cumDays    = cumRaw.map(r => { const p = r.Day.split('-'); return p[1]+'/'+p[2]; });
const cumHealthy = cumRaw.map(r => parseInt(r.Healthy) || 0);
const cumSick    = cumRaw.map(r => parseInt(r.Sick)    || 0);
const cumPending = cumRaw.map(r => parseInt(r.Pending) || 0);
new Chart(document.getElementById('timelineChart'), {
    type: 'line',
    data: {
        labels: cumDays,
        datasets: [
            { label:'Healthy', data:cumHealthy, borderColor:'#2ecc71', backgroundColor:'#2ecc7122', fill:true,  tension:.3, borderWidth:2, pointRadius:2 },
            { label:'Sick',    data:cumSick,    borderColor:'#e74c3c', backgroundColor:'#e74c3c22', fill:true,  tension:.3, borderWidth:2, pointRadius:2 },
            { label:'Pending', data:cumPending, borderColor:'#f39c12', backgroundColor:'transparent',           tension:.3, borderWidth:1.5,pointRadius:2 },
        ]
    },
    options: { responsive:true, maintainAspectRatio:false,
        interaction:{ mode:'index', intersect:false },
        plugins:{ legend:{ labels:{font:{size:10}} },
            tooltip:{ callbacks:{ title: ctx => 'Date: '+ctx[0].label } } },
        scales:{
            x:{ ticks:{font:{size:9},maxRotation:45} },
            y:{ beginAtZero:true, ticks:{ stepSize:1, font:{size:10} },
                title:{ display:true, text:'Animals', font:{size:10} } }
        }
    }
});
</script>
</body>
</html>
