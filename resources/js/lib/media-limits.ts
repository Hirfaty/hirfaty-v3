/**
 * Client-side mirror of config/media.php. This exists purely so the admin
 * gets instant feedback and never uploads a doomed file — the backend rule
 * (App\Rules\ValidProductMedia, and Admin\UserController's logo/banner
 * validation) is still the enforced source of truth. Keep these numbers in
 * sync with config/media.php by hand; there's no shared codegen between
 * PHP config and TypeScript here.
 */
export const MEDIA_LIMITS = {
    image: {
        maxBytes: 5 * 1024 * 1024, // 5 MB
        maxWidth: 4096,
        maxHeight: 4096,
    },
    video: {
        maxBytes: 50 * 1024 * 1024, // 50 MB
    },
} as const;

const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];
const VIDEO_EXTENSIONS = ['mp4', 'mov'];

function isVideoFile(file: File): boolean {
    if (file.type) {
        return file.type.startsWith('video/');
    }

    const ext = file.name.split('.').pop()?.toLowerCase() ?? '';

    return VIDEO_EXTENSIONS.includes(ext);
}

function isImageFile(file: File): boolean {
    if (file.type) {
        return file.type.startsWith('image/');
    }

    const ext = file.name.split('.').pop()?.toLowerCase() ?? '';

    return IMAGE_EXTENSIONS.includes(ext);
}

function formatMb(bytes: number): string {
    return `${(bytes / (1024 * 1024)).toFixed(1)} Mo`;
}

/** Reads an image file's pixel dimensions via a throwaway object URL. */
function readImageDimensions(
    file: File,
): Promise<{ width: number; height: number } | null> {
    return new Promise((resolve) => {
        const url = URL.createObjectURL(file);
        const img = new Image();

        img.onload = () => {
            URL.revokeObjectURL(url);
            resolve({ width: img.naturalWidth, height: img.naturalHeight });
        };
        img.onerror = () => {
            URL.revokeObjectURL(url);
            resolve(null);
        };
        img.src = url;
    });
}

/**
 * Validates one picked file against the size/dimension limits, mirroring
 * App\Rules\ValidProductMedia. Returns null when the file is fine, or a
 * French error message ready to show the admin.
 */
export async function validateMediaFile(file: File): Promise<string | null> {
    if (isImageFile(file)) {
        if (file.size > MEDIA_LIMITS.image.maxBytes) {
            return `« ${file.name} » dépasse ${formatMb(MEDIA_LIMITS.image.maxBytes)}.`;
        }

        const dimensions = await readImageDimensions(file);

        if (
            dimensions &&
            (dimensions.width > MEDIA_LIMITS.image.maxWidth ||
                dimensions.height > MEDIA_LIMITS.image.maxHeight)
        ) {
            return `« ${file.name} » dépasse la résolution maximale de ${MEDIA_LIMITS.image.maxWidth}x${MEDIA_LIMITS.image.maxHeight}px.`;
        }

        return null;
    }

    if (isVideoFile(file)) {
        if (file.size > MEDIA_LIMITS.video.maxBytes) {
            return `« ${file.name} » dépasse ${formatMb(MEDIA_LIMITS.video.maxBytes)}.`;
        }

        return null;
    }

    return `« ${file.name} » n'est pas un type de fichier supporté.`;
}
