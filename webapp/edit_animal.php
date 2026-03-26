<?php
require_once __DIR__ . '/../db.php';

$id = (int)($_GET["id"] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM animals WHERE AnimalID = ?");
$stmt->execute([$id]);
$animal = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = $_POST["name"];
    $species = $_POST["species"];
    $enclosure = $_POST["enclosure"];

    $stmt = $pdo->prepare("UPDATE animals SET Name=?, Species=?, EnclosureID=? WHERE AnimalID=?");
    $stmt->execute([$name, $species, (int)$enclosure, $id]);

    header("Location: animals_report.php");
    exit();
}
?>

<h2>Edit Animal</h2>

<form method="POST">
    <input type="text" name="name" value="<?= $animal['Name'] ?>" required>
    <input type="text" name="species" value="<?= $animal['Species'] ?>" required>
    <input type="number" name="enclosure" value="<?= $animal['EnclosureID'] ?>" required>
    <button type="submit">Save Changes</button>
</form>
