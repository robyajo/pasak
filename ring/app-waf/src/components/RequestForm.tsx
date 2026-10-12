import type { ActiveVector, WafForm } from "@/hooks/useWafConsole";
import type { AttackVector } from "@/lib/vectors";
interface RequestFormProps { form: WafForm; onChange: (patch: Partial<WafForm>) => void; onRandomIp: () => void; onSend: () => void; onSendRandom: () => void; onBulk: (n: number) => void; onApplyVector: (v: AttackVector, group: string) => void; activeVector: ActiveVector | null; onClearVector: () => void; loading: boolean; progress: string }
import { DicesIcon, SendIcon } from "lucide-react";
import { attackVectorGroups, userAgentPresets } from "@/lib/vectors";
import VectorAdvice from "@/components/VectorAdvice";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import { Field, FieldDescription, FieldGroup, FieldLabel } from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Separator } from "@/components/ui/separator";
import { Spinner } from "@/components/ui/spinner";
import { Textarea } from "@/components/ui/textarea";
import { ToggleGroup, ToggleGroupItem } from "@/components/ui/toggle-group";
import { Tooltip, TooltipContent, TooltipTrigger } from "@/components/ui/tooltip";
import { cn } from "cn";

const METHODS = ["GET", "POST", "PUT", "PATCH", "DELETE", "HEAD", "OPTIONS"];

