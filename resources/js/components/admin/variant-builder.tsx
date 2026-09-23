import { ImagePlus, Plus, Trash2, Wand2, X } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { useCropQueue } from '@/hooks/use-crop-queue';
import { validateMediaFile } from '@/lib/media-limits';

export type OptionType =
    | 'color'
    | 'dimension'
    | 'material'
    | 'finish'
    | 'pattern'
    | 'other';

/**
 * One axis/value pair, e.g. name="Couleur", value="Rouge".
 *
 * `name` is the axis and `value` the point on it. They used to both hold the
 * label, which meant the storefront built a selector row per *value* instead
 * of per axis, and no variant could satisfy them all.
 */
export interface VariantOptionDraft {
    type: OptionType;
    name: string;
    value: string;
}

/** An axis and every value it offers, e.g. Couleur → [Rouge, Bleu]. */
export interface AxisDraft {
    type: OptionType;
    /** Canonical for known types; freely named when type is `other`. */
    name: string;
    values: string[];
}

/** A variant photo already uploaded and saved (edit screen only). */
export interface VariantExistingMedia {
    id: number;
    url: string;
}

export interface VariantDraft {
    /**
     * Set for an already-saved variant. Round-tripped so the server matches
     * rows by id rather than by array position — cart_items and order_items
     * hold this id, so position matching silently repointed a shopper's
     * basket line at a different combination.
     */
    id?: number;
    sku: string;
    stock: number;
    price_override: string;
    professional_price_override: string;
    is_active: boolean;
    options: VariantOptionDraft[];
    /** Newly picked photo files, not yet uploaded. */
    media: File[];
    /** Already-saved photos (edit screen only). */
    existingMedia: VariantExistingMedia[];
    /** Ids from existingMedia the admin removed, to delete on save. */
    deletedMediaIds: number[];
}

/**
 * Axis kinds offered to sellers, tuned for craft goods. Everything but
 * `other` carries a canonical name so "Couleur" cannot drift into "couleur"
 * or "Color" across products — the server enforces the same list
 * (ProductVariantOption::AXIS_LABELS), this is only the picker.
 */
export const AXIS_TYPES: { value: OptionType; label: string }[] = [
    { value: 'color', label: 'Couleur' },
    { value: 'dimension', label: 'Dimension' },
    { value: 'material', label: 'Matière' },
    { value: 'finish', label: 'Finition' },
    { value: 'pattern', label: 'Motif' },
    { value: 'other', label: 'Autre…' },
];

const CANONICAL: Partial<Record<OptionType, string>> = {
    color: 'Couleur',
    dimension: 'Dimension',
    material: 'Matière',
    finish: 'Finition',
    pattern: 'Motif',
};

export const axisNameFor = (type: OptionType, custom = '') =>
    CANONICAL[type] ?? custom;

const INPUT =
    'w-full rounded-lg border border-border bg-card px-3 py-2 text-sm text-ink shadow-sm transition placeholder:text-ink-soft/70 focus:border-ink focus:outline-none focus:ring-2 focus:ring-ink/10';
const LABEL = 'mb-1 block text-xs font-medium text-ink-soft';

export const emptyVariant = (): VariantDraft => ({
    sku: '',
    stock: 0,
    price_override: '',
    professional_price_override: '',
    is_active: true,
    options: [],
    media: [],
    existingMedia: [],
    deletedMediaIds: [],
});

/** Order-independent, case-insensitive identity of a combination. */
export const combinationKey = (options: VariantOptionDraft[]) =>
    [...options]
        .sort((a, b) => a.name.localeCompare(b.name))
        .map((o) => `${o.name}=${o.value.trim().toLowerCase()}`)
        .join('|');

/** "Rouge / 4m" */
export const combinationLabel = (options: VariantOptionDraft[]) =>
    options.map((o) => o.value).join(' / ');

/**
 * Rebuild the axis list from saved variants when opening the edit screen.
 *
 * Axes are not stored separately — they are derived from the variants that
 * use them. Because the generator emits combinations in axis order, first-seen
 * order here reproduces the order the admin originally chose.
 */
export function axesFromVariants(variants: VariantDraft[]): AxisDraft[] {
    const axes: AxisDraft[] = [];

    for (const variant of variants) {
        for (const option of variant.options) {
            let axis = axes.find((a) => a.name === option.name);

            if (!axis) {
                axis = { type: option.type, name: option.name, values: [] };
                axes.push(axis);
            }

            if (!axis.values.includes(option.value)) {
                axis.values.push(option.value);
            }
        }
    }

    return axes;
}

