import { usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import StoreStatusBar from '@/components/seller/StoreStatusBar';
import SellerSidebar from '@/components/SellerSidebar';
import { SidebarProvider, SidebarTrigger } from '@/components/ui/sidebar';
import type { PageProps } from '@/types';

export default function SellerLayout({ children }: { children: ReactNode }) {
    const { sellerStore } = usePage<PageProps>().props;

    return (
        <SidebarProvider>
            {/*
             * Same cool neutral surface as the admin panel rather than the
             * storefront's warm cream: these are data-heavy working screens,
             * and white cards need to lift off the page.
             */}
            <div className="flex min-h-screen w-full bg-background">
                <SellerSidebar />

                <div className="flex min-w-0 flex-1 flex-col">
                    <header className="sticky top-0 z-30 flex h-14 items-center gap-3 border-b border-border bg-card/80 px-4 backdrop-blur">
                        <SidebarTrigger />
                        <div className="h-4 w-px bg-border" />
                        <p className="text-sm tracking-wide text-muted-foreground uppercase">
                            Espace vendeur
                        </p>
                    </header>

                    {/*
                     * Sits directly under the header and sticks with it, so the
                     * reason a seller cannot publish stays on screen wherever
                     * they navigate. Renders nothing once the shop can sell.
                     */}
                    {sellerStore && <StoreStatusBar store={sellerStore} />}

                    <main className="flex-1">{children}</main>
                </div>
            </div>
        </SidebarProvider>
    );
}
