<?php
require_once __DIR__ . '/../db.php';
$stmt = $pdo->query("SELECT * FROM employees");
?>

<h2>Employees Report</h2>

<table border="1" cellpadding="10">
    <tr>
        <th>Employee ID</th>
        <th>First Name</th>
        <th>Last Name</th>
        <th>Position</th>
    </tr>

    <?php
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr>";

        echo "<td>" . $row['EmployeeID'] . "</td>";
        echo "<td>" . $row['FirstName'] . "</td>";
        echo "<td>" . $row['LastName'] . "</td>";
        echo "<td>" . $row['Position'] . "</td>";

        
        echo "</tr>";
    }
    ?>
</table>
