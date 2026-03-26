<?php
require_once __DIR__ . '/../db.php';

$stmt = $pdo->query("SELECT * FROM tickets");
?>

<h2>Tickets Report</h2>

<table border="1" cellpadding="10">
    <tr>
        <th>Ticket ID</th>
        <th>Customer ID</th>
        <th>Price</th>
        <th>Purchase Date</th>
    </tr>

    <?php
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr>";

        echo "<td>" . $row['TicketID'] . "</td>";
        echo "<td>" . $row['CustomerID'] . "</td>";
        echo "<td>" . $row['Price'] . "</td>";
        echo "<td>" . $row['PurchaseDate'] . "</td>";

        echo "</tr>";
    }
    ?>
</table>
