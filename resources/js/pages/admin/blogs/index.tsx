import { Head, Link, router } from '@inertiajs/react';
import {
    Eye,
    FileText,
    ImageOff,
    PenLine,
    Plus,
    Search,
    Trash2,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import AdminLayout from '@/layouts/admin/layout';
import { formatDate } from '@/lib/utils';
import type { Blog, BlogStatus, PageProps, PaginatedData } from '@/types';

/** Plural, because these label the filter tabs alongside a count. */
const STATUS_LABELS: Record<BlogStatus, string> = {
    draft: 'Brouillons',
    published: 'Publiés',
    archived: 'Archivés',
};

/** Singular, for the pill on a single row. */
const STATUS_PILL_LABELS: Record<BlogStatus, string> = {
    draft: 'Brouillon',
    published: 'Publié',
    archived: 'Archivé',
};

/** Pill colours per status, so the table scans at a glance. */
const STATUS_STYLES: Record<BlogStatus, string> = {
    draft: 'bg-ink/5 text-ink-soft',
    published: 'bg-olive/15 text-olive',
    archived: 'bg-clay/20 text-ink-soft',
};

interface Filters {
    search: string;
    status: string;
}

const Index = ({
    blogs,
    filters,
    statusCounts,
    statuses,
}: PageProps & {
    blogs: PaginatedData<Blog>;
    filters: Filters;
    statusCounts: Record<string, number>;
    statuses: BlogStatus[];
}) => {
    const [search, setSearch] = useState(filters.search);

    /**
     * One entry point for both controls, because they share a querystring:
     * changing the tab must not drop the search term, and vice versa.
     */
    const applyFilters = (next: Partial<Filters>) => {
        router.get(
            route('admin.blogs.index'),
            { search, status: filters.status, ...next },
            { preserveState: true, replace: true },
        );
    };

    const handleDelete = (blog: Blog) => {
        if (
            confirm(
                `Supprimer « ${blog.title} » ? Cette action est irréversible.`,
            )
        ) {
            router.delete(route('admin.blogs.destroy', blog.id), {
                preserveScroll: true,
            });
        }
    };

    return (
        <div className="min-h-screen bg-cream/30 p-6 md:p-10">
            <Head title="Journal" />

            <div className="mx-auto max-w-7xl">
                <header className="mb-10 flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p className="font-sans text-xs font-medium tracking-[0.2em] text-ink-soft uppercase">
                            Contenu éditorial
                        </p>
                        <h1 className="mt-2 h-editorial text-4xl text-ink md:text-5xl">
                            Le <span className="italic">Journal</span>
                        </h1>
                    </div>

                    <Link
                        href={route('admin.blogs.create')}
                        className="flex items-center gap-2 bg-ink px-6 py-3.5 text-xs font-bold tracking-widest text-cream uppercase transition-colors hover:bg-terracotta"
                    >
                        <Plus className="h-4 w-4" />
                        Écrire un article
                    </Link>
                </header>

                {/* ── Filters ── */}
                <div className="mb-6 flex flex-wrap items-center gap-3">
                    <div className="relative flex-1 md:max-w-sm">
                        <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-ink-soft" />
                        <input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter') {
                                    applyFilters({
                                        search: e.currentTarget.value,
                                    });
                                }
                            }}
                            placeholder="Rechercher un titre…"
                            className="w-full border border-ink/10 bg-white py-2.5 pr-3 pl-9 text-sm text-ink focus:border-terracotta focus:outline-none"
                        />
                    </div>

                    <div className="flex flex-wrap items-center gap-1.5">
                        <FilterTab
                            label="Tous"
                            count={Object.values(statusCounts).reduce(
                                (sum, n) => sum + n,
                                0,
                            )}
                            active={filters.status === ''}
                            onClick={() => applyFilters({ status: '' })}
                        />
                        {statuses.map((status) => (
                            <FilterTab
                                key={status}
                                label={STATUS_LABELS[status]}
                                count={statusCounts[status] ?? 0}
                                active={filters.status === status}
                                onClick={() => applyFilters({ status })}
                            />
                        ))}
                    </div>
                </div>

                {/* ── Table ── */}
                <div className="border border-ink/5 bg-white shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left">
                            <thead>
                                <tr className="border-b border-ink/5 bg-ink/[0.02]">
                                    <Th>Article</Th>
                                    <Th>Statut</Th>
                                    <Th>Parution</Th>
                                    <Th>Auteur</Th>
                                    <Th>Actions</Th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-ink/5">
                                {blogs.data.map((blog) => (
                                    <tr
                                        key={blog.id}
                                        className="group transition-colors hover:bg-cream/10"
                                    >
                                        <td className="px-6 py-4">
                                            <div className="flex items-center gap-3">
                                                <div className="h-12 w-16 shrink-0 overflow-hidden bg-cream/40">
                                                    {blog.cover_url ? (
                                                        <img
                                                            src={blog.cover_url}
                                                            alt=""
                                                            className="h-full w-full object-cover"
                                                        />
                                                    ) : (
                                                        <div className="flex h-full w-full items-center justify-center text-ink/20">
                                                            <ImageOff className="h-4 w-4" />
                                                        </div>
                                                    )}
                                                </div>
                                                <div className="min-w-0">
                                                    <Link
                                                        href={route(
                                                            'admin.blogs.edit',
                                                            blog.id,
                                                        )}
                                                        className="block truncate font-display text-base text-ink transition-colors group-hover:text-terracotta"
                                                    >
                                                        {blog.title}
                                                    </Link>
                                                    <p className="mt-0.5 truncate text-xs text-ink-soft">
                                                        {blog.reading_minutes}{' '}
                                                        min · /{blog.slug}
                                                    </p>
                                                </div>
                                            </div>
                                        </td>

                                        <td className="px-6 py-4">
                                            <span
                                                className={`inline-block px-2.5 py-1 text-[10px] font-bold tracking-widest uppercase ${STATUS_STYLES[blog.status]}`}
                                            >
                                                {
                                                    STATUS_PILL_LABELS[
                                                        blog.status
                                                    ]
                                                }
                                            </span>
                                            {/* Published but dated forward — it
                                                is queued, not live yet. */}
                                            {blog.status === 'published' &&
                                                !blog.is_published && (
                                                    <p className="mt-1 text-[10px] text-terracotta">
                                                        Programmé
                                                    </p>
                                                )}
                                        </td>

                                        <td className="px-6 py-4 text-sm text-ink-soft">
                                            {blog.published_at
                                                ? formatDate(blog.published_at)
                                                : '—'}
                                        </td>

                                        <td className="px-6 py-4 text-sm text-ink-soft">
                                            {blog.author?.name ?? '—'}
                                        </td>

                                        <td className="px-6 py-4">
                                            <div className="flex items-center gap-1">
                                                <IconLink
                                                    href={route(
                                                        'admin.blogs.preview',
                                                        blog.id,
                                                    )}
                                                    label="Aperçu"
                                                >
                                                    <Eye className="h-4 w-4" />
                                                </IconLink>
                                                <IconLink
                                                    href={route(
                                                        'admin.blogs.edit',
                                                        blog.id,
                                                    )}
                                                    label="Modifier"
                                                >
                                                    <PenLine className="h-4 w-4" />
                                                </IconLink>
                                                <button
                                                    type="button"
                                                    title="Supprimer"
                                                    aria-label="Supprimer"
                                                    onClick={() =>
                                                        handleDelete(blog)
                                                    }
                                                    className="rounded-md p-2 text-ink-soft transition-colors hover:bg-red-50 hover:text-red-600"
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}

                                {blogs.data.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={5}
                                            className="px-6 py-16 text-center"
                                        >
                                            <FileText className="mx-auto h-8 w-8 text-ink/15" />
                                            <p className="mt-3 text-sm text-ink-soft">
                                                {filters.search ||
                                                filters.status
                                                    ? 'Aucun article ne correspond à cette recherche.'
                                                    : 'Aucun article pour le moment.'}
                                            </p>
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    {blogs.last_page > 1 && (
                        <div className="flex items-center justify-between border-t border-ink/5 px-6 py-3.5">
                            <p className="text-xs text-ink-soft">
                                {blogs.from}–{blogs.to} sur {blogs.total}
                            </p>
                            <div className="flex items-center gap-1">
                                {blogs.links.map((link, i) => (
                                    <Link
                                        key={i}
                                        href={link.url || '#'}
                                        preserveScroll
                                        className={`flex h-8 min-w-8 items-center justify-center rounded-md px-2 text-sm transition ${
                                            link.active
                                                ? 'bg-ink font-medium text-white'
                                                : 'text-ink-soft hover:bg-muted'
                                        } ${!link.url ? 'pointer-events-none opacity-40' : ''}`}
                                        dangerouslySetInnerHTML={{
                                            __html: link.label,
                                        }}
                                    />
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
};

function Th({ children }: { children: ReactNode }) {
    return (
        <th className="px-6 py-4 font-sans text-[10px] font-bold tracking-widest text-ink/40 uppercase">
            {children}
        </th>
    );
}

function FilterTab({
    label,
    count,
    active,
    onClick,
}: {
    label: string;
    count: number;
    active: boolean;
    onClick: () => void;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={`px-3.5 py-2 text-[11px] font-bold tracking-widest uppercase transition-colors ${
                active
                    ? 'bg-ink text-cream'
                    : 'border border-ink/10 text-ink-soft hover:border-terracotta hover:text-terracotta'
            }`}
        >
            {label}
            <span className="ml-1.5 opacity-60">{count}</span>
        </button>
    );
}

function IconLink({
    href,
    label,
    children,
}: {
    href: string;
    label: string;
    children: ReactNode;
}) {
    return (
        <Link
            href={href}
            title={label}
            aria-label={label}
            className="rounded-md p-2 text-ink-soft transition-colors hover:bg-cream hover:text-terracotta"
        >
            {children}
        </Link>
    );
}

Index.layout = (page: ReactNode) => <AdminLayout children={page} />;
export default Index;
