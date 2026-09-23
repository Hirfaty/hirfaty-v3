import type { Area } from 'react-easy-crop';
import { MEDIA_LIMITS } from '@/lib/media-limits';

/** Longest side of a cropped product photo — plenty for a product page. */
const MAX_SIDE = 2048;

const QUALITIES = [0.92, 0.85, 0.78, 0.7, 0.6];

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
 */
export async function cropImageToFile(
    url: string,
    area: Area,
    originalName: string,
): Promise<File> {
    const img = await loadImage(url);
    const baseName = originalName.replace(/\.[^.]+$/, '') || 'image';
    let scale = Math.min(1, MAX_SIDE / Math.max(area.width, area.height));

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

        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, width, height);
        ctx.drawImage(
            img,
            area.x,
            area.y,
            area.width,
            area.height,
            0,
            0,
            width,
            height,
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
