import { usePage } from '@inertiajs/react';
// import { PageProps } from '@/types';

export function useTranslation() {
    // const { translations, locale, direction } = usePage<PageProps>().props;
    const { translations, locale, direction } = usePage().props;

    const t = (key: string): string => {
        // key format: 'common.save' or 'seller.dashboard'
        const [file, ...rest] = key.split('.');
        const k = rest.join('.');

        return (translations as any)?.[file]?.[k] ?? key;
    };

    return { t, locale, direction, isRTL: direction === 'rtl' };
}
