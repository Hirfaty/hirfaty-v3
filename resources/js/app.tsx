import { createInertiaApp, router } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { CartProvider } from '@/contexts/CartContext';
import { initializeTheme } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

/**
 * Keep <html lang> / <html dir> in step with the active locale.
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
 */
const syncDocumentLocale = (props: Record<string, unknown>) => {
    const locale = props.locale as string | undefined;
    const direction = props.direction as string | undefined;

    if (locale) {
        document.documentElement.lang = locale;
    }

    if (direction) {
        document.documentElement.dir = direction;
    }
};

// Two events, because neither covers both cases on its own: `success` fires
// for ordinary visits (including the switcher's POST /locale/{locale}), while
// `navigate` covers browser back/forward, where no request is made and so no
// success event is emitted. Both carry the resolved page props, and the
// handler is idempotent, so the overlap is harmless.
router.on('success', (event) => syncDocumentLocale(event.detail.page.props));
router.on('navigate', (event) => syncDocumentLocale(event.detail.page.props));

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        // switch (true) {
        //     case name === 'welcome':
        //         return null;
        //     case name.startsWith('auth/'):
        //         return AuthLayout;
        //     case name.startsWith('settings/'):
        //         return [AppLayout, SettingsLayout];
        //     default:
        //         return AppLayout;
        // }
        return null;
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                <CartProvider>
                    {app}
                    <Toaster />
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
