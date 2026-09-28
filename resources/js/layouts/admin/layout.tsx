import type { ReactNode } from 'react';
import NotificationBell from '@/components/admin/notification-bell';
import AdminSidebar from '@/components/AdminSidebar';
import { SidebarProvider, SidebarTrigger } from '@/components/ui/sidebar';

export default function AdminLayout({ children }: { children: ReactNode }) {
    return (
        <SidebarProvider>
            {/*
             * The admin panel uses a cool neutral surface rather than the
             * storefront's warm cream, so data-heavy screens read as a tool
             * and white cards lift off the page.
             */}
            <div className="flex min-h-screen w-full bg-background">
                <AdminSidebar />

                <div className="flex min-w-0 flex-1 flex-col">
                    <header className="sticky top-0 z-10 flex h-14 items-center gap-3 border-b border-border bg-card/80 px-4 backdrop-blur">
                        <SidebarTrigger />
                        <div className="h-4 w-px bg-border" />
                        <p className="text-sm tracking-wide text-muted-foreground uppercase">
                            Espace administrateur
                        </p>
                        <div className="ms-auto">
                            <NotificationBell />
                        </div>
                    </header>
                    <main className="flex-1">{children}</main>
                </div>
            </div>
        </SidebarProvider>
    );
}
