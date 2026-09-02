import { Link, router, usePage } from '@inertiajs/react';
import { Search, ShoppingBag, User } from 'lucide-react';
import { useState, useEffect } from 'react';
import CartSync from '@/components/CartSync';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { Separator } from '@/components/ui/separator';
import { useTranslation } from '@/hooks/use-translation';

import { useGuestCart } from '@/hooks/useGuestCart';
import logo from '@/images/logo.png';

const locales = [
    { code: 'fr', label: 'FR' },
    { code: 'ar', label: 'AR' },
    { code: 'en', label: 'EN' },
] as const;

export const SiteHeader = () => {
    const { t, locale } = useTranslation();
    const { getItems, count: getGuestCount } = useGuestCart();
    // const [guestItems, setGuestItems] = useState(getItems());
    const { cart, auth } = usePage<{
        auth: {
            user: {
                id: number;
                first_name: string;
                last_name: string;
                email: string;
                role: string;
            } | null;
        };
        cart?: {
            items: Array<{
                id: number;
                name: string;
                seller: string;
                qty: number;
                price: number;
                line_total: number;
            }>;
            count: number;
            subtotal: number;
        };
    }>().props;

    const user = auth?.user;

    // const guestItems = getItems();
    const [guestItems, setGuestItems] = useState(getItems());
    const cartData = user
        ? (cart ?? { items: [], count: 0, subtotal: 0 })
        : {
              items: guestItems.map((item) => ({
                  id: item.product_id,
                  name: item.name,
                  seller: item.store_name,
                  qty: item.quantity,
                  price: Number(item.price),
                  line_total: Number(item.price) * item.quantity,
              })),
              count: getGuestCount(),
              subtotal: guestItems.reduce(
                  (sum, item) => sum + Number(item.price) * item.quantity,
                  0,
              ),
          };

    useEffect(() => {
        const syncCart = () => {
            setGuestItems(getItems());
        };
        window.addEventListener('guest-cart-updated', syncCart);

        return () => {
            window.removeEventListener('guest-cart-updated', syncCart);
        };
    });

    const switchLocale = (code: string) => {
        router.post(route('locale.switch', code), {}, { preserveScroll: true });
    };

    const navLinks = [
        { href: '/products', label: t('header.explore') },
        // { href: '/sellers', label: t('header.sellers') },
        { href: '/about', label: t('header.about') },
    ];

    return (
        <header className="border-b border-ink/10 bg-cream">
            <CartSync />
            <div className="container flex items-center justify-between gap-6 py-5">
                <Link
                    href="/"
                    className="flex shrink-0 items-center gap-2"
                    aria-label="Hirfaty"
                >
                    <img
                        src={logo}
                        alt="Hirfaty"
                        className="h-9 w-9 object-contain"
                    />
                    <span className="font-display text-xl leading-none text-ink">
                        Hirfaty<span className="text-terracotta">.</span>
                    </span>
                </Link>

                <nav className="hidden items-center gap-9 text-sm md:flex">
                    {navLinks.map((l) => (
                        <Link
                            key={l.href}
                            href={l.href}
                            className="text-ink/80 transition-colors hover:text-ink"
                        >
                            {l.label}
                        </Link>
                    ))}
                </nav>

                <div className="flex items-center gap-2 md:gap-4">
                    <button
                        aria-label={t('header.search')}
                        className="p-2 text-ink/70 transition-colors hover:text-ink"
                    >
                        <Search
                            className="h-[18px] w-[18px]"
                            strokeWidth={1.5}
                        />
                    </button>
                    <Popover>
                        <PopoverTrigger asChild>
                            <button
                                aria-label="Panier"
                                className="relative p-2 text-ink/70 transition-colors hover:text-ink"
                            >
                                <ShoppingBag
                                    className="h-[18px] w-[18px] cursor-pointer"
                                    strokeWidth={1.5}
                                />
                                {cartData.count > 0 && (
                                    <span className="absolute -top-0.5 -right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-terracotta px-1 text-[10px] font-medium text-cream">
                                        {cartData.count}
                                    </span>
                                )}
                            </button>
                        </PopoverTrigger>
                        <PopoverContent
                            align="end"
                            className="w-96 rounded-none border-ink/15 bg-paper p-0"
                        >
                            <div className="border-b border-ink/10 px-5 py-4">
                                <p className="eyebrow">Votre panier</p>
                            </div>
                            {cartData.items.length === 0 ? (
                                <div className="px-5 py-10 text-center text-sm text-ink-soft">
                                    Votre panier est vide.
                                </div>
                            ) : (
                                <>
                                    <ul className="max-h-80 divide-y divide-ink/10 overflow-auto">
                                        {cartData.items.map((item) => (
                                            <li
                                                key={item.id}
                                                className="flex gap-3 px-5 py-4"
                                            >
                                                <div className="h-14 w-14 shrink-0 border border-ink/10 bg-cream-deep/60" />
                                                <div className="min-w-0 flex-1">
                                                    <p className="truncate font-display text-sm leading-tight text-ink">
                                                        {item.name}
                                                    </p>
                                                    {/* Distinguishes two variants of one product. */}
                                                    {item.variant && (
                                                        <p className="mt-0.5 truncate text-xs text-ink">
                                                            {item.variant}
                                                        </p>
                                                    )}
                                                    <p className="mt-0.5 truncate text-xs text-ink-soft">
                                                        {item.seller}
                                                    </p>
                                                    <p className="mt-1 text-xs text-ink-soft">
                                                        Qté {item.qty}
                                                    </p>
                                                </div>
                                                <div className="flex flex-col items-end justify-between">
                                                    <span className="font-display text-sm text-ink">
                                                        {item.line_total}{' '}
                                                        <span className="text-[10px]">
                                                            MAD
                                                        </span>
                                                    </span>
                                                </div>
                                            </li>
                                        ))}
                                    </ul>
                                    <Separator className="bg-ink/10" />
                                    <div className="space-y-3 px-5 py-4">
                                        <div className="flex items-baseline justify-between">
                                            <span className="text-xs text-ink-soft">
                                                Sous-total
                                            </span>
                                            <span className="font-display text-lg text-ink">
                                                {cartData.subtotal}{' '}
                                                <span className="text-xs">
                                                    MAD
                                                </span>
                                            </span>
                                        </div>
                                        <Link
                                            href={route('cart.show')}
                                            className="block w-full bg-ink py-2.5 text-center font-display text-sm text-cream transition-colors hover:bg-ink/90"
                                        >
                                            Passer commande
                                        </Link>
                                    </div>
                                </>
                            )}
                        </PopoverContent>
                    </Popover>
                    <Popover>
                        <PopoverTrigger asChild>
                            <button
                                aria-label="Compte"
                                className="cursot-pointer hidden p-2 text-ink/70 transition-colors hover:text-ink sm:inline-flex"
                            >
                                <User
                                    className="h-[18px] w-[18px]"
                                    strokeWidth={1.5}
                                />
                            </button>
                        </PopoverTrigger>
                        <PopoverContent
                            align="end"
                            className="w-64 rounded-none border-ink/15 bg-paper p-0"
                        >
                            <div className="border-b border-ink/10 px-5 py-4">
                                <p className="eyebrow">Mon compte</p>
                                <p className="mt-1 text-xs text-ink-soft">
                                    {user
                                        ? `Bonjour, ${user.first_name}`
                                        : 'Bienvenue chez Hirfaty'}
                                </p>
                            </div>
                            <div className="p-2">
                                {user && user.role === 'buyer' ? (
                                    <>
                                        <Link
                                            href={route('dashboard')}
                                            className="block px-3 py-2 text-sm text-ink transition-colors hover:bg-cream-deep/60"
                                        >
                                            Tableau de bord
                                        </Link>
                                        <Link
                                            href={route(
                                                'buyer.account.orders.index',
                                            )}
                                            className="block px-3 py-2 text-sm text-ink transition-colors hover:bg-cream-deep/60"
                                        >
                                            Mes commandes
                                        </Link>
                                        <Link
                                            href="/wishlist"
                                            className="block px-3 py-2 text-sm text-ink transition-colors hover:bg-cream-deep/60"
                                        >
                                            Liste de souhaits
                                        </Link>
                                        <button
                                            onClick={() =>
                                                router.post(route('logout'))
                                            }
                                            className="block w-full px-3 py-2 text-left text-sm text-terracotta transition-colors hover:bg-cream-deep/60"
                                        >
                                            Déconnexion
                                        </button>
                                    </>
                                ) : user && user.role === 'seller' ? (
                                    <>
                                        <Link
                                            href={route('seller.dashboard')}
                                            className="block px-3 py-2 text-sm text-ink transition-colors hover:bg-cream-deep/60"
                                        >
                                            Dashboard
                                        </Link>
                                        <button
                                            onClick={() =>
                                                router.post(route('logout'))
                                            }
                                            className="block w-full px-3 py-2 text-left text-sm text-terracotta transition-colors hover:bg-cream-deep/60"
                                        >
                                            Déconnexion
                                        </button>
                                    </>
                                ) : user && user.role === 'super_admin' ? (
                                    <>
                                        <Link
                                            href={route('admin.dashboard')}
                                            className="block px-3 py-2 text-sm text-ink transition-colors hover:bg-cream-deep/60"
                                        >
                                            Hirfaty Dashboard
                                        </Link>
                                        <button
                                            onClick={() =>
                                                router.post(route('logout'))
                                            }
                                            className="block w-full px-3 py-2 text-left text-sm text-terracotta transition-colors hover:bg-cream-deep/60"
                                        >
                                            Déconnexion
                                        </button>
                                    </>
                                ) : (
                                    <>
                                        <Link
                                            href="/login"
                                            className="block px-3 py-2 text-sm text-ink transition-colors hover:bg-cream-deep/60"
                                        >
                                            Se connecter
                                        </Link>
                                        <Link
                                            href="/register"
                                            className="block px-3 py-2 text-sm text-ink transition-colors hover:bg-cream-deep/60"
                                        >
                                            Créer un compte
                                        </Link>
                                    </>
                                )}
                                <Separator className="my-2 bg-ink/10" />
                                <Link
                                    href="/seller/dashboard"
                                    className="block px-3 py-2 text-sm text-ink-soft transition-colors hover:bg-cream-deep/60 hover:text-ink"
                                >
                                    Espace vendeur
                                </Link>
                            </div>
                        </PopoverContent>
                    </Popover>

                    <span className="hidden h-5 w-px bg-ink/15 sm:block" />

                    {/* Locale switcher */}
                    <div className="hidden items-center text-[11px] font-medium tracking-[0.18em] text-ink/60 sm:flex">
                        {locales.map((l, i) => (
                            <button
                                key={l.code}
                                onClick={() => switchLocale(l.code)}
                                className={`px-1.5 py-1 transition-colors ${
                                    locale === l.code
                                        ? 'text-ink'
                                        : 'hover:text-ink/80'
                                }`}
                            >
                                {l.label}
                                {i < locales.length - 1 && (
                                    <span className="ml-2 text-ink/25">/</span>
                                )}
                            </button>
                        ))}
                    </div>

                    {/*
                     * Hidden below sm: the label is long enough in fr/ar to wrap
                     * onto three lines and squeeze the logo on a 375px screen.
                     * Same escape hatch the account button and locale switcher
                     * next to it already use.
                     */}
                    {/* <Link
                        href="/seller/onboarding"
                        className="ml-2 hidden items-center gap-2 border border-ink px-4 py-2 text-sm whitespace-nowrap text-ink transition-colors hover:bg-ink hover:text-cream sm:inline-flex"
                    >
                        {t('header.start_selling')}
                    </Link> */}
                </div>
            </div>
        </header>
    );
};

export default SiteHeader;
