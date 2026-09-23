import { useRef, useState } from 'react';
import type { ReactNode } from 'react';
import ImageCropDialog from '@/components/admin/image-crop-dialog';

/**
 * Walks a batch of picked images through the crop dialog one by one and hands
 * the cropped results to `onCropped`. Render `dialog` anywhere in the tree;
 * call `enqueue` with the files that should be cropped.
 *
 * The chosen ratio is remembered across batches, since a product's photos
 * usually share one format.
 */
export function useCropQueue(onCropped: (files: File[]) => void) {
    // Each file travels with its preview URL, made when it is queued (an event
    // handler) and revoked when its turn ends.
    const [queue, setQueue] = useState<{ file: File; url: string }[]>([]);
    const [aspect, setAspect] = useState(1);
    // Results are flushed once, when the queue empties, so the parent gets a
    // single onChange — successive calls would each spread the same stale
    // list and drop all but the last photo.
    const done = useRef<File[]>([]);
    const [total, setTotal] = useState(0);

    const enqueue = (files: File[]) => {
        if (files.length === 0) {
            return;
        }

        done.current = [];
        setTotal(files.length);
        setQueue(
            files.map((file) => ({ file, url: URL.createObjectURL(file) })),
        );
    };

    const advance = (cropped?: File) => {
        if (cropped) {
            done.current.push(cropped);
        }

        URL.revokeObjectURL(queue[0].url);

        const rest = queue.slice(1);
        setQueue(rest);

        if (rest.length === 0 && done.current.length > 0) {
            onCropped(done.current);
            done.current = [];
        }
    };

    const current = queue[0];

    const dialog: ReactNode = current ? (
        <ImageCropDialog
            key={`${total}-${queue.length}`}
            file={current.file}
            url={current.url}
            progress={
                total > 1 ? `${total - queue.length + 1} / ${total}` : undefined
            }
            aspect={aspect}
            onAspectChange={setAspect}
            onCancel={() => advance()}
            onConfirm={advance}
        />
    ) : null;

    return { enqueue, dialog };
}
