import { usePage } from '@inertiajs/react';
import { TriangleAlert } from 'lucide-react';
import type { PageProps } from '@/types';

/**
 * A loud banner for the staging site (init.hirfaty.com). Renders nothing
 * anywhere else, so it can sit permanently on the admin pages where filing
 * data by mistake is easy — the new-product form, a user's profile.
 *
 * `path` is where the same task lives on the real site.
 */
export default function StagingWarning({ path = '/admin' }: { path?: string }) {
    const { staging } = usePage<PageProps>().props;

    if (!staging) {
        return null;
    }

    const target = `${staging.production_url}${path}`;
    const host = staging.production_url.replace(/^https?:\/\//, '');

    return (
        <div
            role="alert"
            className="rounded-2xl border-4 border-red-800 bg-red-600 px-6 py-8 text-center text-white shadow-lg"
        >
            <TriangleAlert className="mx-auto mb-3 h-12 w-12" />
            <p className="text-3xl leading-tight font-black tracking-tight uppercase sm:text-5xl">
                Zone de test (staging)
            </p>
            <p className="mt-4 text-xl font-bold sm:text-3xl">
                Ne créez pas de produits ici.
            </p>
            <p className="mt-2 text-lg sm:text-2xl">
                Rendez-vous sur{' '}
                <a href={target} className="font-black underline">
                    {host}
                </a>
            </p>
        </div>
    );
}
