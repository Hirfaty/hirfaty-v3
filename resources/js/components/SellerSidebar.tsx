import { Package, PlusCircle, Store } from 'lucide-react';
import {
    Sidebar,
    SidebarContent,
    SidebarGroup,
    SidebarGroupContent,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { Link, usePage } from '@inertiajs/react';
import { ShoppingBag } from 'lucide-react';

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

export function SellerSidebar() {
    const { state } = useSidebar();
    const collapsed = state === 'collapsed';
    const { url, component } = usePage();
    console.log('url: ', url);

    const isActive = (path: string) => url === path;

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
                <SidebarGroup>
                    <SidebarGroupLabel>Catalogue</SidebarGroupLabel>
                    <SidebarGroupContent>
                        <SidebarMenu>
                            {catalogueItems.map((item) => (
                                <SidebarMenuItem key={item.url}>
                                    <SidebarMenuButton
                                        asChild
                                        isActive={isActive(item.url)}
                                    >
                                        <Link
                                            className="flex items-center gap-2"
                                            href={item.url}
                                        >
                                            <item.icon className="h-4 w-4" />

                                            {!collapsed && (
                                                <span>{item.title}</span>
                                            )}
                                        </Link>
                                    </SidebarMenuButton>
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
                                    <SidebarMenuButton
                                        asChild
                                        isActive={true}
                                        // isActive={pathname.startsWith(item.url)}
                                    >
                                        <Link
                                            href={item.url}
                                            className="flex items-center gap-2"
                                        >
                                            <item.icon className="h-4 w-4" />
                                            {!collapsed && (
                                                <span>{item.title}</span>
                                            )}
                                        </Link>
                                    </SidebarMenuButton>
                                </SidebarMenuItem>
                            ))}
                        </SidebarMenu>
                    </SidebarGroupContent>
                </SidebarGroup>
            </SidebarContent>
        </Sidebar>
    );
}

export default SellerSidebar;
