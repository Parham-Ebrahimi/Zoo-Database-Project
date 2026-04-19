<?php
/**
 * Remove older resolved SICK vet_alerts for an animal before the DB trigger
 * marks the open SICK alert as resolved. If a row (Animal_ID, SICK, 1) already
 * exists from a past illness, flipping the open row to IsResolved = 1 hits
 * uq_open_vet_alert (duplicate '…-SICK-1').
 *
 * Call only when transitioning animal.Health_Status from Sick to Healthy/Pending.
 */
function vet_alerts_clear_stale_resolved_sick(PDO $pdo, int $animalId): void
{
    if ($animalId <= 0) {
        return;
    }
    $pdo->prepare(
        "DELETE FROM vet_alerts
         WHERE Animal_ID = ?
           AND AlertType = 'SICK'
           AND IsResolved = 1"
    )->execute([$animalId]);
}
