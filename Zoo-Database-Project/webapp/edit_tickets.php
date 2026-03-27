<?php
require_once __DIR__ . '/../db.php';

$id = (int)($_GET["id"] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM tickets WHERE TicketID = ?");
$stmt->execute([$id]);
$ticket = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $customerID = $_POST["customerID"];
    $price = $_POST["price"];

    $stmt = $pdo->prepare("UPDATE tickets SET CustomerID=?, Price=? WHERE TicketID=?");
    $stmt->execute([(int)$customerID, (float)$price, $id]);

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
