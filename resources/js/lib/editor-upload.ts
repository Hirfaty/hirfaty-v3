/** Largest image Admin\BlogController::uploadImage accepts (its `max:4096`). */
export const MAX_EDITOR_IMAGE_BYTES = 4 * 1024 * 1024;

/** Formats Admin\BlogController::uploadImage accepts (its `mimes:` rule). */
export const EDITOR_IMAGE_TYPES = [
    'image/jpeg',
    'image/png',
    'image/webp',
    'image/gif',
];

/** Laravel accepts the session CSRF token as an X-XSRF-TOKEN header. */
function csrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

/**
 * Send one image to the editor's upload endpoint and resolve with its public
 * URL. Throws on any failure, leaving the caller to decide what to tell the
 * admin.
 */
export async function uploadEditorImage(
    uploadUrl: string,
    file: File,
): Promise<string> {
    const body = new FormData();
    body.append('image', file);

    const response = await fetch(uploadUrl, {
        method: 'POST',
        body,
        headers: {
            'X-XSRF-TOKEN': csrfToken(),
            Accept: 'application/json',
        },
    });

    if (!response.ok) {
        throw new Error(String(response.status));
    }

    const { url } = (await response.json()) as { url: string };

    return url;
}
