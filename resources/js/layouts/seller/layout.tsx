import { ReactNode } from 'react';
import { SidebarProvider, SidebarTrigger } from '@/components/ui/sidebar';
import SellerSidebar from '@/components/SellerSidebar';

export default function SellerLayout({ children }: { children: ReactNode }) {
    return (
        <SidebarProvider>
            <div className="flex min-h-screen w-full bg-background">
                <SellerSidebar />
                <div className="flex min-w-0 flex-1 flex-col">
                    <header className="sticky top-0 z-10 flex h-14 items-center gap-3 border-b border-border bg-background/80 px-4 backdrop-blur">
                        <SidebarTrigger />
                        <div className="h-4 w-px bg-border" />
                        <p className="text-sm tracking-wide text-muted-foreground uppercase">
                            Tableau de bord
                        </p>
                    </header>
                    <main className="flex-1">{children}</main>
                </div>
            </div>
        </SidebarProvider>
    );
}
