import { router, usePage, Link } from '@inertiajs/react';
import {
    Package,
    Heart,
    MapPin,
    User as UserIcon,
    ShoppingBag,
    LogOut,
    ChevronRight,
    Plus,
    Trash2,
} from 'lucide-react';
import type { ReactNode } from 'react';
import SiteHeader from '@/components/site/SiteHeader';
import { Separator } from '@/components/ui/separator';

type Tab = 'overview' | 'orders' | 'wishlist' | 'addresses' | 'profile';

const tabs: {
    id: Tab;
    label: string;
    icon: typeof Package;
    url: string;
    routeName?: string;
}[] = [
    { id: 'overview', label: 'Aperçu', icon: UserIcon, url: '/overview' },
    {
        id: 'orders',
        label: 'Commandes',
        icon: Package,
        url: '/orders',
        routeName: 'buyer.account.orders.index',
    },
    {
        id: 'wishlist',
        label: 'Coups de cœur',
        icon: Heart,
        url: '/wishlist',
        routeName: 'wishlist.index',
    },
    { id: 'addresses', label: 'Adresses', icon: MapPin, url: '/addresses' },
    { id: 'profile', label: 'Profil', icon: UserIcon, url: '/profile' },
];

const handleLogout = () => {
    router.post(route('logout'));
};

export default function BuyerLayout({ children }: { children: ReactNode }) {
    const { url, props } = usePage();
    const { auth } = props as {
        auth: { user: { first_name: string; last_name: string } | null };
    };

    const greetingName = auth.user
        ? `${auth.user.first_name} ${auth.user.last_name}`
        : 'cher artisan';

    return (
        <div className="paper-grain min-h-screen bg-cream">
            <SiteHeader />

            <section className="container pt-12 pb-8">
                <p className="eyebrow mb-3">Mon espace</p>
                <h1 className="h-editorial text-5xl text-ink md:text-6xl">
                    Bonjour {greetingName},{' '}
                    <span className="text-terracotta italic">
                        bienvenue chez vous
                    </span>
                    .
                </h1>
                <p className="mt-3 max-w-xl text-ink-soft">
                    Suivez vos commandes, retrouvez vos pièces favorites et
                    gérez vos adresses de livraison.
                </p>
            </section>

            <section className="container grid grid-cols-12 gap-8 pb-20 md:gap-12">
                {/* Side nav */}
                <aside className="col-span-12 lg:col-span-3">
                    <div className="border border-ink/15 bg-paper lg:sticky lg:top-6">
                        <div className="flex items-center gap-3 border-b border-ink/10 px-5 py-5">
                            <div className="flex h-11 w-11 items-center justify-center rounded-full bg-terracotta/15 font-display text-lg text-terracotta">
                                {auth.user ? auth.user.first_name[0] : 'A'}
                            </div>
                            <div className="leading-tight">
                                <p className="font-display text-ink">
                                    {greetingName}
                                </p>
                            </div>
                        </div>
                        <nav className="p-2">
                            {tabs.map((t) => {
                                const active = t.routeName
                                    ? route().current(t.routeName)
                                    : url.startsWith(t.url);

                                return (
                                    <Link
                                        key={t.id}
                                        href={t.url}
                                        className={`flex w-full items-center gap-3 px-3 py-2.5 text-sm transition-colors ${
                                            active
                                                ? 'bg-ink text-cream'
                                                : 'text-ink hover:bg-cream-deep/60'
                                        }`}
                                    >
                                        <t.icon
                                            className="h-4 w-4"
                                            strokeWidth={1.5}
                                        />
                                        {t.label}
                                    </Link>
                                );
                            })}
                            <Separator className="my-2 bg-ink/10" />
                            <button
                                onClick={handleLogout}
                                className="flex w-full items-center gap-3 px-3 py-2.5 text-sm text-ink-soft transition-colors hover:text-terracotta"
                            >
                                <LogOut className="h-4 w-4" strokeWidth={1.5} />
                                Déconnexion
                            </button>
                        </nav>
                    </div>
                </aside>

                {/* Content */}
                <div className="col-span-12 space-y-10 lg:col-span-9">
                    <>{children}</>
                </div>
            </section>
        </div>
    );
}
