<?php
require_once __DIR__ . '/session_bootstrap.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit;
}
require_once 'db.php';

$error   = '';
$success = '';
$userID  = (int)$_SESSION['user_id'];

// Get current user info
$stmt = $pdo->prepare("
    SELECT s.UserID, s.Username, s.PasswordHash, s.Role,
           e.FirstName, e.LastName
    FROM systemuser s
    JOIN employees e ON s.EmployeeID = e.EmployeeID
    WHERE s.UserID = ?
");
$stmt->execute([$userID]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: login.html');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current  = $_POST['current_password']  ?? '';
    $new      = $_POST['new_password']      ?? '';
    $confirm  = $_POST['confirm_password']  ?? '';

    if (empty($current) || empty($new) || empty($confirm)) {
        $error = 'All fields are required.';
    } elseif (!password_verify($current, $user['PasswordHash'])) {
        $error = 'Current password is incorrect.';
    } elseif (strlen($new) < 6) {
        $error = 'New password must be at least 6 characters.';
    } elseif ($new !== $confirm) {
        $error = 'New passwords do not match.';
    } elseif (password_verify($new, $user['PasswordHash'])) {
        $error = 'New password must be different from your current password.';
    } else {
        $hash = password_hash($new, PASSWORD_BCRYPT);
        $pdo->prepare("UPDATE systemuser SET PasswordHash = ? WHERE UserID = ?")
            ->execute([$hash, $userID]);
        $success = 'Password changed successfully.';
    }
}

// Back URL depends on role
$role = strtolower($_SESSION['role'] ?? '');
$backUrl = match(true) {
    $role === 'admin'     => 'dashboard.php',
    $role === 'caretaker' => 'caretaker_dashboard.php',
    $role === 'vet'       => 'vet.php',
    default               => 'dashboard.php'
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body { overflow: auto; }
        .page-wrap {
            box-sizing: border-box;
            min-height: 100vh;
            padding: 30px 40px;
            background-color: var(--base-color);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        .card {
            background: white;
            border-radius: 20px;
            padding: 36px 40px;
            max-width: 460px;
            width: 100%;
            box-shadow: 0 8px 32px rgba(23,103,7,.12);
        }
        .card-header {
            text-align: center;
            margin-bottom: 28px;
        }
        .card-header .avatar {
            width: 64px; height: 64px;
            border-radius: 50%;
            background: var(--accent-color);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.6rem; font-weight: 900; color: white;
            margin: 0 auto 14px;
        }
        .card-header h1 { font-size: 1.5rem; margin: 0 0 4px; }
        .card-header p  { font-size: .88rem; color: #888; margin: 0; }

        .form-group { display: flex; flex-direction: column; gap: 4px; margin-bottom: 14px; }
        .form-group label { font-size: .85rem; font-weight: 600; color: var(--text-color); text-align: left; width: auto; height: auto; background: none; border-radius: 0; }
        .input-wrap { position: relative; }
        .input-wrap input {
            width: 100%; box-sizing: border-box;
            padding: 10px 40px 10px 12px;
            border: 2px solid #ddd; border-radius: 10px;
            font: inherit; font-size: .92rem; background: white;
            transition: border-color .15s;
        }
        .input-wrap input:focus { outline: none; border-color: var(--accent-color); }
        .input-wrap input.err { border-color: #e74c3c; }
        .toggle-pw {
            position: absolute; right: 10px; top: 50%;
            transform: translateY(-50%);
            background: none; border: none; cursor: pointer;
            font-size: 1.1rem; color: #aaa; padding: 0;
            line-height: 1;
        }
        .toggle-pw:hover { color: var(--text-color); }

        .pw-strength { height: 4px; border-radius: 2px; background: #eee; overflow: hidden; margin-top: 5px; }
        .pw-fill { height: 100%; border-radius: 2px; transition: width .2s, background .2s; width: 0; }
        .pw-hint { font-size: .73rem; color: #aaa; margin-top: 2px; }

        .rules {
            background: #f0faf0; border-radius: 8px;
            padding: 10px 14px; margin-bottom: 16px;
            font-size: .8rem; color: #555; line-height: 1.7;
        }
        .rules ul { margin: 0; padding-left: 16px; }
        .rule { color: #aaa; }
        .rule.ok { color: #27ae60; font-weight: 600; }

        .submit-btn {
            width: 100%; padding: 12px;
            background: var(--accent-color); border: none;
            border-radius: 1000px; font: inherit;
            font-weight: 700; font-size: 1rem;
            cursor: pointer; color: var(--text-color);
            margin-top: 4px; transition: background .15s;
        }
        .submit-btn:hover { background: var(--text-color); color: white; }

        .back-link {
            display: block; text-align: center;
            margin-top: 16px; color: #888;
            font-size: .88rem; text-decoration: none;
        }
        .back-link:hover { color: var(--text-color); text-decoration: underline; }

        .msg-error {
            padding: 10px 14px; background: #fde8e8;
            border: 1px solid #f5c6c6; border-radius: 8px;
            color: #e74c3c; font-weight: 600;
            font-size: .88rem; margin-bottom: 16px;
        }
        .msg-success {
            padding: 10px 14px; background: #d4edda;
            border: 1px solid #c3e6cb; border-radius: 8px;
            color: #155724; font-weight: 600;
            font-size: .88rem; margin-bottom: 16px;
        }
        .divider {
            border: none; border-top: 1px solid #eee;
            margin: 20px 0;
        }
    </style>
</head>
<body>
<div class="page-wrap">
    <div class="card">
        <div class="card-header">
            <div class="avatar"><?= strtoupper(substr($user['FirstName'], 0, 1)) ?></div>
            <h1>Change password</h1>
            <p>Logged in as <strong><?= htmlspecialchars($user['FirstName'].' '.$user['LastName']) ?></strong>
               &nbsp;·&nbsp; @<?= htmlspecialchars($user['Username']) ?></p>
        </div>

        <?php if ($error): ?>
            <div class="msg-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="msg-success">✓ <?= $success ?></div>
        <?php endif; ?>

        <form method="POST" id="pwForm">
            <div class="form-group">
                <label>Current password</label>
                <div class="input-wrap">
                    <input type="password" name="current_password" id="current_pw"
                           placeholder="Your current password" required autocomplete="current-password">
                    <button type="button" class="toggle-pw" onclick="toggleVis('current_pw', this)">👁</button>
                </div>
            </div>

            <hr class="divider">

            <div class="form-group">
                <label>New password</label>
                <div class="input-wrap">
                    <input type="password" name="new_password" id="new_pw"
                           placeholder="At least 6 characters" required
                           autocomplete="new-password" oninput="checkAll()">
                    <button type="button" class="toggle-pw" onclick="toggleVis('new_pw', this)">👁</button>
                </div>
                <div class="pw-strength"><div class="pw-fill" id="pwBar"></div></div>
                <div class="pw-hint" id="pwHint">Min 6 characters</div>
            </div>

            <div class="form-group">
                <label>Confirm new password</label>
                <div class="input-wrap">
                    <input type="password" name="confirm_password" id="confirm_pw"
                           placeholder="Repeat new password" required
                           autocomplete="new-password" oninput="checkAll()">
                    <button type="button" class="toggle-pw" onclick="toggleVis('confirm_pw', this)">👁</button>
                </div>
            </div>

            <div class="rules">
                <ul>
                    <li class="rule" id="r-len">At least 6 characters</li>
                    <li class="rule" id="r-upper">Contains an uppercase letter</li>
                    <li class="rule" id="r-num">Contains a number</li>
                    <li class="rule" id="r-match">Passwords match</li>
                </ul>
            </div>

            <button type="submit" class="submit-btn">Update password</button>
        </form>

        <a href="<?= $backUrl ?>" class="back-link">← Back to dashboard</a>
    </div>
</div>

<script>
function toggleVis(id, btn) {
    const input = document.getElementById(id);
    input.type = input.type === 'password' ? 'text' : 'password';
    btn.textContent = input.type === 'password' ? '👁' : '🙈';
}

function checkAll() {
    const pw  = document.getElementById('new_pw').value;
    const con = document.getElementById('confirm_pw').value;

    // Rules
    setRule('r-len',   pw.length >= 6);
    setRule('r-upper', /[A-Z]/.test(pw));
    setRule('r-num',   /[0-9]/.test(pw));
    setRule('r-match', pw.length > 0 && pw === con);

    // Strength bar
    let score = 0;
    if (pw.length >= 6)  score++;
    if (pw.length >= 10) score++;
    if (/[A-Z]/.test(pw)) score++;
    if (/[0-9]/.test(pw)) score++;
    if (/[^A-Za-z0-9]/.test(pw)) score++;

    const levels = [
        {w:0,  c:'#eee',    l:''},
        {w:20, c:'#e74c3c', l:'Too short'},
        {w:40, c:'#e67e22', l:'Weak'},
        {w:60, c:'#f39c12', l:'Fair'},
        {w:80, c:'#2ecc71', l:'Good'},
        {w:100,c:'#27ae60', l:'Strong'},
    ];
    const lvl = levels[Math.min(score, 5)];
    const bar  = document.getElementById('pwBar');
    const hint = document.getElementById('pwHint');
    bar.style.width      = lvl.w + '%';
    bar.style.background = lvl.c;
    hint.textContent     = lvl.l || 'Min 6 characters';
    hint.style.color     = lvl.c === '#eee' ? '#aaa' : lvl.c;

    // Confirm field border
    const confirmInput = document.getElementById('confirm_pw');
    if (con.length > 0) {
        confirmInput.style.borderColor = pw === con ? '#27ae60' : '#e74c3c';
    } else {
        confirmInput.style.borderColor = '#ddd';
    }
}

function setRule(id, ok) {
    const el = document.getElementById(id);
    el.classList.toggle('ok', ok);
    el.textContent = (ok ? '✓ ' : '') + el.textContent.replace('✓ ', '');
}
</script>
</body>
</html>
