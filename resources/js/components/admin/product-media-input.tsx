import { GripVertical, ImagePlus, Star, Trash2, Video } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { useCropQueue } from '@/hooks/use-crop-queue';
import { validateMediaFile } from '@/lib/media-limits';

/** Media already stored on the product (edit screen only). */
export interface ExistingMedia {
    id: number;
    url: string;
    type: 'image' | 'video';
}

/**
 * One tile in the gallery. Existing media is identified by id; newly picked
 * files are identified by a client-side key because they have no id until save.
 */
export type MediaItem =
    | {
          kind: 'existing';
          key: string;
          id: number;
          url: string;
          isVideo: boolean;
      }
    | { kind: 'new'; key: string; file: File; url: string; isVideo: boolean };

interface Props {
    items: MediaItem[];
    onChange: (items: MediaItem[]) => void;
    max?: number;
    error?: string;
}

let uid = 0;
const nextKey = () => `m${++uid}`;

export function buildExistingItems(media: ExistingMedia[]): MediaItem[] {
    return media.map((m) => ({
        kind: 'existing' as const,
        key: `e${m.id}`,
        id: m.id,
        url: m.url,
        isVideo: m.type === 'video',
    }));
}

/**
 * Gallery picker with thumbnail previews and drag-to-reorder.
 *
 * Order matters: the first tile is the product's main image, which is why the
 * list is explicitly sortable rather than relying on upload order.
 */
