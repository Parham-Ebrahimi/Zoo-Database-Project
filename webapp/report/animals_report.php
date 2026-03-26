<?php
require_once __DIR__ . '/../db.php';

$stmt = $pdo->query("SELECT * FROM animals");
?>

<h2>Animals Report</h2>

<table border="1" cellpadding="10">
    <tr>
        <th>Animal ID</th>
        <th>Name</th>
        <th>Species</th>
        <th>Enclosure ID</th>
    </tr>

    <?php
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr>";

        echo "<td>" . $row['AnimalID'] . "</td>";
        echo "<td>" . $row['Name'] . "</td>";
        echo "<td>" . $row['Species'] . "</td>";
        echo "<td>" . $row['EnclosureID'] . "</td>";

        //buttons to send to the edit and delete pages
        echo "<td>
                <a href='edit_animal.php?id=" . $row['AnimalID'] . "'>Edit</a> |
                <a href='delete_animal.php?id=" . $row['AnimalID'] . "'>Delete</a>
              </td>";        

        echo "</tr>";
    }
    ?>
</table>
