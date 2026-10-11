import { Head, Link, router } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import '@/components/pasak/pasak.css';
import { SecurityNav } from '@/components/pasak/pasak-nav';
import { Badge, Button, Card, CardContent, CardHeader, CardTitle } from '@/components/pasak/ui';

type RelatedLog = {
    id: number;
    event_type: string;
    threat_level: string;
    path: string | null;
    created_at: string;
};

type LogDetail = {
    id: number;
    ip_address: string;
    device_id: string | null;
    local_ip: string | null;
    event_type: string;
    threat_level: string;
    method: string | null;
    path: string | null;
    full_url: string | null;
    rule_label: string | null;
    evidence: string | null;
    user_agent: string | null;
    referer: string | null;
    was_blocked: boolean;
    action_taken: string | null;
    meta: Record<string, unknown> | null;
    created_at: string;
};

type RelatedBlock = {
    id: number;
    is_active: boolean;
    reason: string | null;
    expires_at: string | null;
} | null;

type LogDetailProps = {
    log: LogDetail;
    relatedLogs: RelatedLog[];
    block: RelatedBlock;
    urls: { index: string; show: string; blockShow: string; destroy: string };
};

const levelLabels: Record<string, string> = {
    critical: 'Critical',
    high: 'High',
    medium: 'Medium',
    low: 'Low',
};

function DetailItem({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="sec-detail-item">
            <div className="sec-detail-label">{label}</div>
            <div className="sec-detail-value">{children}</div>
        </div>
    );
}

export default function SecurityLogDetail({ log, relatedLogs, block, urls }: LogDetailProps) {
    const [flashMsg, setFlashMsg] = useState<string | null>(null);

    const destroy = () => {
        if (!confirm('Delete this log entry?')) {
            return;
        }
        router.delete(urls.destroy, {
            onSuccess: () => setFlashMsg('Log entry deleted.'),
        });
    };

    return (
        <div className="sec-root" style={{ padding: '24px 20px', minHeight: '100vh', background: 'var(--sec-bg)' }}>
            <Head title={`Log #${log.id}`} />

            <SecurityNav />

            {flashMsg && (
                <div className="sec-alert sec-alert-success" style={{ marginBottom: '16px' }}>
                    <span>{flashMsg}</span>
                </div>
            )}

            <Link href={urls.index} className="sec-back-link">
                ← Back to logs
            </Link>

            <div className="sec-page-header">
                <h1 className="sec-page-title">Log entry #{log.id}</h1>
                <p className="sec-page-subtitle">{log.event_type}</p>
            </div>

            <Card>
                <CardContent>
                    <div className="sec-detail-grid">
                        <DetailItem label="Time">
                            <span className="sec-font-mono">{new Date(log.created_at).toLocaleString()}</span>
                        </DetailItem>
                        <DetailItem label="IP address">
                            <span className="sec-font-mono" style={{ fontWeight: 600 }}>{log.ip_address}</span>
                        </DetailItem>
                        <DetailItem label="Threat level">
                            <Badge variant={log.threat_level as any}>{levelLabels[log.threat_level] ?? log.threat_level}</Badge>
                        </DetailItem>
                        <DetailItem label="Blocked">{log.was_blocked ? 'Yes' : 'No'}</DetailItem>
                        <DetailItem label="Method"><span className="sec-font-mono">{log.method ?? '-'}</span></DetailItem>
                        <DetailItem label="Event type">{log.event_type}</DetailItem>
                        <DetailItem label="Rule">{log.rule_label ?? '-'}</DetailItem>
                        <DetailItem label="Action taken">{log.action_taken ?? '-'}</DetailItem>
                        {(log.device_id || log.local_ip) && (
                            <DetailItem label="Device">
                                <span className="sec-font-mono">{log.device_id ?? '-'} / {log.local_ip ?? '-'}</span>
                            </DetailItem>
                        )}
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Request path</CardTitle>
                </CardHeader>
                <CardContent>
                    <pre className="sec-code-block">{log.full_url ?? log.path ?? '-'}</pre>
                </CardContent>
            </Card>

            {log.evidence && (
                <Card>
                    <CardHeader>
                        <CardTitle>Evidence</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <pre className="sec-code-block">{log.evidence}</pre>
                    </CardContent>
                </Card>
            )}

            <Card>
                <CardHeader>
                    <CardTitle>Request context</CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="sec-detail-grid">
                        <DetailItem label="User agent">
                            <span style={{ fontSize: '12.5px' }}>{log.user_agent ?? '-'}</span>
                        </DetailItem>
                        <DetailItem label="Referer">
                            <span style={{ fontSize: '12.5px' }}>{log.referer ?? '-'}</span>
                        </DetailItem>
                    </div>
                    {log.meta && (
                        <div style={{ marginTop: '14px' }}>
                            <div className="sec-detail-label">Metadata</div>
                            <pre className="sec-code-block">{JSON.stringify(log.meta, null, 2)}</pre>
                        </div>
                    )}
                </CardContent>
            </Card>

            {block && (
                <Card>
                    <CardHeader>
                        <CardTitle>Related block</CardTitle>
                        <Link
                            href={urls.blockShow.replace('__ID__', String(block.id))}
                            className="sec-btn sec-btn-secondary sec-btn-sm"
                        >
                            View block
                        </Link>
                    </CardHeader>
                    <CardContent>
                        <div className="sec-detail-grid">
                            <DetailItem label="Status">{block.is_active ? 'Active' : 'Inactive'}</DetailItem>
                            <DetailItem label="Reason">{block.reason ?? '-'}</DetailItem>
                            <DetailItem label="Expires">
                                {block.expires_at ? new Date(block.expires_at).toLocaleString() : 'Permanent'}
                            </DetailItem>
                        </div>
                    </CardContent>
                </Card>
            )}

            {relatedLogs.length > 0 && (
                <Card flush>
                    <CardHeader>
                        <CardTitle>Other logs from this IP</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="sec-table-wrap">
                            <table className="sec-table">
                                <thead>
                                    <tr>
                                        <th>Time</th>
                                        <th>Event</th>
                                        <th>Level</th>
                                        <th>Path</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {relatedLogs.map((related) => (
                                        <tr key={related.id}>
                                            <td className="sec-font-mono" style={{ fontSize: '12px', whiteSpace: 'nowrap' }}>
                                                {new Date(related.created_at).toLocaleString()}
                                            </td>
                                            <td>{related.event_type}</td>
                                            <td>
                                                <Badge variant={related.threat_level as any}>
                                                    {levelLabels[related.threat_level] ?? related.threat_level}
                                                </Badge>
                                            </td>
                                            <td className="sec-font-mono" style={{ fontSize: '12px', maxWidth: '280px', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                                                {related.path}
                                            </td>
                                            <td style={{ textAlign: 'right' }}>
                                                <Link
                                                    href={urls.show.replace('__ID__', String(related.id))}
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
                <Button size="sm" variant="danger" onClick={destroy}>
                    Delete entry
                </Button>
            </div>
        </div>
    );
}
