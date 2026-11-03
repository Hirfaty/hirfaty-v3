import { createInertiaApp, router } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { CartProvider } from '@/contexts/CartContext';
import { CartSheetProvider } from '@/contexts/CartSheetContext';
import { initializeTheme } from '@/hooks/use-appearance';
import { setUrlDefaults } from '@/wayfinder';

// The brand, not VITE_APP_NAME: that is only defined when the build sees an
// .env with it set, and a production image built without one shipped every
// tab as "… - Laravel".
const BRAND = 'Hirfaty';

/**
 * Keep <html lang> / <html dir> — and the URL helpers' locale — in step
 * with the active locale.
 *
 * The Blade root (resources/views/app.blade.php) renders those attributes
 * once, server-side. Inertia never re-renders the root document, and the
 * language switcher is a client-side `router.post('/locale/{locale}')` — so
 * without this they keep whatever locale the tab first loaded with.
 *
 * That silently breaks Arabic rather than merely mislabelling it: app.css
 * hangs the Arabic font stack and the "never italicise Arabic" rule off
 * [lang='ar'], and RTL layout off [dir='rtl']. A stale lang="fr" leaves
 * Arabic text rendered left-to-right and italic in Didot, which carries no
 * Arabic glyphs at all.
 *
 * The URL helpers matter just as much. Public routes live under /{locale}
 * (/fr/products, /ar/products …), and neither route helper is ever passed
 * the locale explicitly — both fill it from a default, the way the server's
 * URL::defaults does. Ziggy reads `Ziggy.defaults` on every call; Wayfinder
 * asks the function below. Starts from the server-rendered <html lang>, then
 * follows the page props, so once the switcher moves the visitor to /ar/…,
 * every link on the page points into /ar/… too. A link left behind in the old
 * language would carry the visitor straight back to it on the next click.
 *
 * `@routes` declares Ziggy as a top-level `const`, which never becomes a
 * property of `globalThis`: only the bare name reaches the object route() reads.
 */
declare const Ziggy: { defaults: Record<string, unknown> } | undefined;

let currentLocale = document.documentElement.lang || 'fr';

setUrlDefaults(() => ({ locale: currentLocale }));

const syncLocale = (props: Record<string, unknown>) => {
    const locale = props.locale as string | undefined;
    const direction = props.direction as string | undefined;

    if (locale) {
        currentLocale = locale;
        document.documentElement.lang = locale;

        if (typeof Ziggy !== 'undefined') {
            Ziggy.defaults = { ...Ziggy.defaults, locale };
        }
    }

    if (direction) {
        document.documentElement.dir = direction;
    }
};

// Two events, because neither covers both cases on its own: `beforeUpdate`
// fires for ordinary visits (including the switcher's POST /locale/{locale})
// before the new page renders — `success` would be too late, the links would
// already be built in the old language. `navigate` covers browser
// back/forward, where no request is made and so no beforeUpdate is emitted.
// Both carry the resolved page props, and the handler is idempotent, so the
// overlap is harmless.
router.on('beforeUpdate', (event) => syncLocale(event.detail.page.props));
router.on('navigate', (event) => syncLocale(event.detail.page.props));

createInertiaApp({
    // Pages pass only their own name; the brand is appended here, once.
    title: (title) => (title ? `${title} — ${BRAND}` : BRAND),
    // No automatic per-page layout: every page wraps its own.
    layout: () => null,
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                <CartProvider>
                    {/*
                     * Above the app, not inside a page: the drawer is opened
                     * from a product page but rendered by SiteHeader, and the
                     * two are siblings. See CartSheetContext.
                     */}
                    <CartSheetProvider>
                        {app}
                        <Toaster />
                    </CartSheetProvider>
                </CartProvider>
            </TooltipProvider>
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();
