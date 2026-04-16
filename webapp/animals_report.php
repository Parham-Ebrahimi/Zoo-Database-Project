<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
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
        .dashboard-wrapper { 
            box-sizing: border-box; 
            min-height: 100vh; 
            padding: 40px; 
            background-color: rgba(187, 223, 158, 0.95);
            overflow-y: auto;
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
            overflow: visible;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1); 
        }
        th { 
            background-color: var(--accent-color); 
            color: white; 
            padding: 12px 15px; 
            text-align: center;
        }
        td { 
            padding: 10px 15px; 
            border-bottom: 1px solid #e0e0e0;
            text-align: center;
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
        .sort-bar {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }
        .sort-bar label {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-color);
        }
        .sort-bar select,
        .sort-bar button {
            padding: 7px 12px;
            border-radius: 8px;
            border: 1px solid #ccc;
            font: inherit;
            font-size: 0.9rem;
            cursor: pointer;
            background: white;
        }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <div class="dashboard-header">
            <h1>Animals Report</h1>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
 
        <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
 
        <?php if (count($animals) === 0): ?>
            <p>No animals found in the database.</p>
        <?php else: ?>

        <div class="sort-bar">
            <label>Sort by:</label>
            <select id="sortField">
                <option value="0">ID</option>
                <option value="1">Name</option>
                <option value="2">Species</option>
                <option value="3">Category</option>
                <option value="4">Age</option>
                <option value="5">Sex</option>
                <option value="6">Enclosure</option>
            </select>
            <button id="dirBtn" onclick="toggleDir()">↑ Asc</button>
        </div>

        <table id="animalTable">
            <thead>
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
            </thead>
            <tbody>
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
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <script>
        let sortDir = 1;

        function sortTable() {
            const col = parseInt(document.getElementById('sortField').value);
            const tbody = document.querySelector('#animalTable tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            rows.sort((a, b) => {
                const av = a.cells[col].innerText.trim();
                const bv = b.cells[col].innerText.trim();
                const an = parseFloat(av), bn = parseFloat(bv);
                if (!isNaN(an) && !isNaN(bn)) return (an - bn) * sortDir;
                return av.localeCompare(bv) * sortDir;
            });
            rows.forEach(r => tbody.appendChild(r));
        }

        function toggleDir() {
            sortDir *= -1;
            document.getElementById('dirBtn').textContent = sortDir === 1 ? '↑ Asc' : '↓ Desc';
            sortTable();
        }

        document.getElementById('sortField').addEventListener('change', sortTable);
    </script>
</body>
</html>