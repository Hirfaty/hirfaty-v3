import { router } from '@inertiajs/react';

/**
 * Change the site's language: the one way every language picker posts it.
 *
 * `preserveState: false` is the point. router.post() preserves state by
 * default, which here would keep the page's components mounted — and React
 * Compiler caches a `route('privacy')` or `route('products.show', slug)` for
 * as long as its arguments don't change, whatever the locale. Those links
 * would go on pointing at the old language, and the first click would carry
 * the visitor straight back to it. Remounting rebuilds every one of them.
 */
export const switchLocale = (code: string) =>
    router.post(
        route('locale.switch', code),
        {},
        { preserveScroll: true, preserveState: false },
    );
