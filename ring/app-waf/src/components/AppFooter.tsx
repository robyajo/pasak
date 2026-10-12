import type { ReactNode } from "react";
interface AppFooterProps { children: ReactNode }
export default function AppFooter({ children }: AppFooterProps) {
  return <p className="mt-8 text-center text-xs text-muted-foreground">{children}</p>;
}
