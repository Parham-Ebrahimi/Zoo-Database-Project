<?php


if (!isset($pdo) || !isset($animalSpecies) || !isset($animalLabel)) {
    return; 
}

$speciesList = is_array($animalSpecies) ? $animalSpecies : [$animalSpecies];

$placeholders = implode(',', array_fill(0, count($speciesList), '?'));

try {
    $stmt = $pdo->prepare("
        SELECT
            a.Name,
            a.Age,
            a.Sex,
            d.Diet_Type   AS Diet,
            e.Enclosure_Name
        FROM animal a
        LEFT JOIN enclosure e ON a.Enclosure_ID = e.Enclosure_ID
        LEFT JOIN diet d      ON a.Animal_ID    = d.Animal_ID
        WHERE a.Species IN ($placeholders)
        ORDER BY a.Name
    ");
    $stmt->execute($speciesList);
    $residents = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $residents = [];
}
?>

<h2>Meet Our <?= htmlspecialchars($animalLabel) ?></h2>
<table class="residents-table">
    <thead>
        <tr>
            <th>Name</th>
            <th>Age</th>
            <th>Sex</th>
            <th>Diet</th>
            <th>Enclosure</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($residents)): ?>
            <tr>
                <td colspan="5" style="color:#888;font-style:italic;text-align:center;">
                    No animals currently listed — check back soon.
                </td>
            </tr>
        <?php else: ?>
            <?php foreach ($residents as $r): ?>
            <tr>
                <td><?= htmlspecialchars($r['Name'] ?? '—') ?></td>
                <td><?= htmlspecialchars($r['Age'] !== null ? $r['Age'] . ' yr' : '—') ?></td>
                <td><?= htmlspecialchars($r['Sex'] ?? '—') ?></td>
                <td><?= htmlspecialchars($r['Diet'] ?? '—') ?></td>
                <td><?= htmlspecialchars($r['Enclosure_Name'] ?? '—') ?></td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>