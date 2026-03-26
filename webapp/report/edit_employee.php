<?php
require_once __DIR__ . '/../db.php';

$id = (int)($_GET["id"] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM employees WHERE EmployeeID = ?");
$stmt->execute([$id]);
$emp = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $first = $_POST["firstname"];
    $last = $_POST["lastname"];
    $position = $_POST["position"];

    $stmt = $pdo->prepare("UPDATE employees SET FirstName=?, LastName=?, Position=? WHERE EmployeeID=?");
    $stmt->execute([$first, $last, $position, $id]);

    header("Location: employees_report.php");
    exit();
}
?>

<h2>Edit Employee</h2>

<form method="POST">
    <input type="text" name="firstname" value="<?= $emp['FirstName'] ?>" required>
    <input type="text" name="lastname" value="<?= $emp['LastName'] ?>" required>
    <input type="text" name="position" value="<?= $emp['Position'] ?>" required>
    <button type="submit">Save Changes</button>
</form>
