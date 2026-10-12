interface TabItem { id: string; label: string; badge?: number | string }
interface AppTabsProps { tabs: TabItem[]; active: string; onChange: (id: string) => void }
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Badge } from "@/components/ui/badge";

/**
 * Navigasi tab tingkat atas (Konsol Uji / Pengujian Serangan).
 */
export default function AppTabs({ tabs, active, onChange }: AppTabsProps) {
    return (
        <Tabs value={active} onValueChange={onChange} className="mb-6">
            <TabsList variant="line">
                {tabs.map((tab) => (
                    <TabsTrigger key={tab.id} value={tab.id}>
                        {tab.label}
                        {tab.badge != null && (
                            <Badge variant="secondary">{tab.badge}</Badge>
                        )}
                    </TabsTrigger>
                ))}
            </TabsList>
        </Tabs>
    );
}
