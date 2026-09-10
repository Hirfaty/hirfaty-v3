import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, CheckCircle2, Loader2 } from 'lucide-react';
import type { ReactNode } from 'react';
import { useEffect } from 'react';
import BlogForm from '@/components/admin/BlogForm';
import AdminLayout from '@/layouts/admin/layout';
import type { BlogStatus, PageProps } from '@/types';

/** Set by Admin\BlogController::store on the redirect back here. */
interface JustCreated {
    id: number;
    title: string;
}

/** How long the confirmation stays up before the listing takes over. */
const REDIRECT_DELAY_MS = 3000;

const Create = ({
    statuses,
    justCreated,
}: PageProps & {
    statuses: BlogStatus[];
    justCreated?: JustCreated | null;
}) => {
    useEffect(() => {
        if (!justCreated) {
            return;
        }

        const timer = setTimeout(
            () => router.visit(route('admin.blogs.index')),
            REDIRECT_DELAY_MS,
        );

        // Cleared on unmount so navigating away early (the "voir la liste"
        // link below) cannot fire a second visit afterwards.
        return () => clearTimeout(timer);
    }, [justCreated]);

    return (
        <div className="flex flex-col gap-6 p-6 md:p-8">
            <Head title="Nouvel article" />

            <header>
                <Link
                    href={route('admin.blogs.index')}
                    className="inline-flex items-center gap-1.5 text-sm text-ink-soft transition-colors hover:text-ink"
                >
                    <ArrowLeft className="h-4 w-4" />
                    Journal
                </Link>
                <h1 className="mt-2 text-2xl font-semibold tracking-tight text-ink">
                    Nouvel article
                </h1>
                <p className="mt-1 text-sm text-ink-soft">
                    Renseignez les cinq blocs ci-dessous, dans l’ordre.
                </p>
            </header>

            {/*
                The form is replaced rather than merely covered: the article
                already exists, and leaving an empty form on screen invites the
                admin to start typing a second one moments before the redirect
                pulls the page out from under them.
            */}
            {justCreated ? (
                <div className="max-w-4xl rounded-xl border border-emerald-200 bg-emerald-50 p-8 text-center shadow-sm">
                    <CheckCircle2 className="mx-auto h-10 w-10 text-emerald-600" />
                    <h2 className="mt-4 text-lg font-semibold text-ink">
                        Article créé
                    </h2>
                    <p className="mt-1.5 text-sm text-ink-soft">
                        « {justCreated.title} » a bien été enregistré.
                    </p>
                    <p className="mt-4 inline-flex items-center gap-2 text-xs text-ink-soft">
                        <Loader2 className="h-3.5 w-3.5 animate-spin" />
                        Retour au journal dans 3 secondes…
                    </p>
                    <div className="mt-5 flex flex-wrap items-center justify-center gap-3">
                        <Link
                            href={route('admin.blogs.index')}
                            className="inline-flex items-center gap-2 rounded-lg bg-ink px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-ink/90"
                        >
                            Voir le journal
                        </Link>
                        <Link
                            href={route('admin.blogs.edit', justCreated.id)}
                            className="inline-flex items-center gap-2 rounded-lg border border-border bg-card px-4 py-2.5 text-sm font-medium text-ink-soft transition hover:border-ink/30 hover:text-ink"
                        >
                            Continuer à écrire
                        </Link>
                    </div>
                </div>
            ) : (
                <BlogForm
                    statuses={statuses}
                    action={route('admin.blogs.store')}
                    uploadUrl={route('admin.blogs.images.store')}
                />
            )}
        </div>
    );
};

Create.layout = (page: ReactNode) => <AdminLayout children={page} />;
export default Create;
