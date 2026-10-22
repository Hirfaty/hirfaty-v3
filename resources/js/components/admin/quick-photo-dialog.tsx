import { router } from '@inertiajs/react';
import { ImageOff, ImagePlus, Loader2, Upload } from 'lucide-react';
import { useRef, useState } from 'react';
import type { ClipboardEvent } from 'react';
import { toast } from 'sonner';
import ImageCropDialog from '@/components/admin/image-crop-dialog';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { Media, PageProps, Product } from '@/types';

interface PhotoSlot {
    media: Media;
    /** "Galerie", or the variant's option values ("Bleu / M"). */
    label: string;
}

/** Every photo of a product: its own gallery first, then each variant's. */
export function photoSlots(product: Product): PhotoSlot[] {
    const own = (product.media ?? [])
        .filter((m) => m.type === 'image')
        .map((media) => ({ media, label: 'Galerie' }));

    const variants = (product.variants ?? []).flatMap((variant) =>
        (variant.media ?? [])
            .filter((m) => m.type === 'image')
            .map((media) => ({
                media,
                label:
                    variant.options.map((o) => o.value).join(' / ') ||
                    `Déclinaison #${variant.id}`,
            })),
    );

    return [...own, ...variants];
}

/** Flagged by the last `media:check` — on the product or any variant. */
export function hasBrokenPhoto(product: Product): boolean {
    return photoSlots(product).some((slot) => slot.media.broken_at);
}

const LINK = /^https?:\/\//i;

/**
 * The products list's shortcut for fixing a photo without opening the edit
 * screen: pick which photo to replace (a broken one is preselected), paste an
 * image link — Google Merchant Center's or Google Images' "Copier l'adresse
 * de l'image" — or the image itself, frame it in the usual crop dialog, and
 * it is saved in place.
 *
 * Links are downloaded by the server (admin.products.remote-image): Google's
 * image hosts send no CORS headers, so the browser could show such a picture
 * but never crop it.
 */
