import type { SurfaceFinding, SurfaceSummary } from "@/lib/surface";
interface SurfacePanelProps { body: string; contentType: string; baseUrl: string }
interface FindingCardProps { finding: SurfaceFinding }
import { useMemo } from "react";
import { analyzeSurface } from "@/lib/surface";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent } from "@/components/ui/card";
import { Empty, EmptyDescription, EmptyTitle } from "@/components/ui/empty";
import { InfoIcon } from "lucide-react";

function methodVariant(method: string): "default" | "secondary" | "destructive" | "outline" {
  switch (method) {
    case "GET":
      return "default";
    case "DELETE":
      return "destructive";
    case "POST":
      return "secondary";
    default:
      return "outline";
  }
}

const SUMMARY_ITEMS: { key: keyof SurfaceSummary; label: string }[] = [
  { key: "form", label: "Formulir" },
  { key: "param", label: "Parameter" },
  { key: "endpoint", label: "Endpoint" },
  { key: "upload", label: "Unggah" },
  { key: "error", label: "Error" },
];

function FindingCard({ finding }: FindingCardProps) {
  return (
    <Card size="sm">
      <CardContent>
        <div className="flex flex-wrap items-center gap-2">
          <Badge variant="secondary">{finding.kind}</Badge>
          <Badge variant={methodVariant(finding.method)}>
            {finding.method}
          </Badge>
          <span className="text-sm font-semibold">{finding.title}</span>
        </div>

        <div className="mt-2 font-mono text-xs break-all text-muted-foreground">
          {finding.target}
        </div>

        {finding.detail && (
          <p className="mt-1 text-xs text-muted-foreground">{finding.detail}</p>
        )}

        {finding.risks?.length > 0 && (
          <div className="mt-2 flex flex-wrap gap-1">
            {finding.risks.map((risk) => (
              <Badge key={risk} variant="destructive">
                {risk}
              </Badge>
            ))}
          </div>
        )}

        {finding.recommendation && (
          <Alert className="mt-2">
            <InfoIcon data-icon="inline-start" />
            <AlertTitle>Rekomendasi</AlertTitle>
            <AlertDescription>{finding.recommendation}</AlertDescription>
          </Alert>
        )}

        {finding.payloads?.length > 0 && (
          <div className="mt-2 flex flex-wrap gap-1">
            {finding.payloads.map((payload) => (
              <Badge key={payload} variant="outline" className="font-mono">
                {payload}
              </Badge>
            ))}
          </div>
        )}
      </CardContent>
    </Card>
  );
}

/**
 * Tab analisis permukaan serangan: menurunkan titik injeksi dari body respons
 * beserta metode HTTP dan payload yang direkomendasikan.
 */
export default function SurfacePanel({ body, contentType, baseUrl }: SurfacePanelProps) {
  const { findings, summary } = useMemo(
    () => analyzeSurface(body ?? "", { contentType: contentType ?? "", baseUrl: baseUrl ?? "" }),
    [body, contentType, baseUrl],
  );

  if (findings.length === 0) {
    return (
      <Empty>
        <EmptyTitle>Tidak ada titik serangan</EmptyTitle>
        <EmptyDescription>
          Tidak ada titik serangan yang terdeteksi pada body ini. Pastikan
          respons berisi HTML/JSON, atau kirim permintaan yang mengembalikan
          halaman target.
        </EmptyDescription>
      </Empty>
    );
  }

  return (
    <div className="h-full w-full overflow-auto p-4">
      <div className="mb-3 flex flex-wrap gap-2">
        {SUMMARY_ITEMS.filter((item) => summary[item.key] > 0).map((item) => (
          <Badge key={item.key} variant="secondary">
            {item.label}: {summary[item.key]}
          </Badge>
        ))}
      </div>

      <div className="flex flex-col gap-2">
        {findings.map((finding, index) => (
          <FindingCard key={`${finding.kind}-${finding.target}-${index}`} finding={finding} />
        ))}
      </div>
    </div>
  );
}
