<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit;
}
require_once 'staff_home.php';
$roleGate = strtolower(trim((string) ($_SESSION['role'] ?? '')));
if (!in_array($roleGate, ['admin', 'caretaker', 'vet', 'keeper'], true) && !staff_is_vet_role()) {
    header('Location: dashboard.php');
    exit;
}
require 'db.php';

$staffHome = staff_home_href();

function animal_table_has_caretaker_column(PDO $pdo): bool
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    try {
        $stmt = $pdo->query("
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'animal'
              AND COLUMN_NAME = 'Caretaker_EmployeeID'
            LIMIT 1
        ");
        $cache = (bool) $stmt->fetchColumn();
    } catch (Throwable $e) {
        $cache = false;
    }
    return $cache;
}

$hasCaretakerCol = animal_table_has_caretaker_column($pdo);

$categories  = $pdo->query("SELECT DISTINCT Category  FROM animal WHERE Category  IS NOT NULL ORDER BY Category")->fetchAll(PDO::FETCH_COLUMN);
$species     = $pdo->query("SELECT DISTINCT Species   FROM animal WHERE Species   IS NOT NULL ORDER BY Species")->fetchAll(PDO::FETCH_COLUMN);
$enclosures  = $pdo->query("SELECT Enclosure_ID, Enclosure_Name FROM enclosure ORDER BY Enclosure_Name")->fetchAll();
$diets       = $pdo->query("SELECT Diet_ID, Diet_Type FROM diet ORDER BY Diet_Type")->fetchAll();
if ($hasCaretakerCol) {
    $caretakers = $pdo->query("
        SELECT e.EmployeeID, CONCAT(e.FirstName,' ',e.LastName) AS FullName
        FROM employees e
        WHERE LOWER(TRIM(e.Role)) IN ('caretaker', 'keeper')
        ORDER BY e.FirstName, e.LastName
    ")->fetchAll();
} else {
    $caretakers = $pdo->query("
        SELECT e.EmployeeID, CONCAT(e.FirstName,' ',e.LastName) AS FullName
        FROM employees e
        WHERE e.Role IN ('caretaker','Caretaker','admin','Admin','vet','Vet')
        ORDER BY e.FirstName
    ")->fetchAll();
}

// Read filters
$f_category    = $_GET['category']    ?? '';
$f_species     = $_GET['species']     ?? '';
$f_enclosure   = $_GET['enclosure']   ?? '';
$f_diet        = $_GET['diet']        ?? '';
$f_sex         = $_GET['sex']         ?? '';
$f_caretaker   = $_GET['caretaker']   ?? '';
$f_age_min     = $_GET['age_min']     ?? '';
$f_age_max     = $_GET['age_max']     ?? '';
$f_search      = trim($_GET['search'] ?? '');
$f_sort        = $_GET['sort']        ?? 'a.Name';
$f_dir         = $_GET['dir']         ?? 'ASC';

$allowedSorts = ['a.Name','a.Species','a.Category','a.Age','a.Sex','e.Enclosure_Name','d.Diet_Type'];
if (!in_array($f_sort, $allowedSorts)) $f_sort = 'a.Name';
$f_dir = ($f_dir === 'DESC') ? 'DESC' : 'ASC';

// Build query
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
if ($f_age_min !== '') { $where[] = 'a.Age >= ?'; $params[] = (int)$f_age_min; }
if ($f_age_max !== '') { $where[] = 'a.Age <= ?'; $params[] = (int)$f_age_max; }

// Assigned caretaker filter (column) vs  health-record vet match
$caretakerJoin = '';
if ($f_caretaker) {
    if ($hasCaretakerCol) {
        $where[] = 'a.Caretaker_EmployeeID = ?';
        $params[] = (int) $f_caretaker;
    } else {
        $caretakerJoin = 'JOIN health_record hr2 ON hr2.Animal_ID = a.Animal_ID AND hr2.Veterinarian_ID = ?';
        $params = array_merge([(int) $f_caretaker], $params);
    }
}

$caretakerJoinSql = $hasCaretakerCol
    ? 'LEFT JOIN employees ck ON a.Caretaker_EmployeeID = ck.EmployeeID'
    : '';

// Latest health record per animal
$sql = "
    SELECT a.Animal_ID, a.Name, a.Species, a.Category, a.Age, a.Sex, a.Daily_Calories, a.food_stock, e.Enclosure_Name, e.Enclosure_ID, d.Diet_Type, d.Restrictions, hr.Record_Date 
    AS LastCheckup, CONCAT(emp.FirstName,' ',emp.LastName) 
    AS LastVisitVet, " . ($hasCaretakerCol ? "CONCAT(ck.FirstName,' ',ck.LastName) 
    AS CaretakerName" : 'NULL AS CaretakerName') . ", (SELECT COUNT(*) 
    FROM health_record WHERE Animal_ID = a.Animal_ID) AS CheckupCount
    FROM animal a
    LEFT JOIN enclosure e    ON a.Enclosure_ID    = e.Enclosure_ID
    LEFT JOIN diet d         ON a.Diet_ID         = d.Diet_ID
    $caretakerJoinSql
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

// Summary stats 
$total   = count($animals);
$lowFood = count(array_filter($animals, fn($a) => (int)$a['food_stock'] <= 10));

$staffColCount = $hasCaretakerCol ? 2 : 1;
$hasActionCol = in_array($_SESSION['role'], ['admin', 'caretaker'], true);
$detailColspan = 10 + $staffColCount + 1 + ($hasActionCol ? 1 : 0);

$byEnclosure = [];
foreach ($animals as $a) {
    $encLabel = $a['Enclosure_Name'] ?? '';
    $encLabel = ($encLabel !== '' && $encLabel !== null) ? $encLabel : 'Unassigned';
    $byEnclosure[$encLabel][] = $a;
}
ksort($byEnclosure, SORT_NATURAL | SORT_FLAG_CASE);

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
        body { overflow: auto; }
        .dashboard-wrapper {
            box-sizing: border-box;
            min-height: 100vh;
            padding: 20px clamp(12px, 2.4vw, 18px);
            background-color: rgba(187, 223, 158, 0.95);
        }
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            border-bottom: 3px solid var(--accent-color);
            padding-bottom: 12px;
        }
        .filter-card {
            background: white;
            border-radius: 12px;
            padding: 12px 14px;
            margin-bottom: 14px;
            box-shadow: 0 3px 8px rgba(0,0,0,0.05);
        }
        .filter-card h2 {
            font-size: 0.95rem;
            font-weight: 700;
            margin-bottom: 10px;
            color: var(--text-color);
        }
        .btn {
            padding: 6px 14px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.85rem;
            border: none;
            cursor: pointer;
            font: inherit;
            display: inline-block;
        }
        .btn-edit {
            background-color: var(--accent-color);
            color: white;
        }
        .btn-delete {
            background-color: #e74c3c;
            color: white;
        }
        .btn-edit:hover {
            background-color: var(--text-color);
        }
        .btn-delete:hover {
            background-color: #c0392b;
        }
        .logout-btn {
            padding: 10px 25px;
            background-color: var(--accent-color);
            border: none;
            border-radius: 1000px;
            font: inherit;
            font-weight: 600;
            cursor: pointer;
            color: var(--text-color);
            text-decoration: none;
        }
        .logout-btn:hover {
            background-color: var(--text-color);
            color: white;
        }
        .back-btn {
            display: inline-block;
            margin-bottom: 14px;
            padding: 7px 14px;
            background-color: var(--base-color);
            border-radius: 8px;
            color: var(--text-color);
            font-weight: 600;
            text-decoration: none;
            font-size: 0.88rem;
        }
        .back-btn:hover { background-color: var(--accent-color); }
        .filter-card label {
            background: none;
            color: var(--text-color);
            font-size: 0.85rem;
            font-weight: 600;
            height: auto;
            width: auto;
            border-radius: 0;
            display: block;
            text-align: left;
            padding: 0;
            fill: none;
            flex-shrink: unset;
        }
        .filter-card form {
            width: 100%;
            margin: 0;
            display: block;
        }
        .filter-card form > div {
            width: auto;
            display: block;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .filter-group label {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-color);
        }
        .filter-group input,
        .filter-group select {
            padding: 6px 10px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font: inherit;
            background: white;
        }
        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: var(--accent-color);
        }
        .filter-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: end;
        }
        .enc-breakdown-title {
            font-size: 1rem;
            margin: 18px 0 8px;
            border-bottom: 1px solid #e0e0e0;
            padding-bottom: 6px;
            color: var(--text-color);
        }
        .enc-breakdown .enc-breakdown-title:first-of-type { margin-top: 0; }
        .enc-count { font-weight: 600; color: #666; font-size: 0.85rem; }
        .enc-animal-list { margin: 0 0 12px 20px; padding: 0; }
        .enc-animal-list li { margin: 5px 0; font-size: 0.9rem; }

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
            padding: 10px 12px !important;
            border-bottom: 2px solid var(--accent-color) !important;
        }
        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 8px;
        }
        .detail-item { font-size: .85rem; }
        .detail-item strong { display: block; font-size: .72rem; text-transform: uppercase; letter-spacing: .05em; color: #888; margin-bottom: 2px; }

        .no-results { padding: 28px; text-align: center; color: #888; font-style: italic; }

        .result-count { font-size: .85rem; color: #666; margin-bottom: 12px; font-weight: 600; }

        .flash-msg {
            padding: 10px 14px;
            border-radius: 8px;
            margin-bottom: 14px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        .flash-msg.ok { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .flash-msg.err { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        .action-btns { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; }

        .ui-modal {
            position: fixed;
            inset: 0;
            z-index: 3000;
            display: grid;
            place-items: center;
            padding: 16px;
        }
        .ui-modal[hidden] { display: none !important; }
        .ui-modal__backdrop {
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, 0.4);
            cursor: pointer;
        }
        .ui-modal__box {
            position: relative;
            background: #fff;
            border-radius: 12px;
            padding: 20px 22px;
            max-width: 400px;
            width: 100%;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.18);
        }
        .ui-modal__box h2 {
            margin: 0 0 10px;
            font-size: 1.05rem;
            color: var(--text-color);
        }
        .ui-modal__box p { margin: 0 0 18px; font-size: 0.9rem; line-height: 1.45; color: #444; }
        .ui-modal__actions { display: flex; flex-wrap: wrap; gap: 8px; justify-content: flex-end; }
    </style>
</head>
<body>
<div class="dashboard-wrapper">

    <div class="dashboard-header">
        <h1>Animals Report</h1>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>

    <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:14px">
        <a href="<?= htmlspecialchars($staffHome) ?>" class="back-btn" style="margin-bottom:0">← Back to dashboard</a>
    </div>

    <?php if (!empty($_GET['deleted'])): ?>
        <p class="flash-msg ok" role="status">Animal record was removed from the directory.</p>
    <?php endif; ?>
    <?php if (!empty($_GET['error'])): ?>
        <p class="flash-msg err" role="alert"><?= htmlspecialchars((string) $_GET['error']) ?></p>
    <?php endif; ?>

    <div class="filter-card">
        <h2>Filter Animals</h2>
        <form method="GET">
            <div class="filter-grid">
                <div class="filter-group filter-group--wide">
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
                    <label><?= $hasCaretakerCol ? 'Assigned caretaker' : 'Staff on health record' ?></label>
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

                <div class="filter-actions">
                    <button type="submit" class="btn btn-edit">Search</button>
                    <a href="animals_report.php" class="btn">Reset</a>
                </div>
            </div>
        </form>
    </div>

    <?php if (empty($animals)): ?>
    <p class="no-results">No animals match the selected filters.</p>
<?php else: ?>

<div class="report-table-scroll">
<table>
    <thead>
        <tr>
            <th>ID</th>
            <th><?= sortLink('a.Name', 'Name', $f_sort, $f_dir) ?></th>
            <th><?= sortLink('a.Species', 'Species', $f_sort, $f_dir) ?></th>
            <th><?= sortLink('a.Category', 'Category', $f_sort, $f_dir) ?></th>
            <th><?= sortLink('a.Age', 'Age', $f_sort, $f_dir) ?></th>
            <th><?= sortLink('a.Sex', 'Sex', $f_sort, $f_dir) ?></th>
            <th><?= sortLink('e.Enclosure_Name', 'Enclosure', $f_sort, $f_dir) ?></th>
            <th><?= sortLink('d.Diet_Type', 'Diet', $f_sort, $f_dir) ?></th>
            <th>Food Stock</th>
            <th>Last Checkup</th>
            <?php if ($hasCaretakerCol): ?>
            <th>Assigned caretaker</th>
            <?php endif; ?>
            <th>Vet</th>
            <th>Actions</th>
        </tr>
    </thead>

    <tbody>
    <?php foreach ($animals as $a):
        $stock = (int)($a['food_stock'] ?? 50);
        $barClass = $stock > 40 ? 'high' : ($stock > 10 ? 'medium' : 'low');
    ?>
        <tr>
            <td><?= $a['Animal_ID'] ?></td>
            <td><strong><?= htmlspecialchars($a['Name'] ?? '—') ?></strong></td>
            <td><?= htmlspecialchars($a['Species'] ?? '—') ?></td>
            <td><?= htmlspecialchars($a['Category'] ?? '—') ?></td>
            <td><?= $a['Age'] !== null ? $a['Age'] . ' yr' : '—' ?></td>
            <td><?= htmlspecialchars($a['Sex'] ?? '—') ?></td>
            <td><?= htmlspecialchars($a['Enclosure_Name'] ?? 'Unassigned') ?></td>
            <td><?= htmlspecialchars($a['Diet_Type'] ?? '—') ?></td>

            <td>
                <div class="food-bar-wrap">
                    <div class="food-bar">
                        <div class="food-fill <?= $barClass ?>" style="width:<?= min(100,$stock) ?>%"></div>
                    </div>
                    <span class="food-pct <?= $stock <= 10 ? 'low' : '' ?>">
                        <?= $stock ?>%
                    </span>
                </div>
            </td>

            <td>
                <?= $a['LastCheckup'] 
                    ? date('M j, Y', strtotime($a['LastCheckup'])) 
                    : '<span style="color:#aaa">None</span>' ?>
            </td>

            <?php if ($hasCaretakerCol): ?>
            <td>
                <?= !empty($a['CaretakerName']) 
                    ? htmlspecialchars($a['CaretakerName']) 
                    : '<span style="color:#aaa">—</span>' ?>
            </td>
            <?php endif; ?>

            <td>
                <?= !empty($a['LastVisitVet']) 
                    ? htmlspecialchars($a['LastVisitVet']) 
                    : '<span style="color:#aaa">—</span>' ?>
            </td>
            <td>
                <div class="action-btns">
                    <a href="edit_animal.php?id=<?= (int) $a['Animal_ID'] ?>" class="btn btn-edit" style="padding:4px 10px;font-size:0.8rem">Edit</a>
                    <form method="post" action="delete_animal.php" class="js-delete-animal-form" style="display:inline;margin:0"
                          data-animal-name="<?= htmlspecialchars($a['Name'] ?? 'this animal', ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="id" value="<?= (int) $a['Animal_ID'] ?>">
                        <button type="submit" class="btn btn-delete" style="padding:4px 10px;font-size:0.8rem">Delete</button>
                    </form>
                </div>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>

</table>
</div>

<?php endif; ?>
</div>

<div id="delete-animal-modal" class="ui-modal" hidden role="dialog" aria-modal="true" aria-labelledby="delete-animal-modal-title">
    <div class="ui-modal__backdrop" data-modal-dismiss></div>
    <div class="ui-modal__box">
        <h2 id="delete-animal-modal-title">Confirm delete</h2>
        <p id="delete-animal-modal-text"></p>
        <div class="ui-modal__actions">
            <button type="button" class="btn btn-edit" data-modal-dismiss>Cancel</button>
            <button type="button" class="btn btn-delete" id="delete-animal-modal-confirm">Delete</button>
        </div>
    </div>
</div>
<script>
(function () {
    var modal = document.getElementById('delete-animal-modal');
    var textEl = document.getElementById('delete-animal-modal-text');
    var confirmBtn = document.getElementById('delete-animal-modal-confirm');
    if (!modal || !textEl || !confirmBtn) return;
    var pendingForm = null;
    var bypass = false;

    function closeModal() {
        modal.hidden = true;
        pendingForm = null;
    }

    document.querySelectorAll('.js-delete-animal-form').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (bypass) {
                bypass = false;
                return;
            }
            e.preventDefault();
            pendingForm = form;
            var name = form.getAttribute('data-animal-name') || 'this animal';
            textEl.textContent = 'Delete ' + name + ' permanently? This cannot be undone.';
            modal.hidden = false;
        });
    });

    confirmBtn.addEventListener('click', function () {
        if (!pendingForm) return;
        bypass = true;
        if (typeof pendingForm.requestSubmit === 'function') {
            pendingForm.requestSubmit();
        } else {
            pendingForm.submit();
        }
        closeModal();
    });

    modal.querySelectorAll('[data-modal-dismiss]').forEach(function (el) {
        el.addEventListener('click', closeModal);
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !modal.hidden) closeModal();
    });
})();
</script>
</body>
</html>