import { Head, Link, router } from '@inertiajs/react';
import {
    Archive,
    ArchiveRestore,
    ChevronLeft,
    ChevronRight,
    EllipsisVertical,
    Eye,
    FileText,
    ImageOff,
    Loader2,
    Pencil,
    Plus,
    Search,
    Trash2,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useTranslation } from '@/hooks/use-translation';
import AdminLayout from '@/layouts/admin/layout';
import { formatDate } from '@/lib/utils';
import type { Blog, BlogStatus, PageProps, PaginatedData } from '@/types';

/** Plural, because these label the filter tabs alongside a count. */
const STATUS_LABELS: Record<BlogStatus, string> = {
    draft: 'Brouillons',
    published: 'Publiés',
    archived: 'Archivés',
};

/** Singular, for the badge on a single row. */
const STATUS_PILL_LABELS: Record<BlogStatus, string> = {
    draft: 'Brouillon',
    published: 'Publié',
    archived: 'Archivé',
};

/** Badge colours per status, matching the role badges on /admin/users. */
const STATUS_BADGE: Record<BlogStatus, string> = {
    draft: 'bg-slate-100 text-slate-700',
    published: 'bg-emerald-100 text-emerald-700',
    archived: 'bg-amber-100 text-amber-700',
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
    const { t } = useTranslation();
    const [search, setSearch] = useState(filters.search);
    const [searching, setSearching] = useState(false);
    // Skip the debounced effect's own request on first render — otherwise
    // landing on the page with ?search= already set fires a redundant fetch.
    const isFirstRender = useRef(true);

    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;

            return;
        }

        setSearching(true);
        const timer = setTimeout(() => {
            router.get(
                route('admin.blogs.index'),
                { status: filters.status, search: search || undefined },
                {
                    preserveState: true,
                    replace: true,
                    onFinish: () => setSearching(false),
                },
            );
        }, 300);

        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const setStatus = (status: string) => {
        router.get(
            route('admin.blogs.index'),
            { search: search || undefined, status: status || undefined },
            { preserveState: true },
        );
    };

    /** Shared success/error handling for the row actions, which all flash. */
    const rowAction = {
        preserveScroll: true,
        onSuccess: (page: { props: object }) => {
            const flash = (page.props as unknown as PageProps).flash;

            if (flash?.error) {
                toast.error(flash.error);
            } else if (flash?.success) {
                toast.success(flash.success);
            }
        },
        onError: () => toast.error('Une erreur est survenue.'),
    };

    const handleArchive = (blog: Blog) => {
        router.patch(
            route('admin.blogs.archive', blog.id),
            undefined,
            rowAction,
        );
    };

    const handleDelete = (blog: Blog) => {
        if (
            confirm(
                `Supprimer « ${blog.title} » ? Cette action est irréversible.`,
            )
        ) {
            router.delete(route('admin.blogs.destroy', blog.id), rowAction);
        }
    };

    const totalCount = Object.values(statusCounts).reduce(
        (sum, n) => sum + n,
        0,
    );
    const tabs: { value: string; label: string; count: number }[] = [
        { value: '', label: 'Tous', count: totalCount },
        ...statuses.map((status) => ({
            value: status,
            label: STATUS_LABELS[status],
            count: statusCounts[status] ?? 0,
        })),
    ];

    return (
        <div className="flex flex-col gap-6 p-6 md:p-8">
            <Head title={t('titles.admin.blogs')} />

            <header className="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight text-ink">
                        Journal
                    </h1>
                    <p className="mt-1 text-sm text-ink-soft">
                        {totalCount} article{totalCount > 1 ? 's' : ''} au
                        total.
                    </p>
                </div>
                <Link
                    href={route('admin.blogs.create')}
                    className="inline-flex items-center gap-2 rounded-lg bg-ink px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-ink/90"
                >
                    <Plus className="h-4 w-4" />
                    Écrire un article
                </Link>
            </header>

            {/* ── Status filter tabs ───────────────────────── */}
            <div className="flex flex-wrap gap-2">
                {tabs.map((tab) => {
                    const active = filters.status === tab.value;

                    return (
                        <button
                            key={tab.label}
                            type="button"
                            onClick={() => setStatus(tab.value)}
                            className={`inline-flex items-center gap-2 rounded-lg border px-3.5 py-2 text-sm font-medium transition ${
                                active
                                    ? 'border-ink bg-ink text-white'
                                    : 'border-border bg-card text-ink-soft hover:border-ink/30 hover:text-ink'
                            }`}
                        >
                            {tab.label}
                            <span
                                className={`rounded-full px-1.5 py-0.5 text-xs ${
                                    active
                                        ? 'bg-white/20 text-white'
                                        : 'bg-muted text-ink-soft'
                                }`}
                            >
                                {tab.count}
                            </span>
                        </button>
                    );
                })}
            </div>

            {/* ── Search ───────────────────────────────────── */}
            <div className="relative max-w-md">
                {searching ? (
                    <Loader2 className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 animate-spin text-ink-soft/50" />
                ) : (
                    <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-ink-soft/50" />
                )}
                <input
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    placeholder="Rechercher un titre…"
                    className="w-full rounded-lg border border-border bg-card py-2.5 pr-3 pl-9 text-sm text-ink shadow-sm transition placeholder:text-ink-soft/60 focus:border-ink focus:ring-2 focus:ring-ink/10 focus:outline-none"
                />
            </div>

            {/* ── Table ────────────────────────────────────── */}
            <div className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                <Table>
                    <TableHeader>
                        <TableRow className="hover:bg-transparent">
                            <TableHead>Article</TableHead>
                            <TableHead>Statut</TableHead>
                            <TableHead>Parution</TableHead>
                            <TableHead>Auteur</TableHead>
                            <TableHead className="text-right">
                                Actions
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {blogs.data.length === 0 ? (
                            <TableRow>
                                <TableCell
                                    colSpan={5}
                                    className="h-32 text-center"
                                >
                                    <FileText className="mx-auto h-8 w-8 text-ink/15" />
                                    <p className="mt-3 text-sm text-ink-soft">
                                        {filters.search || filters.status
                                            ? 'Aucun article ne correspond à cette recherche.'
                                            : 'Aucun article pour le moment.'}
                                    </p>
                                </TableCell>
                            </TableRow>
                        ) : (
                            blogs.data.map((blog) => (
                                <TableRow key={blog.id}>
                                    <TableCell>
                                        <div className="flex items-center gap-3">
                                            <div className="h-12 w-16 shrink-0 overflow-hidden rounded-lg bg-muted">
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
                                                    className="block truncate font-medium text-ink hover:underline"
                                                >
                                                    {blog.title}
                                                </Link>
                                                <p className="mt-0.5 truncate text-xs text-ink-soft">
                                                    {blog.reading_minutes} min ·
                                                    /{blog.slug}
                                                </p>
                                            </div>
                                        </div>
                                    </TableCell>

                                    <TableCell>
                                        <span
                                            className={`inline-flex rounded-full px-2.5 py-1 text-xs font-medium ${STATUS_BADGE[blog.status]}`}
                                        >
                                            {STATUS_PILL_LABELS[blog.status]}
                                        </span>
                                    </TableCell>

                                    <TableCell className="text-sm text-ink-soft">
                                        {blog.published_at
                                            ? formatDate(blog.published_at)
                                            : '—'}
                                    </TableCell>

                                    <TableCell className="text-sm text-ink-soft">
                                        {blog.author?.name ?? '—'}
                                    </TableCell>

                                    <TableCell className="text-right">
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="h-8 w-8"
                                                >
                                                    <EllipsisVertical className="h-4 w-4" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                <DropdownMenuItem asChild>
                                                    <Link
                                                        href={route(
                                                            'admin.blogs.preview',
                                                            blog.id,
                                                        )}
                                                        className="flex items-center gap-2"
                                                    >
                                                        <Eye className="h-4 w-4" />
                                                        Aperçu
                                                    </Link>
                                                </DropdownMenuItem>
                                                <DropdownMenuItem asChild>
                                                    <Link
                                                        href={route(
                                                            'admin.blogs.edit',
                                                            blog.id,
                                                        )}
                                                        className="flex items-center gap-2"
                                                    >
                                                        <Pencil className="h-4 w-4" />
                                                        Modifier
                                                    </Link>
                                                </DropdownMenuItem>

                                                <DropdownMenuItem
                                                    onClick={() =>
                                                        handleArchive(blog)
                                                    }
                                                    className="flex items-center gap-2"
                                                >
                                                    {blog.status ===
                                                    'archived' ? (
                                                        <>
                                                            <ArchiveRestore className="h-4 w-4" />
                                                            Remettre en
                                                            brouillon
                                                        </>
                                                    ) : (
                                                        <>
                                                            <Archive className="h-4 w-4" />
                                                            Archiver
                                                        </>
                                                    )}
                                                </DropdownMenuItem>

                                                <DropdownMenuItem
                                                    onClick={() =>
                                                        handleDelete(blog)
                                                    }
                                                    className="flex items-center gap-2 text-destructive"
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                    Supprimer
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </TableCell>
                                </TableRow>
                            ))
                        )}
                    </TableBody>
                </Table>

                {/* ── Pagination ───────────────────────────── */}
                {blogs.last_page > 1 && (
                    <div className="flex items-center justify-between border-t border-border px-5 py-3.5">
                        <p className="text-xs text-ink-soft">
                            {blogs.from}–{blogs.to} sur {blogs.total}
                        </p>
                        <div className="flex items-center gap-1">
                            <Button
                                variant="outline"
                                size="icon"
                                className="h-8 w-8"
                                disabled={!blogs.prev_page_url}
                                onClick={() =>
                                    router.get(
                                        blogs.prev_page_url!,
                                        undefined,
                                        {
                                            preserveScroll: true,
                                        },
                                    )
                                }
                            >
                                <ChevronLeft className="h-4 w-4" />
                            </Button>
                            {blogs.links
                                .filter((link) => !isNaN(Number(link.label)))
                                .map((link) => (
                                    <Button
                                        key={link.label}
                                        variant={
                                            link.active ? 'default' : 'ghost'
                                        }
                                        size="sm"
                                        className="h-8 w-8"
                                        onClick={() =>
                                            link.url &&
                                            router.get(link.url, undefined, {
                                                preserveScroll: true,
                                            })
                                        }
                                    >
                                        {link.label}
                                    </Button>
                                ))}
                            <Button
                                variant="outline"
                                size="icon"
                                className="h-8 w-8"
                                disabled={!blogs.next_page_url}
                                onClick={() =>
                                    router.get(
                                        blogs.next_page_url!,
                                        undefined,
                                        {
                                            preserveScroll: true,
                                        },
                                    )
                                }
                            >
                                <ChevronRight className="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
};

Index.layout = (page: ReactNode) => <AdminLayout children={page} />;
export default Index;