/** Every combination of the axes' values, in axis order. */
function cartesian(axes: AxisDraft[]): VariantOptionDraft[][] {
    return axes.reduce<VariantOptionDraft[][]>(
        (acc, axis) =>
            acc.flatMap((combo) =>
                axis.values.map((value) => [
                    ...combo,
                    { type: axis.type, name: axis.name, value },
                ]),
            ),
        [[]],
    );
}

interface Props {
    variants: VariantDraft[];
    onChange: (variants: VariantDraft[]) => void;
    errors: Record<string, string>;
}

/**
 * Small multi-image picker scoped to one variant — a colour/finish photo, not
 * a full gallery, so no drag-reorder unlike ProductMediaInput.
 */
function VariantMediaPicker({
    media,
    existingMedia,
    onAddFiles,
    onRemoveNew,
    onRemoveExisting,
}: {
    media: File[];
    existingMedia: VariantExistingMedia[];
    onAddFiles: (files: File[]) => void;
    onRemoveNew: (index: number) => void;
    onRemoveExisting: (id: number) => void;
}) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [rejections, setRejections] = useState<string[]>([]);

    // Derived, not state-synced-via-effect: one object URL per new File,
    // recomputed only when the file list itself changes. The effect below
    // only revokes stale URLs on cleanup — it never writes state.
    const newUrls = useMemo(
        () => media.map((f) => URL.createObjectURL(f)),
        [media],
    );
    useEffect(() => {
        return () => newUrls.forEach((u) => URL.revokeObjectURL(u));
    }, [newUrls]);

    const total = existingMedia.length + media.length;
    const full = total >= 5;

    const { enqueue: enqueueCrop, dialog: cropDialog } =
        useCropQueue(onAddFiles);

    const pick = async (files: FileList | null) => {
        if (!files) {
            return;
        }

        const picked = Array.from(files).slice(0, 5 - total);
        const results = await Promise.all(
            picked.map(async (file) => ({
                file,
                rejection: await validateMediaFile(file, { willCrop: true }),
            })),
        );

        setRejections(
            results.filter((r) => r.rejection).map((r) => r.rejection!),
        );

        const accepted = results.filter((r) => !r.rejection).map((r) => r.file);

        enqueueCrop(accepted);
    };

    return (
        <div>
            <div className="flex flex-wrap items-center gap-2">
                {existingMedia.map((m) => (
                    <div
                        key={`e${m.id}`}
                        className="group relative h-12 w-12 shrink-0 overflow-hidden rounded-lg border border-border bg-muted/40"
                    >
                        <img
                            src={m.url}
                            alt=""
                            className="h-full w-full object-cover"
                        />
                        <button
                            type="button"
                            onClick={() => onRemoveExisting(m.id)}
                            aria-label="Retirer la photo"
                            className="absolute inset-0 flex items-center justify-center bg-ink/0 text-white opacity-0 transition group-hover:bg-ink/50 group-hover:opacity-100"
                        >
                            <Trash2 className="h-3.5 w-3.5" />
                        </button>
                    </div>
                ))}

                {media.map((file, i) => (
                    <div
                        key={`n${i}`}
                        className="group relative h-12 w-12 shrink-0 overflow-hidden rounded-lg border border-border bg-muted/40"
                    >
                        <img
                            src={newUrls[i]}
                            alt=""
                            className="h-full w-full object-cover"
                        />
                        <button
                            type="button"
                            onClick={() => onRemoveNew(i)}
                            aria-label="Retirer la photo"
                            className="absolute inset-0 flex items-center justify-center bg-ink/0 text-white opacity-0 transition group-hover:bg-ink/50 group-hover:opacity-100"
                        >
                            <Trash2 className="h-3.5 w-3.5" />
                        </button>
                    </div>
                ))}

                {!full && (
                    <button
                        type="button"
                        onClick={() => inputRef.current?.click()}
                        className="flex h-12 w-12 shrink-0 flex-col items-center justify-center gap-0.5 rounded-lg border-2 border-dashed border-border text-ink-soft transition hover:border-ink/40 hover:bg-muted/40 hover:text-ink"
                    >
                        <ImagePlus className="h-3.5 w-3.5" />
                        <span className="text-[9px] font-medium">
                            {total}/5
                        </span>
                    </button>
                )}
            </div>

            <input
                ref={inputRef}
                type="file"
                multiple
                accept="image/*"
                className="hidden"
                onChange={(e) => {
                    pick(e.target.files);
                    e.target.value = '';
                }}
            />

            {rejections.length > 0 && (
                <ul className="mt-1.5 space-y-0.5">
                    {rejections.map((message) => (
                        <li key={message} className="text-xs text-red-600">
                            {message}
                        </li>
                    ))}
                </ul>
            )}

            {cropDialog}
        </div>
    );
}

