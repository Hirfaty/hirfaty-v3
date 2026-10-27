import { Link, router, usePage } from '@inertiajs/react';
import {
    ChevronDown,
    FileText,
    LayoutDashboard,
    LogOut,
    Mail,
    MapPin,
    Newspaper,
    Package,
    PanelLeftClose,
    PanelLeftOpen,
    Receipt,
    ShieldCheck,
    ShoppingBag,
    Store,
    Tag,
    Truck,
    Users,
} from 'lucide-react';
import { useState } from 'react';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
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
    SidebarRail,
    useSidebar,
} from '@/components/ui/sidebar';
import type { PageProps } from '@/types';

interface NavItem {
    title: string;
    /**
     * Relative path, e.g. "/admin/products" — deliberately not built with
     * route() at module scope. Ziggy's route() defaults to an ABSOLUTE URL
     * (http://host/...), while Inertia's usePage().url is always relative;
     * comparing the two would never match. route(name, undefined, false)
     * forces a relative path so active-state comparisons are apples-to-apples.
     */
    url: string;
    icon: typeof LayoutDashboard;
}

const overviewItems: NavItem[] = [
    {
        title: "Vue d'ensemble",
        url: route('admin.dashboard', undefined, false),
        icon: LayoutDashboard,
    },
];

const catalogItems: NavItem[] = [
    {
        title: 'Produits',
        url: route('admin.products.index', undefined, false),
        icon: Package,
    },
    {
        title: 'Catégories',
        url: route('admin.categories.index', undefined, false),
        icon: Tag,
    },
];

const contentItems: NavItem[] = [
    {
        title: 'Journal',
        url: route('admin.blogs.index', undefined, false),
        icon: Newspaper,
    },
];

const communityItems: NavItem[] = [
    {
        title: 'Ateliers',
        url: route('admin.stores.index', undefined, false),
        icon: Store,
    },
    {
        title: 'Utilisateurs',
        url: route('admin.users.index', undefined, false),
        icon: Users,
    },
    {
        title: 'Commandes',
        url: route('admin.orders.index', undefined, false),
        icon: ShoppingBag,
    },
    {
        title: 'Factures',
        url: route('admin.invoices.index', undefined, false),
        icon: Receipt,
    },
    {
        title: 'Brochure',
        url: route('admin.brochure-leads.index', undefined, false),
        icon: FileText,
    },
    {
        title: 'Newsletter',
        url: route('admin.newsletter-subscribers.index', undefined, false),
        icon: Mail,
    },
];

const settingsItems: NavItem[] = [
    {
        title: 'Villes',
        url: route('admin.cities.index', undefined, false),
        icon: MapPin,
    },
    {
        title: 'Tarifs de livraison',
        url: route('admin.delivery-rates.index', undefined, false),
        icon: Truck,
    },
];

interface NavSection {
    label: string;
    items: NavItem[];
}

/** The sections that fold; "Tableau" holds one link and always stays open. */
const sections: NavSection[] = [
    { label: 'Catalogue', items: catalogItems },
    { label: 'Contenu', items: contentItems },
    { label: 'Communauté', items: communityItems },
    { label: 'Configuration', items: settingsItems },
];

/**
 * Labels of the sections the admin folded. A per-browser convenience, so
 * localStorage rather than a cookie: it never needs to reach the server, and
 * without SSR the first render can read it directly — no flash of every
 * section opening and then folding again on each page.
 */
const FOLDED_KEY = 'hirfaty_admin_nav_folded';

function readFolded(): string[] {
    try {
        const parsed: unknown = JSON.parse(
            localStorage.getItem(FOLDED_KEY) ?? '[]',
        );

        return Array.isArray(parsed)
            ? parsed.filter((label) => typeof label === 'string')
            : [];
    } catch {
        return [];
    }
}

function writeFolded(labels: string[]) {
    try {
        localStorage.setItem(FOLDED_KEY, JSON.stringify(labels));
    } catch {
        // Private mode or blocked storage: folding still works for this page.
    }
}

/**
 * One group of nav links, sharing the active-state logic below. Given
 * `open`/`onOpenChange` its label becomes a toggle that folds the links away;
 * without them it is a plain, always-open group.
 */
