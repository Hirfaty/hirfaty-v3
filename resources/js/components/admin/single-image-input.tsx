import { ImagePlus, Trash2, Upload } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { validateMediaFile } from '@/lib/media-limits';

interface Props {
    label: string;
    hint?: string;
    /** Controls the preview tile's proportions — wide for a banner, square for a logo. */
    aspect: 'square' | 'wide';
    value: File | null;
    onChange: (file: File | null) => void;
    error?: string;
}

/**
 * Single-image picker with preview and replace/remove — used for the shop's
 * logo and banner, where a reorderable gallery (see ProductMediaInput) would
 * be overkill for one file.
 */
export default function SingleImageInput({
    label,
    hint,
    aspect,
    value,
    onChange,
    error,
}: Props) {
    const inputRef = useRef<HTMLInputElement>(null);
    // Client-side rejection (too large / wrong resolution) — separate from
    // `error`, which is the server's response to a submit.
    const [rejection, setRejection] = useState<string | null>(null);

    // Derived, not state-synced-via-effect: the URL is a pure function of
    // `value`, so no need to store it and no setState-in-effect. The effect
    // below only *revokes* the previous URL on change/unmount — it never
    // writes state, which is what a plain useEffect(() => { setX(...) })
    // here would otherwise trigger a cascading extra render for.
    const previewUrl = useMemo(
        () => (value ? URL.createObjectURL(value) : null),
        [value],
    );
    useEffect(() => {
        return () => {
            if (previewUrl) {
                URL.revokeObjectURL(previewUrl);
            }
        };
    }, [previewUrl]);

    const pick = async (files: FileList | null) => {
        const file = files?.[0];

        if (!file) {
            return;
        }

        const rejectionMessage = await validateMediaFile(file);
        setRejection(rejectionMessage);

        if (!rejectionMessage) {
            onChange(file);
        }
    };

    return (
        <div>
            <p className="text-sm font-medium text-ink">{label}</p>
            {hint && <p className="mt-0.5 text-xs text-ink-soft">{hint}</p>}
            <p className="mt-0.5 text-xs text-ink-soft">
                5 Mo max, 4096x4096px max.
            </p>

            <div
                className={`mt-2 ${aspect === 'wide' ? 'aspect-[3/1]' : 'aspect-square max-w-[180px]'}`}
            >
                {previewUrl ? (
                    <div className="group relative h-full w-full overflow-hidden rounded-xl border border-border bg-muted/40">
                        <img
                            src={previewUrl}
                            alt=""
                            className="h-full w-full object-cover"
                        />
                        <div className="absolute inset-0 flex items-center justify-center gap-2 bg-ink/0 opacity-0 transition group-hover:bg-ink/40 group-hover:opacity-100">
                            <button
                                type="button"
                                onClick={() => inputRef.current?.click()}
                                className="rounded-md bg-white/90 p-2 text-ink shadow-sm transition hover:bg-white"
                                aria-label="Remplacer l'image"
                            >
                                <Upload className="h-4 w-4" />
                            </button>
                            <button
                                type="button"
                                onClick={() => onChange(null)}
                                className="rounded-md bg-white/90 p-2 text-red-600 shadow-sm transition hover:bg-white"
                                aria-label="Retirer l'image"
                            >
                                <Trash2 className="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                ) : (
                    <button
                        type="button"
                        onClick={() => inputRef.current?.click()}
                        className="flex h-full w-full flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-border text-ink-soft transition hover:border-ink/40 hover:bg-muted/40 hover:text-ink"
                    >
                        <ImagePlus className="h-5 w-5" />
                        <span className="text-xs font-medium">
                            Choisir une image
                        </span>
                    </button>
                )}
            </div>

            <input
                ref={inputRef}
                type="file"
                accept="image/*"
                className="hidden"
                onChange={(e) => {
                    pick(e.target.files);
                    e.target.value = '';
                }}
            />

            {rejection && (
                <p className="mt-1.5 text-xs text-red-600">{rejection}</p>
            )}
            {error && <p className="mt-1.5 text-xs text-red-600">{error}</p>}
        </div>
    );
}
