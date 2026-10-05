import type { RequestPayload } from '@inertiajs/core';
import { Link, router } from '@inertiajs/react';
import { ArrowLeft, FileText, Loader2, Lock, Send } from 'lucide-react';
import { useState } from 'react';
import ProductMediaInput, {
    buildExistingItems,
} from '@/components/admin/product-media-input';
import type {
    ExistingMedia,
    MediaItem,
} from '@/components/admin/product-media-input';
import VariantBuilder from '@/components/admin/variant-builder';
import type { VariantDraft } from '@/components/admin/variant-builder';
import { cn } from '@/lib/utils';
import type {
    Category,
    DimensionUnit,
    Product,
    ProductVisibility,
    WeightUnit,
} from '@/types';

export interface StoreOption {
    id: number;
    store_name: string;
    logo?: string | null;
    user?: { id: number; first_name: string; last_name: string };
}

interface Props {
    mode: 'create' | 'edit';
    categories: Category[];
    /** Preselected seller (create via ?store_id=, or the product's owner). */
    store?: StoreOption | null;
    /** All sellers, offered when no store is preselected. */
    stores?: StoreOption[] | null;
    /** Existing product when editing. */
    product?: Product | null;
    submitUrl: string;
    backUrl: string;
}

const INPUT =
    'w-full rounded-lg border border-border bg-card px-3.5 py-2.5 text-sm text-ink shadow-sm transition placeholder:text-ink-soft/70 focus:border-ink focus:outline-none focus:ring-2 focus:ring-ink/10';
const LABEL = 'mb-1.5 block text-sm font-medium text-ink';

const DIMENSION_AXES = [
    { key: 'length', label: 'Longueur' },
    { key: 'width', label: 'Largeur' },
    { key: 'height', label: 'Hauteur' },
] as const;

function Section({
    title,
    description,
    children,
}: {
    title: string;
    description?: string;
    children: React.ReactNode;
}) {
    return (
        <section className="rounded-xl border border-border bg-card p-6">
            <h2 className="text-base font-semibold text-ink">{title}</h2>
            {description && (
                <p className="mt-1 mb-5 text-sm text-ink-soft">{description}</p>
            )}
            <div className={description ? '' : 'mt-5'}>{children}</div>
        </section>
    );
}

function Field({
    label,
    error,
    hint,
    children,
}: {
    label: string;
    error?: string;
    hint?: string;
    children: React.ReactNode;
}) {
    return (
        <div>
            <label className={LABEL}>{label}</label>
            {children}
            {hint && !error && (
                <p className="mt-1.5 text-xs text-ink-soft">{hint}</p>
            )}
            {error && <p className="mt-1.5 text-xs text-red-600">{error}</p>}
        </div>
    );
}

/**
 * Editable form state. Ids come from the server as numbers but from `<select>`
 * as strings, and prices are kept as strings so an empty field stays empty
 * rather than becoming 0.
 */
interface ProductFormState {
    store_id: number | string;
    name: string;
    sku: string;
    description: string;
    base_price: string;
    sale_price: string;
    professional_price: string;
    professional_sale_price: string;
    min_order_qty_professional: number;
    visibility: ProductVisibility;
    stock: number;
    weight: string;
    weight_unit: WeightUnit;
    length: string;
    width: string;
    height: string;
    dimension_unit: DimensionUnit;
    hs_code: string;
    customs_description: string;
    is_fragile: boolean;
    handling_days: string;
}

/**
 * Where a (possibly already-saved) category id sits in the tree, so the two
 * selects below can be preset without the caller needing to know whether the
 * id is a root or one of its children.
 */
function locateCategory(
    categories: Category[],
    categoryId: number | string | null | undefined,
): { mainId: number | string; subId: number | string } {
    const id =
        categoryId === null || categoryId === undefined || categoryId === ''
            ? null
            : Number(categoryId);

    if (id !== null) {
        for (const root of categories) {
            if (root.id === id) {
                return { mainId: root.id, subId: '' };
            }

            const child = (root.children ?? []).find((c) => c.id === id);

            if (child) {
                return { mainId: root.id, subId: child.id };
            }
        }
    }

    return { mainId: categories[0]?.id ?? '', subId: '' };
}

