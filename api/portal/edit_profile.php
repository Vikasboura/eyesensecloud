<?php
/**
 * Edit / Complete Profile — EyeSense Portal
 * Members & SA update their own plot details in the employees table.
 * Client_id + employee_id locked to session. Personal data auto-filled.
 */
require_once __DIR__ . '/../portal_config.php';
require_once __DIR__ . '/../portal_auth.php';
require_once __DIR__ . '/../db_setup.php';
requirePortalLogin();

$session = getPortalSession();
$pdo     = get_license_db();
$empId   = $_SESSION['portal_employee_id'] ?? '';
$userId  = (int)($_SESSION['portal_user_id'] ?? 0);
$cid     = $session['client_id'] ?? '';
$message = '';

require_once __DIR__ . '/shared_migration.php';
try { ensure_plot_columns($pdo); } catch (Throwable $e) {}


// ── Determine matched source table: employees -> users -> register -> society_tenants ──
$profileSource   = null; // 'employees' | 'users' | 'register' | 'society_tenants'
$profileRecordId = null;
$emp             = [];
$isSso           = (!empty($_SESSION['auth_provider']) || !empty($_SESSION['is_sso']) || !empty($session['is_sso']));

$fullName        = '';
$phone           = '';
$email           = '';
$displayRole     = $session['role'] ?? '';
$displayUsername = $session['username'] ?? '';
$displayClientId = $cid ?: 'PUBLIC';

// 1. Check employees
if (!empty($empId) && $cid !== 'PUBLIC') {
    $stmt = $pdo->prepare("SELECT * FROM employees WHERE client_id = ? AND employee_id = ? AND is_deleted = 0 LIMIT 1");
    $stmt->execute([$cid, $empId]);
    $row = $stmt->fetch();
    if ($row) {
        $profileSource   = 'employees';
        $profileRecordId = (int)$row['id'];
        $emp             = $row;
        $fullName        = $row['full_name'] ?? '';
        $phone           = $row['phone'] ?? '';
        $email           = $row['email'] ?? '';
        $displayRole     = $row['role'] ?: $displayRole;
        $displayUsername = $row['employee_id'] ?: $displayUsername;
        if (!empty($row['global_user_id'])) {
            try {
                $chkSso = $pdo->prepare("SELECT COUNT(*) FROM user_identity_providers WHERE user_id = ?");
                $chkSso->execute([$row['global_user_id']]);
                if ((int)$chkSso->fetchColumn() > 0) $isSso = true;
            } catch (Throwable $e) {}
        }
    }
}

// 2. Check users (Global / Google SSO identity)
if (!$profileSource && (!empty($userId) || str_starts_with($empId, 'U-'))) {
    $uId = str_starts_with($empId, 'U-') ? (int)substr($empId, 2) : $userId;
    if ($uId > 0) {
        try {
            $uStmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
            $uStmt->execute([$uId]);
            $row = $uStmt->fetch();
            if ($row) {
                $profileSource   = 'users';
                $profileRecordId = (int)$row['id'];
                $fullName        = $row['full_name'] ?? '';
                $phone           = $row['mobile'] ?? '';
                $email           = $row['email'] ?? '';
                $displayRole     = $displayRole ?: 'Client';
                $displayUsername = $fullName ?: ('User #' . $uId);
                try {
                    $chkSso = $pdo->prepare("SELECT COUNT(*) FROM user_identity_providers WHERE user_id = ?");
                    $chkSso->execute([$uId]);
                    if ((int)$chkSso->fetchColumn() > 0) $isSso = true;
                } catch (Throwable $e) {}
                if (!$isSso && str_starts_with($empId, 'U-')) {
                    $isSso = true;
                }
            }
        } catch (Throwable $e) {}
    }
}

// 3. Check register (Public self-registered accounts)
if (!$profileSource && (!empty($userId) || str_starts_with($empId, 'R-') || $cid === 'PUBLIC')) {
    $rId = str_starts_with($empId, 'R-') ? (int)substr($empId, 2) : $userId;
    if ($rId > 0) {
        try {
            $rStmt = $pdo->prepare("SELECT * FROM register WHERE id = ? LIMIT 1");
            $rStmt->execute([$rId]);
            $row = $rStmt->fetch();
            if ($row) {
                $profileSource   = 'register';
                $profileRecordId = (int)$row['id'];
                $fullName        = $row['fullname'] ?? '';
                $phone           = $row['mobile'] ?? '';
                $email           = $row['email'] ?? '';
                $displayRole     = 'Registered User';
                $displayUsername = $fullName ?: ('User #' . $rId);
                $isSso           = false;
            }
        } catch (Throwable $e) {}
    }
}

// 4. Check society_tenants (Tenant accounts)
if (!$profileSource && (!empty($userId) || str_starts_with($empId, 'T-'))) {
    $tId = str_starts_with($empId, 'T-') ? (int)substr($empId, 2) : $userId;
    if ($tId > 0) {
        try {
            $tStmt = $pdo->prepare("SELECT * FROM society_tenants WHERE id = ? LIMIT 1");
            $tStmt->execute([$tId]);
            $row = $tStmt->fetch();
            if ($row) {
                $profileSource   = 'society_tenants';
                $profileRecordId = (int)$row['id'];
                $fullName        = $row['full_name'] ?? '';
                $phone           = $row['mobile'] ?? '';
                $email           = $row['email'] ?? '';
                $displayRole     = 'Tenant';
                $displayUsername = $fullName ?: ('Tenant #' . $tId);
                $isSso           = false;
            }
        } catch (Throwable $e) {}
    }
}

