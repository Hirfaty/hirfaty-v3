import { Link, router, usePage } from '@inertiajs/react';
import { ChevronDown, Menu, Search, ShoppingCart, User } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { RefObject } from 'react';
import ReviewPrompt from '@/components/buyer/ReviewPrompt';
import CartSheet from '@/components/CartSheet';
import CartSync from '@/components/CartSync';
import CategoriesMegaMenu from '@/components/CategoriesMegaMenu';
import { ZellijStarIcon } from '@/components/home/CraftIcons';
import RegionMenu from '@/components/RegionMenu';
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
import { useCartSheet } from '@/contexts/CartSheetContext';
import { useCartSummary } from '@/hooks/use-cart-summary';
import { useCurrency } from '@/hooks/use-currency';
import { useTranslation } from '@/hooks/use-translation';
import logo from '@/images/logo.png';
import { switchLocale } from '@/lib/locale';

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
     * `solid` (the default) is the in-flow bar whose background matches the
     * page it sits on. `overlay` pins the bar over the top of the page and
     * starts it transparent, so a full-bleed hero runs underneath it, then
     * fades it to cream once the page scrolls.
     *
     * Opt-in rather than automatic: transparent white-on-nothing is only
     * legible over a dark hero, and most pages open on a light section.
     */
    variant?: 'solid' | 'overlay';
    /**
     * `overlay` only: the element the bar stays transparent for. The bar
     * turns cream only once this element's bottom edge has scrolled past the
     * top of the viewport — how the `/about` hero keeps the header
     * transparent over its pinned photograph for a whole window instead of
     * giving way after a bare 24px of scroll (the fallback when no anchor is
     * given).
     */
    overlayAnchor?: RefObject<HTMLElement | null>;
};

