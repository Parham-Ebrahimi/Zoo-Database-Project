<?php
require '../db.php';

$id = $_GET["id"];

$result = $db->query("SELECT * FROM animals WHERE AnimalID = $id");
$animal = $result->fetch_assoc();

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = $_POST["name"];
    $species = $_POST["species"];
    $enclosure = $_POST["enclosure"];

    $stmt = $db->prepare("UPDATE animals SET Name=?, Species=?, EnclosureID=? WHERE AnimalID=?");
    $stmt->bind_param("ssii", $name, $species, $enclosure, $id);
    $stmt->execute();

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
