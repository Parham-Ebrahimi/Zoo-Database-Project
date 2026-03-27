<?php
require_once 'db.php';

$id = $_GET["id"];

$stmt = $pdo->prepare("DELETE FROM employees WHERE EmployeeID = ?");
$stmt->execute([(int)$id]);

header("Location: employees_report.php");
exit();
?>
