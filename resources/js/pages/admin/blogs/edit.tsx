import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, ExternalLink, Trash2 } from 'lucide-react';
import type { ReactNode } from 'react';
import BlogForm from '@/components/admin/BlogForm';
import { useTranslation } from '@/hooks/use-translation';
import AdminLayout from '@/layouts/admin/layout';
import { formatDate } from '@/lib/utils';
import type { Blog, BlogStatus, PageProps } from '@/types';

const Edit = ({
    blog,
    statuses,
}: PageProps & { blog: Blog; statuses: BlogStatus[] }) => {
    const { t } = useTranslation();
    const handleDelete = () => {
        if (
            confirm(
                `Supprimer « ${blog.title} » ? Cette action est irréversible.`,
            )
        ) {
            router.delete(route('admin.blogs.destroy', blog.id));
        }
    };

    return (
        <div className="flex flex-col gap-6 p-6 md:p-8">
            <Head title={`${t('titles.admin.blog_edit')} — ${blog.title}`} />

            <header className="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <Link
                        href={route('admin.blogs.index')}
                        className="inline-flex items-center gap-1.5 text-sm text-ink-soft transition-colors hover:text-ink"
                    >
                        <ArrowLeft className="h-4 w-4" />
                        Journal
                    </Link>
                    <h1 className="mt-2 text-2xl font-semibold tracking-tight text-ink">
                        {blog.title}
                    </h1>
                    <p className="mt-1 text-sm text-ink-soft">
                        {blog.author?.name ?? 'Auteur supprimé'} ·{' '}
                        {blog.published_at
                            ? `Paru le ${formatDate(blog.published_at)}`
                            : 'Jamais publié'}
                        {' · '}
                        {blog.reading_minutes} min de lecture
                    </p>
                </div>

                <div className="flex items-center gap-2">
                    {/* Only a live article has a public URL that resolves —
                        the show route is scoped to published posts. */}
                    {blog.is_published && (
                        <a
                            href={route('blog.show', blog.slug)}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="inline-flex items-center gap-2 rounded-lg border border-border bg-card px-4 py-2.5 text-sm font-medium text-ink-soft transition hover:border-ink/30 hover:text-ink"
                        >
                            <ExternalLink className="h-4 w-4" />
                            Voir en ligne
                        </a>
                    )}
                    <button
                        type="button"
                        onClick={handleDelete}
                        className="inline-flex items-center gap-2 rounded-lg border border-destructive/30 px-4 py-2.5 text-sm font-medium text-destructive transition hover:bg-destructive/5"
                    >
                        <Trash2 className="h-4 w-4" />
                        Supprimer
                    </button>
                </div>
            </header>

            <BlogForm
                blog={blog}
                statuses={statuses}
                action={route('admin.blogs.update', blog.id)}
                uploadUrl={route('admin.blogs.images.store')}
            />
        </div>
    );
};

Edit.layout = (page: ReactNode) => <AdminLayout children={page} />;
export default Edit;
