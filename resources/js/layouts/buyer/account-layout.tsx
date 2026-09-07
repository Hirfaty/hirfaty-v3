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
    | 'payment_methods'
    | 'professional_account';

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
 * The shell shared by the five account pages.
 *
 * The nav sits directly on the page rather than inside a panel: a single
 * hairline runs the height of the column, and the current section is marked
 * by a terracotta bar on its leading edge. Nothing is boxed, so the eye reads
 * one continuous page split into two columns instead of two stacked cards.
 *
 * Warm `souk-*` surfaces rather than the neutral ink/cream tokens: this sits
 * behind the storefront, next to /products and the home page, not with the
 * seller and admin tooling.
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
    // just to render its own sidebar.
    const user = usePage<{ auth: Auth }>().props.auth.user;

    const initials =
        `${user.first_name.charAt(0)}${user.last_name.charAt(0)}`.toUpperCase();

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
        {
            id: 'professional_account' as const,
            label: t('buyer.account.nav_professional'),
            icon: BadgeCheck,
            href: route('professional.show'),
        },
    ];

    return (
        <div className="min-h-screen bg-souk-paper">
            <Head title={title} />
            <SiteHeader />

            <div className="container py-12 lg:py-16">
                <div className="lg:grid lg:grid-cols-[15rem_1fr]">
                    {/* ── Rail ──────────────────────────────────────────── */}
                    <aside className="border-souk-brown/12 pb-8 lg:sticky lg:top-10 lg:self-start lg:border-e lg:pe-8 lg:pb-0">
                        <div className="flex items-center gap-3 lg:block">
                            <span
                                aria-hidden="true"
                                className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-terracotta/10 font-display text-sm text-terracotta lg:h-12 lg:w-12 lg:text-base"
                            >
                                {initials}
                            </span>
                            <div className="min-w-0 lg:mt-4">
                                <p className="truncate font-display text-lg leading-tight text-souk-title">
                                    {user.first_name} {user.last_name}
                                </p>
                                <p className="mt-0.5 truncate text-xs text-souk-brown-deep/55">
                                    {user.email ?? '—'}
                                </p>
                            </div>
                        </div>

                        {/*
                         * Scrolls sideways under lg, where the rail flattens
                         * into a strip above the content rather than wrapping
                         * onto two cramped rows.
                         */}
                        <nav
                            aria-label={t('buyer.account.title')}
                            className="-mx-1 mt-6 flex gap-1 overflow-x-auto lg:mx-0 lg:mt-9 lg:flex-col lg:gap-0.5 lg:overflow-visible"
                        >
                            {items.map((item) => {
                                const Icon = item.icon;
                                const current = active === item.id;

                                return (
                                    <Link
                                        key={item.id}
                                        href={item.href}
                                        aria-current={
                                            current ? 'page' : undefined
                                        }
                                        className={`group relative flex items-center gap-3 py-2.5 ps-4 pe-3 text-sm whitespace-nowrap transition-colors ${
                                            current
                                                ? 'text-souk-title'
                                                : 'text-souk-brown-deep/70 hover:text-souk-title'
                                        } `}
                                    >
                                        {/* The marker: a solid bar on the
                                            leading edge, hinted on hover. */}
                                        <span
                                            aria-hidden="true"
                                            className={`absolute inset-y-1 start-0 w-[2px] rounded-full transition-colors ${
                                                current
                                                    ? 'bg-terracotta'
                                                    : 'bg-transparent group-hover:bg-souk-brown/20'
                                            } `}
                                        />
                                        <Icon
                                            className={`h-4 w-4 shrink-0 ${current ? 'text-terracotta' : ''}`}
                                            strokeWidth={1.6}
                                        />
                                        {item.label}
                                    </Link>
                                );
                            })}
                        </nav>

                        <button
                            type="button"
                            onClick={() => router.post(route('logout'))}
                            className="mt-6 flex items-center gap-3 py-2.5 ps-4 pe-3 text-sm text-souk-brown-deep/50 transition-colors hover:text-terracotta lg:mt-9 lg:border-t lg:border-souk-brown/12 lg:pt-6"
                        >
                            <LogOut className="h-4 w-4" strokeWidth={1.6} />
                            {t('buyer.account.logout')}
                        </button>
                    </aside>

                    {/* ── Content ───────────────────────────────────────── */}
                    <main className="min-w-0 border-t border-souk-brown/12 pt-8 lg:border-0 lg:ps-12 lg:pt-0">
                        <header className="flex flex-wrap items-end justify-between gap-4 border-b border-souk-brown/12 pb-6">
                            <div>
                                <h1 className="font-display text-[clamp(1.7rem,3vw,2.3rem)] leading-tight tracking-[-0.02em] text-souk-title">
                                    {title}
                                </h1>
                                {description && (
                                    <p className="mt-2 max-w-xl text-sm leading-relaxed text-souk-brown-deep/65">
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
