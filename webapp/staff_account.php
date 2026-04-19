<?php
require_once __DIR__ . '/session_bootstrap.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit;
}
if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: dashboard.php');
    exit;
}
require_once __DIR__ . '/db.php';

$userID = (int) $_SESSION['user_id'];
$stmt = $pdo->prepare('
    SELECT s.Username, s.Role, e.FirstName, e.LastName, e.Department
    FROM systemuser s
    JOIN employees e ON e.EmployeeID = s.EmployeeID
    WHERE s.UserID = ?
');
$stmt->execute([$userID]);
$row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
if (!$row) {
    header('Location: login.html');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile — Greenwood Zoo</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body { overflow: auto; }
        .dashboard-wrapper {
            box-sizing: border-box;
            min-height: 100vh;
            padding: 30px 40px;
            background-color: var(--base-color);
        }
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 3px solid var(--accent-color);
            padding-bottom: 15px;
            flex-wrap: wrap;
            gap: 12px;
        }
        .admin-header-actions-inline {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .card {
            background: #fff;
            border-radius: 14px;
            padding: 24px 28px;
            max-width: 520px;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.06);
        }
        .card h2 { margin: 0 0 12px; font-size: 1.2rem; color: var(--text-color); }
        .card dl { margin: 0; display: grid; gap: 10px 16px; }
        .card dt { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.06em; color: #666; font-weight: 700; }
        .card dd { margin: 0; font-size: 0.95rem; font-weight: 600; color: var(--text-color); }
        .card-actions { margin-top: 22px; display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
        .logout-btn {
            padding: 10px 22px;
            background-color: var(--accent-color);
            border: none;
            border-radius: 1000px;
            font: inherit;
            font-weight: 600;
            cursor: pointer;
            color: var(--text-color);
            text-decoration: none;
            display: inline-block;
        }
        .logout-btn:hover { background-color: var(--text-color); color: #fff; text-decoration: none; }
        .back-btn {
            padding: 8px 16px;
            background: #fff;
            border: 2px solid var(--accent-color);
            border-radius: 8px;
            color: var(--text-color);
            font-weight: 600;
            text-decoration: none;
            font-size: 0.88rem;
        }
        .back-btn:hover { background: var(--accent-color); color: #fff; text-decoration: none; }
    </style>
</head>
<body>
<div class="dashboard-wrapper">
    <div class="dashboard-header">
        <h1 style="margin:0;font-size:1.45rem;font-weight:800;color:var(--text-color)">Profile</h1>
        <div class="admin-header-actions-inline">
            <?php include __DIR__ . '/admin_header_cart_profile.inc.php'; ?>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </div>

    <a href="dashboard.php" class="back-btn" style="margin-bottom:16px;display:inline-block">← Back to dashboard</a>

    <div class="card">
        <h2>Signed in as administrator</h2>
        <dl>
            <dt>Name</dt>
            <dd><?= htmlspecialchars(trim(($row['FirstName'] ?? '') . ' ' . ($row['LastName'] ?? ''))) ?></dd>
            <dt>Username</dt>
            <dd><?= htmlspecialchars((string) $row['Username']) ?></dd>
            <dt>Role</dt>
            <dd><?= htmlspecialchars((string) $row['Role']) ?></dd>
            <?php if (!empty($row['Department'])): ?>
            <dt>Department</dt>
            <dd><?= htmlspecialchars((string) $row['Department']) ?></dd>
            <?php endif; ?>
        </dl>
        <div class="card-actions">
            <a href="change-password.php" class="admin-nav-link">Change password</a>
        </div>
    </div>
</div>
</body>
</html>
