<?php
session_start();
if (!isset($_SESSION['user_id']) && !isset($_SESSION['customer_id'])) {
    header('Location: customer-login.html');
    exit;
}
require 'db.php';
 
$result = $pdo->query("
    SELECT a.Animal_ID, a.Name, a.Species, a.Category, a.Age, a.Sex, 
           e.Enclosure_Name
    FROM animal a
    LEFT JOIN enclosure e ON a.Enclosure_ID = e.Enclosure_ID
");
$animals = $result->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Animals Report</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body { overflow: auto; }
        .dashboard-wrapper { 
            box-sizing: border-box; 
            min-height: 100vh; 
            padding: 40px; 
            background-color: rgba(187, 223, 158, 0.95); 
        }
        .dashboard-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 30px; 
            border-bottom: 3px solid var(--accent-color); 
            padding-bottom: 20px; 
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            background: white; 
            border-radius: 15px; 
            overflow: hidden; 
            box-shadow: 0 4px 10px rgba(0,0,0,0.1); 
        }
        th { 
            background-color: var(--accent-color); 
            color: white; 
            padding: 12px 15px; 
            text-align: left; 
        }
        td { 
            padding: 10px 15px; 
            border-bottom: 1px solid #e0e0e0; 
        }
        tr:hover { 
            background-color: var(--base-color); 
        }
        .btn { 
            padding: 6px 14px; 
            border-radius: 8px; 
            text-decoration: none; 
            font-weight: 600; 
            font-size: 0.85rem; 
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
            margin-bottom: 20px; 
            padding: 10px 20px; 
            background-color: var(--base-color); 
            border-radius: 8px; 
            color: var(--text-color); 
            font-weight: 600; 
            text-decoration: none; 
        }
        .back-btn:hover { 
            background-color: var(--accent-color); 
        }

    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <div class="dashboard-header">
            <h1>Animals Report</h1>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
 
        <a href="admin-dashboard.php" class="back-btn">← Back to Dashboard</a>
 
        <?php if (count($animals) === 0): ?>
            <p>No animals found in the database.</p>
        <?php else: ?>
        <table>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Species</th>
                <th>Category</th>
                <th>Age</th>
                <th>Sex</th>
                <th>Enclosure</th>
                <th>Actions</th>
            </tr>
            <?php foreach ($animals as $row): ?>
            <tr>
                <td><?= $row['Animal_ID'] ?></td>
                <td><?= $row['Name'] ?></td>
                <td><?= $row['Species'] ?></td>
                <td><?= $row['Category'] ?></td>
                <td><?= $row['Age'] ?></td>
                <td><?= $row['Sex'] ?></td>
                <td><?= $row['Enclosure_Name'] ?? 'N/A' ?></td>
                <td>
                    <a href="edit_animal.php?id=<?= $row['Animal_ID'] ?>" class="btn btn-edit">Edit</a>
                    <a href="delete_animal.php?id=<?= $row['Animal_ID'] ?>" class="btn btn-delete" onclick="return confirm('Are you sure?')">Delete</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php endif; ?>
    </div>
</body>
</html>