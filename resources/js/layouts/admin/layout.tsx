import type { ReactNode } from 'react';
import AdminSidebar from '@/components/AdminSidebar';
import { SidebarProvider, SidebarTrigger } from '@/components/ui/sidebar';
import zellijPattern from '@/images/zellij-pattern.avif';

export default function AdminLayout({ children }: { children: ReactNode }) {
    return (
        <SidebarProvider>
            <div className="flex min-h-screen w-full bg-background">
                <AdminSidebar />

                <div className="relative isolate flex min-w-0 flex-1 flex-col bg-cream/30">
                    {/*
                     * Zellij corner motif. Anchored to the top of the content
                     * column and faded out diagonally, so it decorates the
                     * corner instead of tiling the whole page. `multiply` drops
                     * the artwork's white ground and leaves only the gold
                     * linework over the cream; the parent isolates the blend.
                     * Mirrored in RTL so it always hugs the sidebar-side corner.
                     */}
                    <div
                        aria-hidden="true"
                        className="pointer-events-none absolute start-0 top-0 z-0 h-80 w-80 opacity-90 mix-blend-multiply select-none md:h-[40rem] md:w-[40rem] rtl:-scale-x-100"
                        style={{
                            backgroundImage: `url(${zellijPattern})`,
                            backgroundSize: '660px 660px',
                            backgroundRepeat: 'no-repeat',
                            backgroundPosition: 'left top',
                            maskImage:
                                'radial-gradient(115% 115% at 0% 0%, #000 0%, #000 32%, transparent 78%)',
                            WebkitMaskImage:
                                'radial-gradient(115% 115% at 0% 0%, #000 0%, #000 32%, transparent 78%)',
                        }}
                    />

                    <header className="sticky top-0 z-20 flex h-14 items-center gap-3 border-b border-border bg-background/70 px-4 backdrop-blur">
                        <SidebarTrigger />
                        <div className="h-4 w-px bg-border" />
                        <p className="text-sm tracking-wide text-muted-foreground uppercase">
                            Espace administrateur
                        </p>
                    </header>
                    <main className="relative z-10 flex-1">{children}</main>
                </div>
            </div>
        </SidebarProvider>
    );
}
