import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    ChevronRight,
    Edit3,
    FolderTree,
    GripVertical,
    Layers,
    Plus,
    Search,
    Share2,
    Trash2,
    TriangleAlert,
} from 'lucide-react';
import type { DragEvent, ReactNode } from 'react';
import { Fragment, useState } from 'react';
import SingleImageInput from '@/components/admin/single-image-input';
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
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useTranslation } from '@/hooks/use-translation';
import AdminLayout from '@/layouts/admin/layout';
import { frenchName } from '@/lib/category';
import type { Category, CategoryLocale, PageProps } from '@/types';

interface CategoryWithCounts extends Category {
    products_count: number;
    stores_count: number;
    /** Always sent by CategoryService::listForAdmin; empty means a root. */
    parent_ids: number[];
    /** Its subcategories, in the order the storefront shows them. */
    child_ids: number[];
}

/**
 * A list that can be reordered: one parent's subcategories, or `null` for the
 * main categories themselves.
 */
type ListId = number | null;

/** The same list with `ids` in their new order. Pure: Inertia replays it. */
const withOrder = (
    rows: CategoryWithCounts[],
    list: ListId,
    ids: number[],
): CategoryWithCounts[] =>
    list === null
        ? [...rows].sort((a, b) => ids.indexOf(a.id) - ids.indexOf(b.id))
        : rows.map((row) =>
              row.id === list ? { ...row, child_ids: ids } : row,
          );

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

// Matches the field styling every other admin form (see
// admin/users/create.tsx) uses, so this dialog doesn't read as a different
// tool from the rest of the panel.
const INPUT =
    'w-full rounded-lg border border-border bg-card px-3.5 py-2.5 text-sm text-ink shadow-sm transition placeholder:text-ink-soft/70 focus:border-ink focus:outline-none focus:ring-2 focus:ring-ink/10';
const LABEL = 'mb-1.5 block text-sm font-medium text-ink';
const ERROR = 'mt-1.5 text-xs text-red-600';

/** A labelled field with its validation message. */
function Field({
    label,
    error,
    children,
}: {
    label: string;
    error?: string;
    children: ReactNode;
}) {
    return (
        <div>
            <label className={LABEL}>{label}</label>
            {children}
            {error && <p className={ERROR}>{error}</p>}
        </div>
    );
}

const isComplete = (cat: Category, locales: CategoryLocale[]) =>
    locales.every((loc) => cat.name?.[loc]?.trim());

/** What the picture is for depends on where the category sits in the tree. */
const imageHint = (parentIds: number[]) =>
    parentIds.length === 0
        ? 'Affichée sur la page d’accueil.'
        : 'Vignette de la sous-catégorie.';

/**
 * The parent picker. A checkbox list rather than a `<select multiple>`: a
 * subcategory can be filed under several crafts at once, and a native
 * multi-select hides that behind a ctrl-click nobody discovers.
 *
 * Nothing ticked means the category is itself a main category — that is the
 * same statement as the old "Racine (aucun parent)" option, just made by
 * leaving the list alone.
 */
function ParentPicker({
    options,
    selected,
    onChange,
}: {
    options: Category[];
    selected: number[];
    onChange: (next: number[]) => void;
}) {
    const toggle = (id: number) =>
        onChange(
            selected.includes(id)
                ? selected.filter((v) => v !== id)
                : [...selected, id],
        );

    return (
        <div className="max-h-56 overflow-y-auto rounded-lg border border-border bg-card p-1 shadow-sm">
            {options.length === 0 ? (
                <p className="px-2.5 py-2 text-sm text-ink-soft">
                    Aucune autre catégorie principale.
                </p>
            ) : (
                options.map((p) => (
                    <label
                        key={p.id}
                        className="flex cursor-pointer items-center gap-2.5 rounded-md px-2.5 py-2 text-sm text-ink transition hover:bg-muted/50"
                    >
                        <input
                            type="checkbox"
                            checked={selected.includes(p.id)}
                            onChange={() => toggle(p.id)}
                            className="h-4 w-4 shrink-0 rounded border-border accent-ink"
                        />
                        {frenchName(p)}
                    </label>
                ))
            )}
        </div>
    );
}

/** A count that opens the matching list; a zero has nothing to open. */
function CountLink({
    count,
    href,
    label,
}: {
    count: number;
    href: string;
    label: string;
}) {
    if (count === 0) {
        return <span className="text-ink-soft/60">0</span>;
    }

    return (
        <Link
            href={href}
            title={label}
            aria-label={`${label} (${count})`}
            className="font-medium text-ink underline decoration-ink/25 underline-offset-4 transition hover:decoration-ink"
        >
            {count}
        </Link>
    );
}

