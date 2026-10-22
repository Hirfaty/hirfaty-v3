import type { Area } from 'react-easy-crop';
import { MEDIA_LIMITS } from '@/lib/media-limits';

/** Longest side of a cropped product photo — plenty for a product page. */
const MAX_SIDE = 2048;

/*
 * Starts at 0.82, not 0.92: above ~0.85 a photograph gains no visible detail
 * but roughly doubles in weight (2048px uploads were landing near 1 MB), and
 * every product card and gallery downloads these originals.
 */
const QUALITIES = [0.82, 0.76, 0.7, 0.6];

function loadImage(url: string): Promise<HTMLImageElement> {
    return new Promise((resolve, reject) => {
        const img = new Image();
        img.onload = () => resolve(img);
        img.onerror = () => reject(new Error('Image illisible.'));
        img.src = url;
    });
}

function toBlob(
    canvas: HTMLCanvasElement,
    quality: number,
): Promise<Blob | null> {
    return new Promise((resolve) =>
        canvas.toBlob(resolve, 'image/jpeg', quality),
    );
}

/**
 * Cuts `area` (pixel coordinates in the source image, as reported by
 * react-easy-crop) out of `url` and returns it as a JPEG File that fits
 * MEDIA_LIMITS.productImage — lowering quality first, then resolution, until
 * it does. Transparent PNGs are flattened onto white, since JPEG has no alpha.
 *
 * `area` may reach past the photo's edges when the admin zoomed out to keep
 * it whole: only the overlap is drawn, and the rest of the frame stays white.
 */
export async function cropImageToFile(
    url: string,
    area: Area,
    originalName: string,
): Promise<File> {
    const img = await loadImage(url);
    const baseName = originalName.replace(/\.[^.]+$/, '') || 'image';
    let scale = Math.min(1, MAX_SIDE / Math.max(area.width, area.height));

    // The part of the photo inside the frame, in source pixels. Clipped here
    // rather than left to drawImage, which browsers have not always clipped.
    const sx = Math.max(0, area.x);
    const sy = Math.max(0, area.y);
    const sw = Math.min(img.naturalWidth, area.x + area.width) - sx;
    const sh = Math.min(img.naturalHeight, area.y + area.height) - sy;

    if (sw <= 0 || sh <= 0) {
        throw new Error("L'image est sortie du cadre.");
    }

    for (let attempt = 0; attempt < 8; attempt++) {
        const width = Math.max(1, Math.round(area.width * scale));
        const height = Math.max(1, Math.round(area.height * scale));

        const canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = height;

        const ctx = canvas.getContext('2d');

        if (!ctx) {
            throw new Error('Recadrage indisponible sur ce navigateur.');
        }

        const kx = width / area.width;
        const ky = height / area.height;

        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, width, height);
        ctx.drawImage(
            img,
            sx,
            sy,
            sw,
            sh,
            (sx - area.x) * kx,
            (sy - area.y) * ky,
            sw * kx,
            sh * ky,
        );

        for (const quality of QUALITIES) {
            const blob = await toBlob(canvas, quality);

            if (blob && blob.size <= MEDIA_LIMITS.productImage.maxBytes) {
                return new File([blob], `${baseName}.jpg`, {
                    type: 'image/jpeg',
                });
            }
        }

        scale *= 0.85;
    }

    throw new Error("Impossible de compresser l'image sous 1 Mo.");
}
