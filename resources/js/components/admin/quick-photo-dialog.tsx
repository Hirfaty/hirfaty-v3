import { router } from '@inertiajs/react';
import { ImageOff, ImagePlus, Loader2, Upload } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { ClipboardEvent } from 'react';
import { toast } from 'sonner';
import ImageCropDialog from '@/components/admin/image-crop-dialog';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { PageProps, Product } from '@/types';

/** One owner's photos, as sent by admin.products.photos.index. */
interface PhotoGroup {
    /** Null for the product's own gallery. */
    variant_id: number | null;
    label: string;
    photos: { id: number; url: string; broken: boolean }[];
}

/** Replace one photo, or add a new one to a gallery (null = the product's). */
type Target = { replace: number } | { addTo: number | null };

/** Flagged by the last `media:check` — on the product or any variant. */
export function hasBrokenPhoto(product: Product): boolean {
    return [
        ...(product.media ?? []),
        ...(product.variants ?? []).flatMap((v) => v.media ?? []),
    ].some((m) => m.type === 'image' && m.broken_at);
}

/**
 * Where the dialog points next: the first broken photo; otherwise the
 * gallery the admin was just adding to, so several photos go in one after
 * another; otherwise the first variant still without a photo.
 */
function nextTarget(groups: PhotoGroup[], previous?: Target): Target {
    for (const group of groups) {
        const broken = group.photos.find((p) => p.broken);

        if (broken) {
            return { replace: broken.id };
        }
    }

    if (
        previous &&
        'addTo' in previous &&
        groups.some((g) => g.variant_id === previous.addTo)
    ) {
        return previous;
    }

    const empty = groups.find(
        (g) => g.variant_id !== null && g.photos.length === 0,
    );

    return { addTo: empty?.variant_id ?? null };
}

async function fetchGroups(productId: number): Promise<PhotoGroup[]> {
    const response = await fetch(
        route('admin.products.photos.index', productId),
        { headers: { Accept: 'application/json' } },
    );

    if (!response.ok) {
        throw new Error('Impossible de charger les photos de ce produit.');
    }

    return ((await response.json()) as { groups: PhotoGroup[] }).groups;
}

const LINK = /^https?:\/\//i;

const TILE =
    'flex w-20 flex-col gap-1 rounded-lg border p-1 text-left transition';

/**
 * The products list's shortcut for fixing photos without opening the edit
 * screen: pick a photo to replace (a broken one is preselected) or a gallery
 * to add to — the product's or a variant's — paste an image link (Google
 * Merchant Center's or Google Images' "Copier l'adresse de l'image") or the
 * image itself, frame it in the usual crop dialog, and it is saved. The
 * dialog stays open afterwards for the next photo.
 *
 * It loads its photos itself (admin.products.photos.index) instead of using
 * the list's row: a product it just fixed may drop out of a filtered list
 * ("Images cassées") while the dialog is still open on it.
 *
 * Links are downloaded by the server (admin.products.remote-image): Google's
 * image hosts send no CORS headers, so the browser could show such a picture
 * but never crop it.
 */
