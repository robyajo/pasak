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

type RelatedBlock = {
    id: number;
    is_active: boolean;
    reason: string | null;
} | null;

type Resolver = {
    name: string;
} | null;

type TicketDetail = {
    id: number;
    ticket_number: string;
    ip_address: string;
    device_id: string | null;
    local_ip: string | null;
    name: string;
    email: string;
    phone: string | null;
    reason: string;
    status: string;
    admin_notes: string | null;
    resolved_at: string | null;
    user_agent: string | null;
    created_at: string;
    blocked_ip: RelatedBlock;
    resolver: Resolver;
};

type TicketDetailProps = {
    ticket: TicketDetail;
    relatedLogs: RelatedLog[];
    urls: { index: string; show: string; logShow: string; blockShow: string; respond: string; destroy: string };
};

function DetailItem({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="sec-detail-item">
            <div className="sec-detail-label">{label}</div>
            <div className="sec-detail-value">{children}</div>
        </div>
    );
}

export default function TicketDetail({ ticket, relatedLogs, urls }: TicketDetailProps) {
    const [flashMsg, setFlashMsg] = useState<string | null>(null);
    const [responding, setResponding] = useState<'approve' | 'reject' | null>(null);
    const [adminNotes, setAdminNotes] = useState('');

    const openRespond = (action: 'approve' | 'reject') => {
        setAdminNotes('');
        setResponding(action);
    };

    const confirmRespond = () => {
        if (responding === null) {
            return;
        }
        router.post(
            urls.respond,
            { action: responding, admin_notes: adminNotes },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setFlashMsg(responding === 'approve' ? 'Ticket approved and IP unblocked.' : 'Ticket rejected.');
                    setResponding(null);
                },
            },
        );
    };

    const destroy = () => {
        if (!confirm('Delete this ticket?')) {
            return;
        }
        router.delete(urls.destroy, {
            onSuccess: () => setFlashMsg('Ticket deleted.'),
        });
    };

    return (
        <div className="sec-root" style={{ padding: '24px 20px', minHeight: '100vh', background: 'var(--sec-bg)' }}>
            <Head title={`Ticket ${ticket.ticket_number}`} />

            <SecurityNav />

            {flashMsg && (
                <div className="sec-alert sec-alert-success" style={{ marginBottom: '16px' }}>
                    <span>{flashMsg}</span>
                </div>
            )}

            <Link href={urls.index} className="sec-back-link">
                ← Back to appeals
            </Link>

            <div className="sec-page-header">
                <h1 className="sec-page-title">Ticket {ticket.ticket_number}</h1>
                <p className="sec-page-subtitle">
                    {ticket.name} — <span className="sec-font-mono">{ticket.ip_address}</span>
                </p>
            </div>

            <Card>
                <CardContent>
                    <div className="sec-detail-grid">
                        <DetailItem label="Status">
                            <Badge variant={ticket.status === 'approved' ? 'success' : ticket.status === 'rejected' ? 'critical' : 'high'}>
                                {ticket.status}
                            </Badge>
                        </DetailItem>
                        <DetailItem label="IP address">
                            <span className="sec-font-mono" style={{ fontWeight: 600 }}>{ticket.ip_address}</span>
                        </DetailItem>
                        <DetailItem label="Requester">{ticket.name}</DetailItem>
                        <DetailItem label="Email">{ticket.email}</DetailItem>
                        {ticket.phone && <DetailItem label="Phone">{ticket.phone}</DetailItem>}
                        <DetailItem label="Submitted">{new Date(ticket.created_at).toLocaleString()}</DetailItem>
                        {(ticket.device_id || ticket.local_ip) && (
                            <DetailItem label="Device">
                                <span className="sec-font-mono">{ticket.device_id ?? '-'} / {ticket.local_ip ?? '-'}</span>
                            </DetailItem>
                        )}
                        {ticket.resolved_at && (
                            <DetailItem label="Resolved">
                                {new Date(ticket.resolved_at).toLocaleString()}
                                {ticket.resolver ? ` — ${ticket.resolver.name}` : ''}
                            </DetailItem>
                        )}
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardContent>
                    <div className="sec-detail-label">Requester reason</div>
                    <pre className="sec-code-block">{ticket.reason}</pre>
                    {ticket.user_agent && (
                        <>
                            <div className="sec-detail-label" style={{ marginTop: '14px' }}>User agent</div>
                            <div className="sec-detail-value" style={{ fontSize: '12.5px' }}>{ticket.user_agent}</div>
                        </>
                    )}
                    {ticket.admin_notes && (
                        <>
                            <div className="sec-detail-label" style={{ marginTop: '14px' }}>Admin notes</div>
                            <pre className="sec-code-block">{ticket.admin_notes}</pre>
                        </>
                    )}
                </CardContent>
            </Card>

            {ticket.blocked_ip && (
                <Card>
                    <CardHeader>
                        <CardTitle>Related block</CardTitle>
                        <Link
                            href={urls.blockShow.replace('__ID__', String(ticket.blocked_ip.id))}
                            className="sec-btn sec-btn-secondary sec-btn-sm"
                        >
                            View block
                        </Link>
                    </CardHeader>
                    <CardContent>
                        <div className="sec-detail-grid">
                            <DetailItem label="Status">{ticket.blocked_ip.is_active ? 'Active' : 'Inactive'}</DetailItem>
                            <DetailItem label="Reason">{ticket.blocked_ip.reason ?? '-'}</DetailItem>
                        </div>
                    </CardContent>
                </Card>
            )}

            {relatedLogs.length > 0 && (
                <Card flush>
                    <CardHeader>
                        <CardTitle>Recent logs from this IP</CardTitle>
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

            {ticket.status === 'pending' ? (
                <div style={{ display: 'flex', gap: '10px', flexWrap: 'wrap' }}>
                    <Button variant="primary" onClick={() => openRespond('approve')}>
                        Approve & unblock
                    </Button>
                    <Button variant="danger" onClick={() => openRespond('reject')}>
                        Reject
                    </Button>
                </div>
            ) : (
                <Button size="sm" variant="danger" onClick={destroy}>
                    Delete ticket
                </Button>
            )}

            {responding !== null && (
                <div className="sec-modal-backdrop">
                    <div className="sec-modal-box">
                        <div className="sec-modal-header">
                            <h3 className="sec-card-title">
                                {responding === 'approve' ? 'Approve appeal' : 'Reject appeal'}
                            </h3>
                            <p className="sec-card-subtitle">
                                {responding === 'approve'
                                    ? 'The blocked IP/device will be unblocked automatically.'
                                    : 'The block will remain active.'}
                            </p>
                        </div>
                        <div className="sec-modal-body">
                            <div>
                                <label style={{ display: 'block', fontSize: '12px', fontWeight: 600, marginBottom: '4px' }}>
                                    Admin notes
                                </label>
                                <textarea
                                    value={adminNotes}
                                    onChange={(e) => setAdminNotes(e.target.value)}
                                    rows={3}
                                    className="sec-textarea"
                                    placeholder="Optional notes shown to the applicant…"
                                />
                            </div>
                        </div>
                        <div className="sec-modal-footer">
                            <Button variant="secondary" onClick={() => setResponding(null)}>
                                Cancel
                            </Button>
                            <Button variant={responding === 'approve' ? 'primary' : 'danger'} onClick={confirmRespond}>
                                {responding === 'approve' ? 'Approve & unblock' : 'Reject'}
                            </Button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
