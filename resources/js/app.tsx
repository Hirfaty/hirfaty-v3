import { createInertiaApp } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { CartProvider } from '@/contexts/CartContext';
import { initializeTheme } from '@/hooks/use-appearance';
import { initLocaleDirection } from '@/lib/locale-direction';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    // Pages import and wrap their own layouts, so there is no automatic
    // per-page layout resolution here.
    layout: () => null,
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

// Keep <html lang>/<html dir> correct when the locale changes mid-session.
initLocaleDirection();
