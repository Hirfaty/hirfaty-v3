import { router } from '@inertiajs/react';

/**
 * Keeps <html lang> and <html dir> in sync with the current locale.
 *
 * The Blade shell sets both on the initial request, but Inertia never
 * re-renders that shell — so switching to Arabic mid-session left the document
 * in `lang="fr" dir="ltr"` and the whole layout stuck in LTR.
 *
 * This listens on the router rather than using `usePage()`, because the app has
 * no single shared layout to mount a component into, and anything rendered
 * beside Inertia's own component sits outside its context.
 */
export function initLocaleDirection(): void {
    const apply = (props: Record<string, unknown> | undefined) => {
        const root = document.documentElement;
        const locale = props?.locale;
        const direction = props?.direction;

        if (typeof locale === 'string' && locale) {
            root.lang = locale;
        }

        if (direction === 'rtl' || direction === 'ltr') {
            root.dir = direction;
        }
    };

    // `navigate` covers the initial page load and ordinary visits, but Inertia
    // skips it when a visit only *replaces* the current history entry — which
    // is exactly what the locale switcher does, since it redirects back to the
    // same URL. `success` fires on every successful response, so listen to both.
    router.on('navigate', (event) => apply(event.detail.page?.props));
    router.on('success', (event) => apply(event.detail.page?.props));
}

export default initLocaleDirection;
