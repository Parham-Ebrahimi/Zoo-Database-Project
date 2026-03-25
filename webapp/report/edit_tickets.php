<?php
require '../db.php';

$id = $_GET["id"];

$result = $db->query("SELECT * FROM tickets WHERE TicketID = $id");
$ticket = $result->fetch_assoc();

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $customerID = $_POST["customerID"];
    $price = $_POST["price"];

    $stmt = $db->prepare("UPDATE tickets SET CustomerID=?, Price=? WHERE TicketID=?");
    $stmt->bind_param("idi", $customerID, $price, $id);
    $stmt->execute();

    header("Location: tickets_report.php");
    exit();
}
?>

<h2>Edit Ticket</h2>

<form method="POST">
    <input type="number" name="customerID" value="<?= $ticket['CustomerID'] ?>" required>
    <input type="number" step="0.01" name="price" value="<?= $ticket['Price'] ?>" required>
    <button type="submit">Save Changes</button>
</form>