/** Map a saved product's variants back into editable drafts. */
function variantsFromProduct(product?: Product | null): VariantDraft[] {
    return (product?.variants ?? []).map((v) => ({
        // Load-bearing: the server matches variants by id on save. Dropping it
        // puts saving back on position matching, which silently rebinds an
        // existing variant — and the cart/order rows referencing its id — to a
        // different combination when one is removed from the middle.
        id: v.id,
        sku: v.sku ?? '',
        stock: v.stock,
        price_override: v.price_override ?? '',
        professional_price_override: v.professional_price_override ?? '',
        weight: v.weight ?? '',
        length: v.length ?? '',
        width: v.width ?? '',
        height: v.height ?? '',
        is_active: v.is_active ?? true,
        options: (v.options ?? []).map((o) => ({
            type: o.type ?? 'other',
            name: o.name,
            value: o.value,
        })),
        media: [],
        existingMedia: (v.media ?? []).map((m) => ({
            id: m.id,
            url: m.url,
        })),
        deletedMediaIds: [],
    }));
}

export default function ProductForm({
    mode,
    categories,
    store,
    stores,
    product,
    submitUrl,
    backUrl,
}: Props) {
    const [data, setData] = useState<ProductFormState>({
        store_id: product?.store_id ?? store?.id ?? '',
        name: product?.name ?? '',
        sku: product?.sku ?? '',
        description: product?.description ?? '',
        base_price: product?.base_price ?? '',
        sale_price: product?.sale_price ?? '',
        professional_price: product?.professional_price ?? '',
        professional_sale_price: product?.professional_sale_price ?? '',
        min_order_qty_professional: product?.min_order_qty_professional ?? 1,
        visibility: product?.visibility ?? 'both',
        stock: product?.stock ?? 1,
        weight: product?.weight ?? '',
        weight_unit: product?.weight_unit ?? 'kg',
        length: product?.length ?? '',
        width: product?.width ?? '',
        height: product?.height ?? '',
        dimension_unit: product?.dimension_unit ?? 'cm',
        hs_code: product?.hs_code ?? '',
        customs_description: product?.customs_description ?? '',
        is_fragile: product?.is_fragile ?? false,
        handling_days:
            product?.handling_days === null ||
            product?.handling_days === undefined
                ? ''
                : String(product.handling_days),
    });

    // Two selects standing in for one `category_id` column: a required main
    // category, and — only when that category actually has subcategories —
    // an optional, more specific one. Whichever is more specific is what
    // gets submitted.
    const initialCategory = locateCategory(categories, product?.category_id);
    const [categoryMainId, setCategoryMainId] = useState<number | string>(
        initialCategory.mainId,
    );
    const [categorySubId, setCategorySubId] = useState<number | string>(
        initialCategory.subId,
    );
    const mainCategory = categories.find(
        (c) => c.id === Number(categoryMainId),
    );
    const subCategories = mainCategory?.children ?? [];
    // The code a blank HS field falls back to: the subcategory's, else the
    // main category's — what ProductShippingService::hsCode() resolves.
    const inheritedHsCode =
        subCategories.find((c) => c.id === Number(categorySubId))?.hs_code ||
        mainCategory?.hs_code ||
        null;

    const [media, setMedia] = useState<MediaItem[]>(
        buildExistingItems((product?.media ?? []) as ExistingMedia[]),
    );
    const [removedMediaIds, setRemovedMediaIds] = useState<number[]>([]);
    const [variants, setVariants] = useState<VariantDraft[]>(
        variantsFromProduct(product),
    );
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    const set = <K extends keyof typeof data>(
        key: K,
        value: (typeof data)[K],
    ) => setData((d) => ({ ...d, [key]: value }));

    const hasVariants = variants.length > 0;

    const handleMediaChange = (next: MediaItem[]) => {
        // Track removals so the server can delete them on save.
        const stillPresent = new Set(
            next.filter((i) => i.kind === 'existing').map((i) => i.id),
        );
        const dropped = media
            .filter((i) => i.kind === 'existing' && !stillPresent.has(i.id))
            .map((i) => (i as { id: number }).id);

        if (dropped.length) {
            setRemovedMediaIds((prev) => [...new Set([...prev, ...dropped])]);
        }

        setMedia(next);
    };

    const submit = (status: 'draft' | 'published') => {
        setProcessing(true);
        setErrors({});

        const payload = {
            ...data,
            category_id: categorySubId || categoryMainId,
            status,
            // FormData has no booleans; Laravel's `boolean` rule takes 1/0.
            is_fragile: data.is_fragile ? 1 : 0,
            // Order the gallery exactly as shown; new files upload in sequence
            // and existing ids carry their new positions.
            media: media
                .filter((m) => m.kind === 'new')
                .map((m) => (m as { file: File }).file),
            media_order: media
                .filter((m) => m.kind === 'existing')
                .map((m) => (m as { id: number }).id),
            deleted_media: removedMediaIds,
            // `existingMedia` is client-side display state only; the server
            // expects the removed ids under `deleted_media` (snake_case).
            variants: hasVariants
                ? variants.map((v) => ({
                      // Absent for a combination that was just generated; the
                      // server creates those and matches the rest by id.
                      ...(v.id ? { id: v.id } : {}),
                      sku: v.sku,
                      stock: v.stock,
                      price_override: v.price_override,
                      professional_price_override:
                          v.professional_price_override,
                      weight: v.weight,
                      length: v.length,
                      width: v.width,
                      height: v.height,
                      is_active: v.is_active,
                      options: v.options,
                      media: v.media,
                      deleted_media: v.deletedMediaIds,
                  }))
                : [],
        };

        // Inertia serialises this into FormData; the cast satisfies its
        // RequestPayload shape, which does not model nested drafts.
        router.post(submitUrl, payload as unknown as RequestPayload, {
            forceFormData: true,
            /*
             * Keep what was typed only when the save is rejected. On success
             * the form must be rebuilt from the saved product: a variant or
             * photo created by this save only gets its id from the server, and
             * a stale form would send it again as new on the next save —
             * duplicating it, or tripping the variant SKU index.
             */
            preserveState: 'errors',
            onError: (errs) => setErrors(errs as Record<string, string>),
            onFinish: () => setProcessing(false),
        });
    };

    const canSubmit = data.name.trim() !== '' && data.store_id !== '';

    return (
        <div className="min-h-screen px-6 py-10">
            <div className="mx-auto max-w-4xl">
                <Link
                    href={backUrl}
                    className="mb-6 inline-flex items-center gap-2 text-sm text-ink-soft transition hover:text-ink"
                >
                    <ArrowLeft className="h-4 w-4" /> Retour
                </Link>

                <header className="mb-8 flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-semibold tracking-tight text-ink">
                            {mode === 'create'
                                ? 'Nouveau produit'
                                : 'Modifier le produit'}
                        </h1>
                        <p className="mt-1.5 text-sm text-ink-soft">
                            {store
                                ? `Pour ${store.store_name}`
                                : 'Créé au nom du vendeur sélectionné.'}
                        </p>
                    </div>

                    {product?.status === 'draft' && (
                        <span className="inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-3 py-1 text-xs font-medium text-amber-800">
                            <FileText className="h-3.5 w-3.5" /> Brouillon
                        </span>
                    )}
                </header>

                {store && (
                    <div className="mb-8 flex items-center gap-4 rounded-xl border border-border bg-card p-4">
                        {store.logo ? (
                            <img
                                src={store.logo}
                                alt={store.store_name}
                                className="h-14 w-14 rounded-full object-cover"
                            />
                        ) : (
                            <div className="flex h-14 w-14 items-center justify-center rounded-full bg-muted text-lg font-semibold text-ink-soft">
                                {store.store_name.charAt(0).toUpperCase()}
                            </div>
                        )}
                        <div>
                            <p className="text-sm text-ink-soft">
                                Création d’un produit pour
                            </p>
                            <p className="text-base font-semibold text-ink">
                                {store.store_name}
                            </p>
                            {store.user && (
                                <p className="text-sm text-ink-soft">
                                    {store.user.first_name}{' '}
                                    {store.user.last_name}
                                </p>
                            )}
                        </div>
                    </div>
                )}

                <div className="space-y-6">
                    {/* ── Seller ─────────────────────────────── */}
                    {!store && (
                        <Section title="Vendeur">
                            <Field label="Boutique" error={errors.store_id}>
                                <select
                                    value={data.store_id}
                                    onChange={(e) =>
                                        set('store_id', e.target.value)
                                    }
                                    className={INPUT}
                                >
                                    <option value="">
                                        — Choisir un vendeur —
                                    </option>
                                    {(stores ?? []).map((s) => (
                                        <option key={s.id} value={s.id}>
                                            {s.store_name}
                                            {s.user
                                                ? ` — ${s.user.first_name} ${s.user.last_name}`
                                                : ''}
                                        </option>
                                    ))}
                                </select>
                            </Field>
                        </Section>
                    )}

                    {/* ── Basics ─────────────────────────────── */}
                    <Section
                        title="Informations"
                        description="Ce que l’acheteur voit en premier."
                    >
                        <div className="grid gap-5 sm:grid-cols-2">
                            <div className="sm:col-span-2">
                                <Field
                                    label="Nom du produit"
                                    error={errors.name}
                                >
                                    <input
                                        value={data.name}
                                        onChange={(e) =>
                                            set('name', e.target.value)
                                        }
                                        placeholder="Tapis Beni Ouarain"
                                        className={INPUT}
                                    />
                                </Field>
                            </div>

                            <Field
                                label="Catégorie principale"
                                error={errors.category_id}
                            >
                                <select
                                    value={categoryMainId}
                                    onChange={(e) => {
                                        setCategoryMainId(e.target.value);
                                        setCategorySubId('');
                                    }}
                                    className={INPUT}
                                >
                                    {categories.map((c) => (
                                        <option key={c.id} value={c.id}>
                                            {c.translated_name}
                                        </option>
                                    ))}
                                </select>
                            </Field>

                            {subCategories.length > 0 && (
                                <Field
                                    label="Sous-catégorie"
                                    hint="Optionnel — laissez sur « Aucune » pour classer directement dans la catégorie principale."
                                >
                                    <select
                                        value={categorySubId}
                                        onChange={(e) =>
                                            setCategorySubId(e.target.value)
                                        }
                                        className={INPUT}
                                    >
                                        <option value="">— Aucune —</option>
                                        {subCategories.map((c) => (
                                            <option key={c.id} value={c.id}>
                                                {c.translated_name}
                                            </option>
                                        ))}
                                    </select>
                                </Field>
                            )}

                            <Field
                                label="Référence (SKU)"
                                error={errors.sku}
                                hint="Optionnel — doit être unique."
                            >
                                <input
                                    value={data.sku}
                                    onChange={(e) => set('sku', e.target.value)}
                                    className={INPUT}
                                />
                            </Field>

                            <div className="sm:col-span-2">
                                <Field
                                    label="Description"
                                    error={errors.description}
                                >
                                    <textarea
                                        rows={5}
                                        value={data.description}
                                        onChange={(e) =>
                                            set('description', e.target.value)
                                        }
                                        placeholder="Matière, dimensions, savoir-faire…"
                                        className={INPUT}
                                    />
                                </Field>
                            </div>
                        </div>
                    </Section>

                    {/* ── Media ──────────────────────────────── */}
                    <Section title="Médias">
                        <ProductMediaInput
                            items={media}
                            onChange={handleMediaChange}
                            error={errors.media}
                        />
                    </Section>

                    {/* ── Pricing ────────────────────────────── */}
                    <Section
                        title="Tarifs"
                        description="Deux grilles indépendantes : particuliers et professionnels."
                    >
                        <div className="grid gap-5 sm:grid-cols-2">
                            {/* Optional: the admin catalogues a piece before
                                the price is agreed with the artisan. An
                                unpriced product is kept off the storefront
                                (Product::scopeStorefront) rather than sold at
                                nothing, which is what the hint warns about. */}
                            <Field
                                label="Prix de base"
                                error={errors.base_price}
                                hint={
                                    data.base_price === ''
                                        ? 'Laissez vide si le prix n’est pas encore fixé — le produit ne sera pas visible en boutique tant qu’il n’a pas de prix.'
                                        : undefined
                                }
                            >
                                <input
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value={data.base_price}
                                    placeholder="À définir"
                                    onChange={(e) =>
                                        set('base_price', e.target.value)
                                    }
                                    className={INPUT}
                                />
                            </Field>

                            <Field label="Prix promo" error={errors.sale_price}>
                                <input
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value={data.sale_price}
                                    onChange={(e) =>
                                        set('sale_price', e.target.value)
                                    }
                                    className={INPUT}
                                />
                            </Field>

                            <Field
                                label="Prix professionnel"
                                error={errors.professional_price}
                                hint="Vide = les pros paient le prix particulier."
                            >
                                <input
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value={data.professional_price}
                                    onChange={(e) =>
                                        set(
                                            'professional_price',
                                            e.target.value,
                                        )
                                    }
                                    className={INPUT}
                                />
                            </Field>

                            <Field
                                label="Promo professionnelle"
                                error={errors.professional_sale_price}
                            >
                                <input
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value={data.professional_sale_price}
                                    onChange={(e) =>
                                        set(
                                            'professional_sale_price',
                                            e.target.value,
                                        )
                                    }
                                    className={INPUT}
                                />
                            </Field>

                            <Field
                                label="Quantité minimum en gros"
                                error={errors.min_order_qty_professional}
                            >
                                <input
                                    type="number"
                                    min="1"
                                    value={data.min_order_qty_professional}
                                    onChange={(e) =>
                                        set(
                                            'min_order_qty_professional',
                                            Number(e.target.value),
                                        )
                                    }
                                    className={INPUT}
                                />
                            </Field>

                            <Field label="Visible pour">
                                <select
                                    value={data.visibility}
                                    onChange={(e) =>
                                        set(
                                            'visibility',
                                            e.target.value as ProductVisibility,
                                        )
                                    }
                                    className={INPUT}
                                >
                                    <option value="both">
                                        Tous les acheteurs
                                    </option>
                                    <option value="individual">
                                        Particuliers uniquement
                                    </option>
                                    <option value="professional">
                                        Professionnels uniquement
                                    </option>
                                </select>
                            </Field>
                        </div>
                    </Section>

                    {/* ── Stock & variants ───────────────────── */}
                    <Section
                        title="Stock & déclinaisons"
                        description={
                            hasVariants
                                ? 'Définissez les options (couleur, dimension…) et leurs valeurs, générez les combinaisons, puis supprimez celles qui n’existent pas. Chacune a son propre stock ; un prix laissé vide reprend celui du produit.'
                                : 'Un produit simple a un seul stock. Ajoutez une option seulement s’il se décline en plusieurs couleurs, dimensions, matières…'
                        }
                    >
                        {!hasVariants && (
                            <div className="mb-4 max-w-xs">
                                <Field label="Stock" error={errors.stock}>
                                    <input
                                        type="number"
                                        min="0"
                                        value={data.stock}
                                        onChange={(e) =>
                                            set('stock', Number(e.target.value))
                                        }
                                        className={INPUT}
                                    />
                                </Field>
                            </div>
                        )}

                        <VariantBuilder
                            variants={variants}
                            onChange={setVariants}
                            errors={errors}
                            weightUnit={data.weight_unit}
                            dimensionUnit={data.dimension_unit}
                        />
                    </Section>

                    {/* ── Shipping ───────────────────────────── */}
                    <Section
                        title="Expédition"
                        description={
                            hasVariants
                                ? 'Poids et dimensions du colis emballé, utilisés pour calculer les frais du transporteur. Ils valent pour toutes les déclinaisons ; une déclinaison peut les remplacer dans le tableau ci-dessus.'
                                : 'Poids et dimensions du colis emballé, utilisés pour calculer les frais du transporteur.'
                        }
                    >
                        <div className="grid gap-5 sm:grid-cols-2">
                            <Field label="Poids" error={errors.weight}>
                                <div className="flex gap-2">
                                    <input
                                        type="number"
                                        min="0"
                                        step="any"
                                        value={data.weight}
                                        onChange={(e) =>
                                            set('weight', e.target.value)
                                        }
                                        className={cn(INPUT, 'min-w-0 flex-1')}
                                    />
                                    <select
                                        value={data.weight_unit}
                                        onChange={(e) =>
                                            set(
                                                'weight_unit',
                                                e.target.value as WeightUnit,
                                            )
                                        }
                                        aria-label="Unité de poids"
                                        className={cn(INPUT, 'w-24 shrink-0')}
                                    >
                                        <option value="g">g</option>
                                        <option value="kg">kg</option>
                                    </select>
                                </div>
                            </Field>

                            <Field
                                label="Pays d’expédition"
                                hint="Tous les colis partent de l’entrepôt Hirfaty au Maroc."
                            >
                                <div
                                    className={`${INPUT} flex items-center gap-2 bg-muted/40 text-ink-soft`}
                                >
                                    <Lock className="h-3.5 w-3.5" />
                                    Maroc (MA)
                                </div>
                            </Field>

                            <div className="sm:col-span-2">
                                <Field
                                    label="Dimensions du colis (L × l × H)"
                                    error={
                                        errors.length ??
                                        errors.width ??
                                        errors.height
                                    }
                                    hint="Mesurez le carton une fois emballé, pas l’objet seul : le transporteur facture le plus élevé entre le poids réel et le poids volumétrique."
                                >
                                    {/*
                                     * One column per axis, each with its own
                                     * caption — a placeholder alone vanishes
                                     * once a figure is typed, leaving three
                                     * unlabelled boxes. min-w-0 lets the
                                     * number inputs shrink to their column
                                     * instead of their intrinsic width, which
                                     * is what made them overlap.
                                     */}
                                    <div className="grid grid-cols-3 gap-3 sm:grid-cols-[repeat(3,minmax(0,1fr))_6rem]">
                                        {DIMENSION_AXES.map((axis) => (
                                            <div
                                                key={axis.key}
                                                className="min-w-0"
                                            >
                                                <span className="mb-1 block text-xs text-ink-soft">
                                                    {axis.label}
                                                </span>
                                                <div className="relative">
                                                    <input
                                                        type="number"
                                                        min="0"
                                                        step="any"
                                                        value={data[axis.key]}
                                                        onChange={(e) =>
                                                            set(
                                                                axis.key,
                                                                e.target.value,
                                                            )
                                                        }
                                                        placeholder="0"
                                                        aria-label={`${axis.label} (${data.dimension_unit})`}
                                                        className={cn(
                                                            INPUT,
                                                            'min-w-0 pe-10',
                                                        )}
                                                    />
                                                    <span className="pointer-events-none absolute inset-y-0 end-3 flex items-center text-xs text-ink-soft">
                                                        {data.dimension_unit}
                                                    </span>
                                                </div>
                                            </div>
                                        ))}
                                        <div className="col-span-3 min-w-0 sm:col-span-1">
                                            <span className="mb-1 block text-xs text-ink-soft">
                                                Unité
                                            </span>
                                            <select
                                                value={data.dimension_unit}
                                                onChange={(e) =>
                                                    set(
                                                        'dimension_unit',
                                                        e.target
                                                            .value as DimensionUnit,
                                                    )
                                                }
                                                aria-label="Unité de dimension"
                                                className={INPUT}
                                            >
                                                <option value="cm">cm</option>
                                                <option value="m">m</option>
                                            </select>
                                        </div>
                                    </div>
                                </Field>
                            </div>

                            <Field
                                label="Code SH (douane)"
                                error={errors.hs_code}
                                hint={
                                    inheritedHsCode
                                        ? `Vide = celui de la catégorie (${inheritedHsCode}).`
                                        : 'Vide = celui de la catégorie (aucun défini pour l’instant).'
                                }
                            >
                                <input
                                    value={data.hs_code}
                                    onChange={(e) =>
                                        set('hs_code', e.target.value)
                                    }
                                    placeholder={inheritedHsCode ?? '5701.10'}
                                    inputMode="numeric"
                                    className={INPUT}
                                />
                            </Field>

                            <Field
                                label="Délai de préparation (jours)"
                                error={errors.handling_days}
                                hint="Jours avant la remise au transporteur."
                            >
                                <input
                                    type="number"
                                    min="0"
                                    max="60"
                                    value={data.handling_days}
                                    onChange={(e) =>
                                        set('handling_days', e.target.value)
                                    }
                                    className={INPUT}
                                />
                            </Field>

                            <div className="sm:col-span-2">
                                <Field
                                    label="Description douanière (en anglais)"
                                    error={errors.customs_description}
                                    hint="Précise et en anglais, ex. « Hand-knotted wool rug ». Une description vague (« decor », « gift ») bloque le colis en douane."
                                >
                                    <input
                                        value={data.customs_description}
                                        onChange={(e) =>
                                            set(
                                                'customs_description',
                                                e.target.value,
                                            )
                                        }
                                        maxLength={255}
                                        dir="ltr"
                                        className={INPUT}
                                    />
                                </Field>
                            </div>

                            <label className="flex items-center gap-2.5 text-sm text-ink sm:col-span-2">
                                <input
                                    type="checkbox"
                                    checked={data.is_fragile}
                                    onChange={(e) =>
                                        set('is_fragile', e.target.checked)
                                    }
                                    className="h-4 w-4 rounded border-border"
                                />
                                Fragile — à manipuler avec précaution
                                (céramique, verre…)
                            </label>
                        </div>
                    </Section>
                </div>

                {/* ── Actions ────────────────────────────────── */}
                <div className="sticky bottom-0 mt-6 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border bg-card/95 px-5 py-4 shadow-sm backdrop-blur">
                    <p className="text-xs text-ink-soft">
                        Un brouillon n’apparaît pas dans la boutique — vous
                        pourrez le compléter plus tard.
                    </p>

                    <div className="flex items-center gap-3">
                        <button
                            type="button"
                            onClick={() => submit('draft')}
                            disabled={processing || !canSubmit}
                            className="inline-flex items-center gap-2 rounded-lg border border-border bg-card px-4 py-2.5 text-sm font-medium text-ink transition hover:border-ink/30 disabled:opacity-50"
                        >
                            <FileText className="h-4 w-4" />
                            Enregistrer le brouillon
                        </button>

                        <button
                            type="button"
                            onClick={() => submit('published')}
                            disabled={processing || !canSubmit}
                            className="inline-flex items-center gap-2 rounded-lg bg-ink px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-ink/90 disabled:opacity-50"
                        >
                            {processing ? (
                                <Loader2 className="h-4 w-4 animate-spin" />
                            ) : (
                                <Send className="h-4 w-4" />
                            )}
                            Publier
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}
