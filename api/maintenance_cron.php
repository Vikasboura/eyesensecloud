<?php
/**
 * EyeSense Cloud Portal — Maintenance Auto-Billing Cron
 * Runs automatically once per day on first portal page load.
 * Generates bills, marks overdue, sends member email notifications.
 */

require_once __DIR__ . '/portal_config.php';
require_once __DIR__ . '/maintenance_notify.php';

/**
 * Run the auto-billing check for a given client.
 * Safe to call on every page load — uses a daily timestamp guard to no-op after first run.
 *
 * @param PDO    $pdo      eyesense_licenses connection
 * @param string $clientId The portal client ID
 * @return void
 */
function runMaintenanceCron(PDO $pdo, string $clientId): void {
    try {
        $today     = date('Y-m-d');
        $thisMonth = date('Y-m-01');

        // ── Guard: only run once per day per client ──
        $settingsStmt = $pdo->prepare("SELECT cron_last_run FROM maintenance_settings WHERE client_id = ?");
        $settingsStmt->execute([$clientId]);
        $settings = $settingsStmt->fetch();

        if ($settings && !empty($settings['cron_last_run'])) {
            $lastRun = substr($settings['cron_last_run'], 0, 10); // just the date part
            if ($lastRun === $today) {
                return; // Already ran today — nothing to do
            }
        }

        // ── Step 1: Generate bills for current month ──
        generateMonthlyBills($pdo, $clientId, $thisMonth);

        // ── Step 2: Load penalty settings ──
        // penalty_amount = Rs. charged per interval (e.g. Rs. 10)
        // penalty_days   = interval in days (e.g. every 2 days)
        $penaltyStmt = $pdo->prepare("SELECT penalty_amount, penalty_days FROM maintenance_settings WHERE client_id = ?");
        $penaltyStmt->execute([$clientId]);
        $penaltyRow = $penaltyStmt->fetch();
        $penaltyAmt      = (float)($penaltyRow['penalty_amount'] ?? 0);
        $penaltyInterval = max(1, (int)($penaltyRow['penalty_days'] ?? 1));

        // ── Step 3: PARTIAL past due → OVERDUE ──
        $partialPastDue = $pdo->prepare("
            SELECT id FROM maintenance_bills
            WHERE client_id=? AND due_date < CURDATE() AND status = 'PARTIAL'
        ");
        $partialPastDue->execute([$clientId]);
        $partialToOverdue = $partialPastDue->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($partialToOverdue)) {
            $pdo->prepare("
                UPDATE maintenance_bills SET status = 'OVERDUE'
                WHERE client_id = ? AND due_date < CURDATE() AND status = 'PARTIAL'
            ")->execute([$clientId]);
            foreach ($partialToOverdue as $bid) {
                try { sendMemberOverdue($pdo, $clientId, (int)$bid); } catch(Throwable $e) { error_log('[CRON NOTIFY] partial→overdue: '.$e->getMessage()); }
            }
        }

        // ── Step 4: PENDING past due → OVERDUE ──
        $pendingPastDue = $pdo->prepare("
            SELECT id FROM maintenance_bills
            WHERE client_id=? AND due_date < CURDATE() AND status = 'PENDING'
        ");
        $pendingPastDue->execute([$clientId]);
        $pendingToOverdue = $pendingPastDue->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($pendingToOverdue)) {
            $pdo->prepare("
                UPDATE maintenance_bills SET status = 'OVERDUE'
                WHERE client_id = ? AND due_date < CURDATE() AND status = 'PENDING'
            ")->execute([$clientId]);
            foreach ($pendingToOverdue as $bid) {
                try { sendMemberOverdue($pdo, $clientId, (int)$bid); } catch(Throwable $e) { error_log('[CRON NOTIFY] pending→overdue: '.$e->getMessage()); }
            }
        }

        // ── Step 5: Interval-based late fines on OVERDUE bills ──
        // fine_amount = penaltyAmt × floor(days_since_due / penaltyInterval)
        // e.g. Rs.10 every 2 days: day 1=0, day 2=10, day 3=10, day 4=20
        // total_due   = amount_due + previous_due + fine_amount (recalculated)
        if ($penaltyAmt > 0) {
            $overdueBills = $pdo->prepare("
                SELECT id, amount_due, previous_due, fine_amount, due_date
                FROM maintenance_bills
                WHERE client_id = ? AND status = 'OVERDUE'
            ");
            $overdueBills->execute([$clientId]);
            $overdueRows = $overdueBills->fetchAll();

            foreach ($overdueRows as $ob) {
                $daysLate = max(0, (int)((strtotime($today) - strtotime($ob['due_date'])) / 86400));

                if ($daysLate < 1) continue; // not yet overdue by a full day

                // First charge on day 1, then additional every N days
                $intervals = 1 + (int)floor($daysLate / $penaltyInterval);
                $newFine    = round($penaltyAmt * $intervals, 2);
                $baseDue    = (float)$ob['amount_due'] + (float)$ob['previous_due'];
                $newTotal   = max(0, $baseDue + $newFine);
                $oldFine    = (float)$ob['fine_amount'];

                if (abs($newFine - $oldFine) > 0.001) {
                    $pdo->prepare("
                        UPDATE maintenance_bills
                        SET fine_amount = ?, total_due = ?
                        WHERE id = ?
                    ")->execute([$newFine, $newTotal, $ob['id']]);
                    error_log("[MAINTENANCE CRON] Fine Rs.{$oldFine}→Rs.{$newFine} ({$daysLate}d, {$intervals} intervals) bill #{$ob['id']} for {$clientId}");
                }
            }
        }

        // ── Step 5: Update last-run timestamp ──

        $pdo->prepare("
            UPDATE maintenance_settings
            SET cron_last_run = NOW()
            WHERE client_id = ?
        ")->execute([$clientId]);

        // If no settings row exists yet, insert one
        $pdo->prepare("
            INSERT IGNORE INTO maintenance_settings (client_id, cron_last_run)
            VALUES (?, NOW())
        ")->execute([$clientId]);

    } catch (Throwable $e) {
        // Cron MUST never break the portal page load
        error_log("[MAINTENANCE CRON] Error for client {$clientId}: " . $e->getMessage());
    }
}

/**
 * Generate bills for the given month for all active members of a client.
 * Skips members who already have a bill this month (INSERT IGNORE).
 * Carries forward unpaid balance from previous month.
 *
 * @param PDO    $pdo      DB connection
 * @param string $clientId Client ID
 * @param string $month    'Y-m-01' format
 * @return int   Number of new bills created
 */
function generateMonthlyBills(PDO $pdo, string $clientId, string $month): int {
    $prevMonth = date('Y-m-01', strtotime($month . ' -1 month'));

    // Fetch all active members
    $members = $pdo->prepare("SELECT * FROM society_members WHERE client_id = ? AND status = 'active'");
    $members->execute([$clientId]);
    $memberList = $members->fetchAll();

    if (empty($memberList)) return 0;

    $insertBill = $pdo->prepare("
        INSERT IGNORE INTO maintenance_bills
            (client_id, member_id, billing_month, amount_due, previous_due, total_due, due_date, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'PENDING')
    ");

    $inserted = 0;

    foreach ($memberList as $m) {
        // Calculate carry-forward from previous month
        $previousDue = 0;

        $prevBill = $pdo->prepare("
            SELECT b.total_due, b.status, COALESCE(SUM(r.amount_paid), 0) AS paid
            FROM maintenance_bills b
            LEFT JOIN maintenance_receipts r ON r.bill_id = b.id AND r.client_id = b.client_id AND r.review_status = 'VERIFIED'
            WHERE b.client_id = ? AND b.member_id = ? AND b.billing_month = ?
            GROUP BY b.id
        ");
        $prevBill->execute([$clientId, $m['id'], $prevMonth]);
        $prev = $prevBill->fetch();

        if ($prev) {
            $due  = (float)$prev['total_due'];
            $paid = (float)$prev['paid'];

            if ($prev['status'] === 'VERIFIED') {
                if ($paid > $due) {
                    // Overpaid — credit the excess (negative previous_due reduces next month's bill)
                    $previousDue = -1 * ($paid - $due);
                } else {
                    // Exactly paid
                    $previousDue = 0;
                }
            } elseif ($prev['status'] === 'PARTIAL') {
                // Partially paid — carry forward only the remaining balance
                $previousDue = max(0, $due - $paid);
            } else {
                // PENDING / OVERDUE / UPLOADED / REJECTED — carry forward full amount
                $previousDue = $due;
            }
        }

        $amountDue = (float)$m['monthly_amount'];
        $totalDue  = max(0, $amountDue + $previousDue); // never negative bill
        $dueDay    = max(1, min(28, (int)($m['due_day'] ?? 10)));
        $dueDate   = date('Y-m-' . str_pad($dueDay, 2, '0', STR_PAD_LEFT), strtotime($month));

        $insertBill->execute([$clientId, $m['id'], $month, $amountDue, $previousDue, $totalDue, $dueDate]);

        if ($insertBill->rowCount() > 0) {
            $newBillId = (int)$pdo->lastInsertId();
            $inserted++;
            // Notify member about new bill
            try { sendMemberNewBill($pdo, $clientId, $newBillId); } catch(Throwable $e) { error_log('[CRON NOTIFY] newbill: '.$e->getMessage()); }
        }
    }

    if ($inserted > 0) {
        error_log("[MAINTENANCE CRON] Auto-generated {$inserted} bill(s) for {$clientId} ({$month})");
    }

    return $inserted;
}
