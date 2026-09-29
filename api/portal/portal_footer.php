</main>

<!-- Modal system -->
<div id="modalOverlay" class="fixed inset-0 z-[9999] hidden items-center justify-center bg-gray-500/60 backdrop-blur-sm p-4" style="display:none">
    <div id="modalBox" class="w-full max-w-sm rounded-2xl border shadow-2xl overflow-hidden" style="background:var(--surfaceLight);">
        <div id="modalHeader" class="px-5 py-4 flex items-center gap-3 border-b border-border">
            <div id="modalIcon" class="w-10 h-10 rounded-full flex items-center justify-center text-lg flex-shrink-0"></div>
            <h3 id="modalTitle" class="text-textMain font-bold text-base"></h3>
        </div>
        <div class="px-5 py-4"><p id="modalMsg" class="text-textSec text-sm leading-relaxed whitespace-pre-line"></p></div>
        <div id="modalActions" class="px-5 py-3 flex justify-end gap-3 border-t border-border"></div>
    </div>
</div>

<!-- Mobile Bottom Nav for Portal -->
<nav id="mobileBottomNav" class="md:hidden fixed bottom-0 left-0 right-0 z-50 flex items-stretch justify-around">
<?php if ($isMemberOnly ?? false): ?>
    <a href="my_dues.php" class="flex flex-col items-center justify-center gap-0.5 flex-1 py-2 px-1 rounded-none transition text-textSec no-underline <?= $currentPage==='my_dues'?'!text-primary bg-primary/10':'' ?>">
        <i class="fas fa-receipt text-lg"></i><span class="text-[10px] font-bold">My Dues</span>
    </a>
    <a href="payment_receipts.php" class="flex flex-col items-center justify-center gap-0.5 flex-1 py-2 px-1 rounded-none transition text-textSec no-underline <?= $currentPage==='payment_receipts'?'!text-primary bg-primary/10':'' ?>">
        <i class="fas fa-table-list text-lg"></i><span class="text-[10px] font-bold">Receipts</span>
    </a>
<?php else: ?>
    <a href="dashboard.php" class="flex flex-col items-center justify-center gap-0.5 flex-1 py-2 px-1 transition text-textSec no-underline <?= $currentPage==='dashboard'?'!text-primary bg-primary/10':'' ?>">
        <i class="fas fa-chart-pie text-lg"></i><span class="text-[10px] font-bold">Overview</span>
    </a>
    <?php if ($isSA || !empty($permissions['VIEW_SNAPSHOTS'])): ?>
    <a href="gallery.php" class="flex flex-col items-center justify-center gap-0.5 flex-1 py-2 px-1 transition text-textSec no-underline <?= $currentPage==='gallery'?'!text-primary bg-primary/10':'' ?>">
        <i class="fas fa-photo-film text-lg"></i><span class="text-[10px] font-bold">Gallery</span>
    </a>
    <?php endif; ?>
    <?php if ($isSA || !empty($permissions['MANAGE_MAINTENANCE'])): ?>
    <a href="maintenance.php" class="flex flex-col items-center justify-center gap-0.5 flex-1 py-2 px-1 transition text-textSec no-underline <?= in_array($currentPage,['maintenance','maintenance_members','maintenance_settings','payment_receipts','pending_receipts'])?'!text-primary bg-primary/10':'' ?>">
        <i class="fas fa-building-columns text-lg"></i><span class="text-[10px] font-bold">Maint.</span>
    </a>
    <?php endif; ?>
    <?php if ($isSA || !empty($permissions['MANAGE_USERS'])): ?>
    <a href="manage_users.php" class="flex flex-col items-center justify-center gap-0.5 flex-1 py-2 px-1 transition text-textSec no-underline <?= $currentPage==='manage_users'?'!text-primary bg-primary/10':'' ?>">
        <i class="fas fa-users-cog text-lg"></i><span class="text-[10px] font-bold">Users</span>
    </a>
    <?php endif; ?>
    <!-- More menu trigger -->
    <button onclick="toggleSidebar()" class="flex flex-col items-center justify-center gap-0.5 flex-1 py-2 px-1 transition text-textSec bg-transparent border-0 cursor-pointer">
        <i class="fas fa-bars text-lg"></i><span class="text-[10px] font-bold">More</span>
    </button>
<?php endif; ?>
</nav>