/** Chip list with an inline "add value" input, for one axis. */
function AxisValues({
    values,
    onAdd,
    onRemove,
}: {
    values: string[];
    onAdd: (value: string) => void;
    onRemove: (value: string) => void;
}) {
    const [draft, setDraft] = useState('');

    const commit = () => {
        const value = draft.trim();

        if (value !== '') {
            onAdd(value);
        }

        setDraft('');
    };

    return (
        <div className="flex flex-wrap items-center gap-1.5">
            {values.map((value) => (
                <span
                    key={value}
                    className="inline-flex items-center gap-1 rounded-full border border-border bg-card py-1 ps-3 pe-1.5 text-sm text-ink"
                >
                    {value}
                    <button
                        type="button"
                        onClick={() => onRemove(value)}
                        aria-label={`Retirer ${value}`}
                        className="rounded-full p-0.5 text-ink-soft transition hover:bg-muted hover:text-ink"
                    >
                        <X className="h-3 w-3" />
                    </button>
                </span>
            ))}

            <input
                value={draft}
                onChange={(e) => setDraft(e.target.value)}
                onBlur={commit}
                onKeyDown={(e) => {
                    // Enter must not submit the surrounding product form.
                    if (e.key === 'Enter' || e.key === ',') {
                        e.preventDefault();
                        commit();
                    }
                }}
                placeholder="Ajouter une valeur…"
                className="min-w-40 flex-1 rounded-lg border border-dashed border-border bg-transparent px-3 py-1.5 text-sm text-ink placeholder:text-ink-soft/70 focus:border-ink focus:outline-none"
            />
        </div>
    );
}