if (empty($fullName)) {
    $fullName = $session['username'] ?? '';
}

// ── Handle Save (Single-Table Scoped Atomic Update) ─────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedFullName = trim($_POST['full_name'] ?? '');
    $postedPhone    = trim($_POST['phone']     ?? '');
    $postedEmail    = trim($_POST['email']     ?? '');
    $plotno         = trim($_POST['plotno']    ?? '');
    $addr           = trim($_POST['plot_address'] ?? '');
    $lat            = trim($_POST['latitude']  ?? '');
    $lng            = trim($_POST['longitude'] ?? '');
    $locSrc         = trim($_POST['location_source'] ?? 'gps');
    $gpsLat         = trim($_POST['gps_latitude']  ?? '');
    $gpsLng         = trim($_POST['gps_longitude'] ?? '');
    $alt            = trim($_POST['alt_phone']    ?? '');
    $status         = trim($_POST['plot_status']  ?? '');
    $notes          = trim($_POST['plot_notes']   ?? '');

    if ($postedFullName === '') {
        $message = 'err:Full Name cannot be empty.';
    } elseif ($postedPhone !== '' && !preg_match('/^[6-9]\d{9}$/', $postedPhone)) {
        $message = 'err:Phone number must be a valid 10-digit mobile number starting with 6, 7, 8, or 9.';
    } elseif ($alt !== '' && !preg_match('/^[6-9]\d{9}$/', $alt)) {
        $message = 'err:Alternate phone number must be a valid 10-digit mobile number starting with 6, 7, 8, or 9.';
    } else {
        $emailToSave = $email;
        $emailError  = null;

        if (!$isSso) {
            if ($postedEmail !== '') {
                if (!filter_var($postedEmail, FILTER_VALIDATE_EMAIL)) {
                    $emailError = 'Please provide a valid email address.';
                } elseif (strtolower($postedEmail) !== strtolower($email)) {
                    $normEmail = strtolower($postedEmail);

                    // Check uniqueness across employees
                    $chkEmp = $pdo->prepare("SELECT id FROM employees WHERE LOWER(email) = ? AND (id != ? OR ? != 'employees') AND is_deleted = 0 LIMIT 1");
                    $chkEmp->execute([$normEmail, (int)$profileRecordId, (string)$profileSource]);
                    if ($chkEmp->fetch()) {
                        $emailError = 'Email address is already in use by a society account.';
                    }

                    // Check uniqueness across society_tenants
                    if (!$emailError) {
                        try {
                            $chkTen = $pdo->prepare("SELECT id FROM society_tenants WHERE LOWER(email) = ? AND (id != ? OR ? != 'society_tenants') AND registration_status NOT IN ('Inactive', 'Rejected') LIMIT 1");
                            $chkTen->execute([$normEmail, (int)$profileRecordId, (string)$profileSource]);
                            if ($chkTen->fetch()) {
                                $emailError = 'Email address is already in use by a tenant account.';
                            }
                        } catch (Throwable $e) {
                            if (!str_contains($e->getMessage(), "doesn't exist")) {
                                error_log("society_tenants email check warning: " . $e->getMessage());
                            }
                        }
                    }

                    // Check uniqueness across register
                    if (!$emailError) {
                        $chkReg = $pdo->prepare("SELECT id FROM register WHERE LOWER(email) = ? AND (id != ? OR ? != 'register') LIMIT 1");
                        $chkReg->execute([$normEmail, (int)$profileRecordId, (string)$profileSource]);
                        if ($chkReg->fetch()) {
                            $emailError = 'Email address is already registered.';
                        }
                    }

                    // Check uniqueness across users
                    if (!$emailError) {
                        $chkUsr = $pdo->prepare("SELECT id FROM users WHERE LOWER(email) = ? AND (id != ? OR ? != 'users') LIMIT 1");
                        $chkUsr->execute([$normEmail, (int)$profileRecordId, (string)$profileSource]);
                        if ($chkUsr->fetch()) {
                            $emailError = 'Email address is already linked to another account.';
                        }
                    }
                }
            }
            if (!$emailError) {
                $emailToSave = $postedEmail;
            }
        }

        if ($emailError) {
            $message = 'err:' . $emailError;
        } else {
            // Plot normalizer
            if ($plotno) {
                preg_match('/(\d+)/', $plotno, $nm);
                preg_match('/([A-Za-z])/', $plotno, $lm);
                if (!empty($nm[1]) && !empty($lm[1])) $plotno = $nm[1].'-'.strtoupper($lm[1]);
            }

            // Prevent accidental wipe of existing plot data if employee submits blank plotno
            if ($profileSource === 'employees' && $plotno === '' && !empty($emp['plotno'])) {
                $plotno = $emp['plotno'];
            }

            // Validate any coordinates submitted
            $coordError = null;
            foreach ([['Location', $lat, $lng], ['GPS reading', $gpsLat, $gpsLng]] as [$label, $vlat, $vlng]) {
                if ($vlat === '' && $vlng === '') continue;
                if (!is_numeric($vlat) || !is_numeric($vlng)) { $coordError = "$label coordinates must be numeric."; break; }
                if ((float)$vlat < -90 || (float)$vlat > 90)   { $coordError = "$label latitude must be between -90 and 90."; break; }
                if ((float)$vlng < -180 || (float)$vlng > 180) { $coordError = "$label longitude must be between -180 and 180."; break; }
            }
            if (!in_array($locSrc, ['gps', 'manual'], true)) $locSrc = 'gps';

            if ($coordError) {
                $message = 'err:' . $coordError;
            } else {
                $maplink = ($lat && $lng) ? "https://www.google.com/maps?q={$lat},{$lng}" : '';
                $manualStamp = ($locSrc === 'manual') ? date('Y-m-d H:i:s') : ($emp['manual_location_updated_at'] ?? null);

                try {
                    $pdo->beginTransaction();

                    // Single UPDATE targeting ONLY the matched source table
                    if ($profileSource === 'employees' && $profileRecordId) {
                        $upd = $pdo->prepare("UPDATE employees SET
                            full_name = ?,
                            phone = ?,
                            email = ?,
                            plotno = ?,
                            plot_address = ?,
                            latitude = ?,
                            longitude = ?,
                            plot_maplink = ?,
                            alt_phone = ?,
                            plot_notes = ?,
                            plot_status = ?,
                            plot_filled_by = ?,
                            location_source = ?,
                            gps_latitude = ?,
                            gps_longitude = ?,
                            manual_location_updated_at = ?,
                            updated_at = NOW()
                            WHERE id = ? AND client_id = ?");
                        $upd->execute([
                            $postedFullName,
                            $postedPhone ?: null,
                            $emailToSave ?: null,
                            $plotno,
                            $addr,
                            $lat,
                            $lng,
                            $maplink,
                            $alt,
                            $notes,
                            $status,
                            $postedFullName,
                            $locSrc,
                            $gpsLat ?: null,
                            $gpsLng ?: null,
                            $manualStamp,
                            $profileRecordId,
                            $cid
                        ]);
                    } elseif ($profileSource === 'users' && $profileRecordId) {
                        if ($isSso) {
                            $upd = $pdo->prepare("UPDATE users SET full_name = ?, mobile = ?, updated_at = NOW() WHERE id = ?");
                            $upd->execute([$postedFullName, $postedPhone ?: null, $profileRecordId]);
                        } else {
                            $upd = $pdo->prepare("UPDATE users SET full_name = ?, mobile = ?, email = ?, updated_at = NOW() WHERE id = ?");
                            $upd->execute([$postedFullName, $postedPhone ?: null, $emailToSave, $profileRecordId]);
                        }
                    } elseif ($profileSource === 'register' && $profileRecordId) {
                        $upd = $pdo->prepare("UPDATE register SET fullname = ?, mobile = ?, email = ? WHERE id = ?");
                        $upd->execute([$postedFullName, $postedPhone, $emailToSave, $profileRecordId]);
                    } elseif ($profileSource === 'society_tenants' && $profileRecordId) {
                        $upd = $pdo->prepare("UPDATE society_tenants SET full_name = ?, mobile = ?, email = ? WHERE id = ?");
                        $upd->execute([$postedFullName, $postedPhone, $emailToSave, $profileRecordId]);
                    }

                    $pdo->commit();

                    // Refresh cached session username and available societies
                    $_SESSION['portal_username'] = $postedFullName;
                    $session['username'] = $postedFullName;

                    if (!empty($_SESSION['portal_available_societies']) && is_array($_SESSION['portal_available_societies'])) {
                        foreach ($_SESSION['portal_available_societies'] as &$soc) {
                            if (($soc['client_id'] ?? '') === $cid && ($soc['user_id'] ?? 0) == $userId) {
                                $soc['full_name'] = $postedFullName;
                            }
                        }
                        unset($soc);
                    }

                    $message = 'ok:Profile saved successfully!';

                    // Refresh active values
                    $fullName = $postedFullName;
                    $phone    = $postedPhone;
                    $email    = $emailToSave;
                    if ($profileSource === 'employees' && $profileRecordId) {
                        $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ? LIMIT 1");
                        $stmt->execute([$profileRecordId]);
                        $emp = $stmt->fetch() ?: [];
                    }

                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $message = 'err:' . $e->getMessage();
                }
            }
        }
    }
}

