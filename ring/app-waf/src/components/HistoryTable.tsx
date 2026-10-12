import type { HistoryEntry } from "@/hooks/useWafConsole";
interface HistoryTableProps { history: HistoryEntry[]; onClear: () => void }
import { DownloadIcon, Trash2Icon } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Empty, EmptyDescription, EmptyTitle } from "@/components/ui/empty";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";

function statusVariant(status: number | undefined): "destructive" | "secondary" | "default" | "outline" {
  if (status === 403 || status === 429 || (status !== undefined && status >= 500)) {
    return "destructive";
  }
  if (status !== undefined && status >= 400) {
    return "secondary";
  }
  if (status !== undefined && status >= 200) {
    return "default";
  }
  return "outline";
}

function exportJson(history: HistoryEntry[]): void {
  const blob = new Blob([JSON.stringify(history, null, 2)], {
    type: 'application/json',
  });
  const url = URL.createObjectURL(blob);
  const anchor = document.createElement('a');
  anchor.href = url;
  anchor.download = `waf-history-${Date.now()}.json`;
  anchor.click();
  URL.revokeObjectURL(url);
}

export default function HistoryTable({ history, onClear }: HistoryTableProps) {
  return (
    <Card>
      <CardHeader>
        <CardTitle>Riwayat ({history.length})</CardTitle>
        <div className="flex gap-2">
          <Button
            type="button"
            variant="outline"
            size="xs"
            onClick={() => exportJson(history)}
            disabled={history.length === 0}
          >
            <DownloadIcon data-icon="inline-start" />
            Ekspor JSON
          </Button>
          <Button
            type="button"
            variant="outline"
            size="xs"
            onClick={onClear}
            disabled={history.length === 0}
          >
            <Trash2Icon data-icon="inline-start" />
            Bersihkan
          </Button>
        </div>
      </CardHeader>
      <CardContent>
        {history.length === 0 ? (
          <Empty>
            <EmptyTitle>Belum ada riwayat</EmptyTitle>
            <EmptyDescription>
              Belum ada riwayat.
            </EmptyDescription>
          </Empty>
        ) : (
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Waktu</TableHead>
                <TableHead>IP</TableHead>
                <TableHead>Path</TableHead>
                <TableHead>Status</TableHead>
                <TableHead>ms</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {history.map((row, index) => (
                <TableRow key={`${row.time}-${row.ip}-${index}`}>
                  <TableCell className="text-muted-foreground">
                    {row.time}
                  </TableCell>
                  <TableCell className="font-mono">{row.ip}</TableCell>
                  <TableCell className="font-mono text-muted-foreground">
                    {row.path}
                  </TableCell>
                  <TableCell>
                    <div className="flex items-center gap-1">
                      <Badge variant={statusVariant(row.status)}>
                        {row.status ?? "—"}
                      </Badge>
                      {row.blocked && (
                        <Badge variant="destructive">BLOCK</Badge>
                      )}
                    </div>
                  </TableCell>
                  <TableCell className="text-muted-foreground">
                    {row.ms ?? "—"}
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        )}
      </CardContent>
    </Card>
  );
}