export default function VariantBuilder({ variants, onChange, errors }: Props) {
    // Axes live alongside the variants rather than being stored: on the edit
    // screen they are reconstructed from whatever the saved variants use.
    const [axes, setAxes] = useState<AxisDraft[]>(() =>
        axesFromVariants(variants),
    );

    const patchVariant = (index: number, patch: Partial<VariantDraft>) =>
        onChange(
            variants.map((v, i) => (i === index ? { ...v, ...patch } : v)),
        );

    const removeVariant = (index: number) =>
        onChange(variants.filter((_, i) => i !== index));

    const patchAxis = (index: number, patch: Partial<AxisDraft>) =>
        setAxes((prev) =>
            prev.map((a, i) => (i === index ? { ...a, ...patch } : a)),
        );

    const addAxis = () => {
        const used = new Set(axes.map((a) => a.type));
        const next =
            AXIS_TYPES.find((t) => t.value !== 'other' && !used.has(t.value))
                ?.value ?? 'other';

        setAxes((prev) => [
            ...prev,
            { type: next, name: axisNameFor(next), values: [] },
        ]);
    };

    /**
     * Dropping an axis also strips it from every variant, then removes any
     * combination that has become a duplicate of an earlier one — otherwise
     * the server rejects the whole product for ragged axes or duplicates.
     */
    const removeAxis = (index: number) => {
        const axis = axes[index];

        setAxes((prev) => prev.filter((_, i) => i !== index));

        const seen = new Set<string>();
        const next: VariantDraft[] = [];

        for (const variant of variants) {
            const options = variant.options.filter((o) => o.name !== axis.name);

            if (options.length === 0) {
                continue;
            }

            const key = combinationKey(options);

            if (seen.has(key)) {
                continue;
            }

            seen.add(key);
            next.push({ ...variant, options });
        }

        onChange(next);
    };

    const removeAxisValue = (axisIndex: number, value: string) => {
        const axis = axes[axisIndex];

        patchAxis(axisIndex, {
            values: axis.values.filter((v) => v !== value),
        });

        // Any combination built on that value no longer exists.
        onChange(
            variants.filter(
                (v) =>
                    !v.options.some(
                        (o) => o.name === axis.name && o.value === value,
                    ),
            ),
        );
    };

    const renameAxis = (index: number, type: OptionType, custom: string) => {
        const axis = axes[index];
        const name = axisNameFor(type, custom);

        patchAxis(index, { type, name });

        // Keep existing combinations pointing at the renamed axis.
        onChange(
            variants.map((v) => ({
                ...v,
                options: v.options.map((o) =>
                    o.name === axis.name ? { ...o, type, name } : o,
                ),
            })),
        );
    };

    /**
     * Add every combination the axes imply that doesn't exist yet, leaving
     * existing rows (and their ids, stock and photos) untouched. Combinations
     * the admin deleted stay deleted until they generate again — which is what
     * makes a sparse grid like Rouge/4m, Rouge/6m, Bleu/4m expressible.
     */
    const generate = () => {
        const usable = axes.filter((a) => a.values.length > 0 && a.name !== '');

        if (usable.length === 0) {
            return;
        }

        const existing = new Set(
            variants.map((v) => combinationKey(v.options)),
        );
        const additions = cartesian(usable)
            .filter((options) => !existing.has(combinationKey(options)))
            .map((options) => ({ ...emptyVariant(), options }));

        onChange([...variants, ...additions]);
    };

    const missingCount = (() => {
        const usable = axes.filter((a) => a.values.length > 0 && a.name !== '');

        if (usable.length === 0) {
            return 0;
        }

        const existing = new Set(
            variants.map((v) => combinationKey(v.options)),
        );

        return cartesian(usable).filter((o) => !existing.has(combinationKey(o)))
            .length;
    })();

    return (
        <div className="space-y-6">
            {/* ── Axes ─────────────────────────────────────── */}
            <div className="space-y-4">
                {axes.map((axis, i) => (
                    <div
                        key={i}
                        className="rounded-xl border border-border bg-muted/20 p-4"
                    >
                        <div className="flex items-start gap-3">
                            <div className="w-44 shrink-0">
                                <label className={LABEL}>Option</label>
                                <select
                                    value={axis.type}
                                    onChange={(e) =>
                                        renameAxis(
                                            i,
                                            e.target.value as OptionType,
                                            axis.type === 'other'
                                                ? axis.name
                                                : '',
                                        )
                                    }
                                    className={INPUT}
                                >
                                    {AXIS_TYPES.map((t) => (
                                        <option key={t.value} value={t.value}>
                                            {t.label}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            {axis.type === 'other' && (
                                <div className="w-52 shrink-0">
                                    <label className={LABEL}>Nom</label>
                                    <input
                                        value={axis.name}
                                        onChange={(e) =>
                                            renameAxis(
                                                i,
                                                'other',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="Tressage, Contenance…"
                                        className={INPUT}
                                    />
                                </div>
                            )}

                            <div className="min-w-0 flex-1">
                                <label className={LABEL}>Valeurs</label>
                                <AxisValues
                                    values={axis.values}
                                    onAdd={(value) =>
                                        !axis.values.includes(value) &&
                                        patchAxis(i, {
                                            values: [...axis.values, value],
                                        })
                                    }
                                    onRemove={(value) =>
                                        removeAxisValue(i, value)
                                    }
                                />
                            </div>

                            <button
                                type="button"
                                onClick={() => removeAxis(i)}
                                aria-label="Retirer cette option"
                                className="mt-5 shrink-0 rounded-lg p-2 text-ink-soft transition hover:bg-red-50 hover:text-red-600"
                            >
                                <Trash2 className="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                ))}

                <div className="flex flex-wrap items-center gap-3">
                    <button
                        type="button"
                        onClick={addAxis}
                        className="inline-flex items-center gap-2 rounded-lg border border-border bg-card px-3.5 py-2 text-sm font-medium text-ink transition hover:bg-muted"
                    >
                        <Plus className="h-4 w-4" /> Ajouter une option
                    </button>

                    {missingCount > 0 && (
                        <button
                            type="button"
                            onClick={generate}
                            className="inline-flex items-center gap-2 rounded-lg bg-ink px-3.5 py-2 text-sm font-medium text-white transition hover:bg-ink/90"
                        >
                            <Wand2 className="h-4 w-4" />
                            Générer {missingCount} déclinaison
                            {missingCount > 1 ? 's' : ''}
                        </button>
                    )}
                </div>

                {axes.length > 0 && (
                    <p className="text-xs text-ink-soft">
                        Générez les combinaisons, puis supprimez celles qui
                        n’existent pas. Chaque combinaison doit utiliser les
                        mêmes options.
                    </p>
                )}
            </div>

            {/* ── Combinations ─────────────────────────────── */}
            {variants.length > 0 && (
                <div className="overflow-hidden rounded-xl border border-border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/40 text-left text-xs text-ink-soft">
                            <tr>
                                <th className="px-4 py-2.5 font-medium">
                                    Déclinaison
                                </th>
                                <th className="px-3 py-2.5 font-medium">
                                    Stock
                                </th>
                                <th className="px-3 py-2.5 font-medium">
                                    Prix
                                </th>
                                <th className="px-3 py-2.5 font-medium">
                                    Prix pro
                                </th>
                                <th className="px-3 py-2.5 font-medium">SKU</th>
                                <th className="px-3 py-2.5 font-medium">
                                    Photos
                                </th>
                                <th className="w-10" />
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-border">
                            {variants.map((variant, i) => (
                                <tr key={variant.id ?? `new-${i}`}>
                                    <td className="px-4 py-3 align-top">
                                        <span className="font-medium text-ink">
                                            {combinationLabel(
                                                variant.options,
                                            ) || '—'}
                                        </span>
                                        {errors[`variants.${i}.options`] && (
                                            <p className="mt-1 text-xs text-red-600">
                                                {
                                                    errors[
                                                        `variants.${i}.options`
                                                    ]
                                                }
                                            </p>
                                        )}
                                    </td>
                                    <td className="px-3 py-3 align-top">
                                        <input
                                            type="number"
                                            min={0}
                                            value={variant.stock}
                                            onChange={(e) =>
                                                patchVariant(i, {
                                                    stock:
                                                        Number(
                                                            e.target.value,
                                                        ) || 0,
                                                })
                                            }
                                            className={`${INPUT} w-20`}
                                        />
                                        {errors[`variants.${i}.stock`] && (
                                            <p className="mt-1 text-xs text-red-600">
                                                {errors[`variants.${i}.stock`]}
                                            </p>
                                        )}
                                    </td>
                                    <td className="px-3 py-3 align-top">
                                        <input
                                            type="number"
                                            min={0}
                                            step="0.01"
                                            value={variant.price_override}
                                            onChange={(e) =>
                                                patchVariant(i, {
                                                    price_override:
                                                        e.target.value,
                                                })
                                            }
                                            placeholder="défaut"
                                            className={`${INPUT} w-24`}
                                        />
                                    </td>
                                    <td className="px-3 py-3 align-top">
                                        <input
                                            type="number"
                                            min={0}
                                            step="0.01"
                                            value={
                                                variant.professional_price_override
                                            }
                                            onChange={(e) =>
                                                patchVariant(i, {
                                                    professional_price_override:
                                                        e.target.value,
                                                })
                                            }
                                            placeholder="défaut"
                                            className={`${INPUT} w-24`}
                                        />
                                    </td>
                                    <td className="px-3 py-3 align-top">
                                        <input
                                            value={variant.sku}
                                            onChange={(e) =>
                                                patchVariant(i, {
                                                    sku: e.target.value,
                                                })
                                            }
                                            placeholder="—"
                                            className={`${INPUT} w-28`}
                                        />
                                    </td>
                                    <td className="px-3 py-3 align-top">
                                        <VariantMediaPicker
                                            media={variant.media}
                                            existingMedia={
                                                variant.existingMedia
                                            }
                                            onAddFiles={(files) =>
                                                patchVariant(i, {
                                                    media: [
                                                        ...variant.media,
                                                        ...files,
                                                    ].slice(0, 5),
                                                })
                                            }
                                            onRemoveNew={(index) =>
                                                patchVariant(i, {
                                                    media: variant.media.filter(
                                                        (_, mi) => mi !== index,
                                                    ),
                                                })
                                            }
                                            onRemoveExisting={(id) =>
                                                patchVariant(i, {
                                                    existingMedia:
                                                        variant.existingMedia.filter(
                                                            (m) => m.id !== id,
                                                        ),
                                                    deletedMediaIds: [
                                                        ...variant.deletedMediaIds,
                                                        id,
                                                    ],
                                                })
                                            }
                                        />
                                    </td>
                                    <td className="px-3 py-3 align-top">
                                        <button
                                            type="button"
                                            onClick={() => removeVariant(i)}
                                            aria-label="Supprimer la déclinaison"
                                            className="rounded-lg p-2 text-ink-soft transition hover:bg-red-50 hover:text-red-600"
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            {axes.length === 0 && variants.length === 0 && (
                <p className="text-sm text-ink-soft">
                    Ajoutez une option (couleur, dimension, matière…) pour
                    proposer plusieurs déclinaisons de ce produit.
                </p>
            )}
        </div>
    );
}
