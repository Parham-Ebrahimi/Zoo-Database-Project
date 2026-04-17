<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.html'); exit; }
if (!in_array(strtolower($_SESSION['role']), ['admin'])) { header('Location: dashboard.php'); exit; }
require 'db.php';

// Filters
$f_dept      = $_GET['dept']      ?? '';
$f_role      = $_GET['role']      ?? '';
$f_status    = $_GET['status']    ?? 'Active';
$f_sex       = $_GET['sex']       ?? '';
$f_search    = trim($_GET['search'] ?? '');
$f_hire_from = $_GET['hire_from'] ?? '';
$f_hire_to   = $_GET['hire_to']   ?? '';
$f_sal_min   = $_GET['sal_min']   ?? '';
$f_sal_max   = $_GET['sal_max']   ?? '';

// Filter options
$departments = $pdo->query("SELECT DISTINCT Department FROM employees WHERE Department IS NOT NULL ORDER BY Department")->fetchAll(PDO::FETCH_COLUMN);
$roles       = $pdo->query("SELECT DISTINCT Role FROM employees WHERE Role IS NOT NULL ORDER BY Role")->fetchAll(PDO::FETCH_COLUMN);

// Build WHERE
$where  = ['1=1'];
$params = [];
if ($f_dept)       { $where[] = 'e.Department = ?';               $params[] = $f_dept; }
if ($f_role)       { $where[] = 'e.Role = ?';                     $params[] = $f_role; }
if ($f_status)     { $where[] = 'e.Status = ?';                   $params[] = $f_status; }
if ($f_sex)        { $where[] = 'e.Sex = ?';                      $params[] = $f_sex; }
if ($f_hire_from)  { $where[] = 'e.HireDate >= ?';                $params[] = $f_hire_from; }
if ($f_hire_to)    { $where[] = 'e.HireDate <= ?';                $params[] = $f_hire_to; }
if ($f_sal_min !== '') { $where[] = 'e.Salary >= ?';            $params[] = (float)$f_sal_min; }
if ($f_sal_max !== '') { $where[] = 'e.Salary <= ?';            $params[] = (float)$f_sal_max; }
if ($f_search) {
    $where[]  = '(e.FirstName LIKE ? OR e.LastName LIKE ? OR CONCAT(e.FirstName,\' \',e.LastName) LIKE ?)';
    $params[] = "%$f_search%"; $params[] = "%$f_search%"; $params[] = "%$f_search%";
}

$wSql = implode(' AND ', $where);

