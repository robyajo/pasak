import { Link, usePage } from '@inertiajs/react';

function NavIcon({ paths }: { paths: string }) {
    return (
        <svg
            className="sec-nav-icon"
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="2"
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden="true"
            dangerouslySetInnerHTML={{ __html: paths }}
        />
    );
}

const items = [
    {
        title: 'Overview',
        path: '',
        icon: '<rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/>',
    },
    {
        title: 'Security Logs',
        path: 'logs',
        icon: '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M9 13h6"/><path d="M9 17h6"/>',
    },
    {
        title: 'Blocked IPs',
        path: 'blocked-ips',
        icon: '<circle cx="12" cy="12" r="10"/><path d="m4.9 4.9 14.2 14.2"/>',
    },
    {
        title: 'Server Audit',
        path: 'server',
        icon: '<rect width="20" height="8" x="2" y="2" rx="2"/><rect width="20" height="8" x="2" y="14" rx="2"/><path d="M6 6h.01"/><path d="M6 18h.01"/>',
    },
    {
        title: 'Sessions',
        path: 'sessions',
        icon: '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
    },
    {
        title: 'Appeals',
        path: 'tickets',
        icon: '<polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>',
    },
    {
        title: 'Settings',
        path: 'settings',
        icon: '<circle cx="12" cy="12" r="3"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
    },
];

export function SecurityNav({ base }: { base?: string }) {
    const { url } = usePage();
    const current = url.split('?')[0];

    // Ikuti prefix dashboard terkonfigurasi (default /pasak) tanpa hardcode.
    const resolvedBase =
        base ?? `/${current.split('/').filter(Boolean)[0] ?? 'pasak'}`;

    return (
        <nav className="sec-nav-bar" aria-label="Pasak Navigation">
            {items.map((item) => {
                const href =
                    item.path === '' ? resolvedBase : `${resolvedBase}/${item.path}`;
                const active = current === href;

                return (
                    <Link
                        key={href}
                        href={href}
                        prefetch
                        className={`sec-nav-link ${active ? 'active' : ''}`}
                    >
                        <NavIcon paths={item.icon} />
                        <span>{item.title}</span>
                    </Link>
                );
            })}
        </nav>
    );
}
