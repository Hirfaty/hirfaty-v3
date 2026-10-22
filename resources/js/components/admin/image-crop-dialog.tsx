import { useState } from 'react';
import Cropper from 'react-easy-crop';
import type { Area } from 'react-easy-crop';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { cropImageToFile } from '@/lib/crop-image';

export const CROP_RATIOS = [
    { label: 'Carré 1:1', value: 1 },
    { label: 'Portrait 4:5', value: 4 / 5 },
    { label: 'Portrait 3:4', value: 3 / 4 },
    { label: 'Paysage 4:3', value: 4 / 3 },
    { label: 'Large 16:9', value: 16 / 9 },
] as const;

interface Props {
    file: File;
    /** Object URL of `file`, owned (and revoked) by the caller. */
    url: string;
    /** Position in the batch, e.g. "2 / 3", so the admin knows how many remain. */
    progress?: string;
    aspect: number;
    onAspectChange: (aspect: number) => void;
    onCancel: () => void;
    onConfirm: (cropped: File) => void;
}

/**
 * Frame to fix a photo's proportions before it is added to a product: the
 * admin picks a ratio, drags and zooms the image inside the frame, and only
 * the framed part is kept — cropped and compressed under 1 MB in the browser.
 * Zooming out past "cover" keeps a photo whole, padded with white.
 */
export default function ImageCropDialog({
    file,
    url,
    progress,
    aspect,
    onAspectChange,
    onCancel,
    onConfirm,
}: Props) {
    const [crop, setCrop] = useState({ x: 0, y: 0 });
    const [zoom, setZoom] = useState(1);
    const [area, setArea] = useState<Area | null>(null);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [ratio, setRatio] = useState<number | null>(null);

    /*
     * Zoom at which the whole photo fits inside the frame. At 1 the photo
     * covers the frame, so a picture far taller or wider than every ratio
     * (a 583x1600 shot, say) always loses its ends; zooming out to here
     * keeps all of it and the frame's empty sides are filled with white.
     */
    const fitZoom = ratio ? Math.min(ratio / aspect, aspect / ratio) : 1;
    // The ratio buttons can raise fitZoom under a zoom picked for another one.
    const shownZoom = Math.max(zoom, fitZoom);
    // Below 1 the photo is smaller than the frame, so it must be free to sit
    // anywhere in it; at 1 and up it is kept covering the frame as before.
    const zoomedOut = shownZoom < 1;

    const confirm = async () => {
        if (!area) {
            return;
        }

        setBusy(true);
        setError(null);

        try {
            onConfirm(await cropImageToFile(url, area, file.name));
        } catch (e) {
            setError(e instanceof Error ? e.message : 'Recadrage impossible.');
            setBusy(false);
        }
    };

    return (
        <Dialog open onOpenChange={(open) => !open && !busy && onCancel()}>
            <DialogContent className="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>
                        Recadrer la photo{progress ? ` (${progress})` : ''}
                    </DialogTitle>
                    <DialogDescription>
                        Choisissez un format, puis déplacez et zoomez
                        l&apos;image dans le cadre — dézoomez pour la garder
                        entière, les bords vides seront blancs. Elle sera
                        compressée sous 1 Mo.
                    </DialogDescription>
                </DialogHeader>

                {/* White, not black: what shows around a zoomed-out photo is
                    exactly what the saved image gets. */}
                <div className="relative h-72 overflow-hidden rounded-lg bg-white sm:h-96">
                    <Cropper
                        image={url}
                        crop={crop}
                        zoom={shownZoom}
                        minZoom={fitZoom}
                        aspect={aspect}
                        restrictPosition={!zoomedOut}
                        onCropChange={setCrop}
                        onZoomChange={setZoom}
                        onCropComplete={(_, pixels) => setArea(pixels)}
                        onMediaLoaded={(media) =>
                            setRatio(media.naturalWidth / media.naturalHeight)
                        }
                    />
                </div>

                <div className="flex flex-wrap gap-2">
                    {CROP_RATIOS.map((r) => (
                        <button
                            key={r.label}
                            type="button"
                            onClick={() => onAspectChange(r.value)}
                            className={`rounded-md border px-2.5 py-1 text-xs transition ${
                                aspect === r.value
                                    ? 'border-ink bg-ink text-white'
                                    : 'border-border text-ink-soft hover:border-ink/40'
                            }`}
                        >
                            {r.label}
                        </button>
                    ))}
                </div>

                <div className="flex items-center gap-3">
                    <label className="flex flex-1 items-center gap-3 text-xs text-ink-soft">
                        Zoom
                        <input
                            type="range"
                            min={fitZoom}
                            max={3}
                            step={0.01}
                            value={shownZoom}
                            onChange={(e) => setZoom(Number(e.target.value))}
                            className="flex-1"
                        />
                    </label>
                    {fitZoom < 1 && (
                        <button
                            type="button"
                            onClick={() => {
                                setZoom(fitZoom);
                                setCrop({ x: 0, y: 0 });
                            }}
                            className="rounded-md border border-border px-2.5 py-1 text-xs text-ink-soft transition hover:border-ink/40"
                        >
                            Image entière
                        </button>
                    )}
                </div>

                {error && <p className="text-xs text-red-600">{error}</p>}

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={onCancel}
                        disabled={busy}
                    >
                        Ignorer cette photo
                    </Button>
                    <Button
                        type="button"
                        onClick={confirm}
                        disabled={busy || !area}
                    >
                        {busy ? 'Traitement…' : 'Valider le recadrage'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
