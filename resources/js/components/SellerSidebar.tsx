import { Link, router, usePage } from '@inertiajs/react';
import { LogOut, Package, PlusCircle, Store, Settings } from 'lucide-react';
import { ShoppingBag } from 'lucide-react';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupContent,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';

const dashboardItems = [
    { title: 'Tableau de bord', url: '/seller/dashboard', icon: Store },
];

const catalogueItems = [
    { title: 'Produits', url: '/seller/products', icon: Package },
    {
        title: 'Ajouter un produit',
        url: '/seller/products/create',
        icon: PlusCircle,
    },
];

const salesItems = [
    { title: 'Commandes', url: '/seller/orders', icon: ShoppingBag },
];

const settingsItems = [
    { title: 'Paramètres', url: '/seller/settings', icon: Settings },
];

export function SellerSidebar() {
    const { state } = useSidebar();
    const collapsed = state === 'collapsed';
    const { url } = usePage();

    // const isActive = (path: string) => url === path;
    const isActive = (path: string) => {
        // Escapes special characters and ensures the string ends
        // after the path or continues with a query parameter
        const regex = new RegExp(`^${path}$`);

        return regex.test(url.split('?')[0]); // split to ignore query strings
    };

    return (
        <Sidebar collapsible="icon" className="border-r border-border">
            <SidebarHeader className="border-b border-border">
                <div className="flex items-center gap-2 px-2 py-2">
                    <div className="flex h-8 w-8 items-center justify-center rounded-sm bg-primary/10">
                        <Store className="h-4 w-4 text-primary" />
                    </div>
                    {!collapsed && (
                        <div className="leading-tight">
                            <p className="font-display text-sm">Hirfaty</p>
                            <p className="text-[11px] tracking-wider text-muted-foreground uppercase">
                                Espace vendeur
                            </p>
                        </div>
                    )}
                </div>
            </SidebarHeader>

            <SidebarContent>
                <SidebarGroup className="p-0 px-1">
                    <SidebarGroupLabel>Catalogue</SidebarGroupLabel>
                    <SidebarGroupContent className="p-0">
                        <SidebarMenu className="rounded-none p-0">
                            {dashboardItems.map((item) => (
                                <SidebarMenuItem key={item.url}>
                                    {(() => {
                                        const active = isActive(item.url);

                                        return (
                                            <SidebarMenuButton
                                                asChild
                                                isActive={active}
                                                className={
                                                    active
                                                        ? 'rounded-none border-r-4 border-r-primary bg-primary/10 p-0 !py-6 text-primary hover:bg-primary/15 hover:text-primary'
                                                        : undefined
                                                }
                                            >
                                                <Link
                                                    className="rounded-none flex items-center gap-2"
                                                    href={item.url}
                                                >
                                                    <item.icon className="h-4 w-4" />

                                                    {!collapsed && (
                                                        <span>
                                                            {item.title}
                                                        </span>
                                                    )}
                                                </Link>
                                            </SidebarMenuButton>
                                        );
                                    })()}
                                </SidebarMenuItem>
                            ))}
                        </SidebarMenu>
                    </SidebarGroupContent>
                </SidebarGroup>
                <SidebarGroup>
                    <SidebarGroupLabel>Catalogue</SidebarGroupLabel>
                    <SidebarGroupContent>
                        <SidebarMenu>
                            {catalogueItems.map((item) => (
                                <SidebarMenuItem key={item.url}>
                                    {(() => {
                                        const active = isActive(item.url);

                                        return (
                                            <SidebarMenuButton
                                                asChild
                                                isActive={active}
                                                className={
                                                    active
                                                        ? 'rounded-none border-r-4 border-r-primary bg-primary/10 p-0 !py-6 text-primary hover:bg-primary/15 hover:text-primary'
                                                        : undefined
                                                }
                                            >
                                                <Link
                                                    className="rounded-none flex items-center gap-2"
                                                    href={item.url}
                                                >
                                                    <item.icon className="h-4 w-4" />

                                                    {!collapsed && (
                                                        <span>
                                                            {item.title}
                                                        </span>
                                                    )}
                                                </Link>
                                            </SidebarMenuButton>
                                        );
                                    })()}
                                </SidebarMenuItem>
                            ))}
                        </SidebarMenu>
                    </SidebarGroupContent>
                </SidebarGroup>

                <SidebarGroup>
                    <SidebarGroupLabel>Ventes</SidebarGroupLabel>
                    <SidebarGroupContent>
                        <SidebarMenu>
                            {salesItems.map((item) => (
                                <SidebarMenuItem key={item.url}>
                                    {(() => {
                                        const active = isActive(item.url);

                                        return (
                                            <SidebarMenuButton
                                                asChild
                                                isActive={active}
                                                className={
                                                    active
                                                        ? 'rounded-none border-r-4 border-r-primary bg-primary/10 p-0 !py-6 text-primary hover:bg-primary/15 hover:text-primary'
                                                        : undefined
                                                }
                                            >
                                                <Link
                                                    href={item.url}
                                                    className="rounded-none flex items-center gap-2"
                                                >
                                                    <item.icon className="h-4 w-4" />
                                                    {!collapsed && (
                                                        <span>
                                                            {item.title}
                                                        </span>
                                                    )}
                                                </Link>
                                            </SidebarMenuButton>
                                        );
                                    })()}
                                </SidebarMenuItem>
                            ))}
                        </SidebarMenu>
                    </SidebarGroupContent>
                </SidebarGroup>

                <SidebarGroup>
                    <SidebarGroupLabel>Compte</SidebarGroupLabel>
                    <SidebarGroupContent>
                        <SidebarMenu>
                            {settingsItems.map((item) => (
                                <SidebarMenuItem key={item.url}>
                                    {(() => {
                                        const active = isActive(item.url);

                                        return (
                                            <SidebarMenuButton
                                                asChild
                                                isActive={active}
                                                className={
                                                    active
                                                        ? 'rounded-none border-r-4 border-r-primary bg-primary/10 p-0 !py-6 text-primary hover:bg-primary/15 hover:text-primary'
                                                        : undefined
                                                }
                                            >
                                                <Link
                                                    href={item.url}
                                                    className="rounded-none flex items-center gap-2"
                                                >
                                                    <item.icon className="h-4 w-4" />
                                                    {!collapsed && (
                                                        <span>
                                                            {item.title}
                                                        </span>
                                                    )}
                                                </Link>
                                            </SidebarMenuButton>
                                        );
                                    })()}
                                </SidebarMenuItem>
                            ))}
                        </SidebarMenu>
                    </SidebarGroupContent>
                </SidebarGroup>
            </SidebarContent>

            <SidebarFooter className="border-t border-border">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            onClick={() => router.post(route('logout'))}
                            className="flex items-center gap-2 text-destructive hover:bg-destructive/10 hover:text-destructive"
                        >
                            <LogOut className="h-4 w-4" />
                            {!collapsed && <span>Déconnexion</span>}
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarFooter>
        </Sidebar>
    );
}

export default SellerSidebar;
