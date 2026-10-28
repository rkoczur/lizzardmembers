<?php
/**
 * Meghirdetett túra szervezői.
 * A `future_tour_organizers` sorai az irányadók; ha nincs ilyen sor, a túra létrehozója (created_by) a szervező.
 */

/** Szervezők listája: id, firstname, lastname, profile_picture, email, phone. */
function getFutureTourOrganizers(PDO $pdo, int $tourId): array
{
    $stmt = $pdo->prepare("
        SELECT u.id, u.firstname, u.lastname, u.profile_picture, u.email, u.phone
        FROM future_tour_organizers o
        JOIN users u ON u.id = o.user_id
        WHERE o.future_tour_id = ?
        ORDER BY o.sort_order, u.lastname, u.firstname
    ");
    $stmt->execute([$tourId]);
    $rows = $stmt->fetchAll();
    if ($rows) return $rows;

    $stmt = $pdo->prepare("
        SELECT u.id, u.firstname, u.lastname, u.profile_picture, u.email, u.phone
        FROM future_tours ft
        JOIN users u ON u.id = ft.created_by
        WHERE ft.id = ?
    ");
    $stmt->execute([$tourId]);
    return $stmt->fetchAll();
}

/** A szervezők felülírása a megadott tag-azonosítókkal (sorrend megtartva). Üres lista → létrehozó. */
function saveFutureTourOrganizers(PDO $pdo, int $tourId, array $userIds): void
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $userIds))));
    $pdo->prepare("DELETE FROM future_tour_organizers WHERE future_tour_id = ?")->execute([$tourId]);
    $ins = $pdo->prepare("
        INSERT INTO future_tour_organizers (future_tour_id, user_id, sort_order)
        SELECT ?, id, ? FROM users WHERE id = ?
    ");
    foreach ($ids as $i => $uid) {
        $ins->execute([$tourId, $i, $uid]);
    }
}

/**
 * Jelentkezési értesítő címzettjei: az aktív adminok (vagy a megadott szerepkörök) + a túra aktív szervezői.
 * Egy személy csak egyszer szerepel. Mezők: id, email, firstname, lastname.
 */
function getTourApplicationNotifyRecipients(PDO $pdo, int $tourId, array $roles = ['admin']): array
{
    $in   = implode(',', array_fill(0, count($roles), '?'));
    $stmt = $pdo->prepare("SELECT id, email, firstname, lastname FROM users WHERE role IN ($in) AND active = 1 AND email <> ''");
    $stmt->execute($roles);
    $recipients = [];
    foreach ($stmt->fetchAll() as $u) $recipients[(int)$u['id']] = $u;

    $active = $pdo->prepare("SELECT id, email, firstname, lastname FROM users WHERE id = ? AND active = 1 AND email <> ''");
    foreach (getFutureTourOrganizers($pdo, $tourId) as $org) {
        if (isset($recipients[(int)$org['id']])) continue;
        $active->execute([(int)$org['id']]);
        if ($u = $active->fetch()) $recipients[(int)$u['id']] = $u;
    }
    return array_values($recipients);
}
