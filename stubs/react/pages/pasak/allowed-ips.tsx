import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import '@/components/pasak/pasak.css';
import { Pagination } from '@/components/pasak/pagination';
import { SecurityNav } from '@/components/pasak/pasak-nav';
import { Badge, Button, Card, CardContent, CardHeader, CardTitle, Input, Label } from '@/components/pasak/ui';

type AllowedIp = {
    id: number;
    ip_address: string;
    label: string | null;
    is_active: boolean;
    expires_at: string | null;
};

type Paginator<T> = {
    data: T[];
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
};

type AllowedIpsProps = {
    entries: Paginator<AllowedIp>;
    stats: { total: number; active: number; permanent: number };
    filters: { search: string; status: string };
    urls: {
        index: string;
        store: string;
        toggle: string;
        destroy: string;
    };
};

function statusOf(entry: AllowedIp): string {
    if (!entry.is_active) {
        return 'Disabled';
    }

    if (entry.expires_at && new Date(entry.expires_at) < new Date()) {
        return 'Expired';
    }

    return 'Active';
}

function statusBadgeVariant(status: string): 'success' | 'high' | 'low' {
    if (status === 'Active') return 'success';
    if (status === 'Expired') return 'high';
    return 'low';
}

export default function AllowedIps({
    entries,
    stats,
    filters,
    urls,
}: AllowedIpsProps) {
    const [search, setSearch] = useState(filters.search);
    const [status, setStatus] = useState(filters.status);
    const [flashMsg, setFlashMsg] = useState<string | null>(null);

    const showNotification = (msg: string) => {
        setFlashMsg(msg);
        setTimeout(() => setFlashMsg(null), 4000);
    };

    const form = useForm({
        ip_address: '',
        label: '',
        notes: '',
        duration_hours: 0,
        is_permanent: true,
    });

    const applyFilters = () => {
        router.get(
            urls.index,
            { search, status },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const submitAllow = (event: React.FormEvent) => {
        event.preventDefault();

        form.post(urls.store, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                showNotification('IP allowed successfully.');
            },
        });
    };

    const toggle = (id: number) => {
        router.patch(
            urls.toggle.replace('__ID__', String(id)),
            {},
            {
                preserveScroll: true,
                onSuccess: () => showNotification('Allow status updated.'),
            },
        );
    };

    const remove = (id: number) => {
        if (!confirm('Remove this allow entry?')) {
            return;
        }

        router.delete(urls.destroy.replace('__ID__', String(id)), {
            preserveScroll: true,
            onSuccess: () => showNotification('Allow entry removed.'),
        });
    };

    return (
        <div className="sec-root" style={{ padding: '24px 20px', minHeight: '100vh', background: 'var(--sec-bg)' }}>
            <Head title="Allowed IPs" />

            <SecurityNav base={urls.index.replace(/\/allowed-ips\/?$/, '') || '/pasak'} />

            {flashMsg && (
                <div className="sec-alert sec-alert-success" style={{ marginBottom: '16px' }}>
                    <span>{flashMsg}</span>
                </div>
            )}

            <div className="sec-page-header">
                <h1 className="sec-page-title">Allowed IPs</h1>
                <p className="sec-page-subtitle">
                    IPs that are always allowed — never logged or blocked.
                </p>
            </div>

            <div className="sec-grid-4">
                <div className="sec-stat-card">
                    <div className="sec-stat-label">Total entries</div>
                    <div className="sec-stat-value">{stats.total}</div>
                </div>
                <div className="sec-stat-card">
                    <div className="sec-stat-label">Active</div>
                    <div className="sec-stat-value" style={{ color: 'var(--sec-success)' }}>{stats.active}</div>
                </div>
                <div className="sec-stat-card">
                    <div className="sec-stat-label">Permanent</div>
                    <div className="sec-stat-value">{stats.permanent}</div>
                </div>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Allow an IP, wildcard, or CIDR</CardTitle>
                </CardHeader>
                <CardContent>
                    <form
                        onSubmit={submitAllow}
                        style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(240px, 1fr))', gap: '14px', alignItems: 'flex-end', paddingTop: '8px' }}
                    >
                        <div>
                            <Label htmlFor="ip_address">IP / wildcard / CIDR *</Label>
                            <Input
                                id="ip_address"
                                value={form.data.ip_address}
                                onChange={(e) =>
                                    form.setData('ip_address', e.target.value)
                                }
                                placeholder="203.0.113.10 / 192.168.1.* / 10.0.0.0/8"
                                required
                                style={{ width: '100%' }}
                            />
                            {form.errors.ip_address && (
                                <p style={{ color: 'var(--sec-danger)', fontSize: '11px', margin: '4px 0 0 0' }}>
                                    {form.errors.ip_address}
                                </p>
                            )}
                        </div>

                        <div>
                            <Label htmlFor="label">Label</Label>
                            <Input
                                id="label"
                                value={form.data.label}
                                onChange={(e) =>
                                    form.setData('label', e.target.value)
                                }
                                placeholder="e.g. Office network"
                                style={{ width: '100%' }}
                            />
                        </div>

                        <div>
                            <Label htmlFor="duration_hours">
                                Duration (hours, 0 = permanent)
                            </Label>
                            <Input
                                id="duration_hours"
                                type="number"
                                min={0}
                                value={form.data.duration_hours}
                                onChange={(e) =>
                                    form.setData(
                                        'duration_hours',
                                        Number(e.target.value),
                                    )
                                }
                                style={{ width: '100%' }}
                            />
                        </div>

                        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: '12px' }}>
                            <label style={{ display: 'inline-flex', alignItems: 'center', gap: '8px', fontSize: '13px', cursor: 'pointer' }}>
                                <input
                                    type="checkbox"
                                    checked={form.data.is_permanent}
                                    onChange={(e) =>
                                        form.setData(
                                            'is_permanent',
                                            e.target.checked,
                                        )
                                    }
                                />
                                <span>Permanent</span>
                            </label>

                            <Button
                                type="submit"
                                variant="primary"
                                disabled={form.processing}
                            >
                                Allow
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>

            <div className="sec-toolbar">
                <div className="sec-toolbar-group">
                    <Input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        onKeyDown={(e: React.KeyboardEvent<HTMLInputElement>) => e.key === 'Enter' && applyFilters()}
                        placeholder="Search IP, label…"
                        style={{ minWidth: '240px' }}
                    />

                    <select
                        value={status}
                        onChange={(e) => setStatus(e.target.value)}
                        className="sec-select"
                    >
                        <option value="">All statuses</option>
                        <option value="active">Active</option>
                        <option value="expired">Expired</option>
                        <option value="permanent">Permanent</option>
                    </select>

                    <Button variant="secondary" onClick={applyFilters}>
                        Filter
                    </Button>
                </div>
            </div>

            <Card flush>
                <CardContent>
                    <div className="sec-table-wrap">
                        <table className="sec-table">
                            <thead>
                                <tr>
                                    <th>IP / range</th>
                                    <th>Label</th>
                                    <th>Status</th>
                                    <th>Expires</th>
                                    <th style={{ textAlign: 'right' }}>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {entries.data.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={5}
                                            style={{ textAlign: 'center', color: 'var(--sec-text-muted)', padding: '36px' }}
                                        >
                                            No allowed IPs yet.
                                        </td>
                                    </tr>
                                )}
                                {entries.data.map((entry) => {
                                    const st = statusOf(entry);

                                    return (
                                        <tr key={entry.id}>
                                            <td>
                                                <div className="sec-font-mono" style={{ fontWeight: 600, fontSize: '13px' }}>
                                                    {entry.ip_address}
                                                </div>
                                            </td>
                                            <td style={{ maxWidth: '250px', fontSize: '12px' }}>
                                                {entry.label ?? '-'}
                                            </td>
                                            <td>
                                                <Badge variant={statusBadgeVariant(st)}>
                                                    {st}
                                                </Badge>
                                            </td>
                                            <td style={{ fontSize: '12px', color: 'var(--sec-text-muted)', whiteSpace: 'nowrap' }}>
                                                {entry.expires_at
                                                    ? new Date(entry.expires_at).toLocaleString()
                                                    : 'Never'}
                                            </td>
                                            <td style={{ textAlign: 'right', whiteSpace: 'nowrap' }}>
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    onClick={() => toggle(entry.id)}
                                                    style={{ marginRight: '6px' }}
                                                >
                                                    {entry.is_active ? 'Disable' : 'Enable'}
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    onClick={() => remove(entry.id)}
                                                >
                                                    Remove
                                                </Button>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

            <div style={{ marginTop: '16px' }}>
                <Pagination links={entries.links} total={entries.total} />
            </div>
        </div>
    );
}
