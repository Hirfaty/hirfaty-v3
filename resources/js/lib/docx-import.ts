import {
    EDITOR_IMAGE_TYPES,
    MAX_EDITOR_IMAGE_BYTES,
    uploadEditorImage,
} from '@/lib/editor-upload';

/** Generous for text, still stops someone dropping a 200 Mo scan by mistake. */
export const MAX_DOCX_BYTES = 20 * 1024 * 1024;

export interface ImportedDocx {
    /** The document's opening heading, when it starts with one. */
    title: string | null;
    /** The body, with that opening heading already taken out. */
    html: string;
    /** Images that could not come across (unsupported format, too heavy, upload failed). */
    skippedImages: number;
}

/**
 * Only Word's Title style needs mapping: mammoth's own defaults already turn
 * every spelling of Heading 1–6 (style name or id, any Word language) into
 * h1–h6, and those are shifted down after conversion.
 */
const STYLE_MAP = [
    "p[style-name='Title'] => h1.docx-title:fresh",
    "p[style-name='title'] => h1.docx-title:fresh",
    "p[style-name='Subtitle'] => p:fresh",
    "p[style-name='subtitle'] => p:fresh",
];

/**
 * Word heading level to article heading level. The article's own title field
 * is the page's single h1 and the editor only allows h2–h4 in the body, so
 * Heading 1 becomes h2 and everything from Heading 3 down collapses to h4.
 */
const HEADING_SHIFT: Record<string, string> = {
    H1: 'h2',
    H2: 'h3',
    H3: 'h4',
    H4: 'h4',
    H5: 'h4',
    H6: 'h4',
};

/**
 * Turn a .docx into article HTML the editor can load.
 *
 * Runs entirely in the browser (mammoth, loaded on demand so it never weighs
 * on the rest of the admin). Embedded images are uploaded one by one to the
 * same endpoint the editor uses for a dropped image, so the article ends up
 * with real URLs rather than megabytes of base64 in its content column.
 */
export async function importDocx(
    file: File,
    uploadUrl: string,
): Promise<ImportedDocx> {
    const { default: mammoth } = await import('mammoth');
    let skippedImages = 0;

    const result = await mammoth.convertToHtml(
        { arrayBuffer: await file.arrayBuffer() },
        {
            styleMap: STYLE_MAP,
            ignoreEmptyParagraphs: true,
            convertImage: mammoth.images.imgElement(async (image) => {
                // EMF/WMF/TIFF are common in Word files and no browser renders
                // them, so they are dropped rather than uploaded broken.
                if (!EDITOR_IMAGE_TYPES.includes(image.contentType)) {
                    skippedImages++;

                    return { src: '' };
                }

                const extension = image.contentType.split('/')[1];
                const upload = new File(
                    [await image.readAsArrayBuffer()],
                    `word-image.${extension}`,
                    { type: image.contentType },
                );

                if (upload.size > MAX_EDITOR_IMAGE_BYTES) {
                    skippedImages++;

                    return { src: '' };
                }

                try {
                    return { src: await uploadEditorImage(uploadUrl, upload) };
                } catch {
                    skippedImages++;

                    return { src: '' };
                }
            }),
        },
    );

    const doc = new DOMParser().parseFromString(result.value, 'text/html');
    const body = doc.body;

    body.querySelectorAll('img').forEach((img) => {
        if (!img.getAttribute('src')) {
            img.remove();
        }
    });

    // Word puts each picture in a paragraph of its own. The editor's image is
    // a block, so it would split that paragraph and leave an empty line above.
    body.querySelectorAll('p').forEach((paragraph) => {
        const onlyImages =
            paragraph.textContent?.trim() === '' &&
            paragraph.children.length > 0 &&
            Array.from(paragraph.children).every((c) => c.tagName === 'IMG');

        if (onlyImages) {
            paragraph.replaceWith(...Array.from(paragraph.children));
        } else if (
            paragraph.textContent?.trim() === '' &&
            paragraph.children.length === 0
        ) {
            // Emptied by a dropped image.
            paragraph.remove();
        }
    });

    // The opening heading becomes the article title — a Word document almost
    // always starts with its own, whether styled Title or Heading 1.
    let title: string | null = null;
    const first = body.firstElementChild;

    if (first?.tagName === 'H1') {
        title = first.textContent?.trim() || null;
        first.remove();
    }

    body.querySelectorAll('h1, h2, h3, h4, h5, h6').forEach((heading) => {
        // A further Title-styled paragraph is a section heading in all but name.
        const level = heading.classList.contains('docx-title')
            ? 'h2'
            : HEADING_SHIFT[heading.tagName];
        const shifted = doc.createElement(level);
        shifted.innerHTML = heading.innerHTML;
        heading.replaceWith(shifted);
    });

    return { title, html: body.innerHTML, skippedImages };
}
