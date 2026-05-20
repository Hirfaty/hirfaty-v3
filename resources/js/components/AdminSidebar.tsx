import { Link, router, usePage } from '@inertiajs/react';
import {
    ShieldCheck,
    Package,
    Users,
    Store,
    LogOut,
    LayoutDashboard,
    ChartBarIcon,
    MapPin,
} from 'lucide-react';
import { toast } from 'sonner';
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

const overviewItems = [
    { title: "Vue d'ensemble", url: '/admin/dashboard', icon: LayoutDashboard },
];

const moderationItems = [
    { title: 'Produits', url: '/admin/products', icon: Package },
    { title: 'Utilisateurs', url: '/admin/users', icon: Users },
    { title: 'Orders', url: '/admin/orders', icon: Package },
    { title: 'Create User', url: '/admin/users/create', icon: Users },
    { title: 'Catégories', url: '/admin/categories', icon: ChartBarIcon },
    { title: 'Villes', url: '/admin/cities', icon: MapPin },
];

export function AdminSidebar() {
    const { state } = useSidebar();
    const collapsed = state === 'collapsed';
    const { url } = usePage();

    const isActive = (path: string) => {
        const regex = new RegExp(`^${path}$`);

        return regex.test(url.split('?')[0]);
    };

    const handleLogout = () => {
        router.post(route('logout'));
    };

    return (
        <Sidebar collapsible="icon" className="border-r border-border">
            <SidebarHeader className="border-b border-border">
                <div className="flex items-center gap-2 px-2 py-2">
                    <div className="flex h-8 w-8 items-center justify-center rounded-sm bg-terracotta/10">
                        <ShieldCheck className="h-4 w-4 text-terracotta" />
                    </div>
                    {!collapsed && (
                        <div className="leading-tight">
                            <p className="font-display text-sm">Hirfaty</p>
                            <p className="text-[11px] tracking-wider text-muted-foreground uppercase">
                                Administration
                            </p>
                        </div>
                    )}
                </div>
            </SidebarHeader>

            <SidebarContent>
                <SidebarGroup>
                    <SidebarGroupLabel>Tableau</SidebarGroupLabel>
                    <SidebarGroupContent>
                        <SidebarMenu>
                            {overviewItems.map((item) => (
                                <SidebarMenuItem key={item.url}>
                                    {(() => {
                                        const active = isActive(item.url);

                                        return (
                                            <SidebarMenuButton
                                                asChild
                                                isActive={active}
                                                className={
                                                    active
                                                        ? 'rounded-none border-r-4 border-r-terracotta bg-terracotta/10 p-0 !py-6 text-terracotta hover:bg-terracotta/15 hover:text-terracotta'
                                                        : undefined
                                                }
                                            >
                                                <Link
                                                    href={item.url}
                                                    className="flex items-center gap-2"
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
                    <SidebarGroupLabel>Modération</SidebarGroupLabel>
                    <SidebarGroupContent>
                        <SidebarMenu>
                            {moderationItems.map((item) => (
                                <SidebarMenuItem key={item.url}>
                                    {(() => {
                                        const active = isActive(item.url);

                                        return (
                                            <SidebarMenuButton
                                                asChild
                                                isActive={active}
                                                className={
                                                    active
                                                        ? 'rounded-none border-r-4 border-r-terracotta bg-terracotta/10 p-0 !py-6 text-terracotta hover:bg-terracotta/15 hover:text-terracotta'
                                                        : undefined
                                                }
                                            >
                                                <Link
                                                    href={item.url}
                                                    className="flex items-center gap-2"
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
                            onClick={handleLogout}
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

export default AdminSidebar;
