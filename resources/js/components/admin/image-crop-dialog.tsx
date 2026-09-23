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
                        l&apos;image dans le cadre. Elle sera compressée sous 1
                        Mo.
                    </DialogDescription>
                </DialogHeader>

                <div className="relative h-72 overflow-hidden rounded-lg bg-black sm:h-96">
                    <Cropper
                        image={url}
                        crop={crop}
                        zoom={zoom}
                        aspect={aspect}
                        onCropChange={setCrop}
                        onZoomChange={setZoom}
                        onCropComplete={(_, pixels) => setArea(pixels)}
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

                <label className="flex items-center gap-3 text-xs text-ink-soft">
                    Zoom
                    <input
                        type="range"
                        min={1}
                        max={3}
                        step={0.05}
                        value={zoom}
                        onChange={(e) => setZoom(Number(e.target.value))}
                        className="flex-1"
                    />
                </label>

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
