<?php
/**
 * _animal_residents.php
 *
 * Reusable partial: renders the "Meet Our [Animal]" table for a species page.
 *
 * Required variables (set before including this file):
 *   $pdo           – active PDO connection (from db.php)
 *   $animalSpecies – string or array of species names (tried as exact match first)
 *   $animalLabel   – display label, e.g. "Lion", "Penguins"
 *
 * Optional:
 *   $animalKeywords – string or array of short keywords for LIKE fallback
 *                     e.g. "Lion", ["Penguin"] — used when exact species name
 *                     doesn't match what's stored in the DB
 */

if (!isset($pdo) || !isset($animalSpecies) || !isset($animalLabel)) {
    return;
}

$speciesList = is_array($animalSpecies) ? $animalSpecies : [$animalSpecies];
$residents   = [];

try {
    // Step 1: exact case-insensitive match on Species
    $ph   = implode(',', array_fill(0, count($speciesList), '?'));
    $stmt = $pdo->prepare("
        SELECT a.Name, a.Age, a.Sex, d.Diet_Type AS Diet, e.Enclosure_Name
        FROM animal a
        LEFT JOIN enclosure e ON a.Enclosure_ID = e.Enclosure_ID
        LEFT JOIN diet d      ON a.Diet_ID       = d.Diet_ID
        WHERE LOWER(a.Species) IN ($ph)
        ORDER BY a.Name
    ");
    $stmt->execute(array_map('strtolower', $speciesList));
    $residents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Step 2: LIKE fallback using $animalKeywords if set, otherwise derive from $animalLabel
    if (empty($residents)) {
        if (isset($animalKeywords)) {
            $keywords = is_array($animalKeywords) ? $animalKeywords : [$animalKeywords];
        } else {
            // Derive from label: split on space, use meaningful words (skip "Our", plurals handled)
            $words    = preg_split('/\s+/', trim($animalLabel));
            $keywords = $words; // try every word in the label as a LIKE pattern
        }

        $orClauses  = implode(' OR ', array_fill(0, count($keywords), 'LOWER(a.Species) LIKE ?'));
        $likeParams = array_map(fn($k) => '%' . strtolower(trim($k)) . '%', $keywords);

        $stmt2 = $pdo->prepare("
            SELECT a.Name, a.Age, a.Sex, d.Diet_Type AS Diet, e.Enclosure_Name
            FROM animal a
            LEFT JOIN enclosure e ON a.Enclosure_ID = e.Enclosure_ID
            LEFT JOIN diet d      ON a.Diet_ID       = d.Diet_ID
            WHERE $orClauses
            ORDER BY a.Name
        ");
        $stmt2->execute($likeParams);
        $residents = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    }
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
                <td><?= $r['Age'] !== null ? htmlspecialchars((string)$r['Age']) . ' yr' : '—' ?></td>
                <td><?= htmlspecialchars($r['Sex'] ?? '—') ?></td>
                <td><?= htmlspecialchars($r['Diet'] ?? '—') ?></td>
                <td><?= htmlspecialchars($r['Enclosure_Name'] ?? '—') ?></td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>