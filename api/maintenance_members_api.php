<?php
/**
 * EyeSense Cloud Portal — Maintenance Members API
 * CRUD operations for society members.
 * When adding a member, auto-creates an employee record so they can log in.
 * Gate: MANAGE_MAINTENANCE or SuperAdmin
 */

require_once __DIR__ . '/portal_config.php';
require_once __DIR__ . '/portal_auth.php';

header('Content-Type: application/json; charset=utf-8');

// Auth
requirePortalLogin();
$pdo     = get_indsac_db();
require_once __DIR__ . '/society_context_holder.php';
SocietyContextHolder::initFromRequest($pdo);
$cid = SocietyContextHolder::getLegacyClientId($pdo);
if (!$cid) {
    json_error('Authentication required: no active society context found.', 401);
}
$currentSocietyId = (int)(SocietyContextHolder::getCurrentSocietyId() ?: ($_SESSION['portal_current_society_id'] ?? 0));
if ($currentSocietyId <= 0 && !empty($cid)) {
    try {
        $sStmt = $pdo->prepare("SELECT id FROM society WHERE legacy_client_id = ? LIMIT 1");
        $sStmt->execute([$cid]);
        $currentSocietyId = (int)$sStmt->fetchColumn();
    } catch (Throwable $e) {}
}
$session = getPortalSession();
$isSA    = !empty($session['is_superadmin']);
$perms   = getAllPermissions($pdo, $cid, SocietyContextHolder::getCurrentRole() ?: ($session['role'] ?? ''));

if (!$isSA && empty($perms['MANAGE_MAINTENANCE'])) {
    json_error('Access denied: MANAGE_MAINTENANCE permission required', 403);
}

