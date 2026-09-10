import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, ExternalLink, Trash2 } from 'lucide-react';
import type { ReactNode } from 'react';
import BlogForm from '@/components/admin/BlogForm';
import AdminLayout from '@/layouts/admin/layout';
import { formatDate } from '@/lib/utils';
import type { Blog, BlogStatus, PageProps } from '@/types';

const Edit = ({
    blog,
    statuses,
}: PageProps & { blog: Blog; statuses: BlogStatus[] }) => {
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
        <div className="min-h-screen bg-cream/30 p-6 md:p-10">
            <Head title={`Modifier — ${blog.title}`} />

            <div className="mx-auto max-w-7xl">
                <header className="mb-10 flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <Link
                            href={route('admin.blogs.index')}
                            className="inline-flex items-center gap-1.5 text-xs tracking-wider text-ink-soft uppercase transition-colors hover:text-terracotta"
                        >
                            <ArrowLeft className="h-3.5 w-3.5" />
                            Journal
                        </Link>
                        <h1 className="mt-3 h-editorial text-4xl text-ink md:text-5xl">
                            {blog.title}
                        </h1>
                        <p className="mt-2 text-xs text-ink-soft">
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
                                className="flex items-center gap-1.5 border border-ink/10 px-4 py-2.5 text-[11px] font-bold tracking-widest text-ink-soft uppercase transition-colors hover:border-terracotta hover:text-terracotta"
                            >
                                <ExternalLink className="h-3.5 w-3.5" />
                                Voir en ligne
                            </a>
                        )}
                        <button
                            type="button"
                            onClick={handleDelete}
                            className="flex items-center gap-1.5 border border-red-200 px-4 py-2.5 text-[11px] font-bold tracking-widest text-red-600 uppercase transition-colors hover:bg-red-50"
                        >
                            <Trash2 className="h-3.5 w-3.5" />
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
        </div>
    );
};

Edit.layout = (page: ReactNode) => <AdminLayout children={page} />;
export default Edit;
