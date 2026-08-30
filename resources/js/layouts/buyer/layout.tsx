import { router } from '@inertiajs/react';
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
import { useState } from 'react';
import SiteHeader from '@/components/SiteHeader';
import { Separator } from '@/components/ui/separator';

type Tab = 'overview' | 'orders' | 'wishlist' | 'addresses' | 'profile';

const tabs: { id: Tab; label: string; icon: typeof Package; url: string }[] = [
    { id: 'overview', label: 'Aperçu', icon: UserIcon, url: '/overview' },
    { id: 'orders', label: 'Commandes', icon: Package, url: '/orders' },
    { id: 'wishlist', label: 'Coups de cœur', icon: Heart, url: '/wishlists' },
    { id: 'addresses', label: 'Adresses', icon: MapPin, url: '/addresses' },
    { id: 'profile', label: 'Profil', icon: UserIcon, url: '/profile' },
];

const handleLogout = () => {
    router.post(route('logout'));
};

export default function SellerLayout({ children }: { children: ReactNode }) {
    const [tab, setTab] = useState<Tab>('overview');

    return (
        <div className="min-h-screen bg-cream paper-grain">
            <SiteHeader />

            <section className="container pt-12 pb-8">
                <p className="mb-3 eyebrow">Mon espace</p>
                <h1 className="h-editorial text-5xl text-ink md:text-6xl">
                    Bonjour [Salma],{' '}
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
                                SB
                            </div>
                            <div className="leading-tight">
                                <p className="font-display text-ink">
                                    Salma Bennani
                                </p>
                                <p className="text-xs text-ink-soft">
                                    salma@exemple.ma
                                </p>
                            </div>
                        </div>
                        <nav className="p-2">
                            {tabs.map((t) => {
                                const active = tab === t.id;

                                return (
                                    <button
                                        key={t.id}
                                        onClick={() => setTab(t.id)}
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
                                    </button>
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