// ── Parse input: support JSON body, POST, and GET ──
$rawBody = file_get_contents('php://input');
$input   = json_decode($rawBody, true);
if (!$input) $input = array_merge($_GET, $_POST);
$action = $input['action'] ?? $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {

        // ── LIST ALL MEMBERS + ADMINS ──
        case 'list':
            $stmt = $pdo->prepare("
                SELECT
                    sm.id, sm.member_code, sm.full_name, sm.flat_number, sm.email, sm.mobile,
                    sm.employee_id, sm.monthly_amount, sm.due_day, sm.notes, sm.status,
                    sm.plot_size_sqft, 'member' AS record_type, '' AS emp_db_id
                FROM society_members sm
                WHERE sm.client_id = ?

                UNION ALL

                SELECT
                    e.id, '' AS member_code, e.full_name, '' AS flat_number, e.email, e.phone AS mobile,
                    e.employee_id, 0 AS monthly_amount, 10 AS due_day, '' AS notes, e.status,
                    0 AS plot_size_sqft, 'admin' AS record_type, CAST(e.id AS CHAR) AS emp_db_id
                FROM employees e
                WHERE e.client_id = ? AND e.role = 'ADMIN'
                AND NOT EXISTS (
                    SELECT 1 FROM society_members sm2
                    WHERE sm2.client_id = e.client_id AND sm2.employee_id = e.employee_id
                )

                ORDER BY record_type DESC, flat_number ASC, full_name ASC
            ");
            $stmt->execute([$cid, $cid]);
            json_response(['members' => $stmt->fetchAll()]);
            break;

        // ── ADD MEMBER OR ADMINISTRATOR ──
        case 'add':
            $accountRole = strtoupper(trim($input['account_role'] ?? 'MEMBER'));
            $isAdminRole = ($accountRole === 'ADMINISTRATOR');

            // Base required fields — flat/amount only needed for members
            $baseRequired = ['full_name', 'login_id', 'password'];
            if (!$isAdminRole) {
                $baseRequired = array_merge($baseRequired, ['flat_number', 'monthly_amount']);
            }
            foreach ($baseRequired as $f) {
                if (empty(trim($input[$f] ?? ''))) {
                    json_error("Field '{$f}' is required");
                }
            }

            $loginId  = trim($input['login_id']);
            $password = $input['password'];
            $fullName = trim($input['full_name']);
            $email    = trim($input['email'] ?? '');
            $mobile   = trim($input['mobile'] ?? '');
            $flat     = trim($input['flat_number'] ?? '');
            $amount   = (float)($input['monthly_amount'] ?? 0);
            $dueDay   = (int)($input['due_day'] ?? 10);
            $notes    = trim($input['notes'] ?? '');

            // Validate login_id format
            if (strlen($loginId) < 3) json_error('Login ID must be at least 3 characters');
            if (!preg_match('/^[a-zA-Z0-9._@-]+$/', $loginId)) {
                json_error('Login ID can only contain letters, numbers, dots, underscores, @ and hyphens');
            }
            if (strlen($password) < 4) json_error('Password must be at least 4 characters');

            // Check if login_id already exists
            $existCheck = $pdo->prepare("SELECT id FROM employees WHERE client_id = ? AND employee_id = ?");
            $existCheck->execute([$cid, $loginId]);
            if ($existCheck->fetch()) {
                json_error("Login ID '{$loginId}' is already taken. Choose another.");
            }

            // Hash password
            $passwordHash = generate_werkzeug_password($password);

            $pdo->beginTransaction();

            try {
                if ($isAdminRole) {
                    // ── Administrator path ─────────────────────────────────
                    // access_level = 'ADMIN' → portal_is_superadmin = true at login
                    $empStmt = $pdo->prepare("
                        INSERT INTO employees (client_id, employee_id, full_name, email, phone, role, access_level, status, password_hash, created_by)
                        VALUES (?, ?, ?, ?, ?, 'ADMIN', 'ADMIN', 'active', ?, ?)
                    ");
                    $empStmt->execute([
                        $cid, $loginId, $fullName,
                        $email ?: null, $mobile ?: null,
                        $passwordHash, $session['username'] ?? 'admin'
                    ]);

                    // Optionally create a society_members record if flat_number was given
                    $flat      = trim($input['flat_number'] ?? '');
                    $amount    = (float)($input['monthly_amount'] ?? 0);
                    $dueDay    = (int)($input['due_day'] ?? 10);
                    if ($flat) {
                        $maxCode = $pdo->prepare("SELECT MAX(CAST(SUBSTRING(member_code, 5) AS UNSIGNED)) FROM society_members WHERE client_id=?");
                        $maxCode->execute([$cid]);
                        $memberCode = 'MBR-' . str_pad(($maxCode->fetchColumn() ?: 0) + 1, 4, '0', STR_PAD_LEFT);
                        $pdo->prepare("
                            INSERT INTO society_members
                              (client_id, member_code, full_name, flat_number, monthly_amount, due_day, email, mobile, employee_id, status, created_by)
                            VALUES (?,?,?,?,?,?,?,?,?,'active',?)
                        ")->execute([
                            $cid, $memberCode, $fullName, $flat, $amount, $dueDay,
                            $email ?: null, $mobile ?: null,
                            $loginId, $session['username'] ?? 'admin'
                        ]);
                    }

                    $newId = $pdo->lastInsertId();
                    $pdo->commit();

                    // Welcome email (non-fatal)
                    if ($email) {
                        require_once __DIR__ . '/maintenance_notify.php';
                        try {
                            sendNewAdminWelcome(
                                $pdo, $cid,
                                $email, $fullName,
                                $loginId, $password
                            );
                        } catch (Throwable $notifyErr) {
                            error_log('[ADMIN WELCOME EMAIL] ' . $notifyErr->getMessage());
                        }
                    }

                    logAudit($pdo, $cid, $session['username'] ?? '', 'ADD_ADMIN_ACCOUNT',
                        "Created admin account: {$fullName} (login: {$loginId})");

                    if ($currentSocietyId > 0) {
                        logSocietyAudit($pdo, $currentSocietyId, 'USER_ADDED', [
                            'actor_name'  => $session['username'] ?? 'admin',
                            'target_id'   => (string)$newId,
                            'target_type' => 'administrator',
                            'new_values'  => [
                                'login_id'  => $loginId,
                                'full_name' => $fullName,
                                'role'      => 'ADMIN',
                            ]
                        ]);
                    }

                    json_response([
                        'success'  => true,
                        'login_id' => $loginId,
                        'id'       => $newId,
                        'message'  => "Administrator account created. Login: {$loginId}"
                    ]);

                } else {
                    // ── Member path (original behaviour) ──────────────────
                    // Auto-generate member_code
                    $maxCode = $pdo->prepare("SELECT MAX(CAST(SUBSTRING(member_code, 5) AS UNSIGNED)) AS mx FROM society_members WHERE client_id = ?");
                    $maxCode->execute([$cid]);
                    $nextNum    = ($maxCode->fetchColumn() ?: 0) + 1;
                    $memberCode = 'MBR-' . str_pad($nextNum, 4, '0', STR_PAD_LEFT);

                    // 1. Employee record
                    $empStmt = $pdo->prepare("
                        INSERT INTO employees (client_id, employee_id, full_name, email, phone, role, access_level, status, password_hash, created_by)
                        VALUES (?, ?, ?, ?, ?, 'MEMBER', 'L0', 'active', ?, ?)
                    ");
                    $empStmt->execute([
                        $cid, $loginId, $fullName,
                        $email ?: null, $mobile ?: null,
                        $passwordHash, $session['username'] ?? 'admin'
                    ]);

                    // 2. Society member record
                    $plotSqft = (float)($input['plot_size_sqft'] ?? 0);
                    $memStmt = $pdo->prepare("
                        INSERT INTO society_members (client_id, member_code, full_name, flat_number, email, mobile, employee_id, monthly_amount, due_day, notes, plot_size_sqft, created_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $memStmt->execute([
                        $cid, $memberCode, $fullName, $flat,
                        $email ?: null, $mobile ?: null,
                        $loginId, $amount, $dueDay, $notes,
                        $plotSqft,
                        $session['username'] ?? 'admin'
                    ]);

                    // 3. Ensure MEMBER role has VIEW_MAINTENANCE permission
                    $pdo->prepare("
                        INSERT IGNORE INTO portal_role_permissions (client_id, role, permission, granted)
                        VALUES (?, 'MEMBER', 'VIEW_MAINTENANCE', 1)
                    ")->execute([$cid]);
                    $pdo->prepare("
                        INSERT IGNORE INTO portal_role_permissions (client_id, role, permission, granted)
                        VALUES ('__DEFAULT__', 'MEMBER', 'VIEW_MAINTENANCE', 1)
                    ")->execute();

                    $newId = $pdo->lastInsertId();
                    $pdo->commit();

                    // Welcome email (non-fatal)
                    if ($email) {
                        require_once __DIR__ . '/maintenance_notify.php';
                        try {
                            sendNewMemberWelcome(
                                $pdo, $cid, $email, $fullName,
                                $flat, $memberCode,
                                $loginId, $password, $amount, $dueDay
                            );
                        } catch (Throwable $notifyErr) {
                            error_log('[MEMBER WELCOME EMAIL] ' . $notifyErr->getMessage());
                        }
                    }

                    logAudit($pdo, $cid, $session['username'] ?? '', 'ADD_SOCIETY_MEMBER',
                        "Added member {$memberCode}: {$fullName} (login: {$loginId})");

                    if ($currentSocietyId > 0) {
                        logSocietyAudit($pdo, $currentSocietyId, 'USER_ADDED', [
                            'actor_name'  => $session['username'] ?? 'admin',
                            'target_id'   => (string)$newId,
                            'target_type' => 'member',
                            'new_values'  => [
                                'login_id'    => $loginId,
                                'member_code' => $memberCode,
                                'full_name'   => $fullName,
                                'flat_number' => $flat,
                                'role'        => 'MEMBER',
                            ]
                        ]);
                    }

                    json_response([
                        'success'     => true,
                        'member_code' => $memberCode,
                        'login_id'    => $loginId,
                        'id'          => $newId,
                        'message'     => "Member created. Login: {$loginId}"
                    ]);
                }

            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
            break;

        // ── EDIT MEMBER ──
        case 'edit':
            if (empty($input['id'])) json_error('Member ID required');

            $memberId = (int)$input['id'];

            // Get current member data
            $curStmt = $pdo->prepare("SELECT * FROM society_members WHERE id = ? AND client_id = ?");
            $curStmt->execute([$memberId, $cid]);
            $curMember = $curStmt->fetch();
            if (!$curMember) json_error('Member not found');

            $pdo->beginTransaction();

            try {
                // Update society_members
                $stmt = $pdo->prepare("
                    UPDATE society_members
                    SET full_name = ?, flat_number = ?, email = ?, mobile = ?,
                        monthly_amount = ?, due_day = ?, notes = ?
                    WHERE id = ? AND client_id = ?
                ");
                $stmt->execute([
                    trim($input['full_name'] ?? $curMember['full_name']),
                    trim($input['flat_number'] ?? $curMember['flat_number']),
                    trim($input['email'] ?? ''),
                    trim($input['mobile'] ?? ''),
                    (float)($input['monthly_amount'] ?? $curMember['monthly_amount']),
                    (int)($input['due_day'] ?? $curMember['due_day']),
                    trim($input['notes'] ?? ''),
                    $memberId,
                    $cid
                ]);

                // Update linked employee record too (name, email, phone, role)
                if ($curMember['employee_id']) {
                    $empStmt = $pdo->prepare("SELECT id, role, access_level, global_user_id FROM employees WHERE client_id = ? AND employee_id = ? LIMIT 1");
                    $empStmt->execute([$cid, $curMember['employee_id']]);
                    $curEmp = $empStmt->fetch();

                    $empUpd = $pdo->prepare("
                        UPDATE employees SET full_name = ?, email = ?, phone = ?
                        WHERE client_id = ? AND employee_id = ?
                    ");
                    $empUpd->execute([
                        trim($input['full_name'] ?? $curMember['full_name']),
                        trim($input['email'] ?? '') ?: null,
                        trim($input['mobile'] ?? '') ?: null,
                        $cid,
                        $curMember['employee_id']
                    ]);

                    // Check if role reassignment was requested
                    $targetRole = null;
                    if (!empty($input['account_role'])) {
                        $targetRole = (strtoupper(trim($input['account_role'])) === 'ADMINISTRATOR') ? 'ADMIN' : 'MEMBER';
                    } elseif (!empty($input['role'])) {
                        $targetRole = strtoupper(trim($input['role']));
                    }

                    if ($targetRole !== null && $curEmp && ($curEmp['role'] ?: 'MEMBER') !== $targetRole) {
                        $oldRole = $curEmp['role'] ?: 'MEMBER';
                        $newRole = $targetRole;
                        $newAccess = in_array($newRole, ['ADMIN', 'SUPERADMIN', 'SUPER_ADMIN'], true) ? 'ADMIN' : 'L0';
                        $pdo->prepare("UPDATE employees SET role = ?, access_level = ? WHERE client_id = ? AND employee_id = ?")
                            ->execute([$newRole, $newAccess, $cid, $curMember['employee_id']]);

                        if (!empty($curEmp['global_user_id']) && $currentSocietyId > 0) {
                            $pdo->prepare("UPDATE user_society_mapping SET role = ? WHERE user_id = ? AND society_id = ?")
                                ->execute([$newRole, (int)$curEmp['global_user_id'], $currentSocietyId]);
                        }

                        if ($currentSocietyId > 0) {
                            logSocietyAudit($pdo, $currentSocietyId, 'ROLE_CHANGED', [
                                'actor_name'  => $session['username'] ?? 'admin',
                                'target_id'   => (string)$curMember['employee_id'],
                                'target_type' => 'user_role',
                                'old_values'  => ['role' => $oldRole],
                                'new_values'  => ['role' => $newRole]
                            ]);
                        }
                        logAudit($pdo, $cid, $session['username'] ?? '', 'ROLE_CHANGED',
                            "Changed role of {$curMember['full_name']} ({$curMember['employee_id']}) from {$oldRole} to {$newRole}");
                    }

                    // If password provided, update it
                    $newPass = trim($input['password'] ?? '');
                    if (!empty($newPass)) {
                        if (strlen($newPass) < 4) json_error('Password must be at least 4 characters');
                        $newHash = generate_werkzeug_password($newPass);
                        $pdo->prepare("UPDATE employees SET password_hash = ? WHERE client_id = ? AND employee_id = ?")
                            ->execute([$newHash, $cid, $curMember['employee_id']]);
                    }
                }

                $pdo->commit();

                logAudit($pdo, $cid, $session['username'] ?? '', 'EDIT_SOCIETY_MEMBER',
                    "Edited member ID {$memberId}");

                json_response(['success' => true]);

            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
            break;

        // ── DEACTIVATE (SOFT DELETE) ──
        case 'deactivate':
            if (empty($input['id'])) json_error('Member ID required');

            $memberId = (int)$input['id'];

            // Get employee_id
            $memStmt = $pdo->prepare("SELECT employee_id FROM society_members WHERE id = ? AND client_id = ?");
            $memStmt->execute([$memberId, $cid]);
            $mem = $memStmt->fetch();

            $pdo->beginTransaction();

            $pdo->prepare("UPDATE society_members SET status = 'inactive' WHERE id = ? AND client_id = ?")
                ->execute([$memberId, $cid]);

            // Also deactivate the employee account
            if ($mem && $mem['employee_id']) {
                $pdo->prepare("UPDATE employees SET status = 'inactive' WHERE client_id = ? AND employee_id = ?")
                    ->execute([$cid, $mem['employee_id']]);
            }

            $pdo->commit();

            logAudit($pdo, $cid, $session['username'] ?? '', 'DEACTIVATE_SOCIETY_MEMBER',
                "Deactivated member ID {$memberId}");

            if ($currentSocietyId > 0) {
                logSocietyAudit($pdo, $currentSocietyId, 'USER_REMOVED', [
                    'actor_name'  => $session['username'] ?? 'admin',
                    'target_id'   => (string)$memberId,
                    'target_type' => 'member',
                    'old_values'  => ['status' => 'active'],
                    'new_values'  => ['status' => 'inactive']
                ]);
            }

            json_response(['success' => true]);
            break;

        // ── REACTIVATE ──
        case 'reactivate':
            if (empty($input['id'])) json_error('Member ID required');

            $memberId = (int)$input['id'];

            $memStmt = $pdo->prepare("SELECT employee_id FROM society_members WHERE id = ? AND client_id = ?");
            $memStmt->execute([$memberId, $cid]);
            $mem = $memStmt->fetch();

            $pdo->beginTransaction();

            $pdo->prepare("UPDATE society_members SET status = 'active' WHERE id = ? AND client_id = ?")
                ->execute([$memberId, $cid]);

            if ($mem && $mem['employee_id']) {
                $pdo->prepare("UPDATE employees SET status = 'active' WHERE client_id = ? AND employee_id = ?")
                    ->execute([$cid, $mem['employee_id']]);
            }

            $pdo->commit();

            json_response(['success' => true]);
            break;

        // ── EDIT ADMIN ACCOUNT ──
        case 'edit_admin':
            $empId    = trim($input['employee_id'] ?? '');
            $fullName = trim($input['full_name'] ?? '');
            $email    = trim($input['email'] ?? '');
            $mobile   = trim($input['mobile'] ?? '');
            $password = $input['password'] ?? '';

            if (!$empId) json_error('Employee ID required');
            if (!$fullName) json_error('Full name is required');

            $curStmt = $pdo->prepare("SELECT id, role, access_level, global_user_id FROM employees WHERE client_id=? AND employee_id=? AND role='ADMIN'");
            $curStmt->execute([$cid, $empId]);
            $cur = $curStmt->fetch();
            if (!$cur) json_error('Admin account not found');

            $pdo->prepare("
                UPDATE employees SET full_name=?, email=?, phone=? WHERE client_id=? AND employee_id=?
            ")->execute([$fullName, $email ?: null, $mobile ?: null, $cid, $empId]);

            // Role reassignment if passed
            $targetRole = null;
            if (!empty($input['account_role'])) {
                $targetRole = (strtoupper(trim($input['account_role'])) === 'ADMINISTRATOR') ? 'ADMIN' : 'MEMBER';
            } elseif (!empty($input['role'])) {
                $targetRole = strtoupper(trim($input['role']));
            }

            if ($targetRole !== null && $targetRole !== 'ADMIN') {
                $oldRole = 'ADMIN';
                $newRole = $targetRole;
                $newAccess = in_array($newRole, ['ADMIN', 'SUPERADMIN', 'SUPER_ADMIN'], true) ? 'ADMIN' : 'L0';
                $pdo->prepare("UPDATE employees SET role = ?, access_level = ? WHERE client_id = ? AND employee_id = ?")
                    ->execute([$newRole, $newAccess, $cid, $empId]);

                if (!empty($cur['global_user_id']) && $currentSocietyId > 0) {
                    $pdo->prepare("UPDATE user_society_mapping SET role = ? WHERE user_id = ? AND society_id = ?")
                        ->execute([$newRole, (int)$cur['global_user_id'], $currentSocietyId]);
                }

                if ($currentSocietyId > 0) {
                    logSocietyAudit($pdo, $currentSocietyId, 'ROLE_CHANGED', [
                        'actor_name'  => $session['username'] ?? 'admin',
                        'target_id'   => (string)$empId,
                        'target_type' => 'user_role',
                        'old_values'  => ['role' => $oldRole],
                        'new_values'  => ['role' => $newRole]
                    ]);
                }
                logAudit($pdo, $cid, $session['username'] ?? '', 'ROLE_CHANGED',
                    "Changed role of admin {$fullName} ({$empId}) from {$oldRole} to {$newRole}");
            }

            if ($password && strlen($password) >= 4) {
                $hash = generate_werkzeug_password($password);
                $pdo->prepare("UPDATE employees SET password_hash=? WHERE client_id=? AND employee_id=?")->execute([$hash, $cid, $empId]);
            }

            logAudit($pdo, $cid, $session['username'] ?? '', 'EDIT_ADMIN_ACCOUNT', "Edited admin: {$fullName} ({$empId})");
            json_response(['success' => true, 'message' => 'Administrator account updated']);
            break;

        // ── CHANGE USER ROLE ──
        case 'change_role':
            $empId   = trim($input['employee_id'] ?? '');
            $newRole = strtoupper(trim($input['role'] ?? ''));
            if (!$empId || !$newRole) json_error('employee_id and role are required');

            $empStmt = $pdo->prepare("SELECT id, role, access_level, full_name, global_user_id FROM employees WHERE client_id = ? AND employee_id = ? LIMIT 1");
            $empStmt->execute([$cid, $empId]);
            $emp = $empStmt->fetch();
            if (!$emp) json_error('Employee not found', 404);

            $oldRole = $emp['role'] ?: 'MEMBER';
            if ($oldRole !== $newRole) {
                $accessLevel = in_array($newRole, ['ADMIN', 'SUPERADMIN', 'SUPER_ADMIN'], true) ? 'ADMIN' : 'L0';
                $pdo->prepare("UPDATE employees SET role = ?, access_level = ? WHERE client_id = ? AND employee_id = ?")
                    ->execute([$newRole, $accessLevel, $cid, $empId]);

                if (!empty($emp['global_user_id']) && $currentSocietyId > 0) {
                    $pdo->prepare("UPDATE user_society_mapping SET role = ? WHERE user_id = ? AND society_id = ?")
                        ->execute([$newRole, (int)$emp['global_user_id'], $currentSocietyId]);
                }

                if ($currentSocietyId > 0) {
                    logSocietyAudit($pdo, $currentSocietyId, 'ROLE_CHANGED', [
                        'actor_name'  => $session['username'] ?? 'admin',
                        'target_id'   => (string)$empId,
                        'target_type' => 'user_role',
                        'old_values'  => ['role' => $oldRole],
                        'new_values'  => ['role' => $newRole]
                    ]);
                }
                logAudit($pdo, $cid, $session['username'] ?? '', 'ROLE_CHANGED', "Changed role of {$emp['full_name']} ({$empId}) from {$oldRole} to {$newRole}");
            }

            json_response(['success' => true, 'old_role' => $oldRole, 'new_role' => $newRole]);
            break;

        // ── DEACTIVATE ADMIN ──
        case 'deactivate_admin':
            $empId = trim($input['employee_id'] ?? '');
            if (!$empId) json_error('Employee ID required');
            // Prevent self-deactivation
            if ($empId === ($session['username'] ?? '')) json_error('You cannot deactivate your own account');
            $pdo->prepare("UPDATE employees SET status='inactive' WHERE client_id=? AND employee_id=? AND role='ADMIN'")->execute([$cid, $empId]);
            logAudit($pdo, $cid, $session['username'] ?? '', 'DEACTIVATE_ADMIN', "Deactivated admin: {$empId}");

            if ($currentSocietyId > 0) {
                logSocietyAudit($pdo, $currentSocietyId, 'USER_REMOVED', [
                    'actor_name'  => $session['username'] ?? 'admin',
                    'target_id'   => (string)$empId,
                    'target_type' => 'administrator',
                    'old_values'  => ['status' => 'active'],
                    'new_values'  => ['status' => 'inactive']
                ]);
            }
            json_response(['success' => true]);
            break;

        // ── REACTIVATE ADMIN ──
        case 'reactivate_admin':
            $empId = trim($input['employee_id'] ?? '');
            if (!$empId) json_error('Employee ID required');
            $pdo->prepare("UPDATE employees SET status='active' WHERE client_id=? AND employee_id=? AND role='ADMIN'")->execute([$cid, $empId]);
            logAudit($pdo, $cid, $session['username'] ?? '', 'REACTIVATE_ADMIN', "Reactivated admin: {$empId}");
            json_response(['success' => true]);
            break;

        // ── LIST ADMINS (for assign dropdown in complaints) ──
        case 'list_admins':
            $admStmt = $pdo->prepare("
                SELECT employee_id, full_name, email, status
                FROM employees
                WHERE client_id=? AND role IN ('ADMIN','SUPERADMIN') AND is_deleted=0
                ORDER BY full_name ASC
            ");
            $admStmt->execute([$cid]);
            json_response(['admins' => $admStmt->fetchAll()]);
            break;

                // ── LIST COMPLAINT ASSIGNEES (admins + members) ──
                case 'list_complaint_assignees':
                        $assigneeStmt = $pdo->prepare("
                                SELECT employee_id, full_name, email, role, status
                                FROM employees
                                WHERE client_id=?
                                    AND is_deleted=0
                                    AND status='active'
                                    AND role IN ('SUPERADMIN','ADMIN','MEMBER')
                                ORDER BY
                                    CASE role
                                        WHEN 'SUPERADMIN' THEN 1
                                        WHEN 'ADMIN' THEN 2
                                        ELSE 3
                                    END,
                                    full_name ASC
                        ");
                        $assigneeStmt->execute([$cid]);
                        json_response(['assignees' => $assigneeStmt->fetchAll()]);
                        break;

        // ── GET MEMBER DETAIL ──
        case 'get_member_detail':
            // 1. Validate member_id: must be present and a positive integer
            $rawMemberId = $input['member_id'] ?? null;
            if ($rawMemberId === null || $rawMemberId === '' ||
                !ctype_digit((string)$rawMemberId) || (int)$rawMemberId <= 0) {
                json_error('member_id must be a positive integer', 400);
            }
            $memberId = (int)$rawMemberId;

            try {
                // 2. Fetch member from society_members scoped to $cid
                //    Also check for admin-only records via employees table
                $memStmt = $pdo->prepare("
                    SELECT
                        sm.id, sm.member_code, sm.full_name, sm.flat_number,
                        sm.email, sm.mobile, sm.employee_id,
                        sm.monthly_amount, sm.due_day, sm.status,
                        sm.plot_size_sqft, sm.client_id,
                        'member' AS record_type
                    FROM society_members sm
                    WHERE sm.id = :member_id
                ");
                $memStmt->execute([':member_id' => $memberId]);
                $memberRow = $memStmt->fetch();

                if (!$memberRow) {
                    // Not found in society_members at all
                    json_error('Member not found', 404);
                }

                // client_id mismatch check (403 — do not reveal the record exists)
                if ($memberRow['client_id'] !== $cid) {
                    json_error('Access denied', 403);
                }

                // Build clean member object for response
                $member = [
                    'id'             => (int)$memberRow['id'],
                    'member_code'    => $memberRow['member_code'],
                    'full_name'      => $memberRow['full_name'],
                    'flat_number'    => $memberRow['flat_number'],
                    'email'          => $memberRow['email'],
                    'mobile'         => $memberRow['mobile'],
                    'employee_id'    => $memberRow['employee_id'],
                    'monthly_amount' => (float)$memberRow['monthly_amount'],
                    'due_day'        => (int)$memberRow['due_day'],
                    'status'         => $memberRow['status'],
                    'plot_size_sqft' => (float)$memberRow['plot_size_sqft'],
                    'record_type'    => $memberRow['record_type'],
                    'client_id'      => $memberRow['client_id'],
                ];

                // 3. Bills with latest receipt (correlated subquery, ordered billing_month DESC)
                $billsStmt = $pdo->prepare("
                    SELECT
                        b.id, b.billing_month, b.amount_due, b.previous_due, b.total_due,
                        b.status, b.due_date, b.member_id, b.client_id,
                        r.id          AS r_id,
                        r.receipt_number,
                        r.payment_date,
                        r.amount_paid AS receipt_amount,
                        r.review_status,
                        r.file_path
                    FROM maintenance_bills b
                    LEFT JOIN maintenance_receipts r ON r.id = (
                        SELECT id FROM maintenance_receipts
                        WHERE bill_id = b.id AND client_id = b.client_id
                        ORDER BY uploaded_at DESC
                        LIMIT 1
                    )
                    WHERE b.member_id = :member_id AND b.client_id = :cid
                    ORDER BY b.billing_month DESC
                ");
                $billsStmt->execute([':member_id' => $memberId, ':cid' => $cid]);
                $billRows = $billsStmt->fetchAll();

                $bills = [];
                foreach ($billRows as $row) {
                    $latestReceipt = null;
                    if ($row['r_id'] !== null) {
                        $latestReceipt = [
                            'id'             => (int)$row['r_id'],
                            'receipt_number' => $row['receipt_number'],
                            'payment_date'   => $row['payment_date'],
                            'receipt_amount' => (float)$row['receipt_amount'],
                            'review_status'  => $row['review_status'],
                            'file_path'      => $row['file_path'],
                        ];
                    }
                    $bills[] = [
                        'id'            => (int)$row['id'],
                        'billing_month' => $row['billing_month'],
                        'amount_due'    => (float)$row['amount_due'],
                        'previous_due'  => (float)$row['previous_due'],
                        'total_due'     => (float)$row['total_due'],
                        'status'        => $row['status'],
                        'due_date'      => $row['due_date'],
                        'member_id'     => (int)$row['member_id'],
                        'client_id'     => $row['client_id'],
                        'latest_receipt' => $latestReceipt,
                    ];
                }

                // 4. Summary aggregate query
                $summaryStmt = $pdo->prepare("
                    SELECT
                        COUNT(CASE WHEN status IN ('PENDING','OVERDUE') THEN 1 END)     AS pending_overdue_count,
                        COUNT(CASE WHEN status = 'OVERDUE'              THEN 1 END)     AS overdue_count,
                        COALESCE(SUM(CASE WHEN status IN ('PENDING','OVERDUE') THEN total_due ELSE 0 END), 0) AS pending_overdue_total,
                        COUNT(CASE WHEN status = 'PARTIAL'              THEN 1 END)     AS partial_count,
                        COALESCE(SUM(CASE WHEN status = 'PARTIAL'       THEN total_due ELSE 0 END), 0)       AS partial_total
                    FROM maintenance_bills
                    WHERE member_id = :member_id AND client_id = :cid
                ");
                $summaryStmt->execute([':member_id' => $memberId, ':cid' => $cid]);
                $summaryRow = $summaryStmt->fetch();

                // Compute total_outstanding: sum of total_due for PENDING/OVERDUE/PARTIAL bills
                // minus sum of amount_paid from VERIFIED receipts on those same bills
                $verifiedStmt = $pdo->prepare("
                    SELECT COALESCE(SUM(mr.amount_paid), 0) AS verified_paid
                    FROM maintenance_receipts mr
                    JOIN maintenance_bills mb ON mb.id = mr.bill_id AND mb.client_id = mr.client_id
                    WHERE mr.client_id = :cid
                      AND mr.member_id = :member_id
                      AND mr.review_status = 'VERIFIED'
                      AND mb.status IN ('PENDING', 'OVERDUE', 'PARTIAL')
                ");
                $verifiedStmt->execute([':cid' => $cid, ':member_id' => $memberId]);
                $verifiedPaid = (float)$verifiedStmt->fetchColumn();

                $pendingOverdueTotal = (float)$summaryRow['pending_overdue_total'];
                $partialTotal        = (float)$summaryRow['partial_total'];
                $totalOutstanding    = max(0, ($pendingOverdueTotal + $partialTotal) - $verifiedPaid);

                // Separate pending_count (PENDING only) vs overdue for display
                // pending_count in the summary represents PENDING+OVERDUE combined for requirement 4.1
                $pendingOverdueCount = (int)$summaryRow['pending_overdue_count'];
                $overdueCount        = (int)$summaryRow['overdue_count'];
                // pending-only count = pending_overdue_count - overdue_count
                $pendingCount        = $pendingOverdueCount - $overdueCount;

                $summary = [
                    'pending_count'   => $pendingCount,
                    'pending_total'   => $pendingOverdueTotal,
                    'overdue_count'   => $overdueCount,
                    'overdue_total'   => $pendingOverdueTotal, // combined for display per design
                    'partial_count'   => (int)$summaryRow['partial_count'],
                    'partial_total'   => $partialTotal,
                    'total_outstanding' => $totalOutstanding,
                ];

                // 5. Return HTTP 200 payload
                json_response([
                    'member'  => $member,
                    'bills'   => $bills,
                    'summary' => $summary,
                ]);

            } catch (PDOException $dbErr) {
                error_log('[get_member_detail] DB error for member_id=' . $memberId . ': ' . $dbErr->getMessage());
                json_error('An internal error occurred. Please try again.', 500);
            } catch (Throwable $err) {
                error_log('[get_member_detail] Unexpected error for member_id=' . $memberId . ': ' . $err->getMessage());
                json_error('An internal error occurred. Please try again.', 500);
            }
            break;

        // ── SEND REMINDER ──
        case 'send_reminder':
            // 1. Validate member_id
            $rawMemberId = $input['member_id'] ?? null;
            if ($rawMemberId === null || $rawMemberId === '' ||
                !ctype_digit((string)$rawMemberId) || (int)$rawMemberId <= 0) {
                json_error('member_id must be a positive integer', 400);
            }
            $memberId = (int)$rawMemberId;

            try {
                // 2. Fetch member scoped to $cid
                $memStmt = $pdo->prepare("
                    SELECT id, full_name, email, flat_number, member_code, client_id
                    FROM society_members
                    WHERE id = :member_id AND client_id = :cid
                ");
                $memStmt->execute([':member_id' => $memberId, ':cid' => $cid]);
                $memberRow = $memStmt->fetch();

                if (!$memberRow) {
                    json_error('Member not found', 404);
                }

                // 3. Require a non-empty email
                $memberEmail = trim($memberRow['email'] ?? '');
                if ($memberEmail === '') {
                    json_error('No email address on file for this member', 400);
                }

                // 4. Fetch all PENDING/OVERDUE bills for the member
                $billsStmt = $pdo->prepare("
                    SELECT id, billing_month, total_due, due_date, status
                    FROM maintenance_bills
                    WHERE member_id = :member_id AND client_id = :cid
                      AND status IN ('PENDING', 'OVERDUE')
                    ORDER BY billing_month ASC
                ");
                $billsStmt->execute([':member_id' => $memberId, ':cid' => $cid]);
                $pendingBills = $billsStmt->fetchAll();

                // 5. Compose and send reminder email
                require_once __DIR__ . '/maintenance_notify.php';

                $s           = _mSettings($pdo, $cid);
                $societyName = $s['society_name'] ?? 'Our Society';
                $memberName  = htmlspecialchars($memberRow['full_name']);

                // Build the bill rows for the email body
                $billRowsHtml = '';
                foreach ($pendingBills as $bill) {
                    $month   = date('F Y', strtotime($bill['billing_month']));
                    $due     = number_format((float)$bill['total_due'], 2);
                    $dueDate = date('d M Y', strtotime($bill['due_date']));
                    $status  = htmlspecialchars($bill['status']);
                    $billRowsHtml .= _row("{$month} ({$status})", "Rs. {$due} — Due: {$dueDate}", '#dc2626');
                }

                if (empty($pendingBills)) {
                    $billRowsHtml = '<tr><td colspan="2" style="padding:8px 0;color:#6b7280;font-size:14px;">No outstanding bills at this time.</td></tr>';
                }

                $body = '<p style="color:#374151;font-size:15px;">Dear <strong>' . $memberName . '</strong>,</p>'
                    . '<p style="color:#6b7280;font-size:14px;line-height:1.6;margin:8px 0 16px;">'
                    . 'This is a reminder that you have outstanding maintenance dues. Please clear them at your earliest convenience to avoid further penalties.</p>'
                    . '<div style="background:#fef2f2;border-radius:10px;padding:18px;border:1px solid #fecaca;margin-bottom:16px;">'
                    . '<p style="color:#991b1b;font-size:12px;font-weight:bold;text-transform:uppercase;letter-spacing:0.05em;margin:0 0 10px;">Outstanding Bills</p>'
                    . '<table style="width:100%;border-collapse:collapse;">' . $billRowsHtml . '</table>'
                    . '</div>'
                    . '<p style="color:#6b7280;font-size:13px;">Please log in to the portal to upload your payment receipt as soon as possible.</p>';

                $html    = _emailTpl('#dc2626', 'Maintenance Reminder', $societyName, $body);
                $subject = "[EyeSense] Maintenance Payment Reminder — {$societyName}";

                $sent = indsacSendEmail($memberEmail, $subject, $html);

                if (!$sent) {
                    error_log('[send_reminder] Email delivery failed for member_id=' . $memberId . ' email=' . $memberEmail);
                    json_response(['success' => false, 'message' => 'Email delivery failed']);
                    break;
                }

                // 6. Log audit event
                logAudit($pdo, $cid, $session['username'] ?? '', 'SEND_REMINDER',
                    "Reminder sent to {$memberName} ({$memberEmail}) for member_id={$memberId}");

                // 7. Return success
                json_response(['success' => true, 'message' => "Reminder sent to {$memberEmail}"]);

            } catch (PDOException $dbErr) {
                error_log('[send_reminder] DB error for member_id=' . $memberId . ': ' . $dbErr->getMessage());
                json_error('An internal error occurred. Please try again.', 500);
            } catch (Throwable $err) {
                error_log('[send_reminder] Unexpected error for member_id=' . $memberId . ': ' . $err->getMessage());
                json_error('An internal error occurred. Please try again.', 500);
            }
            break;

        default:
            json_error('Unknown action: ' . $action);
    }

} catch (PDOException $e) {
    $msg = $e->getMessage();
    if (strpos($msg, 'Duplicate entry') !== false) {
        if (strpos($msg, 'uq_cid_empid') !== false) {
            json_error('That Login ID is already taken. Choose another.');
        }
        json_error('A member with that code or flat already exists');
    }
    json_error('Database error: ' . $msg, 500);
}
