import { Head, router, useForm } from '@inertiajs/react';
import {
    ChevronRight,
    Edit3,
    FolderTree,
    Layers,
    Plus,
    Search,
    Trash2,
    TriangleAlert,
    X,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import AdminLayout from '@/layouts/admin/layout';
import type { Category, CategoryLocale, PageProps } from '@/types';

interface CategoryWithCounts extends Category {
    products_count: number;
    stores_count: number;
    parent?: Category | null;
}

/** Labels for the per-locale name inputs. */
const LOCALE_LABELS: Record<CategoryLocale, string> = {
    fr: 'Français',
    ar: 'العربية',
    en: 'English',
};

/** Compact tags for the inline translation audit strip. */
const LOCALE_SHORT: Record<CategoryLocale, string> = {
    fr: 'FR',
    ar: 'AR',
    en: 'EN',
};

const EMPTY_NAME: Record<CategoryLocale, string> = { fr: '', ar: '', en: '' };

const INPUT_CLASS =
    'w-full border border-ink/10 bg-cream/5 px-3 py-2 text-sm text-ink transition-colors placeholder:text-ink/20 focus:border-terracotta focus:outline-none';

const LABEL_CLASS =
    'mb-1.5 block text-[9px] font-bold tracking-widest text-ink/40 uppercase';

const isComplete = (cat: Category, locales: CategoryLocale[]) =>
    locales.every((loc) => cat.name?.[loc]?.trim());

// ── Translation audit strip ──────────────────────────────────────────
// All three locales are required server-side, so surfacing them inline
// lets an admin spot a missing or wrong translation without opening the
// edit form.
const LocaleStrip = ({
    cat,
    locales,
}: {
    cat: Category;
    locales: CategoryLocale[];
}) => (
    <div className="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1">
        {locales.map((loc) => {
            const value = cat.name?.[loc]?.trim();

            return (
                <span
                    key={loc}
                    className="inline-flex items-center gap-1.5 text-[11px]"
                >
                    <span className="text-[9px] font-bold tracking-widest text-ink/25">
                        {LOCALE_SHORT[loc]}
                    </span>
                    {value ? (
                        <span
                            className="text-ink-soft"
                            dir={loc === 'ar' ? 'rtl' : 'ltr'}
                        >
                            {value}
                        </span>
                    ) : (
                        <span className="inline-flex items-center gap-1 font-medium text-terracotta">
                            <TriangleAlert className="h-3 w-3" />
                            manquant
                        </span>
                    )}
                </span>
            );
        })}
    </div>
);

const CountPill = ({ value, label }: { value: number; label: string }) => (
    <div className="text-center">
        <p
            className={`text-sm font-bold ${value > 0 ? 'text-ink' : 'text-ink/25'}`}
        >
            {value}
        </p>
        <p className="text-[9px] tracking-widest text-ink-soft uppercase">
            {label}
        </p>
    </div>
);

// ── One node in the tree ─────────────────────────────────────────────
interface RowProps {
    cat: CategoryWithCounts;
    locales: CategoryLocale[];
    depth: number;
    childCount: number;
    collapsed: boolean;
    onToggle: () => void;
    onEdit: () => void;
    onDelete: () => void;
}

const CategoryRow = ({
    cat,
    locales,
    depth,
    childCount,
    collapsed,
    onToggle,
    onEdit,
    onDelete,
}: RowProps) => {
    const locked = cat.products_count > 0 || cat.stores_count > 0;

    return (
        <div
            className="flex items-center gap-4 px-6 py-4 transition-colors hover:bg-cream/20"
            style={{ paddingInlineStart: `${1.5 + depth * 2}rem` }}
        >
            {/* Disclosure — only roots with children get a control, but the
                slot is always reserved so every row stays aligned. */}
            <div className="w-4 shrink-0">
                {childCount > 0 && (
                    <button
                        onClick={onToggle}
                        aria-label={collapsed ? 'Expand' : 'Collapse'}
                        aria-expanded={!collapsed}
                        className="text-ink-soft transition-colors hover:text-ink"
                    >
                        <ChevronRight
                            className={`h-4 w-4 transition-transform ${collapsed ? '' : 'rotate-90'}`}
                        />
                    </button>
                )}
            </div>

            <div className="flex h-9 w-9 shrink-0 items-center justify-center bg-cream/60 text-base">
                {cat.icon || <Layers className="h-4 w-4 text-ink/25" />}
            </div>

            <div className="min-w-0 flex-1">
                <div className="flex flex-wrap items-center gap-2">
                    <p
                        className={`text-sm ${depth === 0 ? 'font-semibold text-ink' : 'font-medium text-ink/80'}`}
                    >
                        {cat.translated_name}
                    </p>
                    {depth === 0 && childCount > 0 && (
                        <span className="bg-ink/[0.04] px-1.5 py-0.5 text-[9px] font-bold tracking-widest text-ink-soft uppercase">
                            {childCount}{' '}
                            {childCount === 1 ? 'enfant' : 'enfants'}
                        </span>
                    )}
                    {!isComplete(cat, locales) && (
                        <span className="bg-terracotta/10 px-1.5 py-0.5 text-[9px] font-bold tracking-widest text-terracotta uppercase">
                            incomplet
                        </span>
                    )}
                </div>
                <p className="mt-0.5 font-mono text-[10px] tracking-wider text-ink/30">
                    /{cat.slug}
                </p>
                <LocaleStrip cat={cat} locales={locales} />
            </div>

            <div className="hidden shrink-0 gap-5 sm:flex">
                <CountPill value={cat.products_count} label="Items" />
                <CountPill value={cat.stores_count} label="Shops" />
            </div>

            <div className="flex shrink-0 items-center gap-1">
                <button
                    onClick={onEdit}
                    aria-label={`Edit ${cat.translated_name}`}
                    className="p-2 text-ink-soft transition-colors hover:bg-ink hover:text-cream"
                >
                    <Edit3 className="h-3.5 w-3.5" />
                </button>
                <button
                    onClick={onDelete}
                    disabled={locked}
                    aria-label={`Delete ${cat.translated_name}`}
                    title={
                        locked ? 'Has products or stores attached' : undefined
                    }
                    className="p-2 text-ink-soft transition-colors hover:bg-terracotta hover:text-cream disabled:opacity-25 disabled:hover:bg-transparent disabled:hover:text-ink-soft"
                >
                    <Trash2 className="h-3.5 w-3.5" />
                </button>
            </div>
        </div>
    );
};

const Index = ({
    categories,
    parents,
    locales,
}: PageProps & {
    categories: CategoryWithCounts[];
    parents: Category[];
    locales: CategoryLocale[];
}) => {
    const [editingId, setEditingId] = useState<number | null>(null);
    const [collapsed, setCollapsed] = useState<Set<number>>(new Set());
    const [query, setQuery] = useState('');
    // The dialog's open flag is kept separate from its subject: Radix keeps
    // the content mounted through the close animation, so clearing the
    // category on close would flash an empty name in the title.
    const [deleteTarget, setDeleteTarget] = useState<CategoryWithCounts | null>(
        null,
    );
    const [deleteOpen, setDeleteOpen] = useState(false);

    // ── Create form ───────────────────────────────────────────
    const createForm = useForm({
        name: { ...EMPTY_NAME },
        parent_id: '' as string | number,
        icon: '',
    });

    const handleCreate = () => {
        createForm.post(route('admin.categories.store'), {
            onSuccess: () => createForm.reset(),
        });
    };

    // ── Edit form ─────────────────────────────────────────────
    const editForm = useForm({
        name: { ...EMPTY_NAME },
        parent_id: '' as string | number,
        icon: '',
    });

    const startEdit = (cat: Category) => {
        setEditingId(cat.id);
        editForm.clearErrors();
        editForm.setData({
            // Spread over the empty shape so a locale missing from older rows
            // still renders a (blank) input instead of an uncontrolled one.
            name: { ...EMPTY_NAME, ...cat.name },
            parent_id: cat.parent_id ?? '',
            icon: cat.icon ?? '',
        });
    };

    const handleUpdate = (id: number) => {
        editForm.patch(route('admin.categories.update', id), {
            onSuccess: () => setEditingId(null),
        });
    };

    const askDelete = (cat: CategoryWithCounts) => {
        setDeleteTarget(cat);
        setDeleteOpen(true);
    };

    const confirmDelete = () => {
        if (!deleteTarget) {
            return;
        }

        router.delete(route('admin.categories.destroy', deleteTarget.id), {
            onFinish: () => setDeleteOpen(false),
        });
    };

    const toggle = (id: number) =>
        setCollapsed((prev) => {
            const next = new Set(prev);

            if (next.has(id)) {
                next.delete(id);
            } else {
                next.add(id);
            }

            return next;
        });

    // ── Tree assembly ─────────────────────────────────────────
    const roots = categories.filter((c) => !c.parent_id);
    const childrenOf = (id: number) =>
        categories.filter((c) => c.parent_id === id);

    // A child whose parent was deleted would otherwise vanish from the page
    // entirely, so it gets surfaced in its own group rather than dropped.
    const orphans = categories.filter(
        (c) => c.parent_id && !categories.some((p) => p.id === c.parent_id),
    );

    const q = query.trim().toLowerCase();
    const matches = (c: CategoryWithCounts) =>
        !q ||
        c.slug.toLowerCase().includes(q) ||
        locales.some((loc) => c.name?.[loc]?.toLowerCase().includes(q));

    // Searching keeps a parent visible when only one of its children hits, so
    // the match never loses its hierarchical context.
    const visibleRoots = roots
        .map((root) => {
            const kids = childrenOf(root.id);
            const rootHit = matches(root);

            return {
                root,
                kids: rootHit ? kids : kids.filter(matches),
                rootHit,
            };
        })
        .filter(({ kids, rootHit }) => rootHit || kids.length > 0);

    const visibleOrphans = orphans.filter(matches);
    const incompleteCount = categories.filter(
        (c) => !isComplete(c, locales),
    ).length;

    const stats = [
        { label: 'Total', value: categories.length },
        { label: 'Racines', value: roots.length },
        { label: 'Sous-catégories', value: categories.length - roots.length },
        { label: 'Incomplètes', value: incompleteCount, warn: true },
    ];

    // ── Inline edit form, rendered in place of a row ───────────
    const renderEditor = (cat: CategoryWithCounts, depth: number) => (
        <div
            className="border-y border-ink/5 bg-cream/30 px-6 py-5"
            style={{ paddingInlineStart: `${1.5 + depth * 2}rem` }}
        >
            <div className="grid gap-4 sm:grid-cols-3">
                {locales.map((loc) => (
                    <div key={loc}>
                        <label className={LABEL_CLASS}>
                            {LOCALE_LABELS[loc]}
                        </label>
                        <input
                            dir={loc === 'ar' ? 'rtl' : 'ltr'}
                            value={editForm.data.name[loc] ?? ''}
                            onChange={(e) =>
                                editForm.setData('name', {
                                    ...editForm.data.name,
                                    [loc]: e.target.value,
                                })
                            }
                            className={INPUT_CLASS}
                        />
                        {editForm.errors[
                            `name.${loc}` as keyof typeof editForm.errors
                        ] && (
                            <p className="mt-1 text-[10px] tracking-wider text-terracotta uppercase">
                                {
                                    editForm.errors[
                                        `name.${loc}` as keyof typeof editForm.errors
                                    ]
                                }
                            </p>
                        )}
                    </div>
                ))}
            </div>

            <div className="mt-4 grid gap-4 sm:grid-cols-3">
                <div>
                    <label className={LABEL_CLASS}>Parent</label>
                    <select
                        value={editForm.data.parent_id}
                        onChange={(e) =>
                            editForm.setData('parent_id', e.target.value)
                        }
                        className={INPUT_CLASS}
                    >
                        <option value="">Racine (aucun parent)</option>
                        {parents
                            .filter((p) => p.id !== cat.id)
                            .map((p) => (
                                <option key={p.id} value={p.id}>
                                    {p.translated_name}
                                </option>
                            ))}
                    </select>
                    {editForm.errors.parent_id && (
                        <p className="mt-1 text-[10px] tracking-wider text-terracotta uppercase">
                            {editForm.errors.parent_id}
                        </p>
                    )}
                </div>

                <div>
                    <label className={LABEL_CLASS}>Icône</label>
                    <input
                        value={editForm.data.icon}
                        onChange={(e) =>
                            editForm.setData('icon', e.target.value)
                        }
                        placeholder="🧶"
                        className={INPUT_CLASS}
                    />
                </div>

                <div className="flex items-end gap-2">
                    <button
                        onClick={() => handleUpdate(cat.id)}
                        disabled={editForm.processing}
                        className="bg-ink px-5 py-2 text-xs font-bold tracking-widest text-cream uppercase transition-colors hover:bg-terracotta disabled:opacity-50"
                    >
                        {editForm.processing ? 'Enregistre…' : 'Enregistrer'}
                    </button>
                    <button
                        onClick={() => setEditingId(null)}
                        className="px-3 py-2 text-xs font-medium text-ink-soft transition-colors hover:text-ink"
                    >
                        Annuler
                    </button>
                </div>
            </div>

            <p className="mt-4 text-[10px] tracking-wider text-ink/30 uppercase">
                Le slug /{cat.slug} reste inchangé — le modifier casserait les
                liens existants.
            </p>
        </div>
    );

    const renderNode = (cat: CategoryWithCounts, depth: number) =>
        editingId === cat.id ? (
            renderEditor(cat, depth)
        ) : (
            <CategoryRow
                cat={cat}
                locales={locales}
                depth={depth}
                childCount={childrenOf(cat.id).length}
                collapsed={collapsed.has(cat.id)}
                onToggle={() => toggle(cat.id)}
                onEdit={() => startEdit(cat)}
                onDelete={() => askDelete(cat)}
            />
        );

    return (
        <div className="min-h-screen bg-cream/30 p-6 md:p-10">
            <Head title="Manage Categories" />

            <div className="mx-auto max-w-7xl">
                <header className="mb-8">
                    <p className="font-sans text-xs font-medium tracking-[0.2em] text-ink-soft uppercase">
                        Inventory Architecture
                    </p>
                    <h1 className="mt-2 h-editorial text-4xl text-ink md:text-5xl">
                        Categories & <span className="italic">Taxonomy</span>
                    </h1>
                </header>

                {/* ── Compact stats ── */}
                <div className="mb-10 grid grid-cols-2 gap-px border border-ink/5 bg-ink/5 md:grid-cols-4">
                    {stats.map((s) => (
                        <div key={s.label} className="bg-white px-6 py-5">
                            <p className="text-[10px] font-bold tracking-widest text-ink/40 uppercase">
                                {s.label}
                            </p>
                            <p
                                className={`mt-1 h-editorial text-3xl ${
                                    s.warn && s.value > 0
                                        ? 'text-terracotta'
                                        : 'text-ink'
                                }`}
                            >
                                {s.value}
                            </p>
                        </div>
                    ))}
                </div>

                <div className="grid grid-cols-12 items-start gap-10">
                    {/* ── Tree ── */}
                    <div className="col-span-12 lg:col-span-8">
                        <div className="mb-4 flex items-center gap-3">
                            <div className="relative flex-1">
                                <Search className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-ink/25" />
                                <input
                                    value={query}
                                    onChange={(e) => setQuery(e.target.value)}
                                    placeholder="Rechercher une catégorie…"
                                    className="w-full border border-ink/10 bg-white py-2.5 pr-9 pl-9 text-sm text-ink placeholder:text-ink/25 focus:border-terracotta focus:outline-none"
                                />
                                {query && (
                                    <button
                                        onClick={() => setQuery('')}
                                        aria-label="Clear search"
                                        className="absolute top-1/2 right-3 -translate-y-1/2 text-ink/30 hover:text-ink"
                                    >
                                        <X className="h-3.5 w-3.5" />
                                    </button>
                                )}
                            </div>
                            <button
                                onClick={() =>
                                    setCollapsed((prev) =>
                                        prev.size > 0
                                            ? new Set()
                                            : new Set(roots.map((r) => r.id)),
                                    )
                                }
                                className="shrink-0 border border-ink/10 bg-white px-4 py-2.5 text-[10px] font-bold tracking-widest text-ink-soft uppercase transition-colors hover:border-ink/20 hover:text-ink"
                            >
                                {collapsed.size > 0
                                    ? 'Tout ouvrir'
                                    : 'Tout fermer'}
                            </button>
                        </div>

                        <div className="border border-ink/5 bg-white shadow-sm">
                            {visibleRoots.length === 0 &&
                            visibleOrphans.length === 0 ? (
                                <div className="flex flex-col items-center gap-3 px-6 py-20 text-center">
                                    <FolderTree className="h-8 w-8 text-ink/15" />
                                    <p className="text-sm text-ink-soft">
                                        {q
                                            ? `Aucune catégorie ne correspond à « ${query} ».`
                                            : 'Aucune catégorie pour le moment.'}
                                    </p>
                                </div>
                            ) : (
                                <div className="divide-y divide-ink/5">
                                    {visibleRoots.map(({ root, kids }) => (
                                        <div key={root.id}>
                                            {renderNode(root, 0)}

                                            {!collapsed.has(root.id) &&
                                                kids.length > 0 && (
                                                    <div className="divide-y divide-ink/5 border-t border-ink/5 bg-cream/10">
                                                        {kids.map((kid) => (
                                                            <div key={kid.id}>
                                                                {renderNode(
                                                                    kid,
                                                                    1,
                                                                )}
                                                            </div>
                                                        ))}
                                                    </div>
                                                )}
                                        </div>
                                    ))}

                                    {visibleOrphans.length > 0 && (
                                        <div>
                                            <p className="bg-terracotta/5 px-6 py-2 text-[10px] font-bold tracking-widest text-terracotta uppercase">
                                                Parent introuvable
                                            </p>
                                            <div className="divide-y divide-ink/5">
                                                {visibleOrphans.map((o) => (
                                                    <div key={o.id}>
                                                        {renderNode(o, 1)}
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    )}
                                </div>
                            )}
                        </div>
                    </div>

                    {/* ── Create Sidebar ── */}
                    <div className="col-span-12 lg:col-span-4">
                        <div className="sticky top-10 border border-ink/5 bg-white p-8 shadow-sm">
                            <h3 className="mb-6 h-editorial text-2xl text-ink">
                                New <span className="italic">Category</span>
                            </h3>
                            <div className="space-y-6">
                                <div className="space-y-4">
                                    <label className="block text-[10px] font-bold tracking-widest text-ink/40 uppercase">
                                        Nominal Label — all languages required
                                    </label>
                                    {locales.map((loc) => (
                                        <div key={loc}>
                                            <span className="mb-1 block text-[9px] font-semibold tracking-widest text-ink/30 uppercase">
                                                {LOCALE_LABELS[loc]}
                                            </span>
                                            <input
                                                dir={
                                                    loc === 'ar' ? 'rtl' : 'ltr'
                                                }
                                                value={
                                                    createForm.data.name[loc] ??
                                                    ''
                                                }
                                                onChange={(e) =>
                                                    createForm.setData('name', {
                                                        ...createForm.data.name,
                                                        [loc]: e.target.value,
                                                    })
                                                }
                                                placeholder={
                                                    loc === 'ar'
                                                        ? 'مثال: سجاد يدوي'
                                                        : 'e.g. Handmade Carpets'
                                                }
                                                className="w-full border border-ink/10 bg-cream/5 px-4 py-3 text-sm placeholder:text-ink/20 focus:border-terracotta focus:outline-none"
                                            />
                                            {/* Laravel reports per-locale errors as name.fr, name.ar, ... */}
                                            {createForm.errors[
                                                `name.${loc}` as keyof typeof createForm.errors
                                            ] && (
                                                <p className="mt-1 text-[10px] tracking-wider text-terracotta uppercase">
                                                    {
                                                        createForm.errors[
                                                            `name.${loc}` as keyof typeof createForm.errors
                                                        ]
                                                    }
                                                </p>
                                            )}
                                        </div>
                                    ))}
                                </div>

                                <div>
                                    <label className="mb-2 block text-[10px] font-bold tracking-widest text-ink/40 uppercase">
                                        Hierarchy Placement
                                    </label>
                                    <select
                                        value={createForm.data.parent_id}
                                        onChange={(e) =>
                                            createForm.setData(
                                                'parent_id',
                                                e.target.value,
                                            )
                                        }
                                        className="w-full border border-ink/10 bg-cream/5 px-4 py-3 text-sm focus:border-terracotta focus:outline-none"
                                    >
                                        <option value="">
                                            Independent (Root)
                                        </option>
                                        {parents.map((p) => (
                                            <option key={p.id} value={p.id}>
                                                {p.translated_name}
                                            </option>
                                        ))}
                                    </select>
                                </div>

                                <div>
                                    <label className="mb-2 block text-[10px] font-bold tracking-widest text-ink/40 uppercase">
                                        Visual Icon
                                    </label>
                                    <input
                                        value={createForm.data.icon}
                                        onChange={(e) =>
                                            createForm.setData(
                                                'icon',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="🧶"
                                        className="w-full border border-ink/10 bg-cream/5 px-4 py-3 text-sm placeholder:text-ink/20 focus:border-terracotta focus:outline-none"
                                    />
                                </div>

                                <button
                                    onClick={handleCreate}
                                    disabled={
                                        createForm.processing ||
                                        // Every locale must be filled before the
                                        // server will accept the category.
                                        !locales.every((loc) =>
                                            createForm.data.name[loc]?.trim(),
                                        )
                                    }
                                    className="group flex w-full items-center justify-center gap-2 bg-ink py-4 text-xs font-bold tracking-widest text-cream uppercase transition-colors hover:bg-terracotta disabled:opacity-50"
                                >
                                    <Plus className="h-4 w-4" />
                                    {createForm.processing
                                        ? 'Defining...'
                                        : 'Define Category'}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* ── Delete confirmation ── */}
            <AlertDialog open={deleteOpen} onOpenChange={setDeleteOpen}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            Supprimer «&nbsp;{deleteTarget?.translated_name}
                            &nbsp;» ?
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            Cette action est irréversible.
                            {childrenOf(deleteTarget?.id ?? -1).length > 0 && (
                                <>
                                    {' '}
                                    Cette catégorie a{' '}
                                    {childrenOf(deleteTarget!.id).length}{' '}
                                    sous-catégorie(s) qui se retrouveront sans
                                    parent.
                                </>
                            )}
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Annuler</AlertDialogCancel>
                        <AlertDialogAction
                            onClick={confirmDelete}
                            className="bg-terracotta text-cream hover:bg-terracotta-deep"
                        >
                            Supprimer
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </div>
    );
};

Index.layout = (page: ReactNode) => <AdminLayout children={page} />;
export default Index;