export default function QuickPhotoDialog({
    productId,
    productName,
    onClose,
}: {
    productId: number;
    productName: string;
    onClose: () => void;
}) {
    const [groups, setGroups] = useState<PhotoGroup[] | null>(null);
    const [target, setTarget] = useState<Target>({ addTo: null });
    const [link, setLink] = useState('');
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [crop, setCrop] = useState<{ file: File; url: string } | null>(null);
    const [aspect, setAspect] = useState(1);
    const fileInput = useRef<HTMLInputElement>(null);

    const showGroups = (fresh: PhotoGroup[], previous?: Target) => {
        setGroups(fresh);
        setTarget(nextTarget(fresh, previous));
    };

    const showLoadError = (e: unknown) =>
        setError(e instanceof Error ? e.message : String(e));

    useEffect(() => {
        let current = true;

        fetchGroups(productId).then(
            (fresh) => {
                if (current) {
                    setGroups(fresh);
                    setTarget(nextTarget(fresh));
                }
            },
            (e: Error) => current && setError(e.message),
        );

        return () => {
            current = false;
        };
    }, [productId]);

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
            route('admin.products.photos.store', productId),
            {
                file,
                media_id: 'replace' in target ? target.replace : '',
                variant_id:
                    'addTo' in target && target.addTo !== null
                        ? target.addTo
                        : '',
            },
            {
                forceFormData: true,
                preserveScroll: true,
                preserveState: true,
                onSuccess: (page) => {
                    const flash = (page.props as unknown as PageProps).flash;

                    toast.success(flash?.success ?? 'Photo enregistrée.');
                    endCrop();
                    setLink('');
                    // Stay open on the refreshed photos, ready for the next one.
                    fetchGroups(productId).then(
                        (fresh) => showGroups(fresh, target),
                        showLoadError,
                    );
                },
                onError: (errors) => {
                    endCrop();
                    setError(
                        errors.file ??
                            errors.media_id ??
                            errors.variant_id ??
                            'Enregistrement impossible.',
                    );
                },
            },
        );
    };

    const isTarget = (candidate: Target) =>
        'replace' in candidate
            ? 'replace' in target && target.replace === candidate.replace
            : 'addTo' in target && target.addTo === candidate.addTo;

    const tileState = (candidate: Target) =>
        isTarget(candidate)
            ? 'border-ink ring-2 ring-ink/20'
            : 'border-border hover:border-ink/40';

    return (
        <>
            <Dialog open={!crop} onOpenChange={(open) => !open && onClose()}>
                <DialogContent
                    className="max-h-[90vh] overflow-y-auto sm:max-w-2xl"
                    onPaste={onPaste}
                >
                    <DialogHeader>
                        <DialogTitle>Photos — {productName}</DialogTitle>
                        <DialogDescription>
                            Choisissez la photo à remplacer, ou « Ajouter » dans
                            la galerie ou une déclinaison, puis collez le lien
                            de l&apos;image (ou l&apos;image elle-même). La
                            fenêtre reste ouverte pour enchaîner les photos.
                        </DialogDescription>
                    </DialogHeader>

                    {groups === null ? (
                        <div className="flex h-24 items-center justify-center text-ink-soft">
                            <Loader2 className="h-5 w-5 animate-spin" />
                        </div>
                    ) : (
                        <div className="space-y-4">
                            {groups.map((group) => (
                                <div key={group.variant_id ?? 'product'}>
                                    <p className="mb-1.5 text-xs font-medium text-ink-soft">
                                        {group.label}
                                    </p>
                                    <div className="flex flex-wrap gap-2">
                                        {group.photos.map((photo) => (
                                            <button
                                                key={photo.id}
                                                type="button"
                                                onClick={() =>
                                                    setTarget({
                                                        replace: photo.id,
                                                    })
                                                }
                                                aria-pressed={isTarget({
                                                    replace: photo.id,
                                                })}
                                                className={`${TILE} ${tileState({ replace: photo.id })}`}
                                            >
                                                {photo.broken ? (
                                                    // A lost file stalls for
                                                    // seconds before failing,
                                                    // so it is not requested.
                                                    <span className="flex aspect-square w-full flex-col items-center justify-center gap-1 rounded-md bg-terracotta/10 text-terracotta">
                                                        <ImageOff className="h-5 w-5" />
                                                        <span className="text-[11px] font-medium">
                                                            Cassée
                                                        </span>
                                                    </span>
                                                ) : (
                                                    <img
                                                        src={photo.url}
                                                        alt=""
                                                        loading="lazy"
                                                        className="aspect-square w-full rounded-md bg-muted object-cover"
                                                    />
                                                )}
                                                <span className="text-center text-[11px] text-ink-soft">
                                                    Remplacer
                                                </span>
                                            </button>
                                        ))}
                                        <button
                                            type="button"
                                            onClick={() =>
                                                setTarget({
                                                    addTo: group.variant_id,
                                                })
                                            }
                                            aria-pressed={isTarget({
                                                addTo: group.variant_id,
                                            })}
                                            className={`${TILE} border-dashed ${tileState({ addTo: group.variant_id })}`}
                                        >
                                            <span className="flex aspect-square w-full items-center justify-center rounded-md bg-muted/60 text-ink-soft">
                                                <ImagePlus className="h-5 w-5" />
                                            </span>
                                            <span className="text-center text-[11px] text-ink-soft">
                                                Ajouter
                                            </span>
                                        </button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}

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
                        <Button
                            type="submit"
                            disabled={loading || !link || groups === null}
                        >
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
                            disabled={groups === null}
                            className="inline-flex items-center gap-2 text-sm text-ink-soft transition hover:text-ink disabled:opacity-50"
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

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                        >
                            Terminé
                        </Button>
                    </DialogFooter>
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
