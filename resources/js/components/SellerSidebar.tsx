import { Link, router, usePage } from '@inertiajs/react';
import {
    Coins,
    LayoutDashboard,
    LogOut,
    Package,
    Settings,
    ShoppingBag,
    Store,
} from 'lucide-react';
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
import type { PageProps } from '@/types';

interface NavItem {
    title: string;
    /**
     * Relative path, e.g. "/seller/products" — deliberately not an absolute
     * URL. Ziggy's route() defaults to absolute (http://host/...) while
     * Inertia's usePage().url is always relative, so comparing the two would
     * never match. route(name, undefined, false) forces a relative path.
     */
    url: string;
    icon: typeof LayoutDashboard;
}

const overviewItems: NavItem[] = [
    {
        title: 'Tableau de bord',
        url: route('seller.dashboard', undefined, false),
        icon: LayoutDashboard,
    },
];

/*
 * Read-only. Listings are filed by Hirfaty's own team, not by the artisan —
 * see Seller\ProductController — so there is no "add a product" entry here.
 */
const catalogItems: NavItem[] = [
    {
        title: 'Produits',
        url: route('seller.products.index', undefined, false),
        icon: Package,
    },
];

const salesItems: NavItem[] = [
    {
        title: 'Commandes',
        url: route('seller.orders.index', undefined, false),
        icon: ShoppingBag,
    },
    {
        title: 'Ventes',
        url: route('seller.sales.index', undefined, false),
        icon: Coins,
    },
];

const accountItems: NavItem[] = [
    {
        title: 'Mon atelier',
        url: route('seller.settings.index', undefined, false),
        icon: Settings,
    },
];

function NavGroup({
    label,
    items,
    isActive,
    collapsed,
}: {
    label: string;
    items: NavItem[];
    isActive: (path: string) => boolean;
    collapsed: boolean;
}) {
    if (items.length === 0) {
        return null;
    }

    return (
        <SidebarGroup>
            <SidebarGroupLabel>{label}</SidebarGroupLabel>
            <SidebarGroupContent>
                <SidebarMenu>
                    {items.map((item) => {
                        const active = isActive(item.url);

                        return (
                            <SidebarMenuItem key={item.url}>
                                <SidebarMenuButton
                                    asChild
                                    isActive={active}
                                    className="rounded-lg"
                                >
                                    <Link
                                        href={item.url}
                                        className="flex items-center gap-2.5"
                                    >
                                        <item.icon
                                            className={`h-4 w-4 ${active ? 'text-sidebar-primary' : ''}`}
                                        />
                                        {!collapsed && (
                                            <span>{item.title}</span>
                                        )}
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        );
                    })}
                </SidebarMenu>
            </SidebarGroupContent>
        </SidebarGroup>
    );
}

export function SellerSidebar() {
    const { state } = useSidebar();
    const collapsed = state === 'collapsed';
    const { url, props } = usePage<PageProps>();
    const { auth, sellerStore } = props;

    // Exact match for the dashboard root, prefix match elsewhere — otherwise
    // "Produits" would stay lit while viewing a single product.
    const dashboardUrl = route('seller.dashboard', undefined, false);
    const isActive = (path: string) => {
        const current = url.split('?')[0];

        return path === dashboardUrl
            ? current === path
            : current === path || current.startsWith(`${path}/`);
    };

    return (
        <Sidebar collapsible="icon" className="border-r border-sidebar-border">
            <SidebarHeader className="border-b border-sidebar-border">
                <div className="flex items-center gap-2.5 px-2 py-2">
                    <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-sidebar-primary/15">
                        <Store className="h-4 w-4 text-sidebar-primary" />
                    </div>
                    {!collapsed && (
                        <div className="min-w-0 leading-tight">
                            <p className="truncate font-display text-sm text-sidebar-foreground">
                                {sellerStore?.store_name ?? 'Hirfaty'}
                            </p>
                            <p className="truncate text-[11px] tracking-wider text-sidebar-foreground/60 uppercase">
                                Espace vendeur
                            </p>
                        </div>
                    )}
                </div>
            </SidebarHeader>

            <SidebarContent>
                <NavGroup
                    label="Tableau"
                    items={overviewItems}
                    isActive={isActive}
                    collapsed={collapsed}
                />
                <NavGroup
                    label="Catalogue"
                    items={catalogItems}
                    isActive={isActive}
                    collapsed={collapsed}
                />
                <NavGroup
                    label="Ventes"
                    items={salesItems}
                    isActive={isActive}
                    collapsed={collapsed}
                />
                <NavGroup
                    label="Compte"
                    items={accountItems}
                    isActive={isActive}
                    collapsed={collapsed}
                />
            </SidebarContent>

            <SidebarFooter className="border-t border-sidebar-border">
                {!collapsed && auth?.user && (
                    <div className="flex items-center gap-2.5 px-2 py-1.5">
                        <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-sidebar-accent text-xs font-semibold text-sidebar-foreground">
                            {auth.user.first_name?.[0]}
                            {auth.user.last_name?.[0]}
                        </div>
                        <div className="min-w-0 leading-tight">
                            <p className="truncate text-sm text-sidebar-foreground">
                                {auth.user.first_name} {auth.user.last_name}
                            </p>
                            <p className="truncate text-xs text-sidebar-foreground/60">
                                {auth.user.email}
                            </p>
                        </div>
                    </div>
                )}
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            onClick={() => router.post(route('logout'))}
                            className="rounded-lg text-red-300 hover:bg-red-500/10 hover:text-red-300"
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