// Main employee query
$empStmt = $pdo->prepare("
    SELECT e.*,
           CONCAT(e.FirstName,' ',e.LastName) AS FullName,
           TIMESTAMPDIFF(YEAR, e.DOB, CURDATE()) AS Age,
           TIMESTAMPDIFF(YEAR, e.HireDate, CURDATE()) AS YearsWorked,
           s.Username, s.Role AS SystemRole,
           (SELECT COUNT(*) FROM animal a WHERE a.Caretaker_EmployeeID = e.EmployeeID) AS AnimalsAssigned,
           (SELECT COUNT(*) FROM health_record hr WHERE hr.Veterinarian_ID = e.EmployeeID) AS HealthRecords,
           (SELECT COUNT(*) FROM health_record hr2 WHERE hr2.Veterinarian_ID = e.EmployeeID AND hr2.Record_Date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) AS RecentRecords
    FROM employees e
    LEFT JOIN systemuser s ON s.EmployeeID = e.EmployeeID
    WHERE $wSql
    ORDER BY e.Department, e.LastName
");
$empStmt->execute($params);
$employees = $empStmt->fetchAll(PDO::FETCH_ASSOC);

// Summary stats
$totalEmp     = count($employees);
$totalSalary  = array_sum(array_column($employees, 'Salary'));
$avgSalary    = $totalEmp > 0 ? $totalSalary / $totalEmp : 0;
$hasSystem    = count(array_filter($employees, fn($e) => !empty($e['Username'])));
$totalAnimals = array_sum(array_column($employees, 'AnimalsAssigned'));
$totalRecords = array_sum(array_column($employees, 'HealthRecords'));

// Department breakdown
$deptStmt = $pdo->prepare("
    SELECT Department,
           COUNT(*) AS Headcount,
           SUM(Salary) AS TotalSalary,
           AVG(Salary) AS AvgSalary,
           MIN(Salary) AS MinSalary,
           MAX(Salary) AS MaxSalary
    FROM employees e
    WHERE $wSql
    GROUP BY Department
    ORDER BY Headcount DESC
");
$deptStmt->execute($params);
$deptRows = $deptStmt->fetchAll(PDO::FETCH_ASSOC);

// Role breakdown
$roleStmt = $pdo->prepare("
    SELECT Role, COUNT(*) AS Headcount, AVG(Salary) AS AvgSalary, SUM(Salary) AS TotalSalary
    FROM employees e WHERE $wSql GROUP BY Role ORDER BY Headcount DESC
");
$roleStmt->execute($params);
$roleRows = $roleStmt->fetchAll(PDO::FETCH_ASSOC);

// Salary by department for chart
$salByDept  = array_column($deptRows, 'AvgSalary', 'Department');
$cntByDept  = array_column($deptRows, 'Headcount', 'Department');

// Hire timeline - by year
$hireStmt = $pdo->prepare("
    SELECT YEAR(HireDate) AS YearHired, COUNT(*) AS Count
    FROM employees e WHERE $wSql AND HireDate IS NOT NULL
    GROUP BY YEAR(HireDate) ORDER BY YearHired ASC
");
$hireStmt->execute($params);
$hireRows = $hireStmt->fetchAll(PDO::FETCH_ASSOC);

// Sex breakdown
$sexStmt = $pdo->prepare("
    SELECT Sex, COUNT(*) AS Count FROM employees e WHERE $wSql GROUP BY Sex
");
$sexStmt->execute($params);
$sexRows = $sexStmt->fetchAll(PDO::FETCH_ASSOC);

// Caretaker workload
$caretakerStmt = $pdo->query("
    SELECT CONCAT(e.FirstName,' ',e.LastName) AS Name,
           e.EmployeeID,
           COUNT(a.Animal_ID) AS Animals,
           GROUP_CONCAT(DISTINCT enc.Enclosure_Name ORDER BY enc.Enclosure_Name SEPARATOR ', ') AS Enclosures
    FROM employees e
    LEFT JOIN animal a ON a.Caretaker_EmployeeID = e.EmployeeID
    LEFT JOIN enclosure enc ON enc.Enclosure_ID = a.Enclosure_ID
    WHERE LOWER(e.Role) IN ('caretaker','keeper')
    GROUP BY e.EmployeeID, e.FirstName, e.LastName
    ORDER BY Animals DESC
");
$caretakerRows = $caretakerStmt->fetchAll(PDO::FETCH_ASSOC);

// Vet activity
$vetStmt = $pdo->query("
    SELECT CONCAT(e.FirstName,' ',e.LastName) AS Name,
           e.EmployeeID,
           COUNT(hr.HealthRecord_ID) AS TotalRecords,
           COUNT(CASE WHEN hr.Record_Date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 END) AS Last30Days,
           COUNT(CASE WHEN hr.Health_Status = 'Sick' THEN 1 END) AS SickCases,
           COUNT(CASE WHEN hr.Cured_Date IS NOT NULL THEN 1 END) AS CuredCases,
           MAX(hr.Record_Date) AS LastActivity
    FROM employees e
    LEFT JOIN health_record hr ON hr.Veterinarian_ID = e.EmployeeID
    WHERE LOWER(e.Role) IN ('vet','veterinarian')
    GROUP BY e.EmployeeID, e.FirstName, e.LastName
    ORDER BY TotalRecords DESC
");
$vetRows = $vetStmt->fetchAll(PDO::FETCH_ASSOC);

$hasFilters = array_filter([$f_dept,$f_role,$f_sex,$f_search,$f_hire_from,$f_hire_to,$f_sal_min,$f_sal_max])
           || $f_status !== 'Active';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Employee Report — Greenwood Zoo</title>
<link rel="stylesheet" href="style.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<style>
body{overflow:auto}
.pw{box-sizing:border-box;min-height:100vh;padding:28px 36px;background:rgba(187,223,158,.97)}
.ph{display:flex;justify-content:space-between;align-items:center;margin-bottom:22px;border-bottom:3px solid var(--accent-color);padding-bottom:16px;flex-wrap:wrap;gap:12px}
.ph h1{font-size:1.7rem;margin:0}
.bn{padding:8px 20px;background:var(--base-color);border:2px solid var(--accent-color);border-radius:1000px;font:inherit;font-weight:600;font-size:.88rem;color:var(--text-color);text-decoration:none}
.bn:hover{background:var(--accent-color);text-decoration:none}
.bl{padding:8px 20px;background:var(--accent-color);border:none;border-radius:1000px;font:inherit;font-weight:600;cursor:pointer;color:var(--text-color);text-decoration:none}
.bl:hover{background:var(--text-color);color:white}

.kpi-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:20px}
.kpi{background:white;border-radius:14px;padding:16px 18px;box-shadow:0 2px 10px rgba(0,0,0,.07);border-left:4px solid var(--accent-color)}
.kpi.blue{border-color:#2980b9}.kpi.orange{border-color:#e67e22}.kpi.purple{border-color:#8e44ad}.kpi.red{border-color:#e74c3c}
.kpi .k-label{font-size:.72rem;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:#888;margin-bottom:6px}
.kpi .k-val{font-size:1.6rem;font-weight:900;color:var(--text-color);line-height:1}
.kpi .k-sub{font-size:.75rem;color:#aaa;margin-top:4px}

.fp{background:white;border-radius:14px;padding:16px 20px;margin-bottom:18px;box-shadow:0 2px 8px rgba(0,0,0,.06)}
.fp summary{font-weight:700;font-size:.92rem;cursor:pointer;color:var(--text-color);list-style:none;display:flex;align-items:center;gap:.5rem}
.fp summary::before{content:"🔍"}
.fg-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:11px;margin-top:14px}
.fg{display:flex;flex-direction:column;gap:3px}
.fg label{font-size:.75rem;font-weight:600;color:var(--text-color);text-transform:uppercase;letter-spacing:.04em}
.fg input,.fg select{padding:7px 10px;border:2px solid #ddd;border-radius:8px;font:inherit;font-size:.87rem;background:white}
.fg input:focus,.fg select:focus{outline:none;border-color:var(--accent-color)}
.fa{display:flex;gap:8px;margin-top:12px;flex-wrap:wrap}
.bfil{padding:8px 22px;background:var(--accent-color);border:none;border-radius:8px;font:inherit;font-weight:600;cursor:pointer;color:white}
.bfil:hover{background:var(--text-color)}
.bres{padding:8px 22px;background:#eee;border:none;border-radius:8px;font:inherit;font-weight:600;cursor:pointer;color:#555;text-decoration:none;display:inline-block}
.bres:hover{background:#ddd}

.tab-nav{display:flex;gap:4px;margin-bottom:16px;flex-wrap:wrap}
.tab-btn{padding:8px 18px;border:2px solid #ddd;border-radius:8px 8px 0 0;background:white;font:inherit;font-size:.85rem;font-weight:600;cursor:pointer;color:#888;border-bottom:none;transition:all .15s}
.tab-btn.active{border-color:var(--accent-color);color:var(--text-color);border-bottom:2px solid white;margin-bottom:-2px;z-index:1}
.tab-btn:hover:not(.active){background:#f5f5f5;color:var(--text-color)}
.tab-content{display:none}.tab-content.active{display:block}

.chart-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:18px}
.chart-grid-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:18px}
@media(max-width:900px){.chart-grid,.chart-grid-3{grid-template-columns:1fr}}
.cc{background:white;border-radius:14px;padding:18px 20px;box-shadow:0 2px 10px rgba(0,0,0,.07)}
.cc h3{font-size:.8rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#888;margin:0 0 14px}
.cw{position:relative;height:240px}
.cw-sm{position:relative;height:200px}

.tw{background:white;border-radius:14px;overflow:hidden;box-shadow:0 4px 14px rgba(0,0,0,.08);overflow-x:auto;margin-bottom:18px}
table{width:100%;border-collapse:collapse;min-width:500px}
th{background:var(--accent-color);color:white;padding:10px 13px;text-align:left;font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;white-space:nowrap}
td{padding:9px 13px;border-bottom:1px solid #f0f0f0;font-size:.86rem;vertical-align:middle}
tr:last-child td{border-bottom:none}
tbody tr:hover td{background:rgba(187,223,158,.15)}
tfoot td{background:var(--base-color);font-weight:700;padding:10px 13px;border-top:2px solid var(--accent-color)}

.bdg{display:inline-block;padding:2px 9px;border-radius:999px;font-size:.72rem;font-weight:700}
.bdg-admin{background:#fde8d0;color:#7d3c00}
.bdg-care{background:#d4edda;color:#155724}
.bdg-vet{background:#e8f4fd;color:#1a5276}
.bdg-retail{background:#f0e6ff;color:#4a235a}
.bdg-active{background:#d4edda;color:#155724}
.bdg-inactive{background:#f8d7da;color:#721c24}
.bdg-yes{background:#d4edda;color:#155724}
.bdg-no{background:#f0f0f0;color:#888}

.amt{font-weight:700;color:#27ae60}
.bar-cell{display:flex;align-items:center;gap:8px;min-width:100px}
.bar-outer{flex:1;background:#eee;border-radius:3px;height:8px;overflow:hidden}
.bar-inner{height:100%;border-radius:3px;background:var(--accent-color)}
.no-data{padding:30px;text-align:center;color:#aaa;font-style:italic;font-size:.88rem}

.workload-card{background:white;border-radius:12px;padding:16px 18px;box-shadow:0 2px 8px rgba(0,0,0,.06);margin-bottom:12px;display:flex;align-items:center;gap:14px}
.wl-avatar{width:44px;height:44px;border-radius:50%;background:var(--accent-color);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1.1rem;color:white;flex-shrink:0}
.wl-info{flex:1}
.wl-name{font-weight:700;font-size:.95rem;color:var(--text-color)}
.wl-sub{font-size:.78rem;color:#888;margin-top:2px}
.wl-stat{text-align:right;flex-shrink:0}
.wl-stat .num{font-size:1.4rem;font-weight:900;color:var(--accent-color);line-height:1}
.wl-stat .lbl{font-size:.7rem;color:#aaa;text-transform:uppercase;letter-spacing:.04em}

.section-hdr{font-size:.8rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#888;margin:0 0 10px;padding:0 0 6px;border-bottom:2px solid var(--accent-color);display:block}
.two-col{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:18px}
@media(max-width:860px){.two-col{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="pw">

<div class="ph">
    <div>
        <h1>Employee Report</h1>
        <p style="margin:4px 0 0;font-size:.85rem;color:#555">Welcome, <?= htmlspecialchars($_SESSION['firstname']) ?> &middot; <?= $totalEmp ?> employee<?= $totalEmp!==1?'s':'' ?> shown</p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        <a href="dashboard.php" class="bn">← Dashboard</a>
        <a href="logout.php" class="bl">Logout</a>
    </div>
</div>

<!-- KPI Cards -->
<div class="kpi-grid">
    <div class="kpi">
        <div class="k-label">Total employees</div>
        <div class="k-val"><?= $totalEmp ?></div>
        <div class="k-sub"><?= $f_status ?: 'All statuses' ?></div>
    </div>
    <div class="kpi blue">
        <div class="k-label">Total salary cost</div>
        <div class="k-val" style="font-size:1.3rem">$<?= number_format($totalSalary, 0) ?></div>
        <div class="k-sub">per year</div>
    </div>
    <div class="kpi orange">
        <div class="k-label">Average salary</div>
        <div class="k-val" style="font-size:1.3rem">$<?= number_format($avgSalary, 0) ?></div>
        <div class="k-sub">per year</div>
    </div>
    <div class="kpi purple">
        <div class="k-label">System accounts</div>
        <div class="k-val"><?= $hasSystem ?></div>
        <div class="k-sub">of <?= $totalEmp ?> have login</div>
    </div>
    <div class="kpi">
        <div class="k-label">Animals assigned</div>
        <div class="k-val"><?= $totalAnimals ?></div>
        <div class="k-sub">across caretakers</div>
    </div>
    <div class="kpi red">
        <div class="k-label">Health records</div>
        <div class="k-val"><?= $totalRecords ?></div>
        <div class="k-sub">filed by vets</div>
    </div>
</div>

<!-- Filters -->
<details class="fp" <?= $hasFilters?'open':'' ?>>
    <summary>Filters &amp; Search</summary>
    <form method="GET">
        <div class="fg-grid">
            <div class="fg" style="grid-column:span 2">
                <label>Search name</label>
                <input type="text" name="search" value="<?= htmlspecialchars($f_search) ?>" placeholder="First or last name...">
            </div>
            <div class="fg">
                <label>Department</label>
                <select name="dept">
                    <option value="">All departments</option>
                    <?php foreach ($departments as $d): ?>
                    <option value="<?= htmlspecialchars($d) ?>" <?= $f_dept===$d?'selected':'' ?>><?= htmlspecialchars($d) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="fg">
                <label>Role</label>
                <select name="role">
                    <option value="">All roles</option>
                    <?php foreach ($roles as $r): ?>
                    <option value="<?= htmlspecialchars($r) ?>" <?= $f_role===$r?'selected':'' ?>><?= htmlspecialchars($r) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="fg">
                <label>Status</label>
                <select name="status">
                    <option value="">All</option>
                    <option value="Active"   <?= $f_status==='Active'?'selected':'' ?>>Active</option>
                    <option value="Inactive" <?= $f_status==='Inactive'?'selected':'' ?>>Inactive</option>
                </select>
            </div>
            <div class="fg">
                <label>Sex</label>
                <select name="sex">
                    <option value="">Any</option>
                    <option value="M" <?= $f_sex==='M'?'selected':'' ?>>Male</option>
                    <option value="F" <?= $f_sex==='F'?'selected':'' ?>>Female</option>
                    <option value="Other" <?= $f_sex==='Other'?'selected':'' ?>>Other</option>
                </select>
            </div>
            <div class="fg"><label>Hire date from</label><input type="date" name="hire_from" value="<?= htmlspecialchars($f_hire_from) ?>"></div>
            <div class="fg"><label>Hire date to</label><input type="date" name="hire_to" value="<?= htmlspecialchars($f_hire_to) ?>"></div>
            <div class="fg"><label>Min salary ($)</label><input type="number" name="sal_min" value="<?= htmlspecialchars($f_sal_min) ?>" placeholder="e.g. 30000"></div>
            <div class="fg"><label>Max salary ($)</label><input type="number" name="sal_max" value="<?= htmlspecialchars($f_sal_max) ?>" placeholder="e.g. 60000"></div>
        </div>
        <div class="fa">
            <button type="submit" class="bfil">Apply filters</button>
            <a href="employees_report.php" class="bres">Reset all</a>
        </div>
    </form>
</details>

<!-- Tabs -->
<div class="tab-nav">
    <button class="tab-btn active" onclick="showTab('overview')">📊 Overview</button>
    <button class="tab-btn" onclick="showTab('departments')">🏢 Departments</button>
    <button class="tab-btn" onclick="showTab('caretakers')">🐾 Caretakers</button>
    <button class="tab-btn" onclick="showTab('vets')">🩺 Vets</button>
    <button class="tab-btn" onclick="showTab('salary')">💰 Salary</button>
    <button class="tab-btn" onclick="showTab('directory')">📋 Directory</button>
</div>

<!-- ═══ OVERVIEW ════════════════════════════════════════════════ -->
<div id="tab-overview" class="tab-content active">
    <div class="chart-grid">
        <div class="cc">
            <h3>Headcount by department</h3>
            <div class="cw"><canvas id="deptChart"></canvas></div>
        </div>
        <div class="cc">
            <h3>Staff by role</h3>
            <div class="cw"><canvas id="roleChart"></canvas></div>
        </div>
    </div>
    <div class="chart-grid">
        <div class="cc">
            <h3>Average salary by department</h3>
            <div class="cw"><canvas id="salDeptChart"></canvas></div>
        </div>
        <div class="cc">
            <h3>Hiring timeline</h3>
            <div class="cw"><canvas id="hireChart"></canvas></div>
        </div>
    </div>
    <div class="two-col">
        <div class="cc">
            <h3>Sex distribution</h3>
            <div class="cw-sm"><canvas id="sexChart"></canvas></div>
        </div>
        <div class="cc">
            <h3>System access</h3>
            <div class="cw-sm"><canvas id="accessChart"></canvas></div>
        </div>
    </div>
</div>

<!-- ═══ DEPARTMENTS ═════════════════════════════════════════════ -->
<div id="tab-departments" class="tab-content">
    <span class="section-hdr">Department breakdown</span>
    <?php if (empty($deptRows)): ?>
        <div class="tw"><p class="no-data">No department data found.</p></div>
    <?php else:
        $maxDSal = max(array_column($deptRows,'TotalSalary') ?: [1]);
    ?>
    <div class="tw"><table>
        <thead><tr>
            <th>Department</th><th>Headcount</th>
            <th>Total salary</th><th>Avg salary</th>
            <th>Min salary</th><th>Max salary</th><th>Bar</th>
        </tr></thead>
        <tbody>
        <?php foreach ($deptRows as $r): ?>
        <tr>
            <td><strong><?= htmlspecialchars($r['Department'] ?? 'Unassigned') ?></strong></td>
            <td style="font-weight:700"><?= $r['Headcount'] ?></td>
            <td class="amt">$<?= number_format($r['TotalSalary'],0) ?></td>
            <td>$<?= number_format($r['AvgSalary'],0) ?></td>
            <td style="color:#888">$<?= number_format($r['MinSalary'],0) ?></td>
            <td style="color:#888">$<?= number_format($r['MaxSalary'],0) ?></td>
            <td><div class="bar-cell"><div class="bar-outer"><div class="bar-inner" style="width:<?= $maxDSal>0?round($r['TotalSalary']/$maxDSal*100):0 ?>%"></div></div></div></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr>
            <td>TOTAL</td>
            <td><?= $totalEmp ?></td>
            <td class="amt">$<?= number_format($totalSalary,0) ?></td>
            <td>$<?= number_format($avgSalary,0) ?></td>
            <td colspan="3"></td>
        </tr></tfoot>
    </table></div>

    <!-- Employees per department -->
    <?php foreach ($deptRows as $dept):
        $deptEmps = array_filter($employees, fn($e) => ($e['Department'] ?? '') === ($dept['Department'] ?? ''));
    ?>
    <span class="section-hdr" style="margin-top:18px"><?= htmlspecialchars($dept['Department'] ?? 'Unassigned') ?> (<?= count($deptEmps) ?>)</span>
    <div class="tw"><table>
        <thead><tr><th>Name</th><th>Role</th><th>Hire date</th><th>Years</th><th>Salary</th><th>Status</th><th>Login</th></tr></thead>
        <tbody>
        <?php foreach ($deptEmps as $e): ?>
        <tr>
            <td><strong><?= htmlspecialchars($e['FullName']) ?></strong></td>
            <td><?= htmlspecialchars($e['Role']) ?></td>
            <td><?= $e['HireDate'] ? date('M j, Y', strtotime($e['HireDate'])) : '—' ?></td>
            <td><?= $e['YearsWorked'] ?? '—' ?> yr</td>
            <td class="amt">$<?= number_format($e['Salary'],0) ?></td>
            <td><span class="bdg bdg-<?= strtolower($e['Status'] ?? 'active') ?>"><?= htmlspecialchars($e['Status'] ?? 'Active') ?></span></td>
            <td><span class="bdg <?= $e['Username'] ? 'bdg-yes' : 'bdg-no' ?>"><?= $e['Username'] ? 'Yes' : 'No' ?></span></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- ═══ CARETAKERS ══════════════════════════════════════════════ -->
<div id="tab-caretakers" class="tab-content">
    <span class="section-hdr">Caretaker workload</span>
    <?php if (empty($caretakerRows)): ?>
        <p class="no-data">No caretakers found.</p>
    <?php else: ?>
    <?php foreach ($caretakerRows as $c):
        $initials = strtoupper(substr($c['Name'],0,1));
    ?>
    <div class="workload-card">
        <div class="wl-avatar"><?= $initials ?></div>
        <div class="wl-info">
            <div class="wl-name"><?= htmlspecialchars($c['Name']) ?></div>
            <div class="wl-sub">Enclosures: <?= htmlspecialchars($c['Enclosures'] ?: 'None assigned') ?></div>
        </div>
        <div class="wl-stat">
            <div class="num"><?= $c['Animals'] ?></div>
            <div class="lbl">animals</div>
        </div>
    </div>
    <?php endforeach; ?>

    <span class="section-hdr" style="margin-top:18px">Animals by caretaker</span>
    <div class="tw"><table>
        <thead><tr><th>Caretaker</th><th>Animal</th><th>Species</th><th>Category</th><th>Enclosure</th><th>Health</th><th>Food stock</th></tr></thead>
        <tbody>
        <?php
        foreach ($caretakerRows as $c):
            $anStmt = $pdo->prepare("
                SELECT a.Name, a.Species, a.Category, a.Health_Status, a.food_stock, e.Enclosure_Name
                FROM animal a
                LEFT JOIN enclosure e ON e.Enclosure_ID = a.Enclosure_ID
                WHERE a.Caretaker_EmployeeID = ?
                ORDER BY a.Name
            ");
            $anStmt->execute([$c['EmployeeID']]);
            $anRows = $anStmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($anRows as $an):
                $hs = strtolower($an['Health_Status'] ?? 'pending');
                $stock = (int)($an['food_stock'] ?? 50);
                $bc = $stock > 40 ? '#2ecc71' : ($stock > 10 ? '#f39c12' : '#e74c3c');
        ?>
        <tr>
            <td><?= htmlspecialchars($c['Name']) ?></td>
            <td><strong><?= htmlspecialchars($an['Name']) ?></strong></td>
            <td><?= htmlspecialchars($an['Species']) ?></td>
            <td><?= htmlspecialchars($an['Category']) ?></td>
            <td><?= htmlspecialchars($an['Enclosure_Name'] ?? '—') ?></td>
            <td><span class="bdg bdg-<?= $hs ?>"><?= htmlspecialchars($an['Health_Status'] ?? '—') ?></span></td>
            <td>
                <div class="bar-cell">
                    <div class="bar-outer"><div class="bar-inner" style="width:<?= $stock ?>%;background:<?= $bc ?>"></div></div>
                    <span style="font-size:.75rem;font-weight:700"><?= $stock ?>%</span>
                </div>
            </td>
        </tr>
        <?php endforeach; endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</div>

<!-- ═══ VETS ════════════════════════════════════════════════════ -->
<div id="tab-vets" class="tab-content">
    <span class="section-hdr">Vet activity summary</span>
    <?php if (empty($vetRows)): ?>
        <p class="no-data">No vets found.</p>
    <?php else: ?>
    <?php foreach ($vetRows as $v):
        $initials = strtoupper(substr($v['Name'],0,1));
    ?>
    <div class="workload-card">
        <div class="wl-avatar" style="background:#2980b9"><?= $initials ?></div>
        <div class="wl-info">
            <div class="wl-name"><?= htmlspecialchars($v['Name']) ?></div>
            <div class="wl-sub">
                Last active: <?= $v['LastActivity'] ? date('M j, Y', strtotime($v['LastActivity'])) : 'No records' ?>
                &nbsp;·&nbsp; <?= $v['Last30Days'] ?> records in last 30 days
            </div>
        </div>
        <div style="display:flex;gap:16px;text-align:center">
            <div class="wl-stat"><div class="num" style="color:#e74c3c"><?= $v['SickCases'] ?></div><div class="lbl">sick cases</div></div>
            <div class="wl-stat"><div class="num" style="color:#27ae60"><?= $v['CuredCases'] ?></div><div class="lbl">cured</div></div>
            <div class="wl-stat"><div class="num"><?= $v['TotalRecords'] ?></div><div class="lbl">total records</div></div>
        </div>
    </div>
    <?php endforeach; ?>

    <span class="section-hdr" style="margin-top:18px">Health records by vet</span>
    <div class="tw"><table>
        <thead><tr><th>Vet</th><th>Animal</th><th>Species</th><th>Diagnosis</th><th>Status</th><th>Record date</th><th>Cured date</th></tr></thead>
        <tbody>
        <?php
        foreach ($vetRows as $v):
            $hrStmt = $pdo->prepare("
                SELECT hr.*, a.Name AS AnimalName, a.Species
                FROM health_record hr
                JOIN animal a ON a.Animal_ID = hr.Animal_ID
                WHERE hr.Veterinarian_ID = ?
                ORDER BY hr.Record_Date DESC
                LIMIT 50
            ");
            $hrStmt->execute([$v['EmployeeID']]);
            $hrRows = $hrStmt->fetchAll(PDO::FETCH_ASSOC);
            if (empty($hrRows)):
        ?>
        <tr><td><?= htmlspecialchars($v['Name']) ?></td><td colspan="6" style="color:#aaa;font-style:italic">No health records yet</td></tr>
        <?php else: foreach ($hrRows as $hr): $hs = strtolower($hr['Health_Status'] ?? 'pending'); ?>
        <tr>
            <td><?= htmlspecialchars($v['Name']) ?></td>
            <td><strong><?= htmlspecialchars($hr['AnimalName']) ?></strong></td>
            <td><?= htmlspecialchars($hr['Species']) ?></td>
            <td style="max-width:180px;font-size:.78rem"><?= htmlspecialchars(substr($hr['Diagnosis'] ?? '—',0,60)) ?><?= strlen($hr['Diagnosis'] ?? '')>60?'…':'' ?></td>
            <td><span class="bdg bdg-<?= $hs ?>"><?= htmlspecialchars($hr['Health_Status'] ?? '—') ?></span></td>
            <td><?= date('M j, Y', strtotime($hr['Record_Date'])) ?></td>
            <td><?= $hr['Cured_Date'] ? '<span style="color:#27ae60;font-weight:600">'.date('M j, Y',strtotime($hr['Cured_Date'])).'</span>' : '<span style="color:#aaa">—</span>' ?></td>
        </tr>
        <?php endforeach; endif; endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</div>

<!-- ═══ SALARY ══════════════════════════════════════════════════ -->
<div id="tab-salary" class="tab-content">
    <span class="section-hdr">Salary analysis</span>
    <div class="chart-grid" style="margin-bottom:18px">
        <div class="cc">
            <h3>Salary distribution by role</h3>
            <div class="cw"><canvas id="salRoleChart"></canvas></div>
        </div>
        <div class="cc">
            <h3>Total salary cost by department</h3>
            <div class="cw"><canvas id="salTotalChart"></canvas></div>
        </div>
    </div>
    <div class="tw"><table>
        <thead><tr><th>Name</th><th>Department</th><th>Role</th><th>Salary</th><th>vs avg</th><th>Hire date</th><th>Years</th><th>Bar</th></tr></thead>
        <tbody>
        <?php
        $sorted = $employees;
        usort($sorted, fn($a,$b) => $b['Salary'] <=> $a['Salary']);
        $maxSal = $sorted ? (float)$sorted[0]['Salary'] : 1;
        foreach ($sorted as $e):
            $diff = $avgSalary > 0 ? (($e['Salary'] - $avgSalary) / $avgSalary) * 100 : 0;
            $diffColor = $diff >= 0 ? '#27ae60' : '#e74c3c';
        ?>
        <tr>
            <td><strong><?= htmlspecialchars($e['FullName']) ?></strong></td>
            <td style="font-size:.78rem;color:#888"><?= htmlspecialchars($e['Department'] ?? '—') ?></td>
            <td><?= htmlspecialchars($e['Role']) ?></td>
            <td class="amt">$<?= number_format($e['Salary'],0) ?></td>
            <td style="color:<?= $diffColor ?>;font-weight:600;font-size:.78rem"><?= ($diff>=0?'+':'').number_format($diff,1) ?>%</td>
            <td style="font-size:.78rem"><?= $e['HireDate'] ? date('M j, Y',strtotime($e['HireDate'])) : '—' ?></td>
            <td style="font-size:.78rem"><?= $e['YearsWorked'] ?? '—' ?> yr</td>
            <td><div class="bar-cell"><div class="bar-outer"><div class="bar-inner" style="width:<?= $maxSal>0?round((float)$e['Salary']/$maxSal*100):0 ?>%"></div></div></div></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr>
            <td colspan="3">TOTAL / AVERAGE</td>
            <td class="amt">$<?= number_format($totalSalary,0) ?> / $<?= number_format($avgSalary,0) ?></td>
            <td colspan="4"></td>
        </tr></tfoot>
    </table></div>
</div>

<!-- ═══ DIRECTORY ═══════════════════════════════════════════════ -->
<div id="tab-directory" class="tab-content">
    <span class="section-hdr">Full employee directory (<?= $totalEmp ?>)</span>
    <div class="tw"><table>
        <thead><tr>
            <th>ID</th><th>Name</th><th>Department</th><th>Role</th>
            <th>Sex</th><th>Age</th><th>Hire date</th><th>Years</th>
            <th>Salary</th><th>Status</th><th>Login</th>
            <th>Animals</th><th>Records</th>
        </tr></thead>
        <tbody>
        <?php foreach ($employees as $e): ?>
        <tr>
            <td style="color:#aaa"><?= $e['EmployeeID'] ?></td>
            <td>
                <strong><?= htmlspecialchars($e['FullName']) ?></strong>
                <?php if ($e['Username']): ?>
                <div style="font-size:.7rem;color:#aaa">@<?= htmlspecialchars($e['Username']) ?></div>
                <?php endif; ?>
            </td>
            <td style="font-size:.78rem"><?= htmlspecialchars($e['Department'] ?? '—') ?></td>
            <td><?= htmlspecialchars($e['Role']) ?></td>
            <td><?= htmlspecialchars($e['Sex'] ?? '—') ?></td>
            <td><?= $e['Age'] ?? '—' ?></td>
            <td style="font-size:.78rem"><?= $e['HireDate'] ? date('M j, Y',strtotime($e['HireDate'])) : '—' ?></td>
            <td><?= $e['YearsWorked'] ?? '—' ?> yr</td>
            <td class="amt">$<?= number_format($e['Salary'],0) ?></td>
            <td><span class="bdg bdg-<?= strtolower($e['Status'] ?? 'active') ?>"><?= htmlspecialchars($e['Status'] ?? 'Active') ?></span></td>
            <td><span class="bdg <?= $e['Username'] ? 'bdg-yes' : 'bdg-no' ?>"><?= $e['Username'] ? htmlspecialchars($e['Username']) : 'No' ?></span></td>
            <td style="font-weight:<?= $e['AnimalsAssigned']>0?'700':'' ?>;color:<?= $e['AnimalsAssigned']>0?'var(--accent-color)':'' ?>"><?= $e['AnimalsAssigned'] ?: '—' ?></td>
            <td style="font-weight:<?= $e['HealthRecords']>0?'700':'' ?>;color:<?= $e['HealthRecords']>0?'#2980b9':'' ?>"><?= $e['HealthRecords'] ?: '—' ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</div>

</div><!-- end .pw -->

<script>
function showTab(name) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    event.currentTarget.classList.add('active');
}

const green  = '#6ac473';
const blue   = '#2980b9';
const orange = '#e67e22';
const purple = '#8e44ad';
const red    = '#e74c3c';
const teal   = '#1abc9c';
const colors = [green,blue,orange,purple,red,teal,'#f39c12','#2c3e50'];

const opts = { responsive:true, maintainAspectRatio:false,
    plugins:{ legend:{ labels:{ font:{ family:'Montserrat,sans-serif', size:11 } } } },
    scales:{ x:{ ticks:{ font:{size:10} } }, y:{ ticks:{ font:{size:10} } } }
};

const deptLabels  = <?= json_encode(array_column($deptRows,'Department')) ?>;
const deptCounts  = <?= json_encode(array_map('intval', array_column($deptRows,'Headcount'))) ?>;
const deptAvgSal  = <?= json_encode(array_map('floatval', array_column($deptRows,'AvgSalary'))) ?>;
const deptTotSal  = <?= json_encode(array_map('floatval', array_column($deptRows,'TotalSalary'))) ?>;
const roleLabels  = <?= json_encode(array_column($roleRows,'Role')) ?>;
const roleCounts  = <?= json_encode(array_map('intval', array_column($roleRows,'Headcount'))) ?>;
const roleAvgSal  = <?= json_encode(array_map('floatval', array_column($roleRows,'AvgSalary'))) ?>;
const hireLabels  = <?= json_encode(array_column($hireRows,'YearHired')) ?>;
const hireCounts  = <?= json_encode(array_map('intval', array_column($hireRows,'Count'))) ?>;
const sexLabels   = <?= json_encode(array_column($sexRows,'Sex')) ?>;
const sexCounts   = <?= json_encode(array_map('intval', array_column($sexRows,'Count'))) ?>;

// Dept headcount donut
new Chart(document.getElementById('deptChart'), {
    type:'doughnut',
    data:{ labels:deptLabels, datasets:[{ data:deptCounts, backgroundColor:colors, borderWidth:2, borderColor:'#fff' }] },
    options:{ responsive:true, maintainAspectRatio:false, cutout:'55%',
        plugins:{ legend:{ position:'bottom', labels:{ font:{size:11} } } } }
});

// Role bar chart
new Chart(document.getElementById('roleChart'), {
    type:'bar',
    data:{ labels:roleLabels, datasets:[{ label:'Employees', data:roleCounts, backgroundColor:colors, borderRadius:4 }] },
    options:{ ...opts, plugins:{ ...opts.plugins, legend:{display:false} },
        scales:{ x:{ ticks:{font:{size:10}} }, y:{ ticks:{ stepSize:1, font:{size:10} } } } }
});

// Avg salary by dept
new Chart(document.getElementById('salDeptChart'), {
    type:'bar',
    data:{ labels:deptLabels, datasets:[{ label:'Avg Salary ($)', data:deptAvgSal, backgroundColor:blue+'cc', borderRadius:4 }] },
    options:{ ...opts, indexAxis:'y', plugins:{ ...opts.plugins, legend:{display:false} },
        scales:{ x:{ ticks:{ callback:v => '$'+v.toLocaleString(), font:{size:10} } }, y:{ ticks:{font:{size:10}} } } }
});

// Hire timeline
new Chart(document.getElementById('hireChart'), {
    type:'bar',
    data:{ labels:hireLabels, datasets:[{ label:'Hires', data:hireCounts, backgroundColor:green+'cc', borderRadius:4 }] },
    options:{ ...opts, plugins:{ ...opts.plugins, legend:{display:false} },
        scales:{ x:{ ticks:{font:{size:10}} }, y:{ ticks:{ stepSize:1, font:{size:10} } } } }
});

// Sex pie
new Chart(document.getElementById('sexChart'), {
    type:'pie',
    data:{ labels:sexLabels, datasets:[{ data:sexCounts, backgroundColor:[blue,orange,purple,teal], borderWidth:2, borderColor:'#fff' }] },
    options:{ responsive:true, maintainAspectRatio:false,
        plugins:{ legend:{ position:'bottom', labels:{font:{size:11}} } } }
});

// System access pie
new Chart(document.getElementById('accessChart'), {
    type:'pie',
    data:{ labels:['Has login','No login'], datasets:[{ data:[<?= $hasSystem ?>,<?= $totalEmp-$hasSystem ?>], backgroundColor:[green,'#ddd'], borderWidth:2, borderColor:'#fff' }] },
    options:{ responsive:true, maintainAspectRatio:false,
        plugins:{ legend:{ position:'bottom', labels:{font:{size:11}} } } }
});

// Salary by role bar
new Chart(document.getElementById('salRoleChart'), {
    type:'bar',
    data:{ labels:roleLabels, datasets:[{ label:'Avg Salary', data:roleAvgSal, backgroundColor:orange+'cc', borderRadius:4 }] },
    options:{ ...opts, indexAxis:'y', plugins:{ ...opts.plugins, legend:{display:false} },
        scales:{ x:{ ticks:{ callback:v => '$'+v.toLocaleString(), font:{size:10} } }, y:{ ticks:{font:{size:10}} } } }
});

// Total salary by dept
new Chart(document.getElementById('salTotalChart'), {
    type:'doughnut',
    data:{ labels:deptLabels, datasets:[{ data:deptTotSal, backgroundColor:colors, borderWidth:2, borderColor:'#fff' }] },
    options:{ responsive:true, maintainAspectRatio:false, cutout:'50%',
        plugins:{ legend:{ position:'bottom', labels:{font:{size:11}} },
            tooltip:{ callbacks:{ label: ctx => ' $'+ctx.parsed.toLocaleString() } } } }
});
</script>
</body>
</html>
