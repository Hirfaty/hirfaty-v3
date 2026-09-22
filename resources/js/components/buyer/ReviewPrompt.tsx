import { router, usePage } from '@inertiajs/react';
import { Star } from 'lucide-react';
import { useEffect, useState } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useTranslation } from '@/hooks/use-translation';

/**
 * Where "not now" is remembered.
 *
 * sessionStorage, not localStorage: dismissing should silence the prompt for
 * this visit, not retire it. A buyer who closes it and comes back tomorrow
 * with the delivery still unrated is exactly who it is for — and there is no
 * server flag to unset, so forgetting on tab close is the whole mechanism.
 */
const DISMISSED_KEY = 'hirfaty_review_prompt_dismissed';

/**
 * "You have deliveries to rate" — shown once on arrival.
 *
 * Mounted by SiteHeader rather than by a page, because the point is to catch a
 * buyer wherever they land: the homepage, a product, their basket. `SiteHeader`
 * is the one thing all of those share.
 *
 * It does not rate anything itself. Rating happens on the order list, against
 * the pieces, where the buyer can see what they actually received — this only
 * carries them there. A star input floating over the homepage would be asking
 * someone to judge a vase they can no longer see.
 *
 * `pendingReviews` is shared globally (HandleInertiaRequests) and counts
 * delivered orders with at least one unrated piece. It is null for anyone who
 * is not a signed-in buyer, which is the guard against ever rendering this to
 * a guest, a seller or an admin.
 */
export default function ReviewPrompt() {
    const { t } = useTranslation();
    const page = usePage();
    const pending = page.props.pendingReviews as number | null | undefined;

    const [open, setOpen] = useState(false);

    /*
     * Opened from an effect, not from the initial state, for two reasons:
     * sessionStorage does not exist during SSR, and the check has to re-run on
     * navigation — the buyer may have landed first on a page they were sent to
     * by a link, and this is the first moment the prop is readable.
     *
     * Only ever *opens* here. Closing is the user's job, and re-deriving
     * `open` from the count on every navigation would reopen the dialog the
     * moment they dismissed it.
     */
    useEffect(() => {
        if (!pending || pending < 1) {
            return;
        }

        // Already looking at the order list — the prompt would be pointing at
        // the page it is covering.
        if (page.url.startsWith('/orders')) {
            return;
        }

        if (sessionStorage.getItem(DISMISSED_KEY) === '1') {
            return;
        }

        setOpen(true);
    }, [pending, page.url]);

    const dismiss = () => {
        sessionStorage.setItem(DISMISSED_KEY, '1');
        setOpen(false);
    };

    const goToOrders = () => {
        /*
         * Marked dismissed on the way out too. Without this the prompt fires
         * again the moment they navigate away from /orders having rated only
         * one of two deliveries — which reads as the site not having noticed
         * they just did what it asked.
         */
        sessionStorage.setItem(DISMISSED_KEY, '1');
        setOpen(false);
        router.visit(route('buyer.account.orders.index'));
    };

    return (
        <Dialog
            open={open}
            // Covers the overlay click and Escape as well as the X, so every
            // way out is remembered rather than only the button.
            onOpenChange={(next) => (next ? setOpen(true) : dismiss())}
        >
            <DialogContent className="border-souk-brown/15 bg-souk-cream sm:max-w-md">
                <DialogHeader>
                    <span
                        aria-hidden
                        className="mb-1 inline-flex h-11 w-11 items-center justify-center rounded-full bg-terracotta/10"
                    >
                        <Star className="h-5 w-5 fill-terracotta text-terracotta" />
                    </span>

                    <DialogTitle className="font-display text-xl text-souk-title">
                        {t('buyer.reviews.prompt_title')}
                    </DialogTitle>

                    <DialogDescription className="text-souk-brown-deep/70">
                        {(pending ?? 0) > 1
                            ? t('buyer.reviews.prompt_body_plural').replace(
                                  ':count',
                                  String(pending),
                              )
                            : t('buyer.reviews.prompt_body')}
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter>
                    <button
                        type="button"
                        onClick={dismiss}
                        className="px-4 py-2 text-sm text-souk-brown-deep/60 transition-colors hover:text-souk-brown-deep"
                    >
                        {t('buyer.reviews.prompt_later')}
                    </button>

                    <button
                        type="button"
                        onClick={goToOrders}
                        className="border border-souk-brown bg-souk-brown px-5 py-2 text-sm text-souk-cream transition-colors hover:bg-souk-brown-deep"
                    >
                        {t('buyer.reviews.prompt_cta')}
                    </button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
