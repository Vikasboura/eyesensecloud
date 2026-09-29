<?php
/**
 * EyeSense Cloud Portal — Society Ledger API (Expense Tracking)
 * Actions: list, add, update, delete, reorder
 */
ob_start();
require_once __DIR__ . '/portal_config.php';
require_once __DIR__ . '/portal_auth.php';
require_once __DIR__ . '/portal_notifications_helper.php';

header('Content-Type: application/json; charset=utf-8');

requirePortalLogin();
$pdo     = get_indsac_db();
$session = getPortalSession();
$cid     = $session['client_id'];
$isSA    = !empty($session['is_superadmin']);
$perms   = getAllPermissions($pdo, $cid, $session['role']);
$isAdmin = $isSA || !empty($perms['MANAGE_MAINTENANCE']);
$user    = $session['username'] ?? 'Unknown';
$empId   = trim((string)($_SESSION['portal_employee_id'] ?? ''));

if (!$isAdmin) json_error('Access denied — Administrators only', 403);

$input  = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $input['action'] ?? ($_GET['action'] ?? '');

try {
    switch ($action) {

        // ── List all ledger entries ──────────────────────────────────────
        case 'list':
            $fromDate = $_GET['from'] ?? '';
            $toDate   = $_GET['to']   ?? '';
            $type     = $_GET['type'] ?? '';

            $where  = ['client_id = ?'];
            $params = [$cid];

            if ($fromDate) { $where[] = 'transaction_date >= ?'; $params[] = $fromDate; }
            if ($toDate)   { $where[] = 'transaction_date <= ?'; $params[] = $toDate; }
            if ($type && in_array($type, ['CREDIT','DEBIT'])) { $where[] = 'transaction_type = ?'; $params[] = $type; }

            $stmt = $pdo->prepare("
                SELECT * FROM society_ledger
                WHERE " . implode(' AND ', $where) . "
                ORDER BY transaction_date ASC, sort_order ASC, id ASC
            ");
            $stmt->execute($params);
            $entries = $stmt->fetchAll();

            // Stats (always full, not filtered)
            $statStmt = $pdo->prepare("
                SELECT
                    COALESCE(SUM(CASE WHEN transaction_type='CREDIT' THEN amount ELSE 0 END), 0) AS total_credit,
                    COALESCE(SUM(CASE WHEN transaction_type='DEBIT'  THEN amount ELSE 0 END), 0) AS total_debit,
                    COUNT(*) AS total_count
                FROM society_ledger WHERE client_id = ?
            ");
            $statStmt->execute([$cid]);
            $stats = $statStmt->fetch();

            // Checksum for sync: hash of max updated_at + count
            $hashStmt = $pdo->prepare("SELECT MAX(GREATEST(created_at, COALESCE(updated_at, created_at))) AS latest, COUNT(*) AS cnt FROM society_ledger WHERE client_id=?");
            $hashStmt->execute([$cid]);
            $h = $hashStmt->fetch();
            $checksum = md5(($h['latest'] ?? '') . ':' . ($h['cnt'] ?? 0));

            json_response([
                'entries'  => $entries,
                'stats'    => $stats,
                'checksum' => $checksum,
            ]);
            break;

        // ── Add a new ledger entry ───────────────────────────────────────
        case 'add':
            $date   = trim($input['transaction_date'] ?? '');
            $type   = strtoupper(trim($input['transaction_type'] ?? ''));
            $amount = floatval($input['amount'] ?? 0);
            $party  = trim($input['party_name'] ?? '');
            $work   = trim($input['work_description'] ?? '');
            $cheque = trim($input['cheque_info'] ?? '') ?: null;
            $notes  = trim($input['notes'] ?? '') ?: null;
            $afterId = (int)($input['after_id'] ?? 0); // Insert after this row

            if (!$date || !strtotime($date)) json_error('Transaction date is required');
            if (!in_array($type, ['CREDIT','DEBIT'])) json_error('Type must be CREDIT or DEBIT');
            if ($amount <= 0) json_error('Amount must be greater than zero');
            if (!$party) json_error('Party name is required');

            // Determine sort_order
            $sortOrder = 0;
            if ($afterId > 0) {
                // Get the target row's sort_order and date
                $targetStmt = $pdo->prepare("SELECT sort_order, transaction_date FROM society_ledger WHERE id=? AND client_id=?");
                $targetStmt->execute([$afterId, $cid]);
                $target = $targetStmt->fetch();
                if ($target) {
                    $sortOrder = $target['sort_order'] + 1;
                    // Shift all subsequent entries on the same date
                    $pdo->prepare("UPDATE society_ledger SET sort_order = sort_order + 1 WHERE client_id=? AND transaction_date=? AND sort_order >= ?")->execute([$cid, $target['transaction_date'], $sortOrder]);
                    // Use the target's date if not specified differently
                    if (!$date) $date = $target['transaction_date'];
                }
            } else {
                // Append at end of that date
                $maxStmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order), -1) + 1 FROM society_ledger WHERE client_id=? AND transaction_date=?");
                $maxStmt->execute([$cid, $date]);
                $sortOrder = (int)$maxStmt->fetchColumn();
            }

            $pdo->prepare("
                INSERT INTO society_ledger
                  (client_id, transaction_date, transaction_type, amount, party_name,
                   work_description, cheque_info, notes, sort_order, created_by)
                VALUES (?,?,?,?,?,?,?,?,?,?)
            ")->execute([$cid, $date, $type, $amount, $party, $work, $cheque, $notes, $sortOrder, $user]);
            $newId = (int)$pdo->lastInsertId();

            recalculateLedgerBalances($pdo, $cid);
            logAudit($pdo, $cid, $user, 'LEDGER_ADD', "Added {$type} Rs.{$amount} - {$party} on {$date} (#{$newId})");
            _ledgerNotifyAdmins(
                $pdo,
                $cid,
                $empId,
                'Ledger Entry Added',
                "{$user} added {$type} Rs." . number_format($amount, 2) . " - {$party}.",
                'INFO',
                ['ledger_id' => $newId, 'event' => 'ledger_add']
            );

            json_response(['success' => true, 'id' => $newId, 'message' => 'Transaction added successfully']);
            break;

        // ── Update an existing entry ─────────────────────────────────────
        case 'update':
            $id     = (int)($input['id'] ?? 0);
            $date   = trim($input['transaction_date'] ?? '');
            $type   = strtoupper(trim($input['transaction_type'] ?? ''));
            $amount = floatval($input['amount'] ?? 0);
            $party  = trim($input['party_name'] ?? '');
            $work   = trim($input['work_description'] ?? '');
            $cheque = trim($input['cheque_info'] ?? '') ?: null;
            $notes  = trim($input['notes'] ?? '') ?: null;
            $expectedUpdatedAt = trim($input['expected_updated_at'] ?? '');

            if (!$id) json_error('Transaction ID required');
            if (!$date || !strtotime($date)) json_error('Transaction date is required');
            if (!in_array($type, ['CREDIT','DEBIT'])) json_error('Type must be CREDIT or DEBIT');
            if ($amount <= 0) json_error('Amount must be greater than zero');
            if (!$party) json_error('Party name is required');

            // Fetch existing for concurrency check
            $stmt = $pdo->prepare("SELECT * FROM society_ledger WHERE id=? AND client_id=?");
            $stmt->execute([$id, $cid]);
            $existing = $stmt->fetch();
            if (!$existing) json_error('Transaction not found', 404);

            // Concurrency guard: check if someone else edited it since the client loaded it
            if ($expectedUpdatedAt) {
                $existingTs = $existing['updated_at'] ?? $existing['created_at'];
                if ($existingTs !== $expectedUpdatedAt) {
                    json_error("This record was modified by {$existing['updated_by']} at {$existingTs}. Please close and reopen the record to see the latest version.", 409);
                }
            }

            $pdo->prepare("
                UPDATE society_ledger
                SET transaction_date=?, transaction_type=?, amount=?, party_name=?,
                    work_description=?, cheque_info=?, notes=?, updated_by=?, updated_at=NOW()
                WHERE id=? AND client_id=?
            ")->execute([$date, $type, $amount, $party, $work, $cheque, $notes, $user, $id, $cid]);

            recalculateLedgerBalances($pdo, $cid);
            logAudit($pdo, $cid, $user, 'LEDGER_UPDATE', "Updated #{$id}: {$type} Rs.{$amount} - {$party} on {$date}");
            _ledgerNotifyAdmins(
                $pdo,
                $cid,
                $empId,
                'Ledger Entry Updated',
                "{$user} updated {$type} Rs." . number_format($amount, 2) . " - {$party}.",
                'WARNING',
                ['ledger_id' => $id, 'event' => 'ledger_update']
            );

            json_response(['success' => true, 'message' => 'Transaction updated successfully']);
            break;

        // ── Delete an entry ──────────────────────────────────────────────
        case 'delete':
            $id = (int)($input['id'] ?? 0);
            if (!$id) json_error('Transaction ID required');

            $stmt = $pdo->prepare("SELECT * FROM society_ledger WHERE id=? AND client_id=?");
            $stmt->execute([$id, $cid]);
            $row = $stmt->fetch();
            if (!$row) json_error('Transaction not found', 404);

            $pdo->prepare("DELETE FROM society_ledger WHERE id=? AND client_id=?")->execute([$id, $cid]);
            recalculateLedgerBalances($pdo, $cid);
            logAudit($pdo, $cid, $user, 'LEDGER_DELETE', "Deleted #{$id}: {$row['transaction_type']} Rs.{$row['amount']} - {$row['party_name']}");
            _ledgerNotifyAdmins(
                $pdo,
                $cid,
                $empId,
                'Ledger Entry Deleted',
                "{$user} deleted {$row['transaction_type']} Rs." . number_format((float)$row['amount'], 2) . " - {$row['party_name']}.",
                'ERROR',
                ['ledger_id' => $id, 'event' => 'ledger_delete']
            );

            json_response(['success' => true, 'message' => 'Transaction deleted']);
            break;

        // ── Reorder entries (drag & drop) ────────────────────────────────
        case 'reorder':
            $orderedIds = $input['ordered_ids'] ?? [];
            if (!is_array($orderedIds) || empty($orderedIds)) json_error('No ordering data provided');

            $pdo->beginTransaction();
            try {
                foreach ($orderedIds as $idx => $id) {
                    $id = (int)$id;
                    if ($id <= 0) continue;
                    $pdo->prepare("UPDATE society_ledger SET sort_order=?, updated_by=?, updated_at=NOW() WHERE id=? AND client_id=?")
                        ->execute([$idx, $user, $id, $cid]);
                }
                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }

            recalculateLedgerBalances($pdo, $cid);
            logAudit($pdo, $cid, $user, 'LEDGER_REORDER', 'Reordered ' . count($orderedIds) . ' ledger entries');
            _ledgerNotifyAdmins(
                $pdo,
                $cid,
                $empId,
                'Ledger Order Updated',
                "{$user} reordered " . count($orderedIds) . ' ledger entries.',
                'INFO',
                ['event' => 'ledger_reorder']
            );

            json_response(['success' => true, 'message' => 'Order updated']);
            break;

        // ── Sync check (lightweight) ─────────────────────────────────────
        case 'sync':
            $hashStmt = $pdo->prepare("SELECT MAX(GREATEST(created_at, COALESCE(updated_at, created_at))) AS latest, COUNT(*) AS cnt FROM society_ledger WHERE client_id=?");
            $hashStmt->execute([$cid]);
            $h = $hashStmt->fetch();
            $checksum = md5(($h['latest'] ?? '') . ':' . ($h['cnt'] ?? 0));
            json_response(['checksum' => $checksum]);
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

// ── Balance Recalculation Helper ─────────────────────────────────────────────

function _ledgerNotifyAdmins(
    PDO $pdo,
    string $clientId,
    string $actorEmployeeId,
    string $title,
    string $message,
    string $kind,
    array $payload
): void {
    try {
        portalNotifyMaintenanceAdmins(
            $pdo,
            $clientId,
            $title,
            $message,
            $kind,
            'expenses.php',
            $payload,
            $actorEmployeeId !== '' ? [$actorEmployeeId] : []
        );
    } catch (Throwable $e) {
        error_log('[PORTAL NOTIFY] ledger: ' . $e->getMessage());
    }
}

function recalculateLedgerBalances(PDO $pdo, string $clientId): void {
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            SELECT id, transaction_type, amount
            FROM society_ledger
            WHERE client_id = ?
            ORDER BY transaction_date ASC, sort_order ASC, id ASC
        ");
        $stmt->execute([$clientId]);
        $rows = $stmt->fetchAll();

        $balance = 0.00;
        $update  = $pdo->prepare("UPDATE society_ledger SET balance=? WHERE id=?");
        foreach ($rows as $r) {
            if ($r['transaction_type'] === 'CREDIT') {
                $balance += (float)$r['amount'];
            } else {
                $balance -= (float)$r['amount'];
            }
            $update->execute([round($balance, 2), $r['id']]);
        }
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("[LEDGER] Balance recalculation failed: " . $e->getMessage());
    }
}
