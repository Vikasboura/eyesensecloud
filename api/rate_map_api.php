<?php
/**
 * EyeSense Cloud Portal — Maintenance Rate Map API
 * Actions: list, add, update, delete, lookup, history
 *
 * All write actions require MANAGE_MAINTENANCE permission.
 * lookup is readable by any authenticated portal user.
 */
ob_start();
require_once __DIR__ . '/portal_config.php';
require_once __DIR__ . '/portal_auth.php';

header('Content-Type: application/json; charset=utf-8');
requirePortalLogin();

$pdo     = get_indsac_db();
$session = getPortalSession();
$cid     = $session['client_id'];
$isSA    = !empty($session['is_superadmin']);
$perms   = getAllPermissions($pdo, $cid, $session['role']);
$isAdmin = $isSA || !empty($perms['MANAGE_MAINTENANCE']);
$actor   = $session['username'] ?? $session['employee_id'] ?? 'unknown';

$input  = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $input['action'] ?? ($_GET['action'] ?? '');

try {
    switch ($action) {

        // ── List all rate map rows ──────────────────────────────────────────
        case 'list':
            $stmt = $pdo->prepare("
                SELECT r.*,
                       (SELECT COUNT(*) FROM maintenance_rate_map_history h WHERE h.rate_map_id = r.id) AS history_count
                FROM maintenance_rate_map r
                WHERE r.client_id = ?
                ORDER BY r.plot_size_sqft ASC
            ");
            $stmt->execute([$cid]);
            $rows = $stmt->fetchAll();
            json_response(['rows' => $rows]);
            break;

        // ── Lookup: find matching amount for a given plot size ──────────────
        case 'lookup':
            $sqft = (float)($input['plot_size_sqft'] ?? $_GET['plot_size_sqft'] ?? 0);
            if ($sqft <= 0) json_error('plot_size_sqft required');

            $stmt = $pdo->prepare("
                SELECT id, plot_size_sqft, monthly_amount, label
                FROM maintenance_rate_map
                WHERE client_id = ? AND plot_size_sqft = ?
                LIMIT 1
            ");
            $stmt->execute([$cid, $sqft]);
            $row = $stmt->fetch();
            if ($row) {
                json_response(['found' => true, 'monthly_amount' => (float)$row['monthly_amount'],
                               'label' => $row['label'], 'id' => $row['id']]);
            } else {
                json_response(['found' => false]);
            }
            break;

        // ── Add a new row ───────────────────────────────────────────────────
        case 'add':
            if (!$isAdmin) json_error('Access denied', 403);

            $sqft   = (float)($input['plot_size_sqft'] ?? 0);
            $amount = (float)($input['monthly_amount']  ?? 0);
            $label  = trim($input['label'] ?? '');

            if ($sqft   <= 0) json_error('plot_size_sqft must be > 0');
            if ($amount <= 0) json_error('monthly_amount must be > 0');

            // Check duplicate
            $dup = $pdo->prepare("SELECT id FROM maintenance_rate_map WHERE client_id=? AND plot_size_sqft=?");
            $dup->execute([$cid, $sqft]);
            if ($dup->fetch()) json_error("A rate already exists for {$sqft} sqft. Use update to change it.");

            $pdo->prepare("
                INSERT INTO maintenance_rate_map
                  (client_id, plot_size_sqft, monthly_amount, label, created_by)
                VALUES (?, ?, ?, ?, ?)
            ")->execute([$cid, $sqft, $amount, $label ?: null, $actor]);
            $newId = (int)$pdo->lastInsertId();

            // Record history
            $pdo->prepare("
                INSERT INTO maintenance_rate_map_history
                  (rate_map_id, client_id, plot_size_sqft, old_amount, new_amount, old_label, new_label, change_type, changed_by)
                VALUES (?, ?, ?, NULL, ?, NULL, ?, 'CREATED', ?)
            ")->execute([$newId, $cid, $sqft, $amount, $label ?: null, $actor]);

            logAudit($pdo, $cid, $actor, 'RATE_MAP_ADD',
                "Added: {$sqft} sqft → ₹{$amount}" . ($label ? " ({$label})" : ''));
            json_response(['success' => true, 'id' => $newId,
                'message' => "Rate added: {$sqft} sqft → ₹" . number_format($amount, 2)]);
            break;

        // ── Update an existing row ──────────────────────────────────────────
        case 'update':
            if (!$isAdmin) json_error('Access denied', 403);

            $id     = (int)($input['id'] ?? 0);
            $amount = (float)($input['monthly_amount'] ?? 0);
            $label  = trim($input['label'] ?? '');
            $sqftNew = isset($input['plot_size_sqft']) ? (float)$input['plot_size_sqft'] : null;

            if (!$id)     json_error('Rate map ID required');
            if ($amount <= 0) json_error('monthly_amount must be > 0');

            $existing = $pdo->prepare("SELECT * FROM maintenance_rate_map WHERE id=? AND client_id=?");
            $existing->execute([$id, $cid]);
            $row = $existing->fetch();
            if (!$row) json_error('Rate map entry not found', 404);

            $sqft = $sqftNew ?: (float)$row['plot_size_sqft'];

            // Check for duplicate if sqft is changing
            if ($sqftNew && $sqftNew != (float)$row['plot_size_sqft']) {
                $dup = $pdo->prepare("SELECT id FROM maintenance_rate_map WHERE client_id=? AND plot_size_sqft=? AND id!=?");
                $dup->execute([$cid, $sqftNew, $id]);
                if ($dup->fetch()) json_error("Another entry already exists for {$sqftNew} sqft.");
            }

            $pdo->prepare("
                UPDATE maintenance_rate_map
                SET plot_size_sqft=?, monthly_amount=?, label=?, updated_by=?, updated_at=NOW()
                WHERE id=? AND client_id=?
            ")->execute([$sqft, $amount, $label ?: null, $actor, $id, $cid]);

            // Record history
            $pdo->prepare("
                INSERT INTO maintenance_rate_map_history
                  (rate_map_id, client_id, plot_size_sqft, old_amount, new_amount, old_label, new_label, change_type, changed_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'UPDATED', ?)
            ")->execute([$id, $cid, $sqft,
                (float)$row['monthly_amount'], $amount,
                $row['label'], $label ?: null,
                $actor]);

            logAudit($pdo, $cid, $actor, 'RATE_MAP_UPDATE',
                "Updated #{$id}: {$sqft} sqft ₹{$row['monthly_amount']} → ₹{$amount}");
            json_response(['success' => true,
                'message' => "Rate updated: {$sqft} sqft → ₹" . number_format($amount, 2)]);
            break;

        // ── Delete a row ────────────────────────────────────────────────────
        case 'delete':
            if (!$isAdmin) json_error('Access denied', 403);

            $id = (int)($input['id'] ?? 0);
            if (!$id) json_error('Rate map ID required');

            $existing = $pdo->prepare("SELECT * FROM maintenance_rate_map WHERE id=? AND client_id=?");
            $existing->execute([$id, $cid]);
            $row = $existing->fetch();
            if (!$row) json_error('Rate map entry not found', 404);

            // Record history BEFORE deleting
            $pdo->prepare("
                INSERT INTO maintenance_rate_map_history
                  (rate_map_id, client_id, plot_size_sqft, old_amount, new_amount, old_label, new_label, change_type, changed_by)
                VALUES (?, ?, ?, ?, ?, ?, NULL, 'DELETED', ?)
            ")->execute([$id, $cid, (float)$row['plot_size_sqft'],
                (float)$row['monthly_amount'], (float)$row['monthly_amount'],
                $row['label'], $actor]);

            $pdo->prepare("DELETE FROM maintenance_rate_map WHERE id=? AND client_id=?")->execute([$id, $cid]);

            logAudit($pdo, $cid, $actor, 'RATE_MAP_DELETE',
                "Deleted #{$id}: {$row['plot_size_sqft']} sqft → ₹{$row['monthly_amount']}");
            json_response(['success' => true, 'message' => 'Rate entry deleted']);
            break;

        // ── Get change history for a row ────────────────────────────────────
        case 'history':
            if (!$isAdmin) json_error('Access denied', 403);

            $id = (int)($input['id'] ?? $_GET['id'] ?? 0);
            if (!$id) json_error('Rate map ID required');

            $stmt = $pdo->prepare("
                SELECT * FROM maintenance_rate_map_history
                WHERE rate_map_id=? AND client_id=?
                ORDER BY changed_at DESC
                LIMIT 50
            ");
            $stmt->execute([$id, $cid]);
            json_response(['history' => $stmt->fetchAll()]);
            break;

        default:
            json_error('Unknown action');
    }
} catch (PDOException $e) {
    ob_end_clean();
    json_error('Database error: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    ob_end_clean();
    json_error('Error: ' . $e->getMessage(), 500);
}
