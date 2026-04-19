<?php

session_start();

// Must be a logged-in vet (or admin)
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once 'staff_home.php';

$roleRaw = strtolower(trim((string) ($_SESSION['role'] ?? '')));
$isAdmin = ($roleRaw === 'admin');

if (!$isAdmin && !staff_is_vet_role()) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

require_once 'db.php';

// Fetch all open alerts joined to animal + enclosure for display
$rows = $pdo->query("
    SELECT
        va.AlertID,
        va.Animal_ID,
        va.AlertType,
        va.Message,
        va.CreatedAt,
        a.Name          AS AnimalName,
        a.Species       AS AnimalSpecies,
        e.Enclosure_Name
    FROM vet_alerts va
    JOIN  animal a    ON va.Animal_ID    = a.Animal_ID
    LEFT JOIN enclosure e ON a.Enclosure_ID = e.Enclosure_ID
    WHERE va.IsResolved = 0
    ORDER BY va.CreatedAt DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Human-readable relative time
function human_time_diff(string $dateStr): string {
    $diff = time() - strtotime($dateStr);
    if ($diff < 60)     return 'Just now';
    if ($diff < 3600)   return floor($diff / 60) . 'm ago';
    if ($diff < 86400)  return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M j, Y', strtotime($dateStr));
}

$alerts = [];
foreach ($rows as $row) {
    $alerts[] = [
        'alertId'      => (int)$row['AlertID'],
        'animalId'     => (int)$row['Animal_ID'],
        'animalName'   => $row['AnimalName'],
        'animalSpecies'=> $row['AnimalSpecies'],
        'enclosure'    => $row['Enclosure_Name'] ?? null,
        'message'      => $row['Message'],
        'createdAt'    => $row['CreatedAt'],
        'timeAgo'      => human_time_diff($row['CreatedAt']),
    ];
}

header('Content-Type: application/json');
echo json_encode([
    'openCount' => count($alerts),
    'alerts'    => $alerts,
]);