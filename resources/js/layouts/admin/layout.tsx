import { usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import NotificationBell from '@/components/admin/notification-bell';
import AdminSidebar from '@/components/AdminSidebar';
import { SidebarProvider, SidebarTrigger } from '@/components/ui/sidebar';
import type { PageProps } from '@/types';

export default function AdminLayout({ children }: { children: ReactNode }) {
    // The provider writes the admin's choice to the `sidebar_state` cookie
    // and HandleInertiaRequests shares it back as `sidebarOpen` — without
    // reading it here, every page load reopened a collapsed sidebar.
    const { sidebarOpen } = usePage<PageProps>().props;

    return (
        <SidebarProvider defaultOpen={sidebarOpen}>
            {/*
             * The admin panel uses a cool neutral surface rather than the
             * storefront's warm cream, so data-heavy screens read as a tool
             * and white cards lift off the page.
             */}
            <div className="flex min-h-screen w-full bg-background">
                <AdminSidebar />

                <div className="flex min-w-0 flex-1 flex-col">
                    <header className="sticky top-0 z-10 flex h-14 items-center gap-3 border-b border-border bg-card/80 px-4 backdrop-blur">
                        <SidebarTrigger title="Réduire ou afficher le menu (Ctrl+B)" />
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
