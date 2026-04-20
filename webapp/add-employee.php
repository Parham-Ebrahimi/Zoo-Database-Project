<?php
require_once __DIR__ . '/session_bootstrap.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit;
}
if (!in_array(strtolower($_SESSION['role']), ['admin'])) {
    die("Access denied");
}
require_once 'db.php';

/** OMB-style race categories (single choice; optional field). */
$raceEthnicityOptions = [
    'American Indian or Alaska Native',
    'Asian',
    'Black or African American',
    'Native Hawaiian or Other Pacific Islander',
    'White',
];

$error   = '';
$success = '';
$create_user = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstname   = trim($_POST['firstname']  ?? '');
    $midinit     = trim($_POST['midinit']    ?? '');
    $lastname    = trim($_POST['lastname']   ?? '');
    $role        = trim($_POST['role']       ?? '');
    $department  = trim($_POST['department'] ?? '');
    $salary      = $_POST['salary']          ?? '';
    $hiredate    = $_POST['hiredate']        ?? '';
    $sex         = $_POST['sex']             ?? '';
    $address     = trim($_POST['address']    ?? '');
    $dob         = $_POST['dob']             ?? '';
    $race        = trim($_POST['race']       ?? '');
    $status      = $_POST['status']          ?? 'Active';
    $username    = trim($_POST['username']   ?? '');
    $password    = $_POST['password']        ?? '';
    $create_user = !empty($_POST['create_user']);

    $errors = [];

    if ($race !== '' && !in_array($race, $raceEthnicityOptions, true)) {
        $errors[] = 'Please choose a valid race / ethnicity option, or leave it blank.';
    }

    if (empty($firstname))  $errors[] = 'First name is required.';
    if (empty($lastname))   $errors[] = 'Last name is required.';
    if (empty($role))       $errors[] = 'Role is required.';
    if (empty($sex))        $errors[] = 'Sex is required.';
    if (empty($hiredate))   $errors[] = 'Hire date is required.';
    if (empty($dob))        $errors[] = 'Date of birth is required.';

    if ($salary === '' || $salary === null) {
        $errors[] = 'Salary is required.';
    } elseif ((float)$salary <= 0) {
        $errors[] = 'Salary must be greater than 0.';
    }

    if (!empty($dob) && !empty($hiredate)) {
        $age = (new DateTime($dob))->diff(new DateTime($hiredate))->y;
        if ($age < 18) {
            $errors[] = 'Employee must be at least 18 years old at hire date.';
        }
    }

    if (!empty($hiredate) && strtotime($hiredate) > time()) {
        $errors[] = 'Hire date cannot be in the future.';
    }

    // System user validation
    if ($create_user) {
        if (empty($username)) {
            $errors[] = 'Username is required when creating a login account.';
        } elseif (strlen($username) < 3) {
            $errors[] = 'Username must be at least 3 characters.';
        } else {
            // Check username uniqueness
            $checkUser = $pdo->prepare("SELECT COUNT(*) FROM systemuser WHERE Username = ?");
            $checkUser->execute([$username]);
            if ($checkUser->fetchColumn() > 0) {
                $errors[] = "Username '$username' is already taken.";
            }
        }
        if (empty($password)) {
            $errors[] = 'Password is required when creating a login account.';
        } elseif (strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters.';
        }
    }

    if (!empty($errors)) {
        $error = implode('<br>', $errors);
    } else {
        try {
            $pdo->beginTransaction();

            // Insert employee
            $stmt = $pdo->prepare("
                INSERT INTO employees
                    (FirstName, MidInitial, LastName, Role, Department, Salary,
                     HireDate, Sex, Address, DOB, Race, Status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $firstname,
                $midinit   ?: null,
                $lastname,
                $role,
                $department ?: null,
                (float)$salary,
                $hiredate,
                $sex,
                $address   ?: null,
                $dob,
                $race      ?: null,
                $status,
            ]);
            $employeeID = $pdo->lastInsertId();

            // Create system user if requested
            if ($create_user) {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $sysRole = $role === 'Gift Shop Employee'
                    ? 'Gift Shop Employee'
                    : strtolower($role);

                $stmt2 = $pdo->prepare("
                    INSERT INTO systemuser (EmployeeID, Username, PasswordHash, Role)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt2->execute([$employeeID, $username, $hash, $sysRole]);
            }

            $pdo->commit();

            $success = "Employee <strong>$firstname $lastname</strong> added successfully (ID #$employeeID).";
            if ($create_user) {
                $success .= " Login account created with username <strong>$username</strong>.";
            } else {
                $success .= ' No login account was created — you can add one later.';
            }

            // Clear form
            $firstname = $midinit = $lastname = $role = $department = '';
            $salary = $hiredate = $sex = $address = $dob = $race = '';
            $username = $password = '';
            $status = 'Active';
            $create_user = false;

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Failed to save: ' . htmlspecialchars($e->getMessage());
        }
    }
}

