<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit;
}
if (!in_array($_SESSION['role'], ['caretaker', 'admin'])) {
    header('Location: dashboard.php');
    exit;
}

require_once 'db.php';

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'update_health') {
        $animalId     = (int)$_POST['animal_id'];
        $healthStatus = in_array($_POST['health_status'], ['Healthy', 'Sick']) ? $_POST['health_status'] : 'Healthy';

        $stmt = $pdo->prepare("UPDATE animal SET Health_Status = ? WHERE Animal_ID = ?");
        $stmt->execute([$healthStatus, $animalId]);

        $message     = 'Health status updated successfully.';
        $messageType = 'success';

    } elseif ($_POST['action'] === 'restock_food') {
        $animalId    = (int)$_POST['animal_id'];
        $restockQty  = max(1, (int)($_POST['restock_qty'] ?? 10));

        $stmt = $pdo->prepare("
            UPDATE animal
            SET food_stock = LEAST(food_stock + ?, 100)
            WHERE Animal_ID = ?
        ");
        $stmt->execute([$restockQty, $animalId]);

        $message     = 'Food restocked successfully.';
        $messageType = 'success';
    }
}

$animals = $pdo->query("
    SELECT
        a.Animal_ID,
        a.Name,
        a.Species,
        a.Category,
        a.Age,
        a.Sex,
        COALESCE(a.Health_Status, 'Healthy')  AS Health_Status,
        COALESCE(a.food_stock,    50)          AS food_stock,
        e.Enclosure_Name
    FROM animal a
    LEFT JOIN enclosure e ON a.Enclosure_ID = e.Enclosure_ID
    ORDER BY a.Name
")->fetchAll();

$totalAnimals   = count($animals);
$sickAnimals    = count(array_filter($animals, fn($a) => $a['Health_Status'] === 'Sick'));
$lowFoodAnimals = count(array_filter($animals, fn($a) => (int)$a['food_stock'] <= 10));

require 'caretaker_view.php';
?>
