<style>
.sec-tabs {
    display: flex;
    gap: .5rem;
    overflow-x: auto;
    padding-bottom: .5rem;
    margin-bottom: 1.5rem;
    border-bottom: 1px solid #1f2937;
}
.sec-tab {
    padding: .5rem .9rem;
    font-size: .85rem;
    font-weight: 600;
    border-radius: .5rem;
    color: #9ca3af;
    background: transparent;
    text-decoration: none;
    white-space: nowrap;
    transition: all .15s;
    display: inline-flex;
    align-items: center;
    gap: .4rem;
}
.sec-tab:hover {
    color: #e6edf3;
    background: #1f2937;
    text-decoration: none;
}
.sec-tab.active {
    color: #fff;
    background: #1e3a8a;
    border: 1px solid #3b82f6;
}
.sec-grid-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}
.sec-stat-card {
    background: #111827;
    border: 1px solid #1f2937;
    border-radius: .75rem;
    padding: 1.1rem;
}
.sec-stat-card .val {
    font-size: 1.75rem;
    font-weight: 800;
    line-height: 1.2;
    margin-bottom: .25rem;
}
.sec-stat-card .lbl {
    font-size: .85rem;
    color: #9ca3af;
}
.badge-level {
    font-size: .7rem;
    font-weight: 700;
    padding: .15rem .5rem;
    border-radius: .375rem;
    text-transform: uppercase;
}
.badge-level.critical { background: #7f1d1d; color: #fecaca; }
.badge-level.high { background: #78350d; color: #fef08a; }
.badge-level.medium { background: #1e3a8a; color: #bfdbfe; }
.badge-level.low { background: #374151; color: #e5e7eb; }
.badge-status {
    font-size: .7rem;
    font-weight: 700;
    padding: .15rem .5rem;
    border-radius: .375rem;
}
.badge-status.active { background: #052e16; color: #86efac; border: 1px solid #16a34a; }
.badge-status.inactive { background: #374151; color: #9ca3af; }
.badge-status.pending { background: #78350d; color: #fde047; }
.badge-status.resolved { background: #052e16; color: #86efac; }
.badge-status.rejected { background: #7f1d1d; color: #fecaca; }
.badge-status.ok { background: #052e16; color: #86efac; }
.badge-status.warning { background: #78350d; color: #fde047; }
.badge-status.info { background: #1e3a8a; color: #bfdbfe; }
.table-responsive {
    overflow-x: auto;
}
.filter-bar {
    display: flex;
    gap: .75rem;
    margin-bottom: 1rem;
    flex-wrap: wrap;
    align-items: center;
}
.filter-bar input, .filter-bar select {
    width: auto;
    min-width: 180px;
}
</style>

<div style="margin-bottom: 1rem;">
    <h1 style="margin: 0 0 .25rem 0; font-size: 1.75rem;">🛡️ Pusat Keamanan Sistem (Full Custom)</h1>
    <p style="margin: 0; color: #9ca3af; font-size: .9rem;">
        Dashboard kustom aplikasi host mengintegrasikan seluruh 8 modul keamanan <code>robyajo/pasak</code>.
    </p>
</div>

<nav class="sec-tabs">
    <a href="{{ route('keamanan.index') }}" class="sec-tab {{ request()->routeIs('keamanan.index') ? 'active' : '' }}">
        📊 Ringkasan
    </a>
    <a href="{{ route('keamanan.logs') }}" class="sec-tab {{ request()->routeIs('keamanan.logs') ? 'active' : '' }}">
        📜 Log Ancaman
    </a>
    <a href="{{ route('keamanan.blocked-ips') }}" class="sec-tab {{ request()->routeIs('keamanan.blocked-ips') ? 'active' : '' }}">
        🚫 IP Diblokir
    </a>
    <a href="{{ route('keamanan.allowed-ips') }}" class="sec-tab {{ request()->routeIs('keamanan.allowed-ips') ? 'active' : '' }}">
        ✅ Whitelist IP
    </a>
    <a href="{{ route('keamanan.server') }}" class="sec-tab {{ request()->routeIs('keamanan.server') ? 'active' : '' }}">
        🖥️ Audit Server
    </a>
    <a href="{{ route('keamanan.sessions') }}" class="sec-tab {{ request()->routeIs('keamanan.sessions') ? 'active' : '' }}">
        👥 Sesi Login
    </a>
    <a href="{{ route('keamanan.tickets') }}" class="sec-tab {{ request()->routeIs('keamanan.tickets*') ? 'active' : '' }}">
        🎫 Tiket Banding
    </a>
    <a href="{{ route('keamanan.settings') }}" class="sec-tab {{ request()->routeIs('keamanan.settings') ? 'active' : '' }}">
        ⚙️ Pengaturan WAF
    </a>
</nav>
