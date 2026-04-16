<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit;
}
if (!in_array($_SESSION['role'], ['admin', 'caretaker', 'vet'])) {
    header('Location: dashboard.php');
    exit;
}
require 'db.php';

// ── Fetch filter options ─────────────────────────────────────────
$categories  = $pdo->query("SELECT DISTINCT Category  FROM animal WHERE Category  IS NOT NULL ORDER BY Category")->fetchAll(PDO::FETCH_COLUMN);
$species     = $pdo->query("SELECT DISTINCT Species   FROM animal WHERE Species   IS NOT NULL ORDER BY Species")->fetchAll(PDO::FETCH_COLUMN);
$enclosures  = $pdo->query("SELECT Enclosure_ID, Enclosure_Name FROM enclosure ORDER BY Enclosure_Name")->fetchAll();
$diets       = $pdo->query("SELECT Diet_ID, Diet_Type FROM diet ORDER BY Diet_Type")->fetchAll();
$healthStats = ['Healthy', 'Sick', 'Pending'];
$caretakers  = $pdo->query("
    SELECT e.EmployeeID, CONCAT(e.FirstName,' ',e.LastName) AS FullName
    FROM employees e
    WHERE e.Role IN ('caretaker','Caretaker','admin','Admin','vet','Vet')
    ORDER BY e.FirstName
")->fetchAll();

// ── Read filters ─────────────────────────────────────────────────
$f_category    = $_GET['category']    ?? '';
$f_species     = $_GET['species']     ?? '';
$f_enclosure   = $_GET['enclosure']   ?? '';
$f_diet        = $_GET['diet']        ?? '';
$f_sex         = $_GET['sex']         ?? '';
$f_health      = $_GET['health']      ?? '';
$f_caretaker   = $_GET['caretaker']   ?? '';
$f_age_min     = $_GET['age_min']     ?? '';
$f_age_max     = $_GET['age_max']     ?? '';
$f_date_from   = $_GET['date_from']   ?? '';
$f_date_to     = $_GET['date_to']     ?? '';
$f_search      = trim($_GET['search'] ?? '');
$f_sort        = $_GET['sort']        ?? 'a.Name';
$f_dir         = $_GET['dir']         ?? 'ASC';

$allowedSorts = ['a.Name','a.Species','a.Category','a.Age','a.Sex','a.Health_Status','e.Enclosure_Name','d.Diet_Type'];
if (!in_array($f_sort, $allowedSorts)) $f_sort = 'a.Name';
$f_dir = ($f_dir === 'DESC') ? 'DESC' : 'ASC';

// ── Build query ──────────────────────────────────────────────────
$where  = ['1=1'];
$params = [];

if ($f_search) {
    $where[]  = '(a.Name LIKE ? OR a.Species LIKE ?)';
    $params[] = "%$f_search%";
    $params[] = "%$f_search%";
}
if ($f_category)  { $where[] = 'a.Category = ?';      $params[] = $f_category; }
if ($f_species)   { $where[] = 'a.Species = ?';       $params[] = $f_species; }
if ($f_enclosure) { $where[] = 'a.Enclosure_ID = ?';  $params[] = (int)$f_enclosure; }
if ($f_diet)      { $where[] = 'a.Diet_ID = ?';       $params[] = (int)$f_diet; }
if ($f_sex)       { $where[] = 'a.Sex = ?';           $params[] = $f_sex; }
if ($f_health)    { $where[] = 'a.Health_Status = ?'; $params[] = $f_health; }
if ($f_age_min !== '') { $where[] = 'a.Age >= ?'; $params[] = (int)$f_age_min; }
if ($f_age_max !== '') { $where[] = 'a.Age <= ?'; $params[] = (int)$f_age_max; }

// Caretaker filter via health_record join
$caretakerJoin = '';
if ($f_caretaker) {
    $caretakerJoin = 'JOIN health_record hr2 ON hr2.Animal_ID = a.Animal_ID AND hr2.Veterinarian_ID = ?';
    $params[] = (int)$f_caretaker;
}

// Latest health record per animal (for display)
$sql = "
    SELECT
        a.Animal_ID,
        a.Name,
        a.Species,
        a.Category,
        a.Age,
        a.Sex,
        COALESCE(a.Health_Status, 'Pending') AS Health_Status,
        a.Daily_Calories,
        a.food_stock,
        e.Enclosure_Name,
        e.Enclosure_ID,
        d.Diet_Type,
        d.Restrictions,
        hr.Diagnosis,
        hr.Treatment,
        hr.Record_Date   AS LastCheckup,
        CONCAT(emp.FirstName,' ',emp.LastName) AS Veterinarian,
        (SELECT COUNT(*) FROM health_record WHERE Animal_ID = a.Animal_ID) AS CheckupCount
    FROM animal a
    LEFT JOIN enclosure e    ON a.Enclosure_ID    = e.Enclosure_ID
    LEFT JOIN diet d         ON a.Diet_ID         = d.Diet_ID
    LEFT JOIN (
        SELECT hr1.*
        FROM health_record hr1
        INNER JOIN (
            SELECT Animal_ID, MAX(Record_Date) AS MaxDate
            FROM health_record
            GROUP BY Animal_ID
        ) latest ON hr1.Animal_ID = latest.Animal_ID AND hr1.Record_Date = latest.MaxDate
    ) hr ON hr.Animal_ID = a.Animal_ID
    LEFT JOIN employees emp  ON hr.Veterinarian_ID = emp.EmployeeID
    $caretakerJoin
    WHERE " . implode(' AND ', $where) . "
    ORDER BY $f_sort $f_dir
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$animals = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Summary stats ────────────────────────────────────────────────
$total   = count($animals);
$sick    = count(array_filter($animals, fn($a) => $a['Health_Status'] === 'Sick'));
$healthy = count(array_filter($animals, fn($a) => $a['Health_Status'] === 'Healthy'));
$lowFood = count(array_filter($animals, fn($a) => (int)$a['food_stock'] <= 10));

// Toggle sort direction helper
function sortLink(string $col, string $label, string $current, string $dir): string {
    $params = $_GET;
    $params['sort'] = $col;
    $params['dir']  = ($current === $col && $dir === 'ASC') ? 'DESC' : 'ASC';
    $arrow = ($current === $col) ? ($dir === 'ASC' ? ' ↑' : ' ↓') : '';
    return '<a href="?' . http_build_query($params) . '" style="color:white;text-decoration:none">' . $label . $arrow . '</a>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Animals Report — Greenwood Zoo</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body  { overflow: auto; }

        .page-wrap {
            box-sizing: border-box;
            min-height: 100vh;
            padding: 30px 40px;
            background-color: rgba(187, 223, 158, 0.95);
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            border-bottom: 3px solid var(--accent-color);
            padding-bottom: 16px;
            flex-wrap: wrap;
            gap: 12px;
        }
        .page-header h1 { font-size: 1.75rem; margin: 0; }

        .btn-nav {
            padding: 8px 20px;
            background: var(--base-color);
            border: 2px solid var(--accent-color);
            border-radius: 1000px;
            font: inherit;
            font-weight: 600;
            font-size: .88rem;
            cursor: pointer;
            color: var(--text-color);
            text-decoration: none;
        }
        .btn-nav:hover { background: var(--accent-color); text-decoration: none; }

        .btn-logout {
            padding: 8px 20px;
            background: var(--accent-color);
            border: none;
            border-radius: 1000px;
            font: inherit;
            font-weight: 600;
            cursor: pointer;
            color: var(--text-color);
            text-decoration: none;
        }
        .btn-logout:hover { background: var(--text-color); color: white; }

        /* Stats bar */
        .stats-bar {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }
        .stat {
            background: white;
            border-radius: 12px;
            padding: 14px 16px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,.06);
        }
        .stat .num  { font-size: 1.9rem; font-weight: 900; color: var(--text-color); line-height: 1; }
        .stat .lbl  { font-size: .75rem; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; color: #888; margin-top: 4px; }
        .stat.danger  .num { color: #e74c3c; }
        .stat.warning .num { color: #f39c12; }
        .stat.success .num { color: #27ae60; }

        /* Filter panel */
        .filter-panel {
            background: white;
            border-radius: 14px;
            padding: 18px 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,.06);
        }
        .filter-panel summary {
            font-weight: 700;
            font-size: .95rem;
            cursor: pointer;
            color: var(--text-color);
            list-style: none;
            display: flex;
            align-items: center;
            gap: .5rem;
        }
        .filter-panel summary::before { content: '⚙️'; }
        .filter-panel[open] summary::before { content: '✕'; font-size: .8rem; }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 12px;
            margin-top: 16px;
        }
        .filter-group { display: flex; flex-direction: column; gap: 4px; }
        .filter-group label { font-size: .8rem; font-weight: 600; color: var(--text-color); text-transform: uppercase; letter-spacing: .04em; }
        .filter-group input,
        .filter-group select {
            padding: 7px 10px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font: inherit;
            font-size: .88rem;
            background: white;
        }
        .filter-group input:focus,
        .filter-group select:focus { outline: none; border-color: var(--accent-color); }

        .filter-actions { display: flex; gap: 10px; margin-top: 14px; flex-wrap: wrap; }
        .btn-filter {
            padding: 8px 22px;
            background: var(--accent-color);
            border: none;
            border-radius: 8px;
            font: inherit;
            font-weight: 600;
            cursor: pointer;
            color: white;
        }
        .btn-filter:hover { background: var(--text-color); }
        .btn-reset {
            padding: 8px 22px;
            background: #eee;
            border: none;
            border-radius: 8px;
            font: inherit;
            font-weight: 600;
            cursor: pointer;
            color: #555;
            text-decoration: none;
            display: inline-block;
        }
        .btn-reset:hover { background: #ddd; }

        /* Table */
        .table-wrap {
            background: white;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 4px 14px rgba(0,0,0,.08);
            overflow-x: auto;
        }
        table { width: 100%; border-collapse: collapse; min-width: 900px; }
        th {
            background: var(--accent-color);
            color: white;
            padding: 11px 13px;
            text-align: left;
            font-size: .82rem;
            text-transform: uppercase;
            letter-spacing: .04em;
            white-space: nowrap;
        }
        td { padding: 10px 13px; border-bottom: 1px solid #f0f0f0; font-size: .88rem; vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        tbody tr:hover td { background: rgba(187,223,158,.2); }

        /* Badges */
        .badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 1000px;
            font-size: .75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .04em;
            white-space: nowrap;
        }
        .badge-healthy  { background: #d4edda; color: #155724; }
        .badge-sick     { background: #f8d7da; color: #721c24; }
        .badge-pending  { background: #fff3cd; color: #856505; }

        /* Food bar */
        .food-bar-wrap { display: flex; align-items: center; gap: 6px; min-width: 90px; }
        .food-bar { flex: 1; height: 8px; border-radius: 4px; background: #eee; overflow: hidden; }
        .food-fill { height: 100%; border-radius: 4px; }
        .food-fill.high   { background: #2ecc71; }
        .food-fill.medium { background: #f39c12; }
        .food-fill.low    { background: #e74c3c; }
        .food-pct { font-size: .75rem; font-weight: 700; min-width: 28px; }
        .food-pct.low { color: #e74c3c; }

        /* Detail expand */
        .expand-btn {
            background: none; border: none; cursor: pointer;
            font-size: .8rem; font-weight: 600; color: var(--text-color);
            padding: 3px 8px; border-radius: 6px; background: var(--base-color);
        }
        .expand-btn:hover { background: var(--accent-color); color: white; }

        .detail-row { display: none; }
        .detail-row.open { display: table-row; }
        .detail-cell {
            background: #f8fff8 !important;
            padding: 12px 16px !important;
            border-bottom: 2px solid var(--accent-color) !important;
        }
        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 10px;
        }
        .detail-item { font-size: .85rem; }
        .detail-item strong { display: block; font-size: .72rem; text-transform: uppercase; letter-spacing: .05em; color: #888; margin-bottom: 2px; }

        .no-results { padding: 40px; text-align: center; color: #888; font-style: italic; }

        .result-count { font-size: .85rem; color: #666; margin-bottom: 12px; font-weight: 600; }

        .actions-cell { display: flex; gap: 6px; flex-wrap: wrap; }
        .btn-edit   { padding: 4px 12px; background: var(--accent-color); color: white; border-radius: 6px; text-decoration: none; font-size: .8rem; font-weight: 600; }
        .btn-edit:hover { background: var(--text-color); }
        .btn-delete { padding: 4px 12px; background: #e74c3c; color: white; border-radius: 6px; text-decoration: none; font-size: .8rem; font-weight: 600; border: none; cursor: pointer; font: inherit; }
        .btn-delete:hover { background: #c0392b; }
    </style>
</head>
<body>
<div class="page-wrap">

    <!-- Header -->
    <div class="page-header">
        <div>
            <h1>Animals Report</h1>
            <p style="margin:4px 0 0;font-size:.88rem;color:#555">
                Welcome, <?= htmlspecialchars($_SESSION['firstname']) ?> · Role: <?= $_SESSION['role'] ?>
            </p>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
            <a href="add-animal.php" class="btn-nav">+ Add Animal</a>
            <a href="dashboard.php"  class="btn-nav">← Dashboard</a>
            <a href="logout.php"     class="btn-logout">Logout</a>
        </div>
    </div>

    <!-- Stats bar -->
    <div class="stats-bar">
        <div class="stat">
            <div class="num"><?= $total ?></div>
            <div class="lbl">Total animals</div>
        </div>
        <div class="stat success">
            <div class="num"><?= $healthy ?></div>
            <div class="lbl">Healthy</div>
        </div>
        <div class="stat danger">
            <div class="num"><?= $sick ?></div>
            <div class="lbl">Sick</div>
        </div>
        <div class="stat warning">
            <div class="num"><?= $lowFood ?></div>
            <div class="lbl">Low food stock</div>
        </div>
        <div class="stat">
            <div class="num"><?= count($enclosures) ?></div>
            <div class="lbl">Enclosures</div>
        </div>
    </div>

    <!-- Filter panel -->
    <details class="filter-panel" <?= !empty(array_filter($_GET)) ? 'open' : '' ?>>
        <summary>Filters &amp; Search</summary>
        <form method="GET">
            <div class="filter-grid">
                <div class="filter-group" style="grid-column:1/-1">
                    <label>Search by name or species</label>
                    <input type="text" name="search" value="<?= htmlspecialchars($f_search) ?>" placeholder="e.g. Kowalski, Penguin…">
                </div>

                <div class="filter-group">
                    <label>Category</label>
                    <select name="category">
                        <option value="">All categories</option>
                        <?php foreach ($categories as $c): ?>
                        <option value="<?= htmlspecialchars($c) ?>" <?= $f_category === $c ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Species</label>
                    <select name="species">
                        <option value="">All species</option>
                        <?php foreach ($species as $s): ?>
                        <option value="<?= htmlspecialchars($s) ?>" <?= $f_species === $s ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Enclosure</label>
                    <select name="enclosure">
                        <option value="">All enclosures</option>
                        <?php foreach ($enclosures as $enc): ?>
                        <option value="<?= $enc['Enclosure_ID'] ?>" <?= (int)$f_enclosure === $enc['Enclosure_ID'] ? 'selected' : '' ?>><?= htmlspecialchars($enc['Enclosure_Name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Diet</label>
                    <select name="diet">
                        <option value="">All diets</option>
                        <?php foreach ($diets as $d): ?>
                        <option value="<?= $d['Diet_ID'] ?>" <?= (int)$f_diet === $d['Diet_ID'] ? 'selected' : '' ?>><?= htmlspecialchars($d['Diet_Type']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Sex</label>
                    <select name="sex">
                        <option value="">Any</option>
                        <option value="Male"   <?= $f_sex === 'Male'   ? 'selected' : '' ?>>Male</option>
                        <option value="Female" <?= $f_sex === 'Female' ? 'selected' : '' ?>>Female</option>
                        <option value="M"      <?= $f_sex === 'M'      ? 'selected' : '' ?>>M</option>
                        <option value="F"      <?= $f_sex === 'F'      ? 'selected' : '' ?>>F</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Health status</label>
                    <select name="health">
                        <option value="">All statuses</option>
                        <?php foreach ($healthStats as $h): ?>
                        <option value="<?= $h ?>" <?= $f_health === $h ? 'selected' : '' ?>><?= $h ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Vet / Caretaker</label>
                    <select name="caretaker">
                        <option value="">Any</option>
                        <?php foreach ($caretakers as $c): ?>
                        <option value="<?= $c['EmployeeID'] ?>" <?= (int)$f_caretaker === $c['EmployeeID'] ? 'selected' : '' ?>><?= htmlspecialchars($c['FullName']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Min age (years)</label>
                    <input type="number" name="age_min" value="<?= htmlspecialchars($f_age_min) ?>" min="0" placeholder="e.g. 2">
                </div>

                <div class="filter-group">
                    <label>Max age (years)</label>
                    <input type="number" name="age_max" value="<?= htmlspecialchars($f_age_max) ?>" min="0" placeholder="e.g. 15">
                </div>

                <div class="filter-group">
                    <label>Sort by</label>
                    <select name="sort">
                        <option value="a.Name"          <?= $f_sort === 'a.Name'          ? 'selected' : '' ?>>Name</option>
                        <option value="a.Species"       <?= $f_sort === 'a.Species'       ? 'selected' : '' ?>>Species</option>
                        <option value="a.Category"      <?= $f_sort === 'a.Category'      ? 'selected' : '' ?>>Category</option>
                        <option value="a.Age"           <?= $f_sort === 'a.Age'           ? 'selected' : '' ?>>Age</option>
                        <option value="a.Health_Status" <?= $f_sort === 'a.Health_Status' ? 'selected' : '' ?>>Health</option>
                        <option value="e.Enclosure_Name"<?= $f_sort === 'e.Enclosure_Name'? 'selected' : '' ?>>Enclosure</option>
                        <option value="d.Diet_Type"     <?= $f_sort === 'd.Diet_Type'     ? 'selected' : '' ?>>Diet</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Direction</label>
                    <select name="dir">
                        <option value="ASC"  <?= $f_dir === 'ASC'  ? 'selected' : '' ?>>A → Z / Low → High</option>
                        <option value="DESC" <?= $f_dir === 'DESC' ? 'selected' : '' ?>>Z → A / High → Low</option>
                    </select>
                </div>
            </div>

            <div class="filter-actions">
                <button type="submit" class="btn-filter">Apply filters</button>
                <a href="animals_report.php" class="btn-reset">Reset all</a>
            </div>
        </form>
    </details>

    <!-- Results -->
    <p class="result-count">
        Showing <?= $total ?> animal<?= $total !== 1 ? 's' : '' ?>
        <?php if (array_filter([$f_search,$f_category,$f_species,$f_enclosure,$f_diet,$f_sex,$f_health,$f_caretaker,$f_age_min,$f_age_max])): ?>
            <span style="color:var(--accent-color)">(filtered)</span>
        <?php endif; ?>
    </p>

    <div class="table-wrap">
        <?php if (empty($animals)): ?>
            <p class="no-results">No animals match the selected filters.</p>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th><?= sortLink('a.Name',           'Name',      $f_sort, $f_dir) ?></th>
                    <th><?= sortLink('a.Species',        'Species',   $f_sort, $f_dir) ?></th>
                    <th><?= sortLink('a.Category',       'Category',  $f_sort, $f_dir) ?></th>
                    <th><?= sortLink('a.Age',            'Age',       $f_sort, $f_dir) ?></th>
                    <th><?= sortLink('a.Sex',            'Sex',       $f_sort, $f_dir) ?></th>
                    <th><?= sortLink('e.Enclosure_Name', 'Enclosure', $f_sort, $f_dir) ?></th>
                    <th><?= sortLink('d.Diet_Type',      'Diet',      $f_sort, $f_dir) ?></th>
                    <th><?= sortLink('a.Health_Status',  'Health',    $f_sort, $f_dir) ?></th>
                    <th>Food Stock</th>
                    <th>Last Checkup</th>
                    <th>Vet / Caretaker</th>
                    <th>Details</th>
                    <?php if (in_array($_SESSION['role'], ['admin','caretaker'])): ?>
                    <th>Actions</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($animals as $idx => $a):
                $healthClass = strtolower($a['Health_Status'] ?? 'pending');
                $stock = (int)($a['food_stock'] ?? 50);
                $barClass = $stock > 40 ? 'high' : ($stock > 10 ? 'medium' : 'low');
            ?>
            <tr>
                <td><?= $a['Animal_ID'] ?></td>
                <td><strong><?= htmlspecialchars($a['Name'] ?? '—') ?></strong></td>
                <td><?= htmlspecialchars($a['Species']  ?? '—') ?></td>
                <td><?= htmlspecialchars($a['Category'] ?? '—') ?></td>
                <td><?= $a['Age'] !== null ? $a['Age'] . ' yr' : '—' ?></td>
                <td><?= htmlspecialchars($a['Sex'] ?? '—') ?></td>
                <td><?= htmlspecialchars($a['Enclosure_Name'] ?? 'Unassigned') ?></td>
                <td><?= htmlspecialchars($a['Diet_Type'] ?? '—') ?></td>
                <td><span class="badge badge-<?= $healthClass ?>"><?= htmlspecialchars($a['Health_Status'] ?? 'Pending') ?></span></td>
                <td>
                    <div class="food-bar-wrap">
                        <div class="food-bar">
                            <div class="food-fill <?= $barClass ?>" style="width:<?= min(100,$stock) ?>%"></div>
                        </div>
                        <span class="food-pct <?= $stock <= 10 ? 'low' : '' ?>"><?= $stock ?>%</span>
                    </div>
                </td>
                <td><?= $a['LastCheckup'] ? date('M j, Y', strtotime($a['LastCheckup'])) : '<span style="color:#aaa">None</span>' ?></td>
                <td><?= $a['Veterinarian'] ? htmlspecialchars($a['Veterinarian']) : '<span style="color:#aaa">—</span>' ?></td>
                <td>
                    <button class="expand-btn" onclick="toggleDetail(<?= $a['Animal_ID'] ?>)">
                        Details ▾
                    </button>
                </td>
                <?php if (in_array($_SESSION['role'], ['admin','caretaker'])): ?>
                <td>
                    <div class="actions-cell">
                        <a href="edit_animal.php?id=<?= $a['Animal_ID'] ?>" class="btn-edit">Edit</a>
                        <form method="POST" action="delete_animal.php" style="display:inline" onsubmit="return confirm('Delete <?= htmlspecialchars($a['Name'], ENT_QUOTES) ?>?')">
                            <input type="hidden" name="id" value="<?= $a['Animal_ID'] ?>">
                            <button type="submit" class="btn-delete">Delete</button>
                        </form>
                    </div>
                </td>
                <?php endif; ?>
            </tr>

            <!-- Expandable detail row -->
            <tr class="detail-row" id="detail-<?= $a['Animal_ID'] ?>">
                <td class="detail-cell" colspan="<?= in_array($_SESSION['role'], ['admin','caretaker']) ? 15 : 14 ?>">
                    <div class="detail-grid">
                        <div class="detail-item">
                            <strong>Full name</strong>
                            <?= htmlspecialchars($a['Name'] ?? '—') ?>
                        </div>
                        <div class="detail-item">
                            <strong>Animal ID</strong>
                            #<?= $a['Animal_ID'] ?>
                        </div>
                        <div class="detail-item">
                            <strong>Daily calories</strong>
                            <?= $a['Daily_Calories'] ? number_format($a['Daily_Calories']) . ' kcal' : '—' ?>
                        </div>
                        <div class="detail-item">
                            <strong>Diet type</strong>
                            <?= htmlspecialchars($a['Diet_Type'] ?? '—') ?>
                        </div>
                        <div class="detail-item">
                            <strong>Diet restrictions</strong>
                            <?= htmlspecialchars($a['Restrictions'] ?? 'None') ?>
                        </div>
                        <div class="detail-item">
                            <strong>Enclosure</strong>
                            <?= htmlspecialchars($a['Enclosure_Name'] ?? 'Unassigned') ?>
                        </div>
                        <div class="detail-item">
                            <strong>Health status</strong>
                            <span class="badge badge-<?= strtolower($a['Health_Status'] ?? 'pending') ?>">
                                <?= htmlspecialchars($a['Health_Status'] ?? 'Pending') ?>
                            </span>
                        </div>
                        <div class="detail-item">
                            <strong>Total checkups</strong>
                            <?= $a['CheckupCount'] ?>
                        </div>
                        <div class="detail-item">
                            <strong>Last checkup date</strong>
                            <?= $a['LastCheckup'] ? date('F j, Y', strtotime($a['LastCheckup'])) : 'No records' ?>
                        </div>
                        <div class="detail-item">
                            <strong>Attending vet</strong>
                            <?= htmlspecialchars($a['Veterinarian'] ?? 'Not assigned') ?>
                        </div>
                        <?php if ($a['Diagnosis']): ?>
                        <div class="detail-item" style="grid-column:span 2">
                            <strong>Latest diagnosis</strong>
                            <?= htmlspecialchars($a['Diagnosis']) ?>
                        </div>
                        <?php endif; ?>
                        <?php if ($a['Treatment']): ?>
                        <div class="detail-item" style="grid-column:span 2">
                            <strong>Latest treatment</strong>
                            <?= htmlspecialchars($a['Treatment']) ?>
                        </div>
                        <?php endif; ?>
                        <?php if (in_array($_SESSION['role'], ['admin','vet'])): ?>
                        <div class="detail-item">
                            <strong>View all health records</strong>
                            <a href="health-reports.php?animal_id=<?= $a['Animal_ID'] ?>" style="color:var(--text-color);font-weight:600">
                                View <?= $a['CheckupCount'] ?> record<?= $a['CheckupCount'] != 1 ? 's' : '' ?> →
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

</div>

<script>
function toggleDetail(id) {
    const row = document.getElementById('detail-' + id);
    row.classList.toggle('open');
    const btn = row.previousElementSibling.querySelector('.expand-btn');
    btn.textContent = row.classList.contains('open') ? 'Details ▴' : 'Details ▾';
}
</script>
</body>
</html>
