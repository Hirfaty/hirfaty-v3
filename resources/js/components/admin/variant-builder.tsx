import { ImagePlus, Palette, Plus, Ruler, Tag, Trash2, X } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { validateMediaFile } from '@/lib/media-limits';

export type OptionType = 'color' | 'size' | 'other';

export interface VariantOptionDraft {
    type: OptionType;
    /** Label shown to buyers, e.g. "Rouge brique" or "42". */
    name: string;
    /** Free value; only used by `other`. Colour/size reuse `name`. */
    value: string;
    /** #rrggbb swatch; only used by `color`. */
    color_hex: string;
}

/** A variant photo already uploaded and saved (edit screen only). */
export interface VariantExistingMedia {
    id: number;
    url: string;
}

export interface VariantDraft {
    sku: string;
    stock: number;
    price_override: string;
    professional_price_override: string;
    options: VariantOptionDraft[];
    /** Newly picked photo files, not yet uploaded. */
    media: File[];
    /** Already-saved photos (edit screen only). */
    existingMedia: VariantExistingMedia[];
    /** Ids from existingMedia the admin removed, to delete on save. */
    deletedMediaIds: number[];
}

/**
 * Curated swatches — artisanal tones plus the basics. The admin can still enter
 * any hex with the native picker.
 */
const PALETTE: { label: string; hex: string }[] = [
    { label: 'Noir', hex: '#111827' },
    { label: 'Blanc', hex: '#FFFFFF' },
    { label: 'Gris', hex: '#9CA3AF' },
    { label: 'Beige', hex: '#D9C7A7' },
    { label: 'Sable', hex: '#E7D3B1' },
    { label: 'Marron', hex: '#8B5E3C' },
    { label: 'Terracotta', hex: '#C4622D' },
    { label: 'Rouge', hex: '#DC2626' },
    { label: 'Orange', hex: '#EA580C' },
    { label: 'Safran', hex: '#EAB308' },
    { label: 'Olive', hex: '#6B7A3A' },
    { label: 'Vert', hex: '#16A34A' },
    { label: 'Turquoise', hex: '#14B8A6' },
    { label: 'Bleu Majorelle', hex: '#2743CE' },
    { label: 'Bleu ciel', hex: '#38BDF8' },
    { label: 'Violet', hex: '#7C3AED' },
    { label: 'Rose', hex: '#EC4899' },
    { label: 'Or', hex: '#D4AF37' },
];

const OPTION_TYPES: {
    value: OptionType;
    label: string;
    icon: typeof Palette;
}[] = [
    { value: 'color', label: 'Couleur', icon: Palette },
    { value: 'size', label: 'Taille', icon: Ruler },
    { value: 'other', label: 'Autre', icon: Tag },
];

const INPUT =
    'w-full rounded-lg border border-border bg-card px-3 py-2 text-sm text-ink shadow-sm transition placeholder:text-ink-soft/70 focus:border-ink focus:outline-none focus:ring-2 focus:ring-ink/10';
const LABEL = 'mb-1 block text-xs font-medium text-ink-soft';

export const emptyOption = (
    type: OptionType = 'color',
): VariantOptionDraft => ({
    type,
    name: '',
    value: '',
    color_hex: type === 'color' ? PALETTE[0].hex : '',
});

export const emptyVariant = (): VariantDraft => ({
    sku: '',
    stock: 0,
    price_override: '',
    professional_price_override: '',
    options: [emptyOption('color')],
    media: [],
    existingMedia: [],
    deletedMediaIds: [],
});

interface Props {
    variants: VariantDraft[];
    onChange: (variants: VariantDraft[]) => void;
    errors: Record<string, string>;
}