export default function QuickPhotoDialog({
    product,
    onClose,
}: {
    product: Product;
    onClose: () => void;
}) {
    const slots = photoSlots(product);
    // null = add a new photo to the product's gallery.
    const [target, setTarget] = useState<number | null>(
        () =>
            (slots.find((s) => s.media.broken_at) ?? slots[0])?.media.id ??
            null,
    );
    const [link, setLink] = useState('');
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [crop, setCrop] = useState<{ file: File; url: string } | null>(null);
    const [aspect, setAspect] = useState(1);
    const fileInput = useRef<HTMLInputElement>(null);

    const startCrop = (file: File) => {
        if (!file.type.startsWith('image/')) {
            setError("Ce fichier n'est pas une image.");

            return;
        }

        setError(null);
        setCrop({ file, url: URL.createObjectURL(file) });
    };

    const endCrop = () => {
        if (crop) {
            URL.revokeObjectURL(crop.url);
        }

        setCrop(null);
    };

    const loadLink = async (url: string) => {
        const trimmed = url.trim();

        if (!LINK.test(trimmed)) {
            setError(
                'Collez un lien d’image commençant par http:// ou https://.',
            );

            return;
        }

        setLoading(true);
        setError(null);

        try {
            const response = await fetch(
                route('admin.products.remote-image', { url: trimmed }),
                { headers: { Accept: 'application/json' } },
            );

            if (!response.ok) {
                const body = await response.json().catch(() => null);

                throw new Error(
                    body?.errors?.url?.[0] ??
                        body?.message ??
                        'Impossible de charger cette image.',
                );
            }

            const blob = await response.blob();
            const extension = blob.type.split('/')[1] || 'jpg';

            startCrop(
                new File([blob], `photo.${extension}`, { type: blob.type }),
            );
        } catch (e) {
            setError(
                e instanceof Error
                    ? e.message
                    : 'Impossible de charger cette image.',
            );
        } finally {
            setLoading(false);
        }
    };

    // Ctrl+V anywhere in the dialog: an image copied with "Copier l'image"
    // arrives as a file, one copied with "Copier l'adresse" as a link.
    const onPaste = (e: ClipboardEvent) => {
        const image = Array.from(e.clipboardData.files).find((f) =>
            f.type.startsWith('image/'),
        );

        if (image) {
            e.preventDefault();
            startCrop(image);

            return;
        }

        const text = e.clipboardData.getData('text').trim();

        if (LINK.test(text)) {
            e.preventDefault();
            setLink(text);
            void loadLink(text);
        }
    };

    const save = (file: File) => {
        router.post(
            route('admin.products.photos.store', product.id),
            { file, media_id: target ?? '' },
            {
                forceFormData: true,
                preserveScroll: true,
                preserveState: true,
                onSuccess: (page) => {
                    const flash = (page.props as unknown as PageProps).flash;

                    toast.success(flash?.success ?? 'Photo enregistrée.');
                    endCrop();
                    onClose();
                },
                onError: (errors) => {
                    endCrop();
                    setError(
                        errors.file ??
                            errors.media_id ??
                            'Enregistrement impossible.',
                    );
                },
            },
        );
    };

    return (
        <>
            <Dialog open={!crop} onOpenChange={(open) => !open && onClose()}>
                <DialogContent className="sm:max-w-xl" onPaste={onPaste}>
                    <DialogHeader>
                        <DialogTitle>Remplacer une photo</DialogTitle>
                        <DialogDescription>
                            {product.name} — choisissez la photo à remplacer,
                            puis collez le lien de la nouvelle image (ou
                            l&apos;image elle-même). Vous pourrez la recadrer
                            avant d&apos;enregistrer.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid grid-cols-4 gap-2 sm:grid-cols-5">
                        {slots.map(({ media, label }) => (
                            <button
                                key={media.id}
                                type="button"
                                onClick={() => setTarget(media.id)}
                                aria-pressed={target === media.id}
                                className={`group flex flex-col gap-1 rounded-lg border p-1 text-left transition ${
                                    target === media.id
                                        ? 'border-ink ring-2 ring-ink/20'
                                        : 'border-border hover:border-ink/40'
                                }`}
                            >
                                {media.broken_at ? (
                                    // A lost file stalls for seconds before
                                    // failing, so it is not even requested.
                                    <span className="flex aspect-square w-full flex-col items-center justify-center gap-1 rounded-md bg-terracotta/10 text-terracotta">
                                        <ImageOff className="h-5 w-5" />
                                        <span className="text-[11px] font-medium">
                                            Cassée
                                        </span>
                                    </span>
                                ) : (
                                    <img
                                        src={media.url}
                                        alt=""
                                        loading="lazy"
                                        className="aspect-square w-full rounded-md bg-muted object-cover"
                                    />
                                )}
                                <span className="truncate text-[11px] text-ink-soft">
                                    {label}
                                </span>
                            </button>
                        ))}
                        <button
                            type="button"
                            onClick={() => setTarget(null)}
                            aria-pressed={target === null}
                            className={`flex flex-col gap-1 rounded-lg border p-1 text-left transition ${
                                target === null
                                    ? 'border-ink ring-2 ring-ink/20'
                                    : 'border-dashed border-border hover:border-ink/40'
                            }`}
                        >
                            <span className="flex aspect-square w-full items-center justify-center rounded-md bg-muted/60 text-ink-soft">
                                <ImagePlus className="h-5 w-5" />
                            </span>
                            <span className="truncate text-[11px] text-ink-soft">
                                Nouvelle photo
                            </span>
                        </button>
                    </div>

                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            void loadLink(link);
                        }}
                        className="flex gap-2"
                    >
                        <input
                            value={link}
                            onChange={(e) => setLink(e.target.value)}
                            placeholder="Collez ici le lien de l'image (Ctrl+V)"
                            aria-label="Lien de l'image"
                            autoFocus
                            className="min-w-0 flex-1 rounded-lg border border-border bg-card px-3 py-2 text-sm text-ink shadow-sm transition placeholder:text-ink-soft/60 focus:border-ink focus:ring-2 focus:ring-ink/10 focus:outline-none"
                        />
                        <Button type="submit" disabled={loading || !link}>
                            {loading && (
                                <Loader2 className="h-4 w-4 animate-spin" />
                            )}
                            Charger
                        </Button>
                    </form>

                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <button
                            type="button"
                            onClick={() => fileInput.current?.click()}
                            className="inline-flex items-center gap-2 text-sm text-ink-soft transition hover:text-ink"
                        >
                            <Upload className="h-4 w-4" />
                            ou choisir un fichier
                        </button>
                        <input
                            ref={fileInput}
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            className="hidden"
                            onChange={(e) => {
                                const file = e.target.files?.[0];
                                e.target.value = '';

                                if (file) {
                                    startCrop(file);
                                }
                            }}
                        />
                    </div>

                    {error && <p className="text-sm text-red-600">{error}</p>}

                    <p className="text-xs text-ink-soft">
                        Astuce : dans Google, ouvrez l&apos;image en grand avant
                        de copier son adresse — les miniatures (encrypted-tbn…)
                        sont de petite taille.
                    </p>
                </DialogContent>
            </Dialog>

            {crop && (
                <ImageCropDialog
                    file={crop.file}
                    url={crop.url}
                    aspect={aspect}
                    onAspectChange={setAspect}
                    onCancel={endCrop}
                    onConfirm={save}
                />
            )}
        </>
    );
}
