<?php
/**
 * True for vet / veterinarian (and similar) role strings from the database.
 *
 * @param string|null $role Explicit role, or null to use $_SESSION['role'].
 */
function staff_is_vet_role(?string $role = null): bool
{
    $r = strtolower(trim((string) ($role ?? ($_SESSION['role'] ?? ''))));
    if ($r === 'vet') {
        return true;
    }
    return str_contains($r, 'veterinar');
}

/**
 * Staff landing page for back links and redirects (matches login routing).
 *
 * @param string|null $role Explicit role, or null to use $_SESSION['role'].
 */
function staff_home_href(?string $role = null): string
{
    if (staff_is_vet_role($role)) {
        return 'vet_dashboard.php';
    }
    $r = strtolower(trim((string) ($role ?? ($_SESSION['role'] ?? ''))));
    if ($r === 'caretaker' || $r === 'keeper') {
        return 'caretaker_dashboard.php';
    }

    return 'dashboard.php';
}
