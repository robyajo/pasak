import { Head, Link, router } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import '@/components/pasak/pasak.css';
import { SecurityNav } from '@/components/pasak/pasak-nav';
import { Badge, Button, Card, CardContent, CardHeader, CardTitle } from '@/components/pasak/ui';

type RelatedLog = {
    id: number;
    event_type: string;
    path: string | null;
    created_at: string;
};

type RelatedTicket = {
    id: number;
    ticket_number: string;
    name: string;
    status: string;
};

type BlockDetail = {
    id: number;
    ip_address: string;
    device_id: string | null;
    local_ip: string | null;
    block_scope: string;
    reason: string | null;
    notes: string | null;
    source: string;
    blocked_at: string | null;
    expires_at: string | null;
    is_active: boolean;
    hit_count: number;
    last_hit_at: string | null;
    created_at: string;
};

type BlockedIpDetailProps = {
    block: BlockDetail;
    relatedLogs: RelatedLog[];
    relatedTickets: RelatedTicket[];
    urls: { index: string; show: string; logShow: string; ticketShow: string; toggle: string; destroy: string };
};

function DetailItem({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="sec-detail-item">
            <div className="sec-detail-label">{label}</div>
            <div className="sec-detail-value">{children}</div>
        </div>
    );
}

export default function BlockedIpDetail({ block, relatedLogs, relatedTickets, urls }: BlockedIpDetailProps) {
    const [flashMsg, setFlashMsg] = useState<string | null>(null);

    const toggle = () => {
        router.patch(
            urls.toggle,
            {},
            { preserveScroll: true, onSuccess: () => setFlashMsg(block.is_active ? 'Block disabled.' : 'Block re-enabled.') },
        );
    };

    const unblock = () => {
        if (!confirm('Unblock this IP?')) {
            return;
        }
        router.delete(urls.destroy, {
            onSuccess: () => setFlashMsg('IP unblocked.'),
        });
    };

    return (
        <div className="sec-root" style={{ padding: '24px 20px', minHeight: '100vh', background: 'var(--sec-bg)' }}>
            <Head title="Block detail" />

            <SecurityNav />

            {flashMsg && (
                <div className="sec-alert sec-alert-success" style={{ marginBottom: '16px' }}>
                    <span>{flashMsg}</span>
                </div>
            )}

            <Link href={urls.index} className="sec-back-link">
                ← Back to blocked IPs
            </Link>

            <div className="sec-page-header">
                <h1 className="sec-page-title">Block detail</h1>
                <p className="sec-page-subtitle sec-font-mono">{block.ip_address}</p>
            </div>

            <Card>
                <CardContent>
                    <div className="sec-detail-grid">
                        <DetailItem label="IP address">
                            <span className="sec-font-mono" style={{ fontWeight: 600 }}>{block.ip_address}</span>
                        </DetailItem>
                        <DetailItem label="Status">
                            {block.is_active ? (
                                <Badge variant="critical">Active</Badge>
                            ) : (
                                <Badge variant="low">Inactive</Badge>
                            )}
                        </DetailItem>
                        <DetailItem label="Scope">{block.block_scope}</DetailItem>
                        <DetailItem label="Source">{block.source}</DetailItem>
                        <DetailItem label="Blocked at">
                            {block.blocked_at ? new Date(block.blocked_at).toLocaleString() : new Date(block.created_at).toLocaleString()}
                        </DetailItem>
                        <DetailItem label="Expires">
                            {block.expires_at ? new Date(block.expires_at).toLocaleString() : 'Permanent'}
                        </DetailItem>
                        <DetailItem label="Hits since block">{Number(block.hit_count).toLocaleString()}</DetailItem>
                        <DetailItem label="Last hit">
                            {block.last_hit_at ? new Date(block.last_hit_at).toLocaleString() : '-'}
                        </DetailItem>
                        {(block.device_id || block.local_ip) && (
                            <DetailItem label="Device">
                                <span className="sec-font-mono">{block.device_id ?? '-'} / {block.local_ip ?? '-'}</span>
                            </DetailItem>
                        )}
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardContent>
                    <div className="sec-detail-label">Reason</div>
                    <p style={{ margin: '0 0 14px 0', fontSize: '14px' }}>{block.reason ?? '-'}</p>
                    {block.notes && (
                        <>
                            <div className="sec-detail-label">Notes</div>
                            <pre className="sec-code-block">{block.notes}</pre>
                        </>
                    )}
                </CardContent>
            </Card>

            {relatedLogs.length > 0 && (
                <Card flush>
                    <CardHeader>
                        <CardTitle>Triggering logs ({relatedLogs.length})</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="sec-table-wrap">
                            <table className="sec-table">
                                <thead>
                                    <tr>
                                        <th>Time</th>
                                        <th>Event</th>
                                        <th>Path</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {relatedLogs.map((log) => (
                                        <tr key={log.id}>
                                            <td className="sec-font-mono" style={{ fontSize: '12px', whiteSpace: 'nowrap' }}>
                                                {new Date(log.created_at).toLocaleString()}
                                            </td>
                                            <td>{log.event_type}</td>
                                            <td className="sec-font-mono" style={{ fontSize: '12px', maxWidth: '280px', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                                                {log.path}
                                            </td>
                                            <td style={{ textAlign: 'right' }}>
                                                <Link
                                                    href={urls.logShow.replace('__ID__', String(log.id))}
                                                    className="sec-btn sec-btn-ghost sec-btn-sm"
                                                >
                                                    Detail
                                                </Link>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            )}

            {relatedTickets.length > 0 && (
                <Card flush>
                    <CardHeader>
                        <CardTitle>Appeals for this IP</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="sec-table-wrap">
                            <table className="sec-table">
                                <thead>
                                    <tr>
                                        <th>Ticket</th>
                                        <th>Name</th>
                                        <th>Status</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {relatedTickets.map((ticket) => (
                                        <tr key={ticket.id}>
                                            <td className="sec-font-mono">{ticket.ticket_number}</td>
                                            <td>{ticket.name}</td>
                                            <td>{ticket.status}</td>
                                            <td style={{ textAlign: 'right' }}>
                                                <Link
                                                    href={urls.ticketShow.replace('__ID__', String(ticket.id))}
                                                    className="sec-btn sec-btn-ghost sec-btn-sm"
                                                >
                                                    Detail
                                                </Link>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            )}

            <div style={{ display: 'flex', gap: '10px', flexWrap: 'wrap' }}>
                <Button size="sm" variant="secondary" onClick={toggle}>
                    {block.is_active ? 'Disable block' : 'Re-enable block'}
                </Button>
                <Button size="sm" variant="danger" onClick={unblock}>
                    Unblock
                </Button>
            </div>
        </div>
    );
}
