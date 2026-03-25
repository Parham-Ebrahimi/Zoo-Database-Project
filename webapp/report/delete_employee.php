<?php
require '../db.php';

$id = $_GET["id"];

$stmt = $db->prepare("DELETE FROM employees WHERE EmployeeID = ?");
$stmt->bind_param("i", $id);
$stmt->execute();

header("Location: employees_report.php");
exit();
?>