function ev($k, $r) { return htmlspecialchars($r[$k] ?? ''); }

$plotForShare = $emp['plotno'] ?? '';
$shareUrl = $plotForShare
    ? rtrim(DB_CFG_PORTAL_BASE_URL, '/') . '/visitor.php?plotno=' . urlencode($plotForShare) . '&cid=' . md5($session['client_id'])
    : '';


require_once __DIR__ . '/portal_header.php';
?>

<div class="flex-1 overflow-y-auto">
<div class="p-4 sm:p-6 max-w-3xl mx-auto pb-24">

  <!-- ── Topbar ── -->
  <div class="flex items-start justify-between gap-3 mb-6 flex-wrap">
    <div>
      <h1 class="text-2xl font-bold text-textMain tracking-tight">My Profile</h1>
      <p class="text-sm text-textSec mt-0.5">Update your personal details, plot number, address &amp; GPS location</p>
    </div>
    <a href="dashboard.php"
       class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold
              bg-white/5 border border-white/10 text-textSec hover:text-textMain hover:border-white/25
              transition-all no-underline">
      <i class="fas fa-arrow-left text-xs"></i> Dashboard
    </a>
  </div>

  <!-- ── Flash message ── -->
  <?php if ($message): $isOk = str_starts_with($message,'ok:'); ?>
  <div class="flex items-center gap-3 px-4 py-3 rounded-2xl mb-5 text-sm font-medium border
    <?= $isOk
      ? 'bg-emerald-500/10 border-emerald-500/20 text-emerald-400'
      : 'bg-red-500/10 border-red-500/20 text-red-400' ?>">
    <i class="fas <?= $isOk ? 'fa-circle-check' : 'fa-circle-exclamation' ?> text-base flex-shrink-0"></i>
    <?= htmlspecialchars(substr($message, 3)) ?>
  </div>
  <?php endif; ?>

  <style>
    input[readonly] {
      background: var(--surfaceLight) !important;
      color: var(--textSec) !important;
      cursor: not-allowed !important;
      pointer-events: none;
      user-select: none;
      outline: none !important;
      box-shadow: none !important;
      border-color: var(--border) !important;
    }
    input[readonly]:focus {
      border-color: var(--border) !important;
      outline: none !important;
      box-shadow: none !important;
    }
  </style>

  <form method="POST" class="space-y-4">

    <!-- ── Identity (Editable Fields) ── -->
    <div class="rounded-2xl border border-white/8 bg-white/4 backdrop-blur-sm overflow-hidden">
      <div class="flex items-center gap-2 px-5 py-3 border-b border-white/6">
        <i class="fas fa-id-card text-indigo-400 text-sm"></i>
        <h3 class="text-xs font-bold text-textSec uppercase tracking-widest">Your Identity</h3>
      </div>
      <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">

        <!-- Client ID (Read-only) -->
        <div>
          <label class="flex items-center justify-between text-xs text-textSec font-semibold mb-1.5">
            <span>Client ID</span>
            <span class="text-[10px] text-textSec/70 font-normal"><i class="fas fa-lock text-[9px] mr-1"></i>Read-only</span>
          </label>
          <div class="relative">
            <input type="text" value="<?= htmlspecialchars($displayClientId ?: '—') ?>" readonly tabindex="-1"
              class="w-full pr-9 font-mono text-sm">
            <i class="fas fa-lock absolute right-3 top-1/2 -translate-y-1/2 text-textSec/40 text-xs pointer-events-none"></i>
          </div>
        </div>

        <!-- Username (Read-only) -->
        <div>
          <label class="flex items-center justify-between text-xs text-textSec font-semibold mb-1.5">
            <span>Username / Employee ID</span>
            <span class="text-[10px] text-textSec/70 font-normal"><i class="fas fa-lock text-[9px] mr-1"></i>Read-only</span>
          </label>
          <div class="relative">
            <input type="text" value="<?= htmlspecialchars($displayUsername ?: '—') ?>" readonly tabindex="-1"
              class="w-full pr-9 text-sm">
            <i class="fas fa-lock absolute right-3 top-1/2 -translate-y-1/2 text-textSec/40 text-xs pointer-events-none"></i>
          </div>
        </div>

        <!-- Full Name (Editable) -->
        <div>
          <label class="block text-xs text-textSec font-semibold mb-1.5">
            Full Name <span class="text-rose-400">*</span>
          </label>
          <div class="relative">
            <input type="text" name="full_name" id="full_name" required
              value="<?= htmlspecialchars($fullName) ?>"
              placeholder="e.g. John Doe"
              class="w-full pr-9">
            <i class="fas fa-id-badge absolute right-3 top-1/2 -translate-y-1/2 text-textSec/60 text-sm pointer-events-none"></i>
          </div>
        </div>

        <!-- Phone Number (Editable) -->
        <div>
          <label class="block text-xs text-textSec font-semibold mb-1.5">Phone Number</label>
          <div class="relative">
            <input type="tel" name="phone" id="phone" maxlength="10" minlength="10"
              value="<?= htmlspecialchars($phone) ?>"
              placeholder="e.g. 9876543210"
              class="w-full pr-9">
            <i class="fas fa-phone absolute right-3 top-1/2 -translate-y-1/2 text-textSec/60 text-sm pointer-events-none"></i>
          </div>
          <div id="phoneError" class="text-[11px] text-rose-400 mt-1 hidden"></div>
        </div>

        <!-- Email Address -->
        <?php if ($isSso): ?>
        <div>
          <label class="flex items-center justify-between text-xs text-textSec font-semibold mb-1.5">
            <span>Email Address</span>
            <span class="text-[10px] text-indigo-400 font-medium"><i class="fab fa-google text-[9px] mr-1"></i>Google SSO &bull; Locked</span>
          </label>
          <div class="relative">
            <input type="email" value="<?= htmlspecialchars($email) ?>" readonly tabindex="-1"
              class="w-full pr-9 text-sm">
            <i class="fas fa-envelope absolute right-3 top-1/2 -translate-y-1/2 text-indigo-400/60 text-sm pointer-events-none"></i>
          </div>
        </div>
        <?php else: ?>
        <div>
          <label class="block text-xs text-textSec font-semibold mb-1.5">Email Address</label>
          <div class="relative">
            <input type="email" name="email" id="email"
              value="<?= htmlspecialchars($email) ?>"
              placeholder="user@example.com"
              class="w-full pr-9">
            <i class="fas fa-envelope absolute right-3 top-1/2 -translate-y-1/2 text-textSec/60 text-sm pointer-events-none"></i>
          </div>
        </div>
        <?php endif; ?>

        <!-- Role (Read-only) -->
        <div>
          <label class="flex items-center justify-between text-xs text-textSec font-semibold mb-1.5">
            <span>Role</span>
            <span class="text-[10px] text-textSec/70 font-normal"><i class="fas fa-lock text-[9px] mr-1"></i>Read-only</span>
          </label>
          <div class="relative">
            <input type="text" value="<?= htmlspecialchars(ucfirst($displayRole ?: 'Member')) ?>" readonly tabindex="-1"
              class="w-full pr-9 text-sm">
            <i class="fas fa-lock absolute right-3 top-1/2 -translate-y-1/2 text-textSec/40 text-xs pointer-events-none"></i>
          </div>
        </div>

      </div>
    </div>

    <!-- ── Plot & Address ── -->
    <div class="rounded-2xl border border-white/8 bg-white/4 backdrop-blur-sm overflow-hidden">
      <div class="flex items-center gap-2 px-5 py-3 border-b border-white/6">
        <i class="fas fa-map text-cyan-400 text-sm"></i>
        <h3 class="text-xs font-bold text-textSec uppercase tracking-widest">Plot &amp; Address</h3>
      </div>
      <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">

        <div class="sm:col-span-1">
          <label class="block text-xs text-textSec font-semibold mb-1.5">
            Plot Number
            <span class="text-cyan-500 font-normal ml-1">(auto-normalized)</span>
          </label>
          <div class="relative">
            <input type="text" name="plotno" id="plotno" <?= $profileSource === 'employees' ? 'required' : '' ?>
              value="<?= ev('plotno',$emp) ?>"
              placeholder="A136, 136A, 136-A…"
              class="w-full pr-10">
            <i class="fas fa-map-pin absolute right-3 top-1/2 -translate-y-1/2 text-textSec/60 text-sm pointer-events-none"></i>
          </div>
          <div id="plotPreview" class="mt-1.5"></div>
        </div>

        <div>
          <label class="block text-xs text-textSec font-semibold mb-1.5">Alternate Phone</label>
          <div class="relative">
            <input type="tel" name="alt_phone" id="alt_phone" maxlength="10" minlength="10"
              value="<?= ev('alt_phone',$emp) ?>"
              placeholder="e.g. 9876543210" class="w-full pr-10">
            <i class="fas fa-phone-flip absolute right-3 top-1/2 -translate-y-1/2 text-textSec/60 text-sm pointer-events-none"></i>
          </div>
          <div id="altPhoneError" class="text-[11px] text-rose-400 mt-1 hidden"></div>
        </div>

        <div class="sm:col-span-2">
          <label class="block text-xs text-textSec font-semibold mb-1.5">Full Address</label>
          <textarea name="plot_address" rows="2"
            placeholder="Building/flat, street, city…"
            class="w-full resize-none"><?= ev('plot_address',$emp) ?></textarea>
        </div>

        <div>
          <label class="block text-xs text-textSec font-semibold mb-1.5">Plot Status</label>
          <select name="plot_status" class="w-full">
            <?php foreach ([''=>'— Select —','owner'=>'Owner','tenant'=>'Tenant','vacant'=>'Vacant','commercial'=>'Commercial'] as $v=>$l): ?>
            <option value="<?= $v ?>" <?= ev('plot_status',$emp)===$v?'selected':'' ?>><?= $l ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label class="block text-xs text-textSec font-semibold mb-1.5">Notes</label>
          <input type="text" name="plot_notes" value="<?= ev('plot_notes',$emp) ?>"
            placeholder="Any extra information" class="w-full">
        </div>

      </div>
    </div>

    <!-- ── GPS Location ── -->
    <div class="rounded-2xl border border-white/8 bg-white/4 backdrop-blur-sm overflow-hidden">
      <div class="flex items-center gap-2 px-5 py-3 border-b border-white/6">
        <i class="fas fa-location-dot text-red-400 text-sm"></i>
        <h3 class="text-xs font-bold text-textSec uppercase tracking-widest">GPS Location</h3>
        <span class="ml-auto text-[10px] text-textSec">Click button to auto-detect</span>
      </div>
      <div class="p-5">

        <div class="grid grid-cols-2 gap-3 mb-4">
          <div>
            <label class="block text-xs text-textSec font-semibold mb-1.5">Latitude</label>
            <input type="text" name="latitude" id="latitude"
              value="<?= ev('latitude',$emp) ?>" placeholder="22.7196" class="w-full font-mono">
          </div>
          <div>
            <label class="block text-xs text-textSec font-semibold mb-1.5">Longitude</label>
            <input type="text" name="longitude" id="longitude"
              value="<?= ev('longitude',$emp) ?>" placeholder="75.8577" class="w-full font-mono">
          </div>
        </div>
        
        <input type="hidden" name="location_source" id="location_source" value="<?= ev('location_source',$emp) ?: 'gps' ?>"> 
        <input type="hidden" name="gps_latitude" id="gps_latitude" value="<?= ev('gps_latitude',$emp) ?>"> 
        <input type="hidden" name="gps_longitude" id="gps_longitude" value="<?= ev('gps_longitude',$emp) ?>">
        
        
        <div class="flex flex-wrap items-center gap-2 mb-3">
        <button type="button" id="gpsBtn" onclick="detectGPS()"
          class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-bold
                bg-gradient-to-r from-indigo-600 to-purple-600 text-textMain border-none
                cursor-pointer hover:from-indigo-500 hover:to-purple-500 transition-all
                shadow-lg shadow-indigo-500/20">
          <i class="fas fa-location-crosshairs"></i> Detect My GPS
        </button>
        

        <button type="button" id="manualLocBtn" onclick="openManualLocation()"
          class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-bold
                bg-white/5 border border-white/15 text-textMain cursor-pointer
                hover:border-cyan-400/50 hover:text-cyan-300 transition-all">
          <i class="fas fa-map-pin"></i> Set Current Location Manually
        </button>

        <?php if (ev('location_source',$emp) === 'manual' && ev('gps_latitude',$emp) && ev('gps_longitude',$emp)): ?>
        <button type="button" id="revertGpsBtn" onclick="revertToGps()"
          class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold
                bg-white/5 border border-white/10 text-textSec cursor-pointer hover:text-textMain transition-all">
          <i class="fas fa-rotate-left"></i> Revert to GPS
        </button>
        <?php endif; ?>
        </div>

        <div id="gpsStatus" class="text-xs mb-3 min-h-4">
          <?php if (ev('latitude', $emp) && ev('longitude', $emp)): ?>
              <span class="<?= ev('location_source', $emp) === 'manual' ? 'text-cyan-400' : 'text-emerald-400' ?>">
                  <i class="fas <?= ev('location_source', $emp) === 'manual' ? 'fa-map-pin' : 'fa-circle-check' ?>"></i>
                  <?= ev('location_source', $emp) === 'manual' ? 'Manually set' : 'GPS detected' ?>:
                  <?= ev('latitude', $emp) ?>, <?= ev('longitude', $emp) ?>
              </span>
          <?php else: ?>
              <span class="text-textSec">No location saved yet</span>
          <?php endif; ?>
        </div>

        <!-- Map preview -->
        <?php if (ev('latitude',$emp) && ev('longitude',$emp)):
          $mUrl = "https://www.google.com/maps?q=".ev('latitude',$emp).",".ev('longitude',$emp); ?>
        <div class="rounded-xl bg-gray-200/50 border border-white/8 overflow-hidden" id="mapPreviewBox">
          <div class="flex items-center gap-3 px-4 py-2.5 border-b border-white/6">
            <a href="<?= $mUrl ?>" target="_blank"
               class="text-cyan-400 text-xs font-mono truncate flex-1 no-underline
                      border-b border-dashed border-cyan-500/40 hover:text-cyan-300">
              <i class="fas fa-external-link-alt mr-1 text-[10px]"></i><?= $mUrl ?>
            </a>
            <button type="button"
              onclick="navigator.clipboard.writeText('<?= $mUrl ?>').then(()=>{this.textContent='Copied!';setTimeout(()=>this.innerHTML='<i class=\"fas fa-copy\"></i>',2000)})"
              class="flex-shrink-0 text-xs text-textSec hover:text-textMain bg-white/5 border border-white/10
                     px-3 py-1 rounded-lg cursor-pointer transition-colors">
              <i class="fas fa-copy"></i>
            </button>
          </div>
          <iframe src="<?= $mUrl ?>&output=embed"
            class="w-full h-48 border-none block" loading="lazy"></iframe>
        </div>
        <?php else: ?>
        <div id="mapPreviewBox" class="hidden rounded-xl bg-gray-200/50 border border-white/8 overflow-hidden">
          <div class="flex items-center gap-3 px-4 py-2.5 border-b border-white/6">
            <a href="#" id="mapLinkEl" target="_blank"
               class="text-cyan-400 text-xs font-mono truncate flex-1 no-underline
                      border-b border-dashed border-cyan-500/40"></a>
            <button type="button" id="copyMapBtn"
              class="flex-shrink-0 text-xs text-textSec hover:text-textMain bg-white/5 border border-white/10
                     px-3 py-1 rounded-lg cursor-pointer transition-colors">
              <i class="fas fa-copy"></i>
            </button>
          </div>
          <iframe id="mapIframe" src="" class="w-full h-48 border-none block" loading="lazy"></iframe>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- ── Save button ── -->
    <button type="submit"
      class="w-full py-3.5 rounded-2xl font-bold text-sm text-textMain border-none cursor-pointer
             bg-gradient-to-r from-indigo-600 to-purple-600
             hover:from-indigo-500 hover:to-purple-500
             shadow-xl shadow-indigo-500/20 transition-all
             flex items-center justify-center gap-2">
      <i class="fas fa-floppy-disk"></i> Save Profile
    </button>

  </form>

  <div id="manualLocModal" class="hidden fixed inset-0 z-50 items-center justify-center bg-black/60 p-4">
    <div class="bg-[#12141c] border border-white/10 rounded-2xl w-full max-w-lg overflow-hidden">
      <div class="flex items-center justify-between px-5 py-4 border-b border-white/8">
        <h3 class="text-sm font-bold text-textMain">
          <i class="fas fa-map-pin text-cyan-400 mr-1"></i> Set Exact Location
        </h3>
        <button type="button" onclick="closeManualLocation()" class="text-textSec hover:text-textMain">
          <i class="fas fa-xmark"></i>
        </button>
      </div>
      <div class="p-5">
        <p class="text-xs text-textSec mb-3">
          Click the map or drag the pin to your exact home/apartment location, or type coordinates directly.
        </p>

        <div class="grid grid-cols-2 gap-3 mb-3">
          <div>
            <label class="block text-[10px] text-textSec font-semibold mb-1">Latitude</label>
            <input type="text" id="manualLatInput" class="w-full font-mono text-sm" placeholder="22.719600">
          </div>
          <div>
            <label class="block text-[10px] text-textSec font-semibold mb-1">Longitude</label>
            <input type="text" id="manualLngInput" class="w-full font-mono text-sm" placeholder="75.857700">
          </div>
        </div>

        <div id="manualMap" class="w-full h-64 rounded-xl border border-white/10 mb-3"></div>

        <div id="manualLocError" class="text-xs text-red-400 mb-2 hidden"></div>

        <div class="flex gap-2">
          <button type="button" onclick="confirmManualLocation()"
            class="flex-1 py-2.5 rounded-xl text-sm font-bold bg-gradient-to-r from-emerald-600 to-cyan-600
                  text-textMain border-none cursor-pointer hover:opacity-90 transition-all">
            <i class="fas fa-check"></i> Confirm Location
          </button>

          <button type="button" onclick="closeManualLocation()"
            class="px-5 py-2.5 rounded-xl text-sm font-semibold bg-white/5 border border-white/10
                  text-textSec cursor-pointer hover:text-textMain">
            Cancel
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- ── Share Location ── -->
  <?php if ($shareUrl): ?>
  <div class="mt-4 rounded-2xl border border-white/8 bg-white/4 backdrop-blur-sm overflow-hidden">
    <div class="flex items-center gap-2 px-5 py-3 border-b border-white/6">
      <i class="fas fa-share-nodes text-purple-400 text-sm"></i>
      <h3 class="text-xs font-bold text-textSec uppercase tracking-widest">Share My Location</h3>
    </div>
    <div class="p-5">
      <p class="text-xs text-textSec mb-3">Share this link with visitors so they can find your plot on the map.</p>
      <div class="flex items-center gap-3 bg-gray-200/50 rounded-xl px-4 py-3 border border-white/6">
        <i class="fas fa-link text-primary text-sm flex-shrink-0"></i>
        <span id="shareUrlEl" class="font-mono text-xs text-textSec flex-1 break-all">
          <?= htmlspecialchars($shareUrl) ?>
        </span>
        <button type="button" id="shareBtn"
          onclick="
            navigator.clipboard.writeText(document.getElementById('shareUrlEl').textContent.trim())
              .then(()=>{
                this.innerHTML='<i class=\'fas fa-check\'></i>';
                this.classList.add('text-emerald-400');
                setTimeout(()=>{
                  this.innerHTML='<i class=\'fas fa-copy\'></i>';
                  this.classList.remove('text-emerald-400');
                },2000)
              })"
          class="flex-shrink-0 w-8 h-8 rounded-lg bg-white/5 border border-white/10
                 text-textSec hover:text-textMain cursor-pointer transition-colors
                 flex items-center justify-center text-sm">
          <i class="fas fa-copy"></i>
        </button>
      </div>
    </div>
  </div>
  <?php endif; ?>

