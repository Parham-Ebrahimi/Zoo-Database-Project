<?php
require '../db.php';

$id = $_GET["id"];

$result = $db->query("SELECT * FROM employees WHERE EmployeeID = $id");
$emp = $result->fetch_assoc();

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $first = $_POST["firstname"];
    $last = $_POST["lastname"];
    $position = $_POST["position"];

    $stmt = $db->prepare("UPDATE employees SET FirstName=?, LastName=?, Position=? WHERE EmployeeID=?");
    $stmt->bind_param("sssi", $first, $last, $position, $id);
    $stmt->execute();

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
