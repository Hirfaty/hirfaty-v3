import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    BadgeCheck,
    CreditCard,
    Heart,
    LogOut,
    Package,
    UserRound,
} from 'lucide-react';
import type { ReactNode } from 'react';
import SiteFooter from '@/components/SiteFooter';
import SiteHeader from '@/components/SiteHeader';
import { useTranslation } from '@/hooks/use-translation';
import type { Auth } from '@/types';

export type AccountSection =
    | 'overview'
    | 'orders'
    | 'wishlist'
    | 'payment_methods';

interface Props {
    active: AccountSection;
    /** Page heading. Doubles as the document title. */
    title: string;
    description?: string;
    /** Right-hand side of the heading row — filters, a primary action, etc. */
    action?: ReactNode;
    children: ReactNode;
}

/**
 * The shell shared by the buyer account pages.
 *
 * The nav is a rail down the left on desktop, where a 14rem column has room
 * to spare next to the content. On a phone that same column would push the
 * content into a narrow gutter, so below `lg` it drops back to the original
 * horizontal, side-scrolling strip of tabs instead — two `<nav>`s with the
 * same items, one hidden at each breakpoint, rather than one nav whose axis
 * tries to flip with a handful of utility classes.
 *
 * Deliberately sparse on rules. The previous version drew a hairline down the
 * rail, another under the page heading, another above the content on small
 * screens and another above the sign-out button — four lines carving up a page
 * that is already visually grouped by whitespace. The rail's items now carry
 * their own state as a filled pill instead of a border, so no baseline is
 * needed there either.
 *
 * No avatar or name either. The header above already says who is signed in;
 * repeating it here spent the most prominent spot on the page telling the
 * reader something they knew. What they cannot see anywhere else is which
 * *kind* of account they hold, so that badge takes the slot instead.
 */
