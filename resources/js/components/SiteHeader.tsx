import { Link, router, usePage } from '@inertiajs/react';
import { Search, ShoppingBag, User } from 'lucide-react';
import { useState, useEffect } from 'react';
import CartSync from '@/components/CartSync';
import { ZellijStarIcon } from '@/components/home/CraftIcons';
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

/**
 * Icon buttons wear the horseshoe-arch silhouette used by the product cards
 * and the logo mark, so the whole header belongs to one craft vocabulary.
 * Rounded on the top, squared at the foot — an arch, not a pill.
 */
const ICON_BUTTON =
    'rounded-t-full rounded-b-md p-2 text-ink/70 transition-colors hover:bg-sand/40 hover:text-ink';

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
                /** "Rouge / 4m" — absent on a product without variants. */
                variant?: string | null;
                image?: string | null;
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
    const currentUrl = usePage().url.split('?')[0];

    // const guestItems = getItems();
    const [guestItems, setGuestItems] = useState(getItems());
    const cartData = user
        ? (cart ?? { items: [], count: 0, subtotal: 0 })
        : {
              items: guestItems.map((item) => ({
                  id: item.product_id,
                  name: item.name,
                  // Guest lines keep the label under a different key; surface
                  // it under the same name the server cart uses so the
                  // dropdown renders identically either side of logging in.
                  variant: item.variant_label ?? null,
                  image: item.image,
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

    // Marks the current section so its nav link can carry the zellij star.
    const isActive = (href: string) =>
        currentUrl === href || currentUrl.startsWith(`${href}/`);

    return (
        <header className="relative bg-cream">
            <CartSync />

            {/*
             * Faint zellij lattice across the bar. Tuned well below the value
             * the motif uses on a full section — at section strength it reads
             * as graph paper behind the nav text rather than as texture.
             */}
            <div
                aria-hidden
                className="pointer-events-none absolute inset-0 motif-zellij"
                style={
                    {
                        '--pattern-color': 'rgba(130, 71, 42, 0.045)',
                        '--pattern-size': '34px',
                    } as React.CSSProperties
                }
            />

            <div className="relative container flex items-center justify-between gap-6 py-5">
                <Link
                    href="/"
                    className="group flex shrink-0 items-center gap-2.5"
                    aria-label="Hirfaty"
                >
                    {/*
                     * Arch-topped frame around the mark — the same horseshoe
                     * silhouette the product cards use, so the header reads as
                     * part of the same craft language.
                     */}
                    <span className="flex h-10 w-10 items-center justify-center rounded-t-full rounded-b-lg bg-sand/35 ring-1 ring-terracotta/20 transition-colors group-hover:bg-sand/60">
                        <img
                            src={logo}
                            alt="Hirfaty"
                            className="h-6 w-6 object-contain"
                        />
                    </span>
                    <span className="font-display text-xl leading-none text-ink">
                        Hirfaty<span className="text-terracotta">.</span>
                    </span>
                </Link>

                <nav className="hidden items-center gap-9 text-sm md:flex">
                    {navLinks.map((l) => (
                        <Link
                            key={l.href}
                            href={l.href}
                            aria-current={isActive(l.href) ? 'page' : undefined}
                            className="group relative py-1 text-ink/80 transition-colors hover:text-ink"
                        >
                            {l.label}
                            {/*
                             * Underline grows from the centre on hover; the
                             * current section keeps it, with a zellij star
                             * set into the middle of the rule.
                             */}
                            <span
                                aria-hidden
                                className={`pointer-events-none absolute -bottom-1 left-1/2 h-px -translate-x-1/2 bg-terracotta/45 transition-all duration-300 ${
                                    isActive(l.href)
                                        ? 'w-full'
                                        : 'w-0 group-hover:w-full'
                                }`}
                            />
                            {isActive(l.href) && (
                                <ZellijStarIcon
                                    aria-hidden
                                    className="absolute -bottom-[7px] left-1/2 h-[7px] w-[7px] -translate-x-1/2 bg-cream text-terracotta"
                                />
                            )}
                        </Link>
                    ))}
                </nav>

                <div className="flex items-center gap-2 md:gap-4">
                    <button
                        aria-label={t('header.search')}
                        className={ICON_BUTTON}
                    >
                        <Search
                            className="h-[18px] w-[18px]"
                            strokeWidth={1.5}
                        />
                    </button>
                    <Popover>
                        <PopoverTrigger asChild>
                            <button
                                aria-label={t('header.cart_aria')}
                                className={`relative ${ICON_BUTTON}`}
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
                            className="w-96 overflow-hidden rounded-xl border-ink/15 bg-paper p-0"
                        >
                            <div className="border-b border-ink/10 px-5 py-4">
                                <p className="eyebrow">
                                    {t('header.cart_title')}
                                </p>
                            </div>
                            {cartData.items.length === 0 ? (
                                <div className="px-5 py-10 text-center text-sm text-ink-soft">
                                    {t('header.cart_empty')}
                                </div>
                            ) : (
                                <>
                                    <ul className="max-h-80 divide-y divide-ink/10 overflow-auto">
                                        {cartData.items.map((item) => (
                                            <li
                                                key={item.id}
                                                className="flex gap-3 px-5 py-4"
                                            >
                                                <div className="h-14 w-14 shrink-0 overflow-hidden rounded-xl border border-ink/10 bg-cream-deep/60">
                                                    {item.image ? (
                                                        <img
                                                            src={item.image}
                                                            alt={item.name}
                                                            className="h-full w-full object-cover"
                                                        />
                                                    ) : (
                                                        <div className="flex h-full w-full items-center justify-center">
                                                            <ShoppingBag
                                                                className="h-5 w-5 text-ink/20"
                                                                strokeWidth={
                                                                    1.5
                                                                }
                                                            />
                                                        </div>
                                                    )}
                                                </div>
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
                                                        {t(
                                                            'header.cart_qty',
                                                        ).replace(
                                                            ':count',
                                                            String(item.qty),
                                                        )}
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
                                                {t('header.cart_subtotal')}
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
                                            className="block w-full rounded-xl bg-ink py-2.5 text-center font-display text-sm text-cream transition-colors hover:bg-ink/90"
                                        >
                                            {t('header.cart_checkout')}
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
                                className={`hidden sm:inline-flex ${ICON_BUTTON}`}
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
                                {/* Both buyer kinds, not just 'buyer': a
                                    professional_buyer used to fall through to
                                    the guest menu and be offered "sign in"
                                    while already signed in. */}
                                {user &&
                                (user.role === 'buyer' ||
                                    user.role === 'professional_buyer') ? (
                                    <>
                                        {/* The four pages of the account area,
                                            each a route of its own. */}
                                        <Link
                                            href={route('buyer.dashboard')}
                                            className="block px-3 py-2 text-sm text-ink transition-colors hover:bg-cream-deep/60"
                                        >
                                            Mon compte
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
                                            href={route('buyer.wishlist')}
                                            className="block px-3 py-2 text-sm text-ink transition-colors hover:bg-cream-deep/60"
                                        >
                                            Mes coups de cœur
                                        </Link>
                                        <Link
                                            href={route(
                                                'buyer.payment_methods',
                                            )}
                                            className="block px-3 py-2 text-sm text-ink transition-colors hover:bg-cream-deep/60"
                                        >
                                            Paiement
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
                    <div className="hidden items-center gap-0.5 text-[11px] font-medium tracking-[0.18em] text-ink/60 sm:flex">
                        {locales.map((l) => (
                            <button
                                key={l.code}
                                onClick={() => switchLocale(l.code)}
                                aria-current={
                                    locale === l.code ? 'true' : undefined
                                }
                                /*
                                 * The chosen language sits on its own small
                                 * glazed tile. Replaces the "FR / AR / EN"
                                 * slashes, which read as a breadcrumb — and,
                                 * being a separator baked into each button's
                                 * label, were announced as part of it.
                                 */
                                className={`rounded-sm px-2 py-1 transition-colors ${
                                    locale === l.code
                                        ? 'bg-terracotta/12 text-terracotta ring-1 ring-terracotta/25'
                                        : 'hover:bg-sand/40 hover:text-ink'
                                }`}
                            >
                                {l.label}
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

            {/*
             * Zellij frise closing the header, in place of a flat 1px rule —
             * the tiled strip that edges a doorway. A hairline above it keeps
             * the separation crisp where the lozenges meet the page.
             */}
            <div aria-hidden className="relative">
                <div className="h-px w-full bg-ink/10" />
                <div
                    className="h-[7px] w-full craft-band opacity-70"
                    style={
                        {
                            '--band-color': 'rgba(193, 82, 42, 0.55)',
                            '--band-color-alt': 'rgba(217, 199, 167, 0.75)',
                            '--band-size': '14px',
                        } as React.CSSProperties
                    }
                />
            </div>
        </header>
    );
};

export default SiteHeader;