export default function RequestForm({
    form,
    onChange,
    onRandomIp,
    onSend,
    onSendRandom,
    onBulk,
    onApplyVector,
    activeVector,
    onClearVector,
    loading,
    progress,
}: RequestFormProps) {
    const busy = loading || progress !== "";
    return (
        <Card>
            <CardHeader>
                <CardTitle>Permintaan Uji</CardTitle>
                <CardDescription className="font-mono">POST /api/proxy</CardDescription>
            </CardHeader>
            <CardContent>
                <FieldGroup>
                    <Field>
                        <FieldLabel htmlFor="target">URL Target</FieldLabel>
                        <Input
                            id="target"
                            value={form.target}
                            onChange={(event) =>
                                onChange({ target: event.target.value })
                            }
                            placeholder="http://localhost:8000"
                        />
                    </Field>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field>
                            <FieldLabel htmlFor="path">Path / Query</FieldLabel>
                            <Input
                                id="path"
                                value={form.path}
                                onChange={(event) =>
                                    onChange({ path: event.target.value })
                                }
                                placeholder="/"
                            />
                        </Field>

                        <Field>
                            <FieldLabel htmlFor="method">Metode</FieldLabel>
                            <ToggleGroup
                                variant="outline"
                                value={[form.method]}
                                onValueChange={(values) => {
                                    const next = values[0];
                                    if (next) {
                                        onChange({ method: next });
                                    }
                                }}
                                className="flex-wrap"
                            >
                                {METHODS.map((method) => (
                                    <ToggleGroupItem key={method} value={method}>
                                        {method}
                                    </ToggleGroupItem>
                                ))}
                            </ToggleGroup>
                        </Field>
                    </div>

                    <Field>
                        <FieldLabel htmlFor="ip">IP Virtual (spoofed)</FieldLabel>
                        <div className="flex gap-2">
                            <Input
                                id="ip"
                                value={form.ip}
                                onChange={(event) =>
                                    onChange({ ip: event.target.value })
                                }
                                placeholder="Kosongkan = IP publik acak otomatis"
                            />
                            <Button
                                type="button"
                                variant="outline"
                                onClick={onRandomIp}
                                className="shrink-0"
                            >
                                <DicesIcon data-icon="inline-start" />
                                IP Acak
                            </Button>
                        </div>
                    </Field>

                    <Field>
                        <FieldLabel htmlFor="userAgent">User-Agent</FieldLabel>
                        <Input
                            id="userAgent"
                            value={form.userAgent}
                            onChange={(event) =>
                                onChange({ userAgent: event.target.value })
                            }
                            placeholder="Mozilla/5.0 ..."
                        />
                        <div className="flex flex-wrap gap-2">
                            {userAgentPresets.map((preset) => (
                                <Button
                                    key={preset.label}
                                    type="button"
                                    variant="outline"
                                    size="xs"
                                    onClick={() =>
                                        onChange({ userAgent: preset.value })
                                    }
                                >
                                    {preset.label}
                                </Button>
                            ))}
                        </div>
                    </Field>

                    <Field>
                        <FieldLabel htmlFor="payload">
                            Payload Body (POST/PUT/PATCH/DELETE)
                        </FieldLabel>
                        <Textarea
                            id="payload"
                            rows={3}
                            value={form.payload}
                            onChange={(event) =>
                                onChange({ payload: event.target.value })
                            }
                            placeholder='{"email":"a@b.com","password":"123456"}'
                        />
                    </Field>

                    <Field>
                        <FieldLabel htmlFor="contentType">
                            Content-Type (opsional)
                        </FieldLabel>
                        <Input
                            id="contentType"
                            value={form.contentType}
                            onChange={(event) =>
                                onChange({ contentType: event.target.value })
                            }
                            placeholder="Kosongkan = deteksi otomatis (JSON / form-urlencoded)"
                        />
                    </Field>

                    <Field>
                        <FieldLabel htmlFor="uploadEnabled">
                            <Checkbox
                                id="uploadEnabled"
                                checked={form.uploadEnabled}
                                onCheckedChange={(checked) =>
                                    onChange({
                                        uploadEnabled: checked === true,
                                    })
                                }
                            />
                            Unggah Berkas · multipart/form-data
                        </FieldLabel>
                        <FieldDescription>
                            Untuk uji Arbitrary File Upload / webshell. Aktif
                            saat metode POST / PUT / PATCH.
                        </FieldDescription>

                        {form.uploadEnabled && (
                            <div className="grid gap-4 sm:grid-cols-3">
                                <Field>
                                    <FieldLabel htmlFor="uploadField">
                                        Nama Field
                                    </FieldLabel>
                                    <Input
                                        id="uploadField"
                                        value={form.uploadField}
                                        onChange={(event) =>
                                            onChange({
                                                uploadField: event.target.value,
                                            })
                                        }
                                        placeholder="file"
                                    />
                                </Field>
                                <Field>
                                    <FieldLabel htmlFor="uploadFilename">
                                        Nama Berkas
                                    </FieldLabel>
                                    <Input
                                        id="uploadFilename"
                                        value={form.uploadFilename}
                                        onChange={(event) =>
                                            onChange({
                                                uploadFilename:
                                                    event.target.value,
                                            })
                                        }
                                        placeholder="shell.php"
                                    />
                                </Field>
                                <Field>
                                    <FieldLabel htmlFor="uploadType">
                                        Tipe Berkas
                                    </FieldLabel>
                                    <Input
                                        id="uploadType"
                                        value={form.uploadType}
                                        onChange={(event) =>
                                            onChange({
                                                uploadType: event.target.value,
                                            })
                                        }
                                        placeholder="application/x-php"
                                    />
                                </Field>
                                <div className="sm:col-span-3">
                                    <Field>
                                        <FieldLabel htmlFor="uploadContent">
                                            Isi Berkas
                                        </FieldLabel>
                                        <Textarea
                                            id="uploadContent"
                                            rows={3}
                                            value={form.uploadContent}
                                            onChange={(event) =>
                                                onChange({
                                                    uploadContent:
                                                        event.target.value,
                                                })
                                            }
                                            placeholder={
                                                '<?php system($_GET["cmd"]); ?>'
                                            }
                                        />
                                    </Field>
                                </div>
                            </div>
                        )}
                    </Field>
                </FieldGroup>

                <Separator className="my-5" />

                <div className="flex flex-col gap-4">
                    <Label>Vektor Serangan (klik untuk mengisi)</Label>
                    {attackVectorGroups.map(({ group, items }) => (
                        <div key={group}>
                            <p className="mb-2 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                {group}
                            </p>
                            <div className="flex flex-wrap gap-2">
                                {items.map((vector) => (
                                    <Button
                                        key={vector.label}
                                        type="button"
                                        variant={
                                            activeVector?.label ===
                                            vector.label
                                                ? "destructive"
                                                : "outline"
                                        }
                                        size="xs"
                                        onClick={() =>
                                            onApplyVector(vector, group)
                                        }
                                        className={cn(
                                            activeVector?.label ===
                                                vector.label && "border-current",
                                        )}
                                    >
                                        {vector.label}
                                    </Button>
                                ))}
                            </div>
                        </div>
                    ))}
                </div>

                <VectorAdvice vector={activeVector} onClear={onClearVector} />

                <div className="mt-6 flex flex-wrap items-center gap-3">
                    <Tooltip>
                        <TooltipTrigger
                            render={
                                <Button
                                    type="button"
                                    onClick={onSend}
                                    disabled={busy}
                                >
                                    {loading && (
                                        <Spinner data-icon="inline-start" />
                                    )}
                                    <SendIcon data-icon="inline-start" />
                                    Kirim
                                </Button>
                            }
                        />
                        <TooltipContent>Ctrl+Enter</TooltipContent>
                    </Tooltip>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={onSendRandom}
                        disabled={busy}
                    >
                        <DicesIcon data-icon="inline-start" />
                        Kirim + IP Acak Baru
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onBulk(5)}
                        disabled={busy}
                    >
                        Uji 5 IP Acak
                    </Button>
                    {progress !== "" && (
                        <span className="text-xs text-muted-foreground">
                            {progress}
                        </span>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}