$enclosures = $pdo->query("SELECT Enclosure_ID, Enclosure_Name FROM enclosure ORDER BY Enclosure_Name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Employee</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body { overflow: auto; }
        .dashboard-wrapper { 
            box-sizing:border-box; 
            min-height:100vh; 
            padding:30px 40px; 
            background-color:var(--base-color); 
        }
        .dashboard-header { 
            display:flex; 
            justify-content:space-between; 
            align-items:center; 
            margin-bottom:20px; 
            border-bottom:3px solid var(--accent-color); 
            padding-bottom:15px; 
        }
        .form-card { 
            background:white; 
            border-radius:15px; 
            padding:25px 30px; 
            max-width:780px; 
            box-shadow:0 4px 10px rgba(0,0,0,0.05); 
        }
        .form-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
            gap: 14px;
        }
        .form-group { display:flex; flex-direction:column; gap:4px; min-width:0; }
        .form-group.full { grid-column:1/-1; }
        .form-group label { 
            font-weight:600; 
            font-size:0.88rem; 
            color:var(--text-color); 
            text-align:left; 
            width:auto; 
            height:auto; 
            background:none; 
            border-radius:0; 
        }
        /* Global style.css `form label` is a 50×50 login icon — reset for this staff form row */
        #empForm label.login-toggle-header {
            width: auto !important;
            max-width: 100%;
            height: auto !important;
            min-height: 0;
            display: flex;
            align-items: flex-start;
            gap: 12px 14px;
            box-sizing: border-box;
            background: none !important;
            background-color: transparent !important;
            color: var(--text-color) !important;
            fill: none;
            border-radius: 0;
            font-size: inherit;
            font-weight: inherit;
            justify-content: flex-start;
            flex-shrink: 1;
            padding: 0;
            margin: 0;
            cursor: pointer;
            overflow-wrap: break-word;
            line-height: 1.45;
            text-align: left;
        }
        #empForm label.login-toggle-header .login-toggle-text {
            flex: 1;
            min-width: 0;
        }
        #empForm label.login-toggle-header .login-toggle-title {
            overflow-wrap: break-word;
        }
        .form-group input, .form-group select {
            width:100%; padding:9px 12px; border:2px solid #ddd; border-radius:8px;
            font:inherit; font-size:0.92rem; box-sizing:border-box; background:white; height:auto; flex-grow:0;
        }
        .form-group input:focus, .form-group select:focus { outline:none; border-color:var(--accent-color); }
        .form-group input.error, .form-group select.error { border-color:#e74c3c; }
        form > div { width:auto; display:block; }

        .form-section { grid-column:1/-1; margin:8px 0 2px; padding-bottom:6px; border-bottom:2px solid var(--base-color); }
        .form-section h3 { 
            font-size:0.88rem; 
            font-weight:700; 
            text-transform:uppercase; 
            letter-spacing:0.05em; 
            color:#888; 
            margin:0; 
        }

        .login-toggle {
            grid-column: 1 / -1;
            background: #f0faf0;
            border: 2px solid var(--accent-color);
            border-radius: 12px;
            padding: clamp(14px, 2.5vw, 20px) clamp(14px, 2.5vw, 22px);
            box-sizing: border-box;
            min-width: 0;
            transition: background-color 0.2s, border-color 0.2s;
        }
        .login-toggle:hover {
            background-color: #e8f7e8;
        }
        .login-toggle.active {
            background-color: #e0f5e0;
            border-color: var(--text-color);
        }
        #empForm label.login-toggle-header input[type="checkbox"] {
            width: 18px;
            height: 18px;
            margin-top: 3px;
            flex-shrink: 0;
            cursor: pointer;
            accent-color: var(--accent-color);
        }
        .login-toggle-header .login-toggle-title {
            font-weight: 700;
            font-size: 0.98rem;
            color: var(--text-color);
            display: block;
            line-height: 1.45;
            margin-bottom: 6px;
        }
        .login-toggle-header .hint-text {
            display: block;
            margin-top: 0;
            font-size: 0.8rem;
            color: #666;
            line-height: 1.45;
            overflow-wrap: break-word;
        }
        .login-fields {
            display: none;
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
            gap: 14px 16px;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid rgba(26, 92, 43, 0.22);
        }
        .login-fields.visible {
            display: grid;
        }
        @media (max-width: 640px) {
            .login-fields.visible {
                grid-template-columns: minmax(0, 1fr);
            }
        }

        .submit-btn { 
            margin-top:18px; 
            padding:11px 32px; 
            background-color:var(--accent-color); 
            border:none; 
            border-radius:1000px; 
            font:inherit; 
            font-weight:600; 
            cursor:pointer; 
            color:var(--text-color); 
            font-size:1rem; 
        }
        .submit-btn:hover { background-color:var(--text-color); color:white; }
        .logout-btn { 
            padding:9px 22px; 
            background-color:var(--accent-color); 
            border:none; 
            border-radius:1000px; 
            font:inherit; 
            font-weight:600; 
            cursor:pointer; 
            color:var(--text-color); 
            text-decoration:none; 
        }
        .logout-btn:hover { background-color:var(--text-color); color:white; }
        .back-btn { 
            display:inline-block; 
            margin-bottom:15px; 
            padding:8px 18px; 
            background-color:var(--base-color); 
            border-radius:8px; 
            color:var(--text-color); 
            font-weight:600; 
            text-decoration:none; 
            border:2px solid var(--accent-color); 
            font-size:0.9rem; 
        }
        .back-btn:hover { background-color:var(--accent-color); }
        .msg-error { 
            color:#e74c3c; 
            font-weight:600; 
            margin-bottom:14px; 
            padding:12px 14px; 
            background:#fde8e8; 
            border-radius:8px; 
            border:1px solid #f5c6c6; 
            line-height:1.6; 
        }
        .msg-success { 
            color:#155724; 
            font-weight:600; 
            margin-bottom:14px; 
            padding:12px 14px; 
            background:#d4edda; 
            border-radius:8px; 
            border:1px solid #c3e6cb; 
        }
        .required { color:#e74c3c; }
        .hint-text { font-size:0.74rem; color:#aaa; margin-top:1px; }

        /* Password strength */
        .pw-strength { height:4px; border-radius:2px; margin-top:4px; background:#eee; overflow:hidden; }
        .pw-strength-fill { height:100%; border-radius:2px; transition:width .2s, background .2s; width:0; }
    </style>
</head>
<body>
<div class="dashboard-wrapper">
    <div class="dashboard-header">
        <h1>Add Employee</h1>
        <div class="admin-header-actions-inline">
            <?php include __DIR__ . '/admin_header_cart_profile.inc.php'; ?>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </div>

    <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>

    <div class="form-card">
        <?php if ($error): ?>
            <div class="msg-error"><?= $error ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="msg-success"><?= $success ?></div>
        <?php endif; ?>

        <form method="POST" id="empForm">

            <!-- Personal info -->
            <div class="form-grid">
                <div class="form-section"><h3>Personal information</h3></div>

                <div class="form-group">
                    <label>First name <span class="required">*</span></label>
                    <input type="text" name="firstname" value="<?= htmlspecialchars($firstname ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>Last name <span class="required">*</span></label>
                    <input type="text" name="lastname" value="<?= htmlspecialchars($lastname ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>Middle initial</label>
                    <input type="text" name="midinit" maxlength="1" value="<?= htmlspecialchars($midinit ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Sex <span class="required">*</span></label>
                    <select name="sex" required>
                        <option value="">— Select —</option>
                        <option value="M"     <?= ($sex??'')==='M'    ?'selected':'' ?>>Male</option>
                        <option value="F"     <?= ($sex??'')==='F'    ?'selected':'' ?>>Female</option>
                        <option value="Other" <?= ($sex??'')==='Other'?'selected':'' ?>>Other / Not specified</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Date of birth <span class="required">*</span></label>
                    <input type="date" name="dob" value="<?= htmlspecialchars($dob ?? '') ?>"
                           max="<?= date('Y-m-d', strtotime('-18 years')) ?>" required>
                    <span class="hint-text">Must be 18+ at hire date</span>
                </div>
                <div class="form-group">
                    <label>Race / Ethnicity</label>
                    <select name="race" id="raceSelect">
                        <option value="">Select</option>
                        <?php foreach ($raceEthnicityOptions as $raceOpt): ?>
                            <option value="<?= htmlspecialchars($raceOpt) ?>"<?= (($race ?? '') === $raceOpt) ? ' selected' : '' ?>><?= htmlspecialchars($raceOpt) ?></option>
                        <?php endforeach; ?>
                    </select>
    
                </div>
                <div class="form-group full">
                    <label>Address</label>
                    <input type="text" name="address" value="<?= htmlspecialchars($address ?? '') ?>" placeholder="Street address">
                </div>

                <!-- Employment info -->
                <div class="form-section"><h3>Employment details</h3></div>

                <div class="form-group">
                    <label>Role <span class="required">*</span></label>
                    <select name="role" id="roleSelect" required onchange="syncDepartment()">
                        <option value="">— Select role —</option>
                        <option value="Admin"     <?= ($role??'')==='Admin'    ?'selected':'' ?>>Admin</option>
                        <option value="Caretaker" <?= ($role??'')==='Caretaker'?'selected':'' ?>>Caretaker</option>
                        <option value="Vet"       <?= ($role??'')==='Vet'      ?'selected':'' ?>>Vet</option>
                        <option value="Gift Shop Employee" <?= ($role??'')==='Gift Shop Employee' ?'selected':'' ?>>Gift Shop Employee</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Department</label>
                    <select name="department" id="deptSelect">
                        <option value="">— Auto-filled from role —</option>
                        <option value="Administration" <?= ($department??'')==='Administration'?'selected':'' ?>>Administration</option>
                        <option value="Animal Care"    <?= ($department??'')==='Animal Care'   ?'selected':'' ?>>Animal Care</option>
                        <option value="Veterinary"     <?= ($department??'')==='Veterinary'    ?'selected':'' ?>>Veterinary</option>
                        <option value="Retail"         <?= ($department??'')==='Retail'        ?'selected':'' ?>>Retail</option>
                    </select>
                    <span class="hint-text">Auto-fills when you pick a role</span>
                </div>
                <div class="form-group">
                    <label>Hire date <span class="required">*</span></label>
                    <input type="date" name="hiredate" value="<?= htmlspecialchars($hiredate ?? '') ?>"
                           max="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="Active"   <?= ($status??'Active')==='Active'  ?'selected':'' ?>>Active</option>
                        <option value="Inactive" <?= ($status??'')==='Inactive'?'selected':'' ?>>Inactive</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Salary ($/year) <span class="required">*</span></label>
                    <input type="number" name="salary" step="0.01" min="1"
                           value="<?= htmlspecialchars($salary ?? '') ?>" placeholder="e.g. 40000" required>
                    <span class="hint-text">Must be greater than $0</span>
                </div>

                <div class="login-toggle" id="loginToggleWrap">
                    <label class="login-toggle-header" for="createUserCheck">
                        <input type="checkbox" name="create_user" id="createUserCheck" value="1"
                               <?= !empty($create_user) ? 'checked' : '' ?>>
                        <span class="login-toggle-text">
                            <span class="login-toggle-title">Create login account for this employee</span>
                            <span class="hint-text">Allows them to log into the system</span>
                        </span>
                    </label>
                    <div class="login-fields" id="loginFields">
                        <div class="form-group">
                            <label>Username <span class="required">*</span></label>
                            <input type="text" name="username" id="usernameInput"
                                   value="<?= htmlspecialchars($username ?? '') ?>"
                                   placeholder="e.g. jsmith" autocomplete="off">
                            <span class="hint-text">Min 3 characters, must be unique</span>
                        </div>
                        <div class="form-group">
                            <label>Password <span class="required">*</span></label>
                            <input type="password" name="password" id="passwordInput"
                                   placeholder="Min 6 characters" autocomplete="new-password"
                                   oninput="checkStrength(this.value)">
                            <div class="pw-strength"><div class="pw-strength-fill" id="pwBar"></div></div>
                            <span class="hint-text" id="pwHint">Min 6 characters</span>
                        </div>
                    </div>
                </div>

            </div>

            <button type="submit" class="submit-btn">Add employee</button>
        </form>
    </div>
</div>

<script>
// Auto-fill department when role changes
const roleMap = {
    'Admin':     'Administration',
    'Caretaker': 'Animal Care',
    'Vet':       'Veterinary',
    'Gift Shop Employee': 'Retail',
};
function syncDepartment() {
    const role = document.getElementById('roleSelect').value;
    const dept = document.getElementById('deptSelect');
    if (roleMap[role]) {
        for (let i=0; i<dept.options.length; i++) {
            if (dept.options[i].value === roleMap[role]) {
                dept.selectedIndex = i;
                break;
            }
        }
    }
    autoUsername();
}

/** If “create login” is checked and username is empty, suggest first initial + last name. */
function autoUsername() {
    const cb = document.getElementById('createUserCheck');
    if (!cb || !cb.checked) return;
    const form = document.getElementById('empForm');
    const firstEl = form ? form.querySelector('[name="firstname"]') : null;
    const lastEl = form ? form.querySelector('[name="lastname"]') : null;
    const input = document.getElementById('usernameInput');
    if (!firstEl || !lastEl || !input) return;
    const first = firstEl.value.trim().toLowerCase();
    const last = lastEl.value.trim().toLowerCase();
    if (first && last && !input.value) {
        input.value = first.charAt(0) + last.replace(/\s/g, '');
    }
}

function syncLoginFields() {
    const cb = document.getElementById('createUserCheck');
    const fields = document.getElementById('loginFields');
    const wrap = document.getElementById('loginToggleWrap');
    if (!cb || !fields || !wrap) return;
    fields.classList.toggle('visible', cb.checked);
    wrap.classList.toggle('active', cb.checked);
    autoUsername();
}

document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('empForm');
    const first = form ? form.querySelector('[name="firstname"]') : null;
    const last = form ? form.querySelector('[name="lastname"]') : null;
    if (first) first.addEventListener('blur', autoUsername);
    if (last) last.addEventListener('blur', autoUsername);

    const cb = document.getElementById('createUserCheck');
    if (cb) {
        cb.addEventListener('change', syncLoginFields);
        syncLoginFields();
    }
});


</script>
</body>
</html>