// ── One row in the tree ──────────────────────────────────────────────
interface RowProps {
    cat: CategoryWithCounts;
    locales: CategoryLocale[];
    depth: number;
    childCount: number;
    collapsed: boolean;
    onToggle: () => void;
    onEdit: () => void;
    onDelete: () => void;
    /** False while a filter hides part of the list it would reorder. */
    reorderable: boolean;
    canMoveUp: boolean;
    canMoveDown: boolean;
    onMoveUp: () => void;
    onMoveDown: () => void;
    onDragStart: (event: DragEvent<HTMLElement>) => void;
    onDragEnd: () => void;
    onDragOver: (event: DragEvent<HTMLTableRowElement>) => void;
    /** Dimmed: this row, or the main category it belongs to, is being moved. */
    dragging: boolean;
    /** Where the drop line sits on this row, if the drop would land here. */
    dropEdge: 'top' | 'bottom' | null;
}

/** Why the arrows are unavailable, shown as their tooltip. */
const FILTERED_HINT = 'Effacez les filtres pour réordonner';

const CategoryRow = ({
    cat,
    locales,
    depth,
    childCount,
    collapsed,
    onToggle,
    onEdit,
    onDelete,
    reorderable,
    canMoveUp,
    canMoveDown,
    onMoveUp,
    onMoveDown,
    onDragStart,
    onDragEnd,
    onDragOver,
    dragging,
    dropEdge,
}: RowProps) => {
    const locked = cat.products_count > 0 || cat.stores_count > 0;
    const incomplete = !isComplete(cat, locales);

    return (
        <tr
            onDragOver={onDragOver}
            className={`transition-colors hover:bg-muted/30 ${dragging ? 'opacity-40' : ''} ${
                dropEdge === 'top'
                    ? 'shadow-[inset_0_2px_0_0_var(--color-ink)]'
                    : dropEdge === 'bottom'
                      ? 'shadow-[inset_0_-2px_0_0_var(--color-ink)]'
                      : ''
            }`}
        >
            <td
                className="px-5 py-3"
                style={{ paddingInlineStart: `${1.25 + depth * 1.5}rem` }}
            >
                <div className="flex items-center gap-3">
                    {/* Only the handle is draggable, so the row's text can
                        still be selected and its links still work. The slot
                        stays when reordering is off, to hold the alignment. */}
                    <div className="w-4 shrink-0">
                        {reorderable && (
                            <span
                                draggable
                                onDragStart={onDragStart}
                                onDragEnd={onDragEnd}
                                title="Glisser pour réordonner"
                                className="block cursor-grab text-ink-soft/50 transition hover:text-ink active:cursor-grabbing"
                            >
                                <GripVertical className="h-4 w-4" />
                            </span>
                        )}
                    </div>

                    {/* Disclosure — only roots with children get a control,
                        but the slot is always reserved so every row stays
                        aligned. */}
                    <div className="w-4 shrink-0">
                        {childCount > 0 && (
                            <button
                                type="button"
                                onClick={onToggle}
                                aria-label={
                                    collapsed ? 'Développer' : 'Réduire'
                                }
                                aria-expanded={!collapsed}
                                className="text-ink-soft transition hover:text-ink"
                            >
                                <ChevronRight
                                    className={`h-4 w-4 transition-transform ${collapsed ? '' : 'rotate-90'}`}
                                />
                            </button>
                        )}
                    </div>

                    <div className="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-muted">
                        {cat.image_url ? (
                            <img
                                src={cat.image_url}
                                alt=""
                                className="h-full w-full object-cover"
                            />
                        ) : (
                            <Layers className="h-4 w-4 text-ink-soft/40" />
                        )}
                    </div>

                    <div className="min-w-0">
                        <div className="flex flex-wrap items-center gap-2">
                            <p
                                className={`text-sm ${depth === 0 ? 'font-semibold text-ink' : 'font-medium text-ink'}`}
                            >
                                {frenchName(cat)}
                            </p>
                            {depth === 0 && childCount > 0 && (
                                <span className="rounded-full bg-muted px-1.5 py-0.5 text-[10px] font-medium text-ink-soft">
                                    {childCount} sous-catégorie
                                    {childCount > 1 ? 's' : ''}
                                </span>
                            )}
                            {/* This row is repeated under each of its parents,
                                so say so — otherwise the same name showing up
                                twice in the tree reads as a duplicate. */}
                            {depth > 0 && cat.parent_ids.length > 1 && (
                                <span className="inline-flex items-center gap-1 rounded-full bg-muted px-1.5 py-0.5 text-[10px] font-medium text-ink-soft">
                                    <Share2 className="h-3 w-3" />
                                    {cat.parent_ids.length} parents
                                </span>
                            )}
                            {incomplete && (
                                <span className="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-medium text-amber-700">
                                    <TriangleAlert className="h-3 w-3" />
                                    Incomplet
                                </span>
                            )}
                        </div>
                        <p className="mt-0.5 font-mono text-xs text-ink-soft/60">
                            /{cat.slug}
                        </p>
                        <div className="mt-1 flex flex-wrap items-center gap-x-3 gap-y-0.5">
                            {locales.map((loc) => {
                                const value = cat.name?.[loc]?.trim();

                                return (
                                    <span
                                        key={loc}
                                        className="inline-flex items-center gap-1 text-[11px]"
                                    >
                                        <span className="font-semibold text-ink-soft/50">
                                            {LOCALE_SHORT[loc]}
                                        </span>
                                        {value ? (
                                            <span
                                                className="text-ink-soft"
                                                dir={
                                                    loc === 'ar' ? 'rtl' : 'ltr'
                                                }
                                            >
                                                {value}
                                            </span>
                                        ) : (
                                            <span className="font-medium text-rose-600">
                                                manquant
                                            </span>
                                        )}
                                    </span>
                                );
                            })}
                        </div>
                    </div>
                </div>
            </td>

            <td className="px-5 py-3 text-right text-sm text-ink">
                <CountLink
                    count={cat.products_count}
                    href={route('admin.products.index', {
                        category_id: cat.id,
                    })}
                    label={`Voir les produits de ${frenchName(cat)}`}
                />
            </td>
            <td className="px-5 py-3 text-right text-sm text-ink">
                <CountLink
                    count={cat.stores_count}
                    href={route('admin.stores.index', {
                        category_id: cat.id,
                    })}
                    label={`Voir les ateliers de ${frenchName(cat)}`}
                />
            </td>

            <td className="px-5 py-3">
                <div className="flex items-center justify-end gap-1">
                    {/* Dragging needs a mouse; these also serve touch screens
                        and the keyboard. */}
                    <Button
                        variant="ghost"
                        size="icon"
                        className="h-8 w-8"
                        onClick={onMoveUp}
                        disabled={!reorderable || !canMoveUp}
                        title={reorderable ? undefined : FILTERED_HINT}
                        aria-label={`Monter ${frenchName(cat)}`}
                    >
                        <ArrowUp className="h-3.5 w-3.5" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        className="h-8 w-8"
                        onClick={onMoveDown}
                        disabled={!reorderable || !canMoveDown}
                        title={reorderable ? undefined : FILTERED_HINT}
                        aria-label={`Descendre ${frenchName(cat)}`}
                    >
                        <ArrowDown className="h-3.5 w-3.5" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        className="h-8 w-8"
                        onClick={onEdit}
                        aria-label={`Modifier ${frenchName(cat)}`}
                    >
                        <Edit3 className="h-3.5 w-3.5" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        className="h-8 w-8 text-ink-soft hover:bg-terracotta hover:text-white"
                        onClick={onDelete}
                        disabled={locked}
                        title={
                            locked
                                ? 'Des produits ou boutiques y sont rattachés'
                                : undefined
                        }
                        aria-label={`Supprimer ${frenchName(cat)}`}
                    >
                        <Trash2 className="h-3.5 w-3.5" />
                    </Button>
                </div>
            </td>
        </tr>
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
    const { t } = useTranslation();
    const [collapsed, setCollapsed] = useState<Set<number>>(new Set());
    const [query, setQuery] = useState('');
    const [incompleteOnly, setIncompleteOnly] = useState(false);

    // ── Create dialog ────────────────────────────────────────
    const [createOpen, setCreateOpen] = useState(false);
    const createForm = useForm({
        name: { ...EMPTY_NAME },
        parent_ids: [] as number[],
        hs_code: '',
        image: null as File | null,
    });

    const openCreate = () => {
        createForm.reset();
        createForm.clearErrors();
        setCreateOpen(true);
    };

    const handleCreate = () => {
        createForm.post(route('admin.categories.store'), {
            forceFormData: true,
            onSuccess: () => {
                createForm.reset();
                setCreateOpen(false);
            },
        });
    };

    // ── Edit dialog ───────────────────────────────────────────
    const [editOpen, setEditOpen] = useState(false);
    const [editTarget, setEditTarget] = useState<CategoryWithCounts | null>(
        null,
    );
    // Sent as a POST with `_method` spoofing: PHP does not parse multipart
    // bodies on a real PATCH, so the image would never arrive.
    const editForm = useForm({
        _method: 'patch',
        name: { ...EMPTY_NAME },
        parent_ids: [] as number[],
        hs_code: '',
        image: null as File | null,
        remove_image: false,
    });

    const openEdit = (cat: CategoryWithCounts) => {
        setEditTarget(cat);
        editForm.clearErrors();
        editForm.setData({
            _method: 'patch',
            // Spread over the empty shape so a locale missing from older
            // rows still renders a (blank) input instead of an uncontrolled
            // one.
            name: { ...EMPTY_NAME, ...cat.name },
            parent_ids: cat.parent_ids,
            hs_code: cat.hs_code ?? '',
            image: null,
            remove_image: false,
        });
        setEditOpen(true);
    };

    const handleUpdate = () => {
        if (!editTarget) {
            return;
        }

        editForm.post(route('admin.categories.update', editTarget.id), {
            forceFormData: true,
            onSuccess: () => setEditOpen(false),
        });
    };

    // ── Delete confirmation ───────────────────────────────────
    // The dialog's open flag is kept separate from its subject: Radix keeps
    // the content mounted through the close animation, so clearing the
    // category on close would flash an empty name in the title.
    const [deleteTarget, setDeleteTarget] = useState<CategoryWithCounts | null>(
        null,
    );
    const [deleteOpen, setDeleteOpen] = useState(false);

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
    // A subcategory filed under several crafts is listed under each of them,
    // so one row here can produce several rows in the table.
    const roots = categories.filter((c) => c.parent_ids.length === 0);
    const childrenOf = (id: number) =>
        (categories.find((c) => c.id === id)?.child_ids ?? []).flatMap(
            (childId) => categories.filter((c) => c.id === childId),
        );

    const incompleteCount = categories.filter(
        (c) => !isComplete(c, locales),
    ).length;

    const q = query.trim().toLowerCase();
    const matches = (c: CategoryWithCounts) =>
        (!incompleteOnly || !isComplete(c, locales)) &&
        (!q ||
            c.slug.toLowerCase().includes(q) ||
            locales.some((loc) => c.name?.[loc]?.toLowerCase().includes(q)));

    // Filtering keeps a parent visible when only one of its children hits, so
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

    const isEmpty = visibleRoots.length === 0;

    // ── Reordering ────────────────────────────────────────────
    // Off while filtering: only part of a list is on screen then, and saving
    // that part would push everything hidden to the end of it.
    const reorderable = !q && !incompleteOnly;

    const [drag, setDrag] = useState<{ list: ListId; id: number } | null>(null);
    const [dropTarget, setDropTarget] = useState<{
        id: number;
        edge: 'before' | 'after';
    } | null>(null);

    const idsIn = (list: ListId) =>
        (list === null ? roots : childrenOf(list)).map((c) => c.id);

    // Optimistic so the row stays where it was put; Inertia puts it back if
    // the save fails. Each save carries the whole list, so a quick run of
    // moves ends on the last one.
    const saveOrder = (list: ListId, ids: number[]) => {
        router
            .optimistic<{ categories: CategoryWithCounts[] }>((props) => ({
                categories: withOrder(props.categories, list, ids),
            }))
            .patch(
                route('admin.categories.reorder'),
                { parent_id: list, ids },
                { preserveScroll: true },
            );
    };

    const moveBy = (list: ListId, id: number, offset: number) => {
        const ids = idsIn(list);
        const to = ids.indexOf(id) + offset;

        ids.splice(ids.indexOf(id), 1);
        ids.splice(to, 0, id);

        saveOrder(list, ids);
    };

    const startDrag = (
        event: DragEvent<HTMLElement>,
        list: ListId,
        id: number,
    ) => {
        const row = event.currentTarget.closest('tr');

        event.dataTransfer.effectAllowed = 'move';
        // Firefox will not start a drag that carries no data.
        event.dataTransfer.setData('text/plain', String(id));

        // Show the whole row under the pointer, not just the small handle.
        if (row) {
            event.dataTransfer.setDragImage(row, 16, 16);
        }

        setDrag({ list, id });
    };

    const endDrag = () => {
        setDrag(null);
        setDropTarget(null);
    };

    /**
     * A row only accepts a drop from its own list, so a subcategory cannot
     * leave its parent this way; that stays the edit dialog's job.
     *
     * `index`/`size` place the row within the block being dropped around: a
     * main category moves with its open subcategories, so over any of those
     * rows the drop lands before or after the whole block, on whichever side
     * of its middle the pointer is.
     */
    const dragOverRow = (
        event: DragEvent<HTMLTableRowElement>,
        list: ListId,
        id: number,
        index = 0,
        size = 1,
    ) => {
        if (!drag || drag.list !== list || drag.id === id) {
            setDropTarget(null);

            return;
        }

        // Without this the browser refuses the drop.
        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';

        const box = event.currentTarget.getBoundingClientRect();
        const within = (event.clientY - box.top) / box.height;
        const edge = (index + within) / size < 0.5 ? 'before' : 'after';

        setDropTarget((current) =>
            current?.id === id && current.edge === edge
                ? current
                : { id, edge },
        );
    };

    const drop = (event: DragEvent<HTMLElement>) => {
        event.preventDefault();

        if (drag && dropTarget) {
            const current = idsIn(drag.list);
            const ids = current.filter((id) => id !== drag.id);
            const at =
                ids.indexOf(dropTarget.id) +
                (dropTarget.edge === 'after' ? 1 : 0);

            ids.splice(at, 0, drag.id);

            // Dropped back where it started: nothing to save.
            if (ids.join() !== current.join()) {
                saveOrder(drag.list, ids);
            }
        }

        endDrag();
    };

    return (
        <div className="flex flex-col gap-6 p-6 md:p-8">
            <Head title={t('titles.admin.categories')} />

            <header className="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight text-ink">
                        Catégories
                    </h1>
                    <p className="mt-1 text-sm text-ink-soft">
                        {categories.length} catégorie
                        {categories.length > 1 ? 's' : ''} · {roots.length}{' '}
                        racine{roots.length > 1 ? 's' : ''} ·{' '}
                        {categories.length - roots.length} sous-catégorie
                        {categories.length - roots.length !== 1 ? 's' : ''}
                    </p>
                </div>
                <button
                    type="button"
                    onClick={openCreate}
                    className="inline-flex items-center gap-2 rounded-lg bg-ink px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-ink/90"
                >
                    <Plus className="h-4 w-4" />
                    Nouvelle catégorie
                </button>
            </header>

            {/* Translation-audit queue — only while there is work to do. */}
            {incompleteCount > 0 && (
                <button
                    type="button"
                    onClick={() => setIncompleteOnly((v) => !v)}
                    className={`flex items-center gap-3 rounded-xl border px-5 py-3.5 text-left transition ${
                        incompleteOnly
                            ? 'border-amber-400 bg-amber-100'
                            : 'border-amber-300/60 bg-amber-50 hover:bg-amber-100'
                    }`}
                >
                    <TriangleAlert className="h-5 w-5 shrink-0 text-amber-600" />
                    <span className="text-sm text-ink">
                        <span className="font-medium">
                            {incompleteCount} catégorie
                            {incompleteCount > 1 ? 's' : ''} avec une traduction
                            manquante
                        </span>{' '}
                        <span className="text-ink-soft">
                            {incompleteOnly
                                ? '— cliquez pour tout afficher'
                                : '.'}
                        </span>
                    </span>
                </button>
            )}

            {/* ── Search & tree controls ──────────────────── */}
            <div className="flex flex-wrap items-center gap-3">
                <div className="relative max-w-md flex-1">
                    <Search className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-ink-soft/50" />
                    <input
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder="Rechercher une catégorie…"
                        className="w-full rounded-lg border border-border bg-card py-2.5 pr-3 pl-9 text-sm text-ink shadow-sm transition placeholder:text-ink-soft/60 focus:border-ink focus:ring-2 focus:ring-ink/10 focus:outline-none"
                    />
                </div>
                <button
                    type="button"
                    onClick={() =>
                        setCollapsed((prev) =>
                            prev.size > 0
                                ? new Set()
                                : new Set(roots.map((r) => r.id)),
                        )
                    }
                    className="shrink-0 rounded-lg border border-border bg-card px-3.5 py-2.5 text-sm font-medium text-ink-soft shadow-sm transition hover:border-ink/30 hover:text-ink"
                >
                    {collapsed.size > 0 ? 'Tout développer' : 'Tout réduire'}
                </button>
            </div>

            <p className="-mt-3 text-xs text-ink-soft">
                {reorderable
                    ? 'Glissez une ligne ou utilisez les flèches : la boutique suit cet ordre. Un clic sur une catégorie principale ouvre sa première sous-catégorie.'
                    : `${FILTERED_HINT}.`}
            </p>

            {/* ── Table ────────────────────────────────────── */}
            <div className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                {isEmpty ? (
                    <div className="flex flex-col items-center gap-3 px-6 py-16 text-center">
                        <FolderTree className="h-8 w-8 text-ink-soft/30" />
                        <p className="text-sm text-ink-soft">
                            {q || incompleteOnly
                                ? 'Aucune catégorie ne correspond à ces filtres.'
                                : 'Aucune catégorie pour le moment.'}
                        </p>
                    </div>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="border-b border-border text-left text-xs font-medium text-ink-soft">
                                <tr>
                                    <th className="px-5 py-3">Catégorie</th>
                                    <th className="px-5 py-3 text-right">
                                        Produits
                                    </th>
                                    <th className="px-5 py-3 text-right">
                                        Boutiques
                                    </th>
                                    <th className="px-5 py-3 text-right">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody
                                className="divide-y divide-border"
                                onDrop={drop}
                                onDragLeave={(event) => {
                                    // Fires between rows too; only clear once
                                    // the pointer has left the table's body.
                                    if (
                                        !event.currentTarget.contains(
                                            event.relatedTarget as Node | null,
                                        )
                                    ) {
                                        setDropTarget(null);
                                    }
                                }}
                            >
                                {visibleRoots.map(
                                    ({ root, kids }, rootIndex) => {
                                        const shownKids = collapsed.has(root.id)
                                            ? []
                                            : kids;
                                        const blockSize = shownKids.length + 1;
                                        const movingRoot =
                                            drag?.list === null &&
                                            drag.id === root.id;
                                        const rootEdge =
                                            drag?.list === null &&
                                            dropTarget?.id === root.id
                                                ? dropTarget.edge
                                                : null;

                                        return (
                                            <Fragment key={root.id}>
                                                <CategoryRow
                                                    cat={root}
                                                    locales={locales}
                                                    depth={0}
                                                    childCount={
                                                        childrenOf(root.id)
                                                            .length
                                                    }
                                                    collapsed={collapsed.has(
                                                        root.id,
                                                    )}
                                                    onToggle={() =>
                                                        toggle(root.id)
                                                    }
                                                    onEdit={() =>
                                                        openEdit(root)
                                                    }
                                                    onDelete={() =>
                                                        askDelete(root)
                                                    }
                                                    reorderable={reorderable}
                                                    canMoveUp={rootIndex > 0}
                                                    canMoveDown={
                                                        rootIndex <
                                                        visibleRoots.length - 1
                                                    }
                                                    onMoveUp={() =>
                                                        moveBy(
                                                            null,
                                                            root.id,
                                                            -1,
                                                        )
                                                    }
                                                    onMoveDown={() =>
                                                        moveBy(null, root.id, 1)
                                                    }
                                                    onDragStart={(event) =>
                                                        startDrag(
                                                            event,
                                                            null,
                                                            root.id,
                                                        )
                                                    }
                                                    onDragEnd={endDrag}
                                                    onDragOver={(event) =>
                                                        dragOverRow(
                                                            event,
                                                            null,
                                                            root.id,
                                                            0,
                                                            blockSize,
                                                        )
                                                    }
                                                    dragging={movingRoot}
                                                    dropEdge={
                                                        rootEdge === 'before'
                                                            ? 'top'
                                                            : rootEdge ===
                                                                    'after' &&
                                                                blockSize === 1
                                                              ? 'bottom'
                                                              : null
                                                    }
                                                />
                                                {shownKids.map(
                                                    (kid, kidIndex) => {
                                                        const kidTargeted =
                                                            drag?.list ===
                                                                root.id &&
                                                            dropTarget?.id ===
                                                                kid.id;

                                                        return (
                                                            <CategoryRow
                                                                // Keyed by the pair: a
                                                                // shared subcategory is
                                                                // rendered once under
                                                                // each parent, so its
                                                                // own id is not unique
                                                                // in the table.
                                                                key={`${root.id}-${kid.id}`}
                                                                cat={kid}
                                                                locales={
                                                                    locales
                                                                }
                                                                depth={1}
                                                                childCount={0}
                                                                collapsed={
                                                                    false
                                                                }
                                                                onToggle={() => {}}
                                                                onEdit={() =>
                                                                    openEdit(
                                                                        kid,
                                                                    )
                                                                }
                                                                onDelete={() =>
                                                                    askDelete(
                                                                        kid,
                                                                    )
                                                                }
                                                                reorderable={
                                                                    reorderable
                                                                }
                                                                canMoveUp={
                                                                    kidIndex > 0
                                                                }
                                                                canMoveDown={
                                                                    kidIndex <
                                                                    shownKids.length -
                                                                        1
                                                                }
                                                                onMoveUp={() =>
                                                                    moveBy(
                                                                        root.id,
                                                                        kid.id,
                                                                        -1,
                                                                    )
                                                                }
                                                                onMoveDown={() =>
                                                                    moveBy(
                                                                        root.id,
                                                                        kid.id,
                                                                        1,
                                                                    )
                                                                }
                                                                onDragStart={(
                                                                    event,
                                                                ) =>
                                                                    startDrag(
                                                                        event,
                                                                        root.id,
                                                                        kid.id,
                                                                    )
                                                                }
                                                                onDragEnd={
                                                                    endDrag
                                                                }
                                                                onDragOver={(
                                                                    event,
                                                                ) =>
                                                                    drag?.list ===
                                                                    null
                                                                        ? dragOverRow(
                                                                              event,
                                                                              null,
                                                                              root.id,
                                                                              kidIndex +
                                                                                  1,
                                                                              blockSize,
                                                                          )
                                                                        : dragOverRow(
                                                                              event,
                                                                              root.id,
                                                                              kid.id,
                                                                          )
                                                                }
                                                                dragging={
                                                                    movingRoot ||
                                                                    (drag?.list ===
                                                                        root.id &&
                                                                        drag.id ===
                                                                            kid.id)
                                                                }
                                                                dropEdge={
                                                                    kidTargeted
                                                                        ? dropTarget.edge ===
                                                                          'before'
                                                                            ? 'top'
                                                                            : 'bottom'
                                                                        : rootEdge ===
                                                                                'after' &&
                                                                            kidIndex ===
                                                                                shownKids.length -
                                                                                    1
                                                                          ? 'bottom'
                                                                          : null
                                                                }
                                                            />
                                                        );
                                                    },
                                                )}
                                            </Fragment>
                                        );
                                    },
                                )}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            {/* ── Create dialog ────────────────────────────── */}
            <Dialog open={createOpen} onOpenChange={setCreateOpen}>
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Nouvelle catégorie</DialogTitle>
                        <DialogDescription>
                            Le nom doit être renseigné dans les trois langues.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-4">
                        {locales.map((loc) => (
                            <Field
                                key={loc}
                                label={LOCALE_LABELS[loc]}
                                error={
                                    createForm.errors[
                                        `name.${loc}` as keyof typeof createForm.errors
                                    ]
                                }
                            >
                                <input
                                    dir={loc === 'ar' ? 'rtl' : 'ltr'}
                                    value={createForm.data.name[loc] ?? ''}
                                    onChange={(e) =>
                                        createForm.setData('name', {
                                            ...createForm.data.name,
                                            [loc]: e.target.value,
                                        })
                                    }
                                    placeholder={
                                        loc === 'ar'
                                            ? 'مثال: سجاد يدوي'
                                            : 'ex. Tapis fait main'
                                    }
                                    className={INPUT}
                                />
                            </Field>
                        ))}

                        <Field
                            label="Catégories parentes"
                            error={createForm.errors.parent_ids}
                        >
                            <p className="mb-2 text-xs text-ink-soft">
                                Cochez chaque catégorie principale sous laquelle
                                elle doit apparaître. Laissez vide pour en faire
                                une catégorie principale.
                            </p>
                            <ParentPicker
                                options={parents}
                                selected={createForm.data.parent_ids}
                                onChange={(next) =>
                                    createForm.setData('parent_ids', next)
                                }
                            />
                        </Field>

                        <Field
                            label="Code SH (douane)"
                            error={createForm.errors.hs_code}
                        >
                            <p className="mb-2 text-xs text-ink-soft">
                                Repris par chaque produit de la catégorie, sauf
                                s’il a le sien. 6 chiffres, ex. 5701.10.
                            </p>
                            <input
                                value={createForm.data.hs_code}
                                onChange={(e) =>
                                    createForm.setData(
                                        'hs_code',
                                        e.target.value,
                                    )
                                }
                                placeholder="5701.10"
                                inputMode="numeric"
                                dir="ltr"
                                className={INPUT}
                            />
                        </Field>

                        <SingleImageInput
                            label="Image (optionnel)"
                            hint={imageHint(createForm.data.parent_ids)}
                            aspect="square"
                            value={createForm.data.image}
                            onChange={(file) =>
                                createForm.setData('image', file)
                            }
                            error={createForm.errors.image}
                        />
                    </div>

                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setCreateOpen(false)}
                        >
                            Annuler
                        </Button>
                        <button
                            type="button"
                            onClick={handleCreate}
                            disabled={
                                createForm.processing ||
                                !locales.every((loc) =>
                                    createForm.data.name[loc]?.trim(),
                                )
                            }
                            className="inline-flex items-center justify-center gap-2 rounded-lg bg-ink px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-ink/90 disabled:opacity-50"
                        >
                            {createForm.processing
                                ? 'Création…'
                                : 'Créer la catégorie'}
                        </button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* ── Edit dialog ──────────────────────────────── */}
            <Dialog open={editOpen} onOpenChange={setEditOpen}>
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>
                            Modifier «&nbsp;
                            {editTarget && frenchName(editTarget)}
                            &nbsp;»
                        </DialogTitle>
                        <DialogDescription>
                            Le slug /{editTarget?.slug} reste inchangé — le
                            modifier casserait les liens existants.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-4">
                        {locales.map((loc) => (
                            <Field
                                key={loc}
                                label={LOCALE_LABELS[loc]}
                                error={
                                    editForm.errors[
                                        `name.${loc}` as keyof typeof editForm.errors
                                    ]
                                }
                            >
                                <input
                                    dir={loc === 'ar' ? 'rtl' : 'ltr'}
                                    value={editForm.data.name[loc] ?? ''}
                                    onChange={(e) =>
                                        editForm.setData('name', {
                                            ...editForm.data.name,
                                            [loc]: e.target.value,
                                        })
                                    }
                                    className={INPUT}
                                />
                            </Field>
                        ))}

                        <Field
                            label="Catégories parentes"
                            error={editForm.errors.parent_ids}
                        >
                            <p className="mb-2 text-xs text-ink-soft">
                                Cochez chaque catégorie principale sous laquelle
                                elle doit apparaître. Laissez vide pour en faire
                                une catégorie principale.
                            </p>
                            <ParentPicker
                                // A category cannot sit under itself; the
                                // server rejects it too.
                                options={parents.filter(
                                    (p) => p.id !== editTarget?.id,
                                )}
                                selected={editForm.data.parent_ids}
                                onChange={(next) =>
                                    editForm.setData('parent_ids', next)
                                }
                            />
                        </Field>

                        <Field
                            label="Code SH (douane)"
                            error={editForm.errors.hs_code}
                        >
                            <p className="mb-2 text-xs text-ink-soft">
                                Repris par chaque produit de la catégorie, sauf
                                s’il a le sien. 6 chiffres, ex. 5701.10.
                            </p>
                            <input
                                value={editForm.data.hs_code}
                                onChange={(e) =>
                                    editForm.setData('hs_code', e.target.value)
                                }
                                placeholder="5701.10"
                                inputMode="numeric"
                                dir="ltr"
                                className={INPUT}
                            />
                        </Field>

                        <SingleImageInput
                            label="Image (optionnel)"
                            hint={imageHint(editForm.data.parent_ids)}
                            aspect="square"
                            value={editForm.data.image}
                            onChange={(file) => editForm.setData('image', file)}
                            current={
                                editForm.data.remove_image
                                    ? null
                                    : (editTarget?.image_url ?? null)
                            }
                            onRemoveCurrent={() =>
                                editForm.setData('remove_image', true)
                            }
                            error={editForm.errors.image}
                        />
                    </div>

                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setEditOpen(false)}
                        >
                            Annuler
                        </Button>
                        <button
                            type="button"
                            onClick={handleUpdate}
                            disabled={editForm.processing}
                            className="inline-flex items-center justify-center gap-2 rounded-lg bg-ink px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-ink/90 disabled:opacity-50"
                        >
                            {editForm.processing
                                ? 'Enregistrement…'
                                : 'Enregistrer'}
                        </button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* ── Delete confirmation ──────────────────────── */}
            <AlertDialog open={deleteOpen} onOpenChange={setDeleteOpen}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            Supprimer «&nbsp;
                            {deleteTarget && frenchName(deleteTarget)}
                            &nbsp;» ?
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            Cette action est irréversible.
                            {deleteTarget &&
                                childrenOf(deleteTarget.id).length > 0 && (
                                    <>
                                        {' '}
                                        {
                                            childrenOf(deleteTarget.id).length
                                        }{' '}
                                        sous-catégorie(s) n’y seront plus
                                        rattachées. Celles qui n’ont pas d’autre
                                        parent deviendront des catégories
                                        principales.
                                    </>
                                )}
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Annuler</AlertDialogCancel>
                        <AlertDialogAction
                            onClick={confirmDelete}
                            className="bg-terracotta text-white hover:bg-terracotta/90"
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
