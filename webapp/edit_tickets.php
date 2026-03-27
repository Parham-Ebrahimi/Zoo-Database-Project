<?php
require_once 'db.php';

$id = (int)($_GET["id"] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM tickets WHERE Ticket_ID = ?");
$stmt->execute([$id]);
$ticket = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $price = $_POST["price"];

    $stmt = $pdo->prepare("UPDATE tickets SET Price=? WHERE Ticket_ID=?");
    $stmt->execute([(float)$price, $id]);

    header("Location: tickets_report.php");
    exit();
}
?>

<h2>Edit Ticket</h2>

<form method="POST">
    <input type="number" step="0.01" name="price" value="<?= $ticket['Price'] ?>" required>
    <button type="submit">Save Changes</button>
</form>