function NavGroup({
    label,
    items,
    isActive,
    collapsed,
    open,
    onOpenChange,
}: {
    label: string;
    items: NavItem[];
    isActive: (path: string) => boolean;
    collapsed: boolean;
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
}) {
    const menu = (
        <SidebarGroupContent>
            <SidebarMenu>
                {items.map((item) => {
                    const active = isActive(item.url);

                    return (
                        <SidebarMenuItem key={item.url}>
                            <SidebarMenuButton
                                asChild
                                isActive={active}
                                // Shown only while collapsed to icons.
                                tooltip={item.title}
                                className="rounded-lg"
                            >
                                <Link
                                    href={item.url}
                                    className="flex items-center gap-2.5"
                                >
                                    <item.icon
                                        className={`h-4 w-4 ${active ? 'text-sidebar-primary' : ''}`}
                                    />
                                    {/* Kept for screen readers when collapsed. */}
                                    <span
                                        className={
                                            collapsed ? 'sr-only' : undefined
                                        }
                                    >
                                        {item.title}
                                    </span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    );
                })}
            </SidebarMenu>
        </SidebarGroupContent>
    );

    if (open === undefined || !onOpenChange) {
        return (
            <SidebarGroup>
                <SidebarGroupLabel>{label}</SidebarGroupLabel>
                {menu}
            </SidebarGroup>
        );
    }

    return (
        // Collapsed to icons the labels are hidden, so a folded section could
        // not be reopened — show every section's icons there instead.
        <Collapsible
            open={collapsed || open}
            onOpenChange={onOpenChange}
            className="group/collapsible"
        >
            <SidebarGroup>
                <SidebarGroupLabel
                    asChild
                    className="hover:bg-sidebar-accent hover:text-sidebar-accent-foreground"
                >
                    <CollapsibleTrigger className="w-full">
                        {label}
                        <ChevronDown className="ms-auto transition-transform group-data-[state=closed]/collapsible:-rotate-90" />
                    </CollapsibleTrigger>
                </SidebarGroupLabel>
                <CollapsibleContent>{menu}</CollapsibleContent>
            </SidebarGroup>
        </Collapsible>
    );
}

export function AdminSidebar() {
    const { state, isMobile, toggleSidebar } = useSidebar();
    const collapsed = state === 'collapsed';
    const { url, props } = usePage<PageProps>();
    const { auth } = props;

    // Exact match for the dashboard root, prefix match for everything else —
    // otherwise "Produits" would stay lit while viewing a single product.
    const dashboardUrl = route('admin.dashboard', undefined, false);
    const isActive = (path: string) => {
        const current = url.split('?')[0];

        return path === dashboardUrl
            ? current === path
            : current === path || current.startsWith(`${path}/`);
    };

    // The page remounts this on every visit, so the section holding the
    // current page reopens each time you land in it, even if folded before.
    const [folded, setFolded] = useState(() => {
        const current = sections.find((section) =>
            section.items.some((item) => isActive(item.url)),
        );

        return readFolded().filter((label) => label !== current?.label);
    });

    const setSectionOpen = (label: string, open: boolean) => {
        const next = open
            ? folded.filter((l) => l !== label)
            : [...folded.filter((l) => l !== label), label];

        setFolded(next);
        writeFolded(next);
    };

    const handleLogout = () => {
        router.post(route('logout'));
    };

    return (
        <Sidebar collapsible="icon" className="border-r border-sidebar-border">
            <SidebarHeader className="border-b border-sidebar-border">
                <div className="flex items-center gap-2.5 px-2 py-2">
                    <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-sidebar-primary/15">
                        <ShieldCheck className="h-4 w-4 text-sidebar-primary" />
                    </div>
                    {!collapsed && (
                        <div className="min-w-0 leading-tight">
                            <p className="truncate font-display text-sm text-sidebar-foreground">
                                Hirfaty
                            </p>
                            <p className="truncate text-[11px] tracking-wider text-sidebar-foreground/60 uppercase">
                                Administration
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
                {sections.map((section) => (
                    <NavGroup
                        key={section.label}
                        label={section.label}
                        items={section.items}
                        isActive={isActive}
                        collapsed={collapsed}
                        open={!folded.includes(section.label)}
                        onOpenChange={(open) =>
                            setSectionOpen(section.label, open)
                        }
                    />
                ))}
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
                    {/* On phones the sidebar is a drawer, closed by tapping outside. */}
                    {!isMobile && (
                        <SidebarMenuItem>
                            <SidebarMenuButton
                                onClick={toggleSidebar}
                                tooltip="Agrandir le menu"
                                className="rounded-lg text-sidebar-foreground/70"
                            >
                                {collapsed ? (
                                    <PanelLeftOpen className="h-4 w-4" />
                                ) : (
                                    <PanelLeftClose className="h-4 w-4" />
                                )}
                                <span
                                    className={
                                        collapsed ? 'sr-only' : undefined
                                    }
                                >
                                    {collapsed
                                        ? 'Agrandir le menu'
                                        : 'Réduire le menu'}
                                </span>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    )}
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            onClick={handleLogout}
                            tooltip="Déconnexion"
                            className="rounded-lg text-red-300 hover:bg-red-500/10 hover:text-red-300"
                        >
                            <LogOut className="h-4 w-4" />
                            <span className={collapsed ? 'sr-only' : undefined}>
                                Déconnexion
                            </span>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarFooter>

            {/* Clicking the sidebar's edge toggles it too. */}
            <SidebarRail />
        </Sidebar>
    );
}

export default AdminSidebar;
