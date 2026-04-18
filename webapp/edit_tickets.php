<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit;
}
if (!in_array($_SESSION['role'], ['admin'], true)) {
    die('Access denied');
}
require_once 'db.php';

$id = (int)($_GET["id"] ?? 0);
if ($id <= 0) {
    header('Location: tickets_report.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM tickets WHERE Ticket_ID = ?");
$stmt->execute([$id]);
$ticket = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$ticket) {
    header('Location: tickets_report.php');
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $price = (float)($_POST["price"] ?? 0);

    $stmt = $pdo->prepare("UPDATE tickets SET Price=? WHERE Ticket_ID=?");
    $stmt->execute([$price, $id]);

    header("Location: tickets_report.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Ticket</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body { overflow: auto; }
        .dashboard-wrapper { box-sizing: border-box; min-height: 100vh; padding: 30px 40px; background-color: var(--base-color); }
        .dashboard-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 20px; 
            border-bottom: 3px solid var(--accent-color); 
            padding-bottom: 15px; 
        }
        .form-card { background: white; border-radius: 15px; padding: 25px 30px; max-width: 700px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        .form-group { 
            display: flex; 
            flex-direction: column; 
            max-width: 260px; 
        }
        .form-group label { 
            font-weight: 600; 
            margin-bottom: 4px; 
            color: var(--text-color); 
            font-size: 0.9rem; 
            width: auto; 
            height: auto; 
            background: none; 
            border-radius: 0; 
            text-align: left; 
        }
        .form-group input { 
            width: 100%; 
            padding: 9px 12px; 
            border: 2px solid #ddd; 
            border-radius: 8px; 
            font: inherit; 
            font-size: 0.95rem; 
            box-sizing: border-box; 
            background-color: white; 
            height: auto; 
        }
        .form-group input:focus { outline: none; border-color: var(--accent-color); }
        .submit-btn { 
            margin-top: 16px; 
            padding: 10px 28px; 
            background-color: var(--accent-color); 
            border: none; 
            border-radius: 1000px; 
            font: inherit; 
            font-weight: 600; 
            cursor: pointer; 
            color: var(--text-color); 
        }
        .submit-btn:hover { background-color: var(--text-color); color: white; }
        .logout-btn { 
            padding: 9px 22px; 
            background-color: var(--accent-color); 
            border: none; 
            border-radius: 1000px; 
            font: inherit; 
            font-weight: 600; 
            cursor: pointer; 
            color: var(--text-color); 
            text-decoration: none; 
        }
        .logout-btn:hover { background-color: var(--text-color); color: white; }
        .back-btn { 
            display: inline-block; 
            margin-bottom: 15px; 
            padding: 8px 18px; 
            background-color: var(--base-color); 
            border-radius: 8px; 
            color: var(--text-color); 
            font-weight: 600; 
            text-decoration: none; 
            border: 2px solid var(--accent-color);
            font-size: 0.9rem; 
        }
        .back-btn:hover { background-color: var(--accent-color); }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <div class="dashboard-header">
            <h1>Edit Ticket</h1>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>

        <a href="tickets_report.php" class="back-btn">← Back to Tickets</a>

        <div class="form-card">
            <form method="POST">
                <div class="form-group">
                    <label>Price</label>
                    <input type="number" step="0.01" min="0" name="price" value="<?= htmlspecialchars((string)$ticket['Price']) ?>" required>
                </div>
                <button type="submit" class="submit-btn">Save Changes</button>
            </form>
        </div>
    </div>
</body>
</html>