/**
 * Small multi-image picker scoped to one variant — a swatch/colour photo,
 * not a full gallery, so no drag-reorder unlike ProductMediaInput.
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

    const pick = async (files: FileList | null) => {
        if (!files) {
            return;
        }

        const picked = Array.from(files).slice(0, 5 - total);
        const results = await Promise.all(
            picked.map(async (file) => ({
                file,
                rejection: await validateMediaFile(file),
            })),
        );

        setRejections(
            results.filter((r) => r.rejection).map((r) => r.rejection!),
        );

        const accepted = results.filter((r) => !r.rejection).map((r) => r.file);

        if (accepted.length > 0) {
            onAddFiles(accepted);
        }
    };

    return (
        <div className="mt-4 border-t border-border pt-4">
            <label className={LABEL}>Photos de la déclinaison</label>
            <div className="mt-2 flex flex-wrap items-center gap-2">
                {existingMedia.map((m) => (
                    <div
                        key={`e${m.id}`}
                        className="group relative h-16 w-16 shrink-0 overflow-hidden rounded-lg border border-border bg-muted/40"
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
                            <Trash2 className="h-4 w-4" />
                        </button>
                    </div>
                ))}

                {media.map((file, i) => (
                    <div
                        key={`n${i}`}
                        className="group relative h-16 w-16 shrink-0 overflow-hidden rounded-lg border border-border bg-muted/40"
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
                            <Trash2 className="h-4 w-4" />
                        </button>
                    </div>
                ))}

                {!full && (
                    <button
                        type="button"
                        onClick={() => inputRef.current?.click()}
                        className="flex h-16 w-16 shrink-0 flex-col items-center justify-center gap-1 rounded-lg border-2 border-dashed border-border text-ink-soft transition hover:border-ink/40 hover:bg-muted/40 hover:text-ink"
                    >
                        <ImagePlus className="h-4 w-4" />
                        <span className="text-[10px] font-medium">
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
        </div>
    );
}

export default function VariantBuilder({ variants, onChange, errors }: Props) {
    const patchVariant = (index: number, patch: Partial<VariantDraft>) =>
        onChange(
            variants.map((v, i) => (i === index ? { ...v, ...patch } : v)),
        );

    const patchOption = (
        vIndex: number,
        oIndex: number,
        patch: Partial<VariantOptionDraft>,
    ) =>
        patchVariant(vIndex, {
            options: variants[vIndex].options.map((o, i) =>
                i === oIndex ? { ...o, ...patch } : o,
            ),
        });

    /** Switching kind resets the fields that don't apply to the new kind. */
    const changeType = (vIndex: number, oIndex: number, type: OptionType) =>
        patchOption(vIndex, oIndex, {
            type,
            value: '',
            color_hex: type === 'color' ? PALETTE[0].hex : '',
        });

    return (
        <div className="space-y-4">
            {variants.length === 0 && (
                <div className="rounded-xl border border-dashed border-border bg-muted/30 px-5 py-8 text-center">
                    <p className="text-sm text-ink-soft">
                        Aucune déclinaison. Le stock est géré au niveau du
                        produit.
                    </p>
                    <button
                        type="button"
                        onClick={() => onChange([emptyVariant()])}
                        className="mt-3 inline-flex items-center gap-2 rounded-lg border border-border bg-card px-4 py-2 text-sm font-medium text-ink transition hover:border-ink/30"
                    >
                        <Plus className="h-4 w-4" /> Ajouter une déclinaison
                    </button>
                </div>
            )}

            {variants.map((variant, vIndex) => {
                // Live preview of what this variant will be called, e.g.
                // "Bleu Majorelle / 42" — reinforces that the options below
                // combine into ONE purchasable version of the product, not
                // a separate variant each.
                const preview = variant.options
                    .map((o) => o.name || o.value)
                    .filter(Boolean)
                    .join(' / ');

                return (
                    <div
                        key={vIndex}
                        className="rounded-xl border border-border bg-card p-5"
                    >
                        <div className="mb-4 flex items-center justify-between gap-3">
                            <div>
                                <span className="text-xs font-semibold tracking-wider text-ink-soft uppercase">
                                    Déclinaison {vIndex + 1}
                                </span>
                                {preview && (
                                    <p className="mt-0.5 text-sm font-medium text-ink">
                                        {preview}
                                    </p>
                                )}
                            </div>
                            <button
                                type="button"
                                onClick={() =>
                                    onChange(
                                        variants.filter((_, i) => i !== vIndex),
                                    )
                                }
                                className="inline-flex items-center gap-1.5 text-xs text-red-600 transition hover:text-red-700"
                            >
                                <Trash2 className="h-3.5 w-3.5" /> Retirer cette
                                déclinaison
                            </button>
                        </div>

                        {/* ── Options ─────────────────────────────── */}
                        <div className="space-y-1">
                            <p className={LABEL}>
                                Caractéristiques de cette déclinaison
                            </p>
                            <p className="mb-3 text-xs text-ink-soft">
                                Une seule caractéristique (ex. juste une
                                couleur), ou plusieurs combinées ensemble
                                (couleur + taille) pour définir cette version
                                précise du produit.
                            </p>
                        </div>
                        <div className="space-y-3">
                            {variant.options.map((option, oIndex) => (
                                <div
                                    key={oIndex}
                                    className="rounded-lg border border-border bg-muted/30 p-3"
                                >
                                    <div className="mb-3 flex items-center justify-between gap-3">
                                        {/* Kind switcher */}
                                        <div className="inline-flex rounded-lg border border-border bg-card p-0.5">
                                            {OPTION_TYPES.map((t) => {
                                                const Icon = t.icon;
                                                const active =
                                                    option.type === t.value;

                                                return (
                                                    <button
                                                        key={t.value}
                                                        type="button"
                                                        onClick={() =>
                                                            changeType(
                                                                vIndex,
                                                                oIndex,
                                                                t.value,
                                                            )
                                                        }
                                                        className={`inline-flex items-center gap-1.5 rounded-md px-2.5 py-1.5 text-xs font-medium transition ${
                                                            active
                                                                ? 'bg-ink text-white'
                                                                : 'text-ink-soft hover:text-ink'
                                                        }`}
                                                    >
                                                        <Icon className="h-3.5 w-3.5" />
                                                        {t.label}
                                                    </button>
                                                );
                                            })}
                                        </div>

                                        {variant.options.length > 1 && (
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    patchVariant(vIndex, {
                                                        options:
                                                            variant.options.filter(
                                                                (_, i) =>
                                                                    i !==
                                                                    oIndex,
                                                            ),
                                                    })
                                                }
                                                aria-label="Retirer l'option"
                                                className="rounded-md p-1 text-ink-soft transition hover:text-red-600"
                                            >
                                                <X className="h-4 w-4" />
                                            </button>
                                        )}
                                    </div>

                                    {/* Colour: label + swatch */}
                                    {option.type === 'color' && (
                                        <div className="space-y-3">
                                            <div>
                                                <label className={LABEL}>
                                                    Nom de la couleur
                                                </label>
                                                <input
                                                    value={option.name}
                                                    onChange={(e) =>
                                                        patchOption(
                                                            vIndex,
                                                            oIndex,
                                                            {
                                                                name: e.target
                                                                    .value,
                                                            },
                                                        )
                                                    }
                                                    placeholder="Bleu Majorelle"
                                                    className={INPUT}
                                                />
                                            </div>

                                            <div>
                                                <label className={LABEL}>
                                                    Teinte
                                                </label>
                                                <div className="flex flex-wrap items-center gap-1.5">
                                                    {PALETTE.map((c) => {
                                                        const active =
                                                            option.color_hex.toUpperCase() ===
                                                            c.hex.toUpperCase();

                                                        return (
                                                            <button
                                                                key={c.hex}
                                                                type="button"
                                                                title={c.label}
                                                                aria-label={
                                                                    c.label
                                                                }
                                                                onClick={() =>
                                                                    patchOption(
                                                                        vIndex,
                                                                        oIndex,
                                                                        {
                                                                            color_hex:
                                                                                c.hex,
                                                                            // Prefill the label on first pick
                                                                            // so the admin rarely types it.
                                                                            name:
                                                                                option.name.trim() ===
                                                                                ''
                                                                                    ? c.label
                                                                                    : option.name,
                                                                        },
                                                                    )
                                                                }
                                                                style={{
                                                                    background:
                                                                        c.hex,
                                                                }}
                                                                className={`h-7 w-7 rounded-full border transition ${
                                                                    active
                                                                        ? 'border-ink ring-2 ring-ink/30 ring-offset-1'
                                                                        : 'border-black/10 hover:scale-110'
                                                                }`}
                                                            />
                                                        );
                                                    })}

                                                    {/* Escape hatch for an exact brand tone */}
                                                    <label className="ml-1 inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-border bg-card px-2 py-1 text-xs text-ink-soft transition hover:border-ink/30">
                                                        <input
                                                            type="color"
                                                            value={
                                                                option.color_hex ||
                                                                '#000000'
                                                            }
                                                            onChange={(e) =>
                                                                patchOption(
                                                                    vIndex,
                                                                    oIndex,
                                                                    {
                                                                        color_hex:
                                                                            e.target.value.toUpperCase(),
                                                                    },
                                                                )
                                                            }
                                                            className="h-5 w-5 cursor-pointer border-0 bg-transparent p-0"
                                                        />
                                                        {option.color_hex ||
                                                            'Personnalisé'}
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    )}

                                    {/* Size: label only */}
                                    {option.type === 'size' && (
                                        <div>
                                            <label className={LABEL}>
                                                Taille
                                            </label>
                                            <input
                                                value={option.name}
                                                onChange={(e) =>
                                                    patchOption(
                                                        vIndex,
                                                        oIndex,
                                                        {
                                                            name: e.target
                                                                .value,
                                                        },
                                                    )
                                                }
                                                placeholder="M, 42, 30x40 cm…"
                                                className={INPUT}
                                            />
                                        </div>
                                    )}

                                    {/* Other: free name + value */}
                                    {option.type === 'other' && (
                                        <div className="grid gap-3 sm:grid-cols-2">
                                            <div>
                                                <label className={LABEL}>
                                                    Nom
                                                </label>
                                                <input
                                                    value={option.name}
                                                    onChange={(e) =>
                                                        patchOption(
                                                            vIndex,
                                                            oIndex,
                                                            {
                                                                name: e.target
                                                                    .value,
                                                            },
                                                        )
                                                    }
                                                    placeholder="Matière"
                                                    className={INPUT}
                                                />
                                            </div>
                                            <div>
                                                <label className={LABEL}>
                                                    Valeur
                                                </label>
                                                <input
                                                    value={option.value}
                                                    onChange={(e) =>
                                                        patchOption(
                                                            vIndex,
                                                            oIndex,
                                                            {
                                                                value: e.target
                                                                    .value,
                                                            },
                                                        )
                                                    }
                                                    placeholder="Cuir pleine fleur"
                                                    className={INPUT}
                                                />
                                            </div>
                                        </div>
                                    )}
                                </div>
                            ))}

                            <button
                                type="button"
                                onClick={() =>
                                    patchVariant(vIndex, {
                                        options: [
                                            ...variant.options,
                                            emptyOption('size'),
                                        ],
                                    })
                                }
                                className="text-xs font-medium text-primary transition hover:underline"
                            >
                                + Combiner avec une autre caractéristique (ex.
                                taille en plus de la couleur)
                            </button>
                        </div>

                        {/* ── Stock & price overrides ─────────────── */}
                        <div className="mt-4 grid gap-3 border-t border-border pt-4 sm:grid-cols-4">
                            <div>
                                <label className={LABEL}>SKU</label>
                                <input
                                    value={variant.sku}
                                    onChange={(e) =>
                                        patchVariant(vIndex, {
                                            sku: e.target.value,
                                        })
                                    }
                                    placeholder="Optionnel"
                                    className={INPUT}
                                />
                            </div>
                            <div>
                                <label className={LABEL}>Stock</label>
                                <input
                                    type="number"
                                    min="0"
                                    value={variant.stock}
                                    onChange={(e) =>
                                        patchVariant(vIndex, {
                                            stock: Number(e.target.value),
                                        })
                                    }
                                    className={INPUT}
                                />
                                {errors[`variants.${vIndex}.stock`] && (
                                    <p className="mt-1 text-xs text-red-600">
                                        {errors[`variants.${vIndex}.stock`]}
                                    </p>
                                )}
                            </div>
                            <div>
                                <label className={LABEL}>
                                    Prix particulier
                                </label>
                                <input
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value={variant.price_override}
                                    onChange={(e) =>
                                        patchVariant(vIndex, {
                                            price_override: e.target.value,
                                        })
                                    }
                                    placeholder="hérité"
                                    className={INPUT}
                                />
                            </div>
                            <div>
                                <label className={LABEL}>Prix pro</label>
                                <input
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value={variant.professional_price_override}
                                    onChange={(e) =>
                                        patchVariant(vIndex, {
                                            professional_price_override:
                                                e.target.value,
                                        })
                                    }
                                    placeholder="hérité"
                                    className={INPUT}
                                />
                            </div>
                        </div>

                        <VariantMediaPicker
                            media={variant.media}
                            existingMedia={variant.existingMedia}
                            onAddFiles={(files) =>
                                patchVariant(vIndex, {
                                    media: [...variant.media, ...files],
                                })
                            }
                            onRemoveNew={(i) =>
                                patchVariant(vIndex, {
                                    media: variant.media.filter(
                                        (_, idx) => idx !== i,
                                    ),
                                })
                            }
                            onRemoveExisting={(id) =>
                                patchVariant(vIndex, {
                                    existingMedia: variant.existingMedia.filter(
                                        (m) => m.id !== id,
                                    ),
                                    deletedMediaIds: [
                                        ...variant.deletedMediaIds,
                                        id,
                                    ],
                                })
                            }
                        />
                    </div>
                );
            })}

            {variants.length > 0 && (
                <button
                    type="button"
                    onClick={() => onChange([...variants, emptyVariant()])}
                    className="inline-flex items-center gap-2 rounded-lg border border-border bg-card px-4 py-2.5 text-sm font-medium text-ink transition hover:border-ink/30"
                >
                    <Plus className="h-4 w-4" /> Ajouter une déclinaison
                </button>
            )}
        </div>
    );
}
