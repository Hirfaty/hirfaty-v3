import type { ReactNode } from 'react';
import AdminSidebar from '@/components/AdminSidebar';
import { SidebarProvider, SidebarTrigger } from '@/components/ui/sidebar';
import { SiteHeader } from '@/components/SiteHeader';

export default function AdminLayout({ children }: { children: ReactNode }) {
    return (
        <SidebarProvider>
            <div className="flex min-h-screen w-full bg-background">
                <AdminSidebar />

                <div className="flex min-w-0 flex-1 flex-col">
                    <header className="sticky top-0 z-10 flex h-14 items-center gap-3 border-b border-border bg-background/80 px-4 backdrop-blur">
                        <SidebarTrigger />
                        <div className="h-4 w-px bg-border" />
                        <p className="text-sm tracking-wide text-muted-foreground uppercase">
                            Espace administrateur
                        </p>
                    </header>
                    <main className="flex-1">{children}</main>
                </div>
            </div>
        </SidebarProvider>
    );
}
