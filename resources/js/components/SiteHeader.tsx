import { Link, router, usePage } from '@inertiajs/react';
import { ChevronDown, Menu, Search, ShoppingBag, User } from 'lucide-react';
import { useState, useEffect } from 'react';
import CartSync from '@/components/CartSync';
import { ZellijStarIcon } from '@/components/home/CraftIcons';
import SearchPanel from '@/components/SearchPanel';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { Separator } from '@/components/ui/separator';
import {
    Sheet,
    SheetContent,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
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
 *
 * `light` is the transparent-over-a-dark-hero state; see `variant` below.
 */
const iconButton = (light: boolean) =>
    `rounded-t-full rounded-b-md p-2 transition-colors ${
        light
            ? 'text-white/80 hover:bg-white/15 hover:text-white'
            : 'text-ink/70 hover:bg-sand/40 hover:text-ink'
    }`;

type SiteHeaderProps = {
    /**
     * `solid` (the default) is the in-flow cream bar every page has always
     * had. `overlay` pins the bar over the top of the page and starts it
     * transparent, so a full-bleed hero runs underneath it, then fades it to
     * cream once the page scrolls.
     *
     * Opt-in rather than automatic: transparent white-on-nothing is only
     * legible over a dark hero, and most pages open on a light section.
     */
    variant?: 'solid' | 'overlay';
};

export const SiteHeader = ({ variant = 'solid' }: SiteHeaderProps) => {
    const { t, locale, isRTL } = useTranslation();
    const { getItems, count: getGuestCount } = useGuestCart();
    // const [guestItems, setGuestItems] = useState(getItems());
    const { cart, auth, navCategories } = usePage<{
        auth: {
            user: {
                id: number;
                first_name: string;
                last_name: string;
                email: string;
                role: string;
            } | null;
        };
        /**
         * Top-level crafts behind the "Categories" menu, shared from
         * HandleInertiaRequests so the header has them on every route.
         * Optional because a partial reload may not carry it.
         */
        navCategories?: Array<{
            id: number;
            slug: string;
            translated_name: string;
        }>;
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

    /*
     * The drawer is controlled rather than left to Radix: the header persists
     * across Inertia visits, so a link tapped inside the drawer navigates
     * without unmounting it — the panel has to be told to close.
     */
    const [menuOpen, setMenuOpen] = useState(false);

    /** The search sheet under the bar — see SearchPanel. */
    const [searchOpen, setSearchOpen] = useState(false);

    const switchLocale = (code: string) => {
        setMenuOpen(false);
        router.post(route('locale.switch', code), {}, { preserveScroll: true });
    };

    const navLinks = [
        { href: '/products', label: t('header.marketplace') },
        { href: '/new-arrivals', label: t('header.new_products') },
        { href: '/blog', label: t('header.journal') },
        // { href: '/sellers', label: t('header.sellers') },
        // { href: '/about', label: t('header.about') },
    ];

    const categories = navCategories ?? [];

    // Marks the current section so its nav link can carry the zellij star.
    const isActive = (href: string) =>
        currentUrl === href || currentUrl.startsWith(`${href}/`);

    /*
     * The categories menu lights up on the listing whenever a category is
     * actually selected. Read off the live query string rather than a prop:
     * SiteHeader is shared by every page and cannot depend on the listing's
     * own `filters`, and `usePage().url` already carries it.
     */
    const activeCategoryId = new URLSearchParams(
        usePage().url.split('?')[1] ?? '',
    ).get('category_id');

    /** Deep-link into the listing, pre-filtered to one craft. */
    const categoryHref = (id: number) => `/products?category_id=${id}`;

    /*
     * Only meaningful in `overlay`: whether the page has moved far enough for
     * the bar to take its cream fill. 24px rather than 0 so a one-pixel
     * rubber-band scroll on a trackpad does not flicker the background.
     */
    const [scrolled, setScrolled] = useState(false);

    useEffect(() => {
        if (variant !== 'overlay') {
            return;
        }

        const onScroll = () => setScrolled(window.scrollY > 24);

        // Run once up front: a reload part-way down the page (or a restored
        // scroll position) must not start the bar transparent over content.
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });

        return () => window.removeEventListener('scroll', onScroll);
    }, [variant]);

    /*
     * Transparent bar over a dark hero — flip the type and icons to white.
     * Suspended while the search sheet is open: the sheet is cream, and a
     * transparent bar sitting on top of it would put white type on cream.
     */
    const light = variant === 'overlay' && !scrolled && !searchOpen;

    return (
        <header
            // className={`z-50 transition-colors duration-300 ${
            //     variant === 'overlay'
            //         ? `fixed inset-x-0 top-0 ${
            //               scrolled || searchOpen
            //                   ? 'bg-cream shadow-sm shadow-ink/5'
            //                   : 'bg-transparent'
            //           }`
            //         : 'relative bg-cream'
            // }`}
            className="bg-transparent"
        >
            <CartSync />

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
                    <span
                        className={`flex h-10 w-10 items-center justify-center rounded-t-full rounded-b-lg ring-1 transition-colors ${
                            light
                                ? 'bg-white/15 ring-white/30 group-hover:bg-white/25'
                                : 'bg-sand/35 ring-terracotta/20 group-hover:bg-sand/60'
                        }`}
                    >
                        <img
                            src={logo}
                            alt="Hirfaty"
                            className="h-6 w-6 object-contain"
                        />
                    </span>
                    <span
                        className={`font-display text-xl leading-none transition-colors ${
                            light ? 'text-white' : 'text-ink'
                        }`}
                    >
                        Hirfaty
                        <span
                            className={
                                light ? 'text-souk-cream' : 'text-terracotta'
                            }
                        >
                            .
                        </span>
                    </span>
                </Link>

                <nav className="hidden items-center gap-9 text-sm md:flex">
                    {navLinks.map((l) => (
                        <Link
                            key={l.href}
                            href={l.href}
                            aria-current={isActive(l.href) ? 'page' : undefined}
                            className={`group relative py-1 transition-colors ${
                                light
                                    ? 'text-white/85 hover:text-white'
                                    : 'text-ink/80 hover:text-ink'
                            }`}
                        >
                            {l.label}
                            {/*
                             * Underline grows from the centre on hover; the
                             * current section keeps it, with a zellij star
                             * set into the middle of the rule.
                             */}
                            <span
                                aria-hidden
                                className={`pointer-events-none absolute -bottom-1 left-1/2 h-px -translate-x-1/2 ${light ? 'bg-white/60' : 'bg-terracotta/45'} transition-all duration-300 ${
                                    isActive(l.href)
                                        ? 'w-full'
                                        : 'w-0 group-hover:w-full'
                                }`}
                            />
                            {isActive(l.href) && (
                                <ZellijStarIcon
                                    aria-hidden
                                    className={`absolute -bottom-[7px] left-1/2 h-[7px] w-[7px] -translate-x-1/2 ${
                                        light
                                            ? 'bg-transparent text-white'
                                            : 'bg-cream text-terracotta'
                                    }`}
                                />
                            )}
                        </Link>
                    ))}

                    {/*
                     * Categories as a menu rather than as more top-level
                     * links: there are eight crafts, and spelling them out in
                     * the bar would crowd out everything else at every width.
                     *
                     * A Popover rather than a hover-only flyout so it works
                     * on a touch screen, where there is no hover, and can be
                     * driven from the keyboard.
                     */}
                    {categories.length > 0 && (
                        <Popover>
                            <PopoverTrigger asChild>
                                <button
                                    className={`group relative flex cursor-pointer items-center gap-1.5 py-1 transition-colors ${
                                        light
                                            ? 'text-white/85 hover:text-white'
                                            : 'text-ink/80 hover:text-ink'
                                    }`}
                                >
                                    {t('header.categories')}
                                    <ChevronDown
                                        className="h-3.5 w-3.5 transition-transform duration-300 group-data-[state=open]:rotate-180"
                                        strokeWidth={1.75}
                                    />
                                    <span
                                        aria-hidden
                                        className={`pointer-events-none absolute -bottom-1 left-1/2 h-px -translate-x-1/2 ${light ? 'bg-white/60' : 'bg-terracotta/45'} transition-all duration-300 ${
                                            activeCategoryId
                                                ? 'w-full'
                                                : 'w-0 group-hover:w-full'
                                        }`}
                                    />
                                </button>
                            </PopoverTrigger>
                            <PopoverContent
                                align="start"
                                className="w-72 rounded-xl border-ink/15 bg-paper p-2"
                            >
                                <ul className="grid gap-0.5">
                                    {categories.map((category) => {
                                        const current =
                                            activeCategoryId ===
                                            String(category.id);

                                        return (
                                            <li key={category.id}>
                                                <Link
                                                    href={categoryHref(
                                                        category.id,
                                                    )}
                                                    aria-current={
                                                        current
                                                            ? 'page'
                                                            : undefined
                                                    }
                                                    className={`flex items-center gap-2.5 rounded-md px-3 py-2 text-sm transition-colors ${
                                                        current
                                                            ? 'bg-sand/45 text-ink'
                                                            : 'text-ink/80 hover:bg-cream-deep/60 hover:text-ink'
                                                    }`}
                                                >
                                                    <ZellijStarIcon
                                                        aria-hidden
                                                        className="h-2.5 w-2.5 shrink-0 text-terracotta/70"
                                                    />
                                                    {category.translated_name}
                                                </Link>
                                            </li>
                                        );
                                    })}
                                </ul>

                                <Separator className="my-2 bg-ink/10" />

                                <Link
                                    href="/products"
                                    className="block rounded-md px-3 py-2 text-sm text-ink-soft transition-colors hover:bg-cream-deep/60 hover:text-ink"
                                >
                                    {t('header.all_categories')}
                                </Link>
                            </PopoverContent>
                        </Popover>
                    )}
                </nav>

                <div className="flex items-center gap-2 md:gap-4">
                    <button
                        aria-label={t('header.search')}
                        aria-expanded={searchOpen}
                        onClick={() => setSearchOpen((value) => !value)}
                        className={`cursor-pointer ${iconButton(light)}`}
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
                                className={`relative ${iconButton(light)}`}
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
                                aria-label={t('header.account_aria')}
                                className={iconButton(light)}
                            >
                                <User
                                    className="h-[18px] w-[18px]"
                                    strokeWidth={1.5}
                                />
                            </button>
                        </PopoverTrigger>
                        <PopoverContent
                            align="end"
                            className="w-64 rounded-none rounded-xl border-ink/15 bg-paper p-0"
                        >
                            <div className="border-b border-ink/10 px-5 py-4">
                                <p className="eyebrow">
                                    {t('header.account_heading')}
                                </p>
                                <p className="mt-1 text-xs text-ink-soft">
                                    {user
                                        ? t('header.account_greeting').replace(
                                              ':name',
                                              user.first_name,
                                          )
                                        : t('header.account_welcome')}
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
                                            {t('header.account_my_account')}
                                        </Link>
                                        <Link
                                            href={route(
                                                'buyer.account.orders.index',
                                            )}
                                            className="block px-3 py-2 text-sm text-ink transition-colors hover:bg-cream-deep/60"
                                        >
                                            {t('header.account_orders')}
                                        </Link>
                                        <Link
                                            href={route('buyer.wishlist')}
                                            className="block px-3 py-2 text-sm text-ink transition-colors hover:bg-cream-deep/60"
                                        >
                                            {t('header.account_wishlist')}
                                        </Link>
                                        <Link
                                            href={route(
                                                'buyer.payment_methods',
                                            )}
                                            className="block px-3 py-2 text-sm text-ink transition-colors hover:bg-cream-deep/60"
                                        >
                                            {t('header.account_payment')}
                                        </Link>
                                        <button
                                            onClick={() =>
                                                router.post(route('logout'))
                                            }
                                            className="block w-full px-3 py-2 text-left text-sm text-terracotta transition-colors hover:bg-cream-deep/60"
                                        >
                                            {t('header.account_logout')}
                                        </button>
                                    </>
                                ) : user && user.role === 'seller' ? (
                                    <>
                                        <Link
                                            href={route('seller.dashboard')}
                                            className="block px-3 py-2 text-sm text-ink transition-colors hover:bg-cream-deep/60"
                                        >
                                            {t('header.account_dashboard')}
                                        </Link>
                                        <button
                                            onClick={() =>
                                                router.post(route('logout'))
                                            }
                                            className="block w-full px-3 py-2 text-left text-sm text-terracotta transition-colors hover:bg-cream-deep/60"
                                        >
                                            {t('header.account_logout')}
                                        </button>
                                    </>
                                ) : user && user.role === 'super_admin' ? (
                                    <>
                                        <Link
                                            href={route('admin.dashboard')}
                                            className="block px-3 py-2 text-sm text-ink transition-colors hover:bg-cream-deep/60"
                                        >
                                            {t(
                                                'header.account_admin_dashboard',
                                            )}
                                        </Link>
                                        <button
                                            onClick={() =>
                                                router.post(route('logout'))
                                            }
                                            className="block w-full px-3 py-2 text-left text-sm text-terracotta transition-colors hover:bg-cream-deep/60"
                                        >
                                            {t('header.account_logout')}
                                        </button>
                                    </>
                                ) : (
                                    <>
                                        <Link
                                            href="/login"
                                            className="block px-3 py-2 text-sm text-ink transition-colors hover:bg-cream-deep/60"
                                        >
                                            {t('header.account_login')}
                                        </Link>
                                        <Link
                                            href="/register"
                                            className="block px-3 py-2 text-sm text-ink transition-colors hover:bg-cream-deep/60"
                                        >
                                            {t('header.account_register')}
                                        </Link>
                                    </>
                                )}
                                <Separator className="my-2 bg-ink/10" />
                                <Link
                                    href="/seller/dashboard"
                                    className="block px-3 py-2 text-sm text-ink-soft transition-colors hover:bg-cream-deep/60 hover:text-ink"
                                >
                                    {t('header.account_seller_space')}
                                </Link>
                            </div>
                        </PopoverContent>
                    </Popover>

                    <span
                        className={`hidden h-5 w-px md:block ${light ? 'bg-white/25' : 'bg-ink/15'}`}
                    />

                    {/* Locale switcher */}
                    <div
                        className={`hidden items-center gap-0.5 text-[11px] font-medium tracking-[0.18em] md:flex ${
                            light ? 'text-white/70' : 'text-ink/60'
                        }`}
                    >
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
                                        ? light
                                            ? 'bg-white/20 text-white ring-1 ring-white/35'
                                            : 'bg-terracotta/12 text-terracotta ring-1 ring-terracotta/25'
                                        : light
                                          ? 'hover:bg-white/15 hover:text-white'
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

                    {/*
                     * Small screens only — this is the sole way in to the nav
                     * links and the language switcher below `md`, where both
                     * are hidden from the bar itself.
                     */}
                    <Sheet open={menuOpen} onOpenChange={setMenuOpen}>
                        <SheetTrigger asChild>
                            <button
                                aria-label={t('header.menu')}
                                className={`md:hidden ${iconButton(light)}`}
                            >
                                <Menu
                                    className="h-[20px] w-[20px]"
                                    strokeWidth={1.5}
                                />
                            </button>
                        </SheetTrigger>

                        {/*
                         * Opens from whichever edge the bar's icons sit on, so
                         * the panel appears under the thumb that summoned it —
                         * the whole header mirrors under `dir="rtl"`.
                         */}
                        <SheetContent
                            side={isRTL ? 'left' : 'right'}
                            className="w-72 border-none bg-cream p-0"
                        >
                            <div className="flex h-full flex-col">
                                {/*
                                 * `pr-10` is physical, matching the close
                                 * button SheetContent pins at `top-4 right-4`.
                                 * Under `dir="rtl"` the title is right-aligned
                                 * and would otherwise run underneath it.
                                 */}
                                <div className="border-b border-ink/10 px-6 pt-6 pr-10 pb-5">
                                    <SheetTitle className="font-display text-xl leading-none text-ink">
                                        Hirfaty
                                        <span className="text-terracotta">
                                            .
                                        </span>
                                    </SheetTitle>
                                </div>

                                {/* Scrolls: with the crafts listed out, the
                                    panel is taller than a phone. */}
                                <nav className="flex flex-1 flex-col overflow-y-auto px-3 py-4">
                                    {navLinks.map((l) => (
                                        <Link
                                            key={l.href}
                                            href={l.href}
                                            onClick={() => setMenuOpen(false)}
                                            aria-current={
                                                isActive(l.href)
                                                    ? 'page'
                                                    : undefined
                                            }
                                            className={`rounded-md px-3 py-3 text-base transition-colors ${
                                                isActive(l.href)
                                                    ? 'bg-sand/40 text-ink'
                                                    : 'text-ink/80 hover:bg-sand/30 hover:text-ink'
                                            }`}
                                        >
                                            {l.label}
                                        </Link>
                                    ))}

                                    {/* Listed flat rather than behind a second
                                        disclosure: the drawer is already the
                                        disclosure, and nesting one inside it
                                        buys nothing but an extra tap. */}
                                    {categories.length > 0 && (
                                        <>
                                            <p className="mt-5 px-3 pb-1 text-[11px] tracking-[0.18em] text-ink/50 uppercase">
                                                {t('header.categories')}
                                            </p>
                                            {categories.map((category) => (
                                                <Link
                                                    key={category.id}
                                                    href={categoryHref(
                                                        category.id,
                                                    )}
                                                    onClick={() =>
                                                        setMenuOpen(false)
                                                    }
                                                    aria-current={
                                                        activeCategoryId ===
                                                        String(category.id)
                                                            ? 'page'
                                                            : undefined
                                                    }
                                                    className={`rounded-md px-3 py-2.5 text-sm transition-colors ${
                                                        activeCategoryId ===
                                                        String(category.id)
                                                            ? 'bg-sand/40 text-ink'
                                                            : 'text-ink/75 hover:bg-sand/30 hover:text-ink'
                                                    }`}
                                                >
                                                    {category.translated_name}
                                                </Link>
                                            ))}
                                        </>
                                    )}
                                </nav>

                                {/* Pushed to the foot of the panel: language is
                                    a setting, not a destination. */}
                                <div className="mt-auto border-t border-ink/10 px-6 py-5">
                                    <p className="text-[11px] tracking-[0.18em] text-ink/50 uppercase">
                                        {t('header.language')}
                                    </p>
                                    <div className="mt-3 flex items-center gap-2">
                                        {locales.map((l) => (
                                            <button
                                                key={l.code}
                                                onClick={() =>
                                                    switchLocale(l.code)
                                                }
                                                aria-current={
                                                    locale === l.code
                                                        ? 'true'
                                                        : undefined
                                                }
                                                className={`rounded-sm px-3 py-1.5 text-[11px] font-medium tracking-[0.18em] transition-colors ${
                                                    locale === l.code
                                                        ? 'bg-terracotta/12 text-terracotta ring-1 ring-terracotta/25'
                                                        : 'text-ink/60 hover:bg-sand/40 hover:text-ink'
                                                }`}
                                            >
                                                {l.label}
                                            </button>
                                        ))}
                                    </div>
                                </div>
                            </div>
                        </SheetContent>
                    </Sheet>
                </div>
            </div>

            {/*
             * Outside the container so the sheet spans the full width of the
             * bar, and a sibling of it so it hangs from the header's own box
             * (`top-full`) on both the in-flow and the pinned variant.
             *
             * Mounted only while open, rather than kept mounted and hidden, so
             * the typed term and its results are dropped on close.
             */}
            {searchOpen && <SearchPanel onClose={() => setSearchOpen(false)} />}
        </header>
    );
};

export default SiteHeader;