<script>
const _themes={error:{icon:'fa-circle-exclamation',color:'#ef4444',bg:'rgba(239,68,68,0.15)',border:'#7f1d1d'},warning:{icon:'fa-triangle-exclamation',color:'#f59e0b',bg:'rgba(245,158,11,0.15)',border:'#78350f'},success:{icon:'fa-circle-check',color:'#10b981',bg:'rgba(16,185,129,0.15)',border:'#064e3b'},info:{icon:'fa-circle-info',color:'#6366f1',bg:'rgba(99,102,241,0.15)',border:'#312e81'}};
function _openModal(msg,type,buttons){const t=_themes[type]||_themes.info;document.getElementById('modalIcon').style.cssText=`background:${t.bg};color:${t.color};`;document.getElementById('modalIcon').innerHTML=`<i class="fas ${t.icon}"></i>`;document.getElementById('modalTitle').textContent=type.charAt(0).toUpperCase()+type.slice(1);document.getElementById('modalBox').style.borderColor=t.border;document.getElementById('modalMsg').textContent=msg;const a=document.getElementById('modalActions');a.innerHTML='';buttons.forEach(b=>{const btn=document.createElement('button');btn.textContent=b.text;btn.className=b.cls;btn.onclick=b.action;a.appendChild(btn);});document.getElementById('modalOverlay').style.display='flex';}
function _closeModal(){document.getElementById('modalOverlay').style.display='none';}
function showAlert(msg,type){if(!type){const l=(msg||'').toLowerCase();type=l.includes('error')||l.includes('fail')?'error':l.includes('success')||l.includes('saved')||l.includes('deleted')?'success':l.includes('warning')||l.includes('required')?'warning':'info';}_openModal(msg,type,[{text:'OK',cls:'px-6 py-2 rounded-lg font-bold text-textMain text-sm bg-indigo-600 hover:bg-indigo-500',action:_closeModal}]);}
function showConfirm(msg){return new Promise(r=>{_openModal(msg,'warning',[{text:'Cancel',cls:'px-5 py-2 rounded-lg font-bold text-textSec hover:text-textMain text-sm hover:bg-white/10',action:()=>{_closeModal();r(false);}},{text:'Confirm',cls:'px-5 py-2 rounded-lg font-bold text-textMain text-sm bg-yellow-600 hover:bg-yellow-500',action:()=>{_closeModal();r(true);}}]);});}

// Portal state for JS
const PORTAL = {
    clientId: <?= json_encode($session['client_id']) ?>,
    role: <?= json_encode($session['role']) ?>,
    username: <?= json_encode($session['username']) ?>,
    employeeId: <?= json_encode($_SESSION['portal_employee_id'] ?? '') ?>,
    permissions: <?= json_encode($permissions) ?>
};

function sendPortalHeartbeat() {
    try {
        fetch('../activity_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'heartbeat' }),
            keepalive: true
        }).catch(() => {});
    } catch (e) {
    }
}

let _notifPanelOpen = false;

function _notifApi(action, payload) {
    const body = Object.assign({ action }, payload || {});
    return fetch('../portal_notifications_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
    }).then(r => r.json());
}

function _notifKindClass(kind) {
    const t = String(kind || 'INFO').toUpperCase();
    if (t === 'ERROR') return 'text-red-400';
    if (t === 'WARNING') return 'text-yellow-400';
    if (t === 'SUCCESS') return 'text-emerald-400';
    return 'text-indigo-400';
}

function _renderNotifications(data) {
    const panel = document.getElementById('portalNotifPanel');
    const count = document.getElementById('portalNotifCount');
    const list = document.getElementById('portalNotifList');
    if (!panel || !count || !list) return;

    const unread = Number(data.unread_count || 0);
    count.textContent = unread > 99 ? '99+' : String(unread);
    count.style.display = unread > 0 ? 'inline-flex' : 'none';

    const rows = data.notifications || [];
    if (!rows.length) {
        list.innerHTML = '<div class="text-xs text-textSec text-center py-6">No notifications</div>';
        return;
    }

    list.innerHTML = rows.map(n => {
        const created = new Date(n.created_at).toLocaleString('en-IN', {
            day: '2-digit',
            month: 'short',
            hour: '2-digit',
            minute: '2-digit'
        });
        const unreadCls = Number(n.is_read) ? '' : 'border-indigo-700/40 bg-indigo-900/10';
        const targetAttr = n.target_url ? ` data-targeturl="${String(n.target_url).replace(/"/g, '&quot;')}"` : '';
        return `<div class="rounded-lg border border-border p-3 ${unreadCls}"${targetAttr}>
            <div class="flex items-start gap-2">
                <i class="fas fa-circle text-[9px] mt-1 ${_notifKindClass(n.kind)}"></i>
                <div class="min-w-0 flex-1 cursor-pointer" onclick="openPortalNotification(${n.id}, this)">
                    <div class="text-sm text-gray-100 leading-tight">${escNotif(n.title)}</div>
                    <div class="text-xs text-textSec mt-1">${escNotif(n.message || '')}</div>
                    <div class="text-[10px] text-textSec mt-1">${created}</div>
                </div>
                <button type="button" onclick="deletePortalNotification(${n.id})" class="text-textSec hover:text-red-400 text-xs bg-transparent border-none cursor-pointer" title="Delete">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>`;
    }).join('');
}