export default function BuyerAccountLayout({
    active,
    title,
    description,
    action,
    children,
}: Props) {
    const { t } = useTranslation();

    // Read from the globally shared auth prop, so a page needs no props at all
    // just to render its own chrome.
    const user = usePage<{ auth: Auth }>().props.auth.user;

    /*
     * `role`, not `professional_verified_at`. A buyer whose request is still
     * pending is a `buyer` until an admin promotes them — see
     * Admin\UserController::approveProfessional — so the role is the only
     * field that answers "what am I being billed as right now".
     */
    const isProfessional = user.role === 'professional_buyer';

    const items = [
        {
            id: 'overview' as const,
            label: t('buyer.account.nav_overview'),
            icon: UserRound,
            href: route('buyer.dashboard'),
        },
        {
            id: 'orders' as const,
            label: t('buyer.account.nav_orders'),
            icon: Package,
            href: route('buyer.account.orders.index'),
        },
        {
            id: 'wishlist' as const,
            label: t('buyer.account.nav_wishlist'),
            icon: Heart,
            href: route('buyer.wishlist'),
        },
        {
            id: 'payment_methods' as const,
            label: t('buyer.account.nav_payment'),
            icon: CreditCard,
            href: route('buyer.payment_methods'),
        },
        /*
         * No trade-account tab. The route and page still exist at
         * /professional-account — this only stops the account chrome
         * advertising it, on the same reasoning that took it out of the
         * onboarding tour: it asks a business question of someone who came
         * here to check an order.
         */
    ];

    return (
        <div className="min-h-screen bg-souk-paper">
            <Head title={title} />
            <SiteHeader />

            <div className="container py-10 lg:max-w-screen-xl lg:py-14">
                {/* ── Standing + sign out ───────────────────────────────── */}
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <span
                        className={`inline-flex items-center gap-2 rounded-full px-3.5 py-1.5 text-sm font-medium ${
                            isProfessional
                                ? 'bg-souk-green/10 text-souk-green'
                                : 'bg-souk-brown/10 text-souk-brown-deep/80'
                        }`}
                    >
                        <BadgeCheck className="h-4 w-4" strokeWidth={1.7} />
                        {isProfessional
                            ? t('buyer.account.badge_professional')
                            : t('buyer.account.badge_personal')}
                    </span>

                    <button
                        type="button"
                        onClick={() => router.post(route('logout'))}
                        className="inline-flex items-center gap-2 text-sm text-souk-brown-deep/55 transition-colors hover:text-souk-green"
                    >
                        <LogOut className="h-4 w-4" strokeWidth={1.6} />
                        {t('buyer.account.logout')}
                    </button>
                </div>

                <div className="lg:grid lg:grid-cols-[14rem_1fr] lg:items-start lg:gap-12">
                    {/*
                     * The rail. `lg` and up only — see the layout's docblock
                     * for why a narrower viewport keeps the horizontal strip
                     * below instead.
                     */}
                    <nav
                        aria-label={t('buyer.account.title')}
                        className="mt-7 hidden lg:flex lg:flex-col lg:gap-1"
                    >
                        {items.map((item) => {
                            const Icon = item.icon;
                            const current = active === item.id;

                            return (
                                <Link
                                    key={item.id}
                                    href={item.href}
                                    aria-current={current ? 'page' : undefined}
                                    className={`flex items-center gap-2.5 rounded-lg px-3.5 py-2.5 text-base transition-colors ${
                                        current
                                            ? 'bg-souk-green/10 text-souk-green'
                                            : 'text-souk-brown-deep/65 hover:bg-souk-brown/5 hover:text-souk-title'
                                    }`}
                                >
                                    <Icon
                                        className="h-[1.1rem] w-[1.1rem] shrink-0"
                                        strokeWidth={1.6}
                                    />
                                    {item.label}
                                </Link>
                            );
                        })}
                    </nav>

                    {/*
                     * Scrolls sideways rather than wrapping: four tabs fit on
                     * a phone at a glance, and a second row of them would
                     * read as a different kind of thing. Hidden from `lg` up,
                     * where the rail on the left takes over instead.
                     */}
                    <nav
                        aria-label={t('buyer.account.title')}
                        className="mt-7 -mb-px flex gap-1 overflow-x-auto border-b border-souk-brown/12 [scrollbar-width:none] sm:gap-2 lg:hidden [&::-webkit-scrollbar]:hidden"
                    >
                        {items.map((item) => {
                            const Icon = item.icon;
                            const current = active === item.id;

                            return (
                                <Link
                                    key={item.id}
                                    href={item.href}
                                    aria-current={current ? 'page' : undefined}
                                    className={`group relative flex shrink-0 items-center gap-2.5 px-3 pb-3.5 text-base whitespace-nowrap transition-colors sm:px-4 ${
                                        current
                                            ? 'text-souk-green'
                                            : 'text-souk-brown-deep/65 hover:text-souk-title'
                                    }`}
                                >
                                    <Icon
                                        className="h-[1.1rem] w-[1.1rem] shrink-0"
                                        strokeWidth={1.6}
                                    />
                                    {item.label}

                                    {/* Sits on the strip's own baseline, so the
                                        active tab reads as joined to the page
                                        below it rather than merely coloured. */}
                                    <span
                                        aria-hidden="true"
                                        className={`absolute inset-x-1 -bottom-px h-[2px] rounded-full transition-colors ${
                                            current
                                                ? 'bg-souk-green'
                                                : 'bg-transparent group-hover:bg-souk-brown/20'
                                        }`}
                                    />
                                </Link>
                            );
                        })}
                    </nav>

                    {/* ── Content ───────────────────────────────────────── */}
                    <main className="min-w-0 pt-9 lg:pt-7">
                        <header className="flex flex-wrap items-end justify-between gap-4">
                            <div>
                                <h1 className="font-display text-[clamp(1.9rem,3.2vw,2.6rem)] leading-tight tracking-[-0.02em] text-souk-title">
                                    {title}
                                </h1>
                                {description && (
                                    <p className="mt-2.5 max-w-2xl text-base leading-relaxed text-souk-brown-deep/70">
                                        {description}
                                    </p>
                                )}
                            </div>
                            {action}
                        </header>

                        <div className="pt-8">{children}</div>
                    </main>
                </div>
            </div>

            <SiteFooter />
        </div>
    );
}
