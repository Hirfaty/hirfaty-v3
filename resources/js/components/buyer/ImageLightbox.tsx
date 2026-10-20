import * as DialogPrimitive from '@radix-ui/react-dialog';
import { ChevronLeft, ChevronRight, X } from 'lucide-react';
import type { KeyboardEvent } from 'react';
import { useTranslation } from '@/hooks/use-translation';

const ZOOM_CURSOR_SVG =
    "<svg xmlns='http://www.w3.org/2000/svg' width='44' height='44' viewBox='0 0 44 44'>" +
    "<circle cx='22' cy='22' r='20' fill='white' fill-opacity='0.92' stroke='black' stroke-opacity='0.12'/>" +
    "<path d='M22 14v16M14 22h16' stroke='#2b211b' stroke-width='1.8' stroke-linecap='round'/>" +
    '</svg>';

/**
 * The cursor over an image that opens full screen: a white disc with a plus.
 * Falls back to the browser's own zoom-in where custom cursors are refused.
 */
export const ZOOM_CURSOR = `url("data:image/svg+xml,${encodeURIComponent(ZOOM_CURSOR_SVG)}") 22 22, zoom-in`;

const roundButton =
    'flex items-center justify-center rounded-full transition focus-visible:ring-2 focus-visible:ring-souk-title focus-visible:outline-none';

/**
 * A product's photographs, one at a time, as large as the screen allows —
 * on white, with previous / close / next floating at the bottom.
 *
 * `index` null means closed. Arrow keys move between photographs (mirrored
 * in Arabic, where "next" is to the left), Escape closes.
 */
export default function ImageLightbox({
    images,
    index,
    alt,
    onIndexChange,
    onClose,
}: {
    images: { id: number | string; url: string }[];
    index: number | null;
    alt: string;
    onIndexChange: (index: number) => void;
    onClose: () => void;
}) {
    const { t } = useTranslation();
    const open = index !== null && images.length > 0;
    const current = open ? Math.min(index, images.length - 1) : 0;
    const many = images.length > 1;

    const go = (step: number) =>
        onIndexChange((current + step + images.length) % images.length);

    const onKeyDown = (event: KeyboardEvent) => {
        if (
            !many ||
            (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight')
        ) {
            return;
        }

        const rtl = document.documentElement.dir === 'rtl';
        const forward = (event.key === 'ArrowRight') !== rtl;

        event.preventDefault();
        go(forward ? 1 : -1);
    };

    return (
        <DialogPrimitive.Root
            open={open}
            onOpenChange={(next) => !next && onClose()}
        >
            <DialogPrimitive.Portal>
                <DialogPrimitive.Content
                    onKeyDown={onKeyDown}
                    aria-describedby={undefined}
                    className="fixed inset-0 z-[100] bg-white duration-200 outline-none data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:animate-in data-[state=open]:fade-in-0"
                >
                    <DialogPrimitive.Title className="sr-only">
                        {alt}
                    </DialogPrimitive.Title>

                    {/* The white around the photograph closes it, like the
                        button does. */}
                    <div
                        className="flex h-full w-full items-center justify-center"
                        onClick={(event) =>
                            event.target === event.currentTarget && onClose()
                        }
                    >
                        {open && (
                            <img
                                key={images[current].id}
                                src={images[current].url}
                                alt={alt}
                                className="h-full max-h-screen w-auto max-w-full object-contain"
                            />
                        )}
                    </div>

                    <div className="fixed inset-x-0 bottom-8 flex items-center justify-center gap-6">
                        {many && (
                            <button
                                type="button"
                                onClick={() => go(-1)}
                                aria-label={t('buyer.detail.lightbox_previous')}
                                className={`${roundButton} h-14 w-14 bg-white text-souk-title shadow-md hover:bg-souk-paper`}
                            >
                                <ChevronLeft
                                    className="h-5 w-5 rtl:rotate-180"
                                    strokeWidth={1.75}
                                />
                            </button>
                        )}
                        <DialogPrimitive.Close
                            aria-label={t('buyer.detail.lightbox_close')}
                            className={`${roundButton} h-16 w-16 bg-white/70 text-souk-title shadow-sm backdrop-blur hover:bg-white`}
                        >
                            <X className="h-6 w-6" strokeWidth={1.5} />
                        </DialogPrimitive.Close>
                        {many && (
                            <button
                                type="button"
                                onClick={() => go(1)}
                                aria-label={t('buyer.detail.lightbox_next')}
                                className={`${roundButton} h-14 w-14 bg-white text-souk-title shadow-md hover:bg-souk-paper`}
                            >
                                <ChevronRight
                                    className="h-5 w-5 rtl:rotate-180"
                                    strokeWidth={1.75}
                                />
                            </button>
                        )}
                    </div>
                </DialogPrimitive.Content>
            </DialogPrimitive.Portal>
        </DialogPrimitive.Root>
    );
}