export default function ProductMediaInput({
    items,
    onChange,
    max = 10,
    error,
}: Props) {
    const inputRef = useRef<HTMLInputElement>(null);
    // The dragged key lives in a ref, not state: `drop` must read it
    // synchronously, and a state update may not have flushed by then.
    const dragKeyRef = useRef<string | null>(null);
    const [dragKey, setDragKey] = useState<string | null>(null);
    const [overKey, setOverKey] = useState<string | null>(null);
    // Files rejected client-side (too large, wrong type, over resolution) —
    // separate from `error`, which is the server's response to a submit.
    const [rejections, setRejections] = useState<string[]>([]);

    // Object URLs for picked files must be released or the tab leaks memory.
    const createdUrls = useRef<string[]>([]);
    useEffect(() => {
        const urls = createdUrls.current;

        return () => urls.forEach((u) => URL.revokeObjectURL(u));
    }, []);

    const toItem = (file: File): MediaItem => {
        const url = URL.createObjectURL(file);
        createdUrls.current.push(url);

        return {
            kind: 'new',
            key: nextKey(),
            file,
            url,
            isVideo: file.type.startsWith('video'),
        };
    };

    // Photos are framed and compressed under 1 MB before they join the
    // gallery; videos skip the crop and are added straight away below.
    const { enqueue: enqueueCrop, dialog: cropDialog } = useCropQueue(
        (cropped) => onChange([...items, ...cropped.map(toItem)]),
    );

    const addFiles = async (fileList: FileList | null) => {
        if (!fileList) {
            return;
        }

        const room = max - items.length;
        const picked = Array.from(fileList).slice(0, Math.max(room, 0));

        const results = await Promise.all(
            picked.map(async (file) => ({
                file,
                rejection: await validateMediaFile(file, { willCrop: true }),
            })),
        );

        setRejections(
            results.filter((r) => r.rejection).map((r) => r.rejection!),
        );

        const accepted = results.filter((r) => !r.rejection).map((r) => r.file);
        const videos = accepted.filter((f) => f.type.startsWith('video'));

        if (videos.length > 0) {
            onChange([...items, ...videos.map(toItem)]);
        }

        enqueueCrop(accepted.filter((f) => !f.type.startsWith('video')));
    };

    const remove = (key: string) => {
        const target = items.find((i) => i.key === key);

        if (target?.kind === 'new') {
            URL.revokeObjectURL(target.url);
        }

        onChange(items.filter((i) => i.key !== key));
    };

    const endDrag = () => {
        dragKeyRef.current = null;
        setDragKey(null);
        setOverKey(null);
    };

    /** Move the dragged tile so it lands at the hovered tile's index. */
    const drop = (targetKey: string) => {
        const source = dragKeyRef.current;

        if (!source || source === targetKey) {
            endDrag();

            return;
        }

        const from = items.findIndex((i) => i.key === source);
        const to = items.findIndex((i) => i.key === targetKey);

        if (from === -1 || to === -1) {
            endDrag();

            return;
        }

        const next = [...items];
        const [moved] = next.splice(from, 1);
        next.splice(to, 0, moved);

        onChange(next);
        endDrag();
    };

    const full = items.length >= max;

    return (
        <div>
            <div className="mb-3 flex items-center justify-between">
                <div>
                    <p className="text-sm font-medium text-ink">
                        Images &amp; vidéos
                    </p>
                    <p className="mt-0.5 text-xs text-ink-soft">
                        Glissez pour réordonner — la première image est le
                        visuel principal. Images recadrées et compressées sous 1
                        Mo, vidéos 50 Mo max.
                    </p>
                </div>
                <span className="text-xs text-ink-soft">
                    {items.length}/{max}
                </span>
            </div>

            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
                {items.map((item, index) => (
                    <div
                        key={item.key}
                        draggable
                        onDragStart={() => {
                            dragKeyRef.current = item.key;
                            setDragKey(item.key);
                        }}
                        onDragOver={(e) => {
                            e.preventDefault();
                            setOverKey(item.key);
                        }}
                        onDragLeave={() =>
                            setOverKey((k) => (k === item.key ? null : k))
                        }
                        onDrop={(e) => {
                            e.preventDefault();
                            drop(item.key);
                        }}
                        onDragEnd={endDrag}
                        className={`group relative aspect-square overflow-hidden rounded-xl border bg-muted/40 transition ${
                            overKey === item.key
                                ? 'border-ink ring-2 ring-ink/20'
                                : 'border-border'
                        } ${dragKey === item.key ? 'opacity-40' : ''}`}
                    >
                        {item.isVideo ? (
                            <div className="flex h-full w-full flex-col items-center justify-center gap-1 text-ink-soft">
                                <Video className="h-6 w-6" />
                                <span className="px-2 text-center text-[10px]">
                                    Vidéo
                                </span>
                            </div>
                        ) : (
                            <img
                                src={item.url}
                                alt=""
                                className="h-full w-full object-cover"
                            />
                        )}

                        {/* Main-image badge */}
                        {index === 0 && (
                            <span className="absolute top-1.5 left-1.5 inline-flex items-center gap-1 rounded-md bg-ink/85 px-1.5 py-0.5 text-[10px] font-medium text-white">
                                <Star className="h-3 w-3" /> Principale
                            </span>
                        )}

                        <span className="absolute top-1.5 right-1.5 cursor-grab rounded-md bg-ink/70 p-1 text-white opacity-0 transition group-hover:opacity-100">
                            <GripVertical className="h-3.5 w-3.5" />
                        </span>

                        <button
                            type="button"
                            onClick={() => remove(item.key)}
                            aria-label="Retirer le média"
                            className="absolute right-1.5 bottom-1.5 rounded-md bg-white/90 p-1.5 text-red-600 opacity-0 shadow-sm transition group-hover:opacity-100 hover:bg-white"
                        >
                            <Trash2 className="h-3.5 w-3.5" />
                        </button>
                    </div>
                ))}

                {!full && (
                    <button
                        type="button"
                        onClick={() => inputRef.current?.click()}
                        className="flex aspect-square flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-border text-ink-soft transition hover:border-ink/40 hover:bg-muted/40 hover:text-ink"
                    >
                        <ImagePlus className="h-6 w-6" />
                        <span className="text-xs font-medium">Ajouter</span>
                    </button>
                )}
            </div>

            <input
                ref={inputRef}
                type="file"
                multiple
                accept="image/*,video/*"
                className="hidden"
                onChange={(e) => {
                    addFiles(e.target.files);
                    // Reset so picking the same file twice still fires change.
                    e.target.value = '';
                }}
            />

            {rejections.length > 0 && (
                <ul className="mt-2 space-y-0.5">
                    {rejections.map((message) => (
                        <li key={message} className="text-xs text-red-600">
                            {message}
                        </li>
                    ))}
                </ul>
            )}

            {error && <p className="mt-2 text-xs text-red-600">{error}</p>}

            {cropDialog}
        </div>
    );
}
