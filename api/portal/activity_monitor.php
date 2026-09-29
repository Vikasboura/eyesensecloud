<?php
/**
 * EyeSense Cloud Portal — Activity Monitor (Admin Only)
 * Shows all portal login sessions with IP, location, device, and live status.
 */
require_once __DIR__ . '/portal_header.php';
requirePermission($pdo, $session['client_id'], $session['role'], 'MANAGE_MAINTENANCE');
?>

<style>
.session-active { border-left: 3px solid #34d399; }
.session-inactive { border-left: 3px solid #d1d5db; opacity: 0.65; }
.geo-chip { display:inline-flex; align-items:center; gap:4px; background:rgba(99,102,241,0.1); border:1px solid rgba(99,102,241,0.25); color:#a5b4fc; font-size:11px; padding:2px 8px; border-radius:999px; }
.role-SUPERADMIN { color:#a78bfa; background:rgba(167,139,250,0.1); border:1px solid rgba(167,139,250,0.3); }
.role-ADMIN      { color:#60a5fa; background:rgba(96,165,250,0.1);  border:1px solid rgba(96,165,250,0.3); }
.role-MEMBER     { color:#34d399; background:rgba(52,211,153,0.1);  border:1px solid rgba(52,211,153,0.3); }
.audit-action { font-family:monospace; font-size:11px; background:rgba(99,102,241,0.12); color:#c7d2fe; padding:2px 6px; border-radius:4px; }
</style>

<!-- Topbar -->
<div class="h-auto min-h-[4rem] border-b border-border bg-surface flex flex-wrap items-center justify-between px-4 md:px-6 py-2 gap-2 flex-shrink-0 portal-topbar">
    <div class="min-w-0">
        <h1 class="font-bold text-base md:text-lg text-textMain truncate"><i class="fas fa-satellite-dish text-indigo-400 mr-2"></i>Activity Monitor</h1>
        <p class="text-xs text-textSec hidden sm:block">Live login sessions, IP tracking, and admin audit trail</p>
    </div>
    <button onclick="refreshAll()" class="btn-ghost text-sm"><i class="fas fa-rotate-right mr-1"></i>Refresh</button>
</div>

<div class="flex-1 overflow-y-auto p-4 md:p-6 space-y-6">

    <!-- Summary Stats -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4" id="summaryCards">
        <?php for ($i=0;$i<4;$i++): ?>
        <div class="stat-card text-center"><div class="text-textSec text-xs uppercase tracking-wider mb-1">Loading…</div><div class="text-2xl font-bold text-gray-700">—</div></div>
        <?php endfor; ?>
    </div>

    <!-- Session Filters + Table -->
    <div class="glass-panel rounded-xl p-5">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <h3 class="text-sm font-bold text-textSec uppercase tracking-wider"><i class="fas fa-user-clock text-indigo-400 mr-2"></i>Login Sessions</h3>
            <div class="flex gap-2">
                <select id="fSession" onchange="loadSessions()" class="text-sm">
                    <option value="">All Sessions</option>
                    <option value="active">Active Now</option>
                    <option value="today">Today</option>
                </select>
            </div>
        </div>
        <div class="overflow-x-auto rounded-xl border border-border">
            <table class="w-full text-left text-sm" id="sessionsTable">
                <thead>
                    <tr class="border-b border-border bg-gray-900/60">
                        <th class="px-4 py-3 text-xs text-textSec uppercase tracking-wider">User</th>
                        <th class="px-4 py-3 text-xs text-textSec uppercase tracking-wider">Role</th>
                        <th class="px-4 py-3 text-xs text-textSec uppercase tracking-wider">IP / Location</th>
                        <th class="px-4 py-3 text-xs text-textSec uppercase tracking-wider hidden md:table-cell">Device</th>
                        <th class="px-4 py-3 text-xs text-textSec uppercase tracking-wider">Login At</th>
                        <th class="px-4 py-3 text-xs text-textSec uppercase tracking-wider">Status</th>
                        <th class="px-4 py-3 text-xs text-textSec uppercase tracking-wider">Action</th>
                    </tr>
                </thead>
                <tbody id="sessionsBody">
                    <tr><td colspan="7" class="px-4 py-8 text-center text-textSec text-sm"><i class="fas fa-spinner fa-spin mr-2"></i>Loading…</td></tr>
                </tbody>
            </table>
        </div>
    </div>


</div>

<?php require_once __DIR__ . '/portal_footer.php'; ?>


<script>
const esc = s => String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');

// Parse a MySQL datetime string ("YYYY-MM-DD HH:MM:SS") as IST.
// MySQL returns naive strings; without a tz hint JS assumes UTC which
// causes times to appear 5h30m behind. We tag with +05:30 to fix this.
function parseIST(dt) {
    if (!dt) return new Date(NaN);
    // Already has offset info (e.g. ends with Z or +XX:XX) — pass through
    if (/[Z+]/.test(dt.trim().slice(-6))) return new Date(dt);
    // Replace space separator with T and append IST offset
    return new Date(dt.trim().replace(' ', 'T') + '+05:30');
}


function parseUA(ua) {
    if (!ua) return '—';
    let browser = 'Unknown', os = '';
    if (/Edg\//.test(ua))           browser = 'Edge';
    else if (/OPR\//.test(ua))      browser = 'Opera';
    else if (/Chrome\//.test(ua))   browser = 'Chrome';
    else if (/Firefox\//.test(ua))  browser = 'Firefox';
    else if (/Safari\//.test(ua))   browser = 'Safari';
    if (/Windows/.test(ua))     os = 'Windows';
    else if (/Android/.test(ua)) os = 'Android';
    else if (/iPhone|iPad/.test(ua)) os = 'iOS';
    else if (/Mac OS/.test(ua))  os = 'macOS';
    else if (/Linux/.test(ua))   os = 'Linux';
    return browser + (os ? ' / ' + os : '');
}

async function loadSessions() {
    const filter = document.getElementById('fSession').value;
    const tbody  = document.getElementById('sessionsBody');
    tbody.innerHTML = '<tr><td colspan="7" class="px-4 py-6 text-center text-textSec text-sm"><i class="fas fa-spinner fa-spin mr-2"></i>Loading…</td></tr>';
    try {
        const res  = await fetch('../activity_api.php?action=list_sessions&filter=' + filter + '&limit=100');
        const data = await res.json();
        const rows = data.sessions || [];
        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="7" class="px-4 py-8 text-center text-textSec text-sm">No sessions found</td></tr>';
            return;
        }
        tbody.innerHTML = rows.map(s => {
            const active   = s.is_live == 1;
            const openSession = s.is_active == 1;
            const rowCls   = active ? 'session-active' : 'session-inactive';
            const statusBadge = active
                ? '<span class="badge text-xs" style="color:#34d399;background:rgba(52,211,153,0.1);border:1px solid rgba(52,211,153,0.3);">● Live</span>'
                : (openSession
                    ? '<span class="badge text-xs" style="color:#f59e0b;background:rgba(245,158,11,0.1);border:1px solid rgba(245,158,11,0.3);">Idle</span>'
                    : '<span class="badge text-xs" style="color:var(--textSec);background:rgba(107,114,128,0.08);border:1px solid rgba(107,114,128,0.2);">Offline</span>');
            const geo = [s.city, s.region, s.country].filter(Boolean).join(', ') || '—';
            const isp = s.isp ? `<div class="text-xs text-textSec mt-0.5">${esc(s.isp)}</div>` : '';
            const loginTime  = parseIST(s.login_at).toLocaleString('en-IN', {day:'2-digit',month:'short',hour:'2-digit',minute:'2-digit',hour12:true});
            const logoutTime = s.logout_at ? parseIST(s.logout_at).toLocaleString('en-IN', {hour:'2-digit',minute:'2-digit',hour12:true}) : '';
            const lastSeen = s.last_seen_at ? parseIST(s.last_seen_at).toLocaleString('en-IN', {hour:'2-digit',minute:'2-digit',hour12:true}) : '';
            const roleCls = `role-${s.role}`;
            return `<tr class="${rowCls} border-b border-border/40 hover:bg-textMain/20">
                <td class="px-4 py-3">
                    <div class="font-semibold text-textMain text-sm">${esc(s.full_name||s.employee_id)}</div>
                    <div class="text-xs text-textSec">${esc(s.employee_id)}</div>
                </td>
                <td class="px-4 py-3"><span class="badge text-xs ${roleCls}">${esc(s.role)}</span></td>
                <td class="px-4 py-3">
                    <div class="font-mono text-xs text-indigo-300">${esc(s.ip_address)}</div>
                    <div class="geo-chip mt-1"><i class="fas fa-location-dot"></i>${esc(geo)}</div>
                    ${isp}
                </td>
                <td class="px-4 py-3 hidden md:table-cell text-xs text-textSec">${esc(parseUA(s.user_agent))}</td>
                <td class="px-4 py-3 text-xs text-textSec">${loginTime}${logoutTime ? '<br><span class="text-textSec">Out: '+logoutTime+'</span>' : (lastSeen ? '<br><span class="text-textSec">Seen: '+lastSeen+'</span>' : '')}</td>
                <td class="px-4 py-3">${statusBadge}</td>
                <td class="px-4 py-3">
                    ${openSession ? `<button onclick="forceLogout(${s.id})" class="btn-danger text-xs py-1 px-2" title="Force logout"><i class="fas fa-ban"></i></button>` : ''}
                </td>
            </tr>`;
        }).join('');
    } catch(e) {
        tbody.innerHTML = `<tr><td colspan="7" class="px-4 py-6 text-center text-red-400 text-sm">Error: ${esc(e.message)}</td></tr>`;
    }
}

async function loadSummary() {
    try {
        const res  = await fetch('../activity_api.php?action=dashboard_summary');
        const data = await res.json();
        const s = data.summary || {};
        const cards = [
            { label:'Total Logins',    value: s.total_logins  || 0, color:'text-textMain' },
            { label:'Active Now',      value: s.active_now    || 0, color:'text-emerald-400' },
            { label:'Unique Users',    value: s.unique_users  || 0, color:'text-indigo-400' },
            { label:'Logins Today',    value: s.logins_today  || 0, color:'text-amber-400' },
        ];
        document.getElementById('summaryCards').innerHTML = cards.map(c =>
            `<div class="stat-card text-center">
                <div class="text-textSec text-xs uppercase tracking-wider mb-1">${c.label}</div>
                <div class="text-2xl font-bold ${c.color}">${c.value}</div>
             </div>`
        ).join('');
    } catch(e) {}
}


async function forceLogout(id) {
    if (!await showConfirm('Force-logout this session?')) return;
    try {
        const res  = await fetch('../activity_api.php', {
            method:'POST', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({action:'logout_session', id})
        });
        const d = await res.json();
        if (d.error) throw new Error(d.error);
        showAlert(d.message, 'success');
        loadSessions();
    } catch(e) { showAlert(e.message,'error'); }
}

function refreshAll() { loadSummary(); loadSessions(); }

loadSummary();
loadSessions();
setInterval(refreshAll, 15000);
</script>
