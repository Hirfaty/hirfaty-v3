import { Link, router } from '@inertiajs/react';
import { ArrowRight, ShoppingBag } from 'lucide-react';
import { useEffect } from 'react';
import { Separator } from '@/components/ui/separator';
import { Sheet, SheetContent, SheetTitle } from '@/components/ui/sheet';
import { useCartSheet } from '@/contexts/CartSheetContext';
import { useCartSummary } from '@/hooks/use-cart-summary';
import { useCurrency } from '@/hooks/use-currency';
import { useTranslation } from '@/hooks/use-translation';

/**
 * The cart drawer.
 *
 * Opened either by the header's bag button or, more often, by adding something
 * to the basket — which is why the open state lives in `CartSheetContext`
 * rather than here. It replaces the popover the bag used to drop: one basket,
 * one panel, whoever asked for it.
 *
 * Mounted once, inside `SiteHeader`. The header is on every shopper-facing
 * page and is already inside Inertia's page context, which the summary needs.
 */
export default function CartSheet() {
    const { t, isRTL } = useTranslation();
    const { open, setOpen } = useCartSheet();
    const cart = useCartSummary();

    /*
     * Close on any Inertia visit. SiteHeader — and so this drawer — survives
     * navigation, which would otherwise leave the panel hanging over the page
     * it just sent the shopper to. Done here rather than in each link's
     * onClick: unmounting the panel from inside the click that started the
     * visit swallowed the visit itself.
     */
    useEffect(() => router.on('start', () => setOpen(false)), [setOpen]);

    // Server figures are MAD; this renders them in whatever the visitor is
    // browsing in, currency label included.
    const { money } = useCurrency();

    return (
        <Sheet open={open} onOpenChange={setOpen}>
            {/*
             * Opens from whichever edge the bar's icons sit on — the whole
             * header mirrors under `dir="rtl"`. Full width on a phone, where a
             * three-quarter panel leaves a useless sliver of page behind it.
             */}
            <SheetContent
                side={isRTL ? 'left' : 'right'}
                className="w-full gap-0 border-none bg-paper p-0 sm:max-w-md"
            >
                <div className="flex h-full flex-col">
                    {/*
                     * `pr-10` is physical, matching the close button
                     * SheetContent pins at `top-4 right-4`. Under `dir="rtl"`
                     * the title is right-aligned and would otherwise run
                     * underneath it.
                     */}
                    <div className="border-b border-ink/10 px-6 pt-6 pr-10 pb-5">
                        <SheetTitle className="font-display text-xl leading-none text-ink">
                            {t('header.cart_title')}
                        </SheetTitle>
                        {cart.count > 0 && (
                            <p className="mt-1.5 text-xs text-ink-soft">
                                {t('header.cart_count').replace(
                                    ':count',
                                    String(cart.count),
                                )}
                            </p>
                        )}
                    </div>

                    {cart.items.length === 0 ? (
                        <div className="flex flex-1 flex-col items-center justify-center px-6 text-center">
                            <ShoppingBag
                                className="h-8 w-8 text-ink/20"
                                strokeWidth={1.4}
                            />
                            <p className="mt-4 text-sm text-ink-soft">
                                {t('header.cart_empty')}
                            </p>
                            <Link
                                href="/products"
                                className="mt-5 rounded-xl border border-ink/20 px-5 py-2.5 text-sm text-ink transition-colors hover:border-ink hover:bg-sand/30"
                            >
                                {t('header.marketplace')}
                            </Link>
                        </div>
                    ) : (
                        <>
                            <ul className="flex-1 divide-y divide-ink/10 overflow-y-auto">
                                {cart.items.map((item) => (
                                    <li
                                        key={item.id}
                                        className="flex gap-4 px-6 py-4"
                                    >
                                        <div className="h-20 w-20 shrink-0 overflow-hidden rounded-xl border border-ink/10 bg-cream-deep/60">
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
                                                        strokeWidth={1.5}
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
                                            <p className="mt-1.5 text-xs text-ink-soft">
                                                {t('header.cart_qty').replace(
                                                    ':count',
                                                    String(item.qty),
                                                )}
                                            </p>
                                        </div>
                                        <span className="shrink-0 font-display text-sm text-ink">
                                            {money(item.line_total)}{' '}
                                            <span className="text-[10px]">
                                                TTC
                                            </span>
                                        </span>
                                    </li>
                                ))}
                            </ul>

                            <Separator className="bg-ink/10" />

                            {/*
                             * Deliberately the cart, not the checkout: the
                             * drawer is a confirmation that the piece landed,
                             * and the shopper still has quantities to review
                             * and a delivery address to choose.
                             */}
                            <div className="space-y-3 px-6 py-5">
                                <div className="flex items-baseline justify-between">
                                    <span className="text-xs text-ink-soft">
                                        {t('header.cart_subtotal')}
                                    </span>
                                    <span className="font-display text-lg text-ink">
                                        {money(cart.subtotal)}{' '}
                                        <span className="text-xs">TTC</span>
                                    </span>
                                </div>
                                <Link
                                    href={route('cart.show')}
                                    className="flex w-full items-center justify-center gap-2 rounded-xl bg-ink py-3 text-center font-display text-sm text-cream transition-colors hover:bg-ink/90"
                                >
                                    {t('header.cart_view')}
                                    <ArrowRight
                                        className={`h-4 w-4 ${isRTL ? 'rotate-180' : ''}`}
                                        strokeWidth={1.6}
                                    />
                                </Link>
                            </div>
                        </>
                    )}
                </div>
            </SheetContent>
        </Sheet>
    );
}