export const SiteHeader = ({
    variant = 'solid',
    overlayAnchor,
}: SiteHeaderProps) => {
    const { t, locale, isRTL } = useTranslation();
    const { currency, options: currencyOptions } = useCurrency();
    const { auth, navCategories } = usePage<{
        auth: {
            user: {
                id: number;
                // Nullable: the fast-signup buyer flow no longer asks for a
                // name at all — see RegistrationCodeService::complete — so a
                // freshly registered buyer has neither until they fill it in
                // from /account.
                first_name: string | null;
                last_name: string | null;
                email: string;
                role: string;
            } | null;
        };
        /**
         * Top-level crafts behind the "Categories" menu, each with its
         * subcategories nested — shared from HandleInertiaRequests so the
         * header has them on every route. `children` is an empty array for a
         * parent with no subcategories. Optional because a partial reload
         * may not carry it.
         */
        navCategories?: Array<{
            id: number;
            slug: string;
            translated_name: string;
            /** Public URL of the craft's picture; null shows a placeholder. */
            image_url: string | null;
            children: Array<{
                id: number;
                slug: string;
                translated_name: string;
            }>;
        }>;
    }>().props;

    const user = auth?.user;
    const currentUrl = usePage().url.split('?')[0];

    /** Server cart when signed in, localStorage cart for a guest. */
    const cartData = useCartSummary();

    /* The drawer itself is rendered below; this only summons it. */
    const { openCart } = useCartSheet();

    /*
     * The drawer is controlled rather than left to Radix: the header persists
     * across Inertia visits, so a link tapped inside the drawer navigates
     * without unmounting it — the panel has to be told to close.
     */
    const [menuOpen, setMenuOpen] = useState(false);

    /** The search sheet under the bar — see SearchPanel. */
    const [searchOpen, setSearchOpen] = useState(false);

    /** The categories mega-menu; controlled so it can open on hover. */
    const [categoriesOpen, setCategoriesOpen] = useState(false);
    const categoriesCloseTimer = useRef<number | null>(null);

    const openCategories = () => {
        if (categoriesCloseTimer.current !== null) {
            window.clearTimeout(categoriesCloseTimer.current);
            categoriesCloseTimer.current = null;
        }

        setCategoriesOpen(true);
    };

    const closeCategoriesSoon = () => {
        categoriesCloseTimer.current = window.setTimeout(
            () => setCategoriesOpen(false),
            150,
        );
    };

    const chooseLocale = (code: string) => {
        setMenuOpen(false);
        switchLocale(code);
    };

    const switchCurrency = (code: string) => {
        setMenuOpen(false);
        router.post(
            route('currency.switch', code),
            {},
            { preserveScroll: true },
        );
    };

    const navLinks = [
        {
            href: route('products.index', undefined, false),
            label: t('header.marketplace'),
        },
        {
            href: route('products.new', undefined, false),
            label: t('header.new_products'),
        },
        // { href: '/sellers', label: t('header.sellers') },
    ];

    const aboutLink = {
        href: route('about', undefined, false),
        label: t('header.about'),
    };

    /*
     * Relative, like every href here: they are compared against the page's
     * own URL (isActive), which is relative. "/fr", "/ar" … — the home page
     * of the current language.
     */
    const homeHref = route('home', undefined, false);

    const categories = navCategories ?? [];

    // Marks the current section so its nav link can carry the zellij star.
    const isActive = (href: string) =>
        currentUrl === href || currentUrl.startsWith(`${href}/`);

    /*
     * "Home" only makes sense as a way back — it's absent on the home page
     * itself and sits first, to the left of Marketplace, everywhere else.
     */
    const displayedNavLinks =
        currentUrl === homeHref
            ? navLinks
            : [{ href: homeHref, label: t('header.home') }, ...navLinks];

    /** Shared by every top-level bar link — Home/Marketplace/… and About. */
    const renderNavLink = (l: { href: string; label: string }) => (
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
             * Underline grows from the centre on hover; the current
             * section keeps it, with a zellij star set into the middle
             * of the rule.
             */}
            <span
                aria-hidden
                className={`pointer-events-none absolute -bottom-1 left-1/2 h-px -translate-x-1/2 ${light ? 'bg-white/60' : 'bg-terracotta/45'} transition-all duration-300 ${
                    isActive(l.href) ? 'w-full' : 'w-0 group-hover:w-full'
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
    );

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
    const categoryHref = (id: number) =>
        route('products.index', { category_id: id }, false);

    /*
     * Only meaningful in `overlay`: whether the page has moved far enough for
     * the bar to take its cream fill. With an anchor the bar holds out until
     * the anchored element's bottom clears the top of the viewport — the
     * `/about` hero stays transparent over its pinned photograph for its
     * whole window. Without one it falls back to 24px of scroll rather than
     * 0, so a one-pixel rubber-band scroll on a trackpad does not flicker the
     * background.
     */
    const [scrolled, setScrolled] = useState(false);

    useEffect(() => {
        if (variant !== 'overlay') {
            return;
        }

        const onScroll = () => {
            if (overlayAnchor?.current) {
                setScrolled(
                    overlayAnchor.current.getBoundingClientRect().bottom <= 0,
                );
            } else {
                setScrolled(window.scrollY > 24);
            }
        };

        // Run once up front: a reload part-way down the page (or a restored
        // scroll position) must not start the bar transparent over content.
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });

        return () => window.removeEventListener('scroll', onScroll);
    }, [variant, overlayAnchor]);

    /*
     * Transparent bar over a dark hero — flip the type and icons to white.
     * Suspended while the search sheet is open: the sheet is cream, and a
     * transparent bar sitting on top of it would put white type on cream.
     */
    const light = variant === 'overlay' && !scrolled && !searchOpen;

    return (
        <header
            className={`z-50 transition-colors duration-300 ${
                variant === 'overlay'
                    ? `fixed inset-x-0 top-0 ${
                          scrolled || searchOpen
                              ? 'bg-cream shadow-sm shadow-ink/5'
                              : 'bg-transparent'
                      }`
                    : 'relative bg-transparent'
            }`}
        >
            <CartSync />

            {/*
             * Renders nothing unless the signed-in buyer has a delivery they
             * haven't rated. Mounted here, next to CartSync, because it has to
             * catch them on whatever page they arrive at — and the header is
             * the only thing every one of those pages has in common.
             */}
            <ReviewPrompt />

            <div className="relative container flex items-center justify-between gap-6 py-5">
                <Link
                    href={homeHref}
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
                    {displayedNavLinks.map(renderNavLink)}

                    {/*
                     * Categories as a grouped mega-menu, the way large
                     * marketplaces lay them out: every top-level craft is a
                     * column heading (itself a link), with its subcategories
                     * listed underneath. A craft without subcategories is
                     * just its heading.
                     *
                     * Opens on hover for pointer users, but stays a Popover
                     * so a tap (touch screens have no hover) and the keyboard
                     * open it too. The close is delayed a beat so the pointer
                     * can cross the gap between the trigger and the panel.
                     */}
                    {categories.length > 0 && (
                        <Popover
                            open={categoriesOpen}
                            onOpenChange={setCategoriesOpen}
                        >
                            <PopoverTrigger asChild>
                                <button
                                    onMouseEnter={openCategories}
                                    onMouseLeave={closeCategoriesSoon}
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
                                collisionPadding={16}
                                onMouseEnter={openCategories}
                                onMouseLeave={closeCategoriesSoon}
                                className="w-[min(94vw,50rem)] overflow-hidden rounded-xl border-ink/15 bg-paper p-0"
                            >
                                <CategoriesMegaMenu
                                    categories={categories}
                                    activeCategoryId={activeCategoryId}
                                    categoryHref={categoryHref}
                                    onNavigate={() => setCategoriesOpen(false)}
                                />
                            </PopoverContent>
                        </Popover>
                    )}

                    {renderNavLink(aboutLink)}
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
                    <button
                        type="button"
                        onClick={openCart}
                        aria-label={t('header.cart_aria')}
                        className={`relative cursor-pointer ${iconButton(light)}`}
                    >
                        <ShoppingCart
                            className="h-[18px] w-[18px]"
                            strokeWidth={1.5}
                        />
                        {cartData.count > 0 && (
                            <span className="absolute -top-0.5 -right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-terracotta px-1 text-[10px] font-medium text-cream">
                                {cartData.count}
                            </span>
                        )}
                    </button>
                    {user ? (
                        <Popover>
                            <PopoverTrigger asChild>
                                <button
                                    aria-label="Compte"
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
                                        {t('header.account_greeting').replace(
                                            ':name',
                                            user.first_name ?? user.email,
                                        )}
                                    </p>
                                </div>
                                <div className="p-2">
                                    {/* Both buyer kinds, not just 'buyer': a
                                        professional_buyer used to fall
                                        through to the guest menu and be
                                        offered "sign in" while already signed
                                        in. */}
                                    {user.role === 'buyer' ||
                                    user.role === 'professional_buyer' ? (
                                        <>
                                            {/* The four pages of the account
                                                area, each a route of its
                                                own. */}
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
                                    ) : user.role === 'seller' ? (
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
                                    ) : user.role === 'super_admin' ? (
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
                                    ) : null}
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
                    ) : (
                        <Link
                            href={route('login')}
                            aria-label={t('header.account_login')}
                            title={t('header.account_login')}
                            className={iconButton(light)}
                        >
                            <User
                                className="h-[18px] w-[18px]"
                                strokeWidth={1.5}
                            />
                        </Link>
                    )}

                    <span
                        className={`hidden h-5 w-px md:block ${light ? 'bg-white/25' : 'bg-ink/15'}`}
                    />

                    {/*
                     * Language and currency together — see RegionMenu. They
                     * were two controls side by side, which was a lot of
                     * chrome for two preferences set once.
                     */}
                    <RegionMenu light={light} />

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
                                    {displayedNavLinks.map((l) => (
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

                                    {/* Every craft is a tappable link to its
                                        own listing; a parent with
                                        subcategories gets them listed
                                        indented underneath rather than
                                        behind a second disclosure — the
                                        drawer is already the disclosure, and
                                        nesting one inside it buys nothing but
                                        an extra tap. There is no hover on
                                        touch, so the desktop flyout becomes
                                        this flat, always-expanded list. */}
                                    {categories.length > 0 && (
                                        <>
                                            <p className="mt-5 px-3 pb-1 text-[11px] tracking-[0.18em] text-ink/50 uppercase">
                                                {t('header.categories')}
                                            </p>
                                            {categories.map((category) => (
                                                <div
                                                    key={category.id}
                                                    className="mb-1"
                                                >
                                                    <Link
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
                                                        className={`block rounded-md px-3 py-2.5 text-sm transition-colors ${
                                                            activeCategoryId ===
                                                            String(category.id)
                                                                ? 'bg-sand/40 text-ink'
                                                                : 'text-ink/75 hover:bg-sand/30 hover:text-ink'
                                                        }`}
                                                    >
                                                        {
                                                            category.translated_name
                                                        }
                                                    </Link>
                                                    {(category.children ?? [])
                                                        .length > 0 && (
                                                        <div className="ms-3 border-s border-ink/10 ps-2">
                                                            {(
                                                                category.children ??
                                                                []
                                                            ).map((child) => (
                                                                <Link
                                                                    key={
                                                                        child.id
                                                                    }
                                                                    href={categoryHref(
                                                                        child.id,
                                                                    )}
                                                                    onClick={() =>
                                                                        setMenuOpen(
                                                                            false,
                                                                        )
                                                                    }
                                                                    aria-current={
                                                                        activeCategoryId ===
                                                                        String(
                                                                            child.id,
                                                                        )
                                                                            ? 'page'
                                                                            : undefined
                                                                    }
                                                                    className={`block rounded-md px-3 py-2 text-sm transition-colors ${
                                                                        activeCategoryId ===
                                                                        String(
                                                                            child.id,
                                                                        )
                                                                            ? 'bg-sand/40 text-ink'
                                                                            : 'text-ink/60 hover:bg-sand/30 hover:text-ink'
                                                                    }`}
                                                                >
                                                                    {
                                                                        child.translated_name
                                                                    }
                                                                </Link>
                                                            ))}
                                                        </div>
                                                    )}
                                                </div>
                                            ))}
                                        </>
                                    )}

                                    <Link
                                        href={aboutLink.href}
                                        onClick={() => setMenuOpen(false)}
                                        aria-current={
                                            isActive(aboutLink.href)
                                                ? 'page'
                                                : undefined
                                        }
                                        className={`mt-5 rounded-md px-3 py-3 text-base transition-colors ${
                                            isActive(aboutLink.href)
                                                ? 'bg-sand/40 text-ink'
                                                : 'text-ink/80 hover:bg-sand/30 hover:text-ink'
                                        }`}
                                    >
                                        {aboutLink.label}
                                    </Link>
                                </nav>

                                {/* Pushed to the foot of the panel: language
                                    and currency are settings, not
                                    destinations. The desktop header folds
                                    these into one popover (RegionMenu); on a
                                    phone the panel has the room to lay both
                                    out flat, which is fewer taps. */}
                                <div className="mt-auto space-y-5 border-t border-ink/10 px-6 py-5">
                                    <div>
                                        <p className="text-[11px] tracking-[0.18em] text-ink/50 uppercase">
                                            {t('header.language')}
                                        </p>
                                        <div className="mt-3 flex items-center gap-2">
                                            {locales.map((l) => (
                                                <button
                                                    key={l.code}
                                                    onClick={() =>
                                                        chooseLocale(l.code)
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

                                    <div>
                                        <p className="text-[11px] tracking-[0.18em] text-ink/50 uppercase">
                                            {t('common.currency_label')}
                                        </p>
                                        <div className="mt-3 flex flex-wrap items-center gap-2">
                                            {currencyOptions.map((c) => (
                                                <button
                                                    key={c.code}
                                                    onClick={() =>
                                                        switchCurrency(c.code)
                                                    }
                                                    aria-current={
                                                        currency.code === c.code
                                                            ? 'true'
                                                            : undefined
                                                    }
                                                    className={`rounded-sm px-3 py-1.5 text-[11px] font-medium tracking-[0.18em] transition-colors ${
                                                        currency.code === c.code
                                                            ? 'bg-terracotta/12 text-terracotta ring-1 ring-terracotta/25'
                                                            : 'text-ink/60 hover:bg-sand/40 hover:text-ink'
                                                    }`}
                                                >
                                                    {c.code}
                                                </button>
                                            ))}
                                        </div>
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

            {/*
             * Mounted here rather than per page so that adding to the basket
             * from anywhere opens the one drawer — the bag button above and
             * the product page's Add-to-Cart both just flip its shared state.
             */}
            <CartSheet />
        </header>
    );
};

export default SiteHeader;
