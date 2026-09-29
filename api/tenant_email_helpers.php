<?php
/**
 * Tenant email notification helpers
 * Included by tenant_api.php
 * Branch: 78-es-78-tenant-management--client-profile-module
 */

function _tenantSocName(PDO $pdo, string $cid): string {
    try {
        $r = $pdo->prepare("SELECT society_name FROM maintenance_settings WHERE client_id=? LIMIT 1");
        $r->execute([$cid]);
        return $r->fetchColumn() ?: 'Your Society';
    } catch (Throwable $e) {
        return 'Your Society';
    }
}

function tenantEmailSubmitted(PDO $pdo, string $cid, array $t): void {
    if (empty($t['email'])) return;
    $soc  = _tenantSocName($pdo, $cid);
    $name = htmlspecialchars($t['full_name'] ?? '');
    $flat = htmlspecialchars($t['flat_number'] ?? '—');
    $body = "<p>Dear <strong>$name</strong>,</p>"
          . "<p>Your tenant registration for <strong>" . htmlspecialchars($soc) . "</strong> has been <strong style='color:#6366f1'>submitted successfully</strong>.</p>"
          . "<table style='width:100%;border-collapse:collapse;margin:14px 0;background:#f9fafb;border-radius:8px'>"
          . "<tr><td style='padding:7px 12px;font-size:12px;color:#6b7280;width:42%'>Flat / Room</td><td style='padding:7px 12px;font-size:13px;font-weight:600'>$flat</td></tr>"
          . "<tr style='background:#f3f4f6'><td style='padding:7px 12px;font-size:12px;color:#6b7280'>Mobile</td><td style='padding:7px 12px;font-size:13px;font-weight:600'>" . htmlspecialchars($t['mobile'] ?? '—') . "</td></tr>"
          . "<tr><td style='padding:7px 12px;font-size:12px;color:#6b7280'>Status</td><td style='padding:7px 12px;font-size:13px;color:#d97706;font-weight:700'>Pending Review</td></tr>"
          . "</table>"
          . "<p style='color:#6b7280;font-size:13px'>Your house owner will review your request and you will be notified once a decision is made.</p>";
    tenantNotifyEmail($pdo, "[EyeSense] Tenant Registration Submitted — $soc",
        tenantEmailHtml('Registration Submitted', $body), [$t['email']]);
}

function tenantEmailApproved(PDO $pdo, string $cid, array $t): void {
    if (empty($t['email'])) return;
    $soc  = _tenantSocName($pdo, $cid);
    $name = htmlspecialchars($t['full_name'] ?? '');
    $flat = htmlspecialchars($t['flat_number'] ?? '—');
    $hint = !empty($t['portal_username'])
        ? "<div style='margin-top:14px;padding:12px 16px;background:#ecfdf5;border-radius:8px;border:1px solid #a7f3d0;font-size:13px;color:#065f46'>"
          . "You can now log in to the tenant portal using username: <strong>" . htmlspecialchars($t['portal_username']) . "</strong>"
          . " or your registered mobile number.</div>"
        : "<p style='color:#6b7280;font-size:13px;margin-top:12px'>You may log in to the tenant portal using your registered mobile number once a password is set.</p>";
    $body = "<p>Dear <strong>$name</strong>,</p>"
          . "<p>Your tenant registration for <strong>" . htmlspecialchars($soc) . "</strong> has been <strong style='color:#059669'>APPROVED</strong>.</p>"
          . "<table style='width:100%;border-collapse:collapse;margin:14px 0;background:#f9fafb;border-radius:8px'>"
          . "<tr><td style='padding:7px 12px;font-size:12px;color:#6b7280;width:42%'>Flat / Room</td><td style='padding:7px 12px;font-size:13px;font-weight:600'>$flat</td></tr>"
          . "<tr style='background:#f3f4f6'><td style='padding:7px 12px;font-size:12px;color:#6b7280'>Move-in Date</td><td style='padding:7px 12px;font-size:13px;font-weight:600'>" . htmlspecialchars($t['occupancy_start'] ?? '—') . "</td></tr>"
          . "<tr><td style='padding:7px 12px;font-size:12px;color:#6b7280'>Status</td><td style='padding:7px 12px;font-size:13px;color:#059669;font-weight:700'>Active</td></tr>"
          . "</table>" . $hint;
    tenantNotifyEmail($pdo, "[EyeSense] Tenant Registration Approved — $soc",
        tenantEmailHtml('Registration Approved', $body), [$t['email']]);
}

function tenantEmailRejected(PDO $pdo, string $cid, array $t, string $reason): void {
    if (empty($t['email'])) return;
    $soc  = _tenantSocName($pdo, $cid);
    $name = htmlspecialchars($t['full_name'] ?? '');
    $body = "<p>Dear <strong>$name</strong>,</p>"
          . "<p>Your tenant registration for <strong>" . htmlspecialchars($soc) . "</strong> has been <strong style='color:#dc2626'>rejected</strong>.</p>"
          . "<div style='background:#fef2f2;padding:16px;border-radius:8px;border:1px solid #fecaca;margin:14px 0'>"
          . "<p style='color:#b91c1c;font-size:13px;font-weight:700;margin:0 0 6px'>Reason:</p>"
          . "<p style='color:#991b1b;font-size:13px;margin:0'>" . htmlspecialchars($reason) . "</p></div>"
          . "<p style='color:#6b7280;font-size:13px'>Please contact your house owner or society administration for assistance.</p>";
    tenantNotifyEmail($pdo, "[EyeSense] Tenant Registration Rejected — $soc",
        tenantEmailHtml('Registration Rejected', $body), [$t['email']]);
}
