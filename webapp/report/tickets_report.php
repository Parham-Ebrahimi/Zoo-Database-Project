<?php
require '../db.php';

$result = $db->query("SELECT * FROM tickets");
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
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";

        echo "<td>" . $row['TicketID'] . "</td>";
        echo "<td>" . $row['CustomerID'] . "</td>";
        echo "<td>" . $row['Price'] . "</td>";
        echo "<td>" . $row['PurchaseDate'] . "</td>";

        echo "</tr>";
    }
    ?>
</table>