function escNotif(s) {
    return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

async function refreshPortalNotifications() {
    try {
        const data = await _notifApi('list', { limit: 40 });
        if (!data.error) _renderNotifications(data);
    } catch (e) {
    }
}

async function togglePortalNotifications() {
    const panel = document.getElementById('portalNotifPanel');
    if (!panel) return;
    _notifPanelOpen = !_notifPanelOpen;
    panel.classList.toggle('hidden', !_notifPanelOpen);
    if (_notifPanelOpen) {
        await _notifApi('mark_all_read', {});
        await refreshPortalNotifications();
    }
}

async function deletePortalNotification(id) {
    await _notifApi('delete', { id });
    await refreshPortalNotifications();
}

async function deleteAllPortalNotifications() {
    const ok = await showConfirm('Delete all notifications?');
    if (!ok) return;
    await _notifApi('delete_all', {});
    await refreshPortalNotifications();
}

async function openPortalNotification(id, el) {
    await _notifApi('mark_read', { id });
    const holder = el && el.closest('[data-targeturl]');
    const target = holder ? holder.getAttribute('data-targeturl') : '';
    await refreshPortalNotifications();
    if (target) {
        window.location.href = target;
    }
}

function mountPortalNotificationBell() {
    const topbar = document.querySelector('.portal-topbar');
    if (!topbar) return;
    if (document.getElementById('portalNotifBtn')) return;

    let actions = topbar.querySelector('.portal-topbar-actions');
    if (!actions) {
        actions = document.createElement('div');
        actions.className = 'portal-topbar-actions flex items-center gap-2 flex-shrink-0 relative';

        const children = Array.from(topbar.children);
        const titleBlock = children[0] || null;
        const lastChild = children.length > 1 ? children[children.length - 1] : null;

        if (lastChild && lastChild !== titleBlock) {
            topbar.insertBefore(actions, lastChild.nextSibling);
            actions.appendChild(lastChild);
        } else {
            topbar.appendChild(actions);
        }
    }

    const wrap = document.createElement('div');
    wrap.className = 'relative';
    wrap.innerHTML = `
        <button type="button" id="portalNotifBtn" onclick="togglePortalNotifications()"
            class="w-10 h-10 rounded-lg border border-border bg-gray-900/70 text-textSec hover:text-textMain hover:border-indigo-500 transition relative">
            <i class="fas fa-bell"></i>
            <span id="portalNotifCount" style="display:none" class="absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 rounded-full bg-red-500 text-textMain text-[10px] font-bold items-center justify-center">0</span>
        </button>
        <div id="portalNotifPanel" class="hidden absolute right-0 mt-2 w-[340px] max-w-[88vw] rounded-xl border border-border bg-surface shadow-2xl z-[120]">
            <div class="px-3 py-2 border-b border-border flex items-center justify-between">
                <div class="text-xs font-bold uppercase tracking-wider text-textSec">Notifications</div>
                <button type="button" onclick="deleteAllPortalNotifications()" class="text-[11px] text-red-400 hover:text-red-300 bg-transparent border-none cursor-pointer">Delete All</button>
            </div>
            <div id="portalNotifList" class="max-h-[360px] overflow-y-auto p-2 space-y-2"></div>
        </div>
    `;
    actions.appendChild(wrap);

    document.addEventListener('click', function (ev) {
        if (!_notifPanelOpen) return;
        const panel = document.getElementById('portalNotifPanel');
        const btn = document.getElementById('portalNotifBtn');
        if (!panel || !btn) return;
        if (panel.contains(ev.target) || btn.contains(ev.target)) return;
        _notifPanelOpen = false;
        panel.classList.add('hidden');
    });

    refreshPortalNotifications();
    setInterval(refreshPortalNotifications, 45000);
}

// Inject hamburger button into topbar (mobile)
document.addEventListener('DOMContentLoaded', function() {
    sendPortalHeartbeat();
    setInterval(sendPortalHeartbeat, 15000);
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) sendPortalHeartbeat();
    });

    mountPortalNotificationBell();

    var topbar = document.querySelector('.portal-topbar');
    if (topbar && !topbar.querySelector('[aria-label="Open menu"]')) {
        var btn = document.createElement('button');
        btn.innerHTML = '<i class="fas fa-bars text-xl"></i>';
        btn.className = 'md:hidden text-textSec hover:text-textMain transition mr-3 flex-shrink-0';
        btn.setAttribute('aria-label', 'Open menu');
        btn.onclick = function() { toggleSidebar(); };
        topbar.insertBefore(btn, topbar.firstChild);
        topbar.classList.add('px-4', 'md:px-6');
    }
});
</script>
</body>
</html>