</div><!-- /max-w -->
</div><!-- /flex-1 -->

<script>
document.addEventListener('DOMContentLoaded', () => {

  // Plot normalizer
  function normalizePlot(v) {
    const nm = (v||'').match(/\d+/), lm = (v||'').match(/[A-Za-z]/);
    return (nm && lm) ? `${nm[0]}-${lm[0].toUpperCase()}` : null;
  }

  const plotInput = document.getElementById('plotno');
  const plotPrev  = document.getElementById('plotPreview');

  function updatePlot() {
    const std = normalizePlot(plotInput.value);
    plotPrev.innerHTML = std
      ? `<span class="inline-flex items-center gap-1.5 text-[11px] text-cyan-400 bg-cyan-500/10 px-3 py-1 rounded-full border border-cyan-500/20">
          <i class="fas fa-check-circle text-[10px]"></i> Normalized: <strong class="font-mono">${std}</strong>
         </span>`
      : '';
  }

  if (plotInput) {
    plotInput.addEventListener('input', updatePlot);
    updatePlot();
  }

  // GPS detection
  function detectGPS() {
    const status = document.getElementById('gpsStatus');
    const btn    = document.getElementById('gpsBtn');

    if (!navigator.geolocation) {
      status.innerHTML = '<span class="text-red-400">Geolocation not supported</span>';
      return;
    }

    btn.innerHTML = 'Detecting...';
    btn.disabled = true;

    navigator.geolocation.getCurrentPosition(
    pos => {
      const lat = pos.coords.latitude.toFixed(6);
      const lng = pos.coords.longitude.toFixed(6);

      document.getElementById('gps_latitude').value = lat;
      document.getElementById('gps_longitude').value = lng;

      const isManual = document.getElementById('location_source').value === 'manual';

      if (!isManual) {
        document.getElementById('latitude').value = lat;
        document.getElementById('longitude').value = lng;
        document.getElementById('location_source').value = 'gps';

        updateMapPreview(lat, lng);

        status.innerHTML =
          `<span class="text-emerald-400">
            <i class="fas fa-circle-check"></i> ${lat}, ${lng}
            <span class="text-textSec">(±${Math.round(pos.coords.accuracy)}m)</span>
          </span>`;
      } else {
        status.innerHTML =
          `<span class="text-cyan-400">
            <i class="fas fa-map-pin"></i> Manual location is active.
            GPS reading (${lat}, ${lng}) saved for reference —
            <a href="#" onclick="revertToGps();return false;" class="underline">use GPS instead</a>
          </span>`;
      }

      btn.innerHTML = '<i class="fas fa-location-crosshairs"></i> Detect My GPS';
      btn.disabled = false;
    },
    err => {
      status.innerHTML = 'Error getting location';
      btn.innerHTML = '<i class="fas fa-location-crosshairs"></i> Detect My GPS';
      btn.disabled = false;
    }
  );
  }

  window.detectGPS = detectGPS; // 👈 IMPORTANT (button uses this)

  // ================= MANUAL LOCATION =================

  function isValidCoords(lat, lng) {
    return Number.isFinite(lat) && Number.isFinite(lng) &&
           lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180;
  }

  let manualMap = null, manualMarker = null;

  function openManualLocation() {
    const modal = document.getElementById('manualLocModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');

    const existingLat = parseFloat(document.getElementById('latitude').value);
    const existingLng = parseFloat(document.getElementById('longitude').value);

    const startLat = isValidCoords(existingLat, existingLng) ? existingLat : 22.7196;
    const startLng = isValidCoords(existingLat, existingLng) ? existingLng : 75.8577;

    document.getElementById('manualLatInput').value = startLat.toFixed(6);
    document.getElementById('manualLngInput').value = startLng.toFixed(6);

    setTimeout(() => {
      if (!manualMap) {
        manualMap = L.map('manualMap').setView([startLat, startLng], 18);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
          maxZoom: 19
        }).addTo(manualMap);

        manualMarker = L.marker([startLat, startLng], { draggable: true }).addTo(manualMap);

        manualMarker.on('dragend', () => {
          const p = manualMarker.getLatLng();
          document.getElementById('manualLatInput').value = p.lat.toFixed(6);
          document.getElementById('manualLngInput').value = p.lng.toFixed(6);
        });

        manualMap.on('click', (e) => {
          manualMarker.setLatLng(e.latlng);
          document.getElementById('manualLatInput').value = e.latlng.lat.toFixed(6);
          document.getElementById('manualLngInput').value = e.latlng.lng.toFixed(6);
        });
      } else {
        manualMap.setView([startLat, startLng], 18);
        manualMarker.setLatLng([startLat, startLng]);
      }

      manualMap.invalidateSize();
    }, 100);
  }

  function closeManualLocation() {
    document.getElementById('manualLocModal').classList.add('hidden');
    document.getElementById('manualLocModal').classList.remove('flex');
  }

  function confirmManualLocation() {
    const lat = parseFloat(document.getElementById('manualLatInput').value);
    const lng = parseFloat(document.getElementById('manualLngInput').value);

    if (!isValidCoords(lat, lng)) return;

    document.getElementById('latitude').value = lat.toFixed(6);
    document.getElementById('longitude').value = lng.toFixed(6);
    document.getElementById('location_source').value = 'manual';

    updateMapPreview(lat, lng);
    closeManualLocation();
  }

  function revertToGps() {
    const gLat = document.getElementById('gps_latitude').value;
    const gLng = document.getElementById('gps_longitude').value;

    if (!gLat || !gLng) return;

    document.getElementById('latitude').value = gLat;
    document.getElementById('longitude').value = gLng;
    document.getElementById('location_source').value = 'gps';

    updateMapPreview(gLat, gLng);
  }

  function updateMapPreview(lat, lng) {
    const box = document.getElementById('mapPreviewBox');
    const mapUrl = `https://www.google.com/maps?q=${lat},${lng}`;

    const linkEl = document.getElementById('mapLinkEl');
    const iframe = document.getElementById('mapIframe');

    if (box && linkEl && iframe) {
      linkEl.href = mapUrl;
      linkEl.textContent = mapUrl;
      iframe.src = mapUrl + '&output=embed';
      box.classList.remove('hidden');
    }
  }

  // ================= PHONE & ALT PHONE VALIDATION =================
  const phoneInput    = document.getElementById('phone');
  const phoneError    = document.getElementById('phoneError');
  const altPhoneInput = document.getElementById('alt_phone');
  const altPhoneError = document.getElementById('altPhoneError');

  function validateMobileField(input, errorEl, label) {
    if (!input || !errorEl) return true;
    const val = input.value.trim();
    if (!val) {
      errorEl.classList.add('hidden');
      input.classList.remove('border-rose-500');
      return true;
    }
    if (!/^[6-9]\d{9}$/.test(val)) {
      errorEl.textContent = `${label} must be exactly 10 digits starting with 6, 7, 8, or 9`;
      errorEl.classList.remove('hidden');
      input.classList.add('border-rose-500');
      return false;
    }
    errorEl.classList.add('hidden');
    input.classList.remove('border-rose-500');
    return true;
  }

  [phoneInput, altPhoneInput].forEach(inp => {
    if (!inp) return;
    inp.addEventListener('input', (e) => {
      e.target.value = e.target.value.replace(/\D/g, '').slice(0, 10);
      if (inp === phoneInput) validateMobileField(phoneInput, phoneError, 'Phone number');
      if (inp === altPhoneInput) validateMobileField(altPhoneInput, altPhoneError, 'Alternate phone');
    });
    inp.addEventListener('blur', () => {
      if (inp === phoneInput) validateMobileField(phoneInput, phoneError, 'Phone number');
      if (inp === altPhoneInput) validateMobileField(altPhoneInput, altPhoneError, 'Alternate phone');
    });
  });

  const editProfileForm = document.querySelector('form');
  if (editProfileForm) {
    editProfileForm.addEventListener('submit', (e) => {
      const isPhoneValid = validateMobileField(phoneInput, phoneError, 'Phone number');
      const isAltValid   = validateMobileField(altPhoneInput, altPhoneError, 'Alternate phone');
      if (!isPhoneValid || !isAltValid) {
        e.preventDefault();
        if (!isPhoneValid && phoneInput) phoneInput.focus();
        else if (!isAltValid && altPhoneInput) altPhoneInput.focus();
      }
    });
  }

  // expose functions to buttons
  window.openManualLocation = openManualLocation;
  window.closeManualLocation = closeManualLocation;
  window.confirmManualLocation = confirmManualLocation;
  window.revertToGps = revertToGps;

});
</script>

<?php
// Try portal footer, silently skip if missing
$footerFile = __DIR__ . '/portal_footer.php';
if (file_exists($footerFile)) require_once $footerFile;
else echo '</body></html>';
?>